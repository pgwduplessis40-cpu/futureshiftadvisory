<?php

declare(strict_types=1);

namespace App\Services\Dd;

use App\Enums\EngagementType;
use App\Enums\QuestionnaireSet;
use App\Models\Client;
use App\Models\ConflictDeclaration;
use App\Models\DdEngagement;
use App\Models\Questionnaire;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Conflicts\ConflictDeclarer;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class DdOnboarding
{
    public function __construct(private readonly AuditWriter $audit) {}

    /**
     * @param  array<string, mixed>  $targetDetails
     */
    public function start(Client $buyer, User $advisor, ConflictDeclaration $conflict, string $targetName, array $targetDetails): DdEngagement
    {
        if ($buyer->engagement_type !== EngagementType::DUE_DILIGENCE) {
            throw new InvalidArgumentException('Due diligence onboarding requires a DD engagement-type client.');
        }

        $this->assertConflict($buyer, $advisor, $conflict);
        $questionnaire = $this->ddQuestionnaire();

        return DB::transaction(function () use ($buyer, $advisor, $conflict, $targetName, $targetDetails, $questionnaire): DdEngagement {
            $engagement = DdEngagement::query()->create([
                'client_id' => $buyer->getKey(),
                'target_name' => $targetName,
                'target_details' => $this->targetDetails($targetDetails),
                'status' => DdEngagement::STATUS_IN_PROGRESS,
                'recommendation' => null,
                'conflict_declaration_id' => $conflict->getKey(),
                'created_by_user_id' => $advisor->getKey(),
                'disclaimer_acknowledged_at' => now(),
            ]);

            $this->audit->record('dd.engagement_started', subject: $engagement, actor: $advisor, after: [
                'client_id' => $buyer->getKey(),
                'target_name' => $targetName,
                'questionnaire_id' => $questionnaire->getKey(),
                'questionnaire_set' => QuestionnaireSet::DUE_DILIGENCE->value,
                'standard_advisory_deferred' => true,
                'liability_disclaimer_acknowledged' => true,
            ]);

            return $engagement->refresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function targetPanel(DdEngagement $engagement): array
    {
        $questionnaire = $this->ddQuestionnaire();

        return [
            'id' => $engagement->id,
            'status' => $engagement->status,
            'target_name' => $engagement->target_name,
            'target_details' => $engagement->target_details ?? [],
            'questionnaire' => [
                'id' => $questionnaire->id,
                'set' => QuestionnaireSet::DUE_DILIGENCE->value,
                'title' => $questionnaire->title,
            ],
            'standard_advisory_deferred' => true,
            'liability_disclaimer' => DdDisclaimer::STANDARD,
            'disclaimer_acknowledged_at' => $engagement->disclaimer_acknowledged_at?->toIso8601String(),
            'acquisition_target_tab' => true,
        ];
    }

    private function assertConflict(Client $buyer, User $advisor, ConflictDeclaration $conflict): void
    {
        if ((string) $conflict->client_id !== (string) $buyer->getKey()
            || (string) $conflict->advisor_id !== (string) $advisor->getKey()
            || $conflict->referralType() !== ConflictDeclarer::DUE_DILIGENCE
            || ! $conflict->isFreshFor(ConflictDeclarer::FRESH_FOR_DAYS)) {
            throw new InvalidArgumentException('A fresh due diligence conflict declaration is required before DD onboarding.');
        }
    }

    private function ddQuestionnaire(): Questionnaire
    {
        $questionnaire = Questionnaire::query()
            ->forSet(QuestionnaireSet::DUE_DILIGENCE)
            ->published()
            ->latest('published_at')
            ->first();

        if (! $questionnaire instanceof Questionnaire) {
            throw new InvalidArgumentException('The DD-specific questionnaire has not been published.');
        }

        return $questionnaire;
    }

    /**
     * @param  array<string, mixed>  $targetDetails
     * @return array<string, mixed>
     */
    private function targetDetails(array $targetDetails): array
    {
        return [
            'nzbn' => $targetDetails['nzbn'] ?? null,
            'vendor_name' => $targetDetails['vendor_name'] ?? null,
            'industry' => $targetDetails['industry'] ?? null,
            'asking_price' => $targetDetails['asking_price'] ?? null,
            'notes' => $targetDetails['notes'] ?? null,
            'data_scope' => 'acquisition_target_only',
        ];
    }
}
