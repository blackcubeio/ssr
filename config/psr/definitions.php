<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

/**
 * Blackcube DI definitions for PSR-15 frameworks (PHP-DI).
 */

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
use Blackcube\Ssr\Interfaces\RouteProviderInterface;
use Blackcube\Ssr\Interfaces\SsrRoutingMiddlewareInterface;
use Blackcube\Ssr\Services\HandlerRegistry;
use Blackcube\Ssr\PsrSsrRoutingMiddleware;
use Psr\Container\ContainerInterface;
use Yiisoft\Cache\ArrayCache;
use Yiisoft\Cache\Cache;
use Yiisoft\Cache\CacheInterface;
use Yiisoft\Injector\Injector;

use function DI\autowire;

return [
    CacheInterface::class => function () {
        return new Cache(new ArrayCache());
    },

    Injector::class => function (ContainerInterface $c) {
        return new Injector($c);
    },

    JsonLdBuilderInterface::class => autowire(JsonLdBuilder::class),

    ElasticMdService::class => autowire(),
    RobotsService::class => autowire(),
    SitemapService::class => autowire(),
    LlmsService::class => autowire(),
    MdService::class => autowire(),

    RouteProviderInterface::class => autowire(HandlerRegistry::class)
        ->constructorParameter('scanAttributes', true)
        ->constructorParameter('scanAliases', []),
    SsrRouteProviderInterface::class => \DI\get(RouteProviderInterface::class),
    HandlerDescriptorFactory::class => autowire(),

    PsrSsrRoutingMiddleware::class => autowire()
        ->constructorParameter('jsonLdBuilder', \DI\get(JsonLdBuilderInterface::class)),
    SsrRoutingMiddlewareInterface::class => function (ContainerInterface $c) {
        return $c->get(PsrSsrRoutingMiddleware::class)
            ->withXeo()
            ->withMdAlternate();
    },

    FileProvider::class => autowire()
        ->constructorParameter('filesystems', [])
        ->constructorParameter('defaultAlias', '@blfs'),
    FileProviderInterface::class => \DI\get(FileProvider::class),

    CacheFile::class => autowire()
        ->constructorParameter('cachePath', '')
        ->constructorParameter('cacheUrl', '/assets'),
];
