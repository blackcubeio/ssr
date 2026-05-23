<?php

declare(strict_types=1);

/**
 * BlackcubeServiceProvider.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Ssr\Laravel;

use Blackcube\Dcore\Interfaces\JsonLdBuilderInterface;
use Blackcube\Dcore\Services\ElasticMdService;
use Blackcube\Dcore\Services\JsonLdBuilder;
use Blackcube\Dcore\Services\Xeo\LlmsService;
use Blackcube\Dcore\Services\Xeo\MdService;
use Blackcube\Dcore\Services\Xeo\RobotsService;
use Blackcube\Dcore\Services\Xeo\SitemapService;
use Blackcube\FileProvider\CacheFile;
use Blackcube\FileProvider\FileProvider;
use Blackcube\FileProvider\Interfaces\FileProviderInterface;
use Blackcube\Injector\Injector as BlackcubeInjector;
use Blackcube\Ssr\Interfaces\RouteProviderInterface;
use Blackcube\Ssr\LaravelSsrRoutingMiddleware;
use Blackcube\Ssr\Services\HandlerRegistry;
use Illuminate\Support\ServiceProvider;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Cache\ArrayCache;
use Yiisoft\Cache\Cache;
use Yiisoft\Cache\CacheInterface;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Db\Mysql\Connection;
use Yiisoft\Db\Mysql\Driver;
use Yiisoft\Injector\Injector;

/**
 * Laravel service provider for Blackcube CMS.
 * Registers all Blackcube services (DB, cache, FileProvider, SSR).
 *
 * Configuration via config/blackcube.php (publish with --tag=blackcube-config).
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class BlackcubeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/laravel/blackcube.php', 'blackcube');

        $db = config('blackcube.db');
        $ssr = config('blackcube.ssr');

        // Aliases (shared by FileProvider, CacheFile, HandlerRegistry)
        $this->app->singleton(Aliases::class);

        // PSR-17 factories (needed by SSR PSR-15 handlers)
        $this->app->singletonIf(ResponseFactoryInterface::class, \HttpSoft\Message\ResponseFactory::class);
        $this->app->singletonIf(StreamFactoryInterface::class, \HttpSoft\Message\StreamFactory::class);

        // Database (Yii DB — closure needed for charset method call after construction)
        $this->app->singleton(Driver::class, function () use ($db) {
            $driver = new Driver($db['dsn'], $db['user'], $db['password']);
            $driver->charset('UTF8MB4');
            return $driver;
        });
        // PSR-16 cache → ArrayCache (isolates Yii cache from Laravel's own cache)
        $this->app->singleton(\Psr\SimpleCache\CacheInterface::class, ArrayCache::class);
        $this->app->singleton(SchemaCache::class);
        $this->app->alias(Driver::class, \Yiisoft\Db\Driver\Pdo\PdoDriverInterface::class);
        $this->app->singleton(ConnectionInterface::class, Connection::class);

        // Cache (Yii cache — for HandlerRegistry)
        $this->app->singleton(CacheInterface::class, Cache::class);

        // Injector (closure needed — wraps Laravel container which is $this->app)
        $this->app->singleton(Injector::class, function () {
            return new Injector($this->app);
        });

        // JSON-LD builder
        $this->app->singleton(JsonLdBuilderInterface::class, JsonLdBuilder::class);

        // Route provider (handler registry)
        $this->app->singleton(RouteProviderInterface::class, HandlerRegistry::class);
        $this->app->when(HandlerRegistry::class)->needs('$scanAttributes')->give($ssr['scanAttributes']);
        $this->app->when(HandlerRegistry::class)->needs('$scanAliases')->give($ssr['scanAliases']);
        $this->app->when(HandlerRegistry::class)->needs('$configHandlers')->give($ssr['configHandlers']);
        $this->app->when(HandlerRegistry::class)->needs('$configErrorHandlers')->give($ssr['configErrorHandlers']);

        // FileProvider
        $this->app->singleton(FileProvider::class);
        $this->app->alias(FileProvider::class, FileProviderInterface::class);
        $this->app->when(FileProvider::class)->needs('$filesystems')->give(config('blackcube.fileProvider.filesystems'));
        $this->app->when(FileProvider::class)->needs('$defaultAlias')->give(config('blackcube.fileProvider.defaultAlias'));

        // CacheFile
        $this->app->singleton(CacheFile::class);
        $this->app->when(CacheFile::class)->needs('$cachePath')->give(config('blackcube.cacheFile.cachePath'));
        $this->app->when(CacheFile::class)->needs('$cacheUrl')->give(config('blackcube.cacheFile.cacheUrl'));

        // Xeo services
        $this->app->singleton(ElasticMdService::class);
        $this->app->singleton(RobotsService::class);
        $this->app->singleton(SitemapService::class);
        $this->app->singleton(LlmsService::class);
        $this->app->singleton(MdService::class);

        // SSR routing middleware (closure needed — fluent methods return clones)
        $this->app->singleton(LaravelSsrRoutingMiddleware::class, function () use ($ssr) {
            $middleware = new LaravelSsrRoutingMiddleware(
                $this->app->make(Injector::class),
                $this->app->make(RouteProviderInterface::class),
                $this->app->make(JsonLdBuilderInterface::class),
            );
            $prefixes = $ssr['excludedPrefixes'] ?? [];
            if ($prefixes) {
                $middleware = $middleware->withExcludedPrefixes(...$prefixes);
            }
            if ($ssr['xeo'] ?? false) {
                $middleware = $middleware->withXeo();
            }
            if ($ssr['mdAlternate'] ?? false) {
                $middleware = $middleware->withMdAlternate();
            }
            return $middleware;
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/laravel/blackcube.php' => config_path('blackcube.php'),
        ], 'blackcube-config');

        BlackcubeInjector::init($this->app);
        ConnectionProvider::set($this->app->make(ConnectionInterface::class));
    }
}
