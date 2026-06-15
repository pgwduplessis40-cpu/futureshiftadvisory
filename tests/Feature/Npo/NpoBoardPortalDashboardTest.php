<?php

declare(strict_types=1);

namespace Tests\Feature\Npo;

use App\Enums\ClientStatus;
use App\Enums\EngagementType;
use App\Enums\NpoEngagementSubType;
use App\Enums\NpoLegalStructure;
use App\Enums\ReportType;
use App\Models\Client;
use App\Models\Document;
use App\Models\NpoBoardMember;
use App\Models\NpoEngagement;
use App\Models\Report;
use App\Models\User;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class NpoBoardPortalDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_board_member_dashboard_redirects_and_renders_scoped_resources(): void
    {
        [$boardMember, $client, $engagement] = $this->boardPortal();

        Report::query()->create([
            'client_id' => $client->id,
            'npo_engagement_id' => $engagement->id,
            'type' => ReportType::NpoHealth,
            'title' => 'Board health pack',
            'generated_at' => now(),
            'review_status' => 'reviewed',
            'reviewed_at' => now(),
        ]);
        Report::query()->create([
            'client_id' => $client->id,
            'npo_engagement_id' => $engagement->id,
            'type' => ReportType::NpoAdvisor,
            'title' => 'Advisor only draft',
            'generated_at' => now(),
            'review_status' => 'pending_review',
        ]);
        Document::query()->create([
            'client_id' => $client->id,
            'npo_engagement_id' => $engagement->id,
            'category' => Document::CATEGORY_NPO_BOARD_RECORD,
            'original_filename' => 'Board pack.pdf',
            'stored_path' => 'tests/board-pack.pdf',
            'byte_size' => 1024,
            'mime_type' => 'application/pdf',
            'sha256' => str_repeat('a', 64),
            'scanner_result' => Document::SCANNER_CLEAN,
        ]);
        Document::query()->create([
            'client_id' => $client->id,
            'npo_engagement_id' => $engagement->id,
            'category' => Document::CATEGORY_NPO_BOARD_RECORD,
            'original_filename' => 'Pending scan.pdf',
            'stored_path' => 'tests/pending-scan.pdf',
            'byte_size' => 1024,
            'mime_type' => 'application/pdf',
            'sha256' => str_repeat('b', 64),
            'scanner_result' => Document::SCANNER_PENDING,
        ]);

        $this->actingAsMfa($boardMember)
            ->get(route('dashboard'))
            ->assertRedirect(route('portal.npo-board.dashboard', absolute: false));

        $this->actingAsMfa($boardMember)
            ->get(route('portal.npo-board.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('portal/npo-board/Dashboard')
                ->where('client.legal_name', 'Board Portal Trust')
                ->where('membership.treasurer', true)
                ->where('engagement.sub_type', 'Standard NPO')
                ->has('reports', 1)
                ->where('reports.0.type', 'NPO Health Report')
                ->has('documents', 1)
                ->where('documents.0.filename', 'Board pack.pdf')
                ->where('links.calendar', route('portal.calendar.index', absolute: false))
                ->where('links.messages', route('portal.messages.index', absolute: false)));
    }

    public function test_board_member_calendar_uses_their_npo_client_scope(): void
    {
        [$boardMember] = $this->boardPortal();

        $this->actingAsMfa($boardMember)
            ->get(route('portal.calendar.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('calendar/Index')
                ->where('title', 'Client calendar')
                ->where('subtitle', 'Dated meetings, documents, reports, proposals, wellbeing, and deadlines for Board Portal Trust.'));
    }

    /**
     * @return array{0: User, 1: Client, 2: NpoEngagement}
     */
    private function boardPortal(): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => 'board-portal-advisor@example.test',
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $boardMember = User::factory()->withTwoFactor()->create([
            'email' => 'board-member@example.test',
            'user_type' => User::TYPE_NPO_BOARD_MEMBER,
            'primary_role' => User::TYPE_NPO_BOARD_MEMBER,
        ]);
        $boardMember->assignRole(User::TYPE_NPO_BOARD_MEMBER);

        app(RequestContext::class)->apply('system', [], (string) $advisor->getKey());

        $client = Client::query()->create([
            'engagement_type' => EngagementType::NPO,
            'status' => ClientStatus::ACTIVE,
            'nzbn' => '9429000001111',
            'legal_name' => 'Board Portal Trust',
            'data_quality' => Client::DATA_QUALITY_MEDIUM,
            'created_by_user_id' => $advisor->getKey(),
        ]);

        $engagement = NpoEngagement::query()->create([
            'client_id' => $client->id,
            'sub_type' => NpoEngagementSubType::StandardNpo,
            'legal_structure' => NpoLegalStructure::RegisteredCharity,
        ]);

        NpoBoardMember::query()->create([
            'client_id' => $client->id,
            'npo_engagement_id' => $engagement->id,
            'user_id' => $boardMember->getKey(),
            'treasurer' => true,
            'active' => true,
            'created_by_user_id' => $advisor->getKey(),
        ]);

        return [$boardMember, $client, $engagement];
    }
}
