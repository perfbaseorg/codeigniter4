<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Config;

use Perfbase\CodeIgniter4\Filters\PerfbaseFilter;

class Registrar
{
    /**
     * @return array<string, array<string, class-string>>
     */
    public static function Filters(): array
    {
        return [
            'aliases' => [
                'perfbase' => PerfbaseFilter::class,
            ],
        ];
    }
}
