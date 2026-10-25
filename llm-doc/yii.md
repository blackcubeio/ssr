# ssr — Yii3 locale and error pages

## `YiiLocaleManager` (`src/YiiLocaleManager.php`)

Constructor: `LocaleProvider` (yiisoft/i18n), `SessionInterface` (yiisoft/session), optional `TranslatorInterface` (`:39-43`). Supported locales = the ids of `Language::query()->active()`, loaded once per process in a static array (`:86-97`).

| Method | Effect |
|---|---|
| `set(string $lang)` | ignored when not supported; else `localeProvider->set(new Locale($lang))`, `translator?->setLocale($lang)`, session key `locale` (`:49-57`) |
| `get()` | `localeProvider->get()->language()` (`:62-65`) |
| `applyLocale(ServerRequestInterface $request)` | session value when supported, else the primary language of `Locale::acceptFromHttp(Accept-Language)` when supported, else nothing; posts to the provider and the translator, not to the session (`:70-84`) |

The URL path is never parsed: the locale comes from the resolved Content / Tag (`YiiSsrRoutingMiddleware::prepareDispatch()`, `src/YiiSsrRoutingMiddleware.php:62-71`), then from the session on the next requests. `SessionMiddleware` must run before the SSR middleware.

## `YiiFallbackHandler` (`src/YiiFallbackHandler.php`)

`RequestHandlerInterface` meant to replace the router's not-found handler. Constructor: `Injector`, `RouteProviderInterface`, `HandlerDescriptorFactory`, `LocaleProvider`, `RequestHandlerInterface $defaultHandler`, optional `WebViewRenderer` (`:36-43`).

`handle()` (`:45-79`): `handlerRegistry->getErrorHandlerInfo(404)` → `handlerDescriptorFactory->fromError($route, $localeProvider->get()->asString())`; no handler or no descriptor → `$defaultHandler->handle($request)`. Otherwise the first `Content` / `Tag` of the descriptor data feeds `new XeoInjection($element, $request)` added to the renderer (`:62-64`), params = data + request (+ `WebViewRenderer::class` / `'viewRenderer'`), own `dispatch()` with the three modes (`:81-107`), and the response gets `withStatus(404)` (`:75`).

## `YiiThrowableResponseFactory` (`src/YiiThrowableResponseFactory.php`)

`ThrowableResponseFactoryInterface` decorating the yiisoft/error-handler default one. Constructor: `Injector`, `RouteProviderInterface`, `HandlerDescriptorFactory`, `LocaleProvider`, `ThrowableResponseFactoryInterface $defaultFactory`, optional `WebViewRenderer`, optional `LoggerInterface` (`NullLogger` by default) (`:41-51`).

`create(Throwable, request)` (`:53-96`): status = the throwable code when it is an int in `400..599`, else `500` (`:98-106`); `getErrorHandlerInfo($statusCode)` → `fromError($route, locale asString())`; the CMS handler receives the data, the request, `Throwable::class` and `'exception'` (`:75-80`), plus the renderer carrying a `XeoInjection` of the error element; response `withStatus($statusCode)`. Any exception thrown while building the CMS page is logged (`error`, with `exception` and `original`) and the default factory answers (`:88-95`). Same when no handler matches.

## Wiring (not in the package config)

`config/web/di.php` binds the middleware only. The application binds the two error pieces itself:

```php
YiiFallbackHandler::class => [
    'class' => YiiFallbackHandler::class,
    '__construct()' => [
        'defaultHandler' => Reference::to(NotFoundHandler::class),
    ],
],
ThrowableResponseFactoryInterface::class => [
    'class' => YiiThrowableResponseFactory::class,
    '__construct()' => [
        'defaultFactory' => Reference::to(Yiisoft\ErrorHandler\ThrowableResponseFactory::class),
    ],
],
```

and uses `YiiFallbackHandler` as the `Router` fallback. Both read the locale posted upstream and never set it: an error page renders in the language of the last resolved Content / Tag, or of the session.
