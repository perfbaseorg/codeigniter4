<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Tests\Fixtures;

use CodeIgniter\HTTP\RequestInterface;
use Perfbase\CodeIgniter4\Contracts\UserResolverInterface;

final class FakeUserResolver implements UserResolverInterface
{
    public function __construct(private ?string $value)
    {
    }

    public function resolve(RequestInterface $request): ?string
    {
        return $this->value;
    }
}
