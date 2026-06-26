<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Settings\ProjectSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class PartnerAgreementController extends Controller
{
    public function __construct(private readonly ProjectSettings $settings) {}

    public function index(): Response
    {
        return Inertia::render('admin/partner-agreement/Index', [
            'group' => $this->settings->groupForUi(ProjectSettings::GROUP_PARTNER_AGREEMENT),
            'routes' => [
                'update' => route('admin.partner-agreement.update', absolute: false),
                'reset' => route('admin.partner-agreement.reset', absolute: false),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
        ]);

        $values = (array) $validated['settings'];
        $actor = $this->actor($request);
        $saved = 0;

        foreach ($this->definitions() as $definition) {
            $key = (string) $definition['key'];
            if (! array_key_exists($key, $values)) {
                continue;
            }

            validator(
                ['value' => $values[$key]],
                ['value' => $this->rulesFor($definition)],
                [],
                ['value' => (string) $definition['label']],
            )->validate();

            $this->settings->set($definition, $values[$key], $actor);
            $saved++;
        }

        if ($saved === 0) {
            throw ValidationException::withMessages([
                'settings' => 'No partner agreement terms were changed.',
            ]);
        }

        return to_route('admin.partner-agreement.index')->with('status', 'partner-agreement-saved');
    }

    public function reset(Request $request): RedirectResponse
    {
        $definitions = collect($this->definitions())
            ->keyBy(fn (array $definition): string => (string) $definition['key'])
            ->all();

        $validated = $request->validate([
            'key' => ['required', 'string', Rule::in(array_keys($definitions))],
        ]);

        $this->settings->revoke($definitions[(string) $validated['key']], $this->actor($request));

        return to_route('admin.partner-agreement.index')->with('status', 'partner-agreement-reset');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function definitions(): array
    {
        return $this->settings->definitionsForGroup(ProjectSettings::GROUP_PARTNER_AGREEMENT);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<int, mixed>
     */
    private function rulesFor(array $definition): array
    {
        return match ((string) $definition['type']) {
            ProjectSettings::TYPE_SECRET => ['required', 'string', 'min:4', 'max:10000'],
            ProjectSettings::TYPE_INTEGER => [
                'required',
                'integer',
                'min:'.(int) ($definition['min'] ?? 0),
                'max:'.(int) ($definition['max'] ?? PHP_INT_MAX),
            ],
            ProjectSettings::TYPE_BOOLEAN => ['required', 'boolean'],
            ProjectSettings::TYPE_EMAIL => ['nullable', 'email:rfc', 'max:255'],
            ProjectSettings::TYPE_URL => ['nullable', 'url', 'max:2048'],
            ProjectSettings::TYPE_SELECT => ['nullable', 'string', Rule::in((array) ($definition['options'] ?? []))],
            ProjectSettings::TYPE_STRING_LIST => ['nullable', 'string', 'max:4000'],
            ProjectSettings::TYPE_TEXT => ['nullable', 'string', 'max:20000'],
            default => ['nullable', 'string', 'max:2048'],
        };
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
