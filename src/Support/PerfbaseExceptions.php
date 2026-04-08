<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Support;

use CodeIgniter\Debug\Exceptions;
use CodeIgniter\HTTP\RequestInterface;
use Perfbase\CodeIgniter4\Runtime\ActiveLifecycleRegistry;
use Throwable;

class PerfbaseExceptions extends Exceptions
{
    public function __construct($config, $request = null, $response = null)
    {
        $arguments = [$config];

        $constructor = (new \ReflectionClass(Exceptions::class))->getConstructor();
        if ($constructor !== null && $constructor->getNumberOfRequiredParameters() >= 3) {
            $arguments[] = $request ?? service('request');
            $arguments[] = $response ?? service('response');
        }

        if ($constructor !== null) {
            $constructor->invokeArgs($this, $arguments);
        }
    }

    public function exceptionHandler(Throwable $exception)
    {
        $this->finalizeHttpLifecycle($exception);
        $this->delegateExceptionHandler($exception);
    }

    protected function delegateExceptionHandler(Throwable $exception)
    {
        parent::exceptionHandler($exception);
    }

    private function finalizeHttpLifecycle(Throwable $exception): void
    {
        try {
            $request = $this->requestService();
            if (!$request instanceof RequestInterface) {
                return;
            }

            $registry = $this->lifecycleRegistryService();
            $lifecycle = $registry->getHttpLifecycle($request);
            if ($lifecycle === null) {
                return;
            }

            $lifecycle->setException($exception);
            [$statusCode] = $this->determineCodes($exception);
            $lifecycle->setResponseStatusCode($statusCode);
            $lifecycle->stopProfiling();
        } catch (Throwable $throwable) {
            try {
                $this->errorHandlerService()->handle($throwable, 'http_exception');
            } catch (Throwable) {
                // Never interfere with the framework's real exception handling path.
            }
        } finally {
            if (isset($registry, $request) && $request instanceof RequestInterface) {
                $registry->clearHttpLifecycle($request);
            }
        }
    }

    protected function requestService()
    {
        return service('request');
    }

    protected function lifecycleRegistryService(): ActiveLifecycleRegistry
    {
        return service('perfbaseLifecycleRegistry');
    }

    protected function errorHandlerService(): ErrorHandler
    {
        return service('perfbaseErrorHandler');
    }
}
