<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Tests\Unit\Config;

use Perfbase\CodeIgniter4\Config\Perfbase;
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
        self::assertSame([], $config->excludeConsole);
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
