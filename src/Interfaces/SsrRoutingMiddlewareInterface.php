<?php

declare(strict_types=1);

/**
 * SsrRoutingMiddlewareInterface.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Ssr\Interfaces;

use Closure;
use Psr\Http\Server\MiddlewareInterface;

/**
 * Framework-agnostic interface for SSR routing middleware.
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
interface SsrRoutingMiddlewareInterface extends MiddlewareInterface
{
    /**
     * Exclude path prefixes from SSR resolution.
     *
     * @param string ...$prefixes Path prefixes to skip (without leading slash, e.g. 'dboard/', 'api/')
     */
    public function withExcludedPrefixes(string ...$prefixes): static;

    /**
     * Enable Xeo injection (meta tags, link tags, JSON-LD).
     *
     * @param Closure(array): array|null $jsonLdTransformer Callable to transform JSON-LD data
     */
    public function withXeo(?Closure $jsonLdTransformer = null): static;

    /**
     * Enable markdown alternate Link header on CMS element pages.
     */
    public function withMdAlternate(): static;
}
