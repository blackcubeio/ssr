<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

use Blackcube\Dcore\Interfaces\SsrRouteProviderInterface;
use Blackcube\Dcore\Services\Xeo\LlmsService;
use Blackcube\Dcore\Services\Xeo\MdService;
use Blackcube\Dcore\Services\Xeo\RobotsService;
use Blackcube\Dcore\Services\Xeo\SitemapService;
use Blackcube\Ssr\Interfaces\RouteProviderInterface;
use Blackcube\Ssr\Interfaces\SsrRoutingMiddlewareInterface;
use Blackcube\Ssr\Services\HandlerRegistry;
use Blackcube\Ssr\YiiLocaleManager;
use Blackcube\Ssr\YiiSsrRoutingMiddleware;
use Yiisoft\Definitions\Reference;

/** @var array $params */

return [
    RouteProviderInterface::class => [
        'class' => HandlerRegistry::class,
        '__construct()' => [
            'scanAttributes' => $params['blackcube/ssr']['scanAttributes'] ?? true,
            'scanAliases' => $params['blackcube/ssr']['scanAliases'] ?? [],
            'configHandlers' => $params['blackcube/ssr']['configHandlers'] ?? [],
            'configErrorHandlers' => $params['blackcube/ssr']['configErrorHandlers'] ?? [],
        ],
    ],
    SsrRouteProviderInterface::class => RouteProviderInterface::class,

    YiiSsrRoutingMiddleware::class => [
        'class' => YiiSsrRoutingMiddleware::class,
        '__construct()' => [
            'localeManager' => Reference::to(YiiLocaleManager::class),
        ],
    ],
    SsrRoutingMiddlewareInterface::class => YiiSsrRoutingMiddleware::class,

    RobotsService::class => RobotsService::class,
    SitemapService::class => SitemapService::class,
    LlmsService::class => LlmsService::class,
    MdService::class => MdService::class,
];
