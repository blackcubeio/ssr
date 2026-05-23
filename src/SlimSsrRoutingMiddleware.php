<?php

declare(strict_types=1);

/**
 * SlimSsrRoutingMiddleware.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Ssr;

use Blackcube\Ssr\Handlers;
use Blackcube\Ssr\Interfaces\SsrRoutingMiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Slim/PSR-15 implementation of the SSR routing middleware.
 * Xeo data and JSON-LD are stored as PSR-7 request attributes.
 */
final class SlimSsrRoutingMiddleware extends AbstractSsrRouting implements SsrRoutingMiddlewareInterface
{
    protected function getHandlerMap(): array
    {
        return [
            'sitemap' => Handlers\SitemapHandler::class,
            'robots' => Handlers\RobotsHandler::class,
            'llms' => Handlers\LlmHandler::class,
            'llms-full' => Handlers\LlmFullHandler::class,
            'md' => Handlers\MdHandler::class,
            'redirect' => Handlers\RedirectHandler::class,
        ];
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $uri = $request->getUri();
        $path = ltrim($uri->getPath(), '/');

        $descriptor = $this->resolve($uri->getScheme(), $uri->getHost(), $path);

        if ($descriptor === null) {
            return $handler->handle($request);
        }

        $data = $descriptor->getData();
        $slugId = $this->extractSlugId($data);

        // Xeo data as request attributes
        if ($slugId !== null) {
            $xeoData = $this->buildXeoData($slugId, $uri->getScheme(), $uri->getHost(), $path, (string) $uri);

            if ($xeoData['xeo'] !== null) {
                $request = $request->withAttribute('xeo', $xeoData['xeo']);
                $request = $request->withAttribute('ogUrl', $xeoData['ogUrl']);
            }
            if (!empty($xeoData['jsonLds'])) {
                $request = $request->withAttribute('jsonLds', $xeoData['jsonLds']);
            }
            if ($xeoData['mdAlternateUrl'] !== null) {
                $request = $request->withAttribute('mdAlternateUrl', $xeoData['mdAlternateUrl']);
            }
        }

        // Dispatch
        $params = array_merge($data, [
            ServerRequestInterface::class => $request,
            'request' => $request,
        ]);

        $response = $this->dispatch($descriptor, $params);

        // Link header for markdown alternate
        if ($slugId !== null && isset($xeoData['mdAlternateUrl']) && $xeoData['mdAlternateUrl'] !== null) {
            $response = $response->withAddedHeader(
                'Link',
                '<' . $xeoData['mdAlternateUrl'] . '>; rel="alternate"; type="text/markdown"'
            );
        }

        return $response;
    }

    private function dispatch($descriptor, array $params): ResponseInterface
    {
        $class = $descriptor->getClass();
        $mode = $descriptor->getMode();

        switch ($mode) {
            case 'construct':
                $instance = $this->injector->make($class, $params);
                return $instance->handle($params[ServerRequestInterface::class]);

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
