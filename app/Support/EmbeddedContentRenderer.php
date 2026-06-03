<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;

class EmbeddedContentRenderer
{
    protected const DOCUMENT_EXTENSIONS = [
        'pdf' => 'PDF',
        'doc' => 'Word',
        'docx' => 'Word',
        'xls' => 'Excel',
        'xlsx' => 'Excel',
        'ppt' => 'PPT',
        'pptx' => 'PPT',
        'txt' => 'TXT',
        'zip' => 'ZIP',
        'rar' => 'RAR',
        '7z' => '7Z',
    ];

    public static function render(?string $html): string
    {
        $html = ContentHtmlSanitizer::sanitize((string) ($html ?? ''));

        if ($html === '' || ! static::hasRenderableMarkup($html)) {
            return $html;
        }

        $previous = libxml_use_internal_errors(true);

        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadHTML(
            '<?xml encoding="utf-8" ?><body>'.$html.'</body>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );

        $xpath = new DOMXPath($document);
        static::renderDocumentAndExternalLinks($document, $xpath);

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
            $replacement->setAttribute('class', sprintf('bilibili-video-embed bilibili-video-embed--align-%s', $align));

            $iframe = $document->createElement('iframe');
            $iframe->setAttribute('src', $src);
            $iframe->setAttribute('class', sprintf(
                'bilibili-video-embed__frame bilibili-video-embed__frame--align-%s bilibili-video-embed__frame--width-%s bilibili-video-embed__frame--height-%s',
                $align,
                static::normalizeSizeToken($width),
                static::normalizeSizeToken($height),
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

    protected static function hasRenderableMarkup(string $html): bool
    {
        return str_contains($html, '<a ')
            || str_contains($html, '<a>')
            || str_contains($html, 'data-bilibili-video');
    }

    protected static function renderDocumentAndExternalLinks(DOMDocument $document, DOMXPath $xpath): void
    {
        /** @var DOMElement $link */
        foreach ($xpath->query('//a[@href]') ?: [] as $link) {
            if (! $link->parentNode || static::containsImage($link)) {
                continue;
            }

            $href = trim((string) $link->getAttribute('href'));

            if ($href === '' || str_starts_with($href, '#')) {
                continue;
            }

            $extension = static::documentExtension($href);

            if ($extension !== null) {
                $replacement = static::createDocumentLinkCard($document, $link, $href, $extension);
            } elseif (static::isExternalHttpUrl($href)) {
                $replacement = static::createExternalLinkCard($document, $link, $href);
            } else {
                continue;
            }

            $link->parentNode->replaceChild($replacement, $link);
        }
    }

    protected static function containsImage(DOMElement $link): bool
    {
        return $link->getElementsByTagName('img')->length > 0;
    }

    protected static function documentExtension(string $href): ?string
    {
        $path = (string) (parse_url(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'), PHP_URL_PATH) ?: '');
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return array_key_exists($extension, self::DOCUMENT_EXTENSIONS) ? $extension : null;
    }

    protected static function createDocumentLinkCard(DOMDocument $document, DOMElement $source, string $href, string $extension): DOMElement
    {
        $isExternal = static::isExternalHttpUrl($href);
        $card = $document->createElement('a');
        $card->setAttribute('class', 'cms-file-card cms-file-card--'.static::fileTypeToken($extension).($isExternal ? ' cms-file-card--external' : ''));
        $card->setAttribute('href', $href);
        $card->setAttribute('target', '_blank');
        $card->setAttribute('rel', 'noopener noreferrer');

        if ($isExternal) {
            $card->setAttribute('data-cms-external-link', '1');
            $card->setAttribute('data-cms-external-host', static::urlHost($href));
        }

        $icon = $document->createElement('span');
        $icon->setAttribute('class', 'cms-file-card__icon');
        $icon->appendChild($document->createTextNode(strtoupper($extension)));

        $body = $document->createElement('span');
        $body->setAttribute('class', 'cms-file-card__body');

        $title = $document->createElement('span');
        $title->setAttribute('class', 'cms-file-card__title');
        $title->appendChild($document->createTextNode(static::linkTitle($source, $href)));

        $meta = $document->createElement('span');
        $meta->setAttribute('class', 'cms-file-card__meta');
        $meta->appendChild($document->createTextNode((self::DOCUMENT_EXTENSIONS[$extension] ?? strtoupper($extension)).' 文件'.($isExternal ? ' · 外部来源' : '')));

        $action = $document->createElement('span');
        $action->setAttribute('class', 'cms-file-card__action');
        $action->appendChild($document->createTextNode('下载'));

        $body->appendChild($title);
        $body->appendChild($meta);
        $card->appendChild($icon);
        $card->appendChild($body);
        $card->appendChild($action);

        return $card;
    }

    protected static function createExternalLinkCard(DOMDocument $document, DOMElement $source, string $href): DOMElement
    {
        $host = static::urlHost($href);
        $card = $document->createElement('a');
        $card->setAttribute('class', 'cms-external-card');
        $card->setAttribute('href', $href);
        $card->setAttribute('target', '_blank');
        $card->setAttribute('rel', 'noopener noreferrer');
        $card->setAttribute('data-cms-external-link', '1');
        $card->setAttribute('data-cms-external-host', $host);

        $icon = $document->createElement('span');
        $icon->setAttribute('class', 'cms-external-card__icon');
        $icon->appendChild($document->createTextNode('外'));

        $body = $document->createElement('span');
        $body->setAttribute('class', 'cms-external-card__body');

        $title = $document->createElement('span');
        $title->setAttribute('class', 'cms-external-card__title');
        $title->appendChild($document->createTextNode(static::linkTitle($source, $href)));

        $meta = $document->createElement('span');
        $meta->setAttribute('class', 'cms-external-card__meta');
        $meta->appendChild($document->createTextNode($host !== '' ? '外部链接 · '.$host : '外部链接'));

        $action = $document->createElement('span');
        $action->setAttribute('class', 'cms-external-card__action');
        $action->appendChild($document->createTextNode('打开'));

        $body->appendChild($title);
        $body->appendChild($meta);
        $card->appendChild($icon);
        $card->appendChild($body);
        $card->appendChild($action);

        return $card;
    }

    protected static function linkTitle(DOMElement $source, string $href): string
    {
        $title = trim(preg_replace('/\s+/u', ' ', (string) $source->textContent) ?? '');
        $fileName = static::fileNameFromUrl($href);

        if ($title !== '' && ! static::looksLikePath($title)) {
            return $title;
        }

        return $fileName !== '' ? $fileName : ($title !== '' ? $title : $href);
    }

    protected static function fileNameFromUrl(string $href): string
    {
        $path = (string) (parse_url(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'), PHP_URL_PATH) ?: '');

        return rawurldecode(basename($path));
    }

    protected static function looksLikePath(string $value): bool
    {
        $value = trim($value);

        return str_starts_with($value, '/')
            || preg_match('#^https?://#i', $value) === 1
            || preg_match('#^[A-Za-z0-9_-]+(?:/[A-Za-z0-9_.% -]+)+$#u', $value) === 1;
    }

    protected static function fileTypeToken(string $extension): string
    {
        return match ($extension) {
            'doc', 'docx' => 'word',
            'xls', 'xlsx' => 'excel',
            'ppt', 'pptx' => 'ppt',
            'zip', 'rar', '7z' => 'archive',
            default => $extension,
        };
    }

    protected static function isExternalHttpUrl(string $href): bool
    {
        $scheme = strtolower((string) (parse_url($href, PHP_URL_SCHEME) ?: ''));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = static::urlHost($href);
        $currentHost = static::currentHost();

        return $host !== '' && $currentHost !== '' && strcasecmp($host, $currentHost) !== 0;
    }

    protected static function urlHost(string $href): string
    {
        return strtolower((string) (parse_url($href, PHP_URL_HOST) ?: ''));
    }

    protected static function currentHost(): string
    {
        try {
            return strtolower((string) request()->getHost());
        } catch (\Throwable) {
            return '';
        }
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

    protected static function normalizeSizeToken(string $value): string
    {
        return str_replace(['%', '.'], ['pct', '-'], strtolower($value));
    }
}
