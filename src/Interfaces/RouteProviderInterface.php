<?php

declare(strict_types=1);

/**
 * RouteProviderInterface.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Ssr\Interfaces;

use Blackcube\Dcore\Interfaces\SsrRouteProviderInterface;

interface RouteProviderInterface extends SsrRouteProviderInterface
{
    /**
     * Get handler info for a route.
     *
     * @return array{class: string, mode: string, method: ?string, expects: array}|null
     */
    public function getHandlerInfo(string $route): ?array;

    /**
     * Get error handler info for a status code.
     *
     * @return array{class: string, mode: string, method: ?string, expects: array, route: string}|null
     */
    public function getErrorHandlerInfo(int $statusCode): ?array;

    /**
     * Get error handler info by route name.
     *
     * @return array{class: string, mode: string, method: ?string, expects: array}|null
     */
    public function getErrorHandlerInfoByRoute(string $route): ?array;
}
