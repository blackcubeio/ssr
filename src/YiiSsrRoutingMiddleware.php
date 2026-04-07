<?php

declare(strict_types=1);

/**
 * YiiSsrRoutingMiddleware.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Ssr;

use Blackcube\Dcore\Interfaces\JsonLdBuilderInterface;
use Blackcube\Ssr\Assets\JsonLdAssetBundle;
use Blackcube\Ssr\Handlers;
use Blackcube\Ssr\Interfaces\RouteProviderInterface;
use Blackcube\Ssr\Interfaces\SsrRoutingMiddlewareInterface;
use Blackcube\Ssr\Services\XeoInjection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\Assets\AssetManager;
use Yiisoft\Injector\Injector;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * Yii3 implementation of the SSR routing middleware.
 */
final class YiiSsrRoutingMiddleware extends AbstractSsrRouting implements SsrRoutingMiddlewareInterface
{
    public function __construct(
        Injector $injector,
        RouteProviderInterface $handlerRegistry,
        ?JsonLdBuilderInterface $jsonLdBuilder = null,
        private readonly ?WebViewRenderer $viewRenderer = null,
        private readonly ?AssetManager $assetManager = null,
    ) {
        parent::__construct($injector, $handlerRegistry, $jsonLdBuilder);
    }

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

        // Xeo injection for Yii (ViewRenderer + AssetManager)
        $viewRenderer = $this->viewRenderer;
        if ($this->xeoEnabled && $slugId !== null && $viewRenderer !== null && $this->jsonLdBuilder !== null) {
            $xeoInjection = XeoInjection::from($slugId, $request, $this->jsonLdBuilder, $this->jsonLdTransformer, $this->mdAlternateEnabled);
            $viewRenderer = $viewRenderer->withAddedInjections($xeoInjection);

            $jsStrings = $xeoInjection->getJsonLdJsStrings();
            if (!empty($jsStrings) && $this->assetManager !== null) {
                $this->assetManager->registerCustomized(JsonLdAssetBundle::class, [
                    'jsStrings' => $jsStrings,
                ]);
            }
        }

        // Dispatch
        $params = array_merge($data, [
            ServerRequestInterface::class => $request,
            'request' => $request,
        ]);

        if ($viewRenderer !== null) {
            $params[WebViewRenderer::class] = $viewRenderer;
            $params['viewRenderer'] = $viewRenderer;
        }

        $response = $this->dispatch($descriptor, $params);

        // Link header for markdown alternate
        if ($this->mdAlternateEnabled && $slugId !== null && $path !== '') {
            $mdUrl = $uri->getScheme() . '://' . $uri->getHost() . '/' . $path . '.md';
            $response = $response->withAddedHeader(
                'Link',
                '<' . $mdUrl . '>; rel="alternate"; type="text/markdown"'
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
