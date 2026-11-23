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

    private int $consoleDepth = 0;

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

    /**
     * Records that a Spark command started and returns the nesting depth.
     * Commands invoked from another command (BaseCommand::call(), command())
     * run at depth > 1 and belong to the outer command's trace.
     */
    public function enterConsoleCommand(): int
    {
        return ++$this->consoleDepth;
    }

    /**
     * Records that a Spark command finished and returns the remaining depth.
     */
    public function leaveConsoleCommand(): int
    {
        if ($this->consoleDepth > 0) {
            $this->consoleDepth--;
        }

        return $this->consoleDepth;
    }

    public function reset(): void
    {
        $this->httpLifecycles = [];
        $this->activeConsoleLifecycle = null;
        $this->consoleDepth = 0;
    }
}
