<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Tests\Unit\Support;

use CodeIgniter\Config\Services;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use Perfbase\CodeIgniter4\Tests\Support\CodeIgniterTestCase;
use Perfbase\CodeIgniter4\Config\Perfbase;
use Perfbase\CodeIgniter4\Lifecycle\HttpRequestLifecycle;
use Perfbase\CodeIgniter4\Runtime\ActiveLifecycleRegistry;
use Perfbase\CodeIgniter4\Support\ErrorHandler;
use Perfbase\CodeIgniter4\Support\PerfbaseExceptions;
use Perfbase\CodeIgniter4\Tests\Fixtures\RecordingPerfbaseClient;
use Perfbase\CodeIgniter4\Tests\Fixtures\TestPerfbaseClientProvider;
use Perfbase\CodeIgniter4\Tests\Fixtures\TestPerfbaseExceptions;

final class PerfbaseExceptionsTest extends CodeIgniterTestCase
{
    protected function tearDown(): void
    {
        Services::reset();

        parent::tearDown();
    }

    public function testExceptionHandlerFinalizesActiveLifecycle(): void
    {
        $client = new RecordingPerfbaseClient();
        $request = new IncomingRequest(config('App'), new URI('https://example.com/articles/42'), null, new UserAgent());
        $request->setMethod('GET');

        $registry = new ActiveLifecycleRegistry();
        Services::injectMock('request', $request);
        Services::injectMock('perfbaseLifecycleRegistry', $registry);
        Services::injectMock('perfbaseErrorHandler', new ErrorHandler(false, false));

        $config = new Perfbase();
        $config->enabled = true;
        $config->apiKey = 'test-key';
        $config->sampleRate = 1.0;

        $lifecycle = new HttpRequestLifecycle(
            $request,
            new TestPerfbaseClientProvider($client),
            new ErrorHandler(false, false),
            $config,
            'articles/(:num)'
        );

        $lifecycle->startProfiling();
        $registry->setHttpLifecycle($request, $lifecycle);

        $exceptions = new TestPerfbaseExceptions(config('Exceptions'));
        $throwable = new \RuntimeException('uncaught boom');
        $exceptions->exceptionHandler($throwable);

        self::assertSame($throwable, $exceptions->delegatedException);
        self::assertSame('uncaught boom', $client->attributes['exception']);
        self::assertSame('500', $client->attributes['http_status_code']);
        self::assertSame(1, $client->submitCalls);
        self::assertSame(0, $client->resetCalls);
        self::assertNull($registry->getHttpLifecycle($request));
    }

    public function testExceptionHandlerDropsDisallowedErrorStatusWhenConfigured(): void
    {
        $client = new RecordingPerfbaseClient();
        $request = new IncomingRequest(config('App'), new URI('https://example.com/articles/42'), null, new UserAgent());
        $request->setMethod('GET');

        $registry = new ActiveLifecycleRegistry();
        Services::injectMock('request', $request);
        Services::injectMock('perfbaseLifecycleRegistry', $registry);
        Services::injectMock('perfbaseErrorHandler', new ErrorHandler(false, false));

        $config = new Perfbase();
        $config->enabled = true;
        $config->apiKey = 'test-key';
        $config->sampleRate = 1.0;
        $config->profileHttpStatusCodes = [200, 404];

        $lifecycle = new HttpRequestLifecycle(
            $request,
            new TestPerfbaseClientProvider($client),
            new ErrorHandler(false, false),
            $config,
            'articles/(:num)'
        );

        $lifecycle->startProfiling();
        $registry->setHttpLifecycle($request, $lifecycle);

        $exceptions = new TestPerfbaseExceptions(config('Exceptions'));
        $throwable = new \RuntimeException('uncaught boom');
        $exceptions->exceptionHandler($throwable);

        self::assertSame($throwable, $exceptions->delegatedException);
        self::assertSame('500', $client->attributes['http_status_code']);
        self::assertSame(0, $client->submitCalls);
        self::assertSame(1, $client->resetCalls);
        self::assertNull($registry->getHttpLifecycle($request));
    }

    public function testExceptionHandlerNoOpsWithoutActiveLifecycle(): void
    {
        $request = new IncomingRequest(config('App'), new URI('https://example.com/articles/42'), null, new UserAgent());
        Services::injectMock('request', $request);
        Services::injectMock('perfbaseLifecycleRegistry', new ActiveLifecycleRegistry());
        Services::injectMock('perfbaseErrorHandler', new ErrorHandler(false, false));

        $exceptions = new TestPerfbaseExceptions(config('Exceptions'));
        $throwable = new \RuntimeException('uncaught boom');
        $exceptions->exceptionHandler($throwable);

        self::assertSame($throwable, $exceptions->delegatedException);
    }

    public function testExceptionHandlerLogsLifecycleFinalizationFailuresAndStillClearsRegistry(): void
    {
        $request = new IncomingRequest(config('App'), new URI('https://example.com/articles/42'), null, new UserAgent());
        $request->setMethod('GET');

        $registry = new ActiveLifecycleRegistry();
        Services::injectMock('request', $request);
        Services::injectMock('perfbaseLifecycleRegistry', $registry);

        $logFile = tempnam(sys_get_temp_dir(), 'perfbase-ci4-exception-log-');
        self::assertIsString($logFile);
        $previous = ini_get('error_log');
        ini_set('error_log', $logFile);

        try {
            Services::injectMock('perfbaseErrorHandler', new ErrorHandler(false, true));

            $config = new Perfbase();
            $config->enabled = true;
            $config->apiKey = 'test-key';
            $config->sampleRate = 1.0;

            $lifecycle = new class(
                $request,
                new TestPerfbaseClientProvider(new RecordingPerfbaseClient()),
                new ErrorHandler(false, false),
                $config,
                'articles/(:num)'
            ) extends HttpRequestLifecycle {
                public function stopProfiling(): void
                {
                    throw new \RuntimeException('stop failure');
                }
            };

            $registry->setHttpLifecycle($request, $lifecycle);

            $exceptions = new TestPerfbaseExceptions(config('Exceptions'));
            $throwable = new \RuntimeException('uncaught boom');
            $exceptions->exceptionHandler($throwable);

            $contents = file_get_contents($logFile);
            self::assertIsString($contents);
            self::assertStringContainsString('Perfbase profiling error in http_exception: stop failure', $contents);
            self::assertSame($throwable, $exceptions->delegatedException);
            self::assertNull($registry->getHttpLifecycle($request));
        } finally {
            ini_set('error_log', $previous === false ? '' : (string) $previous);
            @unlink($logFile);
        }
    }

    public function testExceptionHandlerStillDelegatesWhenPerfbaseServiceLookupFails(): void
    {
        $exceptions = new class(config('Exceptions')) extends PerfbaseExceptions {
            public ?\Throwable $delegatedException = null;

            protected function requestService()
            {
                throw new \RuntimeException('service lookup failed');
            }

            protected function errorHandlerService(): ErrorHandler
            {
                return new ErrorHandler(false, false);
            }

            protected function delegateExceptionHandler(\Throwable $exception)
            {
                $this->delegatedException = $exception;
            }
        };

        $throwable = new \RuntimeException('uncaught boom');
        $exceptions->exceptionHandler($throwable);

        self::assertSame($throwable, $exceptions->delegatedException);
    }
}
