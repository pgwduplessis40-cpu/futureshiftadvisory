# Voice Assistant

WO-114 adds a voice assistant companion for advisor-side shortcut capture.

## Sessions

`voice_assistant_sessions` records each shortcut launch, the static intent payload passed to the device, and the completed call-log link. Sessions are client-scoped by Postgres RLS and auditable through:

- `voice_assistant.session_started`
- `voice_assistant.session_completed`
- `voice_assistant.session_cancelled`

The shortcut payload builder only accepts static allowlisted intents:

- `capture_call_note`
- `capture_actions`

The payload is structured data, not a prompt-generation surface.

## Whisper Consent Gate

Live Whisper egress remains off unless `FEATURE_WHISPER_LIVE=true`. When live mode is enabled, `VoiceNoteProcessor` also requires an active `whisper_transcription` consent for the client before any transcription call can occur.

Fake/local transcription remains available for test and fixture flows because it does not leave the secure local disk.
