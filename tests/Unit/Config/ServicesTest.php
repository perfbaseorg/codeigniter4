<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Tests\Unit\Config;

use CodeIgniter\Config\Services as FrameworkServices;
use Perfbase\CodeIgniter4\Tests\Support\CodeIgniterTestCase;
use Perfbase\CodeIgniter4\Config\Services as PerfbaseServices;
use Perfbase\CodeIgniter4\Runtime\ActiveLifecycleRegistry;
use Perfbase\CodeIgniter4\Runtime\PerfbaseClientProvider;
use Perfbase\CodeIgniter4\Runtime\PerfbaseCommands;
use Perfbase\CodeIgniter4\Support\ErrorHandler;
use Perfbase\CodeIgniter4\Support\PerfbaseExceptions;

final class ServicesTest extends CodeIgniterTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        FrameworkServices::reset();
        PerfbaseServices::reset();

        $config = config('Perfbase');
        $config->apiKey = 'test-key';
        $config->debug = true;
        $config->logErrors = false;
    }

    protected function tearDown(): void
    {
        PerfbaseServices::reset();
        FrameworkServices::reset();

        parent::tearDown();
    }

    public function testBuildsPerfbaseServices(): void
    {
        self::assertInstanceOf(ErrorHandler::class, PerfbaseServices::perfbaseErrorHandler(false));
        self::assertInstanceOf(PerfbaseClientProvider::class, PerfbaseServices::perfbaseClientProvider(false));
        self::assertInstanceOf(ActiveLifecycleRegistry::class, PerfbaseServices::perfbaseLifecycleRegistry(false));
        self::assertInstanceOf(PerfbaseCommands::class, PerfbaseServices::commands(false));
        self::assertInstanceOf(PerfbaseExceptions::class, PerfbaseServices::exceptions(config('Exceptions'), false));
    }

    public function testReturnsSharedServiceInstances(): void
    {
        self::assertSame(PerfbaseServices::perfbaseErrorHandler(), PerfbaseServices::perfbaseErrorHandler());
        self::assertSame(PerfbaseServices::perfbaseClientProvider(), PerfbaseServices::perfbaseClientProvider());
        self::assertSame(PerfbaseServices::perfbaseLifecycleRegistry(), PerfbaseServices::perfbaseLifecycleRegistry());
        self::assertSame(PerfbaseServices::commands(), PerfbaseServices::commands());
        self::assertSame(PerfbaseServices::exceptions(), PerfbaseServices::exceptions());
    }
}
