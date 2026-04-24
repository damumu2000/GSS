<?php

namespace App\Support;

use App\Support\ThemeDsl\ThemeDslSpec;
use App\Support\ThemeDsl\ThemeQueryChain;
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
        'config' => ['key', 'default'],
        'linkTo' => ['type', 'id', 'channel_id', 'slug', 'target', 'default'],
        'contentList' => [
            'type', 'status', 'channel', 'channel_id', 'channel_slug', 'is_top', 'is_recommend',
            'with_cover', 'has_image', 'author', 'source', 'include_ids', 'exclude_ids',
            'keyword', 'published_after', 'published_before', 'random', 'order', 'order_by',
            'order_dir', 'offset', 'limit', 'fields',
        ],
        'contentPage' => [
            'type', 'status', 'channel', 'channel_id', 'channel_slug', 'is_top', 'is_recommend',
            'with_cover', 'has_image', 'author', 'source', 'include_ids', 'exclude_ids',
            'keyword', 'published_after', 'published_before', 'random', 'order', 'order_by',
            'order_dir', 'fields', 'page', 'per_page', 'page_name', 'window',
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
        'previous' => ['value'],
        'next' => ['value'],
        'related' => ['value', 'limit'],
        'first' => ['value'],
        'promo' => ['code', 'page_scope', 'display_mode', 'channel_id', 'channel_slug', 'template_name', 'limit', 'fields', 'random'],
        'promos' => ['code', 'page_scope', 'display_mode', 'channel_id', 'channel_slug', 'template_name', 'limit', 'fields', 'random'],
        'themeAsset' => ['path'],
        'themeStyle' => ['path'],
        'themeScript' => ['path'],
    ];

    /**
     * @var array<int, string>
     */
    protected array $templateStack = [];

    protected ?string $activeSource = null;

    protected int $activeOffset = 0;

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
        } catch (ThemeTemplateException $exception) {
            if (! $exception->hasLocation()) {
                $exception->withLocation($template);
            }

            throw $exception;
        } finally {
            array_pop($this->templateStack);
        }
    }

    protected function renderString(string $source, array $context, bool $validateOnly = false): string
    {
        $previousSource = $this->activeSource;
        $previousOffset = $this->activeOffset;
        $this->activeSource = $source;
        $this->activeOffset = 0;

        try {
            $this->assertSafeSource($source);

            $output = '';
            $offset = 0;
            $length = strlen($source);

            while ($offset < $length) {
                $this->activeOffset = $offset;
                $next = $this->nextToken($source, $offset);

                if ($next === null) {
                    $output .= substr($source, $offset);
                    break;
                }

                [$type, $position] = $next;
                $this->activeOffset = $position;

                if ($position > $offset) {
                    $output .= substr($source, $offset, $position - $offset);
                }

                if ($type === 'raw') {
                    $end = strpos($source, '}}}', $position);
                    if ($end === false) {
                        throw ThemeTemplateException::syntax('缺少 }}}');
                    }
                    $expression = trim(substr($source, $position + 3, $end - $position - 3));
                    if ($validateOnly) {
                        $this->validateExpression($expression, $context);
                    } else {
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
                    if ($validateOnly) {
                        $this->validateExpression($expression, $context);
                    } else {
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
        } catch (ThemeTemplateException $exception) {
            if (! $exception->hasLocation()) {
                $exception->withLocation($this->currentTemplateName(), $this->lineFromOffset($source, $this->activeOffset));
            }

            throw $exception;
        } finally {
            $this->activeSource = $previousSource;
            $this->activeOffset = $previousOffset;
        }
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

        $this->validateExpression(trim($matches[2]), $context);
    }

    protected function validateExpression(string $expression, array $context): void
    {
        $this->resolveDirective($expression, $context, true);
    }

    protected function resolveDirective(string $expression, array $context, bool $validateOnly = false): mixed
    {
        $expression = trim($expression);

        if ($expression === '') {
            return null;
        }

        if ($this->containsTopLevelPipe($expression)) {
            return $this->resolvePipelineExpression($expression, $context, $validateOnly);
        }

        if ($this->looksLikeFunctionCall($expression)) {
            return $this->resolveFunctionCallExpression($expression, $context, $validateOnly);
        }

        if ($this->looksLikeLegacyDirectiveSyntax($expression)) {
            throw ThemeTemplateException::syntax('表达式语法错误');
        }

        return $this->resolveValue($expression, $context);
    }

    protected function resolveThemeAssetDirective(string $path, bool $validateOnly = false): string
    {
        $path = trim($path);

        if ($path === '') {
            if ($validateOnly) {
                throw ThemeTemplateException::syntax('主题资源 path 不能为空');
            }

            return '';
        }

        $normalizedPath = ThemeTemplateLocator::normalizeAssetPath($path);

        if ($normalizedPath === null) {
            if ($validateOnly) {
                throw ThemeTemplateException::syntax('主题资源路径不合法：'.$path);
            }

            return '';
        }

        $version = ThemeTemplateLocator::assetVersion($this->siteKey, $this->themeCode, $normalizedPath);

        if ($validateOnly && $version === null) {
            throw ThemeTemplateException::syntax('当前主题资源不存在：'.$normalizedPath);
        }

        return route('site.theme-asset', [
            'theme' => $this->themeCode,
            'path' => $normalizedPath,
            'site' => $this->siteKey,
            'v' => $version ?: null,
        ]);
    }

    protected function resolveThemeStyleDirective(string $path, bool $validateOnly = false): HtmlString
    {
        $url = $this->resolveThemeAssetDirective($path, $validateOnly);

        return new HtmlString($url === '' ? '' : '<link rel="stylesheet" href="'.e($url).'">');
    }

    protected function resolveThemeScriptDirective(string $path, bool $validateOnly = false): HtmlString
    {
        $url = $this->resolveThemeAssetDirective($path, $validateOnly);

        return new HtmlString($url === '' ? '' : '<script src="'.e($url).'"></script>');
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function resolveConfigDirective(array $options): mixed
    {
        $key = trim((string) ($options['key'] ?? ''));
        $default = $options['default'] ?? '';

        return $this->tags->configValue($key, $default);
    }

    protected function looksLikeFunctionCall(string $expression): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*\s*\(.*\)$/s', trim($expression)) === 1;
    }

    protected function looksLikeLegacyDirectiveSyntax(string $expression): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*\s+.+$/s', $expression) === 1;
    }

    protected function containsTopLevelPipe(string $expression): bool
    {
        return count($this->splitTopLevel($expression, '|')) > 1;
    }

    /**
     * @return array<int, string>
     */
    protected function splitTopLevel(string $source, string $delimiter): array
    {
        $result = [];
        $buffer = '';
        $depth = 0;
        $quote = null;
        $length = strlen($source);

        for ($i = 0; $i < $length; $i++) {
            $char = $source[$i];

            if ($quote !== null) {
                $buffer .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $buffer .= $source[$i + 1];
                    $i++;
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === '\'') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }

            if ($char === '(') {
                $depth++;
                $buffer .= $char;
                continue;
            }

            if ($char === ')') {
                $depth = max(0, $depth - 1);
                $buffer .= $char;
                continue;
            }

            if ($char === $delimiter && $depth === 0) {
                $result[] = trim($buffer);
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $result[] = trim($buffer);
        }

        return $result;
    }

    protected function resolvePipelineExpression(string $expression, array $context, bool $validateOnly = false): mixed
    {
        $segments = $this->splitTopLevel($expression, '|');
        $value = $this->resolveDirective(array_shift($segments) ?? '', $context, $validateOnly);

        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }

            [$name, $positional, $named] = $this->parsePipelineSegment($segment, $context, $validateOnly);

            if ($value instanceof ThemeQueryChain) {
                if (! ThemeDslSpec::isAllowedQueryMethod($name)) {
                    throw ThemeTemplateException::syntax('query 不支持方法 '.$name);
                }
                $value = $value->apply($name, $positional, $named);
                continue;
            }

            $value = $this->applyPipeFilter($name, $value, $positional, $named, $validateOnly);
        }

        return $value;
    }

    /**
     * @return array{0:string,1:array<int,mixed>,2:array<string,mixed>}
     */
    protected function parsePipelineSegment(string $segment, array $context, bool $validateOnly = false): array
    {
        $segment = trim($segment);

        if ($this->looksLikeFunctionCall($segment)) {
            [$name, $positional, $named] = $this->parseFunctionCall($segment, $context, $validateOnly);

            return [$name, $positional, $named];
        }

        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $segment)) {
            throw ThemeTemplateException::syntax('管道片段格式无效：'.$segment);
        }

        return [$segment, [], []];
    }

    protected function resolveFunctionCallExpression(string $expression, array $context, bool $validateOnly = false): mixed
    {
        [$name, $positional, $named] = $this->parseFunctionCall($expression, $context, $validateOnly);

        if (! ThemeDslSpec::isAllowedFunction($name)) {
            throw ThemeTemplateException::syntax('不支持的函数调用 '.$name);
        }

        if ($name === 'query') {
            $type = strtolower(trim((string) ($positional[0] ?? $named['type'] ?? 'article')));
            if (! in_array($type, ['article', 'page', 'channel', 'channels', 'promo', 'promos'], true)) {
                throw ThemeTemplateException::syntax('query 仅支持 article/page/channel/channels/promo/promos');
            }

            return new ThemeQueryChain($this->tags, $type);
        }

        if ($name === 'config') {
            $key = trim((string) ($positional[0] ?? $named['key'] ?? ''));
            $default = $positional[1] ?? ($named['default'] ?? '');

            return $this->tags->configValue($key, $default);
        }

        if ($name === 'default') {
            $name = 'valueOr';
        }

        if (! array_key_exists($name, self::DIRECTIVE_ARGUMENTS)) {
            throw ThemeTemplateException::syntax('不支持的函数调用 '.$name);
        }

        $options = $this->mergeCallArguments($name, $positional, $named);

        return $this->invokeFunctionLikeCommand($name, $options, $context, $validateOnly);
    }

    /**
     * @return array{0:string,1:array<int,mixed>,2:array<string,mixed>}
     */
    protected function parseFunctionCall(string $expression, array $context, bool $validateOnly = false): array
    {
        if (! preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*\((.*)\)$/s', trim($expression), $matches)) {
            throw ThemeTemplateException::syntax('函数调用格式无效：'.$expression);
        }

        $name = trim($matches[1]);
        $args = trim($matches[2]);

        if ($args === '') {
            return [$name, [], []];
        }

        $parts = $this->splitTopLevel($args, ',');
        $positional = [];
        $named = [];

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.+)$/s', $part, $namedMatch)) {
                $key = $namedMatch[1];
                if (array_key_exists($key, $named)) {
                    throw ThemeTemplateException::syntax('参数重复：'.$key);
                }
                $named[$key] = $this->resolveDirective(trim($namedMatch[2]), $context, $validateOnly);
                continue;
            }

            $positional[] = $this->resolveDirective($part, $context, $validateOnly);
        }

        return [$name, $positional, $named];
    }

    /**
     * @param  array<int, mixed>  $positional
     * @param  array<string, mixed>  $named
     * @return array<string, mixed>
     */
    protected function mergeCallArguments(string $name, array $positional, array $named): array
    {
        $allowed = self::DIRECTIVE_ARGUMENTS[$name] ?? [];
        $options = $named;

        foreach ($positional as $index => $value) {
            if (! isset($allowed[$index])) {
                continue;
            }
            $argName = $allowed[$index];
            if (! array_key_exists($argName, $options)) {
                $options[$argName] = $value;
            }
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function invokeFunctionLikeCommand(string $name, array $options, array $context, bool $validateOnly = false): mixed
    {
        $this->assertAllowedArguments($name, $options);

        return match ($name) {
            'siteValue' => $this->tags->siteValue($options),
            'config' => $this->resolveConfigDirective($options),
            'linkTo' => $this->tags->linkTo($options),
            'contentList' => $this->tags->contentList($options),
            'contentPage' => $this->tags->contentPage($options),
            'channels' => $this->tags->channels($options),
            'channel' => $this->tags->channel($options),
            'children' => $this->tags->children($options),
            'parent' => $this->tags->parent($options),
            'siblings' => $this->tags->siblings($options),
            'breadcrumb' => $this->tags->breadcrumb($options),
            'content' => $this->tags->content($options),
            'guestbookMessages' => $this->tags->guestbookMessages($options),
            'guestbookStats' => $this->tags->guestbookStats(),
            'valueOr' => $this->tags->valueOr($options),
            'truncate' => $this->tags->truncate($options),
            'plainText' => $this->tags->plainText($options),
            'formatDate' => $this->tags->formatDate($options),
            'timeAgo' => $this->tags->timeAgo($options),
            'textToHtml' => $this->tags->textToHtml($options),
            'nav' => $this->tags->nav((int) ($options['limit'] ?? 8)),
            'stats' => $this->tags->stats(),
            'previous' => $this->tags->previous($options['value'] ?? null),
            'next' => $this->tags->next($options['value'] ?? null),
            'related' => $this->tags->related($options['value'] ?? null, (int) ($options['limit'] ?? 4)),
            'first' => $this->resolveFirstValue($options['value'] ?? null),
            'promo' => $this->tags->promo((string) ($options['code'] ?? ''), $options),
            'promos' => $this->tags->promos($options),
            'themeAsset' => $this->resolveThemeAssetDirective((string) ($options['path'] ?? ''), $validateOnly),
            'themeStyle' => $this->resolveThemeStyleDirective((string) ($options['path'] ?? ''), $validateOnly),
            'themeScript' => $this->resolveThemeScriptDirective((string) ($options['path'] ?? ''), $validateOnly),
            default => null,
        };
    }

    protected function resolveFirstValue(mixed $value): mixed
    {
        if ($value instanceof Collection) {
            return $value->first();
        }

        if (is_array($value)) {
            if ($value === []) {
                return null;
            }

            return array_values($value)[0];
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $positional
     * @param  array<string, mixed>  $named
     */
    protected function applyPipeFilter(
        string $filter,
        mixed $value,
        array $positional = [],
        array $named = [],
        bool $validateOnly = false
    ): mixed {
        $filter = trim($filter);
        $canonical = ThemeDslSpec::canonicalName($filter);

        if ($canonical === 'htmlOut') {
            $canonical = 'plainText';
        }

        if ($canonical === 'default') {
            $canonical = 'valueOr';
        }

        if (! ThemeDslSpec::isAllowedFilter($canonical) && ! in_array($canonical, ['valueOr'], true)) {
            throw ThemeTemplateException::syntax('不支持的过滤器 '.$filter);
        }

        $options = $named;
        $options['value'] = $value;

        if ($canonical === 'truncate' && isset($positional[0]) && ! array_key_exists('length', $options)) {
            $options['length'] = (int) $positional[0];
        } elseif ($canonical === 'valueOr' && isset($positional[0]) && ! array_key_exists('default', $options)) {
            $options['default'] = $positional[0];
        } elseif ($canonical === 'formatDate' && isset($positional[0]) && ! array_key_exists('format', $options)) {
            $options['format'] = $positional[0];
            if (isset($positional[1]) && ! array_key_exists('default', $options)) {
                $options['default'] = $positional[1];
            }
        } elseif ($canonical === 'timeAgo' && isset($positional[0]) && ! array_key_exists('default', $options)) {
            $options['default'] = $positional[0];
        }

        return $this->invokeFunctionLikeCommand($canonical, $options, [], $validateOnly);
    }


    protected function assertSafeSource(string $source): void
    {
        $this->assertPatternNotExists($source, '/<\?/', '模板源码中不允许包含 PHP 代码标签');
        $this->assertPatternNotExists($source, '/<script\b(?![^>]*\bsrc\s*=)[^>]*>/i', '模板源码中不允许使用内联 script');
        $this->assertPatternNotExists($source, '/<style\b[^>]*>/i', '模板源码中不允许使用内联 style');
        $this->assertPatternNotExists($source, '/\sstyle\s*=/i', '模板源码中不允许使用内联 style 属性');
        $this->assertPatternNotExists($source, '/\son[a-z]+\s*=/i', '模板源码中不允许使用内联事件属性');
    }

    protected function assertPatternNotExists(string $source, string $pattern, string $message): void
    {
        if (preg_match($pattern, $source, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return;
        }

        $offset = (int) ($match[0][1] ?? 0);
        throw ThemeTemplateException::syntax($message, $this->currentTemplateName(), $this->lineFromOffset($source, $offset));
    }

    protected function currentTemplateName(): ?string
    {
        $name = end($this->templateStack);

        if ($name === false) {
            return null;
        }

        return (string) $name;
    }

    protected function lineFromOffset(string $source, int $offset): int
    {
        $offset = max(0, min($offset, strlen($source)));

        return substr_count(substr($source, 0, $offset), "\n") + 1;
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

        // Even in raw mode, only trusted HtmlString is allowed to render unescaped.
        return e($string);
    }
}
