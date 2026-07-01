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
        $clientName = e((string) ($client?->legal_name ?: $client?->trading_name ?: 'Client'));
        $tradingName = e((string) ($client?->trading_name ?: $client?->legal_name ?: ''));
        $tradingSuffix = $this->tradingSuffix($clientName, $tradingName);
        $title = e((string) ($plan->title ?: 'Strategic Plan'));
        $status = e(Str::of($plan->status)->replace('_', ' ')->title()->toString());
        $generated = e($plan->generated_at?->format('j M Y') ?? '-');
        $deployed = e($plan->deployed_at?->format('j M Y') ?? '-');
        $proposal = e($plan->proposal?->version ? 'Proposal v'.$plan->proposal->version : 'Accepted proposal');
        $budgetScore = data_get($plan->strategicBudget?->confidence, 'score');
        $budget = e(is_numeric($budgetScore) ? ((string) ((int) $budgetScore)).'/100' : '-');
        $summary = $this->richText((string) ($plan->summary ?? ''));
        $sections = $this->sectionsHtml($plan);
        $milestones = $this->milestonesHtml($plan);

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{$title}</title>
    <style>
        @page { margin: 32px; }
        body { color: #111827; font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; line-height: 1.45; }
        .rule { border-top: 6px solid #2f6f61; margin-bottom: 18px; }
        .brand { color: #2f6f61; font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        h1 { font-size: 25px; line-height: 1.15; margin: 4px 0 4px; }
        h2 { color: #164e43; font-size: 15px; margin: 0 0 8px; }
        h3 { font-size: 12px; margin: 0 0 4px; }
        p { margin: 0 0 8px; }
        .muted { color: #6b7280; }
        .meta { background: #f8fafc; border-bottom: 1px solid #dbe3ea; border-top: 1px solid #dbe3ea; display: table; margin: 18px 0; width: 100%; }
        .meta div { display: table-cell; padding: 10px 12px; width: 20%; }
        .label { color: #6b7280; display: block; font-size: 9px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
        .block { border-left: 4px solid #2f6f61; margin: 0 0 14px; padding: 0 0 4px 12px; page-break-inside: avoid; }
        .summary { background: #f8fafc; margin-bottom: 14px; padding: 12px; }
        table { border-collapse: collapse; margin-top: 8px; width: 100%; }
        th { background: #f8fafc; color: #374151; font-size: 10px; text-align: left; text-transform: uppercase; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 8px; vertical-align: top; }
        .badge { background: #eef2f7; border-radius: 999px; display: inline-block; font-size: 10px; padding: 2px 7px; }
        footer { border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 9px; margin-top: 22px; padding-top: 8px; text-align: right; }
    </style>
</head>
<body>
    <div class="rule"></div>
    <div class="brand">Future Shift Advisory</div>
    <h1>{$title}</h1>
    <p class="muted">{$clientName}{$tradingSuffix}</p>

    <div class="meta">
        <div><span class="label">Status</span>{$status}</div>
        <div><span class="label">Generated</span>{$generated}</div>
        <div><span class="label">Deployed</span>{$deployed}</div>
        <div><span class="label">Proposal</span>{$proposal}</div>
        <div><span class="label">Budget readiness</span>{$budget}</div>
    </div>

    <section class="summary">
        <h2>Summary</h2>
        {$summary}
    </section>

    {$sections}

    <section class="block">
        <h2>Milestone Tracker</h2>
        <table>
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
    </section>

    <footer>Generated using the strategic plan draft in Future Shift Advisory</footer>
</body>
</html>
HTML;
    }

    private function sectionsHtml(StrategicPlan $plan): string
    {
        $sections = collect((array) ($plan->sections ?? []))
            ->filter(fn (mixed $section): bool => is_array($section))
            ->map(fn (array $section): string => sprintf(
                '<section class="block"><h2>%s</h2>%s</section>',
                e((string) ($section['title'] ?? 'Plan section')),
                $this->richText((string) ($section['body'] ?? '')),
            ))
            ->implode('');

        return $sections !== '' ? $sections : '<section class="block"><h2>Plan sections</h2><p class="muted">No strategic plan sections recorded.</p></section>';
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
                    <td><strong>{$title}</strong><br><span class="muted">{$description}</span></td>
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
