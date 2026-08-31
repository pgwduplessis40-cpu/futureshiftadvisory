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
                'answer' => 'We are a New Zealand business advisory practice. We help SME owners, people buying a business, founders getting started, and not-for-profits - with clear, honest advice they can act on. We look at what is really going on, explain what we find in plain language, and help you decide what to do next.',
            ],
            [
                'group' => 'About the work',
                'question' => 'Who do you work with?',
                'answer' => 'Established New Zealand SMEs who want a straight read on the business, people weighing up buying a business, founders building something new, and charities, community groups, and social enterprises. If you are after comfortable reassurance, we are probably not your people. If you want the honest picture, kindly delivered, we will get on well.',
            ],
            [
                'group' => 'About the work',
                'question' => 'Are you really focused on New Zealand?',
                'answer' => 'Yes. We are based in Hamilton and work with organisations across the country. Our advice is grounded in the New Zealand context - local regulation, tax, and the way business actually works here - rather than a borrowed overseas playbook.',
            ],
            [
                'group' => 'About the work',
                'question' => 'Do you use AI in your advice?',
                'answer' => 'We use good tools to work through information quickly and thoroughly, but the judgement and the recommendations are ours - a real advisor stands behind every one. Whatever we tell you, we can show you the evidence for it.',
            ],
            [
                'group' => 'How engagements start',
                'question' => 'How do I become a client?',
                'answer' => 'Send us a note through the contact form. We reply personally, set up a no-pressure conversation, and if it is a good fit we invite you in. We do not run open sign-ups - every client is invited and verified, which helps keep your information safe.',
            ],
            [
                'group' => 'How engagements start',
                'question' => 'What will it cost?',
                'answer' => 'It depends which engagement you need, and we are upfront about how each one is priced. Due Diligence and the Entrepreneur Module are quoted as fixed fees - you know the number before we start, and it does not move unless you ask for something outside the agreed scope. For Standard Advisory and not-for-profit work, the fee is set after we have properly looked at your organisation: we evaluate where things stand, analyse what we find, and shape a strategic plan - then the quote follows from that, sized to the work genuinely needed rather than guessed at upfront. Either way you will see the number, and how we arrived at it, before any work begins.',
            ],
            [
                'group' => 'How engagements start',
                'question' => 'How long does an engagement take?',
                'answer' => 'A Standard Advisory review usually takes weeks, not months. Due diligence follows your deal timeline. Work with founders runs in stages over a few months - because the work is real, not because we are stretching it out.',
            ],
            [
                'group' => 'Starting a new business',
                'question' => 'How do I know if my business idea will work?',
                'answer' => 'You cannot know for certain - but you can find out a great deal before you spend serious money. That is what idea validation is for: testing the concept against real demand, the numbers, who else is already doing it, and what it would genuinely take to deliver. Sometimes the honest answer is that the idea does not hold up in its current form. Hearing that early costs you a conversation; hearing it two years in costs a great deal more.',
            ],
            [
                'group' => 'Starting a new business',
                'question' => 'What does it cost to validate my business idea?',
                'answer' => 'There is a cost, and we quote it as a fixed fee up front so you know exactly what you are committing to before you decide. It is worth weighing that against what is actually at risk. The expensive part of a business idea is rarely the checking - it is the borrowed money, the savings, and the year or two spent building something the evidence never supported. Validation is a small, known cost paid early to avoid a much larger, unknown one later. And if the idea does stack up, you are not just left with a yes: you come out with the plan and the numbers to take to a bank or an investor.',
            ],
            [
                'group' => 'Starting a new business',
                'question' => 'Do I need a business plan in New Zealand?',
                'answer' => 'Legally, no - you can register a company without one. Practically, yes: if you are approaching a bank, an investor, or a funder, they will ask for one. And even when nobody asks, writing it is usually where founders discover the gaps in their own thinking. We help you build a plan with real numbers behind it - a working document, not a template you fill in once and file away.',
            ],
            [
                'group' => 'Starting a new business',
                'question' => 'Should I set up as a sole trader or a company?',
                'answer' => 'It depends on the risk you are carrying, whether you intend to bring others in, and what you expect to earn. Sole trader is simpler and cheaper to run. A company limits your personal liability and is usually expected if you want outside investment. We will talk it through against your actual situation, and say plainly when the simpler option is the right one. This is general guidance rather than legal or tax advice - where you need a lawyer or an accountant, we will tell you.',
            ],
            [
                'group' => 'Starting a new business',
                'question' => 'Can you help me get investor or funding ready?',
                'answer' => 'Yes. Banks, investors, and grant funders all want the same three things: believable numbers, a clear plan, and evidence you have thought about what could go wrong. We help you get those in order before you go asking - and if we think you are not ready yet, we will say so rather than let you burn a first impression.',
            ],
            [
                'group' => 'Starting a new business',
                'question' => 'I have an idea but have not started yet - is it too early to talk to you?',
                'answer' => 'Not at all - that is often the best time. The earliest conversations are the cheapest ones to have, because nothing has been built yet and every option is still open. Bring the idea in whatever shape it is in.',
            ],
            [
                'group' => 'Working with not-for-profits',
                'question' => 'Do you work with charities and not-for-profits?',
                'answer' => 'We do. We have a dedicated lane for charities, incorporated societies, community organisations, and social enterprises. We look at the health of the whole organisation and frame everything around your mission and the difference you make - not commercial profit.',
            ],
            [
                'group' => 'Working with not-for-profits',
                'question' => 'How do you approach Te Tiriti o Waitangi?',
                'answer' => 'Te Tiriti o Waitangi is one of the eight areas we review for not-for-profits. It can be woven through the whole review or considered on its own, depending on what suits your organisation.',
            ],
            [
                'group' => 'Working with not-for-profits',
                'question' => 'Can you review our governance?',
                'answer' => 'Yes - an independent governance and compliance review for your board, with clear findings and the sources behind them. It is designed to support your board’s decisions and is informational; it does not replace legal advice. We will also flag matters like Incorporated Societies Act 2022 re-registration so nothing slips through.',
            ],
            [
                'group' => 'Security & confidentiality',
                'question' => 'Is my information safe with you?',
                'answer' => 'Yes, and we take it seriously. Every account uses multi-factor sign-in, documents are encrypted and checked before they are stored, and only the people working on your engagement can see your information. Confidentiality is not negotiable.',
            ],
            [
                'group' => 'Security & confidentiality',
                'question' => 'What happens to the documents I share?',
                'answer' => 'We review what you send to make sure it lines up with the picture we are forming. If something does not match, we will not bury it - we will raise it with you in plain English and work it out together before going any further.',
            ],
            [
                'group' => 'Working together',
                'question' => 'Will you tell me what I want to hear?',
                'answer' => 'No - and that is rather the point. If something in the business needs attention, you will hear it clearly, with the evidence behind it. We are kind in how we say it and honest in what we say.',
            ],
            [
                'group' => 'Working together',
                'question' => 'Do I get my own portal?',
                'answer' => 'Yes. Invited clients get a secure online space for their questionnaire, documents, reports, and messages with their advisor - all held to the same careful security standards.',
            ],
            [
                'group' => 'Working together',
                'question' => 'Can you build tools or software for us?',
                'answer' => 'Sometimes, yes - when it genuinely helps. While we are working with you, if we spot a job a custom tool could do faster or more reliably than doing it by hand, we will flag it, show you whether it pays off, and quote to build it if it does. That goes for bigger needs as well - if what your business really needs is a more substantial system, we can scope, quote, and build that too, in stages you can see working. Either way it is a benefit of working with us rather than a separate service - and we will tell you honestly when an off-the-shelf option would serve you better.',
            ],
            [
                'group' => 'Working together',
                'question' => 'What if I am not sure which service fits?',
                'answer' => 'Start with a discovery call. We will listen to what is going on, suggest the right fit, and tell you honestly if someone else would serve you better.',
            ],
        ];
    }
}
