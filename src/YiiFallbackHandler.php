<?php

declare(strict_types=1);

/**
 * YiiFallbackHandler.php
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
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\I18n\LocaleProvider;
use Yiisoft\Injector\Injector;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * Yii3 fallback handler for unmatched routes.
 * Dispatches to CMS error handler when available, delegates to default handler otherwise.
 * Does not touch the LocaleProvider — fallbacks use the current locale, they do not set it.
 */
final class YiiFallbackHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly Injector $injector,
        private readonly RouteProviderInterface $handlerRegistry,
        private readonly LocaleProvider $localeProvider,
        private readonly RequestHandlerInterface $defaultHandler,
        private readonly ?WebViewRenderer $viewRenderer = null,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $handlerInfo = $this->handlerRegistry->getErrorHandlerInfo(404);
        $descriptor = $handlerInfo === null ? null : HandlerDescriptor::findByError(
            $handlerInfo['route'],
            $this->localeProvider->get()->asString(),
        );
        if ($descriptor === null) {
            return $this->defaultHandler->handle($request);
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
        ]);
        if ($viewRenderer !== null) {
            $params[WebViewRenderer::class] = $viewRenderer;
            $params['viewRenderer'] = $viewRenderer;
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
