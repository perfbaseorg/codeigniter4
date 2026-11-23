<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Tests\Unit\Lifecycle;

use Perfbase\CodeIgniter4\Config\Perfbase;
use Perfbase\CodeIgniter4\Support\ErrorHandler;
use Perfbase\CodeIgniter4\Tests\Fixtures\RecordingPerfbaseClient;
use Perfbase\CodeIgniter4\Tests\Fixtures\TestPerfbaseClientProvider;
use Perfbase\CodeIgniter4\Tests\Fixtures\TestProfiler;
use Perfbase\CodeIgniter4\Runtime\PerfbaseClientProvider;
use Perfbase\SDK\Exception\PerfbaseException;
use Perfbase\SDK\SubmitResult;
use PHPUnit\Framework\TestCase;

final class AbstractProfilerTest extends TestCase
{
    public function testStartAndStopProfilingIsIdempotent(): void
    {
        $client = new RecordingPerfbaseClient();
        $config = $this->config();
        $profiler = new TestProfiler(new TestPerfbaseClientProvider($client), new ErrorHandler(false, false), $config);

        $profiler->startProfiling();
        $profiler->startProfiling();
        $profiler->stopProfiling();

        self::assertSame(['test'], $client->startedSpans);
        self::assertSame(1, $client->submitCalls);
        self::assertSame('testing', $client->attributes['environment']);
    }

    public function testRespectsSampleRateAndShouldProfile(): void
    {
        $client = new RecordingPerfbaseClient();
        $config = $this->config();
        $config->sampleRate = 0.0;
        $profiler = new TestProfiler(new TestPerfbaseClientProvider($client), new ErrorHandler(false, false), $config);

        $profiler->startProfiling();
        self::assertFalse($profiler->hasStarted());

        $config = $this->config();
        $profiler = new TestProfiler(new TestPerfbaseClientProvider($client), new ErrorHandler(false, false), $config);
        $profiler->setShouldProfile(false);
        $profiler->startProfiling();
        self::assertFalse($profiler->hasStarted());
    }

    public function testSubmitFailureDoesNotThrowInProductionMode(): void
    {
        $client = new RecordingPerfbaseClient();
        $client->submitResult = SubmitResult::retryableFailure(500, 'temporary failure');
        $profiler = new TestProfiler(new TestPerfbaseClientProvider($client), new ErrorHandler(false, false), $this->config());

        $profiler->startProfiling();
        $profiler->stopProfiling();

        self::assertSame(1, $client->submitCalls);
    }

    public function testSubmitExceptionIsSwallowedInProductionMode(): void
    {
        $client = new RecordingPerfbaseClient();
        $client->submitException = new PerfbaseException('Extension returned empty trace data');
        $profiler = new TestProfiler(new TestPerfbaseClientProvider($client), new ErrorHandler(false, false), $this->config());

        $profiler->startProfiling();
        $profiler->stopProfiling();

        self::assertSame(1, $client->submitCalls);
        self::assertFalse($profiler->hasStarted());
    }

    public function testDisabledProfilerDoesNotBuildTheSdkClient(): void
    {
        $config = $this->config();
        $config->enabled = false;
        $config->apiKey = '';

        $factoryCalls = 0;
        $provider = new PerfbaseClientProvider(
            $config,
            new ErrorHandler(true, true),
            static function () use (&$factoryCalls): RecordingPerfbaseClient {
                $factoryCalls++;

                return new RecordingPerfbaseClient();
            }
        );
        $profiler = new class($provider, new ErrorHandler(true, true), $config) extends TestProfiler {
            protected function shouldProfile(): bool
            {
                return $this->config->enabled;
            }
        };

        // Debug mode rethrows: building the SDK config without an API key would throw here.
        $profiler->startProfiling();

        self::assertFalse($profiler->hasStarted());
        self::assertSame(0, $factoryCalls);
    }

    public function testDebugModeRethrowsSubmitExceptions(): void
    {
        $client = new RecordingPerfbaseClient();
        $client->submitException = new \RuntimeException('submit boom');
        $profiler = new TestProfiler(new TestPerfbaseClientProvider($client), new ErrorHandler(true, true), $this->config());

        $profiler->startProfiling();

        $this->expectException(\RuntimeException::class);
        $profiler->stopProfiling();
    }

    public function testStopProfilingSkipsSubmissionWhenSpanCannotStop(): void
    {
        $client = new RecordingPerfbaseClient();
        $client->stopResult = false;
        $profiler = new TestProfiler(new TestPerfbaseClientProvider($client), new ErrorHandler(false, false), $this->config());

        $profiler->startProfiling();
        $profiler->stopProfiling();

        self::assertSame(0, $client->submitCalls);
        self::assertFalse($profiler->hasStarted());
    }

    public function testStopProfilingResetsWithoutSubmittingWhenTraceShouldBeDropped(): void
    {
        $client = new RecordingPerfbaseClient();
        $profiler = new class(new TestPerfbaseClientProvider($client), new ErrorHandler(false, false), $this->config()) extends TestProfiler {
            protected function shouldSubmitTrace(): bool
            {
                return false;
            }
        };

        $profiler->startProfiling();
        $profiler->stopProfiling();

        self::assertSame(0, $client->submitCalls);
        self::assertSame(1, $client->resetCalls);
        self::assertFalse($profiler->hasStarted());
    }

    public function testSampleRateValidationAndDefaultAttributesHelpers(): void
    {
        $config = $this->config();
        $config->sampleRate = 1.5;
        $profiler = new TestProfiler(new TestPerfbaseClientProvider(new RecordingPerfbaseClient()), new ErrorHandler(true, true), $config);

        try {
            $this->expectException(\RuntimeException::class);
            $profiler->passesSampleRateCheckPublic();
        } finally {
            $defaultsProfiler = new TestProfiler(
                new TestPerfbaseClientProvider(new RecordingPerfbaseClient()),
                new ErrorHandler(false, false),
                $this->config()
            );
            $defaultsProfiler->setDefaultAttributesPublic();
        }
    }

    private function config(): Perfbase
    {
        $config = new Perfbase();
        $config->enabled = true;
        $config->apiKey = 'test-key';
        $config->sampleRate = 1.0;

        return $config;
    }
}
