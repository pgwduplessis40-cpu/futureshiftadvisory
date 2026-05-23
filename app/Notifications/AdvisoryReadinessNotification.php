<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\AdvisoryReadinessSignal;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

final class AdvisoryReadinessNotification extends ChannelAwareNotification
{
    use Queueable;

    public function __construct(public readonly AdvisoryReadinessSignal $signal) {}

    public function databaseType(): string
    {
        return 'entrepreneur.advisory_readiness';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $signal = $this->signal->loadMissing('entrepreneurProfile');
        $profile = $signal->entrepreneurProfile;

        return (new MailMessage)
            ->subject('Entrepreneur advisory-readiness signal')
            ->line(($profile?->name ?? 'An entrepreneur').' is nearing advisory readiness.')
            ->line('Readiness score: '.number_format($signal->score, 1).'/100')
            ->action('Open entrepreneur', $profile ? route('advisor.entrepreneurs.show', $profile) : route('dashboard'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $signal = $this->signal->loadMissing('entrepreneurProfile');
        $profile = $signal->entrepreneurProfile;

        return [
            'title' => 'Entrepreneur nearing advisory readiness',
            'message' => ($profile?->name ?? 'Entrepreneur').' has reached an advisory-readiness score of '.number_format($signal->score, 1).'/100.',
            'url' => $profile ? route('advisor.entrepreneurs.show', $profile, absolute: false) : route('dashboard', absolute: false),
            'urgency' => $this->urgency(),
            'advisory_readiness_signal_id' => $signal->id,
            'entrepreneur_profile_id' => $signal->entrepreneur_profile_id,
            'business_plan_id' => $signal->business_plan_id,
            'plan_assessment_id' => $signal->plan_assessment_id,
            'score' => $signal->score,
            'surfaced_at' => $signal->surfaced_at?->toIso8601String(),
        ];
    }
}
