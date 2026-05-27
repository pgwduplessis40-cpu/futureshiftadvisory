<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            TermsVersionSeeder::class,
            StandardAdvisoryQuestionnaireSeeder::class,
            StandardAdvisoryQuestionnaireV2Seeder::class,
            DdSpecificQuestionnaireSeeder::class,
            DdSpecificQuestionnaireV2Seeder::class,
            PostAcquisitionGapQuestionnaireSeeder::class,
            EntrepreneurReadinessQuestionnaireSeeder::class,
            GovernanceReviewQuestionnaireSeeder::class,
            StandardNpoQuestionnaireSeeder::class,
            NzResourceSeeder::class,
            RatingFrameworkSeeder::class,
            FoundingRatingFrameworkValuesSeeder::class,
            UserSeeder::class,
        ]);

        if (filter_var(env('SEED_TESTING_DATA', false), FILTER_VALIDATE_BOOL)) {
            $this->call(TestingSeedDataSeeder::class);
        }
    }
}
