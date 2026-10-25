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
 * Handler with named methods for testing.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class MethodHandler
{
    public function show(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response(200);
        $response->getBody()->write('method-show');

        return $response;
    }

    public function error(ServerRequestInterface $request, \Throwable $exception): ResponseInterface
    {
        $response = new Response(500);
        $response->getBody()->write('method-error');

        return $response;
    }
}
