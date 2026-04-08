<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Tests\Fixtures\Controllers;

use CodeIgniter\Controller;

class ExplodingController extends Controller
{
    public function index(): string
    {
        throw new \RuntimeException('http boom');
    }
}
