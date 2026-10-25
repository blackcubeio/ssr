<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Ssr\Handlers\Laravel;

use Blackcube\Dcore\Helpers\HostHelper;
use Blackcube\Dcore\Services\Xeo\MdService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel handler serving the markdown version of a page (path.md).
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class MdHandler
{
    public function __construct(
        private readonly MdService $mdService,
        private readonly string $path,
    ) {}

    public function handle(Request $request): Response
    {
        $host = HostHelper::findByHostname($request->getHost());
        $content = $host !== null
            ? $this->mdService->generate($host, $request->getHost(), $this->path, $request->getScheme())
            : null;

        if ($content === null) {
            $response = new Response('', 404);
        } else {
            $response = new Response($content, 200, ['Content-Type' => 'text/markdown; charset=utf-8']);
        }

        return $response;
    }
}
