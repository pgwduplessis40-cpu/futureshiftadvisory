<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\EntrepreneurPlanBudgetPurchase;
use App\Models\EntrepreneurProfile;
use App\Models\IdeaValidation;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Support\RequestContext;
use Illuminate\Support\Facades\DB;

/**
 * Activates a paid BP&B add-on only when its Idea Validation has been
 * explicitly passed by an advisor. Both events may arrive in either order.
 */
final class EntrepreneurPlanBudgetProvisioner
{
    public function __construct(
        private readonly ApprovedIdeaPlanStarter $plans,
        private readonly AuditWriter $audit,
        private readonly RequestContext $context,
    ) {}

    public function provision(EntrepreneurProfile $profile, ?User $actor = null): ?BusinessPlan
    {
        return $this->context->withSystemContext(function () use ($profile, $actor): ?BusinessPlan {
            return DB::transaction(function () use ($profile, $actor): ?BusinessPlan {
                $purchase = EntrepreneurPlanBudgetPurchase::query()
                    ->with('user')
                    ->where('entrepreneur_profile_id', $profile->getKey())
                    ->where('status', EntrepreneurPlanBudgetPurchase::STATUS_PAID)
                    ->whereNull('activated_at')
                    ->lockForUpdate()
                    ->first();
                if (! $purchase instanceof EntrepreneurPlanBudgetPurchase) {
                    return null;
                }

                $validation = IdeaValidation::query()
                    ->where('entrepreneur_profile_id', $profile->getKey())
                    ->whereNotNull('advisor_gate_passed_at')
                    ->latest('advisor_gate_passed_at')
                    ->lockForUpdate()
                    ->first();
                if (! $validation instanceof IdeaValidation) {
                    return null;
                }

                $planActor = $actor ?? $purchase->user;
                if (! $planActor instanceof User) {
                    throw new \LogicException('A BP&B purchase cannot be activated without its client user.');
                }

                $plan = $this->plans->start($profile, $validation, $planActor);
                $purchase->forceFill([
                    'approved_idea_validation_id' => $validation->getKey(),
                    'activated_at' => now(),
                    'metadata' => [
                        ...(array) ($purchase->metadata ?? []),
                        'activated_from_idea_validation_revision' => $validation->revision_number,
                    ],
                ])->save();

                $this->audit->record('entrepreneur.plan_budget_purchase_activated', subject: $purchase, actor: $actor, after: [
                    'entrepreneur_profile_id' => $profile->getKey(),
                    'business_plan_id' => $plan->getKey(),
                    'idea_validation_id' => $validation->getKey(),
                    'idea_validation_revision_number' => $validation->revision_number,
                ]);

                return $plan;
            });
        });
    }
}
