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
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;
use Yiisoft\ErrorHandler\ThrowableResponseFactoryInterface;
use Yiisoft\I18n\LocaleProvider;
use Yiisoft\Injector\Injector;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * Yii3 SSR error response factory — decorates the default Yii factory.
 * Dispatches to CMS error handlers when available, falls back to Yii rendering.
 * Does not touch the LocaleProvider — fallbacks use the current locale, they do not set it.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class YiiThrowableResponseFactory implements ThrowableResponseFactoryInterface
{
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly Injector $injector,
        private readonly RouteProviderInterface $handlerRegistry,
        private readonly HandlerDescriptorFactory $handlerDescriptorFactory,
        private readonly LocaleProvider $localeProvider,
        private readonly ThrowableResponseFactoryInterface $defaultFactory,
        private readonly ?WebViewRenderer $viewRenderer = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function create(Throwable $throwable, ServerRequestInterface $request): ResponseInterface
    {
        $statusCode = $this->extractStatusCode($throwable);
        $response = null;
        try {
            $handlerInfo = $this->handlerRegistry->getErrorHandlerInfo($statusCode);
            $descriptor = $handlerInfo === null ? null : $this->handlerDescriptorFactory->fromError(
                $handlerInfo['route'],
                $this->localeProvider->get()->asString(),
            );
            if ($descriptor !== null) {
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
                    Throwable::class => $throwable,
                    'exception' => $throwable,
                ]);
                if ($viewRenderer !== null) {
                    $params[WebViewRenderer::class] = $viewRenderer;
                    $params['viewRenderer'] = $viewRenderer;
                }

                $response = $this->dispatch($handlerInfo, $params)->withStatus($statusCode);
            }
        } catch (Throwable $e) {
            $this->logger->error('CMS error handler failed, default error page used instead', [
                'exception' => $e,
                'original' => $throwable,
            ]);
            $response = null;
        }
        return $response ?? $this->defaultFactory->create($throwable, $request);
    }

    private function extractStatusCode(Throwable $throwable): int
    {
        $code = $throwable->getCode();
        $statusCode = 500;
        if (is_int($code) === true && $code >= 400 && $code < 600) {
            $statusCode = $code;
        }
        return $statusCode;
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
