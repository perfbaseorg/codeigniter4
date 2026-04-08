<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Publishers;

use CodeIgniter\Publisher\Publisher;

class PerfbasePublisher extends Publisher
{
    protected $source = __DIR__ . '/../../app/';
    protected $destination = '';

    public function __construct(?string $source = null, ?string $destination = null)
    {
        parent::__construct($source, $destination ?? $this->appPath());
    }

    public function publish(): bool
    {
        return $this->addPath('Config/Perfbase.php', false)->merge(false);
    }

    private function appPath(): string
    {
        return defined('APPPATH') ? (string) constant('APPPATH') : '';
    }
}
