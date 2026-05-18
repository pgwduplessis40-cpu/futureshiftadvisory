<?php

declare(strict_types=1);

namespace App\Support\Public;

/**
 * Public-site catalog of engagement types.
 * Mirrors the spec's `clients.engagement_type` enum
 * (standard_advisory, due_diligence, post_acquisition_advisory, entrepreneur_module).
 * Will be replaced by reads from EngagementType model once that lands per PLAN.md WO-14.
 */
final class EngagementTypeCatalog
{
    /**
     * @return array<int, array{slug:string, title:string, tagline:string, summary:string, audience:string, deliverables:array<int,string>, accent:string}>
     */
    public static function all(): array
    {
        return [
            [
                'slug' => 'standard_advisory',
                'title' => 'Standard Advisory',
                'tagline' => 'Where most engagements begin.',
                'summary' => 'A structured diagnostic of the whole business — finance, people, operations, sales, strategy, compliance — followed by an evidence-backed roadmap of the moves that actually shift the dial. Every finding cites its source. Nothing is asserted without a reason.',
                'audience' => 'Established NZ SMEs that want a clear, honest read on where they stand and what to fix first.',
                'deliverables' => [
                    'Ten-section diagnostic across the business',
                    'Plain-English findings with source attribution',
                    'Prioritised advisory roadmap',
                    'Client-facing diagnostic report',
                ],
                'accent' => 'pacific',
            ],
            [
                'slug' => 'due_diligence',
                'title' => 'Due Diligence',
                'tagline' => 'The truth before the contract.',
                'summary' => 'Acquisition-grade review of a target business: financials, contracts, HR, compliance, customer concentration, risk. Document discrepancies are flagged in plain English, never suppressed. You walk in knowing what you are actually buying.',
                'audience' => 'Owners and investors evaluating an acquisition, merger, or material investment.',
                'deliverables' => [
                    'Virtual data room intake & document verification',
                    'Red-flag register with severity and evidence',
                    'Financial and operational findings',
                    'DD report with standard liability disclaimer',
                ],
                'accent' => 'admiralty',
            ],
            [
                'slug' => 'post_acquisition_advisory',
                'title' => 'Post-acquisition Advisory',
                'tagline' => 'The first 100 days, designed.',
                'summary' => 'Gap analysis against the DD findings, integration sequencing, and the early advisory cadence that turns a deal into a working business. Built on the same evidence base used to underwrite the acquisition.',
                'audience' => 'Buyers who have just closed and need to convert diligence findings into action.',
                'deliverables' => [
                    'Post-acquisition gap assessment',
                    'Integration & quick-win sequencing',
                    'Stakeholder report',
                    'Rolling advisory engagement',
                ],
                'accent' => 'deep-cove',
            ],
            [
                'slug' => 'entrepreneur_module',
                'title' => 'Entrepreneur Module',
                'tagline' => 'From idea to advisory-ready.',
                'summary' => 'A staged build for founders pre-launch: readiness, idea validation, structured building phases, assessment, revision, launch. Honest scoring. No score inflation. You hear what the evidence says, not what you want to hear.',
                'audience' => 'Founders and early-stage operators building something new in New Zealand.',
                'deliverables' => [
                    'Readiness & idea-validation review',
                    'Staged build plan with mentor cadence',
                    'Entrepreneur assessment report',
                    'Path into Standard Advisory once trading',
                ],
                'accent' => 'cognac',
            ],
        ];
    }

    /**
     * Shorter variant for the home page hero cards.
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
