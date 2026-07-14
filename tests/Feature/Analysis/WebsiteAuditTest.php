<?php

declare(strict_types=1);

namespace Tests\Feature\Analysis;

use App\Enums\AnalysisLens;
use App\Enums\EngagementType;
use App\Enums\QuestionnaireQuestionType;
use App\Enums\QuestionnaireSet;
use App\Models\AnalysisFinding;
use App\Models\AnalysisRun;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentVerification;
use App\Models\Questionnaire;
use App\Models\QuestionnaireResponse;
use App\Models\User;
use App\Models\WebsiteAuditSnapshot;
use App\Services\Analysis\WebsiteAuditRunner;
use App\Services\Analysis\WebsiteUrlConfirmationService;
use App\Services\Analysis\WebsiteUrlPolicy;
use App\Services\Integration\Resilience\ResilientHttp;
use App\Support\RequestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

final class WebsiteAuditTest extends TestCase
{
    use RefreshDatabase;

    private const PUBLIC_TEST_URL = 'https://8.8.8.8/';

    protected function setUp(): void
    {
        parent::setUp();

        app(RequestContext::class)->apply('system', []);
    }

    public function test_confirmed_website_is_fetched_and_produces_page_cited_findings(): void
    {
        [$client, $user] = $this->clientWithQuestionnaire(self::PUBLIC_TEST_URL.' virtual CFO and cash-flow advisory.');
        app(WebsiteUrlConfirmationService::class)->confirm($client, self::PUBLIC_TEST_URL, $user);
        $this->fakeWebsite();

        $run = app(WebsiteAuditRunner::class)->run($client, [
            'actor' => $user,
            'created_by_user_id' => $user->getKey(),
        ]);

        $this->assertSame(AnalysisRun::STATUS_COMPLETED, $run->status);
        $this->assertSame(AnalysisLens::values(), $run->framework_lenses);
        $this->assertCount(4, $run->findings);
        $this->assertTrue($run->findings->every(fn (AnalysisFinding $finding): bool => collect($finding->attributions)
            ->contains(fn (array $attribution): bool => str_starts_with($attribution['source_reference'], 'website:'))));

        $snapshot = WebsiteAuditSnapshot::query()->where('analysis_run_id', $run->getKey())->firstOrFail();
        $this->assertSame(WebsiteAuditSnapshot::STATUS_OK, $snapshot->fetch_status);
        $this->assertNotEmpty($snapshot->pages);
        $this->assertNotEmpty(data_get($snapshot->ai_evidence, 'pages.0.content_hash'));
        $this->assertSame('deterministic_signals_plus_examiner_review', data_get($snapshot->ai_evidence, 'score_source'));
        $this->assertIsInt(data_get($snapshot->scores, 'overall'));
        $this->assertSame(0, data_get($snapshot->technical, 'error_page_count'));
    }

    public function test_unconfirmed_questionnaire_url_skips_fetch_probe_and_ai(): void
    {
        [$client, $user] = $this->clientWithQuestionnaire(self::PUBLIC_TEST_URL.' virtual CFO and cash-flow advisory.');
        Http::preventStrayRequests();

        $run = app(WebsiteAuditRunner::class)->run($client, ['actor' => $user]);

        $this->assertSame(AnalysisRun::STATUS_COMPLETED, $run->status);
        $this->assertSame([], $run->framework_lenses);
        $this->assertSame(0, $run->findings()->count());
        $this->assertSame(0, $run->tokens_in);
        $this->assertDatabaseHas('website_audit_snapshots', [
            'client_id' => $client->getKey(),
            'fetch_status' => WebsiteAuditSnapshot::STATUS_SKIPPED_NO_URL,
            'skip_reason' => WebsiteAuditSnapshot::SKIP_AWAITING_ADVISOR_CONFIRMATION,
            'analysis_run_id' => $run->getKey(),
        ]);
    }

    public function test_no_listed_url_is_skipped_with_a_distinct_reason(): void
    {
        [$client, $user] = $this->clientWithQuestionnaire('The client sells virtual CFO and cash-flow advisory.');
        Http::preventStrayRequests();

        app(WebsiteAuditRunner::class)->run($client, ['actor' => $user]);

        $this->assertDatabaseHas('website_audit_snapshots', [
            'client_id' => $client->getKey(),
            'fetch_status' => WebsiteAuditSnapshot::STATUS_SKIPPED_NO_URL,
            'skip_reason' => WebsiteAuditSnapshot::SKIP_NO_WEBSITE_URL_LISTED,
        ]);
    }

    public function test_questionnaire_url_candidates_exclude_terminal_sentence_punctuation(): void
    {
        [$client] = $this->clientWithQuestionnaire('The website is '.self::PUBLIC_TEST_URL.'.');

        $candidates = app(WebsiteUrlConfirmationService::class)->questionnaireCandidates($client);

        $this->assertSame(self::PUBLIC_TEST_URL, $candidates[0]['url']);
    }

    public function test_confirmed_website_respects_document_verification_gate_before_ai(): void
    {
        [$client, $user] = $this->clientWithQuestionnaire(self::PUBLIC_TEST_URL.' virtual CFO and cash-flow advisory.');
        app(WebsiteUrlConfirmationService::class)->confirm($client, self::PUBLIC_TEST_URL, $user);
        $this->blockingVerificationFor($client);
        $this->fakeWebsite();

        $run = app(WebsiteAuditRunner::class)->run($client, ['actor' => $user]);

        $this->assertSame(AnalysisRun::STATUS_BLOCKED_DOCUMENTS, $run->status);
        $this->assertSame(0, $run->findings()->count());
        $this->assertDatabaseHas('website_audit_snapshots', [
            'analysis_run_id' => $run->getKey(),
            'fetch_status' => WebsiteAuditSnapshot::STATUS_OK,
        ]);
    }

