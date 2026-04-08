<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Support;

use CodeIgniter\Config\Services;
use CodeIgniter\HTTP\RequestInterface;
use Perfbase\CodeIgniter4\Contracts\UserResolverInterface;

final class ShieldUserResolver implements UserResolverInterface
{
    public function isAvailable(): bool
    {
        return $this->resolveAuthService() !== null;
    }

    public function resolve(RequestInterface $request): ?string
    {
        $auth = $this->resolveAuthService();
        if ($auth === null) {
            return null;
        }

        foreach (['user', 'getUser'] as $method) {
            if (!method_exists($auth, $method)) {
                continue;
            }

            return UserIdentifier::fromValue($auth->{$method}());
        }

        return null;
    }

    private function resolveAuthService(): ?object
    {
        foreach (['auth', 'authentication'] as $serviceName) {
            $service = $this->resolveNamedService($serviceName);
            if (is_object($service)) {
                return $service;
            }
        }

        return null;
    }

    private function resolveNamedService(string $serviceName): ?object
    {
        $service = $this->resolveInjectedMock($serviceName);
        if (is_object($service)) {
            return $service;
        }

        try {
            if (Services::serviceExists($serviceName)) {
                /** @var mixed $resolved */
                $resolved = Services::$serviceName();
                if (is_object($resolved)) {
                    return $resolved;
                }
            }
        } catch (\Throwable) {
        }

        try {
            $resolved = service($serviceName);
            if (is_object($resolved)) {
                return $resolved;
            }
        } catch (\Throwable) {
        }

        return null;
    }

    private function resolveInjectedMock(string $serviceName): ?object
    {
        try {
            $reflection = new \ReflectionClass(Services::class);
            if (!$reflection->hasProperty('mocks')) {
                return null;
            }

            $property = $reflection->getProperty('mocks');
            $property->setAccessible(true);
            $mocks = $property->getValue();

            return is_array($mocks) && isset($mocks[$serviceName]) && is_object($mocks[$serviceName])
                ? $mocks[$serviceName]
                : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
