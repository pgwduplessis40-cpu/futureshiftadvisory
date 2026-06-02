<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Http\Controllers\Advisor\AccountingConnectionController;
use App\Http\Controllers\Advisor\AnalysisFeedbackController;
use App\Http\Controllers\Advisor\BriefingController;
use App\Http\Controllers\Advisor\BulkCommunicationController;
use App\Http\Controllers\Advisor\BusinessHealthController;
use App\Http\Controllers\Advisor\CalendarController;
use App\Http\Controllers\Advisor\ClientController;
use App\Http\Controllers\Advisor\ClientEmailController;
use App\Http\Controllers\Advisor\ClientLifecycleController;
use App\Http\Controllers\Advisor\ClientMessageController;
use App\Http\Controllers\Advisor\DocumentVerificationController;
use App\Http\Controllers\Advisor\EntrepreneurActionController;
use App\Http\Controllers\Advisor\EntrepreneurAssessmentController;
use App\Http\Controllers\Advisor\EntrepreneurController;
use App\Http\Controllers\Advisor\EntrepreneurDocumentController;
use App\Http\Controllers\Advisor\EntrepreneurMessageController;
use App\Http\Controllers\Advisor\GoalController;
use App\Http\Controllers\Advisor\KnowledgeAssessmentController;
use App\Http\Controllers\Advisor\KnowledgeController;
use App\Http\Controllers\Advisor\MeetingController;
use App\Http\Controllers\Advisor\MessageInboxController;
use App\Http\Controllers\Advisor\MethodologyController;
use App\Http\Controllers\Advisor\NpoConfigurationController;
use App\Http\Controllers\Advisor\NpoConversionController;
use App\Http\Controllers\Advisor\NpoGovernanceReviewController;
use App\Http\Controllers\Advisor\OffboardingController;
use App\Http\Controllers\Advisor\PaymentController;
use App\Http\Controllers\Advisor\ProposalController;
use App\Http\Controllers\Advisor\ProspectInboxController;
use App\Http\Controllers\Advisor\RedFlagController;
use App\Http\Controllers\Advisor\ReportController;
use App\Http\Controllers\Advisor\StandardAdvisoryController;
use App\Http\Controllers\Advisor\TemplateController;
use App\Http\Controllers\Advisor\TestimonialController;
use App\Http\Controllers\Advisor\VoiceNoteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'mfa'])
    ->prefix('advisor')
    ->name('advisor.')
    ->group(function (): void {
        Route::get('calendar', [CalendarController::class, 'index'])
            ->middleware('permission:'.Permission::CLIENTS_VIEW->value)
            ->name('calendar.index');
        Route::post('calendar/meetings', [CalendarController::class, 'store'])
            ->middleware('permission:'.Permission::CLIENTS_MANAGE->value)
            ->name('calendar.meetings.store');
        Route::patch('calendar/meetings/{meeting}', [CalendarController::class, 'update'])
            ->middleware('permission:'.Permission::CLIENTS_MANAGE->value)
            ->whereUuid('meeting')
            ->name('calendar.meetings.update');
        Route::delete('calendar/meetings/{meeting}', [CalendarController::class, 'cancel'])
            ->middleware('permission:'.Permission::CLIENTS_MANAGE->value)
            ->whereUuid('meeting')
            ->name('calendar.meetings.cancel');

        Route::get('messages', [MessageInboxController::class, 'index'])
            ->middleware('permission:'.Permission::NOTIFICATIONS_VIEW->value)
            ->name('messages.index');

        Route::get('clients', [ClientController::class, 'index'])
            ->middleware('permission:'.Permission::CLIENTS_VIEW->value)
            ->name('clients.index');
        Route::get('clients/create', [ClientController::class, 'create'])
            ->middleware('permission:'.Permission::CLIENTS_MANAGE->value)
            ->name('clients.create');
        Route::post('clients/lookup-nzbn', [ClientController::class, 'lookupNzbn'])
            ->middleware('permission:'.Permission::CLIENTS_MANAGE->value)
            ->name('clients.lookup-nzbn');
        Route::post('clients', [ClientController::class, 'store'])
            ->middleware('permission:'.Permission::CLIENTS_MANAGE->value)
            ->name('clients.store');
        Route::get('clients/{client}/offboarding', [OffboardingController::class, 'create'])
            ->middleware('permission:'.Permission::CLIENTS_MANAGE->value)
            ->name('clients.offboarding.create');
        Route::post('clients/{client}/offboarding', [OffboardingController::class, 'store'])
            ->middleware('permission:'.Permission::CLIENTS_MANAGE->value)
            ->name('clients.offboarding.store');
        Route::patch('clients/{client}/lifecycle', [ClientLifecycleController::class, 'update'])
            ->middleware('permission:'.Permission::CLIENTS_MANAGE->value)
            ->name('clients.lifecycle.update');
        Route::post('clients/{client}/knowledge-assessments', [KnowledgeAssessmentController::class, 'store'])
            ->middleware('permission:'.Permission::CLIENTS_VIEW->value)
            ->name('clients.knowledge-assessments.store');
        Route::post('clients/{client}/knowledge-drafts', [KnowledgeController::class, 'draftFromClient'])
            ->middleware('permission:'.Permission::KNOWLEDGE_MANAGE->value)
            ->name('clients.knowledge-drafts.store');
        Route::post('clients/{client}/goals', [GoalController::class, 'store'])
            ->middleware('permission:'.Permission::CLIENTS_MANAGE->value)
            ->name('clients.goals.store');
        Route::post('clients/{client}/proposals', [ProposalController::class, 'store'])
            ->middleware('permission:'.Permission::PROPOSALS_RELEASE->value)
            ->name('clients.proposals.store');
        Route::post('clients/{client}/reports', [ReportController::class, 'store'])
            ->middleware('permission:'.Permission::REPORTS_PUBLISH->value)
            ->name('clients.reports.store');
        Route::post('clients/{client}/standard-advisory/analysis', [StandardAdvisoryController::class, 'runAnalysis'])
            ->middleware('permission:'.Permission::CLIENTS_MANAGE->value)
            ->name('clients.standard-advisory.analysis');
        Route::post('clients/{client}/standard-advisory/pack', [StandardAdvisoryController::class, 'generatePack'])
            ->middleware('permission:'.Permission::REPORTS_PUBLISH->value)
            ->name('clients.standard-advisory.pack');
        Route::post('clients/{client}/health-radar/recompute', [BusinessHealthController::class, 'recompute'])
            ->middleware('permission:'.Permission::CLIENTS_MANAGE->value)
            ->name('clients.health-radar.recompute');
        Route::post('clients/{client}/meetings', [MeetingController::class, 'store'])
            ->middleware('permission:'.Permission::CLIENTS_MANAGE->value)
            ->name('clients.meetings.store');
        Route::get('clients/{client}/compose', [ClientEmailController::class, 'create'])
            ->middleware('permission:'.Permission::CLIENTS_VIEW->value)
            ->name('clients.compose');
        Route::post('clients/{client}/email', [ClientEmailController::class, 'store'])
            ->middleware('permission:'.Permission::CLIENTS_VIEW->value)
            ->name('clients.email.store');
        Route::get('clients/{client}/messages', [ClientMessageController::class, 'index'])
            ->middleware('permission:'.Permission::CLIENTS_VIEW->value)
            ->name('clients.messages.index');
        Route::post('clients/{client}/messages', [ClientMessageController::class, 'store'])
            ->middleware('permission:'.Permission::CLIENTS_VIEW->value)
            ->name('clients.messages.store');
        Route::get('clients/{client}/messages/{messageThread}', [ClientMessageController::class, 'show'])
            ->middleware('permission:'.Permission::CLIENTS_VIEW->value)
            ->name('clients.messages.show');
        Route::post('clients/{client}/messages/{messageThread}', [ClientMessageController::class, 'reply'])
            ->middleware('permission:'.Permission::CLIENTS_VIEW->value)
            ->name('clients.messages.reply');
        Route::get('clients/{client}/accounting/{provider}/connect', [AccountingConnectionController::class, 'connect'])
            ->middleware('permission:'.Permission::CLIENTS_MANAGE->value)
            ->name('clients.accounting.connect');
        Route::get('clients/{client}/accounting/{provider}/callback', [AccountingConnectionController::class, 'callback'])
            ->middleware('permission:'.Permission::CLIENTS_MANAGE->value)
            ->name('clients.accounting.callback');
        Route::post('clients/{client}/accounting/{accountingConnection}/pull', [AccountingConnectionController::class, 'pull'])
            ->middleware('permission:'.Permission::CLIENTS_MANAGE->value)
            ->name('clients.accounting.pull');
        Route::patch('clients/{client}/accounting/{accountingConnection}/revoke', [AccountingConnectionController::class, 'revoke'])
            ->middleware('permission:'.Permission::CLIENTS_MANAGE->value)
            ->name('clients.accounting.revoke');
        Route::get('clients/{client}', [ClientController::class, 'show'])
            ->middleware('permission:'.Permission::CLIENTS_VIEW->value)
            ->name('clients.show');
        Route::post('clients/{client}/testimonials/nps', [TestimonialController::class, 'requestFromNps'])
            ->middleware('permission:'.Permission::CLIENTS_MANAGE->value)
            ->name('clients.testimonials.nps');
        Route::post('clients/{client}/voice-notes', [VoiceNoteController::class, 'store'])
            ->middleware('permission:'.Permission::CLIENTS_MANAGE->value)
            ->name('clients.voice-notes.store');
        Route::post('clients/{client}/call-logs', [VoiceNoteController::class, 'storeCallLog'])
            ->middleware('permission:'.Permission::CLIENTS_MANAGE->value)
            ->name('clients.call-logs.store');

        Route::patch('npo-engagements/{npoEngagement}/conversion/report-delivered', [NpoConversionController::class, 'reportDelivered'])
            ->middleware('permission:'.Permission::CLIENTS_MANAGE->value)
            ->name('npo-engagements.conversion.report-delivered');
        Route::patch('npo-engagements/{npoEngagement}/conversion/decline', [NpoConversionController::class, 'decline'])
            ->middleware('permission:'.Permission::CLIENTS_MANAGE->value)
            ->name('npo-engagements.conversion.decline');
        Route::patch('npo-engagements/{npoEngagement}/conversion/convert', [NpoConversionController::class, 'convert'])
            ->middleware('permission:'.Permission::CLIENTS_MANAGE->value)
            ->name('npo-engagements.conversion.convert');
        Route::patch('npo-engagements/{npoEngagement}/configuration', [NpoConfigurationController::class, 'update'])
            ->middleware('permission:'.Permission::CLIENTS_MANAGE->value)
            ->name('npo-engagements.configuration.update');
        Route::post('npo-engagements/{npoEngagement}/governance-review/analysis', [NpoGovernanceReviewController::class, 'run'])
            ->middleware('permission:'.Permission::CLIENTS_MANAGE->value)
            ->name('npo-engagements.governance-review.analysis');
        Route::patch('governance-review-findings/{governanceReviewFinding}/review', [NpoGovernanceReviewController::class, 'review'])
            ->middleware('permission:'.Permission::CLIENTS_MANAGE->value)
            ->name('governance-review-findings.review');

        Route::post('payments/{payment}/retry', [PaymentController::class, 'retry'])
            ->middleware('permission:'.Permission::CLIENTS_MANAGE->value)
            ->name('payments.retry');

        Route::patch('proposals/{proposal}/release', [ProposalController::class, 'release'])
            ->middleware('permission:'.Permission::PROPOSALS_RELEASE->value)
            ->name('proposals.release');
        Route::patch('proposals/{proposal}/recall', [ProposalController::class, 'recall'])
            ->middleware('permission:'.Permission::PROPOSALS_RELEASE->value)
            ->name('proposals.recall');
        Route::patch('proposals/{proposal}/renew', [ProposalController::class, 'renew'])
            ->middleware('permission:'.Permission::PROPOSALS_RELEASE->value)
            ->name('proposals.renew');
        Route::post('goals/{goal}/milestones', [GoalController::class, 'milestone'])
            ->middleware('permission:'.Permission::CLIENTS_MANAGE->value)
            ->name('goals.milestones.store');
        Route::post('milestones/{milestone}/actions', [GoalController::class, 'action'])
            ->middleware('permission:'.Permission::CLIENTS_MANAGE->value)
            ->name('milestones.actions.store');
        Route::post('milestones/{milestone}/proof', [GoalController::class, 'proof'])
            ->middleware('permission:'.Permission::CLIENTS_MANAGE->value)
            ->name('milestones.proof.store');
        Route::get('reports/{report}/download', [ReportController::class, 'download'])
            ->middleware('permission:'.Permission::CLIENTS_VIEW->value)
            ->name('reports.download');
        Route::get('reports/{report}/pptx', [ReportController::class, 'downloadPptx'])
            ->middleware('permission:'.Permission::CLIENTS_VIEW->value)
            ->name('reports.pptx');
        Route::patch('reports/{report}/review', [ReportController::class, 'review'])
            ->middleware('permission:'.Permission::REPORTS_PUBLISH->value)
            ->name('reports.review');
        Route::patch('reports/{report}/sections/{reportSection}', [ReportController::class, 'updateSection'])
            ->middleware('permission:'.Permission::REPORTS_PUBLISH->value)
            ->name('reports.sections.update');
        Route::post('reports/{report}/sections/{reportSection}/comments', [ReportController::class, 'commentSection'])
            ->middleware('permission:'.Permission::REPORTS_PUBLISH->value)
            ->name('reports.sections.comments.store');
        Route::patch('industry-briefings/{industryBriefing}/review', [BriefingController::class, 'reviewIndustry'])
            ->middleware('permission:'.Permission::REPORTS_PUBLISH->value)
            ->name('industry-briefings.review');
        Route::patch('pre-meeting-briefs/{preMeetingBrief}/review', [BriefingController::class, 'reviewPreMeeting'])
            ->middleware('permission:'.Permission::REPORTS_PUBLISH->value)
            ->name('pre-meeting-briefs.review');

        Route::get('entrepreneurs', [EntrepreneurController::class, 'index'])
            ->middleware('permission:'.Permission::ENTREPRENEURS_VIEW->value)
            ->name('entrepreneurs.index');
        Route::get('entrepreneurs/create', [EntrepreneurController::class, 'create'])
            ->middleware('permission:'.Permission::ENTREPRENEURS_ASSESS->value)
            ->name('entrepreneurs.create');
        Route::post('entrepreneurs', [EntrepreneurController::class, 'store'])
            ->middleware('permission:'.Permission::ENTREPRENEURS_ASSESS->value)
            ->name('entrepreneurs.store');
        Route::get('entrepreneurs/{entrepreneurProfile}/messages', [EntrepreneurMessageController::class, 'index'])
            ->middleware('permission:'.Permission::ENTREPRENEURS_VIEW->value)
            ->name('entrepreneurs.messages.index');
        Route::post('entrepreneurs/{entrepreneurProfile}/messages', [EntrepreneurMessageController::class, 'store'])
            ->middleware('permission:'.Permission::ENTREPRENEURS_VIEW->value)
            ->name('entrepreneurs.messages.store');
        Route::get('entrepreneurs/{entrepreneurProfile}/messages/{messageThread}', [EntrepreneurMessageController::class, 'show'])
            ->middleware('permission:'.Permission::ENTREPRENEURS_VIEW->value)
            ->name('entrepreneurs.messages.show');
        Route::post('entrepreneurs/{entrepreneurProfile}/messages/{messageThread}', [EntrepreneurMessageController::class, 'reply'])
            ->middleware('permission:'.Permission::ENTREPRENEURS_VIEW->value)
            ->name('entrepreneurs.messages.reply');
        Route::patch('entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/gate', [EntrepreneurActionController::class, 'gateIdea'])
            ->middleware('permission:'.Permission::ENTREPRENEURS_ASSESS->value)
            ->name('entrepreneurs.idea-validations.gate');
        Route::post('entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/assessments', [EntrepreneurActionController::class, 'assess'])
            ->middleware('permission:'.Permission::ENTREPRENEURS_ASSESS->value)
            ->name('entrepreneurs.plans.assessments.store');
        Route::patch('entrepreneurs/{entrepreneurProfile}/assessments/{planAssessment}/finalise', [EntrepreneurActionController::class, 'finalise'])
            ->middleware('permission:'.Permission::ENTREPRENEURS_ASSESS->value)
            ->name('entrepreneurs.assessments.finalise');
        Route::post('entrepreneurs/{entrepreneurProfile}/convert', [EntrepreneurActionController::class, 'convert'])
            ->middleware('permission:'.Permission::ENTREPRENEURS_ASSESS->value)
            ->name('entrepreneurs.convert');
        Route::get('entrepreneurs/{entrepreneurProfile}/assessments/{planAssessment}', [EntrepreneurAssessmentController::class, 'show'])
            ->middleware('permission:'.Permission::ENTREPRENEURS_VIEW->value)
            ->name('entrepreneurs.assessments.show');
        Route::get('entrepreneurs/{entrepreneurProfile}/documents/{document}', [EntrepreneurDocumentController::class, 'show'])
            ->middleware('permission:'.Permission::ENTREPRENEURS_VIEW->value)
            ->name('entrepreneurs.documents.show');
        Route::get('entrepreneurs/{entrepreneurProfile}', [EntrepreneurController::class, 'show'])
            ->middleware('permission:'.Permission::ENTREPRENEURS_VIEW->value)
            ->name('entrepreneurs.show');

        Route::get('knowledge', [KnowledgeController::class, 'index'])
            ->middleware('permission:'.Permission::KNOWLEDGE_VIEW->value)
            ->name('knowledge.index');
        Route::get('knowledge/create', [KnowledgeController::class, 'create'])
            ->middleware('permission:'.Permission::KNOWLEDGE_MANAGE->value)
            ->name('knowledge.create');
        Route::post('knowledge', [KnowledgeController::class, 'store'])
            ->middleware('permission:'.Permission::KNOWLEDGE_MANAGE->value)
            ->name('knowledge.store');
        Route::get('knowledge-drafts/{knowledgeEntryDraft}/review', [KnowledgeController::class, 'reviewDraft'])
            ->middleware('permission:'.Permission::KNOWLEDGE_VIEW->value)
            ->whereUuid('knowledgeEntryDraft')
            ->name('knowledge-drafts.review');
        Route::patch('knowledge-drafts/{knowledgeEntryDraft}/accept', [KnowledgeController::class, 'acceptDraft'])
            ->middleware('permission:'.Permission::KNOWLEDGE_MANAGE->value)
            ->whereUuid('knowledgeEntryDraft')
            ->name('knowledge-drafts.accept');
        Route::patch('knowledge-drafts/{knowledgeEntryDraft}/discard', [KnowledgeController::class, 'discardDraft'])
            ->middleware('permission:'.Permission::KNOWLEDGE_MANAGE->value)
            ->whereUuid('knowledgeEntryDraft')
            ->name('knowledge-drafts.discard');
        Route::get('knowledge/methodologies', [MethodologyController::class, 'index'])
            ->middleware('permission:'.Permission::KNOWLEDGE_VIEW->value)
            ->name('knowledge.methodologies.index');
        Route::get('knowledge/methodologies/{methodology}', [MethodologyController::class, 'show'])
            ->middleware('permission:'.Permission::KNOWLEDGE_VIEW->value)
            ->where('methodology', '[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)*')
            ->name('knowledge.methodologies.show');
        Route::get('knowledge/{knowledgeEntry}', [KnowledgeController::class, 'show'])
            ->middleware('permission:'.Permission::KNOWLEDGE_VIEW->value)
            ->whereUuid('knowledgeEntry')
            ->name('knowledge.show');
        Route::get('knowledge/{knowledgeEntry}/edit', [KnowledgeController::class, 'edit'])
            ->middleware('permission:'.Permission::KNOWLEDGE_MANAGE->value)
            ->whereUuid('knowledgeEntry')
            ->name('knowledge.edit');
        Route::patch('knowledge/{knowledgeEntry}', [KnowledgeController::class, 'update'])
            ->middleware('permission:'.Permission::KNOWLEDGE_MANAGE->value)
            ->whereUuid('knowledgeEntry')
            ->name('knowledge.update');
        Route::delete('knowledge/{knowledgeEntry}', [KnowledgeController::class, 'destroy'])
            ->middleware('permission:'.Permission::KNOWLEDGE_MANAGE->value)
            ->whereUuid('knowledgeEntry')
            ->name('knowledge.destroy');

        Route::get('templates', [TemplateController::class, 'index'])
            ->middleware('permission:'.Permission::TEMPLATE_VIEW->value)
            ->name('templates.index');
        Route::post('templates', [TemplateController::class, 'store'])
            ->middleware('permission:'.Permission::TEMPLATE_MANAGE->value)
            ->name('templates.store');
        Route::get('templates/{template}', [TemplateController::class, 'show'])
            ->middleware('permission:'.Permission::TEMPLATE_VIEW->value)
            ->whereUuid('template')
            ->name('templates.show');
        Route::patch('templates/{template}', [TemplateController::class, 'update'])
            ->middleware('permission:'.Permission::TEMPLATE_MANAGE->value)
            ->whereUuid('template')
            ->name('templates.update');

        Route::get('prospects', [ProspectInboxController::class, 'index'])
            ->middleware('permission:'.Permission::PROSPECTS_VIEW->value)
            ->name('prospects.index');
        Route::patch('prospects/{prospectLead}/triage', [ProspectInboxController::class, 'triage'])
            ->middleware('permission:'.Permission::PROSPECTS_TRIAGE->value)
            ->name('prospects.triage');

        Route::patch('document-verifications/{documentVerification}', [DocumentVerificationController::class, 'update'])
            ->middleware('permission:'.Permission::DOCUMENTS_VERIFY->value)
            ->name('document-verifications.update');

        Route::patch('red-flags/{redFlag}/acknowledge', [RedFlagController::class, 'acknowledge'])
            ->middleware('permission:'.Permission::CLIENTS_VIEW->value)
            ->name('red-flags.acknowledge');
        Route::patch('red-flags/{redFlag}/resolve', [RedFlagController::class, 'resolve'])
            ->middleware('permission:'.Permission::CLIENTS_VIEW->value)
            ->name('red-flags.resolve');

        Route::post('analysis-findings/{analysisFinding}/feedback', [AnalysisFeedbackController::class, 'store'])
            ->middleware('permission:'.Permission::LEARNING_UPDATES_VIEW->value)
            ->name('analysis-findings.feedback.store');

        Route::get('testimonials', [TestimonialController::class, 'index'])
            ->middleware('permission:'.Permission::CLIENTS_VIEW->value)
            ->name('testimonials.index');
        Route::patch('testimonials/{testimonial}/consent', [TestimonialController::class, 'capture'])
            ->middleware('permission:'.Permission::CLIENTS_MANAGE->value)
            ->name('testimonials.capture');

        Route::get('bulk-communications', [BulkCommunicationController::class, 'index'])
            ->middleware('permission:'.Permission::NOTIFICATIONS_MANAGE->value)
            ->name('bulk-communications.index');
        Route::post('bulk-communications', [BulkCommunicationController::class, 'store'])
            ->middleware('permission:'.Permission::NOTIFICATIONS_MANAGE->value)
            ->name('bulk-communications.store');
    });
