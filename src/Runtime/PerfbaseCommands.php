<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Runtime;

use CodeIgniter\CLI\Commands;
use CodeIgniter\Events\Events;

class PerfbaseCommands extends Commands
{
    /**
     * @param array<int|string, string|null> $params
     */
    public function run(string $command, array $params)
    {
        if (!$this->verifyCommand($command, $this->commands)) {
            return EXIT_ERROR;
        }

        $className = $this->commands[$command]['class'];
        $class = new $className($this->logger, $this);

        // Preserve CodeIgniter's native command event contract exactly.
        Events::trigger('pre_command');
        Events::trigger('perfbase_pre_command', $command, $params, $class);

        try {
            $exit = (int) $class->run($params);
            Events::trigger('post_command');
            Events::trigger('perfbase_post_command', $command, $exit, null, $class);

            return $exit;
        } catch (\Throwable $throwable) {
            Events::trigger('perfbase_post_command', $command, EXIT_ERROR, $throwable, $class);
            throw $throwable;
        }
    }
}
