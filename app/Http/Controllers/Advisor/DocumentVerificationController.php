<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class DocumentVerificationController extends Controller
{
    public function update(Request $request, DocumentVerification $documentVerification): RedirectResponse
    {
        Gate::authorize('verify', Document::class);

        $validated = $request->validate([
            'resolution_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $documentVerification->forceFill([
            'resolved_at' => now(),
            'resolved_by_user_id' => $request->user()?->getAuthIdentifier(),
            'resolution_note' => $validated['resolution_note'] ?? null,
        ])->save();

        return back()->with('status', 'document-verification-resolved');
    }
}
