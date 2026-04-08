<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Tests\Unit\Config;

use Perfbase\CodeIgniter4\Config\Registrar;
use Perfbase\CodeIgniter4\Filters\PerfbaseFilter;
use PHPUnit\Framework\TestCase;

final class RegistrarTest extends TestCase
{
    public function testFiltersAliasRegistration(): void
    {
        self::assertSame([
            'aliases' => [
                'perfbase' => PerfbaseFilter::class,
            ],
        ], Registrar::Filters());
    }
}
