<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Tests\Unit\Lifecycle;

use CodeIgniter\Config\Services;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use Perfbase\CodeIgniter4\Tests\Support\CodeIgniterTestCase;
use Perfbase\CodeIgniter4\Config\Perfbase;
use Perfbase\CodeIgniter4\Lifecycle\HttpRequestLifecycle;
use Perfbase\CodeIgniter4\Support\ErrorHandler;
use Perfbase\CodeIgniter4\Tests\Fixtures\FakeUserResolver;
use Perfbase\CodeIgniter4\Tests\Fixtures\RecordingPerfbaseClient;
use Perfbase\CodeIgniter4\Tests\Fixtures\TestPerfbaseClientProvider;

final class HttpRequestLifecycleTest extends CodeIgniterTestCase
{
    protected function tearDown(): void
    {
        Services::reset();

        parent::tearDown();
    }

    public function testHttpLifecycleProfilesAndSetsAttributes(): void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

        $client = new RecordingPerfbaseClient();
        $request = $this->makeRequest('https://example.com/articles/42?secret=1');
        $request->setMethod('GET');

        $lifecycle = new HttpRequestLifecycle(
            $request,
            new TestPerfbaseClientProvider($client),
            new ErrorHandler(false, false),
            $this->config(),
            'articles/(:num)',
            'App\\Controllers\\Articles',
            'show'
        );

        $lifecycle->startProfiling();
        $lifecycle->setResponseStatusCode(201);
        $lifecycle->stopProfiling();

        self::assertSame(['http'], $client->startedSpans);
        self::assertSame('GET articles/(:num)', $client->attributes['action']);
        self::assertStringEndsWith('/articles/42', $client->attributes['http_url']);
        self::assertStringNotContainsString('secret=1', $client->attributes['http_url']);
        self::assertSame('201', $client->attributes['http_status_code']);
        self::assertSame('http', $client->attributes['source']);
    }

    public function testDisabledAndExcludedRequestsDoNotProfile(): void
    {
        $request = $this->makeRequest('https://example.com/admin');
        $request->setMethod('GET');

        $client = new RecordingPerfbaseClient();
        $config = $this->config();
        $config->enabled = false;
        $lifecycle = new HttpRequestLifecycle($request, new TestPerfbaseClientProvider($client), new ErrorHandler(false, false), $config);
        $lifecycle->startProfiling();
        self::assertSame([], $client->startedSpans);

        $config = $this->config();
        $config->excludeHttp = ['/admin'];
        $lifecycle = new HttpRequestLifecycle($request, new TestPerfbaseClientProvider($client), new ErrorHandler(false, false), $config);
        $lifecycle->startProfiling();
        self::assertSame([], $client->startedSpans);
    }

    public function testDisallowedStatusCodeIsDroppedByDefault(): void
    {
        $client = new RecordingPerfbaseClient();
        $request = $this->makeRequest('https://example.com/missing');
        $request->setMethod('GET');

        $lifecycle = new HttpRequestLifecycle(
            $request,
            new TestPerfbaseClientProvider($client),
            new ErrorHandler(false, false),
            $this->config()
        );

        $lifecycle->startProfiling();
        $lifecycle->setResponseStatusCode(404);
        $lifecycle->stopProfiling();

        self::assertSame(0, $client->submitCalls);
        self::assertSame(1, $client->resetCalls);
        self::assertSame('404', $client->attributes['http_status_code']);
    }

    public function testCustomAllowedStatusCodeIsSubmitted(): void
    {
        $client = new RecordingPerfbaseClient();
        $request = $this->makeRequest('https://example.com/missing');
        $request->setMethod('GET');

        $config = $this->config();
        $config->profileHttpStatusCodes = [200, 404];

        $lifecycle = new HttpRequestLifecycle(
            $request,
            new TestPerfbaseClientProvider($client),
            new ErrorHandler(false, false),
            $config
        );

        $lifecycle->startProfiling();
        $lifecycle->setResponseStatusCode(404);
        $lifecycle->stopProfiling();

        self::assertSame(1, $client->submitCalls);
        self::assertSame(0, $client->resetCalls);
        self::assertSame('404', $client->attributes['http_status_code']);
    }

    public function testExceptionIsCaptured(): void
    {
        $request = $this->makeRequest('https://example.com/articles/42');
        $request->setMethod('GET');
        $client = new RecordingPerfbaseClient();

        $lifecycle = new HttpRequestLifecycle($request, new TestPerfbaseClientProvider($client), new ErrorHandler(false, false), $this->config());
        $lifecycle->startProfiling();
        $lifecycle->setException(new \RuntimeException('http boom'));
        $lifecycle->stopProfiling();

        self::assertSame('http boom', $client->attributes['exception']);
    }

    public function testControllerFallbackAndUserIdAreCaptured(): void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

        $request = new class(config('App'), new URI('https://example.com/articles/42'), null, new UserAgent()) extends IncomingRequest {
            public object $user;
        };
        $request->user = new class {
            public function getId(): int
            {
                return 123;
            }
        };
        $request->setMethod('POST');

        $client = new RecordingPerfbaseClient();
        $lifecycle = new HttpRequestLifecycle(
            $request,
            new TestPerfbaseClientProvider($client),
            new ErrorHandler(false, false),
            $this->config(),
            null,
            'App\\Controllers\\Articles',
            'store'
        );

        $lifecycle->startProfiling();
        $lifecycle->stopProfiling();

        self::assertSame(['http'], $client->startedSpans);
        self::assertSame('POST App\\Controllers\\Articles::store', $client->attributes['action']);
        self::assertSame('123', $client->attributes['user_id']);
    }

    public function testShieldResolverCapturesUserIdWhenAvailable(): void
    {
        Services::injectMock('auth', new class {
            public function user(): object
            {
                return new class {
                    public function getId(): int
                    {
                        return 456;
                    }
                };
            }
        });

        $request = $this->makeRequest('https://example.com/account');
        $request->setMethod('GET');
        $client = new RecordingPerfbaseClient();

        $lifecycle = new HttpRequestLifecycle(
            $request,
            new TestPerfbaseClientProvider($client),
            new ErrorHandler(false, false),
            $this->config()
        );

        $lifecycle->startProfiling();
        $lifecycle->stopProfiling();

        self::assertSame('456', $client->attributes['user_id']);
    }

    public function testConfiguredUserResolverOverridesShieldDetection(): void
    {
        Services::injectMock('auth', new class {
            public function user(): object
            {
                return new class {
                    public function getId(): int
                    {
                        return 111;
                    }
                };
            }
        });
        Services::injectMock('customUserResolver', new FakeUserResolver('999'));

        $config = $this->config();
        $config->userResolver = 'customUserResolver';

        $request = $this->makeRequest('https://example.com/account');
        $request->setMethod('GET');
        $client = new RecordingPerfbaseClient();

        $lifecycle = new HttpRequestLifecycle(
            $request,
            new TestPerfbaseClientProvider($client),
            new ErrorHandler(false, false),
            $config
        );

        $lifecycle->startProfiling();
        $lifecycle->stopProfiling();

        self::assertSame('999', $client->attributes['user_id']);
    }

    private function config(): Perfbase
    {
        $config = new Perfbase();
        $config->enabled = true;
        $config->apiKey = 'test-key';
        $config->sampleRate = 1.0;

        return $config;
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
