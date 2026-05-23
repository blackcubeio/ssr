<?php

declare(strict_types=1);

namespace Blackcube\Ssr\Tests\Support\Stub;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use HttpSoft\Message\Response;

/**
 * Simple RequestHandlerInterface implementation for testing.
 */
final class SimpleRequestHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response(200);
        $response->getBody()->write('default-handler');

        return $response;
    }
}
