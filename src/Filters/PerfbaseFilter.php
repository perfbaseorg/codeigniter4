<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Perfbase\CodeIgniter4\Lifecycle\HttpRequestLifecycle;

class PerfbaseFilter implements FilterInterface
{
    /**
     * @param array<string>|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $registry = service('perfbaseLifecycleRegistry');
        $provider = service('perfbaseClientProvider');
        $errorHandler = service('perfbaseErrorHandler');
        $config = config('Perfbase');

        try {
            $router = service('router');
            $routePattern = null;
            $controller = null;
            $method = null;

            if (is_object($router)) {
                if (method_exists($router, 'getMatchedRoute')) {
                    $matchedRoute = $router->getMatchedRoute();
                    if (is_array($matchedRoute) && isset($matchedRoute[0]) && is_string($matchedRoute[0])) {
                        $routePattern = $matchedRoute[0];
                    }
                }

                if (method_exists($router, 'controllerName')) {
                    $resolvedController = $router->controllerName();
                    $controller = is_string($resolvedController) ? $resolvedController : null;
                }

                if (method_exists($router, 'methodName')) {
                    $resolvedMethod = $router->methodName();
                    $method = is_string($resolvedMethod) ? $resolvedMethod : null;
                }
            }

            $lifecycle = new HttpRequestLifecycle(
                $request,
                $provider,
                $errorHandler,
                $config,
                $routePattern,
                $controller,
                $method
            );

            $registry->setHttpLifecycle($request, $lifecycle);
            $lifecycle->startProfiling();
        } catch (\Throwable $throwable) {
            $errorHandler->handle($throwable, 'http_before');
        }

        return null;
    }

    /**
     * @param array<string>|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $registry = service('perfbaseLifecycleRegistry');
        $errorHandler = service('perfbaseErrorHandler');

        try {
            $lifecycle = $registry->getHttpLifecycle($request);
            if (!$lifecycle instanceof HttpRequestLifecycle) {
                return $response;
            }

            $lifecycle->setResponseStatusCode($response->getStatusCode());
            $lifecycle->stopProfiling();
        } catch (\Throwable $throwable) {
            $errorHandler->handle($throwable, 'http_after');
        } finally {
            $registry->clearHttpLifecycle($request);
        }

        return $response;
    }
}
