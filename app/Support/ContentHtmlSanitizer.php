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
        'pre',
        'code',
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
            if (in_array($tagName, ['object', 'embed'], true)) {
                $swfUrl = static::extractLegacySwfUrl($node);

                if ($swfUrl !== '') {
                    static::replaceWithLegacySwfCard($node, $swfUrl);
                    return;
                }
            }

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

            if (str_starts_with($attributeName, 'on')) {
                $node->removeAttribute($attribute->nodeName);
                continue;
            }

            if ($attributeName === 'style') {
                $styleValue = static::sanitizeStyleDeclaration($attributeValue, $tagName);

                if ($styleValue === '') {
                    $node->removeAttribute($attribute->nodeName);
                } else {
                    $node->setAttribute('style', $styleValue);
                }

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

            if ($tagName === 'img' && in_array($attributeName, ['width', 'height'], true)) {
                $dimension = static::sanitizeImageDimensionAttribute($attributeValue);

                if ($dimension === '') {
                    $node->removeAttribute($attribute->nodeName);
                } else {
                    $node->setAttribute($attributeName, $dimension);
                }

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

    protected static function sanitizeStyleDeclaration(string $style, string $tagName): string
    {
        if (! in_array($tagName, [
            'p',
            'span',
            'div',
            'h1',
            'h2',
            'h3',
            'h4',
            'h5',
            'h6',
            'blockquote',
            'pre',
            'code',
            'ul',
            'ol',
            'li',
            'table',
            'thead',
            'tbody',
            'tr',
            'th',
            'td',
        ], true)) {
            return '';
        }

        $allowed = [];
        $declarations = preg_split('/\s*;\s*/u', trim($style)) ?: [];

        foreach ($declarations as $declaration) {
            if ($declaration === '' || ! str_contains($declaration, ':')) {
                continue;
            }

            [$property, $value] = array_map('trim', explode(':', $declaration, 2));
            $property = Str::lower($property);

            if ($property === '' || $value === '') {
                continue;
            }

            $sanitizedValue = match ($property) {
                'text-align' => static::sanitizeTextAlignStyle($value),
                'color', 'background-color' => static::sanitizeColorStyle($value),
                'font-size' => static::sanitizeFontSizeStyle($value),
                'font-family' => static::sanitizeFontFamilyStyle($value),
                'line-height' => static::sanitizeLineHeightStyle($value),
                'text-decoration' => static::sanitizeTextDecorationStyle($value),
                default => '',
            };

            if ($sanitizedValue !== '') {
                $allowed[] = $property.': '.$sanitizedValue;
            }
        }

        return implode('; ', array_values(array_unique($allowed)));
    }

    protected static function sanitizeTextAlignStyle(string $value): string
    {
        $value = Str::lower(trim($value));

        return in_array($value, ['left', 'center', 'right', 'justify'], true) ? $value : '';
    }

    protected static function sanitizeColorStyle(string $value): string
    {
        $value = trim($value);

        if (preg_match('/^(?:#[0-9a-fA-F]{3}|#[0-9a-fA-F]{6})$/', $value) === 1) {
            return strtolower($value);
        }

        if (preg_match('/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}(?:\s*,\s*(?:0|0?\.\d+|1(?:\.0+)?))?\s*\)$/i', $value) === 1) {
            return $value;
        }

        if (preg_match('/^[a-zA-Z]{3,20}$/', $value) === 1) {
            return strtolower($value);
        }

        return '';
    }

    protected static function sanitizeFontSizeStyle(string $value): string
    {
        $value = trim($value);

        return preg_match('/^\d{1,3}(?:\.\d{1,2})?(?:px|pt|em|rem|%)$/i', $value) === 1 ? strtolower($value) : '';
    }

    protected static function sanitizeFontFamilyStyle(string $value): string
    {
        $value = trim($value);

        if (str_contains(Str::lower($value), 'expression') || str_contains(Str::lower($value), 'url(')) {
            return '';
        }

        return preg_match('/^[A-Za-z0-9\x{4e00}-\x{9fa5}\s,"\047,-]+$/u', $value) === 1 ? $value : '';
    }

    protected static function sanitizeLineHeightStyle(string $value): string
    {
        $value = trim($value);

        return preg_match('/^\d{1,2}(?:\.\d{1,2})?(?:px|em|rem|%)?$/i', $value) === 1 ? strtolower($value) : '';
    }

    protected static function sanitizeTextDecorationStyle(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', Str::lower(trim($value))) ?? '';

        return in_array($value, ['underline', 'line-through', 'underline line-through'], true) ? $value : '';
    }

    protected static function sanitizeImageDimensionAttribute(string $value): string
    {
        $value = trim($value);

        if (preg_match('/^\d{1,5}$/', $value) !== 1) {
            return '';
        }

        $dimension = (int) $value;

        return $dimension >= 1 && $dimension <= 20000 ? (string) $dimension : '';
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

        if (str_starts_with($className, 'cms-article-')) {
            return in_array($tagName, [
                'p',
                'span',
                'div',
                'h1',
                'h2',
                'h3',
                'h4',
                'ul',
                'ol',
                'li',
                'blockquote',
                'table',
                'th',
                'td',
                'img',
                'figure',
                'figcaption',
            ], true);
        }

        if (str_starts_with($className, 'bilibili-video-embed')) {
            return $tagName === 'div';
        }

        if (str_starts_with($className, 'legacy-swf-file')) {
            return in_array($tagName, ['div', 'p', 'a', 'span'], true);
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

    protected static function extractLegacySwfUrl(DOMElement $node): string
    {
        foreach (['src', 'data', 'movie', 'value'] as $attributeName) {
            $value = trim((string) $node->getAttribute($attributeName));

            if (static::isSafeSwfUrl($value)) {
                return $value;
            }
        }

        foreach ($node->getElementsByTagName('param') as $param) {
            if (! $param instanceof DOMElement) {
                continue;
            }

            $name = Str::lower(trim((string) $param->getAttribute('name')));

            if (! in_array($name, ['movie', 'src'], true)) {
                continue;
            }

            $value = trim((string) $param->getAttribute('value'));

            if (static::isSafeSwfUrl($value)) {
                return $value;
            }
        }

        $html = $node->ownerDocument?->saveHTML($node) ?: '';

        if (preg_match('/(?:src|data|movie|value)\s*=\s*([\'"])(.*?)\1/is', $html, $matches) === 1) {
            $value = html_entity_decode(trim((string) ($matches[2] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if (static::isSafeSwfUrl($value)) {
                return $value;
            }
        }

        return '';
    }

    protected static function replaceWithLegacySwfCard(DOMElement $node, string $swfUrl): void
    {
        $document = $node->ownerDocument;
        $parent = $node->parentNode;

        if (! $document || ! $parent) {
            return;
        }

        $card = $document->createElement('div');
        $card->setAttribute('class', 'legacy-swf-file');

        $icon = $document->createElement('span');
        $icon->setAttribute('class', 'legacy-swf-file__icon');
        $icon->appendChild($document->createTextNode('SWF'));

        $body = $document->createElement('div');
        $body->setAttribute('class', 'legacy-swf-file__body');

        $title = $document->createElement('p');
        $title->setAttribute('class', 'legacy-swf-file__title');
        $title->appendChild($document->createTextNode('该内容包含旧版 Flash 文件'));

        $description = $document->createElement('p');
        $description->setAttribute('class', 'legacy-swf-file__desc');
        $description->appendChild($document->createTextNode('当前浏览器已不支持直接播放，可下载原文件留存查看。'));

        $link = $document->createElement('a');
        $link->setAttribute('class', 'legacy-swf-file__link');
        $link->setAttribute('href', $swfUrl);
        $link->setAttribute('target', '_blank');
        $link->setAttribute('rel', 'noopener noreferrer');
        $link->appendChild($document->createTextNode('下载查看原文件'));

        $body->appendChild($title);
        $body->appendChild($description);
        $body->appendChild($link);
        $card->appendChild($icon);
        $card->appendChild($body);

        $parent->replaceChild($card, $node);
    }

    protected static function isSafeSwfUrl(string $value): bool
    {
        $value = trim($value);

        if ($value === '' || ! static::isSafeUrl($value, false)) {
            return false;
        }

        return preg_match('/\.swf(?:[?#].*)?$/i', $value) === 1;
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
