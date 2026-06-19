<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\AuditEvent;
use App\Models\Document;
use App\Models\EntrepreneurProfile;
use App\Models\KnowledgeEntryDraft;
use App\Models\ProspectLead;
use App\Models\Report;
use App\Models\Survey;
use App\Models\SurveyAssignment;
use App\Models\Template;
use App\Models\TermsVersion;
use App\Policies\AuditEventPolicy;
use App\Policies\ClientPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\EntrepreneurProfilePolicy;
use App\Policies\KnowledgeEntryDraftPolicy;
use App\Policies\KnowledgeEntryPolicy;
use App\Policies\NotificationPolicy;
use App\Policies\ProspectLeadPolicy;
use App\Policies\QuestionnairePolicy;
use App\Policies\ReportPolicy;
use App\Policies\SurveyAssignmentPolicy;
use App\Policies\SurveyPolicy;
use App\Policies\TemplatePolicy;
use App\Policies\TermsVersionPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy('App\\Models\\Client', ClientPolicy::class);
        Gate::policy(EntrepreneurProfile::class, EntrepreneurProfilePolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy('App\\Models\\Questionnaire', QuestionnairePolicy::class);
        Gate::policy('App\\Models\\Notification', NotificationPolicy::class);
        Gate::policy('App\\Models\\KnowledgeEntry', KnowledgeEntryPolicy::class);
        Gate::policy(KnowledgeEntryDraft::class, KnowledgeEntryDraftPolicy::class);
        Gate::policy(Template::class, TemplatePolicy::class);
        Gate::policy(ProspectLead::class, ProspectLeadPolicy::class);
        Gate::policy(TermsVersion::class, TermsVersionPolicy::class);
        Gate::policy(AuditEvent::class, AuditEventPolicy::class);
        Gate::policy(Report::class, ReportPolicy::class);
        Gate::policy(Survey::class, SurveyPolicy::class);
        Gate::policy(SurveyAssignment::class, SurveyAssignmentPolicy::class);
    }
}
