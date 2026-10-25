# blackcube/ssr — LLM index

**Role:** server-side routing bridge between an HTTP framework and `blackcube/dcore`. One middleware looks at the request path, answers the special routes itself (`robots.txt`, `sitemap.xml`, `llms.txt`, `llms-full.txt`, `*.md`), follows a Slug redirect, or maps the Slug of the request to the application handler declared for the type of the Content / Tag, injecting the entity into the handler and the SEO metadata (Xeo, JSON-LD, hreflang, markdown alternate) into the response. Three framework bindings share one routing core: Yii3 (PSR-15 plus view injections, locale cycle and error pages), any PSR-15 pipeline (request attributes), Laravel (Symfony request attributes).

## Package card

| | |
|---|---|
| Namespace | `Blackcube\Ssr` |
| Routing | `AbstractSsrRouting` (core), `PsrSsrRoutingMiddleware` (PSR-15), `YiiSsrRoutingMiddleware` (extends the PSR one), `LaravelSsrRoutingMiddleware`, `Interfaces\SsrRoutingMiddlewareInterface` |
| Handlers | `Services\HandlerRegistry` (`Interfaces\RouteProviderInterface`), `Attributes\RoutingHandler`, `Handlers\{Sitemap,Robots,Llm,LlmFull,Md,Redirect}Handler` (PSR-15), `Handlers\Laravel\*` (same six, Laravel native), `Handlers\AbstractTextHandler` |
| SEO | `Services\XeoInjection`, `Assets\JsonLdAssetBundle` |
| Yii only | `YiiLocaleManager`, `YiiFallbackHandler`, `YiiThrowableResponseFactory` |
| Laravel only | `Laravel\BlackcubeServiceProvider`, `config/laravel/blackcube.php` |
| Helper | `Helpers\Quill` (cleans Quill editor HTML) |
| Requires | PHP ^8.2, `blackcube/dcore` ^1.0, `psr/http-message` ^2.0, `psr/http-server-middleware` ^1.0, `psr/log` ^3.0, `yiisoft/injector` ^1.2 (`composer.json:29-36`). Everything framework-specific is a `suggest` (`:37-52`): yiisoft aliases / assets / cache / html / i18n / request-provider / session / view / yii-view-renderer for Yii, slim + php-di for PSR, `illuminate/http` for Laravel. |
| Yii config | config-plugin (`config/configuration.php:12-15`): `config/web/params.php` (`blackcube/ssr`: `scanAttributes`, `scanAliases`, `configHandlers`, `configErrorHandlers`, `routes`), `config/web/di.php` (`RouteProviderInterface` → `HandlerRegistry`, `SsrRouteProviderInterface`, `YiiSsrRoutingMiddleware` with `YiiLocaleManager`, `SsrRoutingMiddlewareInterface`, the four dcore Xeo services). PSR: `config/psr/definitions.php` (PHP-DI). Laravel: `config/laravel/blackcube.php`. |
| License | BSD-3-Clause |

## Rules

- One resolution order, in `AbstractSsrRouting::findHandler()` (`src/AbstractSsrRouting.php:114-123`): excluded prefix → pass through; special path → package handler; Slug of the host + path → redirect when `targetUrl` and `httpCode` are set, else the application handler of the element through `HandlerDescriptorFactory::fromElement()` (`:170-207`). No descriptor → the next middleware answers.
- Special paths are the `specialPaths` array (`:50-56`), changed only with `withSpecialPaths()` (`:93-98`). The `routes` key of `config/web/params.php:17-23` declares the same defaults but nothing reads it yet: see `extension.md`.
- Application handlers are found by `HandlerRegistry`: `#[RoutingHandler]` attributes scanned under `scanAliases`, then `configHandlers` / `configErrorHandlers`, config winning over attributes (`src/Services/HandlerRegistry.php:135-146`). The scan is cached with a dependency on the scanned files (`:303-327`).
- Three dispatch modes, decided once per handler from its signature (`:200-241`): `construct` (implements `RequestHandlerInterface`: entities through the constructor, then `handle($request)`), `invoke` (`__invoke()` parameters), `method` (method parameters). Only `Content`, `Tag`, `Content|Tag`, `Slug`, `Element` are tracked as CMS types, plus `Throwable` for error handlers (`:47-59`, `:251-296`); every other parameter is left to the container.
- Xeo is opt-in (`withXeo()`), markdown alternate is opt-in (`withMdAlternate()`); both are immutable `with*()` clones (`src/AbstractSsrRouting.php:64-98`). Xeo data is built only when a slug id is found among the handler data (`:212-221`, `:228-264`).
- The Yii middleware is the only one with a locale cycle: `YiiLocaleManager::applyLocale()` before resolution, `set()` after a Content / Tag is resolved, locale exposed as the `locale` request attribute (`src/YiiSsrRoutingMiddleware.php:48-76`). Error paths never set the locale, they read it (`src/YiiFallbackHandler.php:48-51`, `src/YiiThrowableResponseFactory.php:59-62`).
- Special-route handlers only turn a dcore `Services\Xeo\*` service answer into a response: `null` from the service → `404` (`src/Handlers/AbstractTextHandler.php:50-59`, `src/Handlers/Laravel/*.php`). The content rules (sitemap, robots, llms, markdown) live in `blackcube/dcore`.

## Parts

| File | Content | Read when |
|---|---|---|
| `routing.md` | The routing core, the three middlewares, the request flow, dispatch, xeo data. | before mounting or changing the middleware |
| `handlers.md` | `HandlerRegistry`, the `#[RoutingHandler]` attribute, dispatch modes, the special-route handlers (PSR-15 and Laravel). | before writing an application handler |
| `seo.md` | `XeoInjection` tag by tag, JSON-LD delivery, request attributes on PSR / Laravel, markdown alternate. | before touching the SEO output |
| `yii.md` | `YiiLocaleManager`, `YiiFallbackHandler`, `YiiThrowableResponseFactory`: locale cycle and CMS error pages. | before wiring a Yii application |
| `integration.md` | config-plugin, PHP-DI definitions, Laravel provider and config, `Quill`, tests. | before plugging the package in |
| `extension.md` | What extends and what does not. | before extending |
