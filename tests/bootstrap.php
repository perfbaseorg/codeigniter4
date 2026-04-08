<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

define('CONFIGPATH', realpath(dirname(__DIR__) . '/vendor/codeigniter4/framework/app/Config') . DIRECTORY_SEPARATOR);
define('PUBLICPATH', realpath(dirname(__DIR__) . '/vendor/codeigniter4/framework/public') . DIRECTORY_SEPARATOR);

require dirname(__DIR__) . '/vendor/codeigniter4/framework/system/Test/bootstrap.php';
