<?php

namespace App\Support\ThemeDsl;

class ThemeDslSpec
{
    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        /** @var array<string, mixed> $spec */
        $spec = config('theme_dsl', []);

        return $spec;
    }

    /**
     * @return array<int, string>
     */
    public static function functions(): array
    {
        $items = config('theme_dsl.functions', []);

        return is_array($items) ? array_values(array_unique(array_map('strval', $items))) : [];
    }

    /**
     * @return array<int, string>
     */
    public static function filters(): array
    {
        $items = config('theme_dsl.filters', []);

        return is_array($items) ? array_values(array_unique(array_map('strval', $items))) : [];
    }

    /**
     * @return array<int, string>
     */
    public static function queryMethods(): array
    {
        $items = config('theme_dsl.query_methods', []);

        return is_array($items) ? array_values(array_unique(array_map('strval', $items))) : [];
    }

    public static function isAllowedFunction(string $name): bool
    {
        return in_array($name, self::functions(), true);
    }

    public static function isAllowedFilter(string $name): bool
    {
        return in_array($name, self::filters(), true);
    }

    public static function isAllowedQueryMethod(string $name): bool
    {
        return in_array($name, self::queryMethods(), true);
    }

    /**
     * @return array<string, string>
     */
    public static function aliases(): array
    {
        $items = config('theme_dsl.aliases', []);

        if (! is_array($items)) {
            return [];
        }

        $result = [];
        foreach ($items as $alias => $canonical) {
            $aliasName = trim((string) $alias);
            $canonicalName = trim((string) $canonical);
            if ($aliasName === '' || $canonicalName === '') {
                continue;
            }
            $result[$aliasName] = $canonicalName;
        }

        return $result;
    }

    public static function canonicalName(string $name): string
    {
        $aliases = self::aliases();

        return $aliases[$name] ?? $name;
    }

    public static function maxLimit(): int
    {
        return max(1, (int) config('theme_dsl.limits.max_limit', 100));
    }

    public static function maxPerPage(): int
    {
        return max(1, (int) config('theme_dsl.limits.max_per_page', 50));
    }

    public static function maxWindow(): int
    {
        return max(1, (int) config('theme_dsl.limits.max_window', 5));
    }

    public static function maxKeywordLength(): int
    {
        return max(1, (int) config('theme_dsl.limits.max_keyword_length', 50));
    }

    public static function isAllowedConfigPath(string $path): bool
    {
        $path = trim($path);
        if ($path === '') {
            return false;
        }

        $prefixes = config('theme_dsl.config_prefix_whitelist', []);
        if (! is_array($prefixes) || $prefixes === []) {
            return false;
        }

        foreach ($prefixes as $prefix) {
            $prefix = trim((string) $prefix);
            if ($prefix !== '' && str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
