<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\User;

enum Permission: string
{
    public const DD_GUEST_TOKEN_TYPE = 'dd_guest';

    public const GUARD = 'web';

    case SYSTEM_ADMIN = 'system.admin';
    case USERS_INVITE = 'users.invite';
    case CLIENTS_VIEW = 'clients.view';
    case CLIENTS_MANAGE = 'clients.manage';
    case DOCUMENTS_VIEW = 'documents.view';
    case DOCUMENTS_UPLOAD = 'documents.upload';
    case DOCUMENTS_MANAGE = 'documents.manage';
    case DOCUMENTS_VERIFY = 'documents.verify';
    case QUESTIONNAIRES_VIEW = 'questionnaires.view';
    case QUESTIONNAIRES_DRAFT = 'questionnaires.draft';
    case QUESTIONNAIRES_PUBLISH = 'questionnaires.publish';
    case NOTIFICATIONS_VIEW = 'notifications.view';
    case NOTIFICATIONS_MANAGE = 'notifications.manage';
    case KNOWLEDGE_VIEW = 'knowledge.view';
    case KNOWLEDGE_MANAGE = 'knowledge.manage';
    case KNOWLEDGE_PUBLISH = 'knowledge.publish';
    case TEMPLATE_VIEW = 'template.view';
    case TEMPLATE_MANAGE = 'template.manage';
    case PROSPECTS_VIEW = 'prospects.view';
    case PROSPECTS_TRIAGE = 'prospects.triage';
    case TERMS_VIEW = 'terms.view';
    case TERMS_MANAGE = 'terms.manage';
    case TERMS_PUBLISH = 'terms.publish';
    case AUDIT_VIEW = 'audit.view';
    case CREDENTIAL_MANAGE = 'credential.manage';
    case REFERENCE_DATA_MANAGE = 'reference_data.manage';
    case WELCOME_MESSAGE_MANAGE = 'welcome_message.manage';
    case BOARD_MANAGE = 'board.manage';
    case REPORTS_VIEW = 'reports.view';
    case INTEGRATION_HEALTH_VIEW = 'integration_health.view';
    case REPORTS_PUBLISH = 'reports.publish';
    case PROPOSALS_RELEASE = 'proposals.release';
    case PAYMENTS_MANAGE = 'payments.manage';
    case REFERRALS_SEND = 'referrals.send';
    case LEARNING_UPDATES_VIEW = 'learning_updates.view';
    case LEARNING_UPDATES_APPROVE = 'learning_updates.approve';
    case ENTREPRENEURS_VIEW = 'entrepreneurs.view';
    case ENTREPRENEURS_ASSESS = 'entrepreneurs.assess';
    case SURVEYS_MANAGE = 'surveys.manage';
    case SURVEYS_VIEW = 'surveys.view';
    case BROKER_PORTAL = 'broker.portal';
    case COACH_PORTAL = 'coach.portal';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $permission): string => $permission->value,
            self::cases(),
        );
    }

    /**
     * Canonical Phase 1 RBAC capability matrix from spec section 5.
     *
     * @return array<string, array<int, self>>
     */
    public static function roleMatrix(): array
    {
        return [
            User::TYPE_SUPER_ADMIN => self::cases(),

            User::TYPE_ADVISOR => [
                self::CLIENTS_VIEW,
                self::CLIENTS_MANAGE,
                self::DOCUMENTS_VIEW,
                self::DOCUMENTS_UPLOAD,
                self::DOCUMENTS_MANAGE,
                self::DOCUMENTS_VERIFY,
                self::QUESTIONNAIRES_VIEW,
                self::QUESTIONNAIRES_DRAFT,
                self::QUESTIONNAIRES_PUBLISH,
                self::NOTIFICATIONS_VIEW,
                self::NOTIFICATIONS_MANAGE,
                self::KNOWLEDGE_VIEW,
                self::KNOWLEDGE_MANAGE,
                self::KNOWLEDGE_PUBLISH,
                self::TEMPLATE_VIEW,
                self::PROSPECTS_VIEW,
                self::PROSPECTS_TRIAGE,
                self::TERMS_VIEW,
                self::AUDIT_VIEW,
                self::REPORTS_VIEW,
                self::INTEGRATION_HEALTH_VIEW,
                self::REPORTS_PUBLISH,
                self::PROPOSALS_RELEASE,
                self::PAYMENTS_MANAGE,
                self::REFERRALS_SEND,
                self::LEARNING_UPDATES_VIEW,
                self::ENTREPRENEURS_VIEW,
                self::ENTREPRENEURS_ASSESS,
                self::SURVEYS_VIEW,
            ],

            User::TYPE_JUNIOR_ADVISOR => [
                self::CLIENTS_VIEW,
                self::DOCUMENTS_VIEW,
                self::QUESTIONNAIRES_VIEW,
                self::QUESTIONNAIRES_DRAFT,
                self::NOTIFICATIONS_VIEW,
                self::KNOWLEDGE_VIEW,
                self::TEMPLATE_VIEW,
                self::PROSPECTS_VIEW,
                self::TERMS_VIEW,
                self::REPORTS_VIEW,
                self::LEARNING_UPDATES_VIEW,
                self::ENTREPRENEURS_VIEW,
                self::SURVEYS_VIEW,
            ],

            User::TYPE_ENTREPRENEUR_MENTOR => [
                self::DOCUMENTS_VIEW,
                self::DOCUMENTS_UPLOAD,
                self::QUESTIONNAIRES_VIEW,
                self::QUESTIONNAIRES_DRAFT,
                self::NOTIFICATIONS_VIEW,
                self::KNOWLEDGE_VIEW,
                self::TEMPLATE_VIEW,
                self::TERMS_VIEW,
                self::REPORTS_VIEW,
                self::ENTREPRENEURS_VIEW,
                self::ENTREPRENEURS_ASSESS,
                self::SURVEYS_VIEW,
            ],

            User::TYPE_CLIENT_PRIMARY => [
                self::CLIENTS_VIEW,
                self::DOCUMENTS_VIEW,
                self::DOCUMENTS_UPLOAD,
                self::QUESTIONNAIRES_VIEW,
                self::QUESTIONNAIRES_DRAFT,
                self::NOTIFICATIONS_VIEW,
                self::TERMS_VIEW,
                self::REPORTS_VIEW,
                self::PAYMENTS_MANAGE,
            ],

            User::TYPE_CLIENT_TEAM => [
                self::CLIENTS_VIEW,
                self::DOCUMENTS_VIEW,
                self::DOCUMENTS_UPLOAD,
                self::QUESTIONNAIRES_VIEW,
                self::QUESTIONNAIRES_DRAFT,
                self::NOTIFICATIONS_VIEW,
                self::TERMS_VIEW,
                self::REPORTS_VIEW,
            ],

            User::TYPE_ENTREPRENEUR => [
                self::DOCUMENTS_VIEW,
                self::DOCUMENTS_UPLOAD,
                self::QUESTIONNAIRES_VIEW,
                self::QUESTIONNAIRES_DRAFT,
                self::NOTIFICATIONS_VIEW,
                self::TERMS_VIEW,
                self::REPORTS_VIEW,
                self::ENTREPRENEURS_VIEW,
            ],

            User::TYPE_BROKER => [
                self::NOTIFICATIONS_VIEW,
                self::TERMS_VIEW,
                self::REFERRALS_SEND,
                self::BROKER_PORTAL,
            ],

            User::TYPE_COACH => [
                self::NOTIFICATIONS_VIEW,
                self::TERMS_VIEW,
                self::REFERRALS_SEND,
                self::COACH_PORTAL,
            ],

            User::TYPE_NPO_BOARD_MEMBER => [
                self::DOCUMENTS_VIEW,
                self::NOTIFICATIONS_VIEW,
                self::TERMS_VIEW,
                self::REPORTS_VIEW,
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function valuesForRole(string $role): array
    {
        return array_map(
            static fn (self $permission): string => $permission->value,
            self::roleMatrix()[$role] ?? [],
        );
    }
}
