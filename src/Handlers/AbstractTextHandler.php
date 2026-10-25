<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Ssr\Handlers;

use Blackcube\Dcore\Entities\Host;
use Blackcube\Dcore\Helpers\HostHelper;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Base of the handlers that answer a generated text document (robots.txt, llms.txt, markdown).
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
abstract class AbstractTextHandler implements RequestHandlerInterface
{
    public function __construct(
        protected readonly ResponseFactoryInterface $responseFactory,
        protected readonly StreamFactoryInterface $streamFactory,
    ) {}

    abstract protected function contentType(): string;
    abstract protected function generate(Host $host, ServerRequestInterface $request): ?string;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $host = $this->fromRequest($request);
        $content = $host !== null ? $this->generate($host, $request) : null;
        return $this->textResponse($content);
    }

    protected function fromRequest(ServerRequestInterface $request): ?Host
    {
        return HostHelper::findByHostname($request->getUri()->getHost());
    }

    protected function textResponse(?string $content): ResponseInterface
    {
        $response = $this->responseFactory->createResponse(404);
        if ($content !== null) {
            $response = $this->responseFactory->createResponse(200)
                ->withHeader('Content-Type', $this->contentType())
                ->withBody($this->streamFactory->createStream($content));
        }
        return $response;
    }
}
