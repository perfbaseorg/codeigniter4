<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Runtime;

use CodeIgniter\HTTP\RequestInterface;
use Perfbase\CodeIgniter4\Lifecycle\ConsoleCommandLifecycle;
use Perfbase\CodeIgniter4\Lifecycle\HttpRequestLifecycle;

class ActiveLifecycleRegistry
{
    /** @var array<int, HttpRequestLifecycle> */
    private array $httpLifecycles = [];

    private ?ConsoleCommandLifecycle $activeConsoleLifecycle = null;

    public function setHttpLifecycle(RequestInterface $request, HttpRequestLifecycle $lifecycle): void
    {
        $this->httpLifecycles[spl_object_id($request)] = $lifecycle;
    }

    public function getHttpLifecycle(RequestInterface $request): ?HttpRequestLifecycle
    {
        return $this->httpLifecycles[spl_object_id($request)] ?? null;
    }

    public function clearHttpLifecycle(RequestInterface $request): void
    {
        unset($this->httpLifecycles[spl_object_id($request)]);
    }

    public function setActiveConsoleLifecycle(ConsoleCommandLifecycle $lifecycle): void
    {
        $this->activeConsoleLifecycle = $lifecycle;
    }

    public function getActiveConsoleLifecycle(): ?ConsoleCommandLifecycle
    {
        return $this->activeConsoleLifecycle;
    }

    public function clearActiveConsoleLifecycle(): void
    {
        $this->activeConsoleLifecycle = null;
    }

    public function reset(): void
    {
        $this->httpLifecycles = [];
        $this->activeConsoleLifecycle = null;
    }
}
