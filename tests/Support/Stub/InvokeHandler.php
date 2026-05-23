<?php

declare(strict_types=1);

namespace Blackcube\Ssr\Tests\Support\Stub;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use HttpSoft\Message\Response;

/**
 * Simple __invoke handler for testing.
 */
final class InvokeHandler
{
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response(200);
        $response->getBody()->write('invoke-handler');

        return $response;
    }
}
