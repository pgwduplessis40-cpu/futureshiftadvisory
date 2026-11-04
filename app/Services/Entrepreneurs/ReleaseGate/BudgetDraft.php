<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

use App\Models\BusinessPlan;
use App\Models\EntrepreneurProfile;
use App\Services\Entrepreneurs\BudgetPackBuilder;
use App\Services\Pdf\PdfRenderer;
use Throwable;

final readonly class BudgetDraft
{
    public function __construct(private DraftDelivery $delivery, private BudgetPackBuilder $pack, private PdfRenderer $renderer) {}

    public function pdf(EntrepreneurProfile $profile, BusinessPlan $plan): string
    {
        return $this->delivery->render($plan, 'budget', function () use ($profile, $plan): RenderedDraft {
            try {
                $html = $this->pack->html($profile, $plan);

                return new RenderedDraft($this->renderer->render($html), 'primary', $html);
            } catch (Throwable $exception) {
                report($exception);

                return new RenderedDraft($this->pack->fallbackPdf($profile, $plan), 'fallback');
            }
        });
    }
}
