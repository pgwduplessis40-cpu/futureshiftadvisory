<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Public\EngagementTypeCatalog;
use App\Support\Public\FaqCatalog;
use Illuminate\Http\Response;

/**
 * Serves /llms.txt - a plain-markdown summary of the practice for AI answer
 * engines (see llmstxt.org). Generated from the same catalogs the public pages
 * render, so it cannot drift out of sync with the site.
 */
class LlmsTxtController extends Controller
{
    public function __invoke(): Response
    {
        $base = rtrim((string) config('app.public_url'), '/');

        $lines = [];
        $lines[] = '# Future Shift Advisory';
        $lines[] = '';
        $lines[] = '> A Hamilton-based business advisory practice serving New Zealand SMEs, '
            .'founders, business buyers, and not-for-profits. Clear, honest, evidence-based '
            .'advice - findings are explained in plain language with the reasoning shown.';
        $lines[] = '';
        $lines[] = 'Future Shift Advisory is led by Pieter Du Plessis, Principal Advisor, a member '
            .'of the Institute of Advisors (IOA). The practice works remotely across New Zealand '
            .'from Hamilton, Waikato. Engagements are invite-only and begin with a discovery call. '
            .'Confidentiality is the baseline: multi-factor sign-in, encrypted documents, and '
            .'access limited to the people working on the engagement.';
        $lines[] = '';

        $lines[] = '## Services';
        $lines[] = '';
        foreach (EngagementTypeCatalog::all() as $service) {
            $lines[] = sprintf(
                '- [%s](%s/services#%s): %s %s',
                $service['title'],
                $base,
                $service['slug'],
                $service['tagline'],
                $service['summary'],
            );

            foreach ($service['paths'] ?? [] as $path) {
                $lines[] = sprintf('  - %s: %s', $path['name'], $path['blurb']);
            }
        }
        $lines[] = '';

        $lines[] = '## Pages';
        $lines[] = '';
        $lines[] = "- [Home]({$base}/): Overview of the practice and how we work.";
        $lines[] = "- [Services]({$base}/services): All engagement types, who each is for, and what you receive.";
        $lines[] = "- [About]({$base}/about): The practice's principles and the Principal Advisor's background.";
        $lines[] = "- [FAQ]({$base}/faq): Answers on engagements, fees, security, not-for-profits, and use of AI.";
        $lines[] = "- [Contact]({$base}/contact): Enquiry form to book a discovery call.";
        $lines[] = '';

        $lines[] = '## Frequently asked questions';
        $lines[] = '';
        foreach (FaqCatalog::all() as $faq) {
            $lines[] = sprintf('### %s', $faq['question']);
            $lines[] = '';
            $lines[] = $faq['answer'];
            $lines[] = '';
        }

        $lines[] = '## Contact';
        $lines[] = '';
        $lines[] = '- Email: hello@futureshiftadvisory.nz';
        $lines[] = '- Location: Hamilton, Waikato, New Zealand (working nationwide)';
        $lines[] = "- Enquiries: {$base}/contact";
        $lines[] = '';

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
