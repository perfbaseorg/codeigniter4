<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Runtime;

use Perfbase\CodeIgniter4\Config\Perfbase as PerfbaseConfig;
use Perfbase\CodeIgniter4\Support\ErrorHandler;
use Perfbase\SDK\Config;
use Perfbase\SDK\Perfbase;

class PerfbaseClientProvider
{
    private PerfbaseConfig $config;
    private ErrorHandler $errorHandler;

    /** @var callable */
    private $clientFactory;

    private ?Perfbase $client = null;
    private bool $initialized = false;

    /**
     * @param callable|null $clientFactory
     */
    public function __construct(PerfbaseConfig $config, ErrorHandler $errorHandler, $clientFactory = null)
    {
        $this->config = $config;
        $this->errorHandler = $errorHandler;
        $this->clientFactory = $clientFactory ?? static function (Config $config): Perfbase {
            return new Perfbase($config);
        };
    }

    public function getClient(): ?Perfbase
    {
        if ($this->initialized) {
            return $this->client;
        }

        $this->initialized = true;

        try {
            $sdkConfig = Config::fromArray([
                'api_key' => $this->config->apiKey,
                'api_url' => $this->config->apiUrl,
                'flags' => $this->config->flags,
                'timeout' => $this->config->timeout,
                'proxy' => $this->config->proxy,
            ]);

            $factory = $this->clientFactory;
            $client = $factory($sdkConfig);
            if (!$client instanceof Perfbase) {
                throw new \RuntimeException('Perfbase client factory must return a Perfbase instance.');
            }

            $this->client = $client;
        } catch (\Throwable $throwable) {
            $this->client = null;
            $this->errorHandler->handle($throwable, 'sdk_init');
        }

        return $this->client;
    }
}
