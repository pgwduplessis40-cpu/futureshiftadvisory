import { router } from '@inertiajs/react';
import { CheckCircle2, Trophy } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

export type ServiceJourneyRecognitionPayload = {
    available: boolean;
    enabled: boolean;
    service_key: string;
    title: string;
    preference_url: string;
    seen_url: string;
    points: {
        total: number;
        milestone_count: number;
    };
    badges: Array<{
        id: string;
        key: string;
        label: string;
        earned_at: string | null;
        earned_at_estimated: boolean;
        seen_at: string | null;
    }>;
    new_badge_count: number;
    next_quest: {
        key: string;
        label: string;
        points: number;
        description: string;
    } | null;
    milestones: Array<{
        key: string;
        label: string;
        owner: string;
        owner_label: string;
        points: number;
        status: 'complete' | 'active' | 'pending' | (string & {});
    }>;
};

export function ServiceJourneyRecognitionPanel({
    recognition,
}: {
    recognition: ServiceJourneyRecognitionPayload;
}) {
    if (!recognition.available) {
        return null;
    }

    const updateRecognition = () => {
        router.post(
            recognition.preference_url,
            {
                service_key: recognition.service_key,
                recognition_enabled: !recognition.enabled,
            },
            { preserveScroll: true },
        );
    };
    const markSeen = () => {
        router.post(
            recognition.seen_url,
            { service_key: recognition.service_key },
            { preserveScroll: true },
        );
    };

    return (
        <section
            className="space-y-4 rounded-md border bg-background p-4"
            aria-labelledby="journey-recognition-heading"
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div className="flex items-center gap-2">
                        <Trophy className="size-4" aria-hidden="true" />
                        <h2
                            id="journey-recognition-heading"
                            className="text-base font-semibold"
                        >
                            {recognition.title}
                        </h2>
                    </div>
                    <p className="mt-2 max-w-3xl text-sm text-muted-foreground">
                        {recognition.enabled
                            ? 'Recognition records verified service milestones. It never rewards financial outcomes, speed, or FSA-only work.'
                            : 'Your service progress is always visible. Enable recognition if you would like verified milestones and points recorded for this journey.'}
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    {recognition.enabled && recognition.new_badge_count > 0 ? (
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={markSeen}
                        >
                            {recognition.new_badge_count === 1
                                ? 'Mark milestone seen'
                                : 'Mark milestones seen'}
                        </Button>
                    ) : null}
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={updateRecognition}
                    >
                        {recognition.enabled
                            ? 'Pause recognition'
                            : 'Enable recognition'}
                    </Button>
                </div>
            </div>

            {recognition.enabled ? (
                <>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className="rounded-md border p-3">
                            <div className="text-xs text-muted-foreground">
                                Journey points
                            </div>
                            <div className="mt-1 text-lg font-semibold">
                                {recognition.points.total}
                            </div>
                            <div className="text-xs text-muted-foreground">
                                {recognition.points.milestone_count} verified
                                milestone
                                {recognition.points.milestone_count === 1
                                    ? ''
                                    : 's'}
                            </div>
                        </div>
                        {recognition.next_quest ? (
                            <div className="rounded-md border p-3">
                                <div className="text-xs text-muted-foreground">
                                    Next recognition
                                </div>
                                <div className="mt-1 text-sm font-medium">
                                    {recognition.next_quest.label} ·{' '}
                                    {recognition.next_quest.points} points
                                </div>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {recognition.next_quest.description}
                                </p>
                            </div>
                        ) : null}
                    </div>

                    {recognition.badges.length > 0 ? (
                        <div className="flex flex-wrap gap-2">
                            {recognition.badges.map((badge) => (
                                <Badge
                                    key={badge.id}
                                    variant={
                                        badge.seen_at ? 'secondary' : 'default'
                                    }
                                >
                                    <CheckCircle2
                                        className="size-3"
                                        aria-hidden="true"
                                    />
                                    {badge.label}
                                </Badge>
                            ))}
                        </div>
                    ) : null}
                </>
            ) : null}
        </section>
    );
}
