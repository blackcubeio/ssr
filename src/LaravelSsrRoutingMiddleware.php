<?php

declare(strict_types=1);

/**
 * LaravelSsrRoutingMiddleware.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Ssr;

use Blackcube\Ssr\Handlers\Laravel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel implementation of the SSR routing middleware.
 * Xeo data is stored in $request->attributes for the view layer.
 */
final class LaravelSsrRoutingMiddleware extends AbstractSsrRouting
{
    protected function getHandlerMap(): array
    {
        return [
            'sitemap' => Laravel\SitemapHandler::class,
            'robots' => Laravel\RobotsHandler::class,
            'llms' => Laravel\LlmHandler::class,
            'llms-full' => Laravel\LlmFullHandler::class,
            'md' => Laravel\MdHandler::class,
            'redirect' => Laravel\RedirectHandler::class,
        ];
    }

    public function handle(Request $request, Closure $next): Response
    {
        $path = ltrim($request->getPathInfo(), '/');
        $host = $request->getHost();
        $scheme = $request->getScheme();

        $descriptor = $this->resolve($scheme, $host, $path);

        if ($descriptor === null) {
            return $next($request);
        }

        $data = $descriptor->getData();
        $slugId = $this->extractSlugId($data);

        // Xeo data as request attributes
        if ($slugId !== null) {
            $xeoData = $this->buildXeoData($slugId, $scheme, $host, $path, $request->url());

            if ($xeoData['xeo'] !== null) {
                $request->attributes->set('xeo', $xeoData['xeo']);
                $request->attributes->set('ogUrl', $xeoData['ogUrl']);
            }
            if (!empty($xeoData['jsonLds'])) {
                $request->attributes->set('jsonLds', $xeoData['jsonLds']);
            }
            if ($xeoData['mdAlternateUrl'] !== null) {
                $request->attributes->set('mdAlternateUrl', $xeoData['mdAlternateUrl']);
            }
        }

        // Dispatch
        $params = array_merge($data, [
            Request::class => $request,
            'request' => $request,
        ]);

        $response = $this->dispatch($descriptor, $params);

        // Link header for markdown alternate
        if ($slugId !== null && isset($xeoData['mdAlternateUrl']) && $xeoData['mdAlternateUrl'] !== null) {
            $response->headers->set(
                'Link',
                '<' . $xeoData['mdAlternateUrl'] . '>; rel="alternate"; type="text/markdown"',
                false
            );
        }

        return $response;
    }

    private function dispatch($descriptor, array $params): Response
    {
        $class = $descriptor->getClass();
        $mode = $descriptor->getMode();

        switch ($mode) {
            case 'construct':
                $instance = $this->injector->make($class, $params);
                return $this->injector->invoke([$instance, 'handle'], $params);

            case 'invoke':
                $instance = $this->injector->make($class);
                return $this->injector->invoke($instance, $params);

            case 'method':
                $instance = $this->injector->make($class);
                return $this->injector->invoke([$instance, $descriptor->getMethod()], $params);
        }

        throw new \RuntimeException('Unknown handler mode: ' . $mode);
    }
}
