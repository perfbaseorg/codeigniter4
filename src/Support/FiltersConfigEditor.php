<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Support;

final class FiltersConfigEditor
{
    public function __construct(private string $path)
    {
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    public function hasAlias(): bool
    {
        if (!$this->exists()) {
            return false;
        }

        $contents = file_get_contents($this->path);

        return is_string($contents) && preg_match("/'perfbase'\\s*=>/", $contents) === 1;
    }

    public function hasGlobalBefore(): bool
    {
        return $this->sectionContains('before');
    }

    public function hasGlobalAfter(): bool
    {
        return $this->sectionContains('after');
    }

    public function ensureAlias(): bool
    {
        if ($this->hasAlias()) {
            return false;
        }

        $lines = $this->readLines();
        $output = [];
        $inAliases = false;
        $inserted = false;

        foreach ($lines as $line) {
            if (str_contains($line, 'public array $aliases = [')) {
                $inAliases = true;
            }

            if ($inAliases && trim($line) === '];') {
                $output[] = "        'perfbase'      => \\Perfbase\\CodeIgniter4\\Filters\\PerfbaseFilter::class,\n";
                $inserted = true;
                $inAliases = false;
            }

            $output[] = $line;
        }

        if (!$inserted) {
            throw new \RuntimeException('Unable to locate the aliases section in Filters.php.');
        }

        $this->writeLines($output);

        return true;
    }

    public function ensureGlobalBefore(): bool
    {
        return $this->ensureGlobalEntry('before');
    }

    public function ensureGlobalAfter(): bool
    {
        return $this->ensureGlobalEntry('after');
    }

    private function ensureGlobalEntry(string $phase): bool
    {
        if (($phase === 'before' && $this->hasGlobalBefore()) || ($phase === 'after' && $this->hasGlobalAfter())) {
            return false;
        }

        $lines = $this->readLines();
        $output = [];
        $inGlobals = false;
        $inSection = false;
        $inserted = false;

        foreach ($lines as $line) {
            if (str_contains($line, 'public array $globals = [')) {
                $inGlobals = true;
            }

            if ($inGlobals && str_contains($line, sprintf("'%s' => [", $phase))) {
                $inSection = true;
            }

            if ($inSection && trim($line) === '],') {
                $output[] = "            'perfbase',\n";
                $inserted = true;
                $inSection = false;
            }

            if ($inGlobals && trim($line) === '];') {
                $inGlobals = false;
            }

            $output[] = $line;
        }

        if (!$inserted) {
            throw new \RuntimeException(sprintf('Unable to locate the globals.%s section in Filters.php.', $phase));
        }

        $this->writeLines($output);

        return true;
    }

    /**
     * @return list<string>
     */
    private function readLines(): array
    {
        if (!$this->exists()) {
            throw new \RuntimeException(sprintf('Filters configuration file not found: %s', $this->path));
        }

        $lines = file($this->path);
        if (!is_array($lines)) {
            throw new \RuntimeException(sprintf('Unable to read filters configuration file: %s', $this->path));
        }

        return $lines;
    }

    /**
     * @param list<string> $lines
     */
    private function writeLines(array $lines): void
    {
        if (file_put_contents($this->path, implode('', $lines)) === false) {
            throw new \RuntimeException(sprintf('Unable to update filters configuration file: %s', $this->path));
        }
    }

    private function sectionContains(string $phase): bool
    {
        if (!$this->exists()) {
            return false;
        }

        $contents = file_get_contents($this->path);
        if (!is_string($contents)) {
            return false;
        }

        if (preg_match('/public array \\$globals = \\[(.*?)^\\s*\\];/ms', $contents, $globalsMatch) !== 1) {
            return false;
        }

        $globalsBody = $globalsMatch[1];
        $pattern = sprintf("/'%s'\\s*=>\\s*\\[(.*?)^\\s*\\],/ms", preg_quote($phase, '/'));
        if (preg_match($pattern, $globalsBody, $sectionMatch) !== 1) {
            return false;
        }

        return preg_match("/'perfbase'/", $sectionMatch[1]) === 1;
    }
}
