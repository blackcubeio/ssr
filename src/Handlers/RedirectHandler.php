<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Ssr\Handlers;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Answers the redirection declared on a slug.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class RedirectHandler implements RequestHandlerInterface
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
