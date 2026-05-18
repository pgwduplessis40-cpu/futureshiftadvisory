<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Models\AuditEvent;
use App\Services\Audit\AuditWriter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AuditTrailImmutableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('audit_events append-only trigger requires Postgres.');
        }
    }

    public function test_audit_writer_persists_a_row_with_redacted_payload(): void
    {
        app(AuditWriter::class)->record(
            action: 'demo.action',
            subject: null,
            before: ['email' => 'before@example.co.nz'],
            after: ['email' => 'after@example.co.nz'],
        );

        /** @var AuditEvent $row */
        $row = AuditEvent::query()->latest('occurred_at')->firstOrFail();

        $this->assertSame('demo.action', $row->action);
        $this->assertStringNotContainsString('before@example.co.nz', json_encode($row->before));
        $this->assertStringNotContainsString('after@example.co.nz', json_encode($row->after));
        $this->assertNotNull($row->request_id);
    }

    public function test_direct_update_against_audit_events_is_rejected(): void
    {
        app(AuditWriter::class)->record('demo.target.for.update');

        $this->expectException(QueryException::class);

        DB::table('audit_events')->update(['action' => 'rewritten.history']);
    }

    public function test_direct_delete_against_audit_events_is_rejected(): void
    {
        app(AuditWriter::class)->record('demo.target.for.delete');

        $this->expectException(QueryException::class);

        DB::table('audit_events')->delete();
    }

    public function test_truncate_against_audit_events_is_rejected(): void
    {
        app(AuditWriter::class)->record('demo.target.for.truncate');

        $this->expectException(QueryException::class);

        DB::statement('TRUNCATE audit_events');
    }

    public function test_eloquent_save_on_existing_row_raises_logic_exception(): void
    {
        app(AuditWriter::class)->record('demo.existing');

        /** @var AuditEvent $row */
        $row = AuditEvent::query()->latest('occurred_at')->firstOrFail();
        $row->action = 'attempted.update';

        $this->expectException(\LogicException::class);
        $row->save();
    }

    public function test_request_id_is_consistent_across_events_in_one_request(): void
    {
        $writer = app(AuditWriter::class);

        $writer->record('demo.first');
        $writer->record('demo.second');

        $requestIds = AuditEvent::query()
            ->whereIn('action', ['demo.first', 'demo.second'])
            ->pluck('request_id')
            ->unique();

        $this->assertCount(1, $requestIds);
    }

    public function test_subject_morph_and_client_scope_are_recorded(): void
    {
        $subject = new class extends Model
        {
            protected $table = 'fake_models';

            protected $keyType = 'string';

            public $incrementing = false;

            protected $guarded = [];

            public function getMorphClass(): string
            {
                return 'fake.model';
            }
        };

        $subject->setRawAttributes([
            'id' => (string) Str::uuid(),
            'client_id' => (string) Str::uuid(),
        ], sync: true);
        $subject->exists = true;

        app(AuditWriter::class)->record('demo.with.subject', subject: $subject);

        /** @var AuditEvent $row */
        $row = AuditEvent::query()->latest('occurred_at')->firstOrFail();
        $this->assertSame('fake.model', $row->subject_type);
        $this->assertSame((string) $subject->id, $row->subject_id);
        $this->assertSame((string) $subject->client_id, $row->client_id);
    }
}
