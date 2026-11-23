<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Tests\Fixtures\Commands;

use CodeIgniter\CLI\BaseCommand;

class NestedCommand extends BaseCommand
{
    protected $group = 'Tests';
    protected $name = 'test:nested';
    protected $description = 'Command that invokes another command.';

    /**
     * @param array<int|string, string|null> $params
     */
    public function run(array $params): int
    {
        $this->call('test:ok');

        return 5;
    }
}
