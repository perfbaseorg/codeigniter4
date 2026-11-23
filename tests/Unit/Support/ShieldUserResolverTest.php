<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Tests\Unit\Support;

use CodeIgniter\Config\Services;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use Perfbase\CodeIgniter4\Tests\Support\CodeIgniterTestCase;
use Perfbase\CodeIgniter4\Support\ShieldUserResolver;

final class ShieldUserResolverTest extends CodeIgniterTestCase
{
    protected function tearDown(): void
    {
        Services::reset();

        parent::tearDown();
    }

    public function testResolvesUsersFromAvailableAuthServices(): void
    {
        $resolver = new ShieldUserResolver();
        $request = new IncomingRequest(config('App'), new URI('https://example.com/profile'), null, new UserAgent());

        self::assertFalse($resolver->isAvailable());
        self::assertNull($resolver->resolve($request));

        Services::injectMock('auth', new class {
            public function user(): object
            {
                return new class {
                    public function getId(): int
                    {
                        return 123;
                    }
                };
            }
        });

        self::assertTrue($resolver->isAvailable());
        self::assertSame('123', $resolver->resolve($request));

        Services::reset();
        Services::injectMock('authentication', new class {
            public function getUser(): object
            {
                return new class {
                    public int $id = 456;
                };
            }
        });

        self::assertTrue($resolver->isAvailable());
        self::assertSame('456', $resolver->resolve($request));
    }

    public function testResolvingDoesNotEmitDeprecations(): void
    {
        // Runs on every HTTP request; ReflectionProperty::setAccessible() is
        // deprecated on PHP 8.5 and would be logged by CodeIgniter each time.
        $request = new IncomingRequest(config('App'), new URI('https://example.com/profile'), null, new UserAgent());
        $deprecations = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$deprecations): bool {
            $deprecations[] = $errstr;

            return true;
        }, E_DEPRECATED | E_USER_DEPRECATED);

        try {
            $resolver = new ShieldUserResolver();
            $resolver->isAvailable();
            $resolver->resolve($request);
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $deprecations);
    }
}
