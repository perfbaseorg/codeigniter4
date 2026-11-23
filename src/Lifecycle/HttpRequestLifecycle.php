<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Lifecycle;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use Perfbase\CodeIgniter4\Config\Perfbase;
use Perfbase\CodeIgniter4\Contracts\UserResolverInterface;
use Perfbase\CodeIgniter4\Runtime\PerfbaseClientProvider;
use Perfbase\CodeIgniter4\Support\ErrorHandler;
use Perfbase\CodeIgniter4\Support\FilterMatcher;
use Perfbase\CodeIgniter4\Support\ShieldUserResolver;
use Perfbase\CodeIgniter4\Support\SpanNaming;
use Perfbase\CodeIgniter4\Support\UserIdentifier;
use Perfbase\CodeIgniter4\Support\UserResolverFactory;
use Perfbase\SDK\Utils\EnvironmentUtils;
use Perfbase\SDK\Utils\UrlSanitizer;

class HttpRequestLifecycle extends AbstractProfiler
{
    private RequestInterface $request;
    private ?string $routePattern;
    private ?string $controller;
    private ?string $method;
    private string $httpMethod;
    private ?int $responseStatusCode = null;

    public function __construct(
        RequestInterface $request,
        PerfbaseClientProvider $clientProvider,
        ErrorHandler $errorHandler,
        Perfbase $config,
        ?string $routePattern = null,
        ?string $controller = null,
        ?string $method = null
    ) {
        parent::__construct(
            SpanNaming::forHttp($request->getMethod(), $routePattern, $controller, $method, $this->resolveRequestPath($request)),
            $clientProvider,
            $errorHandler,
            $config
        );

        $this->request = $request;
        $this->routePattern = $routePattern;
        $this->controller = $controller;
        $this->method = $method;
        $this->httpMethod = strtoupper($request->getMethod());
    }

    public function setResponseStatusCode(int $statusCode): void
    {
        $this->responseStatusCode = $statusCode;
        $this->setAttribute('http_status_code', (string) $statusCode);
    }

    protected function shouldProfile(): bool
    {
        if (!$this->config->enabled) {
            return false;
        }

        return FilterMatcher::passesFilters(
            $this->getFilterComponents(),
            $this->config->includeHttp,
            $this->config->excludeHttp
        );
    }

    protected function setDefaultAttributes(): void
    {
        parent::setDefaultAttributes();

        $path = $this->getSanitizedPath();
        $actionIdentifier = $this->routePattern;
        if ($actionIdentifier === null || $actionIdentifier === '') {
            if ($this->controller !== null && $this->controller !== '' && $this->method !== null && $this->method !== '') {
                $actionIdentifier = sprintf('%s::%s', $this->controller, $this->method);
            } else {
                $actionIdentifier = $path;
            }
        }

        $this->setAttributes([
            'source' => 'http',
            'action' => sprintf('%s %s', $this->httpMethod, $actionIdentifier),
            'http_method' => $this->httpMethod,
            'http_url' => $this->buildHttpUrl($path),
            'user_ip' => (string) (EnvironmentUtils::getUserIp() ?? ''),
            'user_agent' => (string) (EnvironmentUtils::getUserUserAgent() ?? ''),
        ]);

        $userId = $this->resolveUserId();
        if ($userId !== null && $userId !== '') {
            $this->setAttribute('user_id', $userId);
        }
    }

    protected function shouldSubmitTrace(): bool
    {
        if ($this->responseStatusCode === null) {
            return true;
        }

        $allowedStatusCodes = is_array($this->config->profileHttpStatusCodes)
            ? $this->config->profileHttpStatusCodes
            : [];

        return in_array($this->responseStatusCode, $allowedStatusCodes, true);
    }

    /**
     * @return array<int, string>
     */
    private function getFilterComponents(): array
    {
        $path = $this->getSanitizedPath();
        $components = [
            $path,
            sprintf('%s %s', $this->httpMethod, $path),
        ];

        if ($this->routePattern !== null && $this->routePattern !== '') {
            $components[] = $this->routePattern;
            $components[] = sprintf('%s %s', $this->httpMethod, $this->routePattern);
        }

        if ($this->controller !== null && $this->controller !== '') {
            $components[] = $this->controller;
        }

        if ($this->method !== null && $this->method !== '') {
            $components[] = $this->method;
        }

        if ($this->controller !== null && $this->controller !== '' && $this->method !== null && $this->method !== '') {
            $components[] = sprintf('%s::%s', $this->controller, $this->method);
        }

        return array_values(array_unique($components));
    }

    private function getSanitizedPath(): string
    {
        $path = '/' . ltrim($this->resolveRequestPath($this->request), '/');

        return $path === '//' ? '/' : $path;
    }

    /**
     * Full request URL including the query string. Credential-looking query
     * values are redacted by the SDK sanitizer; `action` stays query-free.
     */
    private function buildHttpUrl(string $path): string
    {
        $uri = $this->request->getUri();
        $scheme = $uri->getScheme();
        $host = $uri->getHost();

        $url = $path;
        if ($scheme !== '' && $host !== '') {
            $authority = $host;
            $port = $uri->getPort();
            if ($port !== null && !$this->isDefaultPort($scheme, (int) $port)) {
                $authority .= ':' . $port;
            }

            $url = $scheme . '://' . $authority . $path;
        }

        $query = $this->resolveQueryString();
        if ($query !== '') {
            $url .= '?' . $query;
        }

        return UrlSanitizer::sanitize($url);
    }

    private function isDefaultPort(string $scheme, int $port): bool
    {
        $scheme = strtolower($scheme);

        return ($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443);
    }

    private function resolveQueryString(): string
    {
        // The request-bound URI, not $_SERVER['QUERY_STRING'], so the value
        // always belongs to this request (worker mode, sub-requests, tests).
        return ltrim($this->request->getUri()->getQuery(), '?');
    }

    private function resolveRequestPath(RequestInterface $request): string
    {
        $path = $request->getUri()->getPath();
        if ($path !== '') {
            return $path;
        }

        if ($request instanceof IncomingRequest) {
            $requestUri = $request->getServer('REQUEST_URI');
            if (is_string($requestUri) && $requestUri !== '') {
                $resolved = parse_url($requestUri, PHP_URL_PATH);
                if (is_string($resolved) && $resolved !== '') {
                    return $resolved;
                }
            }
        }

        return '/';
    }

    private function resolveUserId(): ?string
    {
        $configuredResolver = $this->resolveConfiguredUserResolver();
        if ($configuredResolver !== null) {
            return UserIdentifier::fromValue($configuredResolver->resolve($this->request));
        }

        $shieldResolver = new ShieldUserResolver();
        if ($shieldResolver->isAvailable()) {
            return UserIdentifier::fromValue($shieldResolver->resolve($this->request));
        }

        if (!$this->request instanceof IncomingRequest || !property_exists($this->request, 'user')) {
            return null;
        }

        /** @var mixed $user */
        $user = $this->request->user;

        return UserIdentifier::fromValue($user);
    }

    private function resolveConfiguredUserResolver(): ?UserResolverInterface
    {
        $factory = new UserResolverFactory();

        return $factory->make($this->config->userResolver);
    }
}
