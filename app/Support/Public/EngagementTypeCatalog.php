<?php

declare(strict_types=1);

namespace App\Support\Public;

/**
 * Public-site catalog of engagement types.
 *
 * Slugs stay aligned with App\Enums\EngagementType (standard_advisory,
 * due_diligence, post_acquisition_advisory, entrepreneur_module) plus the
 * additive NPO lane. Copy is written for the marketing site: warm and plain,
 * keeping the honest, evidence-based positioning without exposing internal
 * platform/AI mechanics.
 *
 * NPO copy follows the NPO module rules: value is mission-framed (never
 * presented as commercial profit), and Governance Review carries a clear
 * "informational, not legal advice" note.
 */
final class EngagementTypeCatalog
{
    /**
     * @return array<int, array{slug:string, title:string, tagline:string, summary:string, audience:string, deliverables:array<int,string>, accent:string, paths?:array<int,array{name:string, blurb:string}>, note?:string}>
     */
    public static function all(): array
    {
        return [
            [
                'slug' => 'standard_advisory',
                'title' => 'Standard Advisory',
                'tagline' => 'Where most journeys start.',
                'summary' => 'We get to know your whole business — the numbers, the people, the day-to-day, and where you want to take it — then give you a clear, honest picture of where you stand. Together we agree the handful of moves that will make the biggest difference, explained in plain language, with the reasoning shown. You will always know why we are recommending something.',
                'audience' => 'Established New Zealand SMEs who want a straight, well-grounded read on the business and a practical plan for what to tackle first.',
                'deliverables' => [
                    'A friendly, whole-of-business review',
                    'Clear findings in plain English, with the thinking behind them',
                    'A prioritised plan you can actually act on',
                    'A written report you can share with your team',
                ],
                'accent' => 'pacific',
            ],
            [
                'slug' => 'due_diligence',
                'title' => 'Due Diligence',
                'tagline' => 'Know what you are really buying.',
                'summary' => 'Thinking about buying a business? We look under the bonnet for you — the finances, the contracts, the people, the risks, and how much it leans on a handful of customers. If something does not add up, we tell you plainly and show you where we saw it. You walk into the deal with your eyes open.',
                'audience' => 'Owners and investors weighing up an acquisition, merger, or sizeable investment.',
                'deliverables' => [
                    'A guided, secure review of the target’s documents',
                    'A ranked register of risks and red flags',
                    'Findings across finance and operations',
                    'A due-diligence report you can rely on',
                ],
                'accent' => 'admiralty',
            ],
            [
                'slug' => 'post_acquisition_advisory',
                'title' => 'Post-acquisition Advisory',
                'tagline' => 'The first 100 days, mapped out.',
                'summary' => 'The deal is done — now the real work starts. We help you close the gaps the diligence uncovered, line up the early wins, and settle into a steady advisory rhythm, so the business you bought becomes the business you wanted.',
                'audience' => 'Buyers who have just settled and want to turn diligence findings into real momentum.',
                'deliverables' => [
                    'A gap assessment against your diligence findings',
                    'A sequenced plan of early priorities and quick wins',
                    'A clear report for your stakeholders',
                    'An ongoing advisory relationship',
                ],
                'accent' => 'deep-cove',
            ],
            [
                'slug' => 'entrepreneur_module',
                'title' => 'Entrepreneur Module',
                'tagline' => 'From first idea to ready-to-go.',
                'summary' => 'Building something new? We walk alongside you from the first idea through to launch — pressure-testing the concept, shaping the plan, and giving you an honest read on whether it is ready. You will hear the encouraging parts and the hard parts, because both matter when it is your time and money on the line.',
                'audience' => 'Founders and early-stage operators getting something off the ground in New Zealand.',
                'deliverables' => [
                    'A readiness and idea-validation review',
                    'A staged build plan with regular mentoring',
                    'An honest assessment of where the idea stands',
                    'A natural path into Standard Advisory once you are trading',
                ],
                'accent' => 'cognac',
            ],
            [
                'slug' => 'npo',
                'title' => 'Not-for-Profits & Social Enterprises',
                'tagline' => 'Mission first, with the evidence to back it.',
                'summary' => 'For New Zealand charities, community organisations, and social enterprises. We look at the health of the whole organisation — from mission and services through to governance, funding, and the difference you make — and frame everything around your impact, not commercial profit. Where we estimate value, we keep it mission-based and we are upfront about the range.',
                'audience' => 'Charities, incorporated societies, community groups, and social enterprises right across Aotearoa New Zealand.',
                'deliverables' => [
                    'An organisation-wide health review',
                    'An impact summary for your board and supporters',
                    'Funder-ready accountability reporting',
                    'Findings written plainly for boards and trustees',
                ],
                'paths' => [
                    [
                        'name' => 'Standard NPO',
                        'blurb' => 'A full health review across eight areas: mission and strategy, service delivery, governance and compliance, financial sustainability, people and capability, impact measurement, funding resilience, and Te Tiriti o Waitangi.',
                    ],
                    [
                        'name' => 'Social Enterprise',
                        'blurb' => 'A dual scorecard for organisations balancing a commercial engine with a social mission — so both sides get an honest read, side by side.',
                    ],
                    [
                        'name' => 'Governance Review',
                        'blurb' => 'An independent look at governance and compliance for your board, with findings, supporting notes, and clear source references. Informational — it does not replace legal advice.',
                    ],
                ],
                'note' => 'Te Tiriti o Waitangi is one of the eight areas we look at, and it can be woven through the whole review or considered on its own. Governance reviews are informational and are not a substitute for legal advice.',
                'accent' => 'harbour',
            ],
        ];
    }

    /**
     * Shorter variant for the home page cards.
     *
     * @return array<int, array{slug:string, title:string, tagline:string, summary:string, accent:string}>
     */
    public static function summaries(): array
    {
        return array_map(
            fn (array $e) => [
                'slug' => $e['slug'],
                'title' => $e['title'],
                'tagline' => $e['tagline'],
                'summary' => $e['summary'],
                'accent' => $e['accent'],
            ],
            self::all(),
        );
    }

    /**
     * Options for the contact form's "What's this about?" select.
     *
     * @return array<int, array{value:string, label:string}>
     */
    public static function selectOptions(): array
    {
        $options = array_map(
            fn (array $e) => ['value' => $e['slug'], 'label' => $e['title']],
            self::all(),
        );

        $options[] = ['value' => 'general', 'label' => 'Just exploring / general enquiry'];

        return $options;
    }
}
