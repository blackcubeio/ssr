<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Ssr\Handlers\Laravel;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel handler answering the redirection declared on a slug.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class RedirectHandler
{
    public function __construct(
        private readonly string $redirectUrl,
        private readonly int $httpCode,
    ) {}

    public function handle(Request $request): Response
    {
        return new Response('', $this->httpCode, ['Location' => $this->redirectUrl]);
    }
}
