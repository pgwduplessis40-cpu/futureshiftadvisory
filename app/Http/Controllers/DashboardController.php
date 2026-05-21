<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DocumentVerification;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user instanceof User && $user->user_type === User::TYPE_ENTREPRENEUR) {
            return to_route('portal.entrepreneur.dashboard');
        }

        if (
            $user instanceof User
            && in_array($user->user_type, [User::TYPE_CLIENT_PRIMARY, User::TYPE_CLIENT_TEAM], true)
            && $user->accessibleClientIds() !== []
        ) {
            return to_route('portal.dashboard');
        }

        return Inertia::render('dashboard', [
            'documentVerificationFlags' => $this->documentVerificationFlags($user),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function documentVerificationFlags(?User $user): array
    {
        if (
            ! $user instanceof User
            || ! in_array($user->user_type, [User::TYPE_SUPER_ADMIN, User::TYPE_ADVISOR], true)
        ) {
            return [];
        }

        return DocumentVerification::query()
            ->outstandingFlags()
            ->with('document.client')
            ->latest()
            ->limit(25)
            ->get()
            ->map(fn (DocumentVerification $verification): array => [
                'id' => $verification->id,
                'outcome' => $verification->outcome,
                'claim_text' => $verification->claim_text,
                'explanation' => $verification->explanation,
                'client_explanation' => $verification->clientFacingExplanation(),
                'client_name' => $verification->document?->client?->legal_name,
                'document_name' => $verification->document?->original_filename,
                'created_at' => $verification->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
