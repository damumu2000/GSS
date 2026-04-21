<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Str;

class ContentHtmlSanitizer
{
    /**
     * @var array<int, string>
     */
    protected const DANGEROUS_TAGS = [
        'script',
        'style',
        'iframe',
        'object',
        'embed',
        'form',
        'input',
        'button',
        'textarea',
        'select',
        'option',
        'link',
        'meta',
    ];

    /**
     * @var array<int, string>
     */
    protected const ALLOWED_TAGS = [
        'p',
        'br',
        'strong',
        'b',
        'em',
        'i',
        'u',
        's',
        'span',
        'div',
        'ul',
        'ol',
        'li',
        'blockquote',
        'a',
        'img',
        'figure',
        'figcaption',
        'table',
        'thead',
        'tbody',
        'tr',
        'th',
        'td',
        'h1',
        'h2',
        'h3',
        'h4',
        'h5',
        'h6',
        'hr',
    ];

    public static function sanitize(?string $html): string
    {
        $html = trim((string) ($html ?? ''));

        if ($html === '') {
            return '';
        }

        $wrappedHtml = '<!DOCTYPE html><html><body><div id="content-html-sanitizer-root">'.$html.'</div></body></html>';
        $document = new DOMDocument('1.0', 'UTF-8');
        $encodedHtml = mb_encode_numericentity($wrappedHtml, [0x80, 0x10FFFF, 0, ~0], 'UTF-8');

        libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            $encodedHtml,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        if (! $loaded) {
            return strip_tags($html);
        }

        $root = $document->getElementById('content-html-sanitizer-root');
        if (! $root instanceof DOMElement) {
            return strip_tags($html);
        }

        foreach (iterator_to_array($root->childNodes) as $childNode) {
            static::sanitizeNode($childNode);
        }

        $sanitized = '';
        foreach ($root->childNodes as $childNode) {
            $sanitized .= $document->saveHTML($childNode);
        }

        return trim($sanitized);
    }

    protected static function sanitizeNode(DOMNode $node): void
    {
        if ($node->nodeType === XML_COMMENT_NODE) {
            $node->parentNode?->removeChild($node);

            return;
        }

        if ($node->nodeType === XML_TEXT_NODE) {
            $node->nodeValue = preg_replace(
                '/[\x{00}-\x{08}\x{0B}\x{0C}\x{0E}-\x{1F}\x{7F}\x{200B}-\x{200D}\x{FEFF}]+/u',
                '',
                $node->nodeValue ?? '',
            ) ?? ($node->nodeValue ?? '');

            return;
        }

        if (! $node instanceof DOMElement) {
            $node->parentNode?->removeChild($node);

            return;
        }

        $tagName = Str::lower($node->tagName);

        if (in_array($tagName, static::DANGEROUS_TAGS, true)) {
            $node->parentNode?->removeChild($node);

            return;
        }

        if (! in_array($tagName, static::ALLOWED_TAGS, true)) {
            static::unwrapNode($node);

            return;
        }

        static::sanitizeAttributes($node, $tagName);

        foreach (iterator_to_array($node->childNodes) as $childNode) {
            static::sanitizeNode($childNode);
        }
    }

    protected static function sanitizeAttributes(DOMElement $node, string $tagName): void
    {
        foreach (iterator_to_array($node->attributes ?? []) as $attribute) {
            if (! $attribute) {
                continue;
            }

            $attributeName = Str::lower($attribute->nodeName);
            $attributeValue = trim((string) $attribute->nodeValue);

            if (str_starts_with($attributeName, 'on') || $attributeName === 'style') {
                $node->removeAttribute($attribute->nodeName);
                continue;
            }

            if ($attributeName === 'class') {
                $className = static::sanitizeClassList($attributeValue, $tagName);

                if ($className === '') {
                    $node->removeAttribute($attribute->nodeName);
                } else {
                    $node->setAttribute('class', $className);
                }
                continue;
            }

            if ($tagName === 'a' && in_array($attributeName, ['href', 'target', 'rel'], true)) {
                continue;
            }

            if ($tagName === 'img' && in_array($attributeName, ['src', 'srcset', 'alt'], true)) {
                continue;
            }

            if ($tagName === 'th' || $tagName === 'td') {
                if (in_array($attributeName, ['colspan', 'rowspan'], true) && preg_match('/^\d{1,3}$/', $attributeValue) === 1) {
                    continue;
                }
            }

            if ($tagName === 'div' && static::isAllowedEmbedAttribute($attributeName, $attributeValue)) {
                continue;
            }

            $node->removeAttribute($attribute->nodeName);
        }

        if ($tagName === 'a') {
            $href = trim((string) $node->getAttribute('href'));
            if ($href === '' || ! static::isSafeUrl($href, false)) {
                $node->removeAttribute('href');
            }

            $target = trim((string) $node->getAttribute('target'));
            if ($target !== '_blank') {
                $node->removeAttribute('target');
                $node->removeAttribute('rel');
            } else {
                $node->setAttribute('rel', 'noopener noreferrer');
            }
        }

        if ($tagName === 'img') {
            $src = trim((string) $node->getAttribute('src'));
            $srcset = trim((string) $node->getAttribute('srcset'));

            if ($src !== '' && ! static::isSafeUrl($src, true)) {
                $node->removeAttribute('src');
                $src = '';
            }

            if ($srcset !== '') {
                $sanitizedSrcset = static::sanitizeSrcset($srcset);

                if ($sanitizedSrcset === '') {
                    $node->removeAttribute('srcset');
                    $srcset = '';
                } else {
                    $node->setAttribute('srcset', $sanitizedSrcset);
                    $srcset = $sanitizedSrcset;
                }
            }

            if ($src === '' && $srcset === '') {
                $node->parentNode?->removeChild($node);
                return;
            }
        }

        if ($tagName === 'div' && ! static::isAllowedEmbedNode($node)) {
            static::unwrapNode($node);
        }
    }

