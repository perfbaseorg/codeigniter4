<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Tests\Integration;

use CodeIgniter\Config\Services;
use Perfbase\CodeIgniter4\Tests\Support\CodeIgniterTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Perfbase\CodeIgniter4\Filters\PerfbaseFilter;
use Perfbase\CodeIgniter4\Runtime\ActiveLifecycleRegistry;
use Perfbase\CodeIgniter4\Tests\Fixtures\RecordingPerfbaseClient;
use Perfbase\CodeIgniter4\Tests\Fixtures\TestPerfbaseClientProvider;

final class HttpFilterIntegrationTest extends CodeIgniterTestCase
{
    use FeatureTestTrait;

    private RecordingPerfbaseClient $client;
    private ActiveLifecycleRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new RecordingPerfbaseClient();
        Services::injectMock('perfbaseClientProvider', new TestPerfbaseClientProvider($this->client));
        $this->registry = new ActiveLifecycleRegistry();
        Services::injectMock('perfbaseLifecycleRegistry', $this->registry);

        $config = config('Perfbase');
        $config->enabled = true;
        $config->apiKey = 'test-key';
        $config->sampleRate = 1.0;

        $filters = config('Filters');
        $filters->aliases['perfbase'] = PerfbaseFilter::class;
        $filters->globals['before'][] = 'perfbase';
        $filters->globals['after'][] = 'perfbase';
    }

    public function testHttpFilterProfilesRequestFlow(): void
    {
        $response = $this->withRoutes([
            ['GET', 'articles/(:num)', static fn (...$params): string => 'article ' . (string) ($params[0] ?? '')],
        ])->call('GET', 'articles/42?secret=1');

        $response->assertOK();
        self::assertSame(['http'], $this->client->startedSpans);
        self::assertStringEndsWith('/articles/42', $this->client->attributes['http_url']);
        self::assertStringNotContainsString('secret=1', $this->client->attributes['http_url']);
        self::assertSame(1, $this->client->submitCalls);
        self::assertSame(0, $this->countTrackedHttpLifecycles());
    }

    public function testHttpFilterHandlesDisabledState(): void
    {
        $config = config('Perfbase');
        $config->enabled = false;

        $response = $this->withRoutes([
            ['GET', 'articles/(:num)', static fn (...$params): string => 'article ' . (string) ($params[0] ?? '')],
        ])->call('GET', 'articles/42');

        $response->assertOK();
        self::assertSame([], $this->client->startedSpans);
        self::assertSame(0, $this->client->submitCalls);
        self::assertSame(0, $this->countTrackedHttpLifecycles());
    }

    public function testHttpFilterDropsDisallowedStatusCodeByDefault(): void
    {
        $response = $this->withRoutes([
            ['GET', 'missing', static function () {
                return service('response')->setStatusCode(404)->setBody('missing');
            }],
        ])->call('GET', 'missing');

        $response->assertStatus(404);
        self::assertSame(['http'], $this->client->startedSpans);
        self::assertSame('404', $this->client->attributes['http_status_code']);
        self::assertSame(0, $this->client->submitCalls);
        self::assertSame(1, $this->client->resetCalls);
        self::assertSame(0, $this->countTrackedHttpLifecycles());
    }

    public function testHttpFilterSubmitsCustomAllowedStatusCode(): void
    {
        $config = config('Perfbase');
        $config->profileHttpStatusCodes = [200, 404];

        $response = $this->withRoutes([
            ['GET', 'missing', static function () {
                return service('response')->setStatusCode(404)->setBody('missing');
            }],
        ])->call('GET', 'missing');

        $response->assertStatus(404);
        self::assertSame(['http'], $this->client->startedSpans);
        self::assertSame('404', $this->client->attributes['http_status_code']);
        self::assertSame(1, $this->client->submitCalls);
        self::assertSame(0, $this->client->resetCalls);
        self::assertSame(0, $this->countTrackedHttpLifecycles());
    }

    public function testSequentialRequestsDoNotLeakLifecycleState(): void
    {
        $routes = [
            ['GET', 'articles/(:num)', static fn (...$params): string => 'article ' . (string) ($params[0] ?? '')],
        ];

        $this->withRoutes($routes)->call('GET', 'articles/42')->assertOK();
        self::assertSame(0, $this->countTrackedHttpLifecycles());

        $this->withRoutes($routes)->call('GET', 'articles/43')->assertOK();

        self::assertSame(
            ['http', 'http'],
            $this->client->startedSpans
        );
        self::assertSame(2, $this->client->submitCalls);
        self::assertSame(0, $this->countTrackedHttpLifecycles());
    }

    private function countTrackedHttpLifecycles(): int
    {
        $reflection = new \ReflectionClass($this->registry);
        $property = $reflection->getProperty('httpLifecycles');
        $property->setAccessible(true);

        return count($property->getValue($this->registry));
    }
}
