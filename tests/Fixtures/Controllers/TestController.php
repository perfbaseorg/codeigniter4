<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Tests\Fixtures\Controllers;

use CodeIgniter\Controller;

class TestController extends Controller
{
    public function show(string $id): string
    {
        return 'article-' . $id;
    }
}
