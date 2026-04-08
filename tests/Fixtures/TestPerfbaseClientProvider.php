<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Tests\Fixtures;

use Perfbase\CodeIgniter4\Config\Perfbase;
use Perfbase\CodeIgniter4\Runtime\PerfbaseClientProvider;
use Perfbase\CodeIgniter4\Support\ErrorHandler;
use Perfbase\SDK\Perfbase as PerfbaseClient;

class TestPerfbaseClientProvider extends PerfbaseClientProvider
{
    public function __construct(private ?PerfbaseClient $client = null)
    {
    }

    public function getClient(): ?PerfbaseClient
    {
        return $this->client;
    }
}
