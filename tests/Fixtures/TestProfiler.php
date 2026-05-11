<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Tests\Fixtures;

use Perfbase\CodeIgniter4\Config\Perfbase;
use Perfbase\CodeIgniter4\Lifecycle\AbstractProfiler;
use Perfbase\CodeIgniter4\Runtime\PerfbaseClientProvider;
use Perfbase\CodeIgniter4\Support\ErrorHandler;

class TestProfiler extends AbstractProfiler
{
    private bool $shouldProfile = true;

    public function __construct(
        PerfbaseClientProvider $clientProvider,
        ErrorHandler $errorHandler,
        Perfbase $config
    ) {
        parent::__construct('test', $clientProvider, $errorHandler, $config);
    }

    public function setShouldProfile(bool $shouldProfile): void
    {
        $this->shouldProfile = $shouldProfile;
    }

    public function passesSampleRateCheckPublic(): bool
    {
        return $this->passesSampleRateCheck();
    }

    public function setDefaultAttributesPublic(): void
    {
        $this->setDefaultAttributes();
    }

    protected function shouldProfile(): bool
    {
        return $this->shouldProfile;
    }
}
