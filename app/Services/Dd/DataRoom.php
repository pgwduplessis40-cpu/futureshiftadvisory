<?php

declare(strict_types=1);

namespace App\Services\Dd;

use App\Enums\ProposalStatus;
use App\Models\DdDataRoomItem;
use App\Models\DdEngagement;
use App\Models\DdGuestLink;
use App\Models\Document;
use App\Models\Proposal;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Storage\Exceptions\InfectedFileException;
use App\Services\Storage\SecureFileWriter;
use App\Support\RequestContext;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class DataRoom
{
    public const WORKSTREAMS = [
        'financial' => 'Financial',
        'valuation' => 'Valuation',
        'legal' => 'Legal',
        'tax' => 'Tax',
        'commercial_market' => 'Commercial / Market',
        'operational' => 'Operational',
        'hr_people' => 'HR / People',
        'nz_regulatory' => 'NZ Regulatory',
    ];

    public function __construct(
        private readonly SecureFileWriter $files,
        private readonly AuditWriter $audit,
        private readonly RequestContext $context,
    ) {}

    /**
     * @return array{link:DdGuestLink, token:string, upload_url:string}
     */
    public function issueGuestLink(
        DdEngagement $engagement,
        User $actor,
        string $workstream,
        ?string $folder = null,
        ?CarbonInterface $expiresAt = null,
        ?string $guestEmail = null,
        ?int $maxUploads = null,
    ): array {
        $this->assertActivated($engagement, $actor);

        $workstream = $this->normaliseWorkstream($workstream);
        $folder = $this->normaliseFolder($folder);
        $token = Str::random(48);

        $link = DdGuestLink::query()->create([
            'client_id' => $engagement->client_id,
            'dd_engagement_id' => $engagement->getKey(),
            'workstream' => $workstream,
            'folder' => $folder,
            'token_hash' => self::hashToken($token),
            'guest_email' => $guestEmail,
            'max_uploads' => $maxUploads,
            'upload_count' => 0,
            'created_by_user_id' => $actor->getKey(),
            'expires_at' => $expiresAt ?? now()->addDays(14),
        ]);

        $this->audit->record('dd.guest_link_created', subject: $link, actor: $actor, after: [
            'dd_engagement_id' => $engagement->getKey(),
            'workstream' => $workstream,
            'folder' => $folder,
            'expires_at' => $link->expires_at?->toIso8601String(),
            'max_uploads' => $maxUploads,
            'token_type' => 'upload_only',
        ]);

        return [
            'link' => $link,
            'token' => $token,
            'upload_url' => route('dd.guest-uploads.store', ['token' => $token], absolute: false),
        ];
    }

    public function revokeGuestLink(DdGuestLink $link, User $actor): DdGuestLink
    {
        if ($link->revoked_at === null) {
            $link->forceFill([
                'revoked_at' => now(),
                'revoked_by_user_id' => $actor->getKey(),
            ])->save();

            $this->audit->record('dd.guest_link_revoked', subject: $link, actor: $actor, after: [
                'dd_engagement_id' => $link->dd_engagement_id,
                'workstream' => $link->workstream,
                'folder' => $link->folder,
            ]);
        }

        return $link->refresh();
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function uploadViaGuestToken(
        string $token,
        UploadedFile $file,
        ?string $guestName = null,
        ?string $guestEmail = null,
        array $metadata = [],
    ): DdDataRoomItem {
        $this->context->apply('system', []);

        $link = $this->resolveUsableLink($token);
        $engagement = $link->engagement;

        if ($engagement instanceof DdEngagement) {
            $this->assertActivated($engagement);
        }

        try {
            $document = $this->files->write(
                uploadedFile: $file,
                owner: null,
                category: Document::CATEGORY_DD_ARTIFACT,
                clientId: (string) $link->client_id,
            );
        } catch (InfectedFileException $e) {
            $this->audit->record('dd.guest_upload_rejected', subject: $link, context: [
                'reason' => 'infected',
                'scanner' => $e->scanResult->toPayload(),
                'workstream' => $link->workstream,
                'folder' => $link->folder,
            ]);

            throw $e;
        }

        if ($document->scanner_result !== Document::SCANNER_CLEAN) {
            $this->audit->record('dd.guest_upload_quarantined', subject: $link, context: [
                'document_id' => $document->getKey(),
                'scanner_result' => $document->scanner_result,
                'workstream' => $link->workstream,
                'folder' => $link->folder,
            ]);
        }

        return DB::transaction(function () use ($link, $document, $guestName, $guestEmail, $metadata): DdDataRoomItem {
            $item = DdDataRoomItem::query()->create([
                'client_id' => $link->client_id,
                'dd_engagement_id' => $link->dd_engagement_id,
                'document_id' => $document->getKey(),
                'workstream' => $link->workstream,
                'folder' => $link->folder,
                'artifact_type' => DdDataRoomItem::ARTIFACT_TYPE,
                'source' => DdDataRoomItem::SOURCE_GUEST_UPLOAD,
                'dd_guest_link_id' => $link->getKey(),
                'guest_name' => $guestName,
                'guest_email' => $guestEmail,
                'metadata' => $metadata,
            ]);

            $link->forceFill([
                'upload_count' => $link->upload_count + 1,
                'last_used_at' => now(),
            ])->save();

            $this->audit->record('dd.guest_upload_received', subject: $item, after: [
                'dd_engagement_id' => $link->dd_engagement_id,
                'document_id' => $document->getKey(),
                'workstream' => $link->workstream,
                'folder' => $link->folder,
                'artifact_type' => DdDataRoomItem::ARTIFACT_TYPE,
                'scanner_result' => $document->scanner_result,
                'token_type' => 'upload_only',
            ]);

            return $item->load('document', 'guestLink');
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(DdEngagement $engagement): array
    {
        $items = DdDataRoomItem::query()
            ->where('dd_engagement_id', $engagement->getKey())
            ->get()
            ->groupBy('workstream');

        $activeLinks = DdGuestLink::query()
            ->where('dd_engagement_id', $engagement->getKey())
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->get()
            ->groupBy('workstream');

        return [
            'activation' => $this->activationStatus($engagement),
            'artifact_category' => Document::CATEGORY_DD_ARTIFACT,
            'guest_upload_only' => true,
            'workstreams' => collect(self::WORKSTREAMS)
                ->map(function (string $label, string $key) use ($items, $activeLinks): array {
                    $workstreamItems = $items->get($key, collect());
                    $latestItem = $workstreamItems->sortByDesc('created_at')->first();

                    return [
                        'key' => $key,
                        'label' => $label,
                        'item_count' => $workstreamItems->count(),
                        'active_guest_links' => $activeLinks->get($key)?->count() ?? 0,
                        'latest_item_at' => $latestItem?->created_at?->toIso8601String(),
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{active:bool, proposal_id:?string, proposal_status:?string, signed_at:?string, message:string}
     */
    public function activationStatus(DdEngagement $engagement): array
    {
        $signed = $this->signedProposal($engagement);
        $latest = $this->latestProposal($engagement);

        if ($signed instanceof Proposal) {
            return [
                'active' => true,
                'proposal_id' => $signed->id,
                'proposal_status' => ProposalStatus::Signed->value,
                'signed_at' => $signed->signed_at?->toIso8601String(),
                'message' => 'DD data room is active because the due diligence fee proposal is signed.',
            ];
        }

        $status = $latest?->status;
        $statusValue = $status instanceof ProposalStatus ? $status->value : (is_string($status) ? $status : null);

        return [
            'active' => false,
            'proposal_id' => $latest?->id,
            'proposal_status' => $statusValue,
            'signed_at' => null,
            'message' => 'DD data room opens after the client signs the due diligence fee proposal.',
        ];
    }

    public function assertActivated(DdEngagement $engagement, ?User $actor = null): void
    {
        if ($this->signedProposal($engagement) instanceof Proposal) {
            return;
        }

        $status = $this->activationStatus($engagement);

        $this->audit->record('dd.data_room_activation_blocked', subject: $engagement, actor: $actor, after: [
            'client_id' => $engagement->client_id,
            'proposal_id' => $status['proposal_id'],
            'proposal_status' => $status['proposal_status'],
        ]);

        throw ValidationException::withMessages([
            'proposal' => $status['message'],
        ]);
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function resolveUsableLink(string $token): DdGuestLink
    {
        $link = DdGuestLink::query()
            ->with('engagement')
            ->where('token_hash', self::hashToken($token))
            ->first();

        if (! $link instanceof DdGuestLink) {
            $this->audit->record('dd.guest_upload_rejected', context: [
                'reason' => 'invalid_token',
            ]);

            throw ValidationException::withMessages([
                'token' => 'This upload link is no longer active.',
            ]);
        }

        if (! $link->isUsable()) {
            $this->audit->record('dd.guest_upload_rejected', subject: $link, context: [
                'reason' => 'inactive_token',
                'revoked_at' => $link->revoked_at?->toIso8601String(),
                'expires_at' => $link->expires_at?->toIso8601String(),
                'upload_count' => $link->upload_count,
                'max_uploads' => $link->max_uploads,
            ]);

            throw ValidationException::withMessages([
                'token' => 'This upload link is no longer active.',
            ]);
        }

        return $link;
    }

    private function signedProposal(DdEngagement $engagement): ?Proposal
    {
        return Proposal::query()
            ->where('client_id', $engagement->client_id)
            ->where('status', ProposalStatus::Signed->value)
            ->latest('signed_at')
            ->latest()
            ->first();
    }

    private function latestProposal(DdEngagement $engagement): ?Proposal
    {
        return Proposal::query()
            ->where('client_id', $engagement->client_id)
            ->latest()
            ->first();
    }

    private function normaliseWorkstream(string $workstream): string
    {
        $normalised = Str::of($workstream)->lower()->replace(['-', ' '], '_')->value();

        if (! array_key_exists($normalised, self::WORKSTREAMS)) {
            throw new InvalidArgumentException("Unknown DD workstream [{$workstream}].");
        }

        return $normalised;
    }

    private function normaliseFolder(?string $folder): string
    {
        $normalised = Str::slug($folder ?: 'general', '_');

        return $normalised === '' ? 'general' : Str::limit($normalised, 160, '');
    }
}
