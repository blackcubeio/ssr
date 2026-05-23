<?php

declare(strict_types=1);

/**
 * YiiThrowableResponseFactory.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Ssr;

use Blackcube\Dcore\Entities\Content;
use Blackcube\Dcore\Entities\Tag;
use Blackcube\Dcore\Services\HandlerDescriptor;
use Blackcube\Ssr\Interfaces\RouteProviderInterface;
use Blackcube\Ssr\Services\XeoInjection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Yiisoft\ErrorHandler\ThrowableResponseFactoryInterface;
use Yiisoft\I18n\LocaleProvider;
use Yiisoft\Injector\Injector;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * Yii3 SSR error response factory — decorates the default Yii factory.
 * Dispatches to CMS error handlers when available, falls back to Yii rendering.
 * Does not touch the LocaleProvider — fallbacks use the current locale, they do not set it.
 */
final class YiiThrowableResponseFactory implements ThrowableResponseFactoryInterface
{
    public function __construct(
        private readonly Injector $injector,
        private readonly RouteProviderInterface $handlerRegistry,
        private readonly LocaleProvider $localeProvider,
        private readonly ThrowableResponseFactoryInterface $defaultFactory,
        private readonly ?WebViewRenderer $viewRenderer = null,
    ) {}

    public function create(Throwable $throwable, ServerRequestInterface $request): ResponseInterface
    {
        $statusCode = $this->resolveStatusCode($throwable);

        try {
            $handlerInfo = $this->handlerRegistry->getErrorHandlerInfo($statusCode);
            $descriptor = $handlerInfo === null ? null : HandlerDescriptor::findByError(
                $handlerInfo['route'],
                $this->localeProvider->get()->asString(),
            );
            if ($descriptor === null) {
                return $this->defaultFactory->create($throwable, $request);
            }

            $data = $descriptor->getData();
            $matches = array_filter($data, static fn($v) => $v instanceof Content || $v instanceof Tag);
            $element = $matches === [] ? null : reset($matches);
            $viewRenderer = ($this->viewRenderer !== null && $element !== null)
                ? $this->viewRenderer->withAddedInjections(XeoInjection::from($element, $request))
                : $this->viewRenderer;

            $params = array_merge($data, [
                ServerRequestInterface::class => $request,
                'request' => $request,
                Throwable::class => $throwable,
                'exception' => $throwable,
            ]);
            if ($viewRenderer !== null) {
                $params[WebViewRenderer::class] = $viewRenderer;
                $params['viewRenderer'] = $viewRenderer;
            }

            return $this->dispatch($handlerInfo, $params)->withStatus($statusCode);
        } catch (Throwable) {
            return $this->defaultFactory->create($throwable, $request);
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
