<?php

declare(strict_types=1);

namespace App\Services\Templates;

use App\Models\LearningUpdate;
use App\Models\LearningUpdateImplementation;
use App\Models\Template;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Learning\ApprovalFlow;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class TemplateImplementer
{
    public function __construct(
        private readonly ApprovalFlow $approvalFlow,
        private readonly AuditWriter $audit,
        private readonly TemplateActivationService $templateActivation,
    ) {}

    public function implement(LearningUpdate $update, ?User $actor = null): LearningUpdateImplementation
    {
        return DB::transaction(function () use ($update, $actor): LearningUpdateImplementation {
            /** @var LearningUpdate $lockedUpdate */
            $lockedUpdate = LearningUpdate::query()
                ->whereKey($update->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->approvalFlow->assertImplementationAllowed($lockedUpdate);

            $templateId = $this->templateId($lockedUpdate);

            /** @var Template $template */
            $template = Template::query()
                ->whereKey($templateId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($template->status === Template::STATUS_ACTIVE && $template->learning_update_implementation_id !== null) {
                return LearningUpdateImplementation::query()
                    ->whereKey($template->learning_update_implementation_id)
                    ->firstOrFail();
            }

            if ($template->status !== Template::STATUS_DRAFT) {
                throw new RuntimeException('Template learning updates can only activate draft templates.');
            }

            /** @var LearningUpdateImplementation $implementation */
            $implementation = LearningUpdateImplementation::query()->create([
                'learning_update_id' => $lockedUpdate->getKey(),
                'implemented_at' => now(),
                'review_due' => $lockedUpdate->review_due_at ?? now()->addDays(30),
                'target_type' => Template::class,
                'target_id' => $template->getKey(),
                'before_state' => [
                    'status' => Template::STATUS_DRAFT,
                    'learning_update_implementation_id' => null,
                ],
                'after_state' => [
                    'status' => Template::STATUS_ACTIVE,
                    'learning_update_implementation_id' => null,
                ],
            ]);

            $template->forceFill([
                'status' => Template::STATUS_ACTIVE,
                'learning_update_implementation_id' => $implementation->getKey(),
            ])->save();

            $this->templateActivation->archiveOverlappingActiveReportTemplates($template);

            $implementation->forceFill([
                'after_state' => [
                    'status' => Template::STATUS_ACTIVE,
                    'learning_update_implementation_id' => $implementation->getKey(),
                ],
            ])->save();

            $lockedUpdate->forceFill([
                'status' => LearningUpdate::STATUS_IMPLEMENTED,
            ])->save();

            $this->audit->record('template.activated_from_learning_update', subject: $template, actor: $actor, before: [
                'status' => Template::STATUS_DRAFT,
                'learning_update_implementation_id' => null,
            ], after: [
                'status' => Template::STATUS_ACTIVE,
                'learning_update_implementation_id' => $implementation->getKey(),
                'learning_update_id' => $lockedUpdate->getKey(),
            ]);

            return $implementation->refresh();
        });
    }

    private function templateId(LearningUpdate $update): string
    {
        $templateId = data_get($update->proposed_change, 'template_id')
            ?? data_get($update->evidence, 'template_id');

        if (! is_string($templateId) || trim($templateId) === '') {
            throw new RuntimeException('Template learning update is missing a target template.');
        }

        return $templateId;
    }
}
