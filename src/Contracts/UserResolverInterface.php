<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Contracts;

use CodeIgniter\HTTP\RequestInterface;

interface UserResolverInterface
{
    public function resolve(RequestInterface $request): ?string;
}
