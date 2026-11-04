<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

final class CandidateBlocks
{
    /** @return list<DocumentBlock> */
    public function fromHtml(string $html, string $planId, string $renderer): array
    {
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $nodes = (new \DOMXPath($document))->query('//h1|//h2|//h3|//h4|//p|//td|//th|//li[not(.//p)]');
        $blocks = [];
        foreach ($nodes ?: [] as $index => $node) {
            $text = trim($node->textContent);
            if ($text === '') {
                continue;
            }
            $id = 'render:'.$index;
            $reference = new SourceReference('render_candidate', $planId, $renderer.':'.$node->nodeName.':'.$index, hash('sha256', $html));
            $blocks[] = new DocumentBlock($id, $text, $reference, heading: str_starts_with($node->nodeName, 'h') && $node->nodeName !== 'html');
        }

        return $blocks;
    }
}
