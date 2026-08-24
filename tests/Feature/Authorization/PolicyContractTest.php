<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Enums\EngagementType;
use App\Enums\Permission;
use App\Enums\SurveyAssignmentStatus;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\EntrepreneurProfile;
use App\Models\KnowledgeEntry;
use App\Models\KnowledgeEntryDraft;
use App\Models\SurveyAssignment;
use App\Models\Template;
use App\Models\User;
use App\Policies\AuditEventPolicy;
use App\Policies\ClientPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\EntrepreneurProfilePolicy;
use App\Policies\KnowledgeEntryDraftPolicy;
use App\Policies\KnowledgeEntryPolicy;
use App\Policies\NotificationPolicy;
use App\Policies\ProspectLeadPolicy;
use App\Policies\QuestionnairePolicy;
use App\Policies\SurveyAssignmentPolicy;
use App\Policies\SurveyPolicy;
use App\Policies\TemplatePolicy;
use App\Policies\TermsVersionPolicy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PolicyContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_document_notification_prospect_and_questionnaire_permissions_remain_separate(): void
    {
        $user = $this->userWithPermissions(
            Permission::DOCUMENTS_VIEW,
            Permission::DOCUMENTS_UPLOAD,
            Permission::DOCUMENTS_MANAGE,
            Permission::DOCUMENTS_VERIFY,
            Permission::NOTIFICATIONS_VIEW,
            Permission::NOTIFICATIONS_MANAGE,
            Permission::PROSPECTS_VIEW,
            Permission::PROSPECTS_TRIAGE,
            Permission::QUESTIONNAIRES_VIEW,
            Permission::QUESTIONNAIRES_DRAFT,
            Permission::QUESTIONNAIRES_PUBLISH,
        );
        $withoutPermissions = $this->userWithPermissions();

        $document = new DocumentPolicy;
        $this->assertTrue($document->viewAny($user));
        $this->assertTrue($document->view($user));
        $this->assertTrue($document->create($user));
        $this->assertTrue($document->update($user));
        $this->assertTrue($document->delete($user));
        $this->assertTrue($document->verify($user));
        $this->assertFalse($document->viewAny($withoutPermissions));
        $this->assertFalse($document->create($withoutPermissions));

        $notifications = new NotificationPolicy;
        $this->assertTrue($notifications->viewAny($user));
        $this->assertTrue($notifications->view($user));
        $this->assertTrue($notifications->create($user));
        $this->assertTrue($notifications->update($user));
        $this->assertTrue($notifications->delete($user));
        $this->assertFalse($notifications->view($withoutPermissions));

        $prospects = new ProspectLeadPolicy;
        $this->assertTrue($prospects->viewAny($user));
        $this->assertTrue($prospects->view($user));
        $this->assertTrue($prospects->create($user));
        $this->assertTrue($prospects->update($user));
        $this->assertTrue($prospects->triage($user));
        $this->assertFalse($prospects->triage($withoutPermissions));

        $questionnaires = new QuestionnairePolicy;
        $this->assertTrue($questionnaires->viewAny($user));
        $this->assertTrue($questionnaires->view($user));
        $this->assertTrue($questionnaires->create($user));
        $this->assertTrue($questionnaires->update($user));
        $this->assertTrue($questionnaires->publish($user));
        $this->assertTrue($questionnaires->delete($user));
        $this->assertFalse($questionnaires->publish($withoutPermissions));
    }

    public function test_audit_records_are_read_only_even_for_authorized_users(): void
    {
        $reader = $this->userWithPermissions(Permission::AUDIT_VIEW);
        $policy = new AuditEventPolicy;

        $this->assertTrue($policy->viewAny($reader));
        $this->assertTrue($policy->view($reader));
        $this->assertFalse($policy->create($reader));
        $this->assertFalse($policy->update($reader));
        $this->assertFalse($policy->delete($reader));
    }

    public function test_client_policy_requires_the_right_permission_and_subject_access(): void
    {
        $client = $this->client('Policy boundary client');
        $advisor = $this->userWithPermissions(Permission::CLIENTS_VIEW, Permission::CLIENTS_MANAGE);
        $this->assignClient($advisor, $client);
        $unassignedAdvisor = $this->userWithPermissions(Permission::CLIENTS_VIEW, Permission::CLIENTS_MANAGE);
        $readOnlyAdvisor = $this->userWithPermissions(Permission::CLIENTS_VIEW);
        $superAdmin = $this->superAdmin();
        $policy = new ClientPolicy;

        $this->assertTrue($policy->viewAny($advisor));
        $this->assertTrue($policy->view($advisor, $client));
        $this->assertTrue($policy->create($advisor));
        $this->assertTrue($policy->update($advisor, $client));
        $this->assertTrue($policy->delete($advisor, $client));
        $this->assertFalse($policy->view($unassignedAdvisor, $client));
        $this->assertFalse($policy->update($readOnlyAdvisor, $client));
        $this->assertTrue($policy->view($superAdmin, $client));
    }

    public function test_knowledge_entries_are_limited_to_the_author_unless_the_user_is_a_super_admin(): void
    {
        $author = $this->userWithPermissions(
            Permission::KNOWLEDGE_VIEW,
            Permission::KNOWLEDGE_MANAGE,
            Permission::KNOWLEDGE_PUBLISH,
        );
        $otherAuthor = $this->userWithPermissions(
            Permission::KNOWLEDGE_VIEW,
            Permission::KNOWLEDGE_MANAGE,
            Permission::KNOWLEDGE_PUBLISH,
        );
        $entry = new KnowledgeEntry(['author_user_id' => $author->getKey()]);
        $draft = new KnowledgeEntryDraft(['author_user_id' => $author->getKey()]);
        $entries = new KnowledgeEntryPolicy;
        $drafts = new KnowledgeEntryDraftPolicy;

        $this->assertTrue($entries->viewAny($author));
        $this->assertTrue($entries->view($author, $entry));
        $this->assertTrue($entries->create($author));
        $this->assertTrue($entries->update($author, $entry));
        $this->assertTrue($entries->delete($author, $entry));
        $this->assertTrue($entries->publish($author, $entry));
        $this->assertFalse($entries->update($otherAuthor, $entry));
        $this->assertTrue($entries->view($author));

        $this->assertTrue($drafts->viewAny($author));
        $this->assertTrue($drafts->view($author, $draft));
        $this->assertTrue($drafts->create($author));
        $this->assertTrue($drafts->update($author, $draft));
        $this->assertTrue($drafts->delete($author, $draft));
        $this->assertFalse($drafts->delete($otherAuthor, $draft));
        $this->assertTrue($drafts->view($author));
        $this->assertTrue($entries->update($this->superAdmin(), $entry));
        $this->assertTrue($drafts->delete($this->superAdmin(), $draft));
    }

    public function test_template_drafts_require_management_permission(): void
    {
        $viewer = $this->userWithPermissions(Permission::TEMPLATE_VIEW);
        $manager = $this->userWithPermissions(Permission::TEMPLATE_VIEW, Permission::TEMPLATE_MANAGE);
        $draft = new Template(['status' => Template::STATUS_DRAFT]);
        $active = new Template(['status' => Template::STATUS_ACTIVE]);
        $policy = new TemplatePolicy;

        $this->assertTrue($policy->viewAny($viewer));
        $this->assertFalse($policy->view($viewer, $draft));
        $this->assertTrue($policy->view($viewer, $active));
        $this->assertTrue($policy->view($viewer));
        $this->assertFalse($policy->create($viewer));
        $this->assertTrue($policy->view($manager, $draft));
        $this->assertTrue($policy->create($manager));
        $this->assertTrue($policy->update($manager));
        $this->assertTrue($policy->delete($manager));
        $this->assertFalse($policy->view($this->userWithPermissions(), $active));
    }

    public function test_terms_and_survey_administration_keep_their_super_admin_rules(): void
    {
        $manager = $this->userWithPermissions(
            Permission::TERMS_VIEW,
            Permission::TERMS_MANAGE,
            Permission::TERMS_PUBLISH,
            Permission::SURVEYS_MANAGE,
        );
        $superAdmin = $this->superAdmin();
        $terms = new TermsVersionPolicy;
        $surveys = new SurveyPolicy;

        $this->assertTrue($terms->viewAny($manager));
        $this->assertTrue($terms->view($manager));
        $this->assertTrue($terms->create($manager));
        $this->assertTrue($terms->update($manager));
        $this->assertTrue($terms->delete($manager));
        $this->assertFalse($terms->publish($manager));
        $this->assertTrue($terms->publish($superAdmin));

        $this->assertTrue($surveys->viewAny($manager));
        $this->assertTrue($surveys->create($manager));
        $this->assertTrue($surveys->update($manager));
        $this->assertTrue($surveys->delete($manager));
        $this->assertTrue($surveys->viewAny($superAdmin));
        $this->assertFalse($surveys->create($this->userWithPermissions()));
        $this->assertFalse($surveys->update($this->userWithPermissions()));
        $this->assertFalse($surveys->delete($this->userWithPermissions()));
    }

    public function test_entrepreneur_profile_policy_distinguishes_read_access_from_assessment_access(): void
    {
        $owner = $this->userWithPermissions(Permission::ENTREPRENEURS_VIEW);
        $advisor = $this->userWithPermissions(Permission::ENTREPRENEURS_VIEW, Permission::ENTREPRENEURS_ASSESS);
        $otherAdvisor = $this->userWithPermissions(Permission::ENTREPRENEURS_VIEW, Permission::ENTREPRENEURS_ASSESS);
        $profile = new EntrepreneurProfile([
            'user_id' => $owner->getKey(),
            'assigned_advisor_id' => $advisor->getKey(),
        ]);
        $policy = new EntrepreneurProfilePolicy;

        $this->assertTrue($policy->viewAny($advisor));
        $this->assertTrue($policy->view($owner, $profile));
        $this->assertTrue($policy->view($advisor, $profile));
        $this->assertFalse($policy->view($otherAdvisor, $profile));
        $this->assertFalse($policy->view($this->userWithPermissions(), $profile));
        $this->assertTrue($policy->create($advisor));
        $this->assertTrue($policy->assess($advisor, $profile));
        $this->assertTrue($policy->finaliseAssessment($advisor, $profile));
        $this->assertTrue($policy->manageInvite($advisor, $profile));
        $this->assertTrue($policy->convert($advisor, $profile));
        $this->assertTrue($policy->updateGamification($advisor, $profile));
        $this->assertFalse($policy->assess($otherAdvisor, $profile));
        $superAdmin = $this->superAdmin();
        $this->assertTrue($policy->view($superAdmin, $profile));
        $this->assertTrue($policy->assess($superAdmin, $profile));
    }

    public function test_survey_assignments_remain_visible_and_actionable_only_to_the_intended_recipient(): void
    {
        $client = $this->client('Survey access client');
        $clientUser = $this->userOfType(User::TYPE_CLIENT_PRIMARY);
        $advisor = $this->userWithPermissions(Permission::SURVEYS_VIEW, Permission::SURVEYS_MANAGE);
        $this->assignClient($clientUser, $client, 'primary_contact');
        $this->assignClient($advisor, $client);
        $clientAssignment = new SurveyAssignment([
            'client_id' => $client->getKey(),
            'status' => SurveyAssignmentStatus::Pending,
        ]);
        $inactiveAssignment = new SurveyAssignment([
            'client_id' => $client->getKey(),
            'status' => SurveyAssignmentStatus::Cancelled,
        ]);
        $entrepreneur = $this->userOfType(User::TYPE_ENTREPRENEUR);
        $entrepreneurAssignment = new SurveyAssignment(['status' => SurveyAssignmentStatus::InProgress]);
        $entrepreneurAssignment->setRelation('entrepreneurProfile', new EntrepreneurProfile([
            'user_id' => $entrepreneur->getKey(),
            'assigned_advisor_id' => $advisor->getKey(),
        ]));
        $policy = new SurveyAssignmentPolicy;

        $this->assertTrue($policy->viewAny($advisor));
        $this->assertTrue($policy->view($clientUser, $clientAssignment));
        $this->assertTrue($policy->view($advisor, $clientAssignment));
        $this->assertTrue($policy->respond($clientUser, $clientAssignment));
        $this->assertFalse($policy->respond($clientUser, $inactiveAssignment));
        $this->assertTrue($policy->cancel($advisor, $clientAssignment));
        $this->assertFalse($policy->cancel($advisor, $inactiveAssignment));
        $this->assertTrue($policy->view($entrepreneur, $entrepreneurAssignment));
        $this->assertTrue($policy->respond($entrepreneur, $entrepreneurAssignment));
        $this->assertTrue($policy->view($advisor, $entrepreneurAssignment));
        $unassignedEntrepreneurAssignment = new SurveyAssignment(['status' => SurveyAssignmentStatus::Pending]);
        $this->assertFalse($policy->view($advisor, $unassignedEntrepreneurAssignment));
        $this->assertFalse($policy->respond($advisor, $unassignedEntrepreneurAssignment));
        $this->assertFalse($policy->cancel($this->userWithPermissions(), $clientAssignment));
        $superAdmin = $this->superAdmin();
        $this->assertTrue($policy->viewAny($superAdmin));
        $this->assertTrue($policy->view($superAdmin, $clientAssignment));
    }

    /**
     * @param  array<int, Permission>  $permissions
     */
    private function userWithPermissions(Permission ...$permissions): User
    {
        $user = User::factory()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);

        foreach ($permissions as $permission) {
            $user->givePermissionTo($permission->value);
        }

        return $user;
    }

    private function superAdmin(): User
    {
        $user = User::factory()->superAdmin()->create();
        $user->assignRole(User::TYPE_SUPER_ADMIN);

        return $user;
    }

    private function userOfType(string $userType): User
    {
        return User::factory()->create([
            'user_type' => $userType,
            'primary_role' => $userType,
        ]);
    }

    private function client(string $legalName): Client
    {
        return Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'legal_name' => $legalName,
            'data_quality' => Client::DATA_QUALITY_MEDIUM,
        ]);
    }

    private function assignClient(User $user, Client $client, string $role = 'lead_advisor'): void
    {
        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $user->getKey(),
            'role' => $role,
            'granted_modules' => [],
        ]);
    }
}
