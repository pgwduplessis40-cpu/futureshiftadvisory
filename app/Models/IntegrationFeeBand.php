<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class IntegrationFeeBand extends Model
{
    use HasUuids;

    public const BAND_S = 'S';

    public const BAND_M = 'M';

    public const BAND_L = 'L';

    public const BAND_XL = 'XL';

    public static function defaultScopeDescriptionFor(string $band): string
    {
        return match ($band) {
            self::BAND_S => 'Up to two connected systems and one straightforward one-way workflow. Includes discovery confirmation, field mapping, configuration, testing, handover, and 30 days of support. Excludes custom code, data migration, and legacy or no-API work.',
            self::BAND_M => 'Two to three systems and up to two workflows or one two-way connection. Includes moderate field mapping, workflow configuration, user acceptance testing, monitoring setup, team handover, and 45 days of hypercare. Excludes substantial data migration and bespoke integrations.',
            self::BAND_L => 'Three to five systems, multiple operational or finance workflows, and moderate-to-high transformations. Includes exception handling, functional testing, go-live support, documentation, and 90 days of optimisation. Vendor costs and major data remediation are excluded.',
            self::BAND_XL => 'Complex multi-system, legacy or no-API, high-volume, or partner-led work. Includes paid discovery, a phased implementation plan, governance, testing, and change control. Detailed scope and price are agreed in staged statements of work.',
            default => 'Scope to be confirmed during integration discovery.',
        };
    }

    /** @return array{monthly_cost:float,markup_percent:float} */
    public static function defaultHostingPricing(): array
    {
        return [
            // Current Voyager VM and backup usage, before FSA's managed-hosting markup.
            'monthly_cost' => 20.66,
            'markup_percent' => 100.0,
        ];
    }

    protected $guarded = [];

    protected $casts = [
        'fee_low' => 'float',
        'fee_mid' => 'float',
        'fee_high' => 'float',
        'hosting_monthly_cost' => 'float',
        'hosting_markup_percent' => 'float',
        'is_active' => 'boolean',
    ];

    /**
     * @return BelongsTo<User, IntegrationFeeBand>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
