<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Ssr;

use Blackcube\Dcore\Services\HandlerDescriptor;
use Blackcube\Ssr\Handlers;
use Blackcube\Ssr\Interfaces\SsrRoutingMiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PSR-15 implementation of the SSR routing middleware.
 * Xeo data and JSON-LD are stored as PSR-7 request attributes.
 *
 * Framework-specific middlewares (Yii) extend this class and override the
 * prepareRequest() and prepareDispatch() hooks to add their own behaviour.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class PsrSsrRoutingMiddleware extends AbstractSsrRouting implements SsrRoutingMiddlewareInterface
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
        $request = $this->prepareRequest($request);

        $uri = $request->getUri();
        $path = ltrim($uri->getPath(), '/');

        $descriptor = $this->findHandler($uri->getHost(), $path);

        if ($descriptor === null) {
            $response = $handler->handle($request);
        } else {
            $data = $descriptor->getData();
            $slugId = $this->extractSlugId($data);

            [$request, $params, $mdAlternateUrl] = $this->prepareDispatch($request, $slugId, $data, $uri, $path);

            $response = $this->dispatch($descriptor, $params);

            if ($mdAlternateUrl !== null) {
                $response = $response->withAddedHeader(
                    'Link',
                    '<' . $mdAlternateUrl . '>; rel="alternate"; type="text/markdown"'
                );
            }
        }

        return $response;
    }

    /**
     * Hook called before route resolution. Yii uses it for locale resolution.
     */
    protected function prepareRequest(ServerRequestInterface $request): ServerRequestInterface
    {
        return $request;
    }

    /**
     * Enrich the request and build dispatch params.
     *
     * @return array{0: ServerRequestInterface, 1: array, 2: ?string}
     */
    protected function prepareDispatch(
        ServerRequestInterface $request,
        ?int $slugId,
        array $data,
        UriInterface $uri,
        string $path
    ): array {
        $mdAlternateUrl = null;

        if ($slugId !== null) {
            $xeoData = $this->buildXeoData($slugId, $uri->getScheme(), $uri->getHost(), $path, (string) $uri);

            if ($xeoData['xeo'] !== null) {
                $request = $request->withAttribute('xeo', $xeoData['xeo']);
                $request = $request->withAttribute('ogUrl', $xeoData['ogUrl']);
            }
            if (empty($xeoData['jsonLds']) === false) {
                $request = $request->withAttribute('jsonLds', $xeoData['jsonLds']);
            }
            if ($xeoData['mdAlternateUrl'] !== null) {
                $request = $request->withAttribute('mdAlternateUrl', $xeoData['mdAlternateUrl']);
                $mdAlternateUrl = $xeoData['mdAlternateUrl'];
            }
        }

        $params = array_merge($data, [
            ServerRequestInterface::class => $request,
            'request' => $request,
        ]);

        return [
            $request,
            $params,
            $mdAlternateUrl,
        ];
    }

    protected function dispatch(HandlerDescriptor $descriptor, array $params): ResponseInterface
    {
        $class = $descriptor->getClass();
        $mode = $descriptor->getMode();

        switch ($mode) {
            case 'construct':
                $instance = $this->injector->make($class, $params);
                $response = $instance->handle($params[ServerRequestInterface::class]);
                break;
            case 'invoke':
                $instance = $this->injector->make($class);
                $response = $this->injector->invoke($instance, $params);
                break;
            case 'method':
                $instance = $this->injector->make($class);
                $response = $this->injector->invoke([
                    $instance,
                    $descriptor->getMethod(),
                ], $params);
                break;
            default:
                throw new \RuntimeException('Unknown handler mode: ' . $mode);
        }

        return $response;
    }
}