    protected static function sanitizeClassList(string $classList, string $tagName): string
    {
        $classes = preg_split('/\s+/u', trim($classList)) ?: [];
        $allowed = [];

        foreach ($classes as $className) {
            if ($className === '') {
                continue;
            }

            if (static::isAllowedClass($className, $tagName)) {
                $allowed[] = $className;
            }
        }

        return implode(' ', array_values(array_unique($allowed)));
    }

    protected static function isAllowedClass(string $className, string $tagName): bool
    {
        if (str_starts_with($className, 'cms-inline-image')) {
            return in_array($tagName, ['p', 'figure', 'img', 'figcaption'], true);
        }

        if (str_starts_with($className, 'bilibili-video-embed')) {
            return $tagName === 'div';
        }

        return false;
    }

    protected static function isAllowedEmbedAttribute(string $attributeName, string $attributeValue): bool
    {
        return match ($attributeName) {
            'data-bilibili-video' => $attributeValue === '1',
            'data-aid', 'data-cid', 'data-p' => preg_match('/^\d{1,20}$/', $attributeValue) === 1,
            'data-bvid' => preg_match('/^[A-Za-z0-9]{6,32}$/', $attributeValue) === 1,
            'data-width' => preg_match('/^\d+(?:px|vw|rem|em|%)?$/i', $attributeValue) === 1,
            'data-height' => preg_match('/^\d+(?:px|vh|rem|em)?$/i', $attributeValue) === 1,
            'data-align' => in_array(Str::lower($attributeValue), ['left', 'center', 'right'], true),
            default => false,
        };
    }

    protected static function isAllowedEmbedNode(DOMElement $node): bool
    {
        return trim((string) $node->getAttribute('data-bilibili-video')) === '1';
    }

    protected static function isSafeUrl(string $value, bool $allowImageData): bool
    {
        if (preg_match('/^\s*(javascript|vbscript|data:(?!image\/))/i', $value) === 1) {
            return false;
        }

        if ($allowImageData && preg_match('/^\s*data:image\/(?:png|jpe?g|gif|webp)(?:;[^,]*)?,/i', $value) === 1) {
            return true;
        }

        if (str_starts_with($value, '/') || str_starts_with($value, './') || str_starts_with($value, '../') || str_starts_with($value, '#')) {
            return true;
        }

        return preg_match('/^https?:\/\//i', $value) === 1;
    }

    protected static function sanitizeSrcset(string $value): string
    {
        $candidates = preg_split('/\s*,\s*/u', trim($value)) ?: [];
        $sanitized = [];

        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }

            $parts = preg_split('/\s+/', $candidate, 2) ?: [];
            $url = trim((string) ($parts[0] ?? ''));
            $descriptor = trim((string) ($parts[1] ?? ''));

            if ($url === '' || ! static::isSafeUrl($url, true)) {
                continue;
            }

            if ($descriptor !== '' && preg_match('/^\d+(?:\.\d+)?[wx]$/i', $descriptor) !== 1) {
                $descriptor = '';
            }

            $sanitized[] = trim($url.' '.$descriptor);
        }

        return implode(', ', $sanitized);
    }

    protected static function unwrapNode(DOMElement $node): void
    {
        $parent = $node->parentNode;

        if (! $parent) {
            return;
        }

        while ($node->firstChild) {
            $parent->insertBefore($node->firstChild, $node);
        }

        $parent->removeChild($node);
    }
}
