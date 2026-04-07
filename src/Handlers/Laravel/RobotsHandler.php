<?php

declare(strict_types=1);

namespace Blackcube\Ssr\Handlers\Laravel;

use Blackcube\Dcore\Services\Xeo\RobotsService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RobotsHandler
{
    public function __construct(
        private readonly RobotsService $robotsService,
    ) {}

    public function handle(Request $request): Response
    {
        $content = $this->robotsService->generate($request->getHost());

        if ($content === null) {
            return new Response('', 404);
        }

        return new Response($content, 200, ['Content-Type' => 'text/plain']);
    }
}
