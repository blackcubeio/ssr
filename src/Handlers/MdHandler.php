<?php

declare(strict_types=1);

namespace Blackcube\Ssr\Handlers;

use Blackcube\Dcore\Services\Xeo\MdService;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class MdHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly MdService $mdService,
        private readonly string $path,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $uri = $request->getUri();
        $content = $this->mdService->generate($uri->getHost(), $this->path, $uri->getScheme());

        if ($content === null) {
            return $this->responseFactory->createResponse(404);
        }

        return $this->responseFactory->createResponse(200)
            ->withHeader('Content-Type', 'text/markdown; charset=utf-8')
            ->withBody($this->streamFactory->createStream($content));
    }
}
