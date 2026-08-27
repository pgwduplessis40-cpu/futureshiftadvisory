<?php

declare(strict_types=1);

namespace App\Services\Clients;

use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\InviteToken;
use App\Models\ServiceActivation;
use App\Models\User;
use App\Services\Dashboards\EconomicExposureMapper;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Typed Inertia contract for the advisor client index.
 *
 * Query filters, scoped client selection and invitation lifecycle data are
 * assembled here, leaving the controller to authorize and render the route.
 */
final class AdvisorClientIndexPayloadBuilder
{
    public function __construct(private readonly AdvisorClientPayloadBuilder $clientPayloads) {}

    /**
     * @return array{
     *     clients:list<array{id:string,engagement_type:string,engagement_type_label:string,is_npo:bool,status:string,status_label:string,account_status:string,account_status_label:string,nzbn:?string,legal_name:?string,trading_name:?string,entity_type:?string,gst_registered:bool,filing_status:?string,data_quality:string}>,
     *     engagementFilter:?array{key:string,label:string,description:string,clear_url:string},
     *     exposureFilter:?array{key:string,label:string,exposed_count:int,unknown_count:int,clear_url:string},
     *     showAdvisorAssignments:bool,
     *     allocationUrl:?string,
     *     transferRequestUrl:?string
     * }
     */
    public function build(Request $request, EconomicExposureMapper $economicExposure): array
    {
        $engagementType = $this->stringQuery($request, 'engagement_type');
        $exposedTo = $this->stringQuery($request, 'exposed_to');
        $engagementFilter = null;
        $exposureFilter = null;
        $user = $request->user();
        $showAdvisorAssignments = $user instanceof User && $user->user_type === User::TYPE_SUPER_ADMIN;
        $isAdvisor = $user instanceof User && in_array($user->user_type, [User::TYPE_ADVISOR, User::TYPE_JUNIOR_ADVISOR], true);
        $clientIds = $isAdvisor ? $user->accessibleClientIds() : null;
        $query = Client::query()
            ->withoutOperationalHealthFixtures()
            ->latest();

        if ($showAdvisorAssignments) {
            $query->with([
                'teamMembers' => fn ($teamMembers) => $teamMembers
                    ->whereIn('role', ['lead_advisor', 'advisor'])
                    ->with(['user:id,name', 'advisorTeam:id,name']),
            ]);
        }

        if (is_array($clientIds)) {
            $clientIds === []
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('id', $clientIds);
        }

        if ($engagementType !== null && $engagementType !== '') {
            $engagement = EngagementType::tryFrom($engagementType);
            abort_unless($engagement instanceof EngagementType, 404);

            if ($engagement === EngagementType::DUE_DILIGENCE) {
                $query->where(function ($clientQuery) use ($engagement): void {
                    $clientQuery
                        ->where('engagement_type', $engagement->value)
                        ->orWhereHas('serviceActivations', function ($activationQuery): void {
                            $activationQuery
                                ->where('service_type', ServiceActivation::SERVICE_DUE_DILIGENCE)
                                ->where('status', ServiceActivation::STATUS_ACTIVE)
                                ->whereNotNull('related_dd_engagement_id');
                        });
                });
            } else {
                $query->where('engagement_type', $engagement->value);
            }

            $engagementFilter = [
                'key' => $engagement->value,
                'label' => $this->engagementLabel($engagement),
                'description' => $engagement->description(),
                'clear_url' => $this->indexUrl($request, ['engagement_type']),
            ];
        }

        if ($exposedTo !== null && $exposedTo !== '') {
            abort_unless(in_array($exposedTo, $economicExposure->supportedFilterKeys(), true), 404);

            $exposure = $economicExposure->forKey($exposedTo, $clientIds);
            $query->whereIn('id', $exposure['client_ids']);
            $exposureFilter = [
                'key' => $exposure['key'],
                'label' => $exposure['label'],
                'exposed_count' => $exposure['exposed_count'],
                'unknown_count' => $exposure['unknown_count'],
                'clear_url' => $this->indexUrl($request, ['exposed_to']),
            ];
        }

        $clients = $query->limit(100)->get();
        $invites = InviteToken::query()
            ->whereIn('id', $clients
                ->map(fn (Client $client): ?string => $this->clientPayloads->tokenId($client))
                ->filter()
                ->values()
                ->all())
            ->get()
            ->keyBy(fn (InviteToken $invite): string => (string) $invite->getKey());
        $activatedInviteEmails = User::query()
            ->whereIn('user_type', [User::TYPE_CLIENT_PRIMARY, User::TYPE_CLIENT_TEAM])
            ->whereIn('email', $clients
                ->map(fn (Client $client): string => $this->clientPayloads->inviteEmail($client))
                ->filter()
                ->values()
                ->all())
            ->pluck('email')
            ->map(fn (string $email): string => Str::lower(trim($email)))
            ->flip();

        return [
            'clients' => $clients
                ->map(fn (Client $client): array => $this->clientPayloads->summary(
                    $client,
                    $showAdvisorAssignments,
                    $invites->get($this->clientPayloads->tokenId($client)),
                    $activatedInviteEmails->has($this->clientPayloads->inviteEmail($client)),
                ))
                ->values()
                ->all(),
            'engagementFilter' => $engagementFilter,
            'exposureFilter' => $exposureFilter,
            'showAdvisorAssignments' => $showAdvisorAssignments,
            'allocationUrl' => $showAdvisorAssignments
                ? route('admin.client-allocations.index', absolute: false)
                : null,
            'transferRequestUrl' => $isAdvisor
                ? route('advisor.client-transfers.index', absolute: false)
                : null,
        ];
    }

    private function stringQuery(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) ? trim($value) : null;
    }

    private function engagementLabel(EngagementType $engagement): string
    {
        return match ($engagement) {
            EngagementType::STANDARD_ADVISORY => 'Advisory',
            EngagementType::NPO => 'NPOs',
            default => $engagement->label(),
        };
    }

    /**
     * @param  list<string>  $without
     */
    private function indexUrl(Request $request, array $without = []): string
    {
        $query = $request->query();

        foreach ($without as $key) {
            unset($query[$key]);
        }

        $query = array_filter(
            $query,
            static fn (mixed $value): bool => is_scalar($value) && trim((string) $value) !== '',
        );

        $url = route('advisor.clients.index', absolute: false);

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }
}
