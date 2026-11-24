<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Tests\Unit\Commands;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Test\Mock\MockInputOutput;
use Perfbase\CodeIgniter4\Commands\PerfbaseInstallCommand;
use Perfbase\CodeIgniter4\Publishers\PerfbasePublisher;
use Perfbase\CodeIgniter4\Runtime\PerfbaseCommands;
use Perfbase\CodeIgniter4\Tests\Support\CodeIgniterTestCase;

final class PerfbaseInstallCommandTest extends CodeIgniterTestCase
{
    public function testInstallCommandPublishesAndUpdatesFiltersIdempotently(): void
    {
        $appPath = $this->writePath() . 'perfbase-ci4-install-' . bin2hex(random_bytes(4)) . '/';
        mkdir($appPath . 'Config', 0777, true);
        copy(dirname(__DIR__, 3) . '/vendor/codeigniter4/framework/app/Config/Filters.php', $appPath . 'Config/Filters.php');

        try {
            $command = new class(service('logger'), new PerfbaseCommands(service('logger')), $appPath) extends PerfbaseInstallCommand {
                public function __construct($logger, $commands, private string $appPath)
                {
                    parent::__construct($logger, $commands);
                }

                protected function publisher(): PerfbasePublisher
                {
                    return new PerfbasePublisher(null, $this->appPath);
                }

                protected function filtersConfigPath(): string
                {
                    return $this->appPath . 'Config/Filters.php';
                }
            };

            $output = $this->captureOutput(static function () use ($command): void {
                $firstRun = $command->run([]);
                $secondRun = $command->run([]);

                self::assertSame(EXIT_SUCCESS, $firstRun);
                self::assertSame(EXIT_SUCCESS, $secondRun);
            });
            self::assertFileExists($appPath . 'Config/Perfbase.php');

            $contents = file_get_contents($appPath . 'Config/Filters.php');
            self::assertIsString($contents);
            self::assertSame(1, preg_match_all("/'perfbase'\\s*=>/", $contents));
            self::assertSame(3, preg_match_all("/'perfbase'/", $contents));
            if ($output !== '') {
                self::assertStringContainsString('Perfbase install completed.', $output);
                self::assertStringContainsString('Set perfbase.apiKey', $output);
                self::assertStringNotContainsString('PERFBASE_API_KEY', $output);
            }
        } finally {
            @unlink($appPath . 'Config/Perfbase.php');
            @unlink($appPath . 'Config/Filters.php');
            @rmdir($appPath . 'Config');
            @rmdir($appPath);
        }
    }

    public function testInstallCommandSupportsAliasOnlyMode(): void
    {
        $appPath = $this->writePath() . 'perfbase-ci4-install-alias-' . bin2hex(random_bytes(4)) . '/';
        mkdir($appPath . 'Config', 0777, true);
        copy(dirname(__DIR__, 3) . '/vendor/codeigniter4/framework/app/Config/Filters.php', $appPath . 'Config/Filters.php');

        try {
            $command = new class(service('logger'), new PerfbaseCommands(service('logger')), $appPath) extends PerfbaseInstallCommand {
                public function __construct($logger, $commands, private string $appPath)
                {
                    parent::__construct($logger, $commands);
                }

                protected function publisher(): PerfbasePublisher
                {
                    return new PerfbasePublisher(null, $this->appPath);
                }

                protected function filtersConfigPath(): string
                {
                    return $this->appPath . 'Config/Filters.php';
                }
            };

            $output = $this->captureOutput(static function () use ($command): void {
                self::assertSame(EXIT_SUCCESS, $command->run(['alias-only' => null]));
            });

            $contents = file_get_contents($appPath . 'Config/Filters.php');
            self::assertIsString($contents);
            self::assertSame(1, preg_match_all("/'perfbase'\\s*=>/", $contents));
            self::assertSame(1, preg_match_all("/'perfbase'/", $contents));
            if ($output !== '') {
                self::assertStringContainsString('--alias-only was used', $output);
            }
        } finally {
            @unlink($appPath . 'Config/Perfbase.php');
            @unlink($appPath . 'Config/Filters.php');
            @rmdir($appPath . 'Config');
            @rmdir($appPath);
        }
    }

