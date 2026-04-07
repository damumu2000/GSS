<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\HtmlString;

class ThemeTemplateEngine
{
    /**
     * @var array<string, array<int, string>>
     */
    protected const DIRECTIVE_ARGUMENTS = [
        'siteValue' => ['key', 'default'],
        'linkTo' => ['type', 'id', 'channel_id', 'slug', 'target', 'default'],
        'contentList' => [
            'type', 'status', 'channel', 'channel_id', 'channel_slug', 'is_top', 'is_recommend',
            'with_cover', 'has_image', 'author', 'source', 'include_ids', 'exclude_ids',
            'keyword', 'published_after', 'published_before', 'random', 'order', 'order_by',
            'order_dir', 'offset', 'limit', 'fields',
        ],
        'channels' => ['status', 'parent_id', 'is_nav', 'type', 'slug', 'include_ids', 'exclude_ids', 'keyword', 'random', 'fields', 'limit'],
        'channel' => ['id', 'channel_id', 'slug'],
        'children' => ['id', 'channel_id', 'slug', 'limit'],
        'parent' => ['id', 'channel_id', 'slug'],
        'siblings' => ['id', 'channel_id', 'slug', 'limit'],
        'breadcrumb' => ['id', 'channel_id', 'slug'],
        'content' => ['id'],
        'guestbookMessages' => ['limit', 'offset', 'status', 'order', 'fields'],
        'guestbookStats' => [],
        'valueOr' => ['value', 'default'],
        'truncate' => ['value', 'length', 'ellipsis'],
        'plainText' => ['value'],
        'formatDate' => ['value', 'format', 'default'],
        'timeAgo' => ['value', 'default'],
        'textToHtml' => ['value'],
        'nav' => ['limit'],
        'stats' => [],
        'previous' => [],
        'next' => [],
        'related' => ['limit'],
        'first' => [],
        'promo' => ['code', 'page_scope', 'display_mode', 'channel_id', 'channel_slug', 'template_name', 'limit', 'fields', 'random'],
        'promos' => ['code', 'page_scope', 'display_mode', 'channel_id', 'channel_slug', 'template_name', 'limit', 'fields', 'random'],
    ];

    /**
     * @var array<int, string>
     */
    protected array $templateStack = [];

    public function __construct(
        protected string $siteKey,
        protected string $themeCode,
        protected ThemeTags $tags,
    ) {
    }

    public function render(string $template, array $context = []): string
    {
        return $this->renderTemplate($template, $context);
    }

    public function validateSource(string $source): void
    {
        $this->renderString($source, [], true);
    }

    protected function loadTemplate(string $template): string
    {
        $template = $this->normalizeTemplateName($template);
        $path = ThemeTemplateLocator::resolvePath($this->siteKey, $this->themeCode, $template);

        if (! File::exists($path)) {
            throw ThemeTemplateException::syntax('找不到模板文件 '.$template.'.tpl');
        }

        return File::get($path);
    }

    protected function renderTemplate(string $template, array $context, bool $validateOnly = false): string
    {
        $template = $this->normalizeTemplateName($template);

        if (in_array($template, $this->templateStack, true)) {
            $chain = array_merge($this->templateStack, [$template]);

            throw ThemeTemplateException::syntax('模板 include 存在循环引用：'.implode(' -> ', $chain));
        }

        if (count($this->templateStack) >= 20) {
            throw ThemeTemplateException::syntax('模板 include 层级过深，请减少嵌套层数');
        }

        $this->templateStack[] = $template;

        try {
            $source = $this->loadTemplate($template);

            return $this->renderString($source, $context, $validateOnly);
        } finally {
            array_pop($this->templateStack);
        }
    }

