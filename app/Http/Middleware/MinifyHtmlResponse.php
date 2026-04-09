<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MinifyHtmlResponse
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldMinify($response)) {
            return $response;
        }

        $content = $response->getContent();

        if (! is_string($content) || $content === '') {
            return $response;
        }

        $placeholders = [];
        $content = preg_replace_callback(
            '/<(script|style|pre|textarea)\b[^>]*>.*?<\/\1>/is',
            function (array $matches) use (&$placeholders): string {
                $tag = strtolower((string) $matches[1]);
                $block = $matches[0];

                if ($tag === 'style') {
                    $block = $this->minifyStyleBlock($block);
                } elseif ($tag === 'script') {
                    $block = $this->compactScriptBlock($block);
                }

                $key = '___HTML_MINIFY_BLOCK_'.count($placeholders).'___';
                $placeholders[$key] = $block;

                return $key;
            },
            $content
        ) ?? $content;

        $content = preg_replace('/<!--(?!\[if).*?-->/s', '', $content) ?? $content;
        $content = preg_replace('/>\s+</', '><', $content) ?? $content;
        $content = preg_replace('/[ \t]{2,}/', ' ', $content) ?? $content;
        $content = preg_replace("/\n{2,}/", "\n", $content) ?? $content;

        if ($placeholders !== []) {
            $content = strtr($content, $placeholders);
        }

        $response->setContent(trim($content));

        return $response;
    }

    protected function shouldMinify(Response $response): bool
    {
        if (! $response->isSuccessful()) {
            return false;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');

        return str_contains(strtolower($contentType), 'text/html');
    }

    protected function minifyStyleBlock(string $block): string
    {
        return preg_replace_callback('/<style\b([^>]*)>(.*?)<\/style>/is', function (array $matches): string {
            $attrs = $matches[1] ?? '';
            $css = $matches[2] ?? '';

            $css = preg_replace('!/\*.*?\*/!s', '', $css) ?? $css;
            $css = preg_replace('/\s+/', ' ', $css) ?? $css;
            $css = preg_replace('/\s*([{}:;,>])\s*/', '$1', $css) ?? $css;
            $css = preg_replace('/;}/', '}', $css) ?? $css;
            $css = trim($css);

            return "<style{$attrs}>{$css}</style>";
        }, $block) ?? $block;
    }

    protected function compactScriptBlock(string $block): string
    {
        return preg_replace_callback('/<script\b([^>]*)>(.*?)<\/script>/is', function (array $matches): string {
            $attrs = $matches[1] ?? '';
            $js = $matches[2] ?? '';

            $lines = preg_split("/\r\n|\n|\r/", $js) ?: [];
            $lines = array_map(static fn (string $line): string => rtrim($line), $lines);
            $lines = array_values(array_filter($lines, static fn (string $line): bool => trim($line) !== ''));
            $js = implode("\n", $lines);

            return "<script{$attrs}>{$js}</script>";
        }, $block) ?? $block;
    }
}
