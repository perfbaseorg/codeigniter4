<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Support;

use CodeIgniter\Config\Services;
use Perfbase\CodeIgniter4\Contracts\UserResolverInterface;

final class UserResolverFactory
{
    public function make(?string $resolverName): ?UserResolverInterface
    {
        if ($resolverName === null || trim($resolverName) === '') {
            return null;
        }

        $resolverName = trim($resolverName);

        if (!str_contains($resolverName, '\\')) {
            $service = $this->resolveNamedService($resolverName);

            return $service instanceof UserResolverInterface ? $service : null;
        }

        if (class_exists($resolverName)) {
            $instance = new $resolverName();

            return $instance instanceof UserResolverInterface ? $instance : null;
        }

        return null;
    }

    private function resolveNamedService(string $resolverName): mixed
    {
        $service = $this->resolveInjectedMock($resolverName);
        if ($service !== null) {
            return $service;
        }

        try {
            if (Services::serviceExists($resolverName)) {
                return Services::$resolverName();
            }
        } catch (\Throwable) {
        }

        try {
            return service($resolverName);
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveInjectedMock(string $resolverName): mixed
    {
        try {
            $reflection = new \ReflectionClass(Services::class);
            if (!$reflection->hasProperty('mocks')) {
                return null;
            }

            $property = $reflection->getProperty('mocks');
            $mocks = $property->getValue();

            if (!is_array($mocks)) {
                return null;
            }

            if (array_key_exists($resolverName, $mocks)) {
                return $mocks[$resolverName];
            }

            $normalizedName = strtolower($resolverName);

            return array_key_exists($normalizedName, $mocks) ? $mocks[$normalizedName] : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
