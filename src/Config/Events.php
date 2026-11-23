<?php

declare(strict_types=1);

use CodeIgniter\Events\Events;

Events::on('perfbase_pre_command', static function (...$args): void {
    $registry = service('perfbaseLifecycleRegistry');
    $provider = service('perfbaseClientProvider');
    $errorHandler = service('perfbaseErrorHandler');
    $config = config('Perfbase');

    // Only the outermost command is profiled; nested commands run inside its trace.
    if ($registry->enterConsoleCommand() > 1) {
        return;
    }

    try {
        $commandName = Perfbase\CodeIgniter4\Support\SpanNaming::detectConsoleCommand($args);
        $lifecycle = new Perfbase\CodeIgniter4\Lifecycle\ConsoleCommandLifecycle(
            $commandName,
            $provider,
            $errorHandler,
            $config
        );

        $registry->clearActiveConsoleLifecycle();
        $registry->setActiveConsoleLifecycle($lifecycle);
        $lifecycle->startProfiling();
    } catch (\Throwable $throwable) {
        $errorHandler->handle($throwable, 'perfbase_pre_command');
    }
});

Events::on('perfbase_post_command', static function (...$args): void {
    $registry = service('perfbaseLifecycleRegistry');
    $errorHandler = service('perfbaseErrorHandler');

    if ($registry->leaveConsoleCommand() > 0) {
        return;
    }

    try {
        $lifecycle = $registry->getActiveConsoleLifecycle();
        if ($lifecycle === null) {
            return;
        }

        $lifecycle->setExitCode(Perfbase\CodeIgniter4\Support\SpanNaming::detectExitCode($args));
        $exception = Perfbase\CodeIgniter4\Support\SpanNaming::detectThrowable($args);
        if ($exception !== null) {
            $lifecycle->setException($exception);
        }

        $lifecycle->stopProfiling();
    } catch (\Throwable $throwable) {
        $errorHandler->handle($throwable, 'perfbase_post_command');
    } finally {
        $registry->clearActiveConsoleLifecycle();
    }
});
