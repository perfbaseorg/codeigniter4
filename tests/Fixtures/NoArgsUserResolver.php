<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Tests\Fixtures;

use CodeIgniter\HTTP\RequestInterface;
use Perfbase\CodeIgniter4\Contracts\UserResolverInterface;

final class NoArgsUserResolver implements UserResolverInterface
{
    public function resolve(RequestInterface $request): ?string
    {
        return $request->getMethod() === 'GET' ? 'class-user' : null;
    }
}
