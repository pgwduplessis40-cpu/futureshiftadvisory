<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\BusinessPlan;
use App\Models\EntrepreneurProfile;
use App\Services\Entrepreneurs\BudgetPackBuilder;
use App\Services\Entrepreneurs\BusinessPlanPreviewRenderer;
use App\Services\Entrepreneurs\FunderReadyBusinessPlanBuilder;
use App\Services\Pdf\PdfRenderer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class EntrepreneurPlanDocumentController extends Controller
{
    public function __construct(
        private readonly BusinessPlanPreviewRenderer $planPreview,
        private readonly FunderReadyBusinessPlanBuilder $funderReadyPlans,
        private readonly BudgetPackBuilder $budgetPack,
        private readonly PdfRenderer $pdf,
    ) {}

    public function latestPlanPreview(EntrepreneurProfile $entrepreneurProfile): Response
    {
        Gate::authorize('view', $entrepreneurProfile);

        $businessPlan = $this->latestEntrepreneurPlan($entrepreneurProfile);
        abort_unless($businessPlan instanceof BusinessPlan, 404);

        return $this->planPreviewResponse($entrepreneurProfile, $businessPlan);
    }

    public function planPreview(EntrepreneurProfile $entrepreneurProfile, BusinessPlan $businessPlan): Response
    {
        Gate::authorize('view', $entrepreneurProfile);
        $this->assertPlanBelongsToProfile($businessPlan, $entrepreneurProfile);

        return $this->planPreviewResponse($entrepreneurProfile, $businessPlan);
    }

    public function latestBudgetPackPdf(EntrepreneurProfile $entrepreneurProfile): Response
    {
        Gate::authorize('view', $entrepreneurProfile);

        $businessPlan = $this->latestEntrepreneurPlan($entrepreneurProfile);
        abort_unless($businessPlan instanceof BusinessPlan, 404);
        abort_unless($this->planPreview->budgetUnlocked($businessPlan), 404);

        return $this->budgetPackPdfResponse($entrepreneurProfile, $businessPlan);
    }

    public function budgetPackPdf(EntrepreneurProfile $entrepreneurProfile, BusinessPlan $businessPlan): Response
    {
        Gate::authorize('view', $entrepreneurProfile);
        $this->assertPlanBelongsToProfile($businessPlan, $entrepreneurProfile);
        abort_unless($this->planPreview->budgetUnlocked($businessPlan), 404);

        return $this->budgetPackPdfResponse($entrepreneurProfile, $businessPlan);
    }

    public function funderReadyPlanPdf(EntrepreneurProfile $entrepreneurProfile, BusinessPlan $businessPlan): Response
    {
        Gate::authorize('view', $entrepreneurProfile);
        $this->assertPlanBelongsToProfile($businessPlan, $entrepreneurProfile);

        $pdf = $this->funderReadyPlans->pdf($entrepreneurProfile, $businessPlan);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->funderReadyPlans->filename($entrepreneurProfile).'"',
        ]);
    }

    private function planPreviewResponse(EntrepreneurProfile $entrepreneurProfile, BusinessPlan $businessPlan): Response
    {
        $pdf = $this->planPreview->pdf($entrepreneurProfile, $businessPlan);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->planPreview->filename($entrepreneurProfile).'"',
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }

    private function budgetPackPdfResponse(EntrepreneurProfile $entrepreneurProfile, BusinessPlan $businessPlan): Response
    {
        try {
            $pdf = $this->pdf->render($this->budgetPack->html($entrepreneurProfile, $businessPlan));
        } catch (Throwable $exception) {
            report($exception);
            $pdf = $this->budgetPack->fallbackPdf($entrepreneurProfile, $businessPlan);
        }
        $filename = Str::slug($entrepreneurProfile->name ?: 'entrepreneur').'-budget-pack.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }

    private function assertPlanBelongsToProfile(BusinessPlan $businessPlan, EntrepreneurProfile $entrepreneurProfile): void
    {
        abort_unless(
            $businessPlan->source_type === BusinessPlan::SOURCE_ENTREPRENEUR
            && (string) $businessPlan->entrepreneur_profile_id === (string) $entrepreneurProfile->getKey(),
            404,
        );
    }

    private function latestEntrepreneurPlan(EntrepreneurProfile $entrepreneurProfile): ?BusinessPlan
    {
        return $entrepreneurProfile->businessPlans()
            ->where('source_type', BusinessPlan::SOURCE_ENTREPRENEUR)
            ->latest('updated_at')
            ->latest('created_at')
            ->first();
    }
}
