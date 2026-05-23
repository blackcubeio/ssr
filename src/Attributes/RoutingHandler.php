<?php

declare(strict_types=1);

/**
 * RoutingHandler.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Ssr\Attributes;

use Attribute;

/**
 * Attribute to mark a class or method as a SSR route handler.
 * Handles both normal routes and error routes.
 *
 * Normal route: #[RoutingHandler(route: 'blog.show')]
 * Error exact:  #[RoutingHandler(route: 'error.404', errorCode: 404)]
 * Error range:  #[RoutingHandler(route: 'error.5xx', errorCodesRange: [500, 599])]
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final class RoutingHandler
{
    public readonly ?int $errorCodesRangeMin;
    public readonly ?int $errorCodesRangeMax;

    public function __construct(
        public readonly string $route,
        public readonly ?int $errorCode = null,
        ?array $errorCodesRange = null,
    ) {
        if ($errorCodesRange !== null) {
            if (count($errorCodesRange) !== 2 || $errorCodesRange[0] > $errorCodesRange[1]) {
                error_log(sprintf(
                    'RoutingHandler: invalid errorCodesRange for route "%s" — expected [min, max] with min <= max, got %s',
                    $route,
                    json_encode($errorCodesRange)
                ));
                $this->errorCodesRangeMin = null;
                $this->errorCodesRangeMax = null;
            } else {
                $this->errorCodesRangeMin = $errorCodesRange[0];
                $this->errorCodesRangeMax = $errorCodesRange[1];
            }
        } else {
            $this->errorCodesRangeMin = null;
            $this->errorCodesRangeMax = null;
        }
    }

    public function isErrorHandler(): bool
    {
        return $this->errorCode !== null
            || ($this->errorCodesRangeMin !== null && $this->errorCodesRangeMax !== null);
    }
}