    protected function renderString(string $source, array $context, bool $validateOnly = false): string
    {
        $this->assertSafeSource($source);

        $output = '';
        $offset = 0;
        $length = strlen($source);

        while ($offset < $length) {
            $next = $this->nextToken($source, $offset);

            if ($next === null) {
                $output .= substr($source, $offset);
                break;
            }

            [$type, $position] = $next;

            if ($position > $offset) {
                $output .= substr($source, $offset, $position - $offset);
            }

            if ($type === 'raw') {
                $end = strpos($source, '}}}', $position);
                if ($end === false) {
                    throw ThemeTemplateException::syntax('缺少 }}}');
                }
                $expression = trim(substr($source, $position + 3, $end - $position - 3));
                if (! $validateOnly) {
                    $output .= $this->stringify($this->resolveDirective($expression, $context), false);
                }
                $offset = $end + 3;
                continue;
            }

            if ($type === 'echo') {
                $end = strpos($source, '}}', $position);
                if ($end === false) {
                    throw ThemeTemplateException::syntax('缺少 }}');
                }
                $expression = trim(substr($source, $position + 2, $end - $position - 2));
                if (! $validateOnly) {
                    $output .= $this->stringify($this->resolveDirective($expression, $context), true);
                }
                $offset = $end + 2;
                continue;
            }

            $end = strpos($source, '%}', $position);
            if ($end === false) {
                throw ThemeTemplateException::syntax('缺少 %}');
            }
            $statement = trim(substr($source, $position + 2, $end - $position - 2));

            if (str_starts_with($statement, 'include ')) {
                $templateName = $this->normalizeTemplateName(trim(substr($statement, 8), " \t\n\r\0\x0B\"'"));
                $rendered = $this->renderTemplate($templateName, $context, $validateOnly);
                if (! $validateOnly) {
                    $output .= $rendered;
                }
                $offset = $end + 2;
                continue;
            }

            if (str_starts_with($statement, 'set ')) {
                if ($validateOnly) {
                    $this->validateSetStatement(substr($statement, 4), $context);
                } else {
                    $this->applySetStatement(substr($statement, 4), $context);
                }
                $offset = $end + 2;
                continue;
            }

            if (str_starts_with($statement, 'if ')) {
                [$trueBranch, $falseBranch, $blockEnd] = $this->extractIfBranches($source, $end + 2);
                $condition = trim(substr($statement, 3));
                if ($validateOnly) {
                    $this->renderString($trueBranch, $context, true);
                    $this->renderString($falseBranch, $context, true);
                } else {
                    $output .= $this->renderString(
                        $this->evaluateCondition($condition, $context) ? $trueBranch : $falseBranch,
                        $context
                    );
                }
                $offset = $blockEnd;
                continue;
            }

            if (str_starts_with($statement, 'for ')) {
                [$itemName, $iterableExpression] = $this->parseForStatement(substr($statement, 4));
                [$body, $blockEnd] = $this->extractBlock($source, $end + 2, 'for', 'endfor');
                if ($validateOnly) {
                    $this->renderString($body, array_merge($context, [
                        $itemName => [],
                        'loop' => ['index' => 0, 'iteration' => 1, 'first' => true, 'last' => true],
                    ]), true);
                } else {
                    $iterable = $this->normalizeIterable($this->resolveValue($iterableExpression, $context));

                    foreach ($iterable as $index => $item) {
                        $childContext = $context;
                        $childContext[$itemName] = $item;
                        $childContext['loop'] = [
                            'index' => $index,
                            'iteration' => $index + 1,
                            'first' => $index === 0,
                            'last' => $index === array_key_last($iterable),
                        ];
                        $output .= $this->renderString($body, $childContext);
                    }
                }

                $offset = $blockEnd;
                continue;
            }

            if (in_array($statement, ['else', 'endif', 'endfor'], true)) {
                throw ThemeTemplateException::syntax('块标签未正确闭合');
            }

            throw ThemeTemplateException::unsupported($statement);
        }

        return $output;
    }

    protected function nextToken(string $source, int $offset): ?array
    {
        $positions = [];

        foreach (['{{{' => 'raw', '{{' => 'echo', '{%' => 'tag'] as $needle => $type) {
            $position = strpos($source, $needle, $offset);
            if ($position !== false) {
                $positions[] = [$type, $position];
            }
        }

        if ($positions === []) {
            return null;
        }

        usort($positions, fn (array $a, array $b): int => $a[1] <=> $b[1]);

        return $positions[0];
    }

    protected function normalizeTemplateName(string $template): string
    {
        $template = trim($template);

        if (! preg_match('/^[A-Za-z0-9_-]+$/', $template)) {
            throw ThemeTemplateException::syntax('模板标识不合法');
        }

        return $template;
    }

    protected function applySetStatement(string $statement, array &$context): void
    {
        if (! preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.+)$/', trim($statement), $matches)) {
            throw ThemeTemplateException::syntax('set 语句格式无效');
        }

