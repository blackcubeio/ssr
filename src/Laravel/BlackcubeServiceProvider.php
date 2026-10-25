<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Ssr\Laravel;

use Blackcube\Dcore\Interfaces\JsonLdBuilderInterface;
use Blackcube\Dcore\Interfaces\SsrRouteProviderInterface;
use Blackcube\Dcore\Services\ElasticMdService;
use Blackcube\Dcore\Services\HandlerDescriptorFactory;
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
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
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

        $this->app->singleton(Aliases::class);

        $this->app->singletonIf(ResponseFactoryInterface::class, \HttpSoft\Message\ResponseFactory::class);
        $this->app->singletonIf(StreamFactoryInterface::class, \HttpSoft\Message\StreamFactory::class);

        $this->app->singleton(Driver::class, fn () => $this->buildDriver($db));
        $this->app->singleton(\Psr\SimpleCache\CacheInterface::class, ArrayCache::class);
        $this->app->singleton(SchemaCache::class);
        $this->app->alias(Driver::class, \Yiisoft\Db\Driver\Pdo\PdoDriverInterface::class);
        $this->app->singleton(ConnectionInterface::class, Connection::class);

        $this->app->singleton(CacheInterface::class, Cache::class);

        $this->app->singleton(Injector::class, fn () => new Injector($this->app));

        $this->app->singleton(JsonLdBuilderInterface::class, JsonLdBuilder::class);

        $this->app->singleton(RouteProviderInterface::class, HandlerRegistry::class);
        $this->app->alias(RouteProviderInterface::class, SsrRouteProviderInterface::class);
        $this->app->when(HandlerRegistry::class)->needs('$scanAttributes')->give($ssr['scanAttributes']);
        $this->app->when(HandlerRegistry::class)->needs('$scanAliases')->give($ssr['scanAliases']);
        $this->app->when(HandlerRegistry::class)->needs('$configHandlers')->give($ssr['configHandlers']);
        $this->app->when(HandlerRegistry::class)->needs('$configErrorHandlers')->give($ssr['configErrorHandlers']);
        $this->app->singleton(HandlerDescriptorFactory::class);

        $this->app->singleton(FileProvider::class);
        $this->app->alias(FileProvider::class, FileProviderInterface::class);
        $this->app->when(FileProvider::class)->needs('$filesystems')->give(config('blackcube.fileProvider.filesystems'));
        $this->app->when(FileProvider::class)->needs('$defaultAlias')->give(config('blackcube.fileProvider.defaultAlias'));

        $this->app->singleton(CacheFile::class);
        $this->app->when(CacheFile::class)->needs('$cachePath')->give(config('blackcube.cacheFile.cachePath'));
        $this->app->when(CacheFile::class)->needs('$cacheUrl')->give(config('blackcube.cacheFile.cacheUrl'));

        $this->app->singleton(ElasticMdService::class);
        $this->app->singleton(RobotsService::class);
        $this->app->singleton(SitemapService::class);
        $this->app->singleton(LlmsService::class);
        $this->app->singleton(MdService::class);

        $this->app->singleton(LaravelSsrRoutingMiddleware::class, fn () => $this->buildSsrRoutingMiddleware($ssr));
    }

    private function buildDriver(array $db): Driver
    {
        $driver = new Driver($db['dsn'], $db['user'], $db['password']);
        $driver->charset('UTF8MB4');
        return $driver;
    }

    private function buildSsrRoutingMiddleware(array $ssr): LaravelSsrRoutingMiddleware
    {
        $middleware = new LaravelSsrRoutingMiddleware(
            $this->app->make(Injector::class),
            $this->app->make(HandlerDescriptorFactory::class),
            $this->app->make(JsonLdBuilderInterface::class),
        );
        $prefixes = $ssr['excludedPrefixes'] ?? [];
        if ($prefixes !== []) {
            $middleware = $middleware->withExcludedPrefixes(...$prefixes);
        }
        if (($ssr['xeo'] ?? false) === true) {
            $middleware = $middleware->withXeo();
        }
        if (($ssr['mdAlternate'] ?? false) === true) {
            $middleware = $middleware->withMdAlternate();
        }
        return $middleware;
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
