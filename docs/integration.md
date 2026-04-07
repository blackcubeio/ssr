# Integration

## Architecture

The SSR package is built on a layered architecture:

| Layer | Class | Purpose |
|---|---|---|
| Abstract | `AbstractSsrRouting` | Shared resolve logic, fluent config, xeo data building |
| Interface | `SsrRoutingMiddlewareInterface` | PSR-15 contract (Yii, Slim) |
| Concrete | `YiiSsrRoutingMiddleware`, `SlimSsrRoutingMiddleware`, `LaravelSsrRoutingMiddleware` | Framework-specific dispatch and response building |
| Handlers | `Handlers\*`, `Handlers\Laravel\*` | Special route handlers (sitemap, robots, llms, md, redirect) |
| Error | `FallbackHandler`, `ThrowableResponseFactory` | Yii error handling (PSR-15) |

Each concrete middleware provides its handler classes via `getHandlerMap()` and implements its own `dispatch()` method.

### Middleware configuration

All implementations share the same immutable `with*()` API (defined in `AbstractSsrRouting`):

```php
$middleware
    ->withExcludedPrefixes('admin/', 'api/')
    ->withXeo()
    ->withMdAlternate();
```

## Yii3

### Config-plugin

The package auto-registers via `config-plugin`:

| Key | File | Content |
|---|---|---|
| `params-web` | `config/web/params.php` | scanAttributes, scanAliases, configHandlers, configErrorHandlers |
| `di-web` | `config/web/di.php` | RouteProviderInterface, SsrRouteProviderInterface, Xeo services |

`YiiSsrRoutingMiddleware`, `FallbackHandler`, and `ThrowableResponseFactory` are wired by the consumer application (see [Installation — Yii3 setup](installation.md#yii3-setup)).

### Xeo delivery

When Xeo is enabled, `YiiSsrRoutingMiddleware` creates an `XeoInjection` instance and adds it to the `WebViewRenderer` via `withAddedInjections()`. JSON-LD scripts are injected via `AssetManager::registerCustomized()` with `JsonLdAssetBundle`. The handler receives the modified `WebViewRenderer`.

## Slim

### Base definitions

The package provides `config/slim/definitions.php` with base PHP-DI definitions. The app imports and overrides:

```php
$blackcube = require '/path/to/vendor/blackcube/ssr/config/slim/definitions.php';
$app = [ /* overrides */ ];
return array_merge($blackcube, $app);
```

### Xeo delivery

When Xeo is enabled, `SlimSsrRoutingMiddleware` stores SEO data as PSR-7 request attributes (`xeo`, `jsonLds`, `ogUrl`, `mdAlternateUrl`). Handlers and templates access them via `$request->getAttribute()`.

### Handlers

Slim handlers implement `RequestHandlerInterface` with CMS entities in the constructor:

```php
#[RoutingHandler(route: 'page')]
final class PageHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly Twig $twig,
        private readonly Content|Tag $element,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->twig->render(new Response(), 'page.twig', [
            'element' => $this->element,
            'xeo' => $request->getAttribute('xeo'),
        ]);
    }
}
```

## Laravel

### Service provider

`Blackcube\Ssr\Laravel\BlackcubeServiceProvider` registers all Blackcube bindings (DB, cache, injector, FileProvider, CacheFile, handler registry, Xeo services, middleware). Configuration via `config/blackcube.php` (publishable).

### Xeo delivery

When Xeo is enabled, `LaravelSsrRoutingMiddleware` stores SEO data in `$request->attributes` (Symfony ParameterBag). Handlers pass them to Blade views.

### Handlers

Laravel handlers use method-level `#[RoutingHandler]` attributes with CMS entities as method parameters:

```php
final class PageHandler
{
    #[RoutingHandler(route: 'page')]
    public function handle(Request $request, Content|Tag $element): Response
    {
        return response()->view('page', [
            'element' => $element,
            'xeo' => $request->attributes->get('xeo'),
        ]);
    }
}
```

### Middleware registration

Register as global middleware in `bootstrap/app.php`:

```php
$middleware->append(LaravelSsrRoutingMiddleware::class);
```

## Quill helper

`Quill` cleans HTML output from the Quill rich text editor.

| Method | Description |
|---|---|
| `Quill::toRaw(?string $html, array $keepTags = ['p'])` | Strip all tags except `$keepTags`, after cleaning |
| `Quill::cleanHtml(?string $html, bool $removeStyles, bool $removeEmptyTags, bool $removeSpan)` | Remove inline styles, empty tags, and `<span>` wrappers |

```php
use Blackcube\Ssr\Helpers\Quill;

$clean = Quill::toRaw($content->description);
$html = Quill::cleanHtml($content->body, removeStyles: true, removeEmptyTags: true, removeSpan: true);
```

## Asset bundles

`JsonLdAssetBundle` is a Yii asset bundle used as a registration target for JSON-LD `<script>` tags. Populated dynamically via `AssetManager::registerCustomized()` in `YiiSsrRoutingMiddleware`.
