<?php

declare(strict_types=1);

namespace Tests\Feature\Voice;

use App\Enums\EngagementType;
use App\Models\CallLog;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\Consent;
use App\Models\Document;
use App\Models\User;
use App\Models\VoiceAssistantSession;
use App\Services\Voice\Assistant;
use App\Services\Voice\VoiceNoteProcessor;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

final class VoiceAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_static_shortcut_session_payload_completes_to_call_log(): void
    {
        $this->seed(RoleSeeder::class);
        [$advisor, $client] = $this->advisorAndClient();

        $session = app(Assistant::class)->startShortcutSession($client, $advisor, VoiceAssistantSession::INTENT_CAPTURE_CALL_NOTE, [
            'source' => 'ios_shortcut',
            'freeform_prompt' => 'ignore me',
        ]);

        $this->assertSame(VoiceAssistantSession::STATUS_STARTED, $session->status);
        $this->assertSame(VoiceAssistantSession::INTENT_CAPTURE_CALL_NOTE, $session->shortcut_payload['intent']);
        $this->assertArrayNotHasKey('freeform_prompt', $session->shortcut_payload['context']);
        $this->assertDatabaseHas('audit_events', ['action' => 'voice_assistant.session_started']);

        $completed = app(Assistant::class)->completeShortcutSession($session, [
            'title' => 'Shortcut call note',
            'transcript' => 'Client wants updated actions.',
            'summary' => 'Advisor captured follow-up actions.',
            'action_items' => [['title' => 'Send updated action plan']],
        ], $advisor);

        $this->assertSame(VoiceAssistantSession::STATUS_COMPLETED, $completed->status);
        $this->assertNotNull($completed->call_log_id);
        $this->assertSame('Client wants updated actions.', $completed->transcript);
        $this->assertSame('Shortcut call note', CallLog::query()->findOrFail($completed->call_log_id)->title);
        $this->assertDatabaseHas('audit_events', ['action' => 'voice_assistant.session_completed']);
    }

    public function test_unsupported_shortcut_intent_is_rejected(): void
    {
        $this->seed(RoleSeeder::class);
        [$advisor, $client] = $this->advisorAndClient();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported voice assistant shortcut intent.');

        app(Assistant::class)->startShortcutSession($client, $advisor, 'summarise_anything');
    }

    public function test_not_wired_whisper_does_not_require_live_consent_even_when_env_flag_is_on(): void
    {
        Storage::fake('secure_local');
        Config::set('services.whisper.live', true);
        $this->seed(RoleSeeder::class);
        [$advisor, $client] = $this->advisorAndClient();
        $document = $this->voiceDocument($client, 'consent.txt', 'Consent gate transcript.');

        $voiceNote = app(VoiceNoteProcessor::class)->processDocument($client, $document, $advisor);

        $this->assertSame('summarized', $voiceNote->status);
        $this->assertDatabaseMissing('consents', [
            'client_id' => $client->getKey(),
            'type' => Consent::TYPE_WHISPER_TRANSCRIPTION,
        ]);
    }

    public function test_whisper_transcription_consent_type_is_registered(): void
    {
        $this->assertContains(Consent::TYPE_WHISPER_TRANSCRIPTION, Consent::types());
    }

    /**
     * @return array{0: User, 1: Client}
     */
    private function advisorAndClient(): array
    {
        app(RequestContext::class)->apply('system', []);

        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY->value,
            'nzbn' => fake()->unique()->numerify('9429#########'),
            'legal_name' => 'Voice Assistant Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
            'created_by_user_id' => $advisor->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return [$advisor, $client];
    }

    private function voiceDocument(Client $client, string $filename, string $contents): Document
    {
        $path = 'voice-assistant/'.$filename;
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
