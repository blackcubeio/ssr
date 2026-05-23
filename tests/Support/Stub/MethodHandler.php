<?php

declare(strict_types=1);

namespace Blackcube\Ssr\Tests\Support\Stub;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use HttpSoft\Message\Response;

/**
 * Handler with named methods for testing.
 */
final class MethodHandler
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
