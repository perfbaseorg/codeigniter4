<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Tests\Integration;

use CodeIgniter\Config\Services;
use Perfbase\CodeIgniter4\Tests\Support\CodeIgniterTestCase;
use Perfbase\CodeIgniter4\Runtime\ActiveLifecycleRegistry;
use Perfbase\CodeIgniter4\Runtime\PerfbaseCommands;
use Perfbase\CodeIgniter4\Tests\Fixtures\Commands\FailingCommand;
use Perfbase\CodeIgniter4\Tests\Fixtures\Commands\NestedCommand;
use Perfbase\CodeIgniter4\Tests\Fixtures\Commands\SuccessfulCommand;
use Perfbase\CodeIgniter4\Tests\Fixtures\RecordingPerfbaseClient;
use Perfbase\CodeIgniter4\Tests\Fixtures\TestPerfbaseClientProvider;

final class ConsoleEventsIntegrationTest extends CodeIgniterTestCase
{
    private RecordingPerfbaseClient $client;
    private ActiveLifecycleRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new RecordingPerfbaseClient();
        Services::injectMock('perfbaseClientProvider', new TestPerfbaseClientProvider($this->client));
        $this->registry = new ActiveLifecycleRegistry();
        Services::injectMock('perfbaseLifecycleRegistry', $this->registry);

        $config = config('Perfbase');
        $config->enabled = true;
        $config->apiKey = 'test-key';
        $config->sampleRate = 1.0;
    }

    public function testCommandsServiceIsDecoratedAndProfilesSuccessfulCommand(): void
    {
        $commands = service('commands');
        self::assertInstanceOf(PerfbaseCommands::class, $commands);

        $this->setCommandMap($commands, [
            'test:ok' => [
                'class' => SuccessfulCommand::class,
                'file' => __FILE__,
                'group' => 'Tests',
                'description' => 'ok',
            ],
        ]);

        $exit = $commands->run('test:ok', []);

        self::assertSame(17, $exit);
        self::assertSame(['cli'], $this->client->startedSpans);
        self::assertSame('17', $this->client->attributes['exit_code']);
        self::assertSame(1, $this->client->submitCalls);
        self::assertNull($this->registry->getActiveConsoleLifecycle());
    }

    public function testFailedCommandStillStopsAndSubmits(): void
    {
        $commands = service('commands');
        self::assertInstanceOf(PerfbaseCommands::class, $commands);

        $this->setCommandMap($commands, [
            'test:fail' => [
                'class' => FailingCommand::class,
                'file' => __FILE__,
                'group' => 'Tests',
                'description' => 'fail',
            ],
        ]);

        $this->expectException(\RuntimeException::class);

        try {
            $commands->run('test:fail', []);
        } finally {
            self::assertSame(['cli'], $this->client->startedSpans);
            self::assertSame('1', $this->client->attributes['exit_code']);
            self::assertSame('cli boom', $this->client->attributes['exception']);
            self::assertSame(1, $this->client->submitCalls);
            self::assertNull($this->registry->getActiveConsoleLifecycle());
        }
    }

    public function testNestedCommandIsProfiledAsPartOfTheOuterCommand(): void
    {
        $commands = service('commands');
        self::assertInstanceOf(PerfbaseCommands::class, $commands);

        $this->setCommandMap($commands, [
            'test:nested' => [
                'class' => NestedCommand::class,
                'file' => __FILE__,
                'group' => 'Tests',
                'description' => 'nested',
            ],
            'test:ok' => [
                'class' => SuccessfulCommand::class,
                'file' => __FILE__,
                'group' => 'Tests',
                'description' => 'ok',
            ],
        ]);

        self::assertSame(5, $commands->run('test:nested', []));

        self::assertSame(['cli'], $this->client->startedSpans);
        self::assertSame(['cli'], $this->client->stoppedSpans);
        self::assertSame('test:nested', $this->client->attributes['action']);
        self::assertSame('5', $this->client->attributes['exit_code']);
        self::assertSame(1, $this->client->submitCalls);
        self::assertNull($this->registry->getActiveConsoleLifecycle());

        // The depth counter is balanced, so the next top-level command is profiled.
        self::assertSame(17, $commands->run('test:ok', []));
        self::assertSame(['cli', 'cli'], $this->client->startedSpans);
        self::assertSame(2, $this->client->submitCalls);
    }

    public function testLongRunningCommandsAreExcludedByDefault(): void
    {
        $commands = service('commands');
        self::assertInstanceOf(PerfbaseCommands::class, $commands);

        $this->setCommandMap($commands, [
            'queue:work' => [
                'class' => SuccessfulCommand::class,
                'file' => __FILE__,
                'group' => 'Tests',
                'description' => 'worker',
            ],
            'serve' => [
                'class' => SuccessfulCommand::class,
                'file' => __FILE__,
                'group' => 'Tests',
                'description' => 'server',
            ],
        ]);

        self::assertSame(17, $commands->run('queue:work', []));
        self::assertSame(17, $commands->run('serve', []));

        self::assertSame([], $this->client->startedSpans);
        self::assertSame(0, $this->client->submitCalls);
        self::assertNull($this->registry->getActiveConsoleLifecycle());
    }

    public function testSequentialCommandsDoNotLeakLifecycleState(): void
    {
        $commands = service('commands');
        self::assertInstanceOf(PerfbaseCommands::class, $commands);

        $this->setCommandMap($commands, [
            'test:ok' => [
                'class' => SuccessfulCommand::class,
                'file' => __FILE__,
                'group' => 'Tests',
                'description' => 'ok',
            ],
        ]);

        self::assertSame(17, $commands->run('test:ok', []));
        self::assertNull($this->registry->getActiveConsoleLifecycle());

        self::assertSame(17, $commands->run('test:ok', []));

        self::assertSame(['cli', 'cli'], $this->client->startedSpans);
        self::assertSame(2, $this->client->submitCalls);
        self::assertNull($this->registry->getActiveConsoleLifecycle());
    }

    private function setCommandMap(PerfbaseCommands $commands, array $map): void
    {
        $reflection = new \ReflectionClass($commands);
        $property = $reflection->getParentClass()->getProperty('commands');
        $property->setValue($commands, $map);
    }
}
