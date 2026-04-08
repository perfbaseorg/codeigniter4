<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Tests\Unit\Filters;

use CodeIgniter\Config\Services;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use Perfbase\CodeIgniter4\Tests\Support\CodeIgniterTestCase;
use Perfbase\CodeIgniter4\Filters\PerfbaseFilter;
use Perfbase\CodeIgniter4\Runtime\ActiveLifecycleRegistry;
use Perfbase\CodeIgniter4\Tests\Fixtures\RecordingPerfbaseClient;
use Perfbase\CodeIgniter4\Tests\Fixtures\TestPerfbaseClientProvider;

final class PerfbaseFilterTest extends CodeIgniterTestCase
{
    private RecordingPerfbaseClient $client;
    private ActiveLifecycleRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new RecordingPerfbaseClient();
        $this->registry = new ActiveLifecycleRegistry();

        Services::injectMock('perfbaseClientProvider', new TestPerfbaseClientProvider($this->client));
        Services::injectMock('perfbaseLifecycleRegistry', $this->registry);

        $config = config('Perfbase');
        $config->enabled = true;
        $config->apiKey = 'test-key';
        $config->sampleRate = 1.0;
    }

    public function testBeforeAndAfterProfileRequest(): void
    {
        Services::injectMock('router', new class {
            public function getMatchedRoute(): array
            {
                return ['articles/([0-9]+)'];
            }

            public function controllerName(): string
            {
                return 'App\\Controllers\\Articles';
            }

            public function methodName(): string
            {
                return 'show';
            }
        });

        $request = $this->makeRequest('http://localhost/articles/42?secret=1');
        $request->setMethod('GET');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(202);

        $filter = new PerfbaseFilter();

        self::assertNull($filter->before($request));
        self::assertNotNull($this->registry->getHttpLifecycle($request));
        self::assertSame($response, $filter->after($request, $response));
        self::assertNull($this->registry->getHttpLifecycle($request));
        self::assertSame(['http.GET./articles/([0-9]+)'], $this->client->startedSpans);
        self::assertSame('202', $this->client->attributes['http_status_code']);
        self::assertSame(1, $this->client->submitCalls);
    }

    public function testAfterReturnsResponseWhenNoLifecycleExists(): void
    {
        $request = $this->makeRequest('http://localhost/articles/42');
        $request->setMethod('GET');

        $response = $this->createMock(ResponseInterface::class);
        $filter = new PerfbaseFilter();

        self::assertSame($response, $filter->after($request, $response));
    }

    private function makeRequest(string $uri): IncomingRequest
    {
        $requestUri = parse_url($uri, PHP_URL_PATH) ?? '/';
        $query = parse_url($uri, PHP_URL_QUERY);
        if (is_string($query) && $query !== '') {
            $requestUri .= '?' . $query;
        }

        $_SERVER['REQUEST_URI'] = $requestUri;

        return new IncomingRequest(config('App'), new URI($uri), null, new UserAgent());
    }
}
