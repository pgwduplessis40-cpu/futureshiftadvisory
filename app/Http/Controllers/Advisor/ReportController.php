<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Enums\ReportType;
use App\Http\Controllers\Controller;
use App\Jobs\ComposeReport;
use App\Models\Client;
use App\Models\EntrepreneurProfile;
use App\Models\Report;
use App\Models\ReportSection;
use App\Models\ReportSectionComment;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Reports\ReportComposer;
use App\Services\Reports\ReportSectionEditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class ReportController extends Controller
{
    public function store(Request $request, Client $client): RedirectResponse
    {
        Gate::authorize('view', $client);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'type' => ['required', Rule::in([
                ReportType::Client->value,
                ReportType::Advisor->value,
                ReportType::Stakeholder->value,
                ReportType::Trajectory->value,
                ReportType::Valuation->value,
                ReportType::DueDiligence->value,
                ReportType::AcquisitionGoNoGo->value,
                ReportType::PostAcquisitionGap->value,
                ReportType::SuccessionValueGap->value,
                ReportType::GovernanceReview->value,
                ReportType::NpoHealth->value,
                ReportType::NpoAdvisor->value,
                ReportType::SocialEnterpriseDual->value,
                ReportType::FunderAccountability->value,
                ReportType::ImpactSummary->value,
            ])],
        ]);

        $type = ReportType::from((string) $validated['type']);
        ComposeReport::dispatch((string) $client->getKey(), $type->value, (int) $user->getKey())->afterCommit();

        return to_route('advisor.clients.show', $client)->with('status', 'report-generation-queued');
    }

    public function download(Request $request, Report $report, AuditWriter $audit, ReportComposer $reports): Response
    {
        return $this->streamReport($request, $report, $audit, 'pdf', $reports);
    }

    public function downloadPptx(Request $request, Report $report, AuditWriter $audit): Response
    {
        return $this->streamReport($request, $report, $audit, 'pptx');
    }

    private function streamReport(
        Request $request,
        Report $report,
        AuditWriter $audit,
        string $format,
        ?ReportComposer $reports = null,
    ): Response {
        $report->loadMissing('client', 'entrepreneurProfile');
        if ($report->client instanceof Client) {
            Gate::authorize('view', $report->client);
        } elseif ($report->entrepreneurProfile instanceof EntrepreneurProfile) {
            Gate::authorize('view', $report->entrepreneurProfile);
        } else {
            abort(404);
        }

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $path = $format === 'pptx' ? $report->pptx_path : $report->pdf_path;
        $disk = Storage::disk('secure_local');

        if ($format === 'pdf' && $report->render_status === Report::RENDER_STATUS_COMPOSING) {
            abort(409, 'Report rendering is still in progress.');
        }

        if ($format === 'pdf' && $report->render_status === Report::RENDER_STATUS_FAILED) {
            abort(503, 'Report rendering failed. Please regenerate the report.');
        }

        if ($format === 'pdf' && $reports instanceof ReportComposer && (
            $path === null
            || ! $disk->exists($path)
            || ! $reports->usesCurrentTemplate($report)
        )) {
            $report = $reports->rerenderArtifacts($report);
            $path = $report->pdf_path;
        }

        abort_if($path === null || ! $disk->exists($path), 404);

        $contents = $disk->get($path);
        abort_if($contents === null, 404);

        $audit->record('report.downloaded', subject: $report, actor: $user, after: [
            'type' => $report->type->value,
            'format' => $format,
        ]);

        $extension = $format === 'pptx' ? 'pptx' : 'pdf';
        $mime = $format === 'pptx'
            ? 'application/vnd.openxmlformats-officedocument.presentationml.presentation'
            : 'application/pdf';
        $subjectName = $report->client?->legal_name ?? $report->entrepreneurProfile?->name ?? 'report';
        $filename = Str::slug($report->type->value.'-'.$subjectName).'.'.$extension;
        $disposition = $format === 'pptx' ? 'attachment' : 'inline';

        return response($contents, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($contents),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function review(Request $request, Report $report, ReportComposer $reports): RedirectResponse
    {
        $report->loadMissing('client');
        Gate::authorize('view', $report->client);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        if (! $reports->usesCurrentTemplate($report)) {
            $reports->rerenderArtifacts($report);

            return to_route('advisor.clients.show', $report->client)->with('status', 'report-template-refreshed');
        }

        $reports->markReviewed($report, $user);

        return to_route('advisor.clients.show', $report->client)->with('status', 'report-reviewed');
    }

    public function release(Request $request, Report $report, ReportComposer $reports): RedirectResponse
    {
        $report->loadMissing('client');
        Gate::authorize('view', $report->client);
        abort_unless($report->type === ReportType::Client, 404);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        if (! $reports->usesCurrentTemplate($report)) {
            $report = $reports->rerenderArtifacts($report);
        }

        if (! $report->reviewed()) {
            $report = $reports->markReviewed($report, $user);
        }

        return to_route('advisor.clients.show', $report->client)
            ->with('status', 'client-report-released')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Client report released.',
            ]);
    }

    public function updateSection(
        Request $request,
        Report $report,
        ReportSection $reportSection,
        ReportSectionEditor $editor,
    ): RedirectResponse {
        $report->loadMissing('client', 'entrepreneurProfile');
        if ($report->client instanceof Client) {
            Gate::authorize('view', $report->client);
        } elseif ($report->entrepreneurProfile instanceof EntrepreneurProfile) {
            Gate::authorize('view', $report->entrepreneurProfile);
        } else {
            abort(404);
        }

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:50000'],
            'reason' => ['nullable', 'string', 'max:4000'],
        ]);

        $changes = array_filter([
            'title' => $validated['title'] ?? null,
            'body' => $validated['body'] ?? null,
        ], static fn (?string $value): bool => $value !== null);

        abort_if($changes === [], 422, 'At least one section field is required.');

        $editor->edit($report, $reportSection, $user, $changes, $validated['reason'] ?? null);

        return back()->with('status', 'report-section-updated');
    }

    public function commentSection(
        Request $request,
        Report $report,
        ReportSection $reportSection,
        ReportSectionEditor $editor,
    ): RedirectResponse {
        $report->loadMissing('client', 'entrepreneurProfile');
        if ($report->client instanceof Client) {
            Gate::authorize('view', $report->client);
        } elseif ($report->entrepreneurProfile instanceof EntrepreneurProfile) {
            Gate::authorize('view', $report->entrepreneurProfile);
        } else {
            abort(404);
        }

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
            'visibility' => ['nullable', Rule::in([
                ReportSectionComment::VISIBILITY_ADVISOR_ONLY,
                ReportSectionComment::VISIBILITY_CLIENT_VISIBLE,
            ])],
        ]);

        $editor->comment(
            $report,
            $reportSection,
            $user,
            $validated['body'],
            $validated['visibility'] ?? ReportSectionComment::VISIBILITY_ADVISOR_ONLY,
        );

        return back()->with('status', 'report-section-commented');
    }
}
