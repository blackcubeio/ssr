<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Ssr\Tests\Support\Stub;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use HttpSoft\Message\Response;

/**
 * Simple __invoke handler for testing.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class InvokeHandler
{
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response(200);
        $response->getBody()->write('invoke-handler');

        return $response;
    }
}
