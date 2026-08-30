<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\BusinessPlan;
use App\Models\EntrepreneurProfile;
use App\Services\Entrepreneurs\BudgetPackBuilder;
use App\Services\Entrepreneurs\BusinessPlanPreviewRenderer;
use App\Services\Pdf\PdfRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

final class EntrepreneurPlanDocumentController extends Controller
{
    public function __construct(
        private readonly EntrepreneurPlanWorkspace $workspace,
        private readonly EntrepreneurPlanRequirements $requirements,
        private readonly BusinessPlanPreviewRenderer $planPreview,
        private readonly BudgetPackBuilder $budgetPack,
        private readonly PdfRenderer $pdf,
    ) {}

    public function preview(Request $request): SymfonyResponse
    {
        $profile = $this->profileFor($request);
        abort_unless($this->workspace->includesPlanBudget($profile), 403);
        $plan = $this->workspace->latestPlan($profile);
        $pdf = $this->planPreview->pdf($profile, $plan);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->planPreview->filename($profile).'"',
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }

    public function budgetPack(Request $request): Response|RedirectResponse
    {
        $profile = $this->profileFor($request);
        abort_unless($this->workspace->includesPlanBudget($profile), 403);
        $plan = $this->workspace->latestPlan($profile);
        abort_unless($plan instanceof BusinessPlan, 404);

        if (! $this->requirements->budgetUnlocked($plan)) {
            return to_route('portal.entrepreneur.plan.show')
                ->with('status', 'entrepreneur-budget-locked')
                ->with('entrepreneur_plan_error', 'Complete Foundation: Business type, location, and operating model, plus Financial: Financial assumptions before viewing the budget pack.');
        }

        return Inertia::render('portal/entrepreneur/BudgetPack', [
            'pack' => $this->budgetPack->payload($profile, $plan),
            'urls' => [
                'plan' => route('portal.entrepreneur.plan.show', absolute: false),
                'pdf' => route('portal.entrepreneur.plan.budget-pack.pdf', absolute: false),
            ],
        ]);
    }

    public function budgetPackPdf(Request $request): SymfonyResponse
    {
        $profile = $this->profileFor($request);
        abort_unless($this->workspace->includesPlanBudget($profile), 403);
        $plan = $this->workspace->latestPlan($profile);
        abort_unless($plan instanceof BusinessPlan && $this->requirements->budgetUnlocked($plan), 404);

        try {
            $pdf = $this->pdf->render($this->budgetPack->html($profile, $plan));
        } catch (Throwable $exception) {
            report($exception);
            $pdf = $this->budgetPack->fallbackPdf($profile, $plan);
        }
        $filename = Str::slug($profile->name ?: 'entrepreneur').'-budget-pack.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }

    private function profileFor(Request $request): EntrepreneurProfile
    {
        return $this->workspace->profileFor($this->workspace->user($request));
    }
}
