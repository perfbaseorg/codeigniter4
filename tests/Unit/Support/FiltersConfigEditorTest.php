<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Tests\Unit\Support;

use Perfbase\CodeIgniter4\Support\FiltersConfigEditor;
use PHPUnit\Framework\TestCase;

final class FiltersConfigEditorTest extends TestCase
{
    public function testEnsuresAliasAndGlobalsIdempotently(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'perfbase-ci4-filters-');
        self::assertIsString($tmp);

        $filtersPath = $tmp . '.php';
        rename($tmp, $filtersPath);
        copy(dirname(__DIR__, 3) . '/vendor/codeigniter4/framework/app/Config/Filters.php', $filtersPath);

        try {
            $editor = new FiltersConfigEditor($filtersPath);

            self::assertTrue($editor->ensureAlias());
            self::assertTrue($editor->ensureGlobalBefore());
            self::assertTrue($editor->ensureGlobalAfter());
            self::assertFalse($editor->ensureAlias());
            self::assertFalse($editor->ensureGlobalBefore());
            self::assertFalse($editor->ensureGlobalAfter());

            $contents = file_get_contents($filtersPath);
            self::assertIsString($contents);
            self::assertSame(1, preg_match_all("/'perfbase'\\s*=>/", $contents));
            self::assertSame(3, preg_match_all("/'perfbase'/", $contents));
            self::assertTrue($editor->hasAlias());
            self::assertTrue($editor->hasGlobalBefore());
            self::assertTrue($editor->hasGlobalAfter());
        } finally {
            @unlink($filtersPath);
        }
    }

    public function testReportsMissingFilesAndMalformedSections(): void
    {
        $missingPath = sys_get_temp_dir() . '/perfbase-ci4-missing-filters-' . bin2hex(random_bytes(4)) . '.php';
        $editor = new FiltersConfigEditor($missingPath);

        self::assertFalse($editor->exists());
        self::assertFalse($editor->hasAlias());
        self::assertFalse($editor->hasGlobalBefore());
        self::assertFalse($editor->hasGlobalAfter());

        $tmp = tempnam(sys_get_temp_dir(), 'perfbase-ci4-bad-filters-');
        self::assertIsString($tmp);

        $filtersPath = $tmp . '.php';
        rename($tmp, $filtersPath);
        file_put_contents($filtersPath, "<?php\nfinal class BrokenFilters {}\n");

        try {
            $brokenEditor = new FiltersConfigEditor($filtersPath);
            $thrown = 0;

            foreach (['ensureAlias', 'ensureGlobalBefore', 'ensureGlobalAfter'] as $method) {
                try {
                    $brokenEditor->{$method}();
                    self::fail(sprintf('%s() should have thrown for malformed Filters.php.', $method));
                } catch (\RuntimeException) {
                    $thrown++;
                }
            }

            self::assertSame(3, $thrown);
        } finally {
            @unlink($filtersPath);
        }
    }
}
