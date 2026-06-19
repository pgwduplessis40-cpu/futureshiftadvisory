<?php

declare(strict_types=1);

namespace App\Services\Templates;

use App\Enums\ReportType;
use App\Models\Template;
use Illuminate\Support\Str;

final class TemplateActivationService
{
    public function archiveOverlappingActiveReportTemplates(Template $template): void
    {
        if ($template->category !== Template::CATEGORY_REPORT || $template->status !== Template::STATUS_ACTIVE) {
            return;
        }

        $activeReportTypes = $this->templateReportTypes($template);

        Template::query()
            ->usable()
            ->where('category', Template::CATEGORY_REPORT)
            ->whereKeyNot($template->getKey())
            ->get()
            ->filter(fn (Template $candidate): bool => $this->reportTypesOverlap(
                $activeReportTypes,
                $this->templateReportTypes($candidate),
            ))
            ->each(function (Template $candidate): void {
                $candidate->forceFill([
                    'status' => Template::STATUS_ARCHIVED,
                ])->save();
            });
    }

    /**
     * @param  array<int, string>  $left
     * @param  array<int, string>  $right
     */
    private function reportTypesOverlap(array $left, array $right): bool
    {
        return array_values(array_intersect($left, $right)) !== [];
    }

    /**
     * @return array<int, string>
     */
    private function templateReportTypes(Template $template): array
    {
        $reportType = data_get($template->structure, 'report_type');

        if (is_string($reportType) && in_array($reportType, $this->reportTypeValues(), true)) {
            return [$reportType];
        }

        $title = Str::lower($template->title);
        $matches = collect($this->reportTypeValues())
            ->filter(fn (string $type): bool => Str::contains($title, $this->reportTemplateKeywords(ReportType::from($type))))
            ->values()
            ->all();

        return $matches === [] ? $this->reportTypeValues() : $matches;
    }

    /**
     * @return array<int, string>
     */
    private function reportTypeValues(): array
    {
        return [
            ReportType::Client->value,
            ReportType::Advisor->value,
            ReportType::Stakeholder->value,
            ReportType::Trajectory->value,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function reportTemplateKeywords(ReportType $type): array
    {
        return match ($type) {
            ReportType::Client => ['client report', 'client'],
            ReportType::Advisor => ['advisor report', 'advisor'],
            ReportType::Stakeholder => ['stakeholder report', 'stakeholder'],
            ReportType::Trajectory => ['business health trajectory report', 'trajectory'],
            default => [Str::lower($type->label()), Str::of($type->value)->replace('_', ' ')->lower()->toString()],
        };
    }
}
