<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\TermsVersion;
use Illuminate\Database\Seeder;

final class WebsiteTermsPrivacyPolicySeeder extends Seeder
{
    public function run(): void
    {
        $policy = TermsVersion::query()->updateOrCreate(
            [
                'document_scope' => TermsVersion::SCOPE_WEBSITE,
                'version' => '1',
            ],
            [
                'title' => TermsVersion::defaultTitle(TermsVersion::SCOPE_WEBSITE),
                'material' => true,
                'notice_period_days' => 0,
                'reviewer_reference' => 'Testing seed policy only. Replace it with the approved website policy before production use.',
                'published_at' => now()->subMinute(),
            ],
        );

        $policy->clauses()->updateOrCreate(
            ['clause_number' => 1],
            [
                'title' => 'Testing policy',
                'body' => 'This is a test-environment Terms and Privacy Policy. Upload and publish the approved website policy before using Idea Validation in production.',
                'material' => true,
            ],
        );
    }
}
