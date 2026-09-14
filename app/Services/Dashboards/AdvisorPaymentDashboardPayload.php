<?php

declare(strict_types=1);

namespace App\Services\Dashboards;

use App\Models\User;
use App\Services\Payments\IdeaValidationPaymentReconciliationService;

/**
 * Composes payment-state and exceptional reconciliation work for the advisor
 * dashboard without disclosing reconciliation evidence to ordinary advisors.
 */
final class AdvisorPaymentDashboardPayload
{
    public function __construct(
        private readonly PaymentStatusReport $paymentStatus,
        private readonly IdeaValidationPaymentReconciliationService $paymentReconciliations,
    ) {}

    /**
     * @param  array<int, string>|null  $clientIds
     * @return array{paymentStatus:array{summary:array{failed:int,retrying:int,retryable:int},items:list<array{id:string,client_id:string,client_name:string|null,client_url:string,status:string,amount:float,currency:string,processed_at:string|null,failed_reason:string|null,attempt:int,automatic_next_retry_at:string|null,manual_retry_available:bool,retry_url:string,drill_url:string,contact_url:string}>},paymentReconciliationQueue:array{available:bool,total:int,action_url:string|null,action_label:string}}
     */
    public function for(User $user, ?array $clientIds): array
    {
        return [
            'paymentStatus' => $this->paymentStatus->forClientIds($clientIds),
            'paymentReconciliationQueue' => $this->reconciliationQueue($user),
        ];
    }

    /**
     * @return array{available:bool,total:int,action_url:string|null,action_label:string}
     */
    private function reconciliationQueue(User $user): array
    {
        if ($user->user_type !== User::TYPE_SUPER_ADMIN) {
            return [
                'available' => false,
                'total' => 0,
                'action_url' => null,
                'action_label' => 'Payment reconciliations',
            ];
        }

        return [
            'available' => true,
            'total' => $this->paymentReconciliations->candidates()->count(),
            'action_url' => route('admin.payment-reconciliations.index', absolute: false),
            'action_label' => 'Review payments',
        ];
    }
}
