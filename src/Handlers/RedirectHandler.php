<?php

declare(strict_types=1);

namespace Blackcube\Ssr\Handlers;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RedirectHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly string $redirectUrl,
        private readonly int $httpCode,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->responseFactory
            ->createResponse($this->httpCode)
            ->withHeader('Location', $this->redirectUrl);
    }
}