        $context[$matches[1]] = $this->resolveDirective(trim($matches[2]), $context);
    }

    protected function validateSetStatement(string $statement, array $context): void
    {
        if (! preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.+)$/', trim($statement), $matches)) {
            throw ThemeTemplateException::syntax('set 语句格式无效');
        }

        $expression = trim($matches[2]);

        if (! preg_match('/^([A-Za-z_][A-Za-z0-9_]*)(?:\s+(.+))?$/', $expression, $directiveMatches)) {
            return;
        }

        $command = $directiveMatches[1];
        $tail = trim($directiveMatches[2] ?? '');

        if (! array_key_exists($command, self::DIRECTIVE_ARGUMENTS)) {
            throw ThemeTemplateException::syntax('不支持的动态标签 '.$command);
        }

        $this->assertAllowedArguments(
            $command,
            $this->extractNamedArgumentsForValidation($command, $tail, $context)
        );
    }

    protected function resolveDirective(string $expression, array $context): mixed
    {
        if (! preg_match('/^([A-Za-z_][A-Za-z0-9_]*)(?:\s+(.+))?$/', $expression, $matches)) {
            return $this->resolveValue($expression, $context);
        }

        $command = $matches[1];
        $tail = trim($matches[2] ?? '');

        $this->assertAllowedArguments(
            $command,
            $this->extractNamedArgumentsForValidation($command, $tail, $context)
        );

        return match ($command) {
            'siteValue' => $this->tags->siteValue($this->parseNamedArguments($tail, $context)),
            'linkTo' => $this->tags->linkTo($this->parseNamedArguments($tail, $context)),
            'contentList' => $this->tags->contentList($this->parseNamedArguments($tail, $context)),
            'channels' => $this->tags->channels($this->parseNamedArguments($tail, $context)),
            'channel' => $this->tags->channel($this->parseNamedArguments($tail, $context)),
            'children' => $this->tags->children($this->parseNamedArguments($tail, $context)),
            'parent' => $this->tags->parent($this->parseNamedArguments($tail, $context)),
            'siblings' => $this->tags->siblings($this->parseNamedArguments($tail, $context)),
            'breadcrumb' => $this->tags->breadcrumb($this->parseNamedArguments($tail, $context)),
            'content' => $this->tags->content($this->parseNamedArguments($tail, $context)),
            'guestbookMessages' => $this->tags->guestbookMessages($this->parseNamedArguments($tail, $context)),
            'guestbookStats' => $this->tags->guestbookStats(),
            'valueOr' => $this->tags->valueOr($this->parseNamedArguments($tail, $context)),
            'truncate' => $this->tags->truncate($this->parseNamedArguments($tail, $context)),
            'plainText' => $this->tags->plainText($this->parseNamedArguments($tail, $context)),
            'formatDate' => $this->tags->formatDate($this->parseNamedArguments($tail, $context)),
            'timeAgo' => $this->tags->timeAgo($this->parseNamedArguments($tail, $context)),
            'textToHtml' => $this->tags->textToHtml($this->parseNamedArguments($tail, $context)),
            'nav' => $this->tags->nav((int) ($this->parseNamedArguments($tail, $context)['limit'] ?? 8)),
            'stats' => $this->tags->stats(),
            'previous' => $this->tags->previous($this->resolveValue($tail, $context)),
            'next' => $this->tags->next($this->resolveValue($tail, $context)),
            'related' => $this->resolveRelatedDirective($tail, $context),
            'first' => $this->resolveFirstDirective($tail, $context),
            'promo' => $this->resolvePromoDirective($tail, $context),
            'promos' => $this->resolvePromosDirective($tail, $context),
            default => $this->resolveValue($expression, $context),
        };
    }

    protected function resolveRelatedDirective(string $tail, array $context): Collection
    {
        $parts = preg_split('/\s+/', trim($tail), 2);
        $subject = $this->resolveValue($parts[0] ?? '', $context);
        $options = $this->parseNamedArguments($parts[1] ?? '', $context);

        return $this->tags->related($subject, (int) ($options['limit'] ?? 4));
    }

    protected function resolveFirstDirective(string $tail, array $context): mixed
    {
        $value = $this->resolveValue(trim($tail), $context);

        if ($value instanceof Collection) {
            return $value->first();
        }

        if (is_array($value)) {
            return reset($value) ?: null;
        }

        return null;
    }

    protected function resolvePromoDirective(string $tail, array $context): ?array
    {
        $options = $this->resolvePromoDirectiveOptions($tail, $context);
        $code = trim((string) ($options['code'] ?? ''));

        if ($code === '') {
            return null;
        }

        unset($options['code']);

        return $this->tags->promo($code, $options);
    }

    protected function resolvePromosDirective(string $tail, array $context): Collection
    {
        return $this->tags->promos($this->resolvePromoDirectiveOptions($tail, $context));
    }

    protected function resolvePromoDirectiveOptions(string $tail, array $context): array
    {
        $tail = trim($tail);

        if ($tail === '') {
            return [];
        }

        if (str_contains($tail, '=')) {
            return $this->parseNamedArguments($tail, $context);
        }

        return [
            'code' => $this->resolveValue($tail, $context),
        ];
    }

    protected function parseNamedArguments(string $source, array $context): array
    {
        $source = trim($source);

        if ($source === '') {
            return [];
        }

        $arguments = [];
        $offset = 0;
        $length = strlen($source);

        while ($offset < $length) {
            if (preg_match('/\G\s+/A', $source, $spacing, 0, $offset)) {
                $offset += strlen($spacing[0]);
                continue;
            }

            if (! preg_match('/\G([A-Za-z_][A-Za-z0-9_]*)=("(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'|[^\s]+)/A', $source, $match, 0, $offset)) {
                throw ThemeTemplateException::syntax('参数格式无效：'.$source);
            }

            if (array_key_exists($match[1], $arguments)) {
                throw ThemeTemplateException::syntax('参数重复：'.$match[1]);
            }

            $arguments[$match[1]] = $this->parseLiteral($match[2], $context);
            $offset += strlen($match[0]);
        }

        return $arguments;
    }

    protected function assertSafeSource(string $source): void
    {
        if (str_contains($source, '<?')) {
            throw ThemeTemplateException::syntax('模板源码中不允许包含 PHP 代码标签');
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractNamedArgumentsForValidation(string $command, string $tail, array $context): array
    {
        $tail = trim($tail);

        if ($tail === '' || ! str_contains($tail, '=')) {
            return [];
        }

        if ($command === 'related') {
            $parts = preg_split('/\s+/', $tail, 2);

            return $this->parseNamedArguments($parts[1] ?? '', $context);
        }

        if ($command === 'promo') {
            if (str_starts_with($tail, 'code=')) {
                return $this->parseNamedArguments($tail, $context);
            }

            $parts = preg_split('/\s+/', $tail, 2);

            return $this->parseNamedArguments($parts[1] ?? '', $context);
        }

        return $this->parseNamedArguments($tail, $context);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function assertAllowedArguments(string $command, array $arguments): void
    {
        if ($arguments === []) {
            return;
        }

        $allowed = self::DIRECTIVE_ARGUMENTS[$command] ?? [];
        $unknown = array_values(array_diff(array_keys($arguments), $allowed));

        if ($unknown !== []) {
            throw ThemeTemplateException::syntax($command.' 不支持以下参数：'.implode('、', $unknown));
        }
    }

    protected function parseLiteral(string $value, array $context): mixed
    {
        $value = trim($value);

        if ($value === 'null') {
            return null;
        }

        if ($value === 'true') {
            return true;
        }

        if ($value === 'false') {
            return false;
        }

        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, '\'') && str_ends_with($value, '\''))) {
            return stripcslashes(substr($value, 1, -1));
        }

        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return $this->resolveValue($value, $context);
    }

    protected function extractIfBranches(string $source, int $offset): array
    {
        $depth = 1;
        $cursor = $offset;
        $elseRange = null;

        while (preg_match('/{%\s*(.*?)\s*%}/s', $source, $match, PREG_OFFSET_CAPTURE, $cursor)) {
            $statement = trim($match[1][0]);
            $tagStart = $match[0][1];
            $tagEnd = $tagStart + strlen($match[0][0]);
            $keyword = strtok($statement, ' ') ?: $statement;

            if ($keyword === 'if') {
                $depth++;
            } elseif ($keyword === 'endif') {
                $depth--;

                if ($depth === 0) {
                    $trueStart = $offset;
                    $trueEnd = $elseRange['start'] ?? $tagStart;
                    $falseStart = $elseRange['end'] ?? $tagStart;
                    $falseEnd = $tagStart;

                    return [
                        substr($source, $trueStart, $trueEnd - $trueStart),
                        $elseRange ? substr($source, $falseStart, $falseEnd - $falseStart) : '',
                        $tagEnd,
                    ];
                }
            } elseif ($keyword === 'else' && $depth === 1) {
                $elseRange = ['start' => $tagStart, 'end' => $tagEnd];
            }

            $cursor = $tagEnd;
        }

        throw ThemeTemplateException::syntax('if 标签未闭合');
    }

    protected function extractBlock(string $source, int $offset, string $startTag, string $endTag): array
    {
        $depth = 1;
        $cursor = $offset;

        while (preg_match('/{%\s*(.*?)\s*%}/s', $source, $match, PREG_OFFSET_CAPTURE, $cursor)) {
            $statement = trim($match[1][0]);
            $tagStart = $match[0][1];
            $tagEnd = $tagStart + strlen($match[0][0]);
            $keyword = strtok($statement, ' ') ?: $statement;

            if ($keyword === $startTag) {
                $depth++;
            } elseif ($keyword === $endTag) {
                $depth--;

                if ($depth === 0) {
                    return [
                        substr($source, $offset, $tagStart - $offset),
                        $tagEnd,
                    ];
                }
            }

            $cursor = $tagEnd;
        }

        throw ThemeTemplateException::syntax($startTag.' 标签未闭合');
    }

    protected function parseForStatement(string $statement): array
    {
        if (! preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s+in\s+([A-Za-z0-9_\.\-]+)$/', trim($statement), $matches)) {
            throw ThemeTemplateException::syntax('for 语句格式无效');
        }

        return [$matches[1], $matches[2]];
    }

    protected function evaluateCondition(string $condition, array $context): bool
    {
        $condition = trim($condition);

        if (preg_match('/^(.+?)\s*(==|!=)\s*(.+)$/', $condition, $matches)) {
            $left = $this->parseLiteral(trim($matches[1]), $context);
            $right = $this->parseLiteral(trim($matches[3]), $context);

            return $matches[2] === '==' ? $left == $right : $left != $right;
        }

        if (str_starts_with($condition, 'not ')) {
            return ! $this->isTruthy($this->resolveValue(substr($condition, 4), $context));
        }

        return $this->isTruthy($this->resolveValue($condition, $context));
    }

    protected function resolveValue(string $expression, array $context): mixed
    {
        $expression = trim($expression);

        if ($expression === '') {
            return null;
        }

        if ($expression === 'null') {
            return null;
        }

        if ($expression === 'true') {
            return true;
        }

        if ($expression === 'false') {
            return false;
        }

        if ((str_starts_with($expression, '"') && str_ends_with($expression, '"')) || (str_starts_with($expression, '\'') && str_ends_with($expression, '\''))) {
            return substr($expression, 1, -1);
        }

        if (is_numeric($expression)) {
            return str_contains($expression, '.') ? (float) $expression : (int) $expression;
        }

        $segments = explode('.', $expression);
        $value = $context[$segments[0]] ?? null;

        foreach (array_slice($segments, 1) as $segment) {
            if ($value instanceof Collection) {
                $value = $value->get($segment);
                continue;
            }

            if (is_array($value)) {
                $value = $value[$segment] ?? null;
                continue;
            }

            if (is_object($value)) {
                $value = $value->{$segment} ?? null;
                continue;
            }

            return null;
        }

        return $value;
    }

    protected function normalizeIterable(mixed $value): array
    {
        if ($value instanceof Collection) {
            return $value->all();
        }

        if (is_array($value)) {
            return array_values($value);
        }

        if (is_iterable($value)) {
            return iterator_to_array($value, false);
        }

        return [];
    }

    protected function isTruthy(mixed $value): bool
    {
        if ($value instanceof Collection) {
            return $value->isNotEmpty();
        }

        if (is_array($value)) {
            return $value !== [];
        }

        return (bool) $value;
    }

    protected function stringify(mixed $value, bool $escape): string
    {
        if ($value instanceof HtmlString) {
            return $value->toHtml();
        }

        if (is_array($value) || $value instanceof Collection) {
            return '';
        }

        $string = (string) ($value ?? '');

        return $escape ? e($string) : $string;
    }
}
