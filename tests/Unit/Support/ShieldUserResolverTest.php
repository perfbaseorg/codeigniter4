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
}
