<?php

declare(strict_types=1);

namespace Blackcube\Ssr\Handlers;

use Blackcube\Dcore\Services\Xeo\RobotsService;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RobotsHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly RobotsService $robotsService,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $content = $this->robotsService->generate($request->getUri()->getHost());

        if ($content === null) {
            return $this->responseFactory->createResponse(404);
        }

        return $this->responseFactory->createResponse(200)
            ->withHeader('Content-Type', 'text/plain')
            ->withBody($this->streamFactory->createStream($content));
    }
}
