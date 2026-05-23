<?php

declare(strict_types=1);

/**
 * di.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

use Blackcube\Dcore\Interfaces\SsrRouteProviderInterface;
use Blackcube\Dcore\Services\Xeo\LlmsService;
use Blackcube\Dcore\Services\Xeo\MdService;
use Blackcube\Dcore\Services\Xeo\RobotsService;
use Blackcube\Dcore\Services\Xeo\SitemapService;
use Blackcube\Ssr\Interfaces\RouteProviderInterface;
use Blackcube\Ssr\Services\HandlerRegistry;

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

    // Xeo services
    RobotsService::class => RobotsService::class,
    SitemapService::class => SitemapService::class,
    LlmsService::class => LlmsService::class,
    MdService::class => MdService::class,
];
