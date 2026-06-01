<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Integration\IntegrationActivationResolver;
use App\Services\Integration\IntegrationCredentials;
use App\Services\Integration\IntegrationRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class IntegrationCredentialController extends Controller
{
    public function __construct(
        private readonly IntegrationActivationResolver $activations,
        private readonly IntegrationCredentials $credentials,
        private readonly IntegrationRegistry $registry,
    ) {}

    public function index(): Response
    {
        return Inertia::render('admin/credentials/Index', [
            'credentials' => $this->credentials->registryRows()
                ->map(fn (array $row): array => [
                    ...$row,
                    'credentials_ready' => $this->activations->readiness((string) $row['integration_key']),
                    'effective_live' => $this->activations->isLive((string) $row['integration_key']),
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $fields = $this->allowedFields();
        $validated = $request->validate([
            'integration_key' => ['required', 'string', Rule::in(array_keys($fields))],
            'field' => ['required', 'string'],
            'value' => ['required', 'string', 'min:4', 'max:10000'],
        ]);

        $integrationKey = (string) $validated['integration_key'];
        $field = (string) $validated['field'];
        abort_unless(in_array($field, $fields[$integrationKey] ?? [], true), 422);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $this->credentials->set($integrationKey, $field, (string) $validated['value'], $user);

        return to_route('admin.integration-credentials.index')->with('status', 'credential-saved');
    }

    public function revoke(Request $request): RedirectResponse
    {
        $fields = $this->allowedFields();
        $validated = $request->validate([
            'integration_key' => ['required', 'string', Rule::in(array_keys($fields))],
            'field' => ['required', 'string'],
        ]);

        $integrationKey = (string) $validated['integration_key'];
        $field = (string) $validated['field'];
        abort_unless(in_array($field, $fields[$integrationKey] ?? [], true), 422);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $this->credentials->revoke($integrationKey, $field, $user);

        return to_route('admin.integration-credentials.index')->with('status', 'credential-revoked');
    }

    public function activate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'integration_key' => ['required', 'string', Rule::in(array_keys($this->allowedFields()))],
        ]);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $this->activations->activate((string) $validated['integration_key'], $user);

        return to_route('admin.integration-credentials.index')->with('status', 'integration-activated');
    }

    public function deactivate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'integration_key' => ['required', 'string', Rule::in(array_keys($this->allowedFields()))],
        ]);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $this->activations->deactivate((string) $validated['integration_key'], $user);

        return to_route('admin.integration-credentials.index')->with('status', 'integration-deactivated');
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function allowedFields(): array
    {
        return $this->registry->all()
            ->filter(fn (array $integration): bool => ($integration['managed_via'] ?? 'vault') === 'vault')
            ->mapWithKeys(fn (array $integration): array => [
                $integration['integration_key'] => $this->registry->credentialFields((string) $integration['integration_key']),
            ])
            ->all();
    }
}
