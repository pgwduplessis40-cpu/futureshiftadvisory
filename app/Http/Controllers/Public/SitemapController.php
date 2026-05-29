<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * Render the public XML sitemap using the canonical production origin.
     */
    public function __invoke(): Response
    {
        $base = rtrim((string) config('app.public_url'), '/');

        // path => [changefreq, priority]
        $routes = [
            '/' => ['weekly', '1.0'],
            '/services' => ['monthly', '0.9'],
            '/about' => ['monthly', '0.7'],
            '/faq' => ['monthly', '0.6'],
            '/contact' => ['yearly', '0.8'],
        ];

        $lastmod = now()->toAtomString();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($routes as $path => [$changefreq, $priority]) {
            $loc = $base.($path === '/' ? '/' : $path);
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.htmlspecialchars($loc, ENT_XML1).'</loc>'."\n";
            $xml .= '    <lastmod>'.$lastmod.'</lastmod>'."\n";
            $xml .= '    <changefreq>'.$changefreq.'</changefreq>'."\n";
            $xml .= '    <priority>'.$priority.'</priority>'."\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>'."\n";

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
