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
        Schema::create('quote_source_extractions', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->uuidMorphs('scopeable');
            $table->text('description_text')->default('');
            $table->timestampTz('description_captured_at');
            $table->string('status', 24)->default('pending');
            $table->jsonb('extracted_rows')->default(DB::raw("'[]'::jsonb"));
            $table->jsonb('confirmed_row_ids')->default(DB::raw("'[]'::jsonb"));
            $table->text('blocked_reason')->nullable();
            $table->timestampTz('extracted_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['client_id', 'scopeable_type', 'scopeable_id'], 'quote_source_extractions_scope_index');
        });

        Schema::create('quote_source_extraction_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('quote_source_extraction_id')
                ->constrained('quote_source_extractions')
                ->cascadeOnDelete();
            $table->foreignUuid('document_id')->constrained('documents')->restrictOnDelete();
            $table->foreignUuid('document_verification_id')
                ->nullable()
                ->constrained('document_verifications')
                ->nullOnDelete();
            $table->string('verification_outcome_at_use', 32)->nullable();
            $table->timestampsTz();

            $table->unique(['quote_source_extraction_id', 'document_id'], 'quote_source_extraction_document_unique');
        });

        $this->installPostgresGuards();
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS quote_source_extractions_tenant_link_guard ON quote_source_extractions;
                DROP TRIGGER IF EXISTS quote_source_extraction_documents_tenant_link_guard ON quote_source_extraction_documents;
                DROP FUNCTION IF EXISTS fsa_assert_quote_source_extraction_tenant_links() CASCADE;
            SQL);
        }

        Schema::dropIfExists('quote_source_extraction_documents');
        Schema::dropIfExists('quote_source_extractions');
    }

    private function installPostgresGuards(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fsa_assert_quote_source_extraction_tenant_links()
            RETURNS trigger AS $$
            DECLARE
                linked_client uuid;
            BEGIN
                IF TG_TABLE_NAME = 'quote_source_extractions' THEN
                    IF NEW.scopeable_type <> 'App\Models\IntegrationScope' THEN
                        RAISE EXCEPTION 'unsupported quote source scopeable type';
                    END IF;

                    SELECT client_id INTO linked_client FROM integration_scopes WHERE id = NEW.scopeable_id;
                    IF linked_client IS NULL OR linked_client <> NEW.client_id THEN
                        RAISE EXCEPTION 'quote source extraction client must match its scopeable client';
                    END IF;
                ELSIF TG_TABLE_NAME = 'quote_source_extraction_documents' THEN
                    SELECT client_id INTO linked_client
                    FROM quote_source_extractions
                    WHERE id = NEW.quote_source_extraction_id;
                    IF linked_client IS NULL THEN
                        RAISE EXCEPTION 'quote source extraction document requires an extraction';
                    END IF;

                    IF NOT EXISTS (
                        SELECT 1 FROM documents
                        WHERE id = NEW.document_id AND client_id = linked_client
                    ) THEN
                        RAISE EXCEPTION 'quote source document client must match its extraction client';
                    END IF;

                    IF NEW.document_verification_id IS NOT NULL AND NOT EXISTS (
                        SELECT 1 FROM document_verifications
                        WHERE id = NEW.document_verification_id
                          AND document_id = NEW.document_id
                          AND client_id = linked_client
                    ) THEN
                        RAISE EXCEPTION 'quote source verification must match its document and client';
                    END IF;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER quote_source_extractions_tenant_link_guard
                BEFORE INSERT OR UPDATE ON quote_source_extractions
                FOR EACH ROW EXECUTE FUNCTION fsa_assert_quote_source_extraction_tenant_links();

            CREATE TRIGGER quote_source_extraction_documents_tenant_link_guard
                BEFORE INSERT OR UPDATE ON quote_source_extraction_documents
                FOR EACH ROW EXECUTE FUNCTION fsa_assert_quote_source_extraction_tenant_links();

            ALTER TABLE quote_source_extractions ENABLE ROW LEVEL SECURITY;
            ALTER TABLE quote_source_extractions FORCE ROW LEVEL SECURITY;
            CREATE POLICY quote_source_extractions_client_scope ON quote_source_extractions
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                );

            ALTER TABLE quote_source_extraction_documents ENABLE ROW LEVEL SECURITY;
            ALTER TABLE quote_source_extraction_documents FORCE ROW LEVEL SECURITY;
            CREATE POLICY quote_source_extraction_documents_client_scope ON quote_source_extraction_documents
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR EXISTS (
                        SELECT 1
                        FROM quote_source_extractions
                        WHERE quote_source_extractions.id = quote_source_extraction_documents.quote_source_extraction_id
                          AND quote_source_extractions.client_id::text = ANY (fsa_current_client_ids())
                    )
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR EXISTS (
                        SELECT 1
                        FROM quote_source_extractions
                        WHERE quote_source_extractions.id = quote_source_extraction_documents.quote_source_extraction_id
                          AND quote_source_extractions.client_id::text = ANY (fsa_current_client_ids())
                    )
                );
        SQL);
    }
};
