<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Fortify\CreateNewUser;
use App\Concerns\WritesAudit;
use App\Events\CoBrowseActionDispatched;
use App\Events\ScreenShareSignal;
use App\Jobs\DispatchDailyDigest;
use App\Jobs\DispatchWeeklyDigest;
use App\Jobs\EndScreenShareSessionIfDisconnected;
use App\Jobs\RefreshIdeaValidationAiReview;
use App\Jobs\RunEntrepreneurPlanAssessment;
use App\Models\AuditEvent;
use App\Models\CoBrowseAction;
use App\Services\Notifications\DigestDispatcher;
use App\Services\ScreenShare\ScreenShareSessions;
use App\Support\RequestContext;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

final class InfrastructureContractsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(RequestContext::class)->apply('system', []);
    }

    public function test_registration_action_validates_and_persists_a_new_user(): void
    {
        $user = app(CreateNewUser::class)->create([
            'name' => 'New Platform User',
            'email' => 'new-platform-user@example.test',
            'password' => 'Valid-password-123!',
            'password_confirmation' => 'Valid-password-123!',
        ]);

        $this->assertSame('new-platform-user@example.test', $user->email);
        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'name' => 'New Platform User',
        ]);
    }

    public function test_audit_trait_records_mutations_and_sensitive_reads_with_context(): void
    {
        $writer = new class
        {
            use WritesAudit;

            /** @param array<string, mixed> $context */
            public function write(string $action, array $context): AuditEvent
            {
                return $this->audit($action, after: ['fixture' => true], context: $context);
            }

            /** @param array<string, mixed> $context */
            public function read(string $action, array $context): AuditEvent
            {
                return $this->auditRead($action, context: $context);
            }
        };

        $mutation = $writer->write('infrastructure.fixture_updated', ['source' => 'test']);
        $read = $writer->read('infrastructure.fixture_viewed', ['source' => 'test']);

        $this->assertSame('infrastructure.fixture_updated', $mutation->action);
        $this->assertSame('infrastructure.fixture_viewed', $read->action);
    }

    public function test_digest_and_disconnect_jobs_delegate_to_their_services(): void
    {
        (new DispatchDailyDigest)->handle(app(DigestDispatcher::class));
        (new DispatchWeeklyDigest)->handle(app(DigestDispatcher::class));
        (new EndScreenShareSessionIfDisconnected(
            sessionId: '00000000-0000-0000-0000-000000000000',
            connectionId: '00000000-0000-0000-0000-000000000001',
        ))->handle(app(ScreenShareSessions::class));

        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('screen_share_sessions', 0);
    }

    public function test_stale_entrepreneur_jobs_return_without_touching_deleted_source_records(): void
    {
        (new RefreshIdeaValidationAiReview(
            ideaValidationId: '00000000-0000-0000-0000-000000000002',
            advisorId: 999998,
        ))->handle(app(RequestContext::class));
        $assessmentJob = new RunEntrepreneurPlanAssessment(
            businessPlanId: '00000000-0000-0000-0000-000000000003',
            advisorId: 999997,
        );
        $assessmentJob->handle(app(RequestContext::class));
        $assessmentJob->failed(new RuntimeException('The queued assessment source was removed.'));

        $this->assertDatabaseCount('idea_validations', 0);
        $this->assertDatabaseCount('business_plans', 0);
    }

    public function test_entrepreneur_assessment_job_can_complete_all_criterion_requests_before_its_reservation_expires(): void
    {
        $assessmentJob = new RunEntrepreneurPlanAssessment(
            businessPlanId: '00000000-0000-0000-0000-000000000003',
            advisorId: 999997,
        );

        $this->assertGreaterThan(12 * 60, $assessmentJob->timeout);
        $this->assertGreaterThan($assessmentJob->timeout, config('queue.connections.database.retry_after'));
    }

    public function test_realtime_events_publish_only_the_participant_scoped_payloads(): void
    {
        $signal = new ScreenShareSignal(
            connectionId: 'connection-1',
            sessionId: 'session-1',
            fromConnectionId: 'connection-2',
            signalId: 7,
            signalType: 'offer',
            payload: ['type' => 'offer', 'sdp' => 'v=0'],
        );
        $action = new CoBrowseAction([
            'id' => 7,
            'session_id' => '00000000-0000-0000-0000-000000000008',
            'type' => 'highlight',
            'payload' => ['target' => 'client.dashboard.progress'],
        ]);
        $guidance = new CoBrowseActionDispatched('connection-3', $action);

        $this->assertInstanceOf(PrivateChannel::class, $signal->broadcastOn());
        $this->assertSame('screen-share.signal', $signal->broadcastAs());
        $this->assertSame([
            'id' => 7,
            'session_id' => 'session-1',
            'from_connection_id' => 'connection-2',
            'type' => 'offer',
            'payload' => ['type' => 'offer', 'sdp' => 'v=0'],
        ], $signal->broadcastWith());
        $this->assertInstanceOf(PrivateChannel::class, $guidance->broadcastOn());
        $this->assertSame('co-browse.action', $guidance->broadcastAs());
        $this->assertSame([
            'id' => 7,
            'session_id' => '00000000-0000-0000-0000-000000000008',
            'type' => 'highlight',
            'payload' => ['target' => 'client.dashboard.progress'],
        ], $guidance->broadcastWith());
    }
}
