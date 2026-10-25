<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Ssr\Handlers;

use Blackcube\Dcore\Entities\Host;
use Blackcube\Dcore\Services\Xeo\RobotsService;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Serves robots.txt for the requested host.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class RobotsHandler extends AbstractTextHandler
{
    public function __construct(
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
        private readonly RobotsService $robotsService,
    ) {
        parent::__construct($responseFactory, $streamFactory);
    }

    protected function contentType(): string
    {
        return 'text/plain';
    }

    protected function generate(Host $host, ServerRequestInterface $request): ?string
    {
        return $this->robotsService->generate($host);
    }
}
