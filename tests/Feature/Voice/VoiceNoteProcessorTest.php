<?php

declare(strict_types=1);

namespace Tests\Feature\Voice;

use App\Enums\EngagementType;
use App\Models\CallLog;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\Document;
use App\Models\Goal;
use App\Models\Milestone;
use App\Models\MilestoneAction;
use App\Models\User;
use App\Models\VoiceNote;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Contracts\AiResponse;
use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Ai\Contracts\Uncertainty;
use App\Services\Integration\VirusScanner\Contracts\FileScanner;
use App\Services\Integration\VirusScanner\ScanResult;
use App\Services\Voice\VoiceNoteProcessor;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class VoiceNoteProcessorTest extends TestCase
{
    use RefreshDatabase;

    public function test_voice_note_is_transcribed_summarised_and_links_actions_to_milestones(): void
    {
        Storage::fake('secure_local');
        $this->bindSummaryAi();
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();
        $client = $this->client('Voice Client Limited', $advisor);
        $milestone = $this->milestone($client, 'Complete cash-flow cleanup');
        $document = $this->voiceDocument($client, 'meeting.txt', 'Please send the revised cash-flow model next week.');

        $voiceNote = app(VoiceNoteProcessor::class)->processDocument($client, $document, $advisor, $milestone);

        $callLog = CallLog::query()->where('voice_note_id', $voiceNote->id)->firstOrFail();
        $action = MilestoneAction::query()->where('call_log_id', $callLog->id)->firstOrFail();

        $this->assertSame(VoiceNote::STATUS_SUMMARIZED, $voiceNote->status);
        $this->assertSame('Please send the revised cash-flow model next week.', $voiceNote->transcription_text);
        $this->assertSame('Client agreed to send the revised cash-flow model.', $callLog->summary);
        $this->assertSame($milestone->id, $action->milestone_id);
        $this->assertSame('Send revised cash-flow model', $action->title);
        $this->assertSame($action->id, $callLog->action_items[0]['milestone_action_id']);
        $this->assertDatabaseHas('audit_events', ['action' => 'voice_note.processed']);
        $this->assertDatabaseHas('audit_events', ['action' => 'call_log.action_linked']);
    }

    public function test_manual_phone_call_log_links_action_items_to_milestones(): void
    {
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();
        $client = $this->client('Manual Call Limited', $advisor);
        $milestone = $this->milestone($client, 'Prepare governance pack');

        $callLog = app(VoiceNoteProcessor::class)->recordCallLog($client, [
            'title' => 'Governance follow-up',
            'summary' => 'Advisor and client agreed on next board pack action.',
            'action_items' => [
                [
                    'title' => 'Upload board pack',
                    'milestone_id' => $milestone->id,
                    'priority' => 'high',
                ],
            ],
        ], $advisor);

        $this->assertInstanceOf(CallLog::class, $callLog);
        $this->assertSame(CallLog::CHANNEL_PHONE_CALL, $callLog->channel);
        $this->assertSame(1, MilestoneAction::query()->where('call_log_id', $callLog->id)->count());
        $this->assertDatabaseHas('audit_events', ['action' => 'call_log.created']);
    }

    public function test_advisor_route_accepts_voice_upload_and_processes_note(): void
    {
        Storage::fake('secure_local');
        $this->bindSummaryAi();
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();
        $client = $this->client('Route Voice Limited', $advisor);
        $milestone = $this->milestone($client, 'Route action milestone');

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.voice-notes.store', $client), [
                'audio' => UploadedFile::fake()->createWithContent('voice.txt', 'Route upload transcript.'),
                'milestone_id' => $milestone->id,
            ])
            ->assertRedirect();

        $this->assertSame(1, VoiceNote::query()->count());
        $this->assertSame(1, CallLog::query()->count());
        $this->assertSame(1, MilestoneAction::query()->count());
    }

    public function test_advisor_route_keeps_quarantined_voice_upload_without_processing_note(): void
    {
        Storage::fake('secure_local');
        $this->bindSummaryAi();
        $this->seed(RoleSeeder::class);
        $advisor = $this->advisor();
        $client = $this->client('Quarantined Voice Limited', $advisor);

        $this->app->bind(FileScanner::class, fn (): FileScanner => new class implements FileScanner
        {
            public function scan(mixed $stream): ScanResult
            {
                return ScanResult::error('daemon offline', ['engine' => 'fake-clamav']);
            }
        });

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.voice-notes.store', $client), [
                'audio' => UploadedFile::fake()->createWithContent('voice.txt', 'Route upload transcript.'),
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'voice-note-quarantined');

        $document = Document::query()->firstOrFail();

        $this->assertSame(Document::SCANNER_ERROR, $document->scanner_result);
        $this->assertStringStartsWith('quarantine/other/', $document->stored_path);
        $this->assertSame(0, VoiceNote::query()->count());
        $this->assertSame(0, CallLog::query()->count());
        $this->assertSame(0, MilestoneAction::query()->count());
    }

    private function bindSummaryAi(): void
    {
        $this->app->instance(AiClient::class, new class implements AiClient
        {
            public function analyse(PromptEnvelope $prompt): AiResponse
            {
                return $this->response($prompt);
            }

            public function verifyDocument(PromptEnvelope $prompt): AiResponse
            {
                return $this->response($prompt);
            }

            public function scoreCriterion(PromptEnvelope $prompt): AiResponse
            {
                return $this->response($prompt);
            }

            public function summarise(PromptEnvelope $prompt): AiResponse
            {
                return $this->response($prompt);
            }

            public function redFlag(PromptEnvelope $prompt): AiResponse
            {
                return $this->response($prompt);
            }

            private function response(PromptEnvelope $prompt): AiResponse
            {
                return new AiResponse(
                    text: 'Client agreed to send the revised cash-flow model.',
                    attributions: [['claim' => 'summary', 'source_reference' => 'voice_note:test']],
                    uncertainty: Uncertainty::Low,
                    biasSignals: [],
                    model: 'test-summary',
                    promptVersion: $prompt->version,
                    promptHash: $prompt->hash(),
                    tokensIn: 10,
                    tokensOut: 10,
                    metadata: [
                        'summary_payload' => [
                            'summary' => 'Client agreed to send the revised cash-flow model.',
                            'decisions' => ['Use updated model for next review.'],
                            'action_items' => [
                                [
                                    'title' => 'Send revised cash-flow model',
                                    'priority' => 'high',
                                ],
                            ],
                        ],
                    ],
                );
            }
        });
    }

    private function advisor(): User
    {
        $user = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $user->assignRole(User::TYPE_ADVISOR);

        return $user;
    }

    private function client(string $name, User $advisor): Client
    {
        app(RequestContext::class)->apply('system', [], (string) $advisor->getKey());

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => fake()->unique()->numerify('9429#########'),
            'legal_name' => $name,
            'data_quality' => Client::DATA_QUALITY_LOW,
            'created_by_user_id' => $advisor->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [],
        ]);

        return $client;
    }

    private function milestone(Client $client, string $title): Milestone
    {
        $goal = Goal::query()->create([
            'client_id' => $client->getKey(),
            'title' => 'Goal for '.$title,
            'pv_target' => 0,
            'status' => 'active',
        ]);

        return Milestone::query()->create([
            'goal_id' => $goal->getKey(),
            'client_id' => $client->getKey(),
            'title' => $title,
            'pv_of_impact' => 0,
            'status' => Milestone::STATUS_PENDING,
        ]);
    }

    private function voiceDocument(Client $client, string $filename, string $contents): Document
    {
        $path = 'voice-notes/'.$filename;
        Storage::disk('secure_local')->put($path, $contents);

        return Document::query()->create([
            'client_id' => $client->getKey(),
            'category' => Document::CATEGORY_OTHER,
            'original_filename' => $filename,
            'stored_path' => $path,
            'byte_size' => strlen($contents),
            'mime_type' => 'text/plain',
            'sha256' => hash('sha256', $contents),
            'scanner_result' => Document::SCANNER_CLEAN,
            'scanner_payload' => ['scanner' => 'fixture'],
        ]);
    }
}
