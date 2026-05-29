<?php

declare(strict_types=1);

namespace App\Support\Public;

/**
 * Public-site FAQ content.
 *
 * Written warm and plain, answer-first (good for search snippets and AI answer
 * engines), keeping the honest, evidence-based positioning. Internal platform
 * and AI mechanics are kept out of the client-facing copy.
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
                'question' => 'What does Future Shift Advisory do?',
                'answer' => 'We are a New Zealand business advisory practice. We help SME owners, people buying a business, founders getting started, and not-for-profits — with clear, honest advice they can act on. We look at what is really going on, explain what we find in plain language, and help you decide what to do next.',
            ],
            [
                'group' => 'About the work',
                'question' => 'Who do you work with?',
                'answer' => 'Established New Zealand SMEs who want a straight read on the business, people weighing up buying a business, founders building something new, and charities, community groups, and social enterprises. If you are after comfortable reassurance, we are probably not your people. If you want the honest picture, kindly delivered, we will get on well.',
            ],
            [
                'group' => 'About the work',
                'question' => 'Are you really focused on New Zealand?',
                'answer' => 'Yes. We are based in Hamilton and work with organisations across the country. Our advice is grounded in the New Zealand context — local regulation, tax, and the way business actually works here — rather than a borrowed overseas playbook.',
            ],
            [
                'group' => 'About the work',
                'question' => 'Do you use AI in your advice?',
                'answer' => 'We use good tools to work through information quickly and thoroughly, but the judgement and the recommendations are ours — a real advisor stands behind every one. Whatever we tell you, we can show you the evidence for it.',
            ],
            [
                'group' => 'How engagements start',
                'question' => 'How do I become a client?',
                'answer' => 'Send us a note through the contact form. We reply personally, set up a no-pressure conversation, and if it is a good fit we invite you in. We do not run open sign-ups — every client is invited and verified, which helps keep your information safe.',
            ],
            [
                'group' => 'How engagements start',
                'question' => 'What will it cost?',
                'answer' => 'It depends on what you actually need, so we talk that through before quoting and we are clear about how the fee is worked out. We will walk you through the options in your first conversation — no surprises.',
            ],
            [
                'group' => 'How engagements start',
                'question' => 'How long does an engagement take?',
                'answer' => 'A Standard Advisory review usually takes weeks, not months. Due diligence follows your deal timeline. Work with founders runs in stages over a few months — because the work is real, not because we are stretching it out.',
            ],
            [
                'group' => 'Working with not-for-profits',
                'question' => 'Do you work with charities and not-for-profits?',
                'answer' => 'We do. We have a dedicated lane for charities, incorporated societies, community organisations, and social enterprises. We look at the health of the whole organisation and frame everything around your mission and the difference you make — not commercial profit.',
            ],
            [
                'group' => 'Working with not-for-profits',
                'question' => 'How do you approach Te Tiriti o Waitangi?',
                'answer' => 'Te Tiriti o Waitangi is one of the eight areas we review for not-for-profits. It can be woven through the whole review or considered on its own, depending on what suits your organisation.',
            ],
            [
                'group' => 'Working with not-for-profits',
                'question' => 'Can you review our governance?',
                'answer' => 'Yes — an independent governance and compliance review for your board, with clear findings and the sources behind them. It is designed to support your board’s decisions and is informational; it does not replace legal advice. We will also flag matters like Incorporated Societies Act 2022 re-registration so nothing slips through.',
            ],
            [
                'group' => 'Security & confidentiality',
                'question' => 'Is my information safe with you?',
                'answer' => 'Yes, and we take it seriously. Every account uses multi-factor sign-in, documents are encrypted and checked before they are stored, and only the people working on your engagement can see your information. Confidentiality is not negotiable.',
            ],
            [
                'group' => 'Security & confidentiality',
                'question' => 'What happens to the documents I share?',
                'answer' => 'We review what you send to make sure it lines up with the picture we are forming. If something does not match, we will not bury it — we will raise it with you in plain English and work it out together before going any further.',
            ],
            [
                'group' => 'Working together',
                'question' => 'Will you tell me what I want to hear?',
                'answer' => 'No — and that is rather the point. If something in the business needs attention, you will hear it clearly, with the evidence behind it. We are kind in how we say it and honest in what we say.',
            ],
            [
                'group' => 'Working together',
                'question' => 'Do I get my own portal?',
                'answer' => 'Yes. Invited clients get a secure online space for their questionnaire, documents, reports, and messages with their advisor — all held to the same careful security standards.',
            ],
            [
                'group' => 'Working together',
                'question' => 'What if I am not sure which service fits?',
                'answer' => 'Start with a discovery call. We will listen to what is going on, suggest the right fit, and tell you honestly if someone else would serve you better.',
            ],
        ];
    }
}
