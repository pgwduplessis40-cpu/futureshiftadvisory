<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\BlogPost;
use App\Support\RequestContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class BlogPostRlsTest extends TestCase
{
    use RefreshDatabase;

    private const RLS_APP_ROLE = 'fsa_blog_rls_app';

    private bool $connectionBypassesRls = false;

    private string $draftId;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Blog post RLS requires Postgres (see docs/dev-setup.md).');
        }

        $this->connectionBypassesRls = $this->currentRoleBypassesRls();
        if ($this->connectionBypassesRls) {
            $this->createNonBypassRole();
        }

        app(RequestContext::class)->apply(RequestContext::ROLE_SUPER_ADMIN, []);
        $this->draftId = (string) Str::uuid();
        DB::table('blog_posts')->insert([
            'id' => $this->draftId,
            'slug' => 'private-draft',
            'title' => 'Private draft',
            'description' => 'Private draft description',
            'body' => 'Private draft body',
            'status' => BlogPost::STATUS_DRAFT,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('RESET ROLE');

            if ($this->connectionBypassesRls) {
                DB::statement('REVOKE SELECT, INSERT, UPDATE, DELETE ON blog_posts FROM '.self::RLS_APP_ROLE);
                DB::statement('REVOKE USAGE ON SCHEMA public FROM '.self::RLS_APP_ROLE);
                DB::statement('DROP ROLE IF EXISTS '.self::RLS_APP_ROLE);
            }
        }

        parent::tearDown();
    }

    public function test_guest_can_read_published_posts_but_not_drafts(): void
    {
        app(RequestContext::class)->apply(RequestContext::ROLE_GUEST, []);

        $slugs = $this->withRlsRole(fn (): array => DB::table('blog_posts')->pluck('slug')->all());

        $this->assertContains('how-to-start-a-business-in-new-zealand', $slugs);
        $this->assertNotContains('private-draft', $slugs);
    }

    public function test_super_admin_can_read_drafts(): void
    {
        app(RequestContext::class)->apply(RequestContext::ROLE_SUPER_ADMIN, []);

        $this->assertSame(
            'private-draft',
            $this->withRlsRole(fn (): string => (string) DB::table('blog_posts')->where('id', $this->draftId)->value('slug')),
        );
    }

    public function test_non_admin_cannot_read_drafts_or_write_posts(): void
    {
        app(RequestContext::class)->apply('advisor', []);

        $this->assertSame(
            0,
            $this->withRlsRole(fn (): int => DB::table('blog_posts')->where('id', $this->draftId)->count()),
        );

        $this->expectException(QueryException::class);

        $this->withRlsRole(fn (): bool => DB::table('blog_posts')->insert([
            'id' => (string) Str::uuid(),
            'slug' => 'blocked-write',
            'title' => 'Blocked write',
            'description' => 'Blocked write description',
            'body' => 'Blocked write body',
            'status' => BlogPost::STATUS_DRAFT,
            'created_at' => now(),
            'updated_at' => now(),
        ]));
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
            GRANT SELECT, INSERT, UPDATE, DELETE ON blog_posts TO %1$s;
        SQL, self::RLS_APP_ROLE));
    }

    /**
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @return TResult
     */
    private function withRlsRole(callable $callback): mixed
    {
        if (! $this->connectionBypassesRls) {
            return $callback();
        }

        DB::statement('SET ROLE '.self::RLS_APP_ROLE);
        $usesSavepoint = DB::transactionLevel() > 0;
        if ($usesSavepoint) {
            DB::statement('SAVEPOINT blog_post_rls_probe');
        }

        try {
            $result = $callback();

            if ($usesSavepoint) {
                DB::statement('RELEASE SAVEPOINT blog_post_rls_probe');
            }

            return $result;
        } catch (\Throwable $exception) {
            if ($usesSavepoint) {
                DB::statement('ROLLBACK TO SAVEPOINT blog_post_rls_probe');
            }

            throw $exception;
        } finally {
            DB::statement('RESET ROLE');
        }
    }
}
