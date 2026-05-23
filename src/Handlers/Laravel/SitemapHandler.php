<?php

declare(strict_types=1);

namespace Blackcube\Ssr\Handlers\Laravel;

use Blackcube\Dcore\Services\Xeo\SitemapService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SitemapHandler
{
    public function __construct(
        private readonly SitemapService $sitemapService,
    ) {}

    public function handle(Request $request): Response
    {
        $content = $this->sitemapService->generate($request->getScheme(), $request->getHost());

        if ($content === null) {
            return new Response('', 404);
        }

        return new Response($content, 200, ['Content-Type' => 'application/xml']);
    }
}
