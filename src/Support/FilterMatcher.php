<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Support;

class FilterMatcher
{
    /**
     * @param array<int, string> $components
     * @param array<int, string> $filters
     */
    public static function matches(array $components, array $filters): bool
    {
        foreach ($filters as $filter) {
            if ($filter === '*' || $filter === '.*') {
                return true;
            }

            if (preg_match('/^\/.*\/$/', $filter) === 1) {
                foreach ($components as $component) {
                    if (self::matchesRegex($filter, $component)) {
                        return true;
                    }
                }

                continue;
            }

            foreach ($components as $component) {
                if (fnmatch($filter, $component)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $components
     * @param array<int, string> $include
     * @param array<int, string> $exclude
     */
    public static function passesFilters(array $components, array $include, array $exclude): bool
    {
        if ($include === [] || !self::matches($components, $include)) {
            return false;
        }

        if ($exclude !== [] && self::matches($components, $exclude)) {
            return false;
        }

        return true;
    }

    private static function matchesRegex(string $pattern, string $value): bool
    {
        return @preg_match($pattern, $value) === 1;
    }
}
