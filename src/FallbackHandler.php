<?php

declare(strict_types=1);

/**
 * FallbackHandler.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Ssr;

use Blackcube\Dcore\Services\HandlerDescriptor;
use Blackcube\Ssr\Interfaces\RouteProviderInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\Injector\Injector;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * Fallback handler for unmatched routes.
 * Dispatches to CMS error handler when available, delegates to default handler otherwise.
 */
final class FallbackHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly Injector $injector,
        private readonly RouteProviderInterface $handlerRegistry,
        private readonly ?WebViewRenderer $viewRenderer = null,
        private readonly ?RequestHandlerInterface $defaultHandler = null,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $handlerInfo = $this->handlerRegistry->getErrorHandlerInfo(404);

        if ($handlerInfo === null) {
            if ($this->defaultHandler !== null) {
                return $this->defaultHandler->handle($request);
            }
            throw new \RuntimeException('No 404 error handler configured');
        }

        $descriptor = HandlerDescriptor::findByError('404', $handlerInfo['route']);
        $data = $descriptor !== null ? $descriptor->getData() : [];

        $params = array_merge($data, [
            ServerRequestInterface::class => $request,
            'request' => $request,
        ]);

        if ($this->viewRenderer !== null) {
            $params[WebViewRenderer::class] = $this->viewRenderer;
            $params['viewRenderer'] = $this->viewRenderer;
        }

        return $this->dispatch($handlerInfo, $params)->withStatus(404);
    }

    private function dispatch(array $handlerInfo, array $params): ResponseInterface
    {
        $class = $handlerInfo['class'];
        $mode = $handlerInfo['mode'];

        switch ($mode) {
            case 'construct':
                $instance = $this->injector->make($class, $params);
                return $instance->handle($params[ServerRequestInterface::class]);

            case 'invoke':
                $instance = $this->injector->make($class);
                return $this->injector->invoke($instance, $params);

            case 'method':
                $instance = $this->injector->make($class);
                return $this->injector->invoke([$instance, $handlerInfo['method']], $params);
        }

        throw new \RuntimeException('Unknown handler mode: ' . $mode);
    }
}
