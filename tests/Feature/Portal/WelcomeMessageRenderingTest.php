<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\User;
use App\Services\Portal\Welcome\WelcomeMessageManager;
use App\Services\Portal\Welcome\WelcomeMessageRenderer;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WelcomeMessageRenderingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_placeholders_are_substituted_and_markdown_is_rendered(): void
    {
        [$client, $contact] = $this->clientWithContact('Aroha Ngata', 'Kauri Joinery');
        $this->publish('Kia ora {{contact_first_name}}, welcome to {{practice_name}}. We are glad to work with {{business_name}}. **Ready** when you are.');

        $rendered = app(WelcomeMessageRenderer::class)->renderForClient($client, $contact);

        $this->assertTrue($rendered['has_message']);
        $this->assertSame(1, $rendered['version']);
        $this->assertStringContainsString('Aroha', $rendered['html']);
        $this->assertStringContainsString('Future Shift Advisory', $rendered['html']);
        $this->assertStringContainsString('Kauri Joinery', $rendered['html']);
        $this->assertStringContainsString('<strong>Ready</strong>', $rendered['html']);
    }

    public function test_user_supplied_values_cannot_inject_markup(): void
    {
        [$client, $contact] = $this->clientWithContact(
            'Aroha Ngata',
            'Kauri <b>Joinery</b> **Ltd**',
        );
        $this->publish('Welcome to {{practice_name}}. We work with {{business_name}}. <script>alert(1)</script>');

        $rendered = app(WelcomeMessageRenderer::class)->renderForClient($client, $contact);

        $this->assertStringNotContainsString('<script>', $rendered['html']);
        $this->assertStringNotContainsString('<b>', $rendered['html']);
        $this->assertStringContainsString('Kauri Joinery Ltd', $rendered['html']);
        $this->assertStringNotContainsString('**', $rendered['html']);
    }

    public function test_empty_first_name_is_tidied_and_unknown_placeholders_are_dropped(): void
    {
        [$client] = $this->clientWithContact('Aroha Ngata', 'Kauri Joinery');
        $this->publish('Kia ora {{contact_first_name}}, welcome. {{unknown_token}}');

        // No contact -> first name resolves empty; the dangling comma space is tidied.
        $rendered = app(WelcomeMessageRenderer::class)->renderForClient($client, null);

        $this->assertStringContainsString('Kia ora, welcome.', $rendered['html']);
        $this->assertStringNotContainsString('{{', $rendered['html']);
        $this->assertStringNotContainsString('unknown_token', $rendered['html']);
    }

    public function test_no_active_message_returns_no_message(): void
    {
        [$client, $contact] = $this->clientWithContact('Aroha Ngata', 'Kauri Joinery');

        $rendered = app(WelcomeMessageRenderer::class)->renderForClient($client, $contact);

        $this->assertFalse($rendered['has_message']);
        $this->assertSame('', $rendered['html']);
        $this->assertNull($rendered['version']);
    }

    private function publish(string $body): void
    {
        $author = User::factory()->superAdmin()->create();
        app(WelcomeMessageManager::class)->publish($body, $author);
    }

    /**
     * @return array{0: Client, 1: User}
     */
    private function clientWithContact(string $contactName, string $tradingName): array
    {
        $contact = User::factory()->create([
            'name' => $contactName,
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '9429000000017',
            'legal_name' => 'Kauri Joinery Limited',
            'trading_name' => $tradingName,
            'entity_type' => 'NZ Limited Company',
            'gst_registered' => true,
            'filing_status' => 'registered',
            'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
            'primary_contact_user_id' => $contact->getKey(),
        ]);

        return [$client, $contact];
    }
}
