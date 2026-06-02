<?php

declare(strict_types=1);

namespace App\Services\Portal\Welcome;

use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\User;
use App\Models\WelcomeMessage;
use Illuminate\Support\Str;

final class WelcomeMessageRenderer
{
    public const DEFAULT_PRACTICE_NAME = 'Future Shift Advisory';

    public function __construct(private readonly WelcomeMessageManager $messages) {}

    /**
     * Render the active welcome message for a specific client and signed-in contact.
     *
     * @return array{has_message: bool, html: string, version: int|null}
     */
    public function renderForClient(Client $client, ?User $contact): array
    {
        return $this->render($this->resolversForClient($client, $contact));
    }

    /**
     * Render the active message with representative sample values, for the admin editor.
     *
     * @return array{has_message: bool, html: string, version: int|null}
     */
    public function renderPreview(): array
    {
        return $this->render([
            'contact_first_name' => static fn (): string => 'Aroha',
            'business_name' => static fn (): string => 'Riverside Joinery Ltd',
            'practice_name' => static fn (): string => self::DEFAULT_PRACTICE_NAME,
            'advisor_name' => static fn (): string => 'Sam Whiteley',
            'engagement_type_label' => static fn (): string => 'Standard Advisory',
        ]);
    }

    /**
     * @param  array<string, callable(): string>  $resolvers
     * @return array{has_message: bool, html: string, version: int|null}
     */
    private function render(array $resolvers): array
    {
        $message = $this->messages->current();

        if (! $message instanceof WelcomeMessage) {
            return ['has_message' => false, 'html' => '', 'version' => null];
        }

        $source = $this->substitute((string) $message->body, $resolvers);

        $html = Str::markdown($source, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return [
            'has_message' => trim(strip_tags($html)) !== '',
            'html' => $html,
            'version' => $message->version,
        ];
    }

    /**
     * @param  array<string, callable(): string>  $resolvers
     */
    private function substitute(string $body, array $resolvers): string
    {
        $replaced = preg_replace_callback(
            '/\{\{\s*([a-z_]+)\s*\}\}/',
            function (array $matches) use ($resolvers): string {
                $key = $matches[1];

                if (! isset($resolvers[$key])) {
                    return '';
                }

                return $this->sanitizeValue((string) $resolvers[$key]());
            },
            $body,
        ) ?? $body;

        // Tidy artefacts left when a placeholder resolves empty, e.g. "Kia ora ," -> "Kia ora,".
        $replaced = preg_replace('/[ \t]+([,.;:!?])/', '$1', $replaced) ?? $replaced;
        $replaced = preg_replace('/[ \t]{2,}/', ' ', $replaced) ?? $replaced;

        return $replaced;
    }

    /**
     * Values are admin-authored templates rendered into markdown, but the substituted
     * values come from user data — strip any markup/markdown control characters so a
     * client name can never inject formatting or markup.
     */
    private function sanitizeValue(string $value): string
    {
        $value = strip_tags($value);
        $value = str_replace(['{{', '}}', '\\', '`', '*', '_', '<', '>'], '', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim(Str::limit($value, 120, ''));
    }

    /**
     * @return array<string, callable(): string>
     */
    private function resolversForClient(Client $client, ?User $contact): array
    {
        return [
            'contact_first_name' => static function () use ($contact): string {
                if (! $contact instanceof User) {
                    return '';
                }

                $name = trim((string) $contact->name);
                $first = trim((string) Str::of($name)->before(' '));

                return $first !== '' ? $first : $name;
            },
            'business_name' => static fn (): string => (string) ($client->trading_name ?: $client->legal_name),
            'practice_name' => static fn (): string => self::DEFAULT_PRACTICE_NAME,
            'advisor_name' => fn (): string => $this->leadAdvisorName($client),
            'engagement_type_label' => static function () use ($client): string {
                $engagementType = $client->engagement_type instanceof EngagementType
                    ? $client->engagement_type
                    : EngagementType::tryFrom((string) $client->engagement_type);

                return $engagementType?->label() ?? '';
            },
        ];
    }

    private function leadAdvisorName(Client $client): string
    {
        $userId = ClientTeamMember::query()
            ->where('client_id', $client->getKey())
            ->where('role', 'lead_advisor')
            ->latest()
            ->value('user_id');

        $name = $userId !== null
            ? User::query()->whereKey($userId)->value('name')
            : null;

        return is_string($name) && trim($name) !== '' ? trim($name) : 'your advisory team';
    }
}
