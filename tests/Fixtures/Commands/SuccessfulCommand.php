<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Tests\Fixtures\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SuccessfulCommand extends BaseCommand
{
    protected $group = 'Tests';
    protected $name = 'test:ok';
    protected $description = 'Successful test command.';

    /**
     * @param array<int|string, string|null> $params
     */
    public function run(array $params): int
    {
        CLI::write('ok');

        return 17;
    }
}
