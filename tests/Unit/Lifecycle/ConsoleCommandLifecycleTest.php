<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Tests\Unit\Lifecycle;

use Perfbase\CodeIgniter4\Config\Perfbase;
use Perfbase\CodeIgniter4\Lifecycle\ConsoleCommandLifecycle;
use Perfbase\CodeIgniter4\Support\ErrorHandler;
use Perfbase\CodeIgniter4\Tests\Fixtures\RecordingPerfbaseClient;
use Perfbase\CodeIgniter4\Tests\Fixtures\TestPerfbaseClientProvider;
use PHPUnit\Framework\TestCase;

final class ConsoleCommandLifecycleTest extends TestCase
{
    public function testConsoleLifecycleProfilesAndSetsExitCodeAndException(): void
    {
        $client = new RecordingPerfbaseClient();
        $lifecycle = new ConsoleCommandLifecycle(
            'test:ok',
            new TestPerfbaseClientProvider($client),
            new ErrorHandler(false, false),
            $this->config()
        );

        $lifecycle->startProfiling();
        $lifecycle->setExitCode(17);
        $lifecycle->setException(new \RuntimeException('cli boom'));
        $lifecycle->stopProfiling();

        self::assertSame(['cli'], $client->startedSpans);
        self::assertSame('test:ok', $client->attributes['action']);
        self::assertSame('console', $client->attributes['source']);
        self::assertSame('17', $client->attributes['exit_code']);
        self::assertSame('cli boom', $client->attributes['exception']);
    }

    public function testDisabledAndExcludedConsoleCommandsDoNotProfile(): void
    {
        $client = new RecordingPerfbaseClient();
        $config = $this->config();
        $config->enabled = false;

        $lifecycle = new ConsoleCommandLifecycle('test:ok', new TestPerfbaseClientProvider($client), new ErrorHandler(false, false), $config);
        $lifecycle->startProfiling();
        self::assertSame([], $client->startedSpans);

        $config = $this->config();
        $config->excludeConsole = ['test:*'];
        $lifecycle = new ConsoleCommandLifecycle('test:ok', new TestPerfbaseClientProvider($client), new ErrorHandler(false, false), $config);
        $lifecycle->startProfiling();
        self::assertSame([], $client->startedSpans);
    }

    private function config(): Perfbase
    {
        $config = new Perfbase();
        $config->enabled = true;
        $config->apiKey = 'test-key';
        $config->sampleRate = 1.0;

        return $config;
    }
}
