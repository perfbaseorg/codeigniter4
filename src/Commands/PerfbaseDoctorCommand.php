<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Perfbase\CodeIgniter4\Runtime\PerfbaseClientProvider;
use Perfbase\CodeIgniter4\Support\FiltersConfigEditor;
use Perfbase\CodeIgniter4\Support\ShieldUserResolver;

class PerfbaseDoctorCommand extends BaseCommand
{
    protected $group = 'Perfbase';
    protected $name = 'perfbase:doctor';
    protected $description = 'Report Perfbase configuration and runtime health for a CodeIgniter application.';

    public function run(array $params)
    {
        $config = config('Perfbase');
        $provider = $this->clientProvider();
        $client = $provider->getClient();
        $editor = new FiltersConfigEditor($this->filtersConfigPath());
        $shieldResolver = new ShieldUserResolver();

        $checks = [
            'enabled' => $config->enabled,
            'api_key' => trim($config->apiKey) !== '',
            'sdk_boot' => $client !== null,
            'extension' => $client?->isExtensionAvailable() ?? false,
            'filter_alias' => $editor->hasAlias() || isset(config('Filters')->aliases['perfbase']),
            'global_before' => $editor->hasGlobalBefore(),
            'global_after' => $editor->hasGlobalAfter(),
            'shield' => $shieldResolver->isAvailable(),
            'worker_active' => defined('FRANKENPHP_WORKER') && (bool) FRANKENPHP_WORKER,
            'worker_configured' => is_file($this->workerEntryPath()),
        ];

        CLI::write('Perfbase doctor report', 'yellow');
        CLI::newLine();

        $this->writeCheck('Enabled', $checks['enabled']);
        $this->writeCheck('API key configured', $checks['api_key']);
        $this->writeCheck('SDK boot successful', $checks['sdk_boot']);
        $this->writeCheck('Extension available', $checks['extension']);
        $this->writeCheck('Filter alias configured', $checks['filter_alias']);
        $this->writeCheck('Global before filter configured', $checks['global_before']);
        $this->writeCheck('Global after filter configured', $checks['global_after']);
        $this->writeCheck('Shield detected', $checks['shield']);
        $this->writeCheck('Worker mode active', $checks['worker_active']);
        $this->writeCheck('Worker mode configured', $checks['worker_configured']);

        if ($checks['worker_active'] || $checks['worker_configured']) {
            CLI::newLine();
            CLI::write('Worker mode note: restart workers after changing Perfbase config or deployment artifacts.');
        }

        $failed = !$checks['api_key']
            || !$checks['sdk_boot']
            || !$checks['extension']
            || !$checks['filter_alias']
            || !$checks['global_before']
            || !$checks['global_after'];

        return $failed ? EXIT_ERROR : EXIT_SUCCESS;
    }

    protected function clientProvider(): PerfbaseClientProvider
    {
        return service('perfbaseClientProvider');
    }

    protected function filtersConfigPath(): string
    {
        return $this->appPath() . 'Config/Filters.php';
    }

    protected function workerEntryPath(): string
    {
        return $this->rootPath() . 'public/frankenphp-worker.php';
    }

    private function writeCheck(string $label, bool $passed): void
    {
        CLI::write(sprintf('%s: %s', $label, $passed ? 'yes' : 'no'), $passed ? 'green' : 'red');
    }

    private function appPath(): string
    {
        return defined('APPPATH') ? (string) constant('APPPATH') : '';
    }

    private function rootPath(): string
    {
        return defined('ROOTPATH') ? (string) constant('ROOTPATH') : '';
    }
}
