<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Tests\Unit\Commands;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Config\Services;
use CodeIgniter\Test\Mock\MockInputOutput;
use Perfbase\CodeIgniter4\Commands\PerfbaseDoctorCommand;
use Perfbase\CodeIgniter4\Runtime\PerfbaseCommands;
use Perfbase\CodeIgniter4\Support\FiltersConfigEditor;
use Perfbase\CodeIgniter4\Tests\Support\CodeIgniterTestCase;
use Perfbase\CodeIgniter4\Tests\Fixtures\RecordingPerfbaseClient;
use Perfbase\CodeIgniter4\Tests\Fixtures\TestPerfbaseClientProvider;

final class PerfbaseDoctorCommandTest extends CodeIgniterTestCase
{
    public function testDoctorCommandReportsHealthySetup(): void
    {
        $appPath = sys_get_temp_dir() . '/perfbase-ci4-doctor-' . bin2hex(random_bytes(4)) . '/';
        mkdir($appPath . 'Config', 0777, true);
        mkdir($appPath . 'public', 0777, true);
        $filtersPath = $appPath . 'Config/Filters.php';
        copy(dirname(__DIR__, 3) . '/vendor/codeigniter4/framework/app/Config/Filters.php', $filtersPath);

        $editor = new FiltersConfigEditor($filtersPath);
        $editor->ensureAlias();
        $editor->ensureGlobalBefore();
        $editor->ensureGlobalAfter();
        file_put_contents($appPath . 'public/frankenphp-worker.php', '<?php');

        $config = config('Perfbase');
        $config->enabled = true;
        $config->apiKey = 'test-key';

        Services::injectMock('auth', new class {
            public function user(): object
            {
                return new class {
                    public function getId(): int
                    {
                        return 1;
                    }
                };
            }
        });

        try {
            $client = new RecordingPerfbaseClient();
            $provider = new TestPerfbaseClientProvider($client);
            $command = new class(service('logger'), new PerfbaseCommands(service('logger')), $provider, $filtersPath, $appPath . 'public/frankenphp-worker.php') extends PerfbaseDoctorCommand {
                public function __construct($logger, $commands, private TestPerfbaseClientProvider $provider, private string $filtersPath, private string $workerPath)
                {
                    parent::__construct($logger, $commands);
                }

                protected function clientProvider(): \Perfbase\CodeIgniter4\Runtime\PerfbaseClientProvider
                {
                    return $this->provider;
                }

                protected function filtersConfigPath(): string
                {
                    return $this->filtersPath;
                }

                protected function workerEntryPath(): string
                {
                    return $this->workerPath;
                }
            };

            $output = $this->captureOutput(static function () use ($command): void {
                self::assertSame(EXIT_SUCCESS, $command->run([]));
            });
            if ($output !== '') {
                self::assertStringContainsString('API key configured: yes', $output);
                self::assertStringContainsString('Shield detected: yes', $output);
                self::assertStringContainsString('Worker mode configured: yes', $output);
            }
        } finally {
            @unlink($appPath . 'public/frankenphp-worker.php');
            @unlink($filtersPath);
            @rmdir($appPath . 'public');
            @rmdir($appPath . 'Config');
            @rmdir($appPath);
        }
    }

    public function testDoctorCommandReportsFailureForBrokenSetup(): void
    {
        $appPath = sys_get_temp_dir() . '/perfbase-ci4-doctor-fail-' . bin2hex(random_bytes(4)) . '/';
        mkdir($appPath . 'Config', 0777, true);
        copy(dirname(__DIR__, 3) . '/vendor/codeigniter4/framework/app/Config/Filters.php', $appPath . 'Config/Filters.php');

        $config = config('Perfbase');
        $config->enabled = true;
        $config->apiKey = '';

        try {
            $client = new RecordingPerfbaseClient();
            $client->extensionAvailable = false;
            $provider = new TestPerfbaseClientProvider($client);
            $command = new class(service('logger'), new PerfbaseCommands(service('logger')), $provider, $appPath . 'Config/Filters.php') extends PerfbaseDoctorCommand {
                public function __construct($logger, $commands, private TestPerfbaseClientProvider $provider, private string $filtersPath)
                {
                    parent::__construct($logger, $commands);
                }

                protected function clientProvider(): \Perfbase\CodeIgniter4\Runtime\PerfbaseClientProvider
                {
                    return $this->provider;
                }

                protected function filtersConfigPath(): string
                {
                    return $this->filtersPath;
                }

                protected function workerEntryPath(): string
                {
                    return $this->filtersPath . '.worker';
                }
            };

            $output = $this->captureOutput(static function () use ($command): void {
                self::assertSame(EXIT_ERROR, $command->run([]));
            });
            if ($output !== '') {
                self::assertStringContainsString('API key configured: no', $output);
                self::assertStringContainsString('Extension available: no', $output);
            }
        } finally {
            @unlink($appPath . 'Config/Filters.php');
            @rmdir($appPath . 'Config');
            @rmdir($appPath);
        }
    }

    public function testDoctorCommandBaseHelpersUseDefaultServicesAndPaths(): void
    {
        $provider = new TestPerfbaseClientProvider(new RecordingPerfbaseClient());
        Services::injectMock('perfbaseClientProvider', $provider);

        $command = new class(service('logger'), new PerfbaseCommands(service('logger'))) extends PerfbaseDoctorCommand {
            public function clientProviderPublic(): \Perfbase\CodeIgniter4\Runtime\PerfbaseClientProvider
            {
                return $this->clientProvider();
            }

            public function filtersConfigPathPublic(): string
            {
                return $this->filtersConfigPath();
            }

            public function workerEntryPathPublic(): string
            {
                return $this->workerEntryPath();
            }
        };

        self::assertSame($provider, $command->clientProviderPublic());
        self::assertSame((string) constant('APPPATH') . 'Config/Filters.php', $command->filtersConfigPathPublic());
        self::assertSame((string) constant('ROOTPATH') . 'public/frankenphp-worker.php', $command->workerEntryPathPublic());
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
