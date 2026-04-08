<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Tests\Unit\Support;

use Perfbase\CodeIgniter4\Support\FilterMatcher;
use PHPUnit\Framework\TestCase;

final class FilterMatcherTest extends TestCase
{
    public function testMatchesWildcardAndRegexAndGlob(): void
    {
        self::assertTrue(FilterMatcher::matches(['/articles/42'], ['*']));
        self::assertTrue(FilterMatcher::matches(['GET /articles/42'], ['/^GET \\/articles\\/\\d+$/']));
        self::assertTrue(FilterMatcher::matches(['test:ok'], ['test:*']));
        self::assertFalse(FilterMatcher::matches(['GET /admin'], ['POST *']));
    }

    public function testPassesFiltersRespectsIncludeAndExclude(): void
    {
        self::assertTrue(FilterMatcher::passesFilters(['GET /articles/42'], ['GET *'], []));
        self::assertFalse(FilterMatcher::passesFilters(['GET /articles/42'], [], []));
        self::assertFalse(FilterMatcher::passesFilters(['GET /admin'], ['GET *'], ['GET /admin']));
    }

    public function testInvalidRegexFiltersFailClosed(): void
    {
        self::assertFalse(FilterMatcher::matches(['GET /articles/42'], ['/(broken']));
        self::assertFalse(FilterMatcher::passesFilters(['GET /articles/42'], ['/(broken'], []));
    }
}
