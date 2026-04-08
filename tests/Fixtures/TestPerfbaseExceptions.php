<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Tests\Fixtures;

use Perfbase\CodeIgniter4\Support\PerfbaseExceptions;
use Throwable;

final class TestPerfbaseExceptions extends PerfbaseExceptions
{
    public ?Throwable $delegatedException = null;

    protected function delegateExceptionHandler(Throwable $exception)
    {
        $this->delegatedException = $exception;
    }
}
