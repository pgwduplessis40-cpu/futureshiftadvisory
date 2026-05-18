<?php

declare(strict_types=1);

namespace App\Support\Public;

/**
 * Public-site FAQ content.
 * Drafted from the platform spec (AI integrity principle, invite-only access,
 * document verification, MFA, four engagement types) and the Meridian Warm brand voice.
 * Edit copy here; the FAQ page renders whatever this returns.
 */
final class FaqCatalog
{
    /**
     * @return array<int, array{group:string, question:string, answer:string}>
     */
    public static function all(): array
    {
        return [
            [
                'group' => 'About the work',
                'question' => 'What does Future Shift Advisory actually do?',
                'answer' => 'We run structured, evidence-based advisory engagements for New Zealand SMEs and entrepreneurs. Diagnostics, due diligence, post-acquisition support, and an entrepreneur module for founders pre-launch. Every finding cites its source. Nothing is asserted without a reason.',
            ],
            [
                'group' => 'About the work',
                'question' => 'Who do you typically work with?',
                'answer' => 'Established New Zealand SMEs that want an honest read on the business, buyers and investors running diligence on an acquisition, and founders building something new. If you want comfortable answers, we are the wrong fit. If you want the truth before the comfortable, you are in the right place.',
            ],
            [
                'group' => 'About the work',
                'question' => 'Are you New Zealand-specific?',
                'answer' => 'Yes. Our analysis, references, and frameworks are NZ-grounded — NZBN, Companies Office, IRD, NZ tax and compliance context. We are based in Hamilton and work nationwide.',
            ],
            [
                'group' => 'How engagements start',
                'question' => 'How do I become a client?',
                'answer' => 'Send us a note through the contact form. We respond personally, scope an initial conversation, and — if there is a good fit — issue you a secure invite into our client portal. We do not run open self-registration; every account is invited and verified.',
            ],
            [
                'group' => 'How engagements start',
                'question' => 'What does an engagement cost?',
                'answer' => 'Scope drives price. We talk through what you actually need before quoting, and we make the basis for the fee explicit. A formal fee structure (hours-based, outcome-based, and entrepreneur structures) is being finalised — we will walk you through it in the discovery call.',
            ],
            [
                'group' => 'How engagements start',
                'question' => 'How long does a typical engagement take?',
                'answer' => 'A Standard Advisory diagnostic is usually weeks, not months. Due diligence runs to the deal timeline. The entrepreneur module is staged across readiness, validation, build, and assessment — measured in months because the work is meaningful, not because we are stretching it.',
            ],
            [
                'group' => 'Security & confidentiality',
                'question' => 'How is my information protected?',
                'answer' => 'Multi-factor authentication is mandatory for every account. Documents are encrypted at rest, scanned before storage, and access is scoped per client. We keep an immutable audit trail of every action on your data. Confidentiality is non-negotiable.',
            ],
            [
                'group' => 'Security & confidentiality',
                'question' => 'What happens to documents I upload?',
                'answer' => 'Every uploaded document is reviewed for relevance and accuracy. Where claims line up with the document, it is verified. Where they only partially line up, an advisory flag is raised. Where they contradict, the discrepancy is stated in plain English and the affected analysis pauses until it is resolved. Accuracy discrepancies are never suppressed.',
            ],
            [
                'group' => 'Our approach to AI',
                'question' => 'Do you use AI in your work?',
                'answer' => 'Yes — as an evidencing tool, never as the authority. Every AI-assisted output cites its source. AI does not assert; it evidences. There is no score inflation, no hidden warnings, and uncertainty is disclosed when the data is insufficient. A human advisor is accountable for every recommendation.',
            ],
            [
                'group' => 'Our approach to AI',
                'question' => 'Will you tell me what I want to hear?',
                'answer' => 'No. Problems and low scores are stated clearly. Kindness in delivery, not in content. If something is broken in the business, you will hear it directly — with the evidence sitting underneath it.',
            ],
            [
                'group' => 'Working together',
                'question' => 'Do I get my own portal?',
                'answer' => 'Yes. Invited clients receive a secure portal with their questionnaire, document workspace, reports, and ongoing communication with your advisor. The same authentication and audit standards apply across every account type.',
            ],
            [
                'group' => 'Working together',
                'question' => 'What if I am not sure which engagement fits?',
                'answer' => 'Start with a discovery call. We will listen to what is actually going on, talk through which engagement type makes sense, and tell you honestly if a different provider would serve you better.',
            ],
        ];
    }
}
