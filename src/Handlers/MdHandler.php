<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Ssr\Handlers;

use Blackcube\Dcore\Entities\Host;
use Blackcube\Dcore\Services\Xeo\MdService;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Serves the markdown version of a page (path.md).
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class MdHandler extends AbstractTextHandler
{
    public function __construct(
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
        private readonly MdService $mdService,
        private readonly string $path,
    ) {
        parent::__construct($responseFactory, $streamFactory);
    }

    protected function contentType(): string
    {
        return 'text/markdown; charset=utf-8';
    }

    protected function generate(Host $host, ServerRequestInterface $request): ?string
    {
        $uri = $request->getUri();
        return $this->mdService->generate($host, $uri->getHost(), $this->path, $uri->getScheme());
    }
}
