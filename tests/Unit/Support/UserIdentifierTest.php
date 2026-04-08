<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Tests\Unit\Support;

use Perfbase\CodeIgniter4\Support\UserIdentifier;
use PHPUnit\Framework\TestCase;

final class UserIdentifierTest extends TestCase
{
    public function testResolvesScalarAndObjectIdentifiers(): void
    {
        self::assertSame('42', UserIdentifier::fromValue(42));
        self::assertNull(UserIdentifier::fromValue('   '));
        self::assertNull(UserIdentifier::fromValue(['not' => 'valid']));

        $methodBacked = new class {
            public function getIdentifier(): string
            {
                return 'abc';
            }
        };

        $propertyBacked = new class {
            public int $id = 99;
        };

        $invalidObject = new class {
            public function getId(): array
            {
                return [];
            }
        };

        self::assertSame('abc', UserIdentifier::fromValue($methodBacked));
        self::assertSame('99', UserIdentifier::fromValue($propertyBacked));
        self::assertNull(UserIdentifier::fromValue($invalidObject));
    }
}
