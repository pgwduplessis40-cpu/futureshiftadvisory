<?php

declare(strict_types=1);

namespace Tests\Feature\Npo;

use App\Enums\EngagementType;
use App\Enums\NpoEngagementSubType;
use App\Enums\NpoLegalStructure;
use App\Enums\QuestionnaireQuestionType;
use App\Enums\QuestionnaireSet;
use App\Enums\ReportType;
use App\Models\Client;
use App\Models\NpoEngagement;
use App\Models\Questionnaire;
use App\Models\User;
use App\Services\Questionnaires\QuestionnaireResponseRecorder;
use App\Support\RequestContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class NpoEngagementScaffoldTest extends TestCase
{
    use RefreshDatabase;

    private const RLS_APP_ROLE = 'fsa_npo_engagements_rls_app';

    private bool $connectionBypassesRls = false;

    protected function setUp(): void
    {
        parent::setUp();

        app(RequestContext::class)->apply('system', []);

        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->connectionBypassesRls = $this->currentRoleBypassesRls();

            if ($this->connectionBypassesRls) {
                $this->createNonBypassRole();
            }
        }
    }

    protected function tearDown(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('RESET ROLE');

            if ($this->connectionBypassesRls) {
                DB::statement('REVOKE SELECT ON npo_engagements FROM '.self::RLS_APP_ROLE);
                DB::statement('REVOKE USAGE ON SCHEMA public FROM '.self::RLS_APP_ROLE);
                DB::statement('DROP ROLE IF EXISTS '.self::RLS_APP_ROLE);
            }
        }

        parent::tearDown();
    }

    public function test_questionnaire_responses_are_unique_per_npo_engagement(): void
    {
        $user = User::factory()->create();
        $client = $this->client('NPO Questionnaire Limited');
        $first = $this->npoEngagement($client, NpoEngagementSubType::GovernanceReview);
        $second = $this->npoEngagement($client, NpoEngagementSubType::StandardNpo);
        [$questionnaire, $questionId] = $this->questionnaire();
        $recorder = app(QuestionnaireResponseRecorder::class);

        $firstResponse = $recorder->record($client, $user, $questionnaire, [
            'answers' => [
                $questionId => ['value' => 'First engagement answer.'],
            ],
        ], ['npo_engagement_id' => $first->id]);

        $secondResponse = $recorder->record($client, $user, $questionnaire, [
            'answers' => [
                $questionId => ['value' => 'Second engagement answer.'],
            ],
        ], ['npo_engagement_id' => $second->id]);

        $this->assertNotSame($firstResponse->id, $secondResponse->id);
        $this->assertDatabaseHas('questionnaire_responses', [
            'id' => $firstResponse->id,
            'npo_engagement_id' => $first->id,
        ]);
        $this->assertDatabaseHas('questionnaire_responses', [
            'id' => $secondResponse->id,
            'npo_engagement_id' => $second->id,
        ]);

        $updatedFirst = $recorder->record($client, $user, $questionnaire, [
            'answers' => [
                $questionId => ['value' => 'Updated first answer.'],
            ],
        ], ['npo_engagement_id' => $first->id]);

        $this->assertSame($firstResponse->id, $updatedFirst->id);
        $this->assertSame(2, DB::table('questionnaire_responses')->where('client_id', $client->id)->count());
    }

    public function test_legacy_questionnaire_response_path_still_updates_one_client_questionnaire_row(): void
    {
        $user = User::factory()->create();
        $client = $this->client('Legacy Questionnaire Limited', EngagementType::STANDARD_ADVISORY);
        [$questionnaire, $questionId] = $this->questionnaire();
        $recorder = app(QuestionnaireResponseRecorder::class);

        $first = $recorder->record($client, $user, $questionnaire, [
            'answers' => [
                $questionId => ['value' => 'Legacy first.'],
            ],
        ]);
        $second = $recorder->record($client, $user, $questionnaire, [
            'answers' => [
                $questionId => ['value' => 'Legacy updated.'],
            ],
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, DB::table('questionnaire_responses')->where('client_id', $client->id)->count());
        $this->assertDatabaseHas('questionnaire_responses', [
            'id' => $first->id,
            'npo_engagement_id' => null,
        ]);
    }

    public function test_composite_fk_rejects_cross_client_engagement_attachment(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Composite NPO FK assertions require Postgres.');
        }

        $clientA = $this->client('NPO FK A Limited');
        $clientB = $this->client('NPO FK B Limited');
        $engagementA = $this->npoEngagement($clientA);

        DB::statement('SAVEPOINT npo_cross_client_fk_probe');

        try {
            DB::table('reports')->insert([
                'id' => (string) Str::uuid(),
                'client_id' => $clientB->id,
                'npo_engagement_id' => $engagementA->id,
                'type' => ReportType::GovernanceReview->value,
                'title' => 'Cross-client report',
                'generated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->fail('The composite NPO engagement/client foreign key allowed a cross-client report.');
        } catch (QueryException $e) {
            DB::statement('ROLLBACK TO SAVEPOINT npo_cross_client_fk_probe');
            $this->assertStringContainsString('violates foreign key constraint', $e->getMessage());
        } finally {
            DB::statement('RELEASE SAVEPOINT npo_cross_client_fk_probe');
        }
    }

    public function test_npo_engagements_are_isolated_by_client_rls(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('NPO engagement RLS assertions require Postgres.');
        }

        $clientA = $this->client('NPO RLS A Limited');
        $clientB = $this->client('NPO RLS B Limited');
        $engagementA = $this->npoEngagement($clientA);
        $engagementB = $this->npoEngagement($clientB);

        app(RequestContext::class)->apply('advisor', [(string) $clientA->getKey()]);

        $visibleIds = $this->withRlsRole(fn (): array => DB::table('npo_engagements')
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all());

        $this->assertContains($engagementA->id, $visibleIds);
        $this->assertNotContains($engagementB->id, $visibleIds);
    }

    private function client(string $name, EngagementType $type = EngagementType::NPO): Client
    {
        return Client::query()->create([
            'engagement_type' => $type,
            'nzbn' => fake()->numerify('9429#########'),
            'legal_name' => $name,
            'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
        ]);
    }

    private function npoEngagement(Client $client, NpoEngagementSubType $subType = NpoEngagementSubType::GovernanceReview): NpoEngagement
    {
        return NpoEngagement::query()->create([
            'client_id' => $client->getKey(),
            'sub_type' => $subType,
            'legal_structure' => NpoLegalStructure::RegisteredCharity,
        ]);
    }

    /**
     * @return array{0: Questionnaire, 1: string}
     */
    private function questionnaire(): array
    {
        $questionnaire = Questionnaire::query()->create([
            'set' => QuestionnaireSet::STANDARD_ADVISORY,
            'version' => (string) Str::uuid(),
            'title' => 'NPO Scaffold Questionnaire',
            'published_at' => now(),
        ]);

        $section = $questionnaire->sections()->create([
            'order' => 1,
            'title' => 'Governance',
        ]);

        $question = $section->questions()->create([
            'order' => 1,
            'type' => QuestionnaireQuestionType::LONG_TEXT,
            'prompt' => 'Describe the current governance focus.',
            'required' => true,
        ]);

        return [$questionnaire, (string) $question->getKey()];
    }

    private function currentRoleBypassesRls(): bool
    {
        $role = DB::selectOne(
            'SELECT rolsuper, rolbypassrls FROM pg_roles WHERE rolname = current_user'
        );

        return (bool) ($role->rolsuper ?? false) || (bool) ($role->rolbypassrls ?? false);
    }

    private function createNonBypassRole(): void
    {
        DB::unprepared(sprintf(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = '%1$s') THEN
                    CREATE ROLE %1$s NOLOGIN NOBYPASSRLS;
                END IF;
            END
            $$;

            GRANT USAGE ON SCHEMA public TO %1$s;
            GRANT SELECT ON npo_engagements TO %1$s;
        SQL, self::RLS_APP_ROLE));
    }

    /**
     * @template TValue
     *
     * @param  callable(): TValue  $callback
     * @return TValue
     */
    private function withRlsRole(callable $callback): mixed
    {
        if (! $this->connectionBypassesRls) {
            return $callback();
        }

        DB::statement('SET ROLE '.self::RLS_APP_ROLE);
        $usesSavepoint = DB::transactionLevel() > 0;

        if ($usesSavepoint) {
            DB::statement('SAVEPOINT npo_engagements_rls_probe');
        }

        try {
            $result = $callback();

            if ($usesSavepoint) {
                DB::statement('RELEASE SAVEPOINT npo_engagements_rls_probe');
            }

            return $result;
        } catch (\Throwable $e) {
            if ($usesSavepoint) {
                DB::statement('ROLLBACK TO SAVEPOINT npo_engagements_rls_probe');
            }

            throw $e;
        } finally {
            DB::statement('RESET ROLE');
        }
    }
}
