<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Tests\Unit\Runtime;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use Perfbase\CodeIgniter4\Tests\Support\CodeIgniterTestCase;
use Perfbase\CodeIgniter4\Config\Perfbase;
use Perfbase\CodeIgniter4\Lifecycle\ConsoleCommandLifecycle;
use Perfbase\CodeIgniter4\Lifecycle\HttpRequestLifecycle;
use Perfbase\CodeIgniter4\Runtime\ActiveLifecycleRegistry;
use Perfbase\CodeIgniter4\Support\ErrorHandler;
use Perfbase\CodeIgniter4\Tests\Fixtures\RecordingPerfbaseClient;
use Perfbase\CodeIgniter4\Tests\Fixtures\TestPerfbaseClientProvider;

final class ActiveLifecycleRegistryTest extends CodeIgniterTestCase
{
    public function testStoresAndClearsHttpAndConsoleLifecycles(): void
    {
        $registry = new ActiveLifecycleRegistry();
        $config = new Perfbase();
        $config->enabled = true;

        $provider = new TestPerfbaseClientProvider(new RecordingPerfbaseClient());
        $handler = new ErrorHandler(false, false);
        $request = new IncomingRequest(config('App'), new URI('https://example.com/articles/42'), null, new UserAgent());
        $request->setMethod('GET');

        $http = new HttpRequestLifecycle($request, $provider, $handler, $config);
        $console = new ConsoleCommandLifecycle('test:ok', $provider, $handler, $config);

        $registry->setHttpLifecycle($request, $http);
        $registry->setActiveConsoleLifecycle($console);

        self::assertSame($http, $registry->getHttpLifecycle($request));
        self::assertSame($console, $registry->getActiveConsoleLifecycle());

        $registry->clearHttpLifecycle($request);
        $registry->clearActiveConsoleLifecycle();

        self::assertNull($registry->getHttpLifecycle($request));
        self::assertNull($registry->getActiveConsoleLifecycle());

        $registry->setHttpLifecycle($request, $http);
        $registry->setActiveConsoleLifecycle($console);
        $registry->reset();

        self::assertNull($registry->getHttpLifecycle($request));
        self::assertNull($registry->getActiveConsoleLifecycle());
    }
}
