<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Tests\Unit\Support;

use CodeIgniter\Config\Services;
use Perfbase\CodeIgniter4\Tests\Support\CodeIgniterTestCase;
use Perfbase\CodeIgniter4\Support\UserResolverFactory;
use Perfbase\CodeIgniter4\Tests\Fixtures\FakeUserResolver;
use Perfbase\CodeIgniter4\Tests\Fixtures\NoArgsUserResolver;

final class UserResolverFactoryTest extends CodeIgniterTestCase
{
    protected function tearDown(): void
    {
        Services::reset();

        parent::tearDown();
    }

    public function testBuildsResolversFromServiceNameAndClassName(): void
    {
        $factory = new UserResolverFactory();

        Services::injectMock('customUserResolver', new FakeUserResolver('service-user'));

        self::assertNull($factory->make(null));
        self::assertNull($factory->make('   '));
        self::assertInstanceOf(FakeUserResolver::class, $factory->make('customUserResolver'));
        self::assertInstanceOf(NoArgsUserResolver::class, $factory->make(NoArgsUserResolver::class));
        self::assertNull($factory->make(\stdClass::class));
        self::assertNull($factory->make('missingResolverService'));
    }
}
