<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Config;

use CodeIgniter\Config\BaseConfig;
use Perfbase\SDK\FeatureFlags;

class Perfbase extends BaseConfig
{
    public bool $enabled = false;
    public bool $debug = false;
    public bool $logErrors = true;
    public string $apiKey = '';
    public string $apiUrl = 'https://ingress.perfbase.cloud';
    public float $sampleRate = 0.1;
    public int $timeout = 10;
    public ?string $proxy = null;
    public int $flags = FeatureFlags::DefaultFlags;
    public string $appVersion = '';
    public ?string $userResolver = null;

    /** @var array<int, int>|string */
    public array|string $profileHttpStatusCodes = '';

    /** @var array<int, string> */
    public array $includeHttp = ['*'];

    /** @var array<int, string> */
    public array $excludeHttp = [];

    /** @var array<int, string> */
    public array $includeConsole = ['*'];

    /** @var array<int, string> */
    public array $excludeConsole = [];

    public function __construct()
    {
        parent::__construct();

        $this->enabled = $this->normalizeBool(env('perfbase.enabled'), $this->enabled);
        $this->debug = $this->normalizeBool(env('perfbase.debug'), $this->debug);
        $this->logErrors = $this->normalizeBool(env('perfbase.logErrors'), $this->logErrors);
        $this->apiKey = $this->normalizeString(env('perfbase.apiKey'), $this->apiKey);
        $this->apiUrl = $this->normalizeString(env('perfbase.apiUrl'), $this->apiUrl);
        $this->sampleRate = $this->normalizeFloat(env('perfbase.sampleRate'), $this->sampleRate);
        $this->timeout = $this->normalizeInt(env('perfbase.timeout'), $this->timeout);
        $this->proxy = $this->normalizeEnvNullableString('perfbase.proxy', $this->proxy);
        $this->flags = $this->normalizeInt(env('perfbase.flags'), $this->flags);
        $this->appVersion = $this->normalizeString(env('perfbase.appVersion'), $this->appVersion);
        $this->userResolver = $this->normalizeEnvNullableString('perfbase.userResolver', $this->userResolver);
        $this->profileHttpStatusCodes = $this->normalizeIntList(
            env('perfbase.profileHttpStatusCodes'),
            is_array($this->profileHttpStatusCodes) ? $this->profileHttpStatusCodes : $this->defaultProfileHttpStatusCodes()
        );
        $this->includeHttp = $this->normalizeStringList(env('perfbase.includeHttp'), $this->includeHttp);
        $this->excludeHttp = $this->normalizeStringList(env('perfbase.excludeHttp'), $this->excludeHttp);
        $this->includeConsole = $this->normalizeStringList(env('perfbase.includeConsole'), $this->includeConsole);
        $this->excludeConsole = $this->normalizeStringList(env('perfbase.excludeConsole'), $this->excludeConsole);
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private function normalizeStringList(mixed $value, array $default = []): array
    {
        if (is_string($value)) {
            $parts = preg_split('/\r\n|\r|\n|,/', $value);
            if (!is_array($parts)) {
                return $default;
            }

            return array_values(array_filter(array_map('trim', $parts), static fn (string $part): bool => $part !== ''));
        }

        if ($value === null) {
            return $default;
        }

        if (!is_array($value)) {
            return $default;
        }

        $normalized = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                continue;
            }

            $item = trim($item);
            if ($item === '') {
                continue;
            }

            $normalized[] = $item;
        }

        return $normalized;
    }

    /**
     * @param mixed $value
     * @param array<int, int> $default
     * @return array<int, int>
     */
    private function normalizeIntList(mixed $value, array $default = []): array
    {
        if (is_string($value)) {
            $parts = preg_split('/\r\n|\r|\n|,/', $value);
            if (!is_array($parts)) {
                return $default;
            }

            $value = $parts;
        }

        if ($value === null) {
            return $default;
        }

        if (!is_array($value)) {
            return $default;
        }

        $normalized = [];
        foreach ($value as $item) {
            if (is_int($item)) {
                $normalized[] = $item;
                continue;
            }

            if (!is_string($item)) {
                continue;
            }

            $item = trim($item);
            if ($item === '' || !ctype_digit($item)) {
                continue;
            }

            $normalized[] = (int) $item;
        }

        return array_values(array_unique($normalized));
    }

    private function normalizeBool(mixed $value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        return (bool) $value;
    }

    /**
     * @return array<int, int>
     */
    private function defaultProfileHttpStatusCodes(): array
    {
        return [...range(200, 299), ...range(500, 599)];
    }

    private function normalizeInt(mixed $value, int $default): int
    {
        if ($value === null) {
            return $default;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    private function normalizeFloat(mixed $value, float $default): float
    {
        if ($value === null) {
            return $default;
        }

        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return $default;
    }

    private function normalizeString(mixed $value, string $default): string
    {
        if ($value === null) {
            return $default;
        }

        if (!is_scalar($value)) {
            return $default;
        }

        return (string) $value;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function normalizeEnvNullableString(string $key, ?string $default): ?string
    {
        if (!$this->hasEnvValue($key)) {
            return $default;
        }

        return $this->normalizeNullableString(env($key));
    }

    private function hasEnvValue(string $key): bool
    {
        return array_key_exists($key, $_ENV)
            || array_key_exists($key, $_SERVER)
            || getenv($key) !== false;
    }
}
