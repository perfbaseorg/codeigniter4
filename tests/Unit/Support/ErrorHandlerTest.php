<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Tests\Unit\Support;

use Perfbase\CodeIgniter4\Support\ErrorHandler;
use PHPUnit\Framework\TestCase;

final class ErrorHandlerTest extends TestCase
{
    public function testDebugModeRethrows(): void
    {
        $handler = new ErrorHandler(true, true);

        $this->expectException(\RuntimeException::class);
        $handler->handle(new \RuntimeException('boom'), 'test');
    }

    public function testProductionModeLogsWhenEnabled(): void
    {
        $logFile = tempnam(sys_get_temp_dir(), 'perfbase-ci4-log-');
        self::assertIsString($logFile);

        $previous = ini_get('error_log');
        ini_set('error_log', $logFile);

        try {
            $handler = new ErrorHandler(false, true);
            $handler->handle(new \RuntimeException('logged failure'), 'test');

            $contents = file_get_contents($logFile);
            self::assertIsString($contents);
            self::assertStringContainsString('Perfbase profiling error in test: logged failure', $contents);
        } finally {
            ini_set('error_log', $previous === false ? '' : (string) $previous);
            @unlink($logFile);
        }
    }

    public function testProductionModeSilencesWhenLoggingDisabled(): void
    {
        $logFile = tempnam(sys_get_temp_dir(), 'perfbase-ci4-silent-');
        self::assertIsString($logFile);

        $previous = ini_get('error_log');
        ini_set('error_log', $logFile);

        try {
            $handler = new ErrorHandler(false, false);
            $handler->handle(new \RuntimeException('hidden'), 'test');

            $contents = file_get_contents($logFile);
            self::assertIsString($contents);
            self::assertSame('', $contents);
        } finally {
            ini_set('error_log', $previous === false ? '' : (string) $previous);
            @unlink($logFile);
        }
    }
}
