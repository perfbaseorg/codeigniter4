<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

// PHP 8.6 raises E_WARNING when a SessionHandlerInterface implementation lacks create_sid() /
// validateId(), and CodeIgniter's session handlers (<= 4.7.6) don't define them. CodeIgniter's
// error handler turns warnings into exceptions, so whichever test first loads a handler (via
// mockSession() or a feature request) errors. This package never uses sessions, so load the
// handlers once here with that warning silenced. Remove once CodeIgniter ships
// https://github.com/codeigniter4/CodeIgniter4/pull/10802 and the floor moves past it.
if (PHP_VERSION_ID >= 80600) {
    $previousLevel = error_reporting(error_reporting() & ~E_WARNING);
    try {
        class_exists(\CodeIgniter\Session\Handlers\ArrayHandler::class);
        class_exists(\CodeIgniter\Session\Handlers\FileHandler::class);
    } finally {
        error_reporting($previousLevel);
    }
}

define('CONFIGPATH', realpath(dirname(__DIR__) . '/vendor/codeigniter4/framework/app/Config') . DIRECTORY_SEPARATOR);
define('PUBLICPATH', realpath(dirname(__DIR__) . '/vendor/codeigniter4/framework/public') . DIRECTORY_SEPARATOR);

require dirname(__DIR__) . '/vendor/codeigniter4/framework/system/Test/bootstrap.php';
