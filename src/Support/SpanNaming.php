<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Support;

class SpanNaming
{
    public static function forHttp(
        string $method,
        ?string $routePattern,
        ?string $controller,
        ?string $controllerMethod,
        string $path
    ): string {
        $identifier = $routePattern;

        if ($identifier === null || $identifier === '') {
            if ($controller !== null && $controller !== '' && $controllerMethod !== null && $controllerMethod !== '') {
                $identifier = sprintf('%s::%s', $controller, $controllerMethod);
            } else {
                $identifier = self::ensureLeadingSlash($path);
            }
        } elseif ($identifier[0] !== '/') {
            $identifier = self::ensureLeadingSlash($identifier);
        }

        return sprintf('http.%s.%s', strtoupper($method), $identifier);
    }

    public static function forConsole(string $command): string
    {
        return sprintf('console.%s', $command);
    }

    /**
     * @param array<int, mixed> $args
     */
    public static function detectConsoleCommand(array $args): string
    {
        foreach ($args as $arg) {
            if (is_string($arg) && $arg !== '') {
                return $arg;
            }

            if (is_object($arg)) {
                if (method_exists($arg, 'getName')) {
                    $name = $arg->getName();
                    if (is_string($name) && $name !== '') {
                        return $name;
                    }
                }

                if (method_exists($arg, '__toString')) {
                    $value = (string) $arg;
                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }

        if (isset($_SERVER['argv']) && is_array($_SERVER['argv'])) {
            foreach ($_SERVER['argv'] as $index => $value) {
                if (!is_string($value) || $value === '' || $index === 0 || str_starts_with($value, '-')) {
                    continue;
                }

                if ($value === 'spark' && isset($_SERVER['argv'][$index + 1]) && is_string($_SERVER['argv'][$index + 1])) {
                    return $_SERVER['argv'][$index + 1];
                }

                return $value;
            }
        }

        return 'spark:unknown';
    }

    /**
     * @param array<int, mixed> $args
     */
    public static function detectExitCode(array $args): int
    {
        foreach ($args as $arg) {
            if (is_int($arg)) {
                return $arg;
            }
        }

        return 0;
    }

    /**
     * @param array<int, mixed> $args
     */
    public static function detectThrowable(array $args): ?\Throwable
    {
        foreach ($args as $arg) {
            if ($arg instanceof \Throwable) {
                return $arg;
            }
        }

        return null;
    }

    private static function ensureLeadingSlash(string $value): string
    {
        return '/' . ltrim($value, '/');
    }
}
