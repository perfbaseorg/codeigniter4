<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Lifecycle;

use Perfbase\CodeIgniter4\Config\Perfbase;
use Perfbase\CodeIgniter4\Runtime\PerfbaseClientProvider;
use Perfbase\CodeIgniter4\Support\ErrorHandler;
use Perfbase\CodeIgniter4\Support\FilterMatcher;
use Perfbase\CodeIgniter4\Support\SpanNaming;

class ConsoleCommandLifecycle extends AbstractProfiler
{
    private string $command;

    public function __construct(
        string $command,
        PerfbaseClientProvider $clientProvider,
        ErrorHandler $errorHandler,
        Perfbase $config
    ) {
        parent::__construct(
            SpanNaming::forConsole($command),
            $clientProvider,
            $errorHandler,
            $config
        );

        $this->command = $command;
    }

    protected function shouldProfile(): bool
    {
        if (!$this->config->enabled) {
            return false;
        }

        return FilterMatcher::passesFilters(
            [$this->command],
            $this->config->includeConsole,
            $this->config->excludeConsole
        );
    }

    protected function setDefaultAttributes(): void
    {
        parent::setDefaultAttributes();

        $this->setAttributes([
            'source' => 'console',
            'action' => $this->command,
        ]);
    }
}