    public function test_probe_treats_a_404_as_a_measured_response_not_a_fallback(): void
    {
        Http::fake([
            'https://example.com/missing' => Http::response('not found', 404),
        ]);

        $result = app(ResilientHttp::class)->probe(
            service: 'website_audit:example.com',
            endpoint: 'https://example.com/missing',
            acceptableStatusCodes: [404],
        );

        $this->assertSame(404, $result->statusCode);
        $this->assertFalse($result->fromFallback);
        $this->assertSame('success', $result->status);
        $this->assertDatabaseMissing('integration_calls', [
            'service' => 'website_audit:example.com',
            'status' => 'failure',
        ]);
    }

    public function test_url_policy_rejects_loopback_and_non_http_urls(): void
    {
        $policy = app(WebsiteUrlPolicy::class);

        $this->expectException(InvalidArgumentException::class);
        $policy->resolvePublicUrl('http://127.0.0.1/');
    }

    /**
     * @return array{0:Client,1:User}
     */
    private function clientWithQuestionnaire(string $websiteValue): array
    {
        $user = User::factory()->create();
        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '942900'.random_int(1000000, 9999999),
            'legal_name' => 'Website Audit Fixture Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
            'primary_contact_user_id' => $user->getKey(),
        ]);
        $questionnaire = Questionnaire::query()->create([
            'set' => QuestionnaireSet::STANDARD_ADVISORY,
            'version' => 'website-audit-'.Str::lower(Str::random(8)),
            'title' => 'Website audit fixture',
            'published_at' => now(),
        ]);
        $section = $questionnaire->sections()->create(['order' => 1, 'title' => 'Website']);
        $websiteQuestion = $section->questions()->create([
            'order' => 1,
            'type' => QuestionnaireQuestionType::TEXT,
            'prompt' => 'Website URL and main product or service pages',
            'required' => false,
        ]);
        $offerQuestion = $section->questions()->create([
            'order' => 2,
            'type' => QuestionnaireQuestionType::TEXT,
            'prompt' => 'What products or services does the business sell?',
            'required' => true,
        ]);
        $response = QuestionnaireResponse::query()->create([
            'client_id' => $client->getKey(),
            'questionnaire_id' => $questionnaire->getKey(),
            'submitted_at' => now(),
            'submitted_by_user_id' => $user->getKey(),
        ]);
        $response->answers()->create([
            'question_id' => $websiteQuestion->getKey(),
            'value' => $websiteValue,
            'attached_document_ids' => [],
        ]);
        $response->answers()->create([
            'question_id' => $offerQuestion->getKey(),
            'value' => 'Fixed-fee cash-flow advisory, monthly virtual CFO support, and pricing workshops for New Zealand SMEs.',
            'attached_document_ids' => [],
        ]);

        return [$client, $user];
    }

    private function fakeWebsite(): void
    {
        $html = <<<'HTML'
<!doctype html><html lang="en"><head><title>Virtual CFO Advisory</title><meta name="description" content="Cash-flow advisory for New Zealand SMEs."><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="canonical" href="https://8.8.8.8/"><script type="application/ld+json">{"@context":"https://schema.org","@type":"ProfessionalService"}</script></head><body><h1>Virtual CFO and cash-flow advisory</h1><h2>Clear financial decisions</h2><p>Fixed-fee cash-flow advisory and monthly CFO support for New Zealand SMEs.</p><a href="/privacy">Privacy policy</a><a href="/terms">Terms and conditions</a><a href="mailto:hello@example.test">Email us</a><a href="/contact">Book a consultation</a><form action="/enquire"><input type="submit" value="Request a quote"></form><img src="team.jpg" alt="Advisor meeting a client"></body></html>
HTML;

        Http::fake([
            self::PUBLIC_TEST_URL.'robots.txt' => Http::response('', 404),
            self::PUBLIC_TEST_URL.'sitemap.xml' => Http::response('', 404),
            self::PUBLIC_TEST_URL.'*' => Http::response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']),
        ]);
    }

    private function blockingVerificationFor(Client $client): void
    {
        $document = Document::query()->create([
            'client_id' => $client->id,
            'category' => Document::CATEGORY_OTHER,
            'original_filename' => 'website-claim.txt',
            'stored_path' => 'website-audit/'.Str::uuid().'.txt',
            'byte_size' => 10,
            'mime_type' => 'text/plain',
            'sha256' => hash('sha256', $client->id),
            'uploaded_by_user_id' => $client->primary_contact_user_id,
            'scanner_result' => Document::SCANNER_CLEAN,
        ]);
        DocumentVerification::query()->create([
            'document_id' => $document->id,
            'client_id' => $client->id,
            'claim_source' => 'website-audit',
            'context_hash' => hash('sha256', $document->id),
            'claim_text' => 'Website evidence needs advisor review.',
            'outcome' => DocumentVerification::OUTCOME_ADVISORY_FLAG,
            'verified_at' => now(),
        ]);
    }
}
