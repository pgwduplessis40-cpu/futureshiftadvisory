<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ServiceRatePackage;
use App\Services\Blog\BlogPosts;
use App\Services\Entrepreneurs\EntrepreneurServiceOffer;
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
    public function __invoke(EntrepreneurServiceOffer $offers, BlogPosts $blog): Response
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
            .'from Hamilton, Waikato. Most engagements begin with a discovery call; Idea Validation has a dedicated online entry page. '
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
        $ideaOffer = $offers->forScope(ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION);
        $ideaPrice = $ideaOffer['available'] && $ideaOffer['amount_ex_gst'] !== null
            ? ' Current price: $'.number_format($ideaOffer['amount_ex_gst'], 2).' + GST.'
            : '';
        $lines[] = "- [Home]({$base}/): Idea validation for New Zealand founders - the starting point, whose answers then feed the business plan and budget.";
        $lines[] = "- [How idea validation works]({$base}/services/entrepreneur): What idea validation checks, the advisor review within 24 hours, how it feeds the business plan and budget, and funding readiness.{$ideaPrice}";
        $lines[] = "- [Start idea validation]({$base}/validate-idea/purchase): Create an account and pay the fixed fee to begin.";
        $lines[] = "- [Services]({$base}/services): Add-on services for established businesses, buyers, and not-for-profits - who each is for, and what you receive.";
        $lines[] = "- [Terms of Use]({$base}/terms): The current published customer terms, also available as structured JSON at {$base}/terms.json.";
        $lines[] = "- [Privacy Policy]({$base}/privacy): How Future Shift Advisory collects, uses, holds, and shares personal information, also available as structured JSON at {$base}/privacy.json.";
        $lines[] = "- [About]({$base}/about): The practice's principles and the Principal Advisor's background.";
        $lines[] = "- [Blog]({$base}/blog): Practical, honest writing on starting and building a business in New Zealand.";
        $lines[] = "- [FAQ]({$base}/faq): Answers on engagements, fees, security, not-for-profits, and use of AI.";
        $lines[] = "- [Contact]({$base}/contact): Enquiry form to book a discovery call.";
        $lines[] = '';

        $posts = $blog->published()
            ->map(fn ($post): array => $blog->summary($post))
            ->all();
        if ($posts !== []) {
            $lines[] = '## Blog';
            $lines[] = '';
            foreach ($posts as $post) {
                $lines[] = sprintf(
                    '- [%s](%s/blog/%s) (%s): %s',
                    $post['title'],
                    $base,
                    $post['slug'],
                    $post['date_iso'],
                    $post['description'],
                );
            }
            $lines[] = '';
        }

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
