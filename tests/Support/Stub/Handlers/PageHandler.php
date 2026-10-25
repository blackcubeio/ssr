<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Ssr\Tests\Support\Stub\Handlers;

use Blackcube\Dcore\Entities\Content;
use Blackcube\Dcore\Entities\Tag;
use Blackcube\Ssr\Attributes\RoutingHandler;
use HttpSoft\Message\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[RoutingHandler(route: 'page')]
class PageHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly Content|Tag $element,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new Response(200);
    }
}
