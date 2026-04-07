<?php

declare(strict_types=1);

namespace Blackcube\Ssr\Handlers\Laravel;

use Blackcube\Dcore\Services\Xeo\MdService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class MdHandler
{
    public function __construct(
        private readonly MdService $mdService,
        private readonly string $path,
    ) {}

    public function handle(Request $request): Response
    {
        $content = $this->mdService->generate($request->getHost(), $this->path, $request->getScheme());

        if ($content === null) {
            return new Response('', 404);
        }

        return new Response($content, 200, ['Content-Type' => 'text/markdown; charset=utf-8']);
    }
}
