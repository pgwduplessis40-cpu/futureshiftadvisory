<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Proposal;
use App\Models\StrategicPlan;
use App\Models\StrategicPlanMilestone;
use App\Models\User;
use App\Services\Pdf\PdfRenderer;
use App\Services\Reports\BrandedReportLayout;
use App\Services\StrategicPlans\StrategicPlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

final class StrategicPlanController extends Controller
{
    public function __construct(
        private readonly StrategicPlanService $plans,
        private readonly PdfRenderer $pdf,
        private readonly BrandedReportLayout $layout,
    ) {}

    public function generate(Request $request, Proposal $proposal): RedirectResponse
    {
        $proposal->loadMissing('client');
        Gate::authorize('view', $proposal->client);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        try {
            $plan = $this->plans->generateForProposal($proposal, $user);
        } catch (InvalidArgumentException $exception) {
            return to_route('advisor.clients.show', $proposal->client)
                ->withErrors(['strategic_plan' => $exception->getMessage()]);
        }

        return to_route('advisor.clients.show', $plan->client_id)
            ->with('status', 'strategic-plan-generated');
    }

    public function update(Request $request, StrategicPlan $strategicPlan): RedirectResponse
    {
        $strategicPlan->loadMissing('client');
        Gate::authorize('view', $strategicPlan->client);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'summary' => ['nullable', 'string', 'max:4000'],
            'sections' => ['array', 'max:8'],
            'sections.*.key' => ['required_with:sections', 'string', 'max:80'],
            'sections.*.title' => ['nullable', 'string', 'max:160'],
            'sections.*.body' => ['nullable', 'string', 'max:8000'],
            'milestones' => ['array', 'max:20'],
            'milestones.*.id' => ['nullable', 'uuid'],
            'milestones.*.title' => ['nullable', 'string', 'max:180'],
            'milestones.*.description' => ['nullable', 'string', 'max:1000'],
            'milestones.*.owner' => ['nullable', 'string', Rule::in(['client', 'advisor', 'joint'])],
            'milestones.*.due_offset_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'milestones.*.status' => ['nullable', 'string', Rule::in(['pending', 'in_progress', 'completed', 'blocked'])],
            'milestones.*.progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'milestones.*.advisor_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->plans->update($strategicPlan, $validated, $user);
        } catch (InvalidArgumentException $exception) {
            return to_route('advisor.clients.show', $strategicPlan->client)
                ->withErrors(['strategic_plan' => $exception->getMessage()]);
        }

