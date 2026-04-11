<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Tests\Unit\Runtime;

use CodeIgniter\Events\Events;
use Perfbase\CodeIgniter4\Tests\Fixtures\Commands\FailingCommand;
use Perfbase\CodeIgniter4\Tests\Fixtures\Commands\SuccessfulCommand;
use Perfbase\CodeIgniter4\Runtime\PerfbaseCommands;
use PHPUnit\Framework\TestCase;

final class PerfbaseCommandsTest extends TestCase
{
    public function testRunMatchesNativeBehaviorForUnknownCommand(): void
    {
        $commands = new class (service('logger')) extends PerfbaseCommands {
            public bool $verifyCalled = false;

            public function verifyCommand(string $command, array $commands): bool
            {
                $this->verifyCalled = true;

                return false;
            }
        };
        $nativePreCalled = false;
        $nativePostCalled = false;
        $perfbasePreCalled = false;
        $perfbasePostCalled = false;

        Events::on('pre_command', static function () use (&$nativePreCalled): void {
            $nativePreCalled = true;
        });
        Events::on('post_command', static function () use (&$nativePostCalled): void {
            $nativePostCalled = true;
        });
        Events::on('perfbase_pre_command', static function () use (&$perfbasePreCalled): void {
            $perfbasePreCalled = true;
        });
        Events::on('perfbase_post_command', static function () use (&$perfbasePostCalled): void {
            $perfbasePostCalled = true;
        });

        $result = $commands->run('missing:command', []);

        self::assertTrue($commands->verifyCalled);
        self::assertTrue($result === null);
        self::assertFalse($nativePreCalled);
        self::assertFalse($nativePostCalled);
        self::assertFalse($perfbasePreCalled);
        self::assertFalse($perfbasePostCalled);
    }

    public function testRunPreservesNativeEventsAndAddsPerfbaseEventsForSuccess(): void
    {
        $commands = new PerfbaseCommands(service('logger'));
        $this->setCommandMap($commands, [
            'test:ok' => [
                'class' => SuccessfulCommand::class,
                'file' => __FILE__,
                'group' => 'Tests',
                'description' => 'ok',
            ],
        ]);

        $nativePreArgs = null;
        $nativePostArgs = null;
        $perfbasePreArgs = null;
        $perfbasePostArgs = null;

        Events::on('pre_command', static function (...$args) use (&$nativePreArgs): void {
            $nativePreArgs = $args;
        });
        Events::on('post_command', static function (...$args) use (&$nativePostArgs): void {
            $nativePostArgs = $args;
        });
        Events::on('perfbase_pre_command', static function (...$args) use (&$perfbasePreArgs): void {
            $perfbasePreArgs = $args;
        });
        Events::on('perfbase_post_command', static function (...$args) use (&$perfbasePostArgs): void {
            $perfbasePostArgs = $args;
        });

        self::assertSame(17, $commands->run('test:ok', []));
        self::assertSame([], $nativePreArgs);
        self::assertSame([], $nativePostArgs);
        self::assertIsArray($perfbasePreArgs);
        self::assertIsArray($perfbasePostArgs);
        self::assertSame('test:ok', $perfbasePreArgs[0]);
        self::assertSame(17, $perfbasePostArgs[1]);
        self::assertNull($perfbasePostArgs[2]);
    }

    public function testRunDoesNotTriggerNativePostCommandOnFailure(): void
    {
        $commands = new PerfbaseCommands(service('logger'));
        $this->setCommandMap($commands, [
            'test:fail' => [
                'class' => FailingCommand::class,
                'file' => __FILE__,
                'group' => 'Tests',
                'description' => 'fail',
            ],
        ]);

        $nativePreCalled = false;
        $nativePostCalled = false;
        $perfbasePostArgs = null;

        Events::on('pre_command', static function () use (&$nativePreCalled): void {
            $nativePreCalled = true;
        });
        Events::on('post_command', static function () use (&$nativePostCalled): void {
            $nativePostCalled = true;
        });
        Events::on('perfbase_post_command', static function (...$args) use (&$perfbasePostArgs): void {
            $perfbasePostArgs = $args;
        });

        $this->expectException(\RuntimeException::class);

        try {
            $commands->run('test:fail', []);
        } finally {
            self::assertTrue($nativePreCalled);
            self::assertFalse($nativePostCalled);
            self::assertIsArray($perfbasePostArgs);
            self::assertSame('test:fail', $perfbasePostArgs[0]);
            self::assertSame(EXIT_ERROR, $perfbasePostArgs[1]);
            self::assertInstanceOf(\RuntimeException::class, $perfbasePostArgs[2]);
        }
    }

    protected function tearDown(): void
    {
        Events::removeAllListeners('pre_command');
        Events::removeAllListeners('post_command');
        Events::removeAllListeners('perfbase_pre_command');
        Events::removeAllListeners('perfbase_post_command');

        parent::tearDown();
    }

    private function setCommandMap(PerfbaseCommands $commands, array $map): void
    {
        $reflection = new \ReflectionClass($commands);
        $property = $reflection->getParentClass()->getProperty('commands');
        $property->setAccessible(true);
        $property->setValue($commands, $map);
    }
}
