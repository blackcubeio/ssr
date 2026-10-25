# ssr — Handlers

## `HandlerRegistry` (`src/Services/HandlerRegistry.php`)

Implements `Interfaces\RouteProviderInterface`, which extends dcore `SsrRouteProviderInterface` with `getErrorHandlerInfo(int $statusCode): ?array` (`src/Interfaces/RouteProviderInterface.php:30`). Constructor: `Aliases`, `CacheInterface`, `scanAttributes = true`, `scanAliases = []`, `configHandlers = []`, `configErrorHandlers = []` (`:64-71`).

| Method | Returns |
|---|---|
| `getHandlerInfo(string $route)` | `['class', 'mode', 'method', 'expects']` or `null` (`:78-83`); called by dcore `HandlerDescriptorFactory::fromElement()` |
| `getErrorHandlerInfo(int $statusCode)` | the first error handler whose `code` equals the status, else the first whose `[min, max]` contains it, with `'route'` added (`:91-114`) |
| `getErrorHandlerInfoByRoute(string $route)` | the error handler info by route name (`:121-126`) |
| `getAvailableRoutes()` | route names of handlers and error handlers (`:128-133`) |

Loading (`:135-146`): when `scanAttributes` is `true`, `scanAll()` scans every `.php` file under each alias of `scanAliases` (`:379-399`, directories resolved with `Aliases::get()`), through `cache->getOrSet(CACHE_KEY, …, null, $dependency)` where the dependency is a `FileDependency` on the single file or an `AnyDependency` of `FileDependency` (`:303-327`). The class name of a file is read from its tokens: `namespace` + first `class` keyword not preceded by `::` (`:401-434`), one class per file. Then `array_merge(scanned, config)`: a config entry replaces a scanned entry with the same route.

Config shapes (`:151-193`): `configHandlers[route] = Class::class` or `[Class::class, 'method']`; `configErrorHandlers[route] = ['handler' => Class::class | [Class::class, 'method'], 'code' => 404]` or `['handler' => …, 'min' => 500, 'max' => 599]` (a bare class is accepted as `handler` with no code and no range).

### Dispatch modes (`:200-241`)

| Mode | Detection | Entities injected through |
|---|---|---|
| `construct` | class implements `RequestHandlerInterface` | constructor parameters; then `handle($request)` |
| `invoke` | otherwise, class with `__invoke()` | `__invoke()` parameters |
| `method` | attribute on a method, or config `[Class, 'method']` | method parameters |

`expects` lists the CMS types found among those parameters (`:251-296`): `Content`, `Tag`, `Slug`, `Element` by `is_a()` (subclasses included), the union `Content|Tag` as the string `'Content|Tag'`, and `Throwable` for error handlers only. dcore `HandlerDescriptorFactory` fills them from the resolved element.

## `#[RoutingHandler]` (`src/Attributes/RoutingHandler.php`)

`#[Attribute(TARGET_METHOD | TARGET_CLASS)]` (`:27`). `route` (string), `errorCode` (?int), `errorCodesRange` (`[min, max]`) (`:33-55`). `isErrorHandler()` is true with a code or a valid range (`:57-61`). An invalid range (not two values, or min > max) is logged with `error_log()` and ignored, leaving a normal route (`:38-47`).

```php
#[RoutingHandler(route: 'page')]
class PageHandler implements RequestHandlerInterface { … }          // construct mode, PSR-15

class BlogHandler
{
    #[RoutingHandler(route: 'article')]
    public function show(Request $request, Content $article): Response { … }   // method mode, Laravel style
}

#[RoutingHandler(route: 'error-404', errorCode: 404)]
class NotFoundHandler implements RequestHandlerInterface { … }

#[RoutingHandler(route: 'error-5xx', errorCodesRange: [500, 599])]
class ServerErrorHandler { public function __invoke(Throwable $exception, …) { … } }
```

Route names are the ones carried by the dcore Types (element route) and by the error elements used by `HandlerDescriptorFactory::fromError()`.

## Special-route handlers

### PSR-15 (`src/Handlers/`)

`AbstractTextHandler` (`AbstractTextHandler.php`): `ResponseFactoryInterface` + `StreamFactoryInterface` (`:30-33`); `handle()` finds the dcore `Host` with `HostHelper::findByHostname(request host)`, calls `generate(Host, request)`, and answers `200` with the subclass `contentType()` and the text as body, or `404` when the host is unknown or the service returned `null` (`:38-59`).

| Handler | dcore service call | Content-Type |
|---|---|---|
| `RobotsHandler` (`:26`) | `RobotsService::generate($host)` (`:43`) | `text/plain` |
| `SitemapHandler` (`:26`) | `SitemapService::generate($host, $uri->getHost(), $uri->getScheme())` (`:44`) | `application/xml` |
| `LlmHandler` (`:27`) | `LlmsService::generate($scheme, $host, $locale)` with `$locale` from the `locale` request attribute, `null` when absent (`:45-46`) | `text/plain; charset=utf-8` |
| `LlmFullHandler` | `LlmsService::generateFull($scheme, $host, $locale)`, same locale rule | `text/plain; charset=utf-8` |
| `MdHandler` (`:26`) | `MdService::generate($host, $uri->getHost(), $this->path, $uri->getScheme())`; `path` is the request path without the `.md` suffix, passed by the descriptor (`:45`) | `text/markdown; charset=utf-8` |
| `RedirectHandler` (`:25`) | none: `createResponse($httpCode)->withHeader('Location', $redirectUrl)` | — |

### Laravel (`src/Handlers/Laravel/`)

Same six classes, no interface, `handle(Request $request): Response` returning a Symfony `Response`: `new Response('', 404)` when the service returns `null` or the host is unknown, else `new Response($content, 200, ['Content-Type' => …])` (`MdHandler.php:32-46`, `SitemapHandler.php:31-45`, `RobotsHandler.php:31-43`, `LlmHandler.php:31`, `LlmFullHandler.php:31-47`); `RedirectHandler` answers `new Response('', $httpCode, ['Location' => $redirectUrl])` (`RedirectHandler.php:30`). The two llms handlers read `$request->attributes->get('locale')` (`LlmHandler.php:33`, `LlmFullHandler.php:33`).
