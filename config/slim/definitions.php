<?php

declare(strict_types=1);

/**
 * Blackcube DI definitions for Slim (PHP-DI).
 */

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
use Blackcube\Ssr\Interfaces\RouteProviderInterface;
use Blackcube\Ssr\Interfaces\SsrRoutingMiddlewareInterface;
use Blackcube\Ssr\Services\HandlerRegistry;
use Blackcube\Ssr\SlimSsrRoutingMiddleware;
use Psr\Container\ContainerInterface;
use Yiisoft\Cache\ArrayCache;
use Yiisoft\Cache\Cache;
use Yiisoft\Cache\CacheInterface;
use Yiisoft\Injector\Injector;

use function DI\autowire;

return [
    // Cache
    CacheInterface::class => function () {
        return new Cache(new ArrayCache());
    },

    // Injector
    Injector::class => function (ContainerInterface $c) {
        return new Injector($c);
    },

    // JSON-LD builder
    JsonLdBuilderInterface::class => autowire(JsonLdBuilder::class),

    // Xeo services (used by SSR handlers)
    ElasticMdService::class => autowire(),
    RobotsService::class => autowire(),
    SitemapService::class => autowire(),
    LlmsService::class => autowire(),
    MdService::class => autowire(),

    // Route provider (handler registry — app should override scanAliases)
    RouteProviderInterface::class => autowire(HandlerRegistry::class)
        ->constructorParameter('scanAttributes', true)
        ->constructorParameter('scanAliases', []),

    // SSR routing middleware
    SlimSsrRoutingMiddleware::class => autowire()
        ->constructorParameter('jsonLdBuilder', \DI\get(JsonLdBuilderInterface::class)),
    SsrRoutingMiddlewareInterface::class => function (ContainerInterface $c) {
        return $c->get(SlimSsrRoutingMiddleware::class)
            ->withXeo()
            ->withMdAlternate();
    },

    // FileProvider (app should override filesystems paths)
    FileProvider::class => autowire()
        ->constructorParameter('filesystems', [])
        ->constructorParameter('defaultAlias', '@blfs'),
    FileProviderInterface::class => \DI\get(FileProvider::class),

    // CacheFile (app should override cachePath/cacheUrl)
    CacheFile::class => autowire()
        ->constructorParameter('cachePath', '')
        ->constructorParameter('cacheUrl', '/assets'),
];
