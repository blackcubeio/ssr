# ssr — Routing

## Core: `AbstractSsrRouting` (`src/AbstractSsrRouting.php`)

Constructor: `Injector` (yiisoft), `HandlerDescriptorFactory` (dcore), optional `JsonLdBuilderInterface` (`:58-62`). State: `xeoEnabled`, `mdAlternateEnabled`, `excludedPrefixes`, `jsonLdTransformer`, `specialPaths` (`:36-56`).

| Method | Effect |
|---|---|
| `withExcludedPrefixes(string ...$prefixes)` | clone with the prefixes; a path starting with one of them is left to the next middleware (`:64-69`, `:125-134`). Prefixes are compared to the path without its leading slash (`src/PsrSsrRoutingMiddleware.php:53`). |
| `withXeo(?Closure $jsonLdTransformer = null)` | clone with xeo enabled and an optional `fn(array $jsonLds): array` (`:71-77`) |
| `withMdAlternate()` | clone with the markdown alternate enabled (`:79-84`) |
| `withSpecialPaths(array $paths)` | clone, `array_merge` over the defaults: keys `robots`, `sitemap`, `llms`, `llms-full`, `md-suffix` (`:93-98`) |
| `getHandlerMap()` (abstract) | the concrete class gives its handler classes for the keys `sitemap`, `robots`, `llms`, `llms-full`, `md`, `redirect` (`:107`) |

### `findHandler(string $host, string $path): ?HandlerDescriptor` (`:114-123`)

1. Excluded prefix → `null`.
2. `specialHandler()` (`:141-163`): path equal to `specialPaths['robots' | 'sitemap' | 'llms' | 'llms-full']` → `HandlerDescriptorFactory::simple($class, 'construct')`; path ending with `md-suffix` → `simple($handlers['md'], 'construct', [], fn() => ['path' => $pathWithoutSuffix])`. A key missing from the handler map disables that special route.
3. `slugHandler()` (`:170-207`): `HostHelper::findByHostname($host)`, then `Slug::query()->andWhere(['hostId' => …, 'path' => $path])->active()->one()`. Slug with `getTargetUrl() !== null` and `getHttpCode() !== null` → redirect descriptor carrying `redirectUrl` (the Slug link with the `host` template filled from the request, `:187`) and `httpCode`. Otherwise `$slug->getElement()` → `HandlerDescriptorFactory::fromElement($element)` (`:201`), which picks the application handler registered for the element's type route.

### Xeo data (`:212-284`)

`extractSlugId(array $data)` returns the `getSlugId()` of the first `Content` / `Tag` found in the descriptor data (`:212-221`). `buildXeoData($slugId, $scheme, $host, $path, $requestUrl)` returns `['xeo' => ?Xeo, 'ogUrl' => ?string, 'jsonLds' => array, 'mdAlternateUrl' => ?string]` (`:228-264`): with xeo enabled, a slug id and a JSON-LD builder, `Xeo::from($slugId)` with its canonical and alternate links expanded on the request host and `//` turned into `https://` (`:266-284`), `ogUrl` = canonical href or the request URL, `jsonLds` = `jsonLdBuilder->build($slugId, $host)` through the transformer when set. With the markdown alternate enabled and a non-empty path, `mdAlternateUrl` = `scheme://host/path.md`.

## PSR-15: `PsrSsrRoutingMiddleware` (`src/PsrSsrRoutingMiddleware.php`)

Implements `SsrRoutingMiddlewareInterface` (`MiddlewareInterface` + the three `with*()` + `LOCALE_ATTRIBUTE = 'locale'`, `src/Interfaces/SsrRoutingMiddlewareInterface.php:29-48`). Handler map: `Handlers\{Sitemap,Robots,Llm,LlmFull,Md,Redirect}Handler` (`:34-44`).

`process()` (`:46-76`): `prepareRequest()` hook → `findHandler(host, ltrim(path, '/'))` → no descriptor: `$handler->handle($request)`; descriptor: `prepareDispatch()` → `dispatch()` → when a markdown alternate URL exists, `Link: <url>; rel="alternate"; type="text/markdown"` added to the response (`:67-72`).

`prepareDispatch()` (`:91-126`): `buildXeoData()`, then request attributes `xeo` and `ogUrl` (when a Xeo exists), `jsonLds` (when not empty), `mdAlternateUrl`; handler params = descriptor data + `ServerRequestInterface::class` + `'request'`.

`dispatch()` (`:128-154`): `construct` → `injector->make($class, $params)` then `->handle($request)`; `invoke` → `make($class)` then `injector->invoke($instance, $params)`; `method` → `invoke([$instance, $method], $params)`; unknown mode → `RuntimeException`.

## Yii3: `YiiSsrRoutingMiddleware` (`src/YiiSsrRoutingMiddleware.php`)

Extends the PSR middleware; constructor adds optional `WebViewRenderer`, `AssetManager`, `YiiLocaleManager` (`:37-46`).

- `prepareRequest()`: `localeManager?->applyLocale($request)` (`:48-53`).
- `prepareDispatch()` (`:55-111`): the first `Content` / `Tag` of the data gives `getLanguageId()` → `localeManager?->set($lang)`; the current locale becomes the `locale` request attribute (`:73-76`). With xeo enabled, a slug id, a view renderer and a JSON-LD builder: `new XeoInjection($slugId, $request, $jsonLdBuilder, $jsonLdTransformer, $mdAlternateEnabled)` added to the renderer with `withAddedInjections()`, and the JSON-LD `<script>` strings registered with `AssetManager::registerCustomized(JsonLdAssetBundle::class, ['jsStrings' => …])` (`:80-90`). Params add `WebViewRenderer::class` and `'viewRenderer'` (the renderer carrying the injection) next to the request (`:96-104`). The markdown `Link` header follows the PSR path (`:92-94`).

No request attributes `xeo` / `jsonLds` on Yii: the view layer receives them through the renderer injections (see `seo.md`).

## Laravel: `LaravelSsrRoutingMiddleware` (`src/LaravelSsrRoutingMiddleware.php`)

Extends `AbstractSsrRouting` directly (no PSR-15). Handler map: `Handlers\Laravel\*` (`:29-39`). `handle(Request $request, Closure $next): Response` (`:41-87`): path from `getPathInfo()`, host and scheme from the request; no descriptor → `$next($request)`; otherwise `buildXeoData()` stored in `$request->attributes` under `xeo`, `ogUrl`, `jsonLds`, `mdAlternateUrl` (`:55-68`), params = data + `Request::class` + `'request'`, dispatch, then the `Link` header (`:77-83`).

`dispatch()` (`:89-119`): same three modes, except `construct` calls `injector->invoke([$instance, 'handle'], $params)` so that `handle()` may receive injected parameters (`:96-100`). No locale cycle: the `locale` attribute is read by the Laravel llms handlers but never set by this middleware.
