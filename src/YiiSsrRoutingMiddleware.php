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
use Blackcube\Dcore\Interfaces\JsonLdBuilderInterface;
use Blackcube\Dcore\Services\HandlerDescriptorFactory;
use Blackcube\Ssr\Assets\JsonLdAssetBundle;
use Blackcube\Ssr\Services\XeoInjection;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Yiisoft\Assets\AssetManager;
use Yiisoft\Injector\Injector;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * Yii3 implementation of the SSR routing middleware.
 *
 * Extends the PSR-15 base with Yii-specific behaviour: locale management,
 * XeoInjection into the WebViewRenderer and JSON-LD asset registration.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class YiiSsrRoutingMiddleware extends PsrSsrRoutingMiddleware
{
    public function __construct(
        Injector $injector,
        HandlerDescriptorFactory $handlerDescriptorFactory,
        ?JsonLdBuilderInterface $jsonLdBuilder = null,
        private readonly ?WebViewRenderer $viewRenderer = null,
        private readonly ?AssetManager $assetManager = null,
        private readonly ?YiiLocaleManager $localeManager = null,
    ) {
        parent::__construct($injector, $handlerDescriptorFactory, $jsonLdBuilder);
    }

    protected function prepareRequest(ServerRequestInterface $request): ServerRequestInterface
    {
        $this->localeManager?->applyLocale($request);

        return $request;
    }

    protected function prepareDispatch(
        ServerRequestInterface $request,
        ?int $slugId,
        array $data,
        UriInterface $uri,
        string $path
    ): array {
        $element = null;
        foreach ($data as $value) {
            if ($element === null && ($value instanceof Content === true || $value instanceof Tag === true)) {
                $element = $value;
            }
        }
        $lang = $element?->getLanguageId();
        if ($lang !== null) {
            $this->localeManager?->set($lang);
        }

        $locale = $this->localeManager?->get();
        if ($locale !== null && $locale !== '') {
            $request = $request->withAttribute(self::LOCALE_ATTRIBUTE, $locale);
        }

        $viewRenderer = $this->viewRenderer;
        $mdAlternateUrl = null;
        if ($this->xeoEnabled === true && $slugId !== null && $viewRenderer !== null && $this->jsonLdBuilder !== null) {
            $xeoInjection = new XeoInjection($slugId, $request, $this->jsonLdBuilder, $this->jsonLdTransformer, $this->mdAlternateEnabled);
            $viewRenderer = $viewRenderer->withAddedInjections($xeoInjection);

            $jsStrings = $xeoInjection->getJsonLdJsStrings();
            if (empty($jsStrings) === false && $this->assetManager !== null) {
                $this->assetManager->registerCustomized(JsonLdAssetBundle::class, [
                    'jsStrings' => $jsStrings,
                ]);
            }
        }

        if ($this->mdAlternateEnabled === true && $slugId !== null && $path !== '') {
            $mdAlternateUrl = $uri->getScheme() . '://' . $uri->getHost() . '/' . $path . '.md';
        }

        $params = array_merge($data, [
            ServerRequestInterface::class => $request,
            'request' => $request,
        ]);

        if ($viewRenderer !== null) {
            $params[WebViewRenderer::class] = $viewRenderer;
            $params['viewRenderer'] = $viewRenderer;
        }

        return [
            $request,
            $params,
            $mdAlternateUrl,
        ];
    }
}
