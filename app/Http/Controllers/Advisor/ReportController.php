<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Enums\NpoEngagementSubType;
use App\Enums\ReportType;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\NpoEngagement;
use App\Models\Report;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Reports\ReportComposer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class ReportController extends Controller
{
    public function store(Request $request, Client $client, ReportComposer $reports): RedirectResponse
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
                ReportType::GovernanceReview->value,
            ])],
        ]);

        $type = ReportType::from((string) $validated['type']);
        if ($type === ReportType::GovernanceReview) {
            $engagement = NpoEngagement::query()
                ->where('client_id', $client->getKey())
                ->where('sub_type', NpoEngagementSubType::GovernanceReview->value)
                ->latest()
                ->first();

            abort_unless($engagement instanceof NpoEngagement, 404);

            $reports->composeGovernanceReview($engagement, $user);
        } else {
            $reports->compose($client, $type, $user);
        }

        return to_route('advisor.clients.show', $client)->with('status', 'report-generated');
    }

    public function download(Request $request, Report $report, AuditWriter $audit): Response
    {
        return $this->streamReport($request, $report, $audit, 'pdf');
    }

    public function downloadPptx(Request $request, Report $report, AuditWriter $audit): Response
    {
        return $this->streamReport($request, $report, $audit, 'pptx');
    }

    private function streamReport(Request $request, Report $report, AuditWriter $audit, string $format): Response
    {
        $report->loadMissing('client');
        Gate::authorize('view', $report->client);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $path = $format === 'pptx' ? $report->pptx_path : $report->pdf_path;
        $disk = Storage::disk('secure_local');

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
        $filename = Str::slug($report->type->value.'-'.($report->client?->legal_name ?? 'report')).'.'.$extension;
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

        $reports->markReviewed($report, $user);

        return to_route('advisor.clients.show', $report->client)->with('status', 'report-reviewed');
    }
}
