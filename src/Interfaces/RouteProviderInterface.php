<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Ssr\Interfaces;

use Blackcube\Dcore\Interfaces\SsrRouteProviderInterface;

/**
 * Source of the SSR routes: CMS handlers and error handlers by route (declared by
 * SsrRouteProviderInterface) or by status code.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
interface RouteProviderInterface extends SsrRouteProviderInterface
{
    /**
     * Get error handler info for a status code.
     *
     * @return array{class: string, mode: string, method: ?string, expects: array, route: string}|null
     */
    public function getErrorHandlerInfo(int $statusCode): ?array;
}
