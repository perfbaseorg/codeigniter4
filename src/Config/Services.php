<?php

declare(strict_types=1);

namespace Perfbase\CodeIgniter4\Config;

use CodeIgniter\Config\BaseService;
use Perfbase\CodeIgniter4\Runtime\ActiveLifecycleRegistry;
use Perfbase\CodeIgniter4\Runtime\PerfbaseCommands;
use Perfbase\CodeIgniter4\Runtime\PerfbaseClientProvider;
use Perfbase\CodeIgniter4\Support\ErrorHandler;
use Perfbase\CodeIgniter4\Support\PerfbaseExceptions;

class Services extends BaseService
{
    public static function perfbaseErrorHandler(bool $getShared = true): ErrorHandler
    {
        if ($getShared) {
            return static::getSharedInstance('perfbaseErrorHandler');
        }

        /** @var Perfbase $config */
        $config = config('Perfbase');

        return new ErrorHandler($config->debug, $config->logErrors);
    }

    public static function perfbaseClientProvider(bool $getShared = true): PerfbaseClientProvider
    {
        if ($getShared) {
            return static::getSharedInstance('perfbaseClientProvider');
        }

        /** @var Perfbase $config */
        $config = config('Perfbase');

        return new PerfbaseClientProvider($config, static::perfbaseErrorHandler());
    }

    public static function perfbaseLifecycleRegistry(bool $getShared = true): ActiveLifecycleRegistry
    {
        if ($getShared) {
            return static::getSharedInstance('perfbaseLifecycleRegistry');
        }

        return new ActiveLifecycleRegistry();
    }

    public static function commands(bool $getShared = true): PerfbaseCommands
    {
        if ($getShared) {
            return static::getSharedInstance('commands');
        }

        return new PerfbaseCommands(service('logger'));
    }

    public static function exceptions($config = null, bool $getShared = true): PerfbaseExceptions
    {
        if ($getShared) {
            return static::getSharedInstance('exceptions', $config);
        }

        $config ??= config('Exceptions');

        return new PerfbaseExceptions($config, service('request'), service('response'));
    }
}
