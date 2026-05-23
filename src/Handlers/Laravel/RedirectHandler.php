<?php

declare(strict_types=1);

namespace Blackcube\Ssr\Handlers\Laravel;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RedirectHandler
{
    public function __construct(
        private readonly string $redirectUrl,
        private readonly int $httpCode,
    ) {}

    public function handle(Request $request): Response
    {
        return new Response('', $this->httpCode, ['Location' => $this->redirectUrl]);
    }
}
