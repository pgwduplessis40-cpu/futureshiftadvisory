<?php

declare(strict_types=1);

namespace App\Enums;

enum EngagementType: string
{
    case STANDARD_ADVISORY = 'standard_advisory';
    case DUE_DILIGENCE = 'due_diligence';
    case POST_ACQUISITION_ADVISORY = 'post_acquisition_advisory';
    case ENTREPRENEUR_MODULE = 'entrepreneur_module';
    case NPO = 'npo';

    public function label(): string
    {
        return match ($this) {
            self::STANDARD_ADVISORY => 'Standard Advisory',
            self::DUE_DILIGENCE => 'Due Diligence',
            self::POST_ACQUISITION_ADVISORY => 'Post-acquisition Advisory',
            self::ENTREPRENEUR_MODULE => 'Entrepreneur Module',
            self::NPO => 'NPO',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::STANDARD_ADVISORY => 'Whole-business diagnostic and advisory roadmap.',
            self::DUE_DILIGENCE => 'Acquisition-grade review for an investment or purchase.',
            self::POST_ACQUISITION_ADVISORY => 'Post-close gap analysis and integration sequencing.',
            self::ENTREPRENEUR_MODULE => 'Founder profile for the Phase 1 entrepreneur intake path.',
            self::NPO => 'Not-for-profit advisory, social enterprise, and governance review engagements.',
        };
    }

    /**
     * @return array<int, array{value:string, label:string, description:string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
                'description' => $type->description(),
            ],
            self::cases(),
        );
    }
}
