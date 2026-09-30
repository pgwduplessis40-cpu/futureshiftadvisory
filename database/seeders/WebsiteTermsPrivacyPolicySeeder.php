<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\TermsVersion;
use Illuminate\Database\Seeder;

final class WebsiteTermsPrivacyPolicySeeder extends Seeder
{
    public function run(): void
    {
        $this->seedDocument(
            TermsVersion::SCOPE_WEBSITE_TERMS,
            'Testing Terms of Use',
            'This is a test-environment Terms of Use document. Upload and publish the approved terms before using Idea Validation in production.',
        );
        $this->seedDocument(
            TermsVersion::SCOPE_PRIVACY_POLICY,
            'Testing Privacy Policy',
            'This is a test-environment Privacy Policy. Upload and publish the approved privacy policy before using Idea Validation in production.',
        );
    }

    private function seedDocument(string $scope, string $clauseTitle, string $clauseBody): void
    {
        $document = TermsVersion::query()->updateOrCreate(
            [
                'document_scope' => $scope,
                'version' => '1',
            ],
            [
                'title' => TermsVersion::defaultTitle($scope),
                'material' => true,
                'notice_period_days' => 0,
                'reviewer_reference' => 'Testing seed document only. Replace it with the approved document before production use.',
                'published_at' => now()->subMinute(),
            ],
        );

        $document->clauses()->updateOrCreate(
            ['clause_number' => 1],
            [
                'title' => $clauseTitle,
                'body' => $clauseBody,
                'material' => true,
            ],
        );
    }
}
