# WO-07 RBAC matrix

Future Shift Advisory uses `spatie/laravel-permission` for application RBAC. The canonical permission names live in `app/Enums/Permission.php`; `database/seeders/RoleSeeder.php` is the executable matrix and the tests assert that the database matches it exactly.

All role names match the nine `users.user_type` values from spec section 5. The DD guest is deliberately not a user type or Spatie role.

## Roles

| Role | Spec intent | Permissions |
|---|---|---|
| `super_admin` | Full platform access across clients, advisors, brokers, coaches, entrepreneurs, settings, terms, and learning updates. | All permissions. |
| `advisor` | Full access to assigned clients and entrepreneurs, including analysis, reports, proposals, payments, referrals, DD, PV, and assessments. | `clients.view`, `clients.manage`, `documents.view`, `documents.upload`, `documents.manage`, `documents.verify`, `questionnaires.view`, `questionnaires.draft`, `questionnaires.publish`, `notifications.view`, `notifications.manage`, `knowledge.view`, `knowledge.manage`, `knowledge.publish`, `prospects.view`, `prospects.triage`, `terms.view`, `audit.view`, `reports.view`, `reports.publish`, `proposals.release`, `payments.manage`, `referrals.send`, `learning_updates.view`, `entrepreneurs.view`, `entrepreneurs.assess` |
| `junior_advisor` | View and draft only. Cannot publish, release proposals, manage payments, send referrals, or approve learning updates. | `clients.view`, `documents.view`, `questionnaires.view`, `questionnaires.draft`, `notifications.view`, `knowledge.view`, `prospects.view`, `terms.view`, `reports.view`, `learning_updates.view`, `entrepreneurs.view` |
| `entrepreneur_mentor` | Mentor entrepreneurs and conduct business plan assessments without advisory analysis modules, client financial data, or payment management. | `documents.view`, `documents.upload`, `questionnaires.view`, `questionnaires.draft`, `notifications.view`, `knowledge.view`, `terms.view`, `reports.view`, `entrepreneurs.view`, `entrepreneurs.assess` |
| `client_primary` | Full client portal access including documents, questionnaire responses, reports, and payment authority. | `clients.view`, `documents.view`, `documents.upload`, `questionnaires.view`, `questionnaires.draft`, `notifications.view`, `terms.view`, `reports.view`, `payments.manage` |
| `client_team` | Client portal access to specific modules granted by the primary contact or advisor. | `clients.view`, `documents.view`, `documents.upload`, `questionnaires.view`, `questionnaires.draft`, `notifications.view`, `terms.view`, `reports.view` |
| `entrepreneur` | Entrepreneur portal access for plan building, assessments, documents, progress, and revision submissions. | `documents.view`, `documents.upload`, `questionnaires.view`, `questionnaires.draft`, `notifications.view`, `terms.view`, `reports.view`, `entrepreneurs.view` |
| `broker` | Broker portal only: profile, referrals, status, reverse referrals, and panel agreement. | `notifications.view`, `terms.view`, `referrals.send`, `broker.portal` |
| `coach` | Coach portal only: profile, referrals, status, reverse referrals, and coach panel agreement. | `notifications.view`, `terms.view`, `referrals.send`, `coach.portal` |

## DD guest

DD target-business guest access is `dd_guest` token access only. It must not create a `users` row, a Spatie role, or a normal login session. Phase 3 can issue time-limited upload tokens using `Permission::DD_GUEST_TOKEN_TYPE`, scoped to a specific DD workstream folder.

## Enforcement

- `role:*` routes use `App\Http\Middleware\EnsureRole`.
- `permission:*` routes use `App\Http\Middleware\EnsurePermission`.
- Resource authorization is handled through policies for `Client`, `Document`, `Questionnaire`, `Notification`, `KnowledgeEntry`, `ProspectLead`, `TermsVersion`, and `AuditEvent`.
- RLS still reads `User::fsaRole()` through `RequestContext`; after WO-07 that method resolves the first Spatie role and falls back to `primary_role` only when no role assignment exists.
