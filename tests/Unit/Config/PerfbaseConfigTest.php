<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Tests\Unit\Config;

use Perfbase\CodeIgniter4\Config\Perfbase;
use Perfbase\CodeIgniter4\Runtime\PerfbaseClientProvider;
use Perfbase\CodeIgniter4\Support\ErrorHandler;
use Perfbase\CodeIgniter4\Tests\Fixtures\RecordingPerfbaseClient;
use Perfbase\SDK\Config;
use Perfbase\SDK\FeatureFlags;
use PHPUnit\Framework\TestCase;

final class PerfbaseConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->clearEnv('perfbase.enabled');
        $this->clearEnv('perfbase.proxy');
        $this->clearEnv('perfbase.profileHttpStatusCodes');
        $this->clearEnv('perfbase.includeHttp');
        $this->clearEnv('perfbase.excludeHttp');
        $this->clearEnv('perfbase.includeConsole');
        $this->clearEnv('perfbase.excludeConsole');
        $this->clearEnv('perfbase.userResolver');
        $this->clearEnv('perfbase.apiKey');
        $this->clearEnv('perfbase.apiUrl');
        $this->clearEnv('perfbase.timeout');
        $this->clearEnv('perfbase.flags');
        $this->clearEnv('perfbase.sampleRate');
        $this->clearEnv('perfbase.logErrors');

        parent::tearDown();
    }

    public function testDefaultsAreCorrect(): void
    {
        $config = new Perfbase();

        self::assertFalse($config->enabled);
        self::assertFalse($config->debug);
        self::assertTrue($config->logErrors);
        self::assertSame('https://ingress.perfbase.cloud', $config->apiUrl);
        self::assertSame(0.1, $config->sampleRate);
        self::assertSame(
            [...range(200, 299), ...range(500, 599)],
            $config->profileHttpStatusCodes
        );
        self::assertSame(['*'], $config->includeHttp);
        self::assertSame(['serve', 'queue:work'], $config->excludeConsole);
        self::assertSame(FeatureFlags::DefaultFlags, $config->flags);
    }

    public function testStringEnvValuesProduceAValidSdkConfig(): void
    {
        $this->setEnv('perfbase.apiKey', 'test-key');
        $this->setEnv('perfbase.timeout', '12');
        $this->setEnv('perfbase.flags', (string) FeatureFlags::TrackPdo);
        $this->setEnv('perfbase.sampleRate', '0.5');

        $config = new Perfbase();

        self::assertSame(12, $config->timeout);
        self::assertSame(FeatureFlags::TrackPdo, $config->flags);
        self::assertSame(0.5, $config->sampleRate);

        $sdkConfig = $this->buildSdkConfig($config);
        self::assertSame(12, $sdkConfig->getTimeout());
        self::assertSame(FeatureFlags::TrackPdo, $sdkConfig->getFlags());
    }

    public function testBlankEnvValuesKeepTheDefaults(): void
    {
        // Mirrors an .env file copied from the README template with blanks left in.
        $this->setEnv('perfbase.apiKey', 'test-key');
        $this->setEnv('perfbase.apiUrl', '');
        $this->setEnv('perfbase.timeout', '');
        $this->setEnv('perfbase.flags', '');
        $this->setEnv('perfbase.sampleRate', '');
        $this->setEnv('perfbase.logErrors', '');
        $this->setEnv('perfbase.proxy', '');
        $this->setEnv('perfbase.excludeConsole', '');

        $config = new Perfbase();

        self::assertSame('https://ingress.perfbase.cloud', $config->apiUrl);
        self::assertSame(10, $config->timeout);
        self::assertSame(FeatureFlags::DefaultFlags, $config->flags);
        self::assertSame(0.1, $config->sampleRate);
        self::assertTrue($config->logErrors);
        self::assertNull($config->proxy);
        self::assertSame(['serve', 'queue:work'], $config->excludeConsole);

        $sdkConfig = $this->buildSdkConfig($config);
        self::assertSame(10, $sdkConfig->getTimeout());
        self::assertSame(FeatureFlags::DefaultFlags, $sdkConfig->getFlags());
        self::assertSame('https://ingress.perfbase.cloud', $sdkConfig->getApiUrl());
    }

    private function buildSdkConfig(Perfbase $config): Config
    {
        $captured = null;
        $provider = new PerfbaseClientProvider(
            $config,
            new ErrorHandler(true, true),
            static function (Config $sdkConfig) use (&$captured): RecordingPerfbaseClient {
                $captured = $sdkConfig;

                return new RecordingPerfbaseClient();
            }
        );

        self::assertNotNull($provider->getClient());
        self::assertInstanceOf(Config::class, $captured);

        return $captured;
    }

    public function testEnvBackedValuesAreNormalized(): void
    {
        $this->setEnv('perfbase.enabled', 'true');
        $this->setEnv('perfbase.proxy', '   ');
        $this->setEnv('perfbase.profileHttpStatusCodes', "200,\n201,404");
        $this->setEnv('perfbase.includeHttp', "GET /articles/*,\nPOST /articles");
        $this->setEnv('perfbase.excludeHttp', "GET /admin,\nPOST /admin");
        $this->setEnv('perfbase.includeConsole', "cache:clear\nqueue:*");
        $this->setEnv('perfbase.excludeConsole', "queue:work\ncache:*");
        $this->setEnv('perfbase.userResolver', ' customResolver ');

        $config = new Perfbase();

        self::assertTrue($config->enabled);
        self::assertNull($config->proxy);
        self::assertSame([200, 201, 404], $config->profileHttpStatusCodes);
        self::assertSame(['GET /articles/*', 'POST /articles'], $config->includeHttp);
        self::assertSame(['GET /admin', 'POST /admin'], $config->excludeHttp);
        self::assertSame(['cache:clear', 'queue:*'], $config->includeConsole);
        self::assertSame(['queue:work', 'cache:*'], $config->excludeConsole);
        self::assertSame('customResolver', $config->userResolver);
    }

    private function setEnv(string $key, string $value): void
    {
        putenv(sprintf('%s=%s', $key, $value));
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    private function clearEnv(string $key): void
    {
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }
}
