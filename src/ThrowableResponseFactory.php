<?php

declare(strict_types=1);

/**
 * ThrowableResponseFactory.php
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
use Throwable;
use Yiisoft\ErrorHandler\ThrowableResponseFactoryInterface;
use Yiisoft\Injector\Injector;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * SSR error response factory — decorates the default Yii factory.
 * Dispatches to CMS error handlers when available, falls back to Yii rendering.
 */
final class ThrowableResponseFactory implements ThrowableResponseFactoryInterface
{
    public function __construct(
        private readonly Injector $injector,
        private readonly RouteProviderInterface $handlerRegistry,
        private readonly ?WebViewRenderer $viewRenderer = null,
        private readonly ?ThrowableResponseFactoryInterface $defaultFactory = null,
    ) {}

    public function create(Throwable $throwable, ServerRequestInterface $request): ResponseInterface
    {
        $statusCode = $this->resolveStatusCode($throwable);

        try {
            $handlerInfo = $this->handlerRegistry->getErrorHandlerInfo($statusCode);
            if ($handlerInfo === null) {
                if ($this->defaultFactory !== null) {
                    return $this->defaultFactory->create($throwable, $request);
                }
                throw $throwable;
            }

            $descriptor = HandlerDescriptor::findByError((string) $statusCode, $handlerInfo['route']);
            $data = $descriptor !== null ? $descriptor->getData() : [];

            $params = array_merge($data, [
                ServerRequestInterface::class => $request,
                'request' => $request,
                Throwable::class => $throwable,
                'exception' => $throwable,
            ]);

            if ($this->viewRenderer !== null) {
                $params[WebViewRenderer::class] = $this->viewRenderer;
                $params['viewRenderer'] = $this->viewRenderer;
            }

            return $this->dispatch($handlerInfo, $params)->withStatus($statusCode);
        } catch (Throwable) {
            if ($this->defaultFactory !== null) {
                return $this->defaultFactory->create($throwable, $request);
            }
            throw $throwable;
        }
    }

    private function resolveStatusCode(Throwable $throwable): int
    {
        $code = $throwable->getCode();
        if (is_int($code) && $code >= 400 && $code < 600) {
            return $code;
        }

        return 500;
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
