<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\ClientStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Document;
use App\Models\NpoBoardMember;
use App\Models\NpoEngagement;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class NpoBoardDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->user_type === User::TYPE_NPO_BOARD_MEMBER, 403);

        $membership = NpoBoardMember::query()
            ->with(['client', 'npoEngagement'])
            ->where('user_id', $user->getKey())
            ->where('active', true)
            ->whereNull('revoked_at')
            ->latest()
            ->firstOrFail();

        $client = $membership->client;
        $engagement = $membership->npoEngagement;
        abort_unless($client instanceof Client, 404);
        abort_if($client->status === ClientStatus::SUSPENDED, 404);
        abort_unless($engagement instanceof NpoEngagement, 404);

        return Inertia::render('portal/npo-board/Dashboard', [
            'client' => [
                'id' => $client->getKey(),
                'legal_name' => $client->legal_name,
                'trading_name' => $client->trading_name,
                'nzbn' => $client->nzbn,
            ],
            'membership' => [
                'treasurer' => $membership->treasurer,
                'joined_at' => $membership->created_at?->toIso8601String(),
            ],
            'engagement' => [
                'id' => $engagement->getKey(),
                'sub_type' => $engagement->sub_type?->label() ?? 'NPO engagement',
                'conversion_status' => $engagement->conversion_status?->label(),
                'report_delivered_at' => $engagement->report_delivered_at?->toIso8601String(),
                'reengagement_due_at' => $engagement->reengagement_due_at?->toDateString(),
            ],
            'reports' => $this->reports($engagement),
            'documents' => $this->documents($engagement),
            'links' => [
                'calendar' => route('portal.calendar.index', absolute: false),
                'messages' => route('portal.messages.index', absolute: false),
                'notifications' => route('notifications.index', absolute: false),
            ],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function reports(NpoEngagement $engagement): array
    {
        return Report::query()
            ->where('client_id', $engagement->client_id)
            ->where('npo_engagement_id', $engagement->getKey())
            ->whereIn('review_status', ['not_required', 'reviewed'])
            ->latest('generated_at')
            ->limit(6)
            ->get()
            ->map(fn (Report $report): array => [
                'id' => $report->getKey(),
                'type' => $report->type?->label() ?? 'Report',
                'review_status' => $report->review_status,
                'generated_at' => $report->generated_at?->toIso8601String(),
                'url' => route('portal.reports.show', $report, absolute: false),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function documents(NpoEngagement $engagement): array
    {
        return Document::query()
            ->visibleToClients()
            ->where('client_id', $engagement->client_id)
            ->where('npo_engagement_id', $engagement->getKey())
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (Document $document): array => [
                'id' => $document->getKey(),
                'filename' => $document->original_filename,
                'category' => $document->category,
                'uploaded_at' => $document->created_at?->toIso8601String(),
                'url' => route('portal.documents.show', $document, absolute: false),
            ])
            ->all();
    }
}
