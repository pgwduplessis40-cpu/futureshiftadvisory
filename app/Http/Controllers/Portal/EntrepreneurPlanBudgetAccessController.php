<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\IdeaValidation;
use App\Models\EntrepreneurPlanBudgetPurchase;
use App\Models\ServiceRatePackage;
use App\Services\Entrepreneurs\EntrepreneurServiceOffer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class EntrepreneurPlanBudgetAccessController extends Controller
{
    public function __invoke(
        Request $request,
        EntrepreneurPlanWorkspace $workspace,
        EntrepreneurServiceOffer $offers,
    ): Response {
        $user = $workspace->user($request);
        $profile = $workspace->profileFor($user);
        $access = $workspace->packageAccess($profile);
        $ideaValidationApproved = IdeaValidation::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->whereNotNull('advisor_gate_passed_at')
            ->exists();
        $purchase = EntrepreneurPlanBudgetPurchase::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->latest()
            ->first();

        return Inertia::render('portal/entrepreneur/PlanBudgetAccess', [
            'hasPlanBudgetAccess' => $access['includes_plan_budget'],
            'ideaValidationApproved' => $ideaValidationApproved,
            'offer' => $offers->forScope(ServiceRatePackage::SCOPE_ENTREPRENEUR_PLAN_BUDGET),
            'purchase' => $purchase instanceof EntrepreneurPlanBudgetPurchase ? [
                'status' => $purchase->status,
                'amount_ex_gst' => $purchase->amount_ex_gst !== null ? (float) $purchase->amount_ex_gst : null,
                'gst_amount' => $purchase->gst_amount !== null ? (float) $purchase->gst_amount : null,
                'amount_including_gst' => $purchase->amount_including_gst !== null ? (float) $purchase->amount_including_gst : null,
                'currency' => $purchase->currency,
            ] : null,
            'checkoutUrls' => [
                'paymentIntent' => route('portal.entrepreneur.plan-budget.payment-intent', absolute: false),
                'confirmPayment' => route('portal.entrepreneur.plan-budget.confirm-payment', absolute: false),
                'confirmFixturePayment' => route('portal.entrepreneur.plan-budget.confirm-fixture-payment', absolute: false),
            ],
            'workspaceUrl' => route('portal.entrepreneur.plan.show', absolute: false),
        ]);
    }
}
