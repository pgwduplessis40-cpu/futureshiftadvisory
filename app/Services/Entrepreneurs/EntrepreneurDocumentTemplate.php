<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\Template;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class EntrepreneurDocumentTemplate
{
    public const BUSINESS_PLAN = 'entrepreneur-business-plan';

    public const BUDGET_PACK = 'entrepreneur-budget-pack';

    public function businessPlan(): ?Template
    {
        return $this->for(self::BUSINESS_PLAN, ['business plan', 'entrepreneur plan']);
    }

    public function budgetPack(): ?Template
    {
        return $this->for(self::BUDGET_PACK, ['budget pack', 'entrepreneur budget']);
    }

    private function for(string $documentType, array $titleKeywords): ?Template
    {
        if (! Schema::hasTable('templates')) {
            return null;
        }

        return Template::query()
            ->usable()
            ->whereIn('category', [Template::CATEGORY_REPORT, Template::CATEGORY_OTHER])
            ->get()
            ->filter(function (Template $template) use ($documentType, $titleKeywords): bool {
                $configuredType = data_get($template->structure, 'document_type');
                $configuredTypes = (array) data_get($template->structure, 'document_types', []);

                return $configuredType === $documentType
                    || in_array($documentType, $configuredTypes, true)
                    || Str::contains(Str::lower($template->title), $titleKeywords);
            })
            ->sortByDesc(function (Template $template) use ($documentType): array {
                $configuredType = data_get($template->structure, 'document_type');
                $configuredTypes = (array) data_get($template->structure, 'document_types', []);
                $specificity = $configuredType === $documentType ? 2 : (in_array($documentType, $configuredTypes, true) ? 1 : 0);

                return [$specificity, $template->version, $template->updated_at?->getTimestamp() ?? 0];
            })
            ->first();
    }
}
