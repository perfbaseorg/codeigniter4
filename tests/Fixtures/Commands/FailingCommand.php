<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Tests\Fixtures\Commands;

use CodeIgniter\CLI\BaseCommand;

class FailingCommand extends BaseCommand
{
    protected $group = 'Tests';
    protected $name = 'test:fail';
    protected $description = 'Failing test command.';

    /**
     * @param array<int|string, string|null> $params
     */
    public function run(array $params): int
    {
        throw new \RuntimeException('cli boom');
    }
}
