<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EngagementType;
use App\Enums\FeeMethod;
use App\Enums\ProposalStatus;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\FeeCalculation;
use App\Models\Proposal;
use App\Models\StrategicBudget;
use App\Models\User;
use App\Services\Proposals\ProposalBuilder;
use App\Services\StrategicPlans\StrategicPlanService;
use App\Support\RequestContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class StrategicPlanDurationDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);

        $advisor = $this->user(
            email: 'duration-demo.advisor@example.test',
            name: 'Duration Demo Advisor',
            userType: User::TYPE_ADVISOR,
        );
        $clientUser = $this->user(
            email: 'duration-demo.client@example.test',
            name: 'Duration Demo Client',
            userType: User::TYPE_CLIENT_PRIMARY,
        );

        $client = Client::query()->updateOrCreate([
            'nzbn' => '9429000012124',
        ], [
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'legal_name' => 'Duration Strategy Demo Limited',
            'trading_name' => 'Duration Strategy Demo',
            'data_quality' => Client::DATA_QUALITY_MEDIUM,
            'created_by_user_id' => $advisor->getKey(),
            'primary_contact_user_id' => $clientUser->getKey(),
        ]);

        $this->teamMember($client, $advisor, 'lead_advisor');
        $this->teamMember($client, $clientUser, 'primary_contact');

        $standard = $this->feeCalculation(
            client: $client,
            key: 'duration-demo-standard-12',
            mid: 10_000,
            roi: 2.7,
            complexity: 'standard',
            multiplier: 1.0,
            service: '12-month advisory plan'
        );
        $complex = $this->feeCalculation(
            client: $client,
            key: 'duration-demo-complex-24',
            mid: 22_000,
            roi: 3.4,
            complexity: 'high',
            multiplier: 1.25,
            service: '24-month strategic change programme'
        );
        $transformational = $this->feeCalculation(
            client: $client,
            key: 'duration-demo-transformational-36',
            mid: 46_000,
            roi: 4.1,
            complexity: 'very_high',
            multiplier: 1.5,
            service: '36-month transformation plan'
        );

        $this->budget($client, $advisor);

        $builder = app(ProposalBuilder::class);
        $signedProposal = $this->proposalForCalculation(
            builder: $builder,
            client: $client,
            calculation: $transformational,
            advisor: $advisor,
            summary: 'Transformational advisory proposal for testing a 36-month strategic plan duration.',
        );
        $this->signProposal($signedProposal, $clientUser);
        app(StrategicPlanService::class)->generateForProposal($signedProposal->refresh(), $advisor);

        $this->proposalForCalculation(
            builder: $builder,
            client: $client,
            calculation: $complex,
            advisor: $advisor,
            summary: 'Released proposal for testing a 24-month strategic plan duration in client sign-off.',
        );

        $this->command?->info('Strategic plan duration demo seeded.');
        $this->command?->line('Advisor login: duration-demo.advisor@example.test / password');
        $this->command?->line('Client login: duration-demo.client@example.test / password');
        $this->command?->line('Client: Duration Strategy Demo Limited');
        $this->command?->line('Pending calculation: '.$standard->id.' (12 months)');
        $this->command?->line('Released proposal calculation: '.$complex->id.' (24 months)');
        $this->command?->line('Signed proposal calculation: '.$transformational->id.' (36 months)');
    }

    private function user(string $email, string $name, string $userType): User
    {
        /** @var User $user */
        $user = User::query()->updateOrCreate([
            'email' => $email,
        ], [
            'name' => $name,
            'password' => Hash::make('password'),
            'user_type' => $userType,
            'primary_role' => $userType,
            'email_verified_at' => now(),
        ]);

        if (method_exists($user, 'hasRole') && ! $user->hasRole($userType)) {
            $user->assignRole($userType);
        }

        return $user;
    }

    private function teamMember(Client $client, User $user, string $role): void
    {
        ClientTeamMember::query()->updateOrCreate([
            'client_id' => $client->getKey(),
            'user_id' => $user->getKey(),
            'role' => $role,
        ], [
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);
    }

    private function feeCalculation(
        Client $client,
        string $key,
        float $mid,
        float $roi,
        string $complexity,
        float $multiplier,
        string $service,
    ): FeeCalculation {
        $calculation = FeeCalculation::query()
            ->where('client_id', $client->getKey())
            ->get()
            ->first(fn (FeeCalculation $candidate): bool => data_get($candidate->justification, 'duration_demo_key') === $key);

        $calculation ??= new FeeCalculation([
            'client_id' => $client->getKey(),
        ]);

        $calculation->forceFill([
            'client_id' => $client->getKey(),
            'method' => FeeMethod::OutcomeBased,
            'inputs' => ['duration_demo_key' => $key],
            'suggested_low' => round($mid * 0.8, 2),
            'suggested_mid' => $mid,
            'suggested_high' => round($mid * 1.2, 2),
            'improvement_pv_total' => round($mid * $roi, 2),
            'risk_cost_pv_total' => round($mid * 0.18, 2),
            'roi_ratio' => $roi,
            'justification' => [
                'duration_demo_key' => $key,
                'basis' => 'Strategic plan duration demo fixture.',
                'complexity' => [
                    'level' => $complexity,
                    'multiplier' => $multiplier,
                ],
                'services' => [
                    ['name' => $service, 'line_total' => $mid],
                ],
            ],
        ])->save();

        return $calculation->refresh();
    }

    private function budget(Client $client, User $advisor): StrategicBudget
    {
        $budget = StrategicBudget::query()
            ->where('client_id', $client->getKey())
            ->where('label', 'Duration demo approved budget')
            ->first();
        $budget ??= new StrategicBudget(['client_id' => $client->getKey()]);

        $budget->forceFill([
            'client_id' => $client->getKey(),
            'pathway' => StrategicBudget::PATHWAY_ADVISORY,
            'label' => 'Duration demo approved budget',
            'status' => StrategicBudget::STATUS_ADVISOR_APPROVED,
            'horizon_months' => 36,
            'business_plan_sections' => [
                [
                    'key' => 'action_priorities',
                    'answer' => 'Focus the plan on decision cadence, owner accountability, and commercial outcomes rather than source-data summaries.',
                ],
                [
                    'key' => 'budget_and_affordability',
                    'answer' => 'Use monthly proposal terms that align with the strategic plan duration and client cash-flow tolerance.',
                ],
            ],
            'confidence' => ['score' => 86],
            'approved_at' => now(),
            'approved_by_user_id' => $advisor->getKey(),
        ])->save();

        return $budget->refresh();
    }

    private function proposalForCalculation(
        ProposalBuilder $builder,
        Client $client,
        FeeCalculation $calculation,
        User $advisor,
        string $summary,
    ): Proposal {
        $proposal = Proposal::query()
            ->where('fee_calculation_id', $calculation->getKey())
            ->first();

        if (! $proposal instanceof Proposal) {
            $proposal = $builder->generate($client, $calculation, [
                'scope' => [
                    'summary' => $summary,
                    'included' => [
                        'Strategic plan and milestone roadmap',
                        'Advisor review rhythm and decision checkpoints',
                        'Commercial outcome tracking',
                    ],
                    'excluded' => [
                        'Third-party software, legal, audit, and implementation vendor fees unless separately agreed.',
                        'Raw source-data appendices beyond decision-ready evidence summaries.',
                    ],
                ],
            ], [
                'created_by_user_id' => $advisor->getKey(),
            ]);
        }

        $proposal = $proposal->refresh();

        if (in_array($proposal->status, [ProposalStatus::Draft, ProposalStatus::Renewed], true)) {
            $proposal = $builder->release($proposal, $advisor);
        }

        if ($proposal->status !== ProposalStatus::Signed && $proposal->status !== ProposalStatus::Released) {
            $proposal->forceFill([
                'status' => ProposalStatus::Released,
                'released_at' => now(),
                'released_by_user_id' => $advisor->getKey(),
                'expires_at' => now()->addDays(30),
                'recalled_at' => null,
                'recalled_by_user_id' => null,
                'expired_at' => null,
            ])->save();
        }

        return $proposal->refresh();
    }

    private function signProposal(Proposal $proposal, User $clientUser): void
    {
        if ($proposal->status === ProposalStatus::Signed) {
            return;
        }

        if ($proposal->status !== ProposalStatus::Released) {
            $proposal->forceFill([
                'status' => ProposalStatus::Released,
                'released_at' => now(),
                'expires_at' => now()->addDays(30),
            ])->save();
        }

        Proposal::allowSignoffStatusTransition(function () use ($proposal, $clientUser): void {
            $proposal->forceFill([
                'status' => ProposalStatus::AwaitingSignature,
                'awaiting_signature_at' => now(),
            ])->save();

            $proposal->forceFill([
                'status' => ProposalStatus::Signed,
                'signed_at' => now(),
                'signed_by_user_id' => $clientUser->getKey(),
            ])->save();
        });
    }
}
