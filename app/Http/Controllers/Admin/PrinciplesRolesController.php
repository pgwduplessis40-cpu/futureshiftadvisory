<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformGovernanceVersion;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class PrinciplesRolesController extends Controller
{
    public function __construct(
        private readonly AuditWriter $audit,
    ) {}

    public function index(): Response
    {
        $current = PlatformGovernanceVersion::query()
            ->active()
            ->latest('version')
            ->first();

        return Inertia::render('admin/principles-roles/Index', [
            'current' => $current instanceof PlatformGovernanceVersion
                ? $this->versionPayload($current)
                : null,
            'defaults' => [
                'principles' => $this->defaultPrinciples(),
                'roles' => $this->defaultRoles(),
            ],
            'history' => PlatformGovernanceVersion::query()
                ->with('createdBy:id,name')
                ->latest('version')
                ->limit(20)
                ->get()
                ->map(fn (PlatformGovernanceVersion $version): array => [
                    ...$this->versionPayload($version),
                    'created_by' => $version->createdBy?->name,
                ])
                ->values()
                ->all(),
            'storeUrl' => route('admin.principles-roles.store', absolute: false),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'principles_text' => ['required', 'string', 'min:20', 'max:30000'],
            'roles_text' => ['required', 'string', 'min:5', 'max:12000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $principles = $this->lines((string) $validated['principles_text']);
        $roles = $this->lines((string) $validated['roles_text']);

        if ($principles === []) {
            throw ValidationException::withMessages([
                'principles_text' => 'Add at least one platform principle.',
            ]);
        }

        if ($roles === []) {
            throw ValidationException::withMessages([
                'roles_text' => 'Add at least one role.',
            ]);
        }

        /** @var User $user */
        $user = $request->user();

        $version = DB::transaction(function () use ($principles, $roles, $user, $validated): PlatformGovernanceVersion {
            $previous = PlatformGovernanceVersion::query()
                ->active()
                ->lockForUpdate()
                ->latest('version')
                ->first();
            $nextVersion = ((int) (PlatformGovernanceVersion::query()->max('version') ?? 0)) + 1;

            PlatformGovernanceVersion::query()
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $version = PlatformGovernanceVersion::query()->create([
                'version' => $nextVersion,
                'principles' => $principles,
                'roles' => $roles,
                'notes' => $validated['notes'] ?? null,
                'is_active' => true,
                'activated_at' => now(),
                'created_by_user_id' => $user->getKey(),
            ]);

            $this->audit->record(
                'platform_governance.updated',
                subject: $version,
                actor: $user,
                before: $previous instanceof PlatformGovernanceVersion ? [
                    'version' => $previous->version,
                    'principles' => $previous->principles,
                    'roles' => $previous->roles,
                ] : null,
                after: [
                    'version' => $version->version,
                    'principles_count' => count($principles),
                    'roles_count' => count($roles),
                ],
            );

            return $version;
        });

        return to_route('admin.principles-roles.index')
            ->with('status', 'platform-governance-updated')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Principles & Roles updated to version '.$version->version.'.',
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function versionPayload(PlatformGovernanceVersion $version): array
    {
        return [
            'id' => $version->id,
            'version' => $version->version,
            'principles' => array_values((array) $version->principles),
            'roles' => array_values((array) $version->roles),
            'notes' => $version->notes,
            'is_active' => $version->is_active,
            'activated_at' => $version->activated_at?->toIso8601String(),
            'created_at' => $version->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function lines(string $value): array
    {
        return collect(preg_split('/\R/u', $value) ?: [])
            ->map(fn (string $line): string => trim(preg_replace('/^[\s\-\*\x{2022}]+/u', '', $line) ?? ''))
            ->filter(fn (string $line): bool => $line !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function defaultPrinciples(): array
    {
        return [
            'Every AI output on the Future Shift Advisory platform - analysis, guidance, scoring, recommendation, document review, or resource - must be honest, evidence-based, accurate, free from bias, and truthful.',
            'The reputation of Future Shift Advisory rests on the quality and integrity of the advice this platform provides.',
            'An AI that flatters, inflates, or misleads - even with good intentions - causes harm to the people who rely on it and damage to the practice that cannot be undone.',
            'Accuracy and honesty are not in conflict with care and encouragement. The platform delivers both.',
            'Entrepreneurs are often making life-defining decisions. Many have no prior business experience. If the AI inflates their readiness or misrepresents plan quality, FSA has misled a vulnerable person at a critical life decision point. The human cost is real. The reputational cost to FSA is severe.',
            'Accuracy discrepancies are NEVER suppressed. Not for any reason. Every contradiction between claimed and documented facts is surfaced to the advisor.',
            'Entrepreneurs are often making life-defining decisions. The platform is honest, evidence-based, accurate, free from bias, and truthful always.',
            'Honest assessments are delivered with genuine encouragement to improve. Never one without the other.',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function defaultRoles(): array
    {
        return [
            'Mentor | Advisor | Partner',
            'FA&P (Financial Planning and Analysis)',
            'CFO (Chief Financial Officer)',
            'FM (Finance Manager)',
            'COO (Chief Operating Officer)',
            'BA (Business Analyst)',
            'FA (Financial Analyst)',
            'Due diligence professional',
        ];
    }
}
