<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Clients now see the standard_advisory engagement as "Business Advisory".
 * The internal set key is unchanged; only the questionnaire title clients see
 * during onboarding is updated. Titles an administrator has customised are
 * left alone - only the exact default title is renamed.
 */
return new class extends Migration
{
    private const OLD_TITLE = 'Standard Advisory Questionnaire';

    private const NEW_TITLE = 'Business Advisory Questionnaire';

    public function up(): void
    {
        $this->rename(self::OLD_TITLE, self::NEW_TITLE);
    }

    public function down(): void
    {
        $this->rename(self::NEW_TITLE, self::OLD_TITLE);
    }

    private function rename(string $from, string $to): void
    {
        DB::table('questionnaires')
            ->where('set', 'standard_advisory')
            ->where('title', $from)
            ->update([
                'title' => $to,
                'updated_at' => now(),
            ]);
    }
};
