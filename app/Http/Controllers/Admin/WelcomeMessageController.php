<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WelcomeMessage;
use App\Services\Portal\Welcome\WelcomeMessageManager;
use App\Services\Portal\Welcome\WelcomeMessageRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class WelcomeMessageController extends Controller
{
    public function __construct(
        private readonly WelcomeMessageManager $messages,
        private readonly WelcomeMessageRenderer $renderer,
    ) {}

    public function index(): Response
    {
        $current = $this->messages->current();

        return Inertia::render('admin/welcome-message/Index', [
            'current' => $current instanceof WelcomeMessage ? [
                'version' => $current->version,
                'body' => $current->body,
                'activated_at' => $current->activated_at?->toIso8601String(),
            ] : null,
            'preview' => $this->renderer->renderPreview(),
            'placeholders' => $this->placeholders(),
            'history' => $this->messages->history()
                ->map(fn (WelcomeMessage $message): array => [
                    'id' => $message->id,
                    'version' => $message->version,
                    'is_active' => $message->is_active,
                    'activated_at' => $message->activated_at?->toIso8601String(),
                    'created_by' => $message->createdBy?->name,
                    'characters' => mb_strlen((string) $message->body),
                ])
                ->all(),
            'storeUrl' => route('admin.welcome-message.store', absolute: false),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:20', 'max:8000'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $this->messages->publish($validated['body'], $user);

        return to_route('admin.welcome-message.index')->with('status', 'welcome-message-published');
    }

    /**
     * @return array<int, array{token: string, description: string}>
     */
    private function placeholders(): array
    {
        return [
            ['token' => '{{contact_first_name}}', 'description' => "The signed-in client contact's first name."],
            ['token' => '{{business_name}}', 'description' => 'Trading name, falling back to the legal name.'],
            ['token' => '{{practice_name}}', 'description' => 'Your practice name (Future Shift Advisory).'],
            ['token' => '{{advisor_name}}', 'description' => 'Lead advisor, falling back to "your advisory team".'],
            ['token' => '{{engagement_type_label}}', 'description' => 'e.g. Standard Advisory, NPO, Due Diligence.'],
        ];
    }
}