        return to_route('advisor.clients.show', $strategicPlan->client)
            ->with('status', 'strategic-plan-saved');
    }

    public function deploy(Request $request, StrategicPlan $strategicPlan): RedirectResponse
    {
        $strategicPlan->loadMissing('client');
        Gate::authorize('view', $strategicPlan->client);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $this->plans->deploy($strategicPlan, $user);

        return to_route('advisor.clients.show', $strategicPlan->client)
            ->with('status', 'strategic-plan-deployed');
    }

    public function pdf(Request $request, StrategicPlan $strategicPlan): Response
    {
        $strategicPlan->loadMissing([
            'client',
            'proposal',
            'strategicBudget',
            'milestones' => fn ($query) => $query
                ->orderBy('due_offset_days')
                ->orderBy('created_at'),
        ]);

        $client = $strategicPlan->client;
        abort_unless($client instanceof Client, 404);
        Gate::authorize('view', $client);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $contents = $this->pdf->render($this->pdfHtml($strategicPlan));
        $filename = Str::slug('strategic-plan-'.($client->legal_name ?: $client->trading_name ?: 'client')).'.pdf';

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($contents),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    private function pdfHtml(StrategicPlan $plan): string
    {
        $client = $plan->client;
        $clientName = (string) ($client?->legal_name ?: $client?->trading_name ?: 'Client');
        $tradingName = (string) ($client?->trading_name ?: $client?->legal_name ?: '');
        $tradingSuffix = $this->tradingSuffix($clientName, $tradingName);
        $title = (string) ($plan->title ?: 'Strategic Plan');
        $status = Str::of((string) ($plan->status ?: StrategicPlan::STATUS_DRAFT))->replace('_', ' ')->title()->toString();
        $generated = $plan->generated_at?->format('j M Y') ?? '-';
        $deployed = $plan->deployed_at?->format('j M Y') ?? '-';
        $proposal = $plan->proposal?->version ? 'Proposal v'.$plan->proposal->version : 'Accepted proposal';
        $budgetScore = data_get($plan->strategicBudget?->confidence, 'score');
        $budget = is_numeric($budgetScore) ? ((string) ((int) $budgetScore)).'/100' : '-';
        $summary = $this->richText((string) ($plan->summary ?? ''));
        $sections = $this->sectionsHtml($plan);
        $milestones = $this->milestonesHtml($plan);
        $documentTag = $plan->status === StrategicPlan::STATUS_DEPLOYED ? 'Deployed strategic plan' : 'Draft strategic plan';

        $contentHtml = <<<HTML
    <article class="report-section plan-summary">
        <h2>Summary</h2>
        {$summary}
    </article>

    <div class="section-grid">
    {$sections}
    </div>

    <article class="report-section milestone-section">
        <h2>Milestone Tracker</h2>
        <table class="milestone-table">
            <thead>
                <tr>
                    <th>Milestone</th>
                    <th>Owner</th>
                    <th>Due</th>
                    <th>Status</th>
                    <th>Progress</th>
                </tr>
            </thead>
            <tbody>
                {$milestones}
            </tbody>
        </table>
    </article>
HTML;

        return $this->layout->document(
            title: $title,
            templateKey: 'strategic-plan',
            documentTag: $documentTag,
            eyebrow: 'Strategic plan',
            heading: $title,
            subheading: $clientName.$tradingSuffix,
            meta: [
                'Status' => $status,
                'Generated' => $generated,
                'Deployed' => $deployed,
                'Proposal' => $proposal,
                'Budget readiness' => $budget,
            ],
            contentHtml: $contentHtml,
            footer: 'Generated using the strategic plan draft in Future Shift Advisory',
            snapshotTitle: 'Plan snapshot',
            metaColumns: 5,
            extraCss: $this->planPdfCss(),
        );
    }

    private function sectionsHtml(StrategicPlan $plan): string
    {
        $sections = collect((array) ($plan->sections ?? []))
            ->filter(fn (mixed $section): bool => is_array($section))
            ->map(fn (array $section): string => sprintf(
                '<article class="report-section plan-section"><h2>%s</h2>%s</article>',
                e((string) ($section['title'] ?? 'Plan section')),
                $this->richText((string) ($section['body'] ?? '')),
            ))
            ->implode('');

        return $sections !== '' ? $sections : '<article class="report-section plan-section"><h2>Plan sections</h2><p class="muted">No strategic plan sections recorded.</p></article>';
    }

    private function milestonesHtml(StrategicPlan $plan): string
    {
        $rows = $plan->milestones
            ->sortBy('due_offset_days')
            ->map(function (StrategicPlanMilestone $milestone): string {
                $title = e($milestone->title);
                $description = e((string) ($milestone->description ?? ''));
                $owner = e(Str::of($milestone->owner)->replace('_', ' ')->title()->toString());
                $due = e($milestone->due_date?->format('j M Y') ?? $milestone->due_offset_days.' days after deployment');
                $status = e(Str::of($milestone->status)->replace('_', ' ')->title()->toString());
                $progress = e(((string) $milestone->progress_percent).'%');

                return <<<HTML
                <tr>
                    <td><strong class="milestone-title">{$title}</strong><br><span class="muted">{$description}</span></td>
                    <td>{$owner}</td>
                    <td>{$due}</td>
                    <td><span class="badge">{$status}</span></td>
                    <td>{$progress}</td>
                </tr>
HTML;
            })
            ->implode('');

        return $rows !== '' ? $rows : '<tr><td colspan="5" class="muted">No milestones recorded.</td></tr>';
    }

    private function planPdfCss(): string
    {
        return <<<'CSS'
.section-grid {
  display: grid;
  gap: 8px;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}
.section-grid .report-section {
  min-height: 58px;
  padding: 8px 10px;
}
.plan-summary {
  background: #fbfaf6;
}
.report-section p {
  margin: 0 0 4px;
}
.report-section p:last-child {
  margin-bottom: 0;
}
.milestone-section {
  padding-bottom: 10px;
}
.milestone-table {
  border-collapse: collapse;
  font-size: 9.5px;
  line-height: 1.35;
  table-layout: fixed;
  width: 100%;
}
.milestone-table th {
  background: #f4efe3;
  border: 0;
  border-bottom: 1px solid #d8d1c2;
  color: #1c2f4a;
  font-size: 8px;
  font-weight: 700;
  padding: 5px 6px;
  text-align: left;
  text-transform: uppercase;
}
.milestone-table th:nth-child(1) { width: 46%; }
.milestone-table th:nth-child(2) { width: 12%; }
.milestone-table th:nth-child(3) { width: 20%; }
.milestone-table th:nth-child(4) { width: 13%; }
.milestone-table th:nth-child(5) { width: 9%; }
.milestone-table td {
  border: 0;
  border-bottom: 1px solid #eee7db;
  overflow-wrap: anywhere;
  padding: 5px 6px;
  text-align: left;
  vertical-align: top;
}
.milestone-table tr:last-child td {
  border-bottom: 0;
}
.milestone-title {
  color: #13233a;
}
.badge {
  background: #f4efe3;
  border: 1px solid #ded6c7;
  border-radius: 999px;
  color: #1c2f4a;
  display: inline-block;
  font-size: 8.5px;
  font-weight: 700;
  padding: 1px 6px;
}
CSS;
    }

    private function richText(string $text): string
    {
        $text = trim($text);

        if ($text === '') {
            return '<p class="muted">No detail recorded.</p>';
        }

        return collect(preg_split('/\R{2,}/', $text) ?: [])
            ->map(fn (string $paragraph): string => '<p>'.nl2br(e(trim($paragraph))).'</p>')
            ->implode('');
    }

    private function tradingSuffix(string $clientName, string $tradingName): string
    {
        if ($tradingName === '' || $tradingName === $clientName) {
            return '';
        }

        return ' / '.$tradingName;
    }
}
