<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;

class EmbeddedContentRenderer
{
    public static function render(?string $html): string
    {
        $html = (string) ($html ?? '');

        if ($html === '' || ! str_contains($html, 'data-bilibili-video')) {
            return $html;
        }

        $previous = libxml_use_internal_errors(true);

        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadHTML(
            '<?xml encoding="utf-8" ?><body>'.$html.'</body>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );

        $xpath = new DOMXPath($document);
        /** @var DOMElement $node */
        foreach ($xpath->query('//*[@data-bilibili-video="1"]') ?: [] as $node) {
            $aid = trim((string) $node->getAttribute('data-aid'));
            $bvid = trim((string) $node->getAttribute('data-bvid'));
            $cid = trim((string) $node->getAttribute('data-cid'));
            $page = trim((string) $node->getAttribute('data-p'));
            $width = static::normalizeWidth($node->getAttribute('data-width'));
            $height = static::normalizeHeight($node->getAttribute('data-height'));
            $align = static::normalizeAlign($node->getAttribute('data-align'));

            if ($aid === '' || $bvid === '' || $cid === '') {
                continue;
            }

            $src = 'https://player.bilibili.com/player.html?'.http_build_query([
                'isOutside' => 'true',
                'aid' => $aid,
                'bvid' => $bvid,
                'cid' => $cid,
                'p' => $page !== '' ? $page : '1',
                'autoplay' => '0',
            ]);

            $replacement = $document->createElement('div');
            $replacement->setAttribute('class', 'bilibili-video-embed');
            $replacement->setAttribute('style', sprintf(
                'margin:%s;text-align:%s;',
                match ($align) {
                    'left' => '20px auto 20px 0',
                    'right' => '20px 0 20px auto',
                    default => '20px auto',
                },
                $align,
            ));

            $iframe = $document->createElement('iframe');
            $iframe->setAttribute('src', $src);
            $iframe->setAttribute('style', sprintf(
                'display:block;width:%s;height:%s;max-width:100%%;margin:%s;border:0;border-radius:16px;overflow:hidden;background:#000;',
                htmlspecialchars($width, ENT_QUOTES),
                htmlspecialchars($height, ENT_QUOTES),
                match ($align) {
                    'left' => '0 auto 0 0',
                    'right' => '0 0 0 auto',
                    default => '0 auto',
                },
            ));
            $iframe->setAttribute('allowfullscreen', 'true');
            $iframe->setAttribute('scrolling', 'no');
            $iframe->setAttribute('frameborder', '0');
            $iframe->setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');

            $replacement->appendChild($iframe);
            $node->parentNode?->replaceChild($replacement, $node);
        }

        $body = $document->getElementsByTagName('body')->item(0);
        $output = '';

        if ($body) {
            foreach ($body->childNodes as $child) {
                $output .= $document->saveHTML($child);
            }
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $output;
    }

    protected static function normalizeWidth(?string $raw): string
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return '100%';
        }

        if (preg_match('/^\d+$/', $raw)) {
            return $raw.'px';
        }

        if (preg_match('/^\d+(?:px|vw|rem|em|%)$/i', $raw)) {
            return $raw;
        }

        return '100%';
    }

    protected static function normalizeHeight(?string $raw): string
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return '450px';
        }

        if (preg_match('/^\d+$/', $raw)) {
            return $raw.'px';
        }

        if (preg_match('/^\d+(?:px|vh|rem|em)$/i', $raw)) {
            return $raw;
        }

        return '450px';
    }

    protected static function normalizeAlign(?string $raw): string
    {
        $raw = strtolower(trim((string) $raw));

        if (in_array($raw, ['left', 'center', 'right'], true)) {
            return $raw;
        }

        return 'center';
    }
}
