<?php

declare(strict_types=1);

namespace Blackcube\Ssr\Handlers\Laravel;

use Blackcube\Dcore\Services\Xeo\LlmsService;
use Blackcube\Ssr\Interfaces\SsrRoutingMiddlewareInterface;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class LlmHandler
{
    public function __construct(
        private readonly LlmsService $llmsService,
    ) {}

    public function handle(Request $request): Response
    {
        $locale = $request->attributes->get(SsrRoutingMiddlewareInterface::LOCALE_ATTRIBUTE);
        $content = $this->llmsService->generate(
            $request->getScheme(),
            $request->getHost(),
            is_string($locale) ? $locale : null,
        );

        if ($content === null) {
            return new Response('', 404);
        }

        return new Response($content, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
