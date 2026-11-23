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

    /**
     * Long-running Spark commands are excluded by default: a trace started for
     * the worker or server process would only end when the process exits.
     *
     * @var array<int, string>
     */
    public array $excludeConsole = ['serve', 'queue:work'];

    public function __construct()
    {
        // Snapshot the declared defaults (including any overrides in a published
        // app/Config/Perfbase.php) before BaseConfig applies env values: it casts
        // blank values such as `perfbase.timeout=` to 0 / '' in place.
        $defaults = [
            'enabled' => $this->enabled,
            'debug' => $this->debug,
            'logErrors' => $this->logErrors,
            'apiKey' => $this->apiKey,
            'apiUrl' => $this->apiUrl,
            'sampleRate' => $this->sampleRate,
            'timeout' => $this->timeout,
            'proxy' => $this->proxy,
            'flags' => $this->flags,
            'appVersion' => $this->appVersion,
            'userResolver' => $this->userResolver,
            'profileHttpStatusCodes' => is_array($this->profileHttpStatusCodes)
                ? $this->profileHttpStatusCodes
                : $this->defaultProfileHttpStatusCodes(),
            'includeHttp' => $this->includeHttp,
            'excludeHttp' => $this->excludeHttp,
            'includeConsole' => $this->includeConsole,
            'excludeConsole' => $this->excludeConsole,
        ];

        parent::__construct();

        // A blank env value (`perfbase.flags=`) means "not set" and keeps the default.
        $this->enabled = $this->normalizeBool($this->envValue('perfbase.enabled'), $defaults['enabled']);
        $this->debug = $this->normalizeBool($this->envValue('perfbase.debug'), $defaults['debug']);
        $this->logErrors = $this->normalizeBool($this->envValue('perfbase.logErrors'), $defaults['logErrors']);
        $this->apiKey = $this->normalizeString($this->envValue('perfbase.apiKey'), $defaults['apiKey']);
        $this->apiUrl = $this->normalizeString($this->envValue('perfbase.apiUrl'), $defaults['apiUrl']);
        $this->sampleRate = $this->normalizeFloat($this->envValue('perfbase.sampleRate'), $defaults['sampleRate']);
        $this->timeout = $this->normalizeInt($this->envValue('perfbase.timeout'), $defaults['timeout']);
        $this->proxy = $this->normalizeEnvNullableString('perfbase.proxy', $defaults['proxy']);
        $this->flags = $this->normalizeInt($this->envValue('perfbase.flags'), $defaults['flags']);
        $this->appVersion = $this->normalizeString($this->envValue('perfbase.appVersion'), $defaults['appVersion']);
        $this->userResolver = $this->normalizeEnvNullableString('perfbase.userResolver', $defaults['userResolver']);
        $this->profileHttpStatusCodes = $this->normalizeIntList(
            $this->envValue('perfbase.profileHttpStatusCodes'),
            $defaults['profileHttpStatusCodes']
        );
        $this->includeHttp = $this->normalizeStringList($this->envValue('perfbase.includeHttp'), $defaults['includeHttp']);
        $this->excludeHttp = $this->normalizeStringList($this->envValue('perfbase.excludeHttp'), $defaults['excludeHttp']);
        $this->includeConsole = $this->normalizeStringList($this->envValue('perfbase.includeConsole'), $defaults['includeConsole']);
        $this->excludeConsole = $this->normalizeStringList($this->envValue('perfbase.excludeConsole'), $defaults['excludeConsole']);
    }

    /**
     * Returns the env value, or null when it is unset or blank.
     */
    private function envValue(string $key): mixed
    {
        $value = env($key);

        if (is_string($value) && trim($value) === '') {
            return null;
        }

        return $value;
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
