<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Perfbase\CodeIgniter4\Publishers\PerfbasePublisher;
use Perfbase\CodeIgniter4\Support\FiltersConfigEditor;

class PerfbaseInstallCommand extends BaseCommand
{
    protected $group = 'Perfbase';
    protected $name = 'perfbase:install';
    protected $description = 'Publish Perfbase config and register the Perfbase filter in CodeIgniter.';
    protected $usage = 'perfbase:install [--alias-only]';
    protected $options = [
        '--alias-only' => 'Only register the filter alias. Do not add Perfbase to global before/after filters.',
    ];

    public function run(array $params)
    {
        $aliasOnly = array_key_exists('alias-only', $params) || CLI::getOption('alias-only');
        try {
            $publisher = $this->publisher();
            if (!$publisher->publish()) {
                CLI::error('Unable to publish the Perfbase config stub.');

                foreach ($publisher->getErrors() as $file => $exception) {
                    CLI::write($file);
                    CLI::error($exception->getMessage());
                }

                return EXIT_ERROR;
            }

            $editor = new FiltersConfigEditor($this->filtersConfigPath());

            $aliasChanged = $editor->ensureAlias();
            $beforeChanged = false;
            $afterChanged = false;

            if (!$aliasOnly) {
                $beforeChanged = $editor->ensureGlobalBefore();
                $afterChanged = $editor->ensureGlobalAfter();
            }

            CLI::write('Perfbase install completed.', 'green');
            CLI::write($aliasChanged ? 'Added perfbase filter alias.' : 'Perfbase filter alias already present.');

            if ($aliasOnly) {
                CLI::write('Skipped global filter registration because --alias-only was used.');
            } else {
                CLI::write($beforeChanged ? 'Added perfbase to global before filters.' : 'Perfbase already present in global before filters.');
                CLI::write($afterChanged ? 'Added perfbase to global after filters.' : 'Perfbase already present in global after filters.');
            }

            CLI::newLine();
            CLI::write('Next steps:', 'yellow');
            CLI::write('1. Set PERFBASE_API_KEY in your environment or app/Config/Perfbase.php.');
            CLI::write('2. Install the Perfbase PHP extension.');
            CLI::write('3. Run `spark perfbase:doctor` to verify the setup.');

            return EXIT_SUCCESS;
        } catch (\Throwable $throwable) {
            CLI::error('Perfbase install failed.');
            CLI::error($throwable->getMessage());

            return EXIT_ERROR;
        }
    }

    protected function publisher(): PerfbasePublisher
    {
        return new PerfbasePublisher();
    }

    protected function filtersConfigPath(): string
    {
        return $this->appPath() . 'Config/Filters.php';
    }

    private function appPath(): string
    {
        return defined('APPPATH') ? (string) constant('APPPATH') : '';
    }
}