    public function testInstallCommandReturnsErrorWhenPublisherFails(): void
    {
        try {
            $command = new class(service('logger'), new PerfbaseCommands(service('logger'))) extends PerfbaseInstallCommand {
                protected function publisher(): PerfbasePublisher
                {
                    return new class extends PerfbasePublisher {
                        public function __construct()
                        {
                        }

                        public function publish(): bool
                        {
                            return false;
                        }
                    };
                }
            };

            $output = $this->captureOutput(static function () use ($command): void {
                self::assertSame(EXIT_ERROR, $command->run([]));
            });
            if ($output !== '') {
                self::assertStringContainsString('Unable to publish the Perfbase config stub.', $output);
            }
        } finally {
        }
    }

    public function testInstallCommandBaseHelpersUseApplicationPaths(): void
    {
        $command = new class(service('logger'), new PerfbaseCommands(service('logger'))) extends PerfbaseInstallCommand {
            public function publisherPublic(): PerfbasePublisher
            {
                return $this->publisher();
            }

            public function filtersConfigPathPublic(): string
            {
                return $this->filtersConfigPath();
            }
        };

        self::assertInstanceOf(PerfbasePublisher::class, $command->publisherPublic());
        self::assertSame($this->appPath() . 'Config/Filters.php', $command->filtersConfigPathPublic());
    }

    public function testInstallCommandReturnsErrorWhenFiltersConfigCannotBeEdited(): void
    {
        $appPath = $this->writePath() . 'perfbase-ci4-install-broken-' . bin2hex(random_bytes(4)) . '/';
        mkdir($appPath . 'Config', 0777, true);
        file_put_contents($appPath . 'Config/Filters.php', "<?php\nfinal class BrokenFilters {}\n");

        try {
            $command = new class(service('logger'), new PerfbaseCommands(service('logger')), $appPath) extends PerfbaseInstallCommand {
                public function __construct($logger, $commands, private string $appPath)
                {
                    parent::__construct($logger, $commands);
                }

                protected function publisher(): PerfbasePublisher
                {
                    return new PerfbasePublisher(null, $this->appPath);
                }

                protected function filtersConfigPath(): string
                {
                    return $this->appPath . 'Config/Filters.php';
                }
            };

            $output = $this->captureOutput(static function () use ($command): void {
                self::assertSame(EXIT_ERROR, $command->run([]));
            });
            if ($output !== '') {
                self::assertStringContainsString('Perfbase install failed.', $output);
            }
        } finally {
            @unlink($appPath . 'Config/Perfbase.php');
            @unlink($appPath . 'Config/Filters.php');
            @rmdir($appPath . 'Config');
            @rmdir($appPath);
        }
    }

    private function writePath(): string
    {
        return (string) constant('WRITEPATH');
    }

    private function appPath(): string
    {
        return (string) constant('APPPATH');
    }

    private function captureOutput(callable $callback): string
    {
        $supportsMockIo = class_exists(MockInputOutput::class)
            && (new \ReflectionClass(CLI::class))->hasMethod('setInputOutput')
            && (new \ReflectionClass(CLI::class))->hasMethod('resetInputOutput');

        if ($supportsMockIo) {
            $io = new MockInputOutput();
            $cliReflection = new \ReflectionClass(CLI::class);
            $cliReflection->getMethod('setInputOutput')->invoke(null, $io);

            try {
                $callback();

                return $io->getOutput();
            } finally {
                $cliReflection->getMethod('resetInputOutput')->invoke(null);
            }
        }

        ob_start();

        try {
            $callback();

            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
