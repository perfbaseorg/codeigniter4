<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Tests\Unit\Support;

use Perfbase\CodeIgniter4\Config\Perfbase;
use Perfbase\CodeIgniter4\Runtime\PerfbaseClientProvider;
use Perfbase\CodeIgniter4\Support\ErrorHandler;
use Perfbase\CodeIgniter4\Tests\Fixtures\RecordingPerfbaseClient;
use Perfbase\SDK\Config;
use Perfbase\SDK\Perfbase as PerfbaseClient;
use PHPUnit\Framework\TestCase;

final class PerfbaseClientProviderTest extends TestCase
{
    public function testValidConfigReturnsCachedClient(): void
    {
        $config = new Perfbase();
        $config->apiKey = 'test-key';

        $created = 0;
        $expectedClient = new RecordingPerfbaseClient();
        $provider = new PerfbaseClientProvider(
            $config,
            new ErrorHandler(false, false),
            static function (Config $sdkConfig) use (&$created, $expectedClient): PerfbaseClient {
                $created++;
                return $expectedClient;
            }
        );

        $first = $provider->getClient();
        $second = $provider->getClient();

        self::assertSame($expectedClient, $first);
        self::assertSame($first, $second);
        self::assertSame(1, $created);
    }

    public function testInvalidConfigDegradesToNull(): void
    {
        $config = new Perfbase();
        $config->apiKey = '';

        $provider = new PerfbaseClientProvider($config, new ErrorHandler(false, false));

        self::assertNull($provider->getClient());
    }

    public function testFactoryMustReturnPerfbaseInstance(): void
    {
        $config = new Perfbase();
        $config->apiKey = 'test-key';

        $provider = new PerfbaseClientProvider(
            $config,
            new ErrorHandler(false, false),
            static function () {
                return new \stdClass();
            }
        );

        self::assertNull($provider->getClient());
    }

    public function testFailedInitializationDoesNotRetryFactory(): void
    {
        $config = new Perfbase();
        $config->apiKey = 'test-key';

        $attempts = 0;
        $provider = new PerfbaseClientProvider(
            $config,
            new ErrorHandler(false, false),
            static function () use (&$attempts): never {
                $attempts++;
                throw new \RuntimeException('factory boom');
            }
        );

        self::assertNull($provider->getClient());
        self::assertNull($provider->getClient());
        self::assertSame(1, $attempts);
    }
}
