<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Document;
use App\Models\Milestone;
use App\Services\Storage\SecureFileWriter;
use App\Services\Voice\VoiceNoteProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class VoiceNoteController extends Controller
{
    public function store(
        Request $request,
        Client $client,
        SecureFileWriter $files,
        VoiceNoteProcessor $processor,
    ): RedirectResponse {
        $validated = $request->validate([
            'audio' => ['required', 'file', 'max:51200'],
            'milestone_id' => ['nullable', 'uuid'],
        ]);

        $milestone = isset($validated['milestone_id'])
            ? Milestone::query()
                ->where('client_id', $client->getKey())
                ->whereKey($validated['milestone_id'])
                ->first()
            : null;
        $document = $files->write(
            uploadedFile: $validated['audio'],
            owner: $request->user(),
            category: Document::CATEGORY_OTHER,
            clientId: (string) $client->getKey(),
        );

        $processor->processDocument($client, $document, $request->user(), $milestone);

        return back()->with('status', 'voice-note-processed');
    }

    public function storeCallLog(Request $request, Client $client, VoiceNoteProcessor $processor): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'occurred_at' => ['nullable', 'date'],
            'transcript' => ['nullable', 'string'],
            'summary' => ['nullable', 'string'],
            'action_items' => ['nullable', 'array'],
            'action_items.*.title' => ['required_with:action_items', 'string', 'max:255'],
            'action_items.*.milestone_id' => ['nullable', 'uuid'],
            'action_items.*.due_date' => ['nullable', 'date'],
            'action_items.*.priority' => ['nullable', 'string', 'max:40'],
        ]);

        $processor->recordCallLog($client, $validated, $request->user());

        return back()->with('status', 'call-log-created');
    }
}
