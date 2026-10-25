<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Ssr\Handlers;

use Blackcube\Dcore\Entities\Host;
use Blackcube\Dcore\Services\Xeo\LlmsService;
use Blackcube\Ssr\Interfaces\SsrRoutingMiddlewareInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Serves llms-full.txt: the full text of the site for language models.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class LlmFullHandler extends AbstractTextHandler
{
    public function __construct(
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
        private readonly LlmsService $llmsService,
    ) {
        parent::__construct($responseFactory, $streamFactory);
    }

    protected function contentType(): string
    {
        return 'text/plain; charset=utf-8';
    }

    protected function generate(Host $host, ServerRequestInterface $request): ?string
    {
        $uri = $request->getUri();
        $locale = $request->getAttribute(SsrRoutingMiddlewareInterface::LOCALE_ATTRIBUTE);
        return $this->llmsService->generateFull(
            $uri->getScheme(),
            $uri->getHost(),
            is_string($locale) === true ? $locale : null,
        );
    }
}
