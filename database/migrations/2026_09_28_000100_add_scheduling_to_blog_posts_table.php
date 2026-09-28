<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->string('scheduled_title', 200)->nullable();
            $table->string('scheduled_description', 300)->nullable();
            $table->text('scheduled_body')->nullable();
            $table->timestampTz('scheduled_at')->nullable();
            $table->index(['status', 'scheduled_at']);
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DROP POLICY IF EXISTS blog_posts_public_read ON blog_posts;
            DROP POLICY IF EXISTS blog_posts_admin_insert ON blog_posts;
            DROP POLICY IF EXISTS blog_posts_admin_update ON blog_posts;
            DROP POLICY IF EXISTS blog_posts_admin_delete ON blog_posts;

            CREATE POLICY blog_posts_public_read ON blog_posts
                FOR SELECT
                USING (
                    (
                        status = 'published'
                        AND published_title IS NOT NULL
                        AND published_description IS NOT NULL
                        AND published_body IS NOT NULL
                        AND published_at IS NOT NULL
                    )
                    OR fsa_current_role() IN ('super_admin', 'system')
                );

            CREATE POLICY blog_posts_admin_insert ON blog_posts
                FOR INSERT
                WITH CHECK (fsa_current_role() = 'super_admin');

            CREATE POLICY blog_posts_admin_update ON blog_posts
                FOR UPDATE
                USING (fsa_current_role() IN ('super_admin', 'system'))
                WITH CHECK (fsa_current_role() IN ('super_admin', 'system'));

            CREATE POLICY blog_posts_admin_delete ON blog_posts
                FOR DELETE
                USING (fsa_current_role() = 'super_admin');
        SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                DROP POLICY IF EXISTS blog_posts_public_read ON blog_posts;
                DROP POLICY IF EXISTS blog_posts_admin_insert ON blog_posts;
                DROP POLICY IF EXISTS blog_posts_admin_update ON blog_posts;
                DROP POLICY IF EXISTS blog_posts_admin_delete ON blog_posts;

                CREATE POLICY blog_posts_public_read ON blog_posts
                    FOR SELECT
                    USING (
                        (
                            status = 'published'
                            AND published_title IS NOT NULL
                            AND published_description IS NOT NULL
                            AND published_body IS NOT NULL
                            AND published_at IS NOT NULL
                        )
                        OR fsa_current_role() = 'super_admin'
                    );

                CREATE POLICY blog_posts_admin_insert ON blog_posts
                    FOR INSERT
                    WITH CHECK (fsa_current_role() = 'super_admin');

                CREATE POLICY blog_posts_admin_update ON blog_posts
                    FOR UPDATE
                    USING (fsa_current_role() = 'super_admin')
                    WITH CHECK (fsa_current_role() = 'super_admin');

                CREATE POLICY blog_posts_admin_delete ON blog_posts
                    FOR DELETE
                    USING (fsa_current_role() = 'super_admin');
            SQL);
        }

        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->dropIndex(['status', 'scheduled_at']);
            $table->dropColumn([
                'scheduled_title',
                'scheduled_description',
                'scheduled_body',
                'scheduled_at',
            ]);
        });
    }
};
