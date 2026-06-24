<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\EngagementType;
use App\Enums\ReportType;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\EntrepreneurProfile;
use App\Models\Report;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Entrepreneurs\EntrepreneurInviteReconciler;
use App\Services\Portal\ClientPortalResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class ReportController extends Controller
{
    public function __construct(
        private readonly ClientPortalResolver $clients,
        private readonly AuditWriter $audit,
        private readonly EntrepreneurInviteReconciler $entrepreneurInvites,
    ) {}

    public function show(Request $request, Report $report): Response
    {
        $user = $request->user();
        if ($user instanceof User && $user->user_type === User::TYPE_ENTREPRENEUR) {
            return $this->showEntrepreneurReport($request, $report, $user);
        }

        $client = $this->clients->resolveFor($request);

        abort_unless((string) $report->client_id === (string) $client->getKey(), 404);
        abort_unless($this->clientCanView($client, $report), 404);

        $disk = Storage::disk('secure_local');
        abort_if($report->pdf_path === null || ! $disk->exists($report->pdf_path), 404);

        $contents = $disk->get($report->pdf_path);
        abort_if($contents === null, 404);

        if ($user instanceof User) {
            $this->audit->record('portal.report.downloaded', subject: $report, actor: $user, after: [
                'type' => $report->type->value,
            ]);
        }

        $filename = Str::slug($report->type->value.'-'.($client->legal_name ?? 'report')).'.pdf';

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($contents),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    private function showEntrepreneurReport(Request $request, Report $report, User $user): Response
    {
        $this->entrepreneurInvites->reconcile($user);

        $profile = EntrepreneurProfile::query()
            ->where('user_id', $user->getKey())
            ->firstOrFail();

        abort_unless((string) $report->entrepreneur_profile_id === (string) $profile->getKey(), 404);
        abort_unless($this->entrepreneurCanView($report), 404);

        $disk = Storage::disk('secure_local');
        abort_if($report->pdf_path === null || ! $disk->exists($report->pdf_path), 404);

        $contents = $disk->get($report->pdf_path);
        abort_if($contents === null, 404);

        $this->audit->record('portal.report.downloaded', subject: $report, actor: $user, after: [
            'type' => $report->type->value,
            'entrepreneur_profile_id' => $profile->getKey(),
        ]);

        $filename = Str::slug($report->type->value.'-'.($profile->name ?? 'report')).'.pdf';

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($contents),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    private function clientCanView(Client $client, Report $report): bool
    {
        $type = $report->type instanceof ReportType
            ? $report->type
            : ReportType::tryFrom((string) $report->type);

        if ($type === ReportType::Client) {
            return in_array($report->review_status, ['not_required', 'reviewed'], true);
        }

        if ($type === ReportType::PostAcquisitionGap) {
            return $this->engagementType($client) === EngagementType::POST_ACQUISITION_ADVISORY
                && in_array($report->review_status, ['not_required', 'reviewed'], true);
        }

        if ($type === ReportType::DueDiligence) {
            return $this->engagementType($client) === EngagementType::DUE_DILIGENCE
                && in_array($report->review_status, ['not_required', 'reviewed'], true);
        }

        if (! in_array($this->engagementType($client), [EngagementType::NPO], true)) {
            return false;
        }

        if (in_array($type, [ReportType::GovernanceReview, ReportType::NpoHealth, ReportType::SocialEnterpriseDual], true)) {
            return in_array($report->review_status, ['not_required', 'reviewed'], true);
        }

        if (in_array($type, [ReportType::FunderAccountability, ReportType::ImpactSummary], true)) {
            return $report->reviewed();
        }

        return false;
    }

    private function entrepreneurCanView(Report $report): bool
    {
        $type = $report->type instanceof ReportType
            ? $report->type
            : ReportType::tryFrom((string) $report->type);

        return $type === ReportType::EntrepreneurAssessment
            && in_array($report->review_status, ['not_required', 'reviewed'], true);
    }

    private function engagementType(Client $client): ?EngagementType
    {
        return $client->engagement_type instanceof EngagementType
            ? $client->engagement_type
            : EngagementType::tryFrom((string) $client->engagement_type);
    }
}
