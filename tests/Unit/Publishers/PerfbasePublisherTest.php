<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Tests\Unit\Publishers;

use Perfbase\CodeIgniter4\Publishers\PerfbasePublisher;
use PHPUnit\Framework\TestCase;

final class PerfbasePublisherTest extends TestCase
{
    public function testPublishesConfigStubAndIsDiscoverable(): void
    {
        $destination = (string) constant('WRITEPATH') . 'perfbase-ci4-publisher-' . bin2hex(random_bytes(4)) . '/';
        mkdir($destination . 'Config', 0777, true);

        try {
            $publisher = new PerfbasePublisher(null, $destination);
            self::assertTrue($publisher->publish());
            self::assertFileExists($destination . 'Config/Perfbase.php');

            $method = new \ReflectionMethod(\CodeIgniter\Publisher\Publisher::class, 'discover');
            $discovered = $method->invokeArgs(
                null,
                $method->getNumberOfParameters() >= 2
                    ? ['Publishers', 'Perfbase\\CodeIgniter4']
                    : ['Publishers']
            );

            self::assertNotSame([], $discovered);
            self::assertInstanceOf(PerfbasePublisher::class, $discovered[0]);
        } finally {
            @unlink($destination . 'Config/Perfbase.php');
            @rmdir($destination . 'Config');
            @rmdir($destination);
        }
    }
}
