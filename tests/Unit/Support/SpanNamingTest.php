<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Tests\Unit\Support;

use Perfbase\CodeIgniter4\Support\SpanNaming;
use PHPUnit\Framework\TestCase;

final class SpanNamingTest extends TestCase
{
    public function testForHttpPrefersRoutePatternThenControllerThenPath(): void
    {
        self::assertSame(
            'http',
            SpanNaming::forHttp('GET', 'articles/(:num)', 'App\\Controllers\\Articles', 'show', '/articles/42')
        );

        self::assertSame(
            'http',
            SpanNaming::forHttp('GET', null, 'App\\Controllers\\Articles', 'show', '/articles/42')
        );

        self::assertSame(
            'http',
            SpanNaming::forHttp('GET', null, null, null, 'articles/42')
        );
    }

    public function testForConsoleAndDetectionHelpers(): void
    {
        $_SERVER['argv'] = ['php', 'spark', 'cache:clear'];

        self::assertSame('cli', SpanNaming::forConsole('cache:clear'));
        self::assertSame('cache:clear', SpanNaming::detectConsoleCommand([]));
        self::assertSame('queue:work', SpanNaming::detectConsoleCommand(['queue:work']));
        self::assertSame(17, SpanNaming::detectExitCode(['queue:work', 17]));

        $throwable = new \RuntimeException('boom');
        self::assertSame($throwable, SpanNaming::detectThrowable([$throwable]));
        self::assertNull(SpanNaming::detectThrowable([]));
    }

    public function testGeneratedSpanNamesAreSdkSafe(): void
    {
        $span = SpanNaming::forHttp('DELETE', '/accounts/{account}/settings', null, null, '/accounts/42/settings');

        self::assertMatchesRegularExpression('/^[A-Za-z]+$/', $span);
        self::assertLessThanOrEqual(64, strlen($span));
    }

    public function testDetectConsoleCommandSupportsObjectsAndUnknownFallback(): void
    {
        $namedCommand = new class {
            public function getName(): string
            {
                return 'migrate';
            }
        };

        $stringableCommand = new class {
            public function __toString(): string
            {
                return 'cache:warm';
            }
        };

        $_SERVER['argv'] = ['php', '--ansi'];

        self::assertSame('migrate', SpanNaming::detectConsoleCommand([$namedCommand]));
        self::assertSame('cache:warm', SpanNaming::detectConsoleCommand([$stringableCommand]));
        self::assertSame('spark:unknown', SpanNaming::detectConsoleCommand([]));
        self::assertSame(0, SpanNaming::detectExitCode(['cache:warm']));
    }
}
