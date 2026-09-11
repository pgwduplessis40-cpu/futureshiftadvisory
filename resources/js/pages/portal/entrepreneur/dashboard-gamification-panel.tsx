import { router } from '@inertiajs/react';
import { Flame, Trophy } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatNzDate } from '@/lib/formatters';
import type { GamificationPayload } from './Dashboard';

export function DashboardGamificationPanel({
    gamification,
    isIdeaValidationOnly,
    ideaValidationSubmitted,
}: {
    gamification: GamificationPayload;
    isIdeaValidationOnly: boolean;
    ideaValidationSubmitted: boolean;
}) {
    const badges = gamification.badges ?? [];
    const newBadgeCount = gamification.new_badge_count ?? 0;
    const completionPercent = isIdeaValidationOnly
        ? ideaValidationSubmitted
            ? 100
            : 0
        : (gamification.plan_completion?.percent ?? 0);
    const markSeen = () => {
        if (!gamification.seen_url) {
            return;
        }

        router.post(gamification.seen_url, {}, { preserveScroll: true });
    };

    return (
        <section
            className="space-y-3"
            data-co-browse-target="entrepreneur.dashboard.journey"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <Trophy className="size-4" aria-hidden="true" />
                    <h2 className="text-base font-semibold">Journey</h2>
                </div>
                {newBadgeCount > 0 ? (
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={markSeen}
                    >
                        {newBadgeCount === 1
                            ? 'Mark badge seen'
                            : 'Mark badges seen'}
                    </Button>
                ) : null}
            </div>

            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div className="rounded-md border bg-background p-4">
                    <div className="text-xs text-muted-foreground">Level</div>
                    <div className="mt-2 text-sm font-medium">
                        {journeyLevelLabel(gamification.current_level)}
                    </div>
                </div>
                <div
                    className="rounded-md border bg-background p-4"
                    data-co-browse-target="entrepreneur.dashboard.progress"
                >
                    <div className="text-xs text-muted-foreground">
                        {isIdeaValidationOnly
                            ? 'Idea Validation completion'
                            : 'Plan completion'}
                    </div>
                    <div className="mt-2 text-sm font-medium">
                        {completionPercent}%
                    </div>
                    <div className="mt-3 h-2 overflow-hidden rounded-full bg-muted">
                        <div
                            className="h-full rounded-full bg-emerald-500"
                            style={{
                                width: `${Math.min(100, Math.max(0, completionPercent))}%`,
                            }}
                        />
                    </div>
                </div>
                <div className="rounded-md border bg-background p-4">
                    <div className="text-xs text-muted-foreground">
                        Journey points
                    </div>
                    <div className="mt-2 text-sm font-medium">
                        {gamification.points?.total ?? 0} points
                    </div>
                    <div className="mt-1 text-xs text-muted-foreground">
                        {gamification.points?.milestone_count ?? 0} verified{' '}
                        milestone
                        {(gamification.points?.milestone_count ?? 0) === 1
                            ? ''
                            : 's'}
                    </div>
                </div>
                <div className="rounded-md border bg-background p-4">
                    <div className="flex items-center gap-2 text-xs text-muted-foreground">
                        <Flame className="size-3.5" aria-hidden="true" />
                        Streak
                    </div>
                    <div className="mt-2 text-sm font-medium">
                        {gamification.current_streak ?? 0} days
                    </div>
                    <div className="mt-1 text-xs text-muted-foreground">
                        Last active{' '}
                        {formatDate(gamification.last_active_at ?? null)}
                    </div>
                </div>
            </div>

            {gamification.next_quest ? (
                <div className="flex flex-wrap items-center justify-between gap-3 rounded-md border bg-muted/30 p-3">
                    <div>
                        <div className="text-sm font-medium">
                            Next quest: {gamification.next_quest.label}
                        </div>
                        <div className="mt-1 text-sm text-muted-foreground">
                            {gamification.next_quest.description}
                        </div>
                    </div>
                    <Badge variant="outline">
                        {gamification.next_quest.points} points
                    </Badge>
                </div>
            ) : null}

            {badges.length > 0 ? (
                <div className="flex flex-wrap gap-2">
                    {badges.map((badge) => (
                        <Badge
                            key={badge.id}
                            variant={badge.seen_at ? 'secondary' : 'default'}
                            title={
                                badge.earned_at_estimated
                                    ? `${formatDate(badge.earned_at)} estimated`
                                    : formatDate(badge.earned_at)
                            }
                        >
                            {badge.label}
                        </Badge>
                    ))}
                </div>
            ) : gamification.next_milestone ? (
                <div className="text-sm text-muted-foreground">
                    Next: {nextMilestoneLabel(gamification.next_milestone)}
                </div>
            ) : null}
        </section>
    );
}

function journeyLevelLabel(
    level: GamificationPayload['current_level'] | undefined,
): string {
    if (!level) {
        return '-';
    }

    if (level.stage === 'onboarding') {
        return level.phase
            ? `Getting started phase ${level.phase}`
            : 'Getting started';
    }

    return level.label;
}

function nextMilestoneLabel(
    milestone: NonNullable<GamificationPayload['next_milestone']>,
): string {
    return milestone.key === 'idea_validated'
        ? 'Idea validation'
        : milestone.label;
}

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    return formatNzDate(value);
}
