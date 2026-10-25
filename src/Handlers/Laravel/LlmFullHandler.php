<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Ssr\Handlers\Laravel;

use Blackcube\Dcore\Services\Xeo\LlmsService;
use Blackcube\Ssr\Interfaces\SsrRoutingMiddlewareInterface;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel handler serving llms-full.txt: the full text of the site for language models.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class LlmFullHandler
{
    public function __construct(
        private readonly LlmsService $llmsService,
    ) {}

    public function handle(Request $request): Response
    {
        $locale = $request->attributes->get(SsrRoutingMiddlewareInterface::LOCALE_ATTRIBUTE);
        $content = $this->llmsService->generateFull(
            $request->getScheme(),
            $request->getHost(),
            is_string($locale) === true ? $locale : null,
        );

        if ($content === null) {
            $response = new Response('', 404);
        } else {
            $response = new Response($content, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        return $response;
    }
}
