<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Enums\ReportType;
use App\Models\AdvisoryReadinessSignal;
use App\Models\BusinessPlan;
use App\Models\Document;
use App\Models\EntrepreneurProfile;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\MessageThreadParticipant;
use App\Models\ReadinessAssessment;
use App\Models\Report;
use App\Models\User;

/**
 * @phpstan-type ReadinessSummary array{completed:bool, score:float|int|null, outcome:string|null, assessed_at:string|null}
 * @phpstan-type AdvisoryReadinessSummary array{id:string, score:float|int|null, surfaced_at:string|null}
 * @phpstan-type ReportSummary array{id:string, title:string, generated_at:string|null, view_url:string, download_url:string}
 * @phpstan-type ConversionSummary array{available:bool, converted:bool, client_id:string|null, convert_url:string}
 * @phpstan-type DocumentSummary array{id:string, original_filename:string, category:string, scanner_result:string|null, scanner_message:mixed, uploaded_at:string|null, uploaded_by_name:string|null, url:string|null}
 * @phpstan-type MessageSummary array{threads_count:int, unread_count:int, latest_activity_at:string|null, url:string}
 */
final class AdvisorEntrepreneurSupportPayload
{
    /** @return ReadinessSummary */
    public function readiness(EntrepreneurProfile $profile): array
    {
        $assessment = ReadinessAssessment::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->latest('assessed_at')
            ->latest()
            ->first();

        return [
            'completed' => $assessment instanceof ReadinessAssessment,
            'score' => $assessment?->score,
            'outcome' => $assessment?->outcome,
            'assessed_at' => $assessment?->assessed_at?->toIso8601String(),
        ];
    }

    /** @return AdvisoryReadinessSummary|null */
    public function advisoryReadiness(EntrepreneurProfile $profile): ?array
    {
        $signal = AdvisoryReadinessSignal::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->latest('surfaced_at')
            ->latest()
            ->first();

        if (! $signal instanceof AdvisoryReadinessSignal) {
            return null;
        }

        return [
            'id' => $signal->id,
            'score' => $signal->score,
            'surfaced_at' => $signal->surfaced_at?->toIso8601String(),
        ];
    }

    /** @return list<ReportSummary> */
    public function reports(EntrepreneurProfile $profile): array
    {
        return Report::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->where('type', ReportType::EntrepreneurAssessment)
            ->latest('generated_at')
            ->limit(5)
            ->get()
            ->map(function (Report $report): array {
                $url = route('advisor.reports.download', $report, absolute: false);

                return [
                    'id' => $report->id,
                    'title' => $report->title,
                    'generated_at' => $report->generated_at?->toIso8601String(),
                    'view_url' => $url,
                    'download_url' => $url,
                ];
            })
            ->values()
            ->all();
    }

    /** @return ConversionSummary */
    public function conversion(EntrepreneurProfile $profile, ?BusinessPlan $plan): array
    {
        $signalExists = AdvisoryReadinessSignal::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->exists();

        return [
            'available' => $signalExists && ! $plan?->client_id,
            'converted' => $plan?->client_id !== null,
            'client_id' => $plan?->client_id,
            'convert_url' => route('advisor.entrepreneurs.convert', $profile, absolute: false),
        ];
    }

    /** @return list<DocumentSummary> */
    public function documents(EntrepreneurProfile $profile): array
    {
        return Document::query()
            ->with('uploadedBy')
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->latest()
            ->get()
            ->groupBy(fn (Document $document): string => implode('|', [
                $document->category,
                $document->sha256 ?: $document->getKey(),
            ]))
            ->map(fn ($duplicates): Document => $duplicates->firstWhere(
                'scanner_result',
                Document::SCANNER_CLEAN,
            ) ?? $duplicates->first())
            ->sortByDesc('created_at')
            ->take(6)
            ->map(fn (Document $document): array => [
                'id' => $document->id,
                'original_filename' => $document->original_filename,
                'category' => $document->category,
                'scanner_result' => $document->scanner_result,
                'scanner_message' => data_get($document->scanner_payload, 'message'),
                'uploaded_at' => $document->created_at?->toIso8601String(),
                'uploaded_by_name' => $document->uploadedBy?->name,
                'url' => $document->isVisibleToClients()
                    ? route('advisor.entrepreneurs.documents.show', [$profile, $document], absolute: false)
                    : null,
            ])
            ->values()
            ->all();
    }

    /** @return MessageSummary */
    public function messages(EntrepreneurProfile $profile, User $user): array
    {
        $threadIds = MessageThread::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->pluck('id');
        $latestThread = MessageThread::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->orderByDesc('last_activity_at')
            ->orderByDesc('created_at')
            ->first();

        $participantRows = MessageThreadParticipant::query()
            ->whereIn('thread_id', $threadIds)
            ->where('user_id', $user->getKey())
            ->get(['thread_id', 'last_read_at']);

        $unread = $participantRows->sum(function (MessageThreadParticipant $participant) use ($user): int {
            $query = Message::query()
                ->where('thread_id', $participant->thread_id)
                ->where('sender_user_id', '!=', $user->getKey());

            if ($participant->last_read_at !== null) {
                $query->where('sent_at', '>', $participant->last_read_at);
            }

            return $query->count();
        });

        return [
            'threads_count' => $threadIds->count(),
            'unread_count' => (int) $unread,
            'latest_activity_at' => $latestThread?->last_activity_at?->toIso8601String(),
            'url' => route('advisor.entrepreneurs.messages.index', $profile, absolute: false),
        ];
    }
}
