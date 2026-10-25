<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Ssr;

use Blackcube\Dcore\Entities\Content;
use Blackcube\Dcore\Entities\Tag;
use Blackcube\Dcore\Services\HandlerDescriptorFactory;
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
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class YiiFallbackHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly Injector $injector,
        private readonly RouteProviderInterface $handlerRegistry,
        private readonly HandlerDescriptorFactory $handlerDescriptorFactory,
        private readonly LocaleProvider $localeProvider,
        private readonly RequestHandlerInterface $defaultHandler,
        private readonly ?WebViewRenderer $viewRenderer = null,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $handlerInfo = $this->handlerRegistry->getErrorHandlerInfo(404);
        $descriptor = $handlerInfo === null ? null : $this->handlerDescriptorFactory->fromError(
            $handlerInfo['route'],
            $this->localeProvider->get()->asString(),
        );
        if ($descriptor === null) {
            $response = $this->defaultHandler->handle($request);
        } else {
            $data = $descriptor->getData();
            $element = null;
            foreach ($data as $value) {
                if ($element === null && ($value instanceof Content === true || $value instanceof Tag === true)) {
                    $element = $value;
                }
            }
            $viewRenderer = ($this->viewRenderer !== null && $element !== null)
                ? $this->viewRenderer->withAddedInjections(new XeoInjection($element, $request))
                : $this->viewRenderer;

            $params = array_merge($data, [
                ServerRequestInterface::class => $request,
                'request' => $request,
            ]);
            if ($viewRenderer !== null) {
                $params[WebViewRenderer::class] = $viewRenderer;
                $params['viewRenderer'] = $viewRenderer;
            }

            $response = $this->dispatch($handlerInfo, $params)->withStatus(404);
        }

        return $response;
    }

    private function dispatch(array $handlerInfo, array $params): ResponseInterface
    {
        $class = $handlerInfo['class'];
        $mode = $handlerInfo['mode'];

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
                    $handlerInfo['method'],
                ], $params);
                break;
            default:
                throw new \RuntimeException('Unknown handler mode: ' . $mode);
        }

        return $response;
    }
}
