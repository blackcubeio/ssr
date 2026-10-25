<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Ssr\Handlers\Laravel;

use Blackcube\Dcore\Helpers\HostHelper;
use Blackcube\Dcore\Services\Xeo\RobotsService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel handler serving robots.txt for the requested host.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class RobotsHandler
{
    public function __construct(
        private readonly RobotsService $robotsService,
    ) {}

    public function handle(Request $request): Response
    {
        $host = HostHelper::findByHostname($request->getHost());
        $content = $host !== null ? $this->robotsService->generate($host) : null;

        if ($content === null) {
            $response = new Response('', 404);
        } else {
            $response = new Response($content, 200, ['Content-Type' => 'text/plain']);
        }

        return $response;
    }
}
