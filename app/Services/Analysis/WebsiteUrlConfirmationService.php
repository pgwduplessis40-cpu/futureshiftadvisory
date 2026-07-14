<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Enums\QuestionnaireSet;
use App\Models\Client;
use App\Models\QuestionnaireResponse;
use App\Models\User;
use App\Models\WebsiteUrlConfirmation;
use App\Services\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class WebsiteUrlConfirmationService
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly WebsiteUrlPolicy $urls,
    ) {}

    /**
     * @param  array<int, string>  $sourceAnswerIds
     */
    public function confirm(Client $client, string $url, User $actor, array $sourceAnswerIds = []): WebsiteUrlConfirmation
    {
        $rootUrl = $this->urls->normaliseRootUrl($url);

        return DB::transaction(function () use ($client, $rootUrl, $actor, $sourceAnswerIds): WebsiteUrlConfirmation {
            WebsiteUrlConfirmation::query()
                ->where('client_id', $client->getKey())
                ->whereIn('status', [
                    WebsiteUrlConfirmation::STATUS_CONFIRMED,
                    WebsiteUrlConfirmation::STATUS_PENDING_ADVISOR_REVIEW,
                ])
                ->update(['status' => WebsiteUrlConfirmation::STATUS_REVOKED]);

            $confirmation = WebsiteUrlConfirmation::query()->create([
                'client_id' => $client->getKey(),
                'root_url' => $rootUrl,
                'status' => WebsiteUrlConfirmation::STATUS_CONFIRMED,
                'source_questionnaire_answer_ids' => array_values(array_unique($sourceAnswerIds)),
                'confirmed_by_user_id' => $actor->getKey(),
                'confirmed_at' => now(),
            ]);

            $this->audit->record('website_audit.url_confirmed', subject: $confirmation, actor: $actor, after: [
                'root_url' => $rootUrl,
                'source_questionnaire_answer_ids' => $confirmation->source_questionnaire_answer_ids,
            ]);

            return $confirmation;
        });
    }

    public function submitForAdvisorReview(Client $client, string $url, User $actor): WebsiteUrlConfirmation
    {
        $rootUrl = $this->urls->normaliseRootUrl($url);

        return DB::transaction(function () use ($client, $rootUrl, $actor): WebsiteUrlConfirmation {
            $existing = WebsiteUrlConfirmation::query()
                ->where('client_id', $client->getKey())
                ->where('status', WebsiteUrlConfirmation::STATUS_PENDING_ADVISOR_REVIEW)
                ->where('root_url', $rootUrl)
                ->latest()
                ->first();

            if ($existing instanceof WebsiteUrlConfirmation) {
                return $existing;
            }

            WebsiteUrlConfirmation::query()
                ->where('client_id', $client->getKey())
                ->where('status', WebsiteUrlConfirmation::STATUS_PENDING_ADVISOR_REVIEW)
                ->update(['status' => WebsiteUrlConfirmation::STATUS_REVOKED]);

            $submission = WebsiteUrlConfirmation::query()->create([
                'client_id' => $client->getKey(),
                'root_url' => $rootUrl,
                'status' => WebsiteUrlConfirmation::STATUS_PENDING_ADVISOR_REVIEW,
                'source_questionnaire_answer_ids' => [],
            ]);

            $this->audit->record('website_audit.url_submitted', subject: $submission, actor: $actor, after: [
                'root_url' => $rootUrl,
            ]);

            return $submission;
        });
    }

    public function latestConfirmed(Client $client): ?WebsiteUrlConfirmation
    {
        return WebsiteUrlConfirmation::query()
            ->where('client_id', $client->getKey())
            ->where('status', WebsiteUrlConfirmation::STATUS_CONFIRMED)
            ->latest('confirmed_at')
            ->latest()
            ->first();
    }

    public function latestPendingAdvisorReview(Client $client): ?WebsiteUrlConfirmation
    {
        return WebsiteUrlConfirmation::query()
            ->where('client_id', $client->getKey())
            ->where('status', WebsiteUrlConfirmation::STATUS_PENDING_ADVISOR_REVIEW)
            ->latest()
            ->first();
    }

    /**
     * @return array<int, array{url:string, answer_id:?string, source:'client'|'questionnaire'}>
     */
    public function candidates(Client $client): array
    {
        $candidates = [];
        $submission = $this->latestPendingAdvisorReview($client);

        if ($submission instanceof WebsiteUrlConfirmation) {
            $candidates[$submission->root_url] = [
                'url' => $submission->root_url,
                'answer_id' => null,
                'source' => 'client',
            ];
        }

        foreach ($this->questionnaireCandidates($client) as $candidate) {
            $candidates[$candidate['url']] ??= [
                'url' => $candidate['url'],
                'answer_id' => $candidate['answer_id'],
                'source' => 'questionnaire',
            ];
        }

        return array_values($candidates);
    }

    /**
     * @return array<int, array{url:string, answer_id:string}>
     */
    public function questionnaireCandidates(Client $client): array
    {
        $answers = QuestionnaireResponse::query()
            ->where('client_id', $client->getKey())
            ->whereHas('questionnaire', fn ($query) => $query->forSet(QuestionnaireSet::STANDARD_ADVISORY))
            ->with('answers.question')
            ->latest('submitted_at')
            ->latest()
            ->limit(3)
            ->get()
            ->flatMap(fn (QuestionnaireResponse $response) => $response->answers);

        $candidates = [];
        foreach ($answers as $answer) {
            $value = is_array($answer->value) ? json_encode($answer->value) : (string) $answer->value;
            preg_match_all('/(?:https?:\/\/|www\.)[^\s<>()\[\]"\']+/i', (string) $value, $matches);

            foreach ($matches[0] ?? [] as $match) {
                try {
                    $url = $this->urls->normaliseRootUrl(rtrim($match, '.,;:!?'));
                } catch (InvalidArgumentException) {
                    continue;
                }

                $candidates[$url] = ['url' => $url, 'answer_id' => (string) $answer->getKey()];
            }
        }

        return array_values($candidates);
    }
}
