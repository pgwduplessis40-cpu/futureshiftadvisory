# Questionnaire engine

WO-17 introduces the reusable questionnaire engine for Phase 1 Standard Advisory onboarding and future questionnaire sets.

## Storage model

Questionnaire content is versioned:

- `questionnaires` stores set, version, title, and publish metadata.
- `questionnaire_sections` stores ordered section headings and help text.
- `questionnaire_questions` stores ordered questions, type, help text, options, required flag, and conditional logic.
- `questionnaire_responses` stores one client response per questionnaire version.
- `questionnaire_answers` stores JSON answer values and `attached_document_ids` for WO-18 document upload linkage.

Published questionnaire versions are immutable through the admin UI. Drafting a new version clones the latest published or draft structure and remaps conditional question IDs into the new version.

## Standard Advisory seed

`StandardAdvisoryQuestionnaireSeeder` creates the Phase 1 Standard Advisory set with the 10 required sections:

1. Business Overview
2. Products and Services
3. Market and Customers
4. Financial Position
5. People and HR
6. Operations
7. Sales and Marketing
8. Strategy and Goals
9. Compliance and Risk
10. Owner and Leadership

The seeded set exercises every Phase 1 question type: text, long-text, number, currency, date, single-select, multi-select, file-attach, and Likert.

## Conditional logic

Conditional rules use the plan shape:

```json
{ "when": "question-uuid", "equals": "yes", "show": "target-question-uuid" }
```

`in` may be used instead of `equals` for multi-value matches. The server-side source of truth is `QuestionnaireRuleEngine`; the React preview and portal renderer mirror it in `resources/js/lib/questionnaires/conditional-logic.ts`.

On submit, hidden questions are not required and their existing answers are removed from the response. Visible required questions are validated by type before persistence.

## Portal integration

WO-16 Step 5 now loads the latest published Standard Advisory questionnaire for `standard_advisory` clients. Other questionnaire sets remain Phase 3-gated. Submitting Step 5 writes `questionnaire_responses` and `questionnaire_answers`, audits `questionnaire.submitted`, then advances the onboarding wizard.

File-attach questions do not upload files in WO-17. They accept and persist `attached_document_ids` so WO-18 can connect uploaded documents to answers without changing the answer schema.

## Admin builder

Super-admin questionnaire builder routes live under `/admin/questionnaires`. The builder supports:

- list, draft, edit, preview, publish
- drag-and-drop section and question ordering via `@dnd-kit/core`
- type-specific option editing
- simple conditional logic editing
- live preview using the same React renderer as the portal
