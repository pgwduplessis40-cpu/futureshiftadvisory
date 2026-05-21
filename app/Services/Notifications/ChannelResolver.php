<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\CommunicationPreference;
use App\Models\User;
use Illuminate\Notifications\Notification;

final class ChannelResolver
{
    public const DATABASE_CHANNEL = 'fsa_database';

    /**
     * @return array<int, string>
     */
    public function channelsFor(object $notifiable, Notification $notification): array
    {
        return $this->decisionFor($notifiable, $notification)->channels;
    }

    public function decisionFor(object $notifiable, Notification $notification): ChannelDecision
    {
        $urgency = $this->urgency($notification);

        if ($urgency === 'urgent') {
            return new ChannelDecision(
                channels: [self::DATABASE_CHANNEL, 'mail'],
                urgency: $urgency,
                preferenceChannel: 'bypassed',
                frequency: CommunicationPreference::FREQUENCY_IMMEDIATE,
                mailNow: true,
                emailDeferred: false,
                platformNow: true,
                bypassedPreference: true,
            );
        }

        $preference = $notifiable instanceof User
            ? $this->preferenceFor($notifiable)
            : null;
        $channel = $preference?->channel ?? CommunicationPreference::CHANNEL_BOTH;
        $frequency = $preference?->frequency ?? CommunicationPreference::FREQUENCY_IMMEDIATE;
        $wantsEmail = in_array($channel, [
            CommunicationPreference::CHANNEL_EMAIL_ONLY,
            CommunicationPreference::CHANNEL_BOTH,
        ], true);
        $mailNow = $wantsEmail && $frequency === CommunicationPreference::FREQUENCY_IMMEDIATE;
        $emailDeferred = $wantsEmail && in_array($frequency, [
            CommunicationPreference::FREQUENCY_DAILY,
            CommunicationPreference::FREQUENCY_WEEKLY,
        ], true);

        $channels = [self::DATABASE_CHANNEL];
        if ($mailNow) {
            $channels[] = 'mail';
        }

        return new ChannelDecision(
            channels: $channels,
            urgency: $urgency,
            preferenceChannel: $channel,
            frequency: $frequency,
            mailNow: $mailNow,
            emailDeferred: $emailDeferred,
            platformNow: in_array($channel, [
                CommunicationPreference::CHANNEL_IN_PLATFORM_ONLY,
                CommunicationPreference::CHANNEL_BOTH,
            ], true),
            bypassedPreference: false,
        );
    }

    public function urgency(Notification $notification): string
    {
        if (method_exists($notification, 'urgency')) {
            $urgency = $notification->urgency();

            return is_string($urgency) && $urgency !== '' ? $urgency : 'normal';
        }

        if (property_exists($notification, 'urgency') && is_string($notification->urgency)) {
            return $notification->urgency !== '' ? $notification->urgency : 'normal';
        }

        return 'normal';
    }

    private function preferenceFor(User $user): CommunicationPreference
    {
        /** @var CommunicationPreference $preference */
        $preference = $user->communicationPreference()->firstOrCreate([], [
            'channel' => CommunicationPreference::CHANNEL_BOTH,
            'frequency' => CommunicationPreference::FREQUENCY_IMMEDIATE,
            'timezone' => 'Pacific/Auckland',
        ]);

        return $preference;
    }
}
