<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class AuditTrailController extends Controller
{
    public function __invoke(Request $request): Response
    {
        Gate::authorize('viewAny', AuditEvent::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'action' => ['nullable', 'string', 'max:120'],
            'actor' => ['nullable', 'string', 'max:120'],
            'subject' => ['nullable', 'string', 'max:120'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $events = $this->applyFilters(AuditEvent::query(), $filters)
            ->latest('occurred_at')
            ->paginate(50)
            ->withQueryString();

        $actorUsers = $this->actorUsersFor(
            $events->getCollection()
                ->pluck('actor_user_key')
                ->filter()
                ->map(fn (mixed $key): string => (string) $key)
                ->unique()
                ->values()
                ->all(),
        );

        $events->through(fn (AuditEvent $event): array => $this->eventPayload($event, $actorUsers));

        return Inertia::render('admin/audit-trail/Index', [
            'events' => $events,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'action' => $filters['action'] ?? '',
                'actor' => $filters['actor'] ?? '',
                'subject' => $filters['subject'] ?? '',
                'date_from' => $filters['date_from'] ?? '',
                'date_to' => $filters['date_to'] ?? '',
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        if ($this->filled($filters, 'q')) {
            $term = (string) $filters['q'];
            $actorKeys = $this->actorKeysMatching($term);

            $query->where(function (Builder $inner) use ($term, $actorKeys): void {
                $this->applyLikeAny($inner, [
                    'action',
                    'actor_role',
                    'actor_user_key',
                    'subject_type',
                    'subject_id',
                    'ip',
                    'user_agent',
                ], $term);

                $inner->orWhere('request_id', $term)
                    ->orWhere('client_id', $term);

                if ($actorKeys !== []) {
                    $inner->orWhereIn('actor_user_key', $actorKeys);
                }
            });
        }

        if ($this->filled($filters, 'action')) {
            $this->applyLikeAny($query, ['action'], (string) $filters['action']);
        }

        if ($this->filled($filters, 'actor')) {
            $actor = (string) $filters['actor'];
            $actorKeys = $this->actorKeysMatching($actor);

            $query->where(function (Builder $inner) use ($actor, $actorKeys): void {
                $this->applyLikeAny($inner, ['actor_role', 'actor_user_key'], $actor);

                if ($actorKeys !== []) {
                    $inner->orWhereIn('actor_user_key', $actorKeys);
                }
            });
        }

        if ($this->filled($filters, 'subject')) {
            $this->applyLikeAny($query, ['subject_type', 'subject_id', 'client_id'], (string) $filters['subject']);
        }

        if ($this->filled($filters, 'date_from')) {
            $query->where('occurred_at', '>=', Carbon::parse((string) $filters['date_from'])->startOfDay());
        }

        if ($this->filled($filters, 'date_to')) {
            $query->where('occurred_at', '<=', Carbon::parse((string) $filters['date_to'])->endOfDay());
        }

        return $query;
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function applyLikeAny(Builder $query, array $columns, string $term): void
    {
        $needle = '%'.strtolower($term).'%';

        $query->where(function (Builder $inner) use ($columns, $needle): void {
            foreach ($columns as $column) {
                $inner->orWhereRaw("LOWER(CAST({$column} AS TEXT)) LIKE ?", [$needle]);
            }
        });
    }

    /**
     * @return array<int, string>
     */
    private function actorKeysMatching(string $term): array
    {
        $needle = '%'.strtolower($term).'%';

        return User::query()
            ->whereRaw('LOWER(name) LIKE ?', [$needle])
            ->orWhereRaw('LOWER(email) LIKE ?', [$needle])
            ->limit(100)
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<string, array{name: string, email: string, user_type: string|null}>
     */
    private function actorUsersFor(array $keys): array
    {
        $numericKeys = array_values(array_filter(
            $keys,
            fn (string $key): bool => ctype_digit($key),
        ));

        if ($numericKeys === []) {
            return [];
        }

        return User::query()
            ->whereIn('id', $numericKeys)
            ->get(['id', 'name', 'email', 'user_type'])
            ->mapWithKeys(fn (User $user): array => [
                (string) $user->getKey() => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'user_type' => $user->user_type,
                ],
            ])
            ->all();
    }

    /**
     * @param  array<string, array{name: string, email: string, user_type: string|null}>  $actorUsers
     * @return array<string, mixed>
     */
    private function eventPayload(AuditEvent $event, array $actorUsers): array
    {
        $actorKey = is_string($event->actor_user_key) ? $event->actor_user_key : null;
        $actorUser = $actorKey === null ? null : ($actorUsers[$actorKey] ?? null);
        $actorLabel = $actorUser === null
            ? ($event->actor_role ?: 'system')
            : $actorUser['name'].' <'.$actorUser['email'].'>';

        return [
            'id' => $event->id,
            'occurred_at' => $event->occurred_at?->toIso8601String(),
            'occurred_at_label' => $event->occurred_at
                ? $event->occurred_at->copy()->timezone(config('app.timezone'))->format('d M Y, g:i A')
                : null,
            'action' => $event->action,
            'actor' => [
                'label' => $actorLabel,
                'role' => $event->actor_role,
                'user_key' => $actorKey,
                'user_id' => $event->actor_user_id,
                'name' => $actorUser['name'] ?? null,
                'email' => $actorUser['email'] ?? null,
                'user_type' => $actorUser['user_type'] ?? null,
            ],
            'client_id' => $event->client_id,
            'subject_type' => $event->subject_type,
            'subject_id' => $event->subject_id,
            'before' => $event->before,
            'after' => $event->after,
            'ip' => $event->ip,
            'user_agent' => $event->user_agent,
            'request_id' => $event->request_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filled(array $filters, string $key): bool
    {
        return isset($filters[$key]) && is_string($filters[$key]) && trim($filters[$key]) !== '';
    }
}
