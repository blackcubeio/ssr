# Routing

## AbstractSsrRouting

Base class for all SSR routing middlewares. Provides `resolve()` which maps a request to a `HandlerDescriptor`, and `getHandlerMap()` which each concrete middleware implements to provide its framework-specific handler classes.

```
Request → excluded prefix check → special routes → slug lookup → dispatch → Response
                                       ↓                ↓
                              robots, sitemap,     redirect or
                              llms, md             CMS element
```

### Resolution order

1. Excluded prefixes → pass through
2. `robots.txt` → RobotsHandler
3. `sitemap.xml` → SitemapHandler
4. `llms.txt` → LlmHandler
5. `llms-full.txt` → LlmFullHandler
6. `*.md` → MdHandler
7. Slug lookup → redirect → RedirectHandler
8. Slug lookup → CMS element → app handler via `HandlerDescriptor::fromElement()`

Special routes and redirects use handlers from the SSR package (`Handlers/` for PSR-15, `Handlers/Laravel/` for Laravel). CMS element handlers are in the consumer application.

### Handler map

Each concrete middleware provides its handler classes:

```php
protected function getHandlerMap(): array
{
    return [
        'sitemap' => Handlers\SitemapHandler::class,
        'robots' => Handlers\RobotsHandler::class,
        'llms' => Handlers\LlmHandler::class,
        'llms-full' => Handlers\LlmFullHandler::class,
        'md' => Handlers\MdHandler::class,
        'redirect' => Handlers\RedirectHandler::class,
    ];
}
```

### Prefix exclusion

Paths that should bypass SSR (admin panel, API endpoints) are excluded via `withExcludedPrefixes()`:

```php
$middleware->withExcludedPrefixes('dboard/', 'api/')
```

Prefixes are matched without leading slash.

### Xeo toggle

SEO injection is opt-in:

```php
$middleware->withXeo()
```

When enabled, the middleware builds Xeo data (meta tags, JSON-LD, canonical) for CMS element pages. Delivery depends on the framework:
- **Yii3**: `XeoInjection` → view renderer injections + `AssetManager`
- **Slim**: PSR-7 request attributes (`xeo`, `jsonLds`, `ogUrl`)
- **Laravel**: Symfony request attributes (`$request->attributes`)

An optional JSON-LD transformer can reshape the data:

```php
$middleware->withXeo(function (array $jsonLds): array {
    return $jsonLds;
})
```

### Markdown alternate

Opt-in via `withMdAlternate()`. When enabled on a CMS element page, the middleware adds an HTTP `Link` header:

```
Link: <https://example.com/le-golf.md>; rel="alternate"; type="text/markdown"
```

### Special paths override

Default paths (`robots.txt`, `sitemap.xml`, `llms.txt`, `llms-full.txt`, `.md` suffix) can be overridden via `withSpecialPaths()`. Missing keys keep their default:

```php
$middleware->withSpecialPaths([
    'robots' => 'my-robots.txt',
    'sitemap' => 'seo/sitemap.xml',
])
```

Defaults come from the `blackcube/ssr.routes` config parameter.

## Special route handlers

The SSR package provides handlers for special routes. Data generation is delegated to dcore services (`Services/Xeo/`):

| Handler | Route | dcore service | Content-Type |
|---|---|---|---|
| `SitemapHandler` | `/sitemap.xml` | `SitemapService` | `application/xml` |
| `RobotsHandler` | `/robots.txt` | `RobotsService` | `text/plain` |
| `LlmHandler` | `/llms.txt` | `LlmsService::generate()` | `text/plain; charset=utf-8` |
| `LlmFullHandler` | `/llms-full.txt` | `LlmsService::generateFull()` | `text/plain; charset=utf-8` |
| `MdHandler` | `/{path}.md` | `MdService` | `text/markdown; charset=utf-8` |
| `RedirectHandler` | Slug with targetUrl | — | 301/302 redirect |

Each handler exists in two versions: `Handlers\*` (PSR-15 for Yii/Slim) and `Handlers\Laravel\*` (Laravel native).

### `SitemapHandler`

Generates `/sitemap.xml`. For each Content and Tag, checks that the Slug has an associated `Sitemap` model with `isActive() === true`. Only active Sitemaps are included. Uses `frequency` and `priority` from the Sitemap model (not hardcoded values).

### `RobotsHandler`

Generates `/robots.txt`. Reads the `GlobalXeo` of kind `"Robots"` for the current host.

### `LlmHandler`

Generates `/llms.txt`. Reads the `LlmMenu` tree (3 levels: root, categories, entries) and produces a structured text file for LLM discovery:

```
# Root name
> Root description

## Category name
> Category description

- [SEO title](https://host/path.md): SEO description
```

Entry links point to the `.md` alternate of each Content/Tag (served by `MdHandler`). Returns 404 if no root node exists.

### `LlmFullHandler`

Generates `/llms-full.txt`. Same tree structure as `LlmHandler`, but instead of link lines, each level 3 entry includes the full markdown content rendered by `ElasticMdService::renderMarkdown()`. Headings are shifted by +2 levels to respect the tree hierarchy (`#` → `###`, `##` → `####`).

### `MdHandler`

Serves `/{slug}.md`. Strips the `.md` suffix, resolves the Slug, and renders the linked Content/Tag as clean markdown via `ElasticMdService::renderMarkdown()`. Returns `text/markdown; charset=utf-8`.

The rendered markdown:
- Has no front matter, no delimiters, no LLM instructions
- Resolves `@blfs/` file references to public CacheFile URLs
- Resolves internal links to absolute URLs
- Removes images with missing files

### `RedirectHandler`

Redirects to `Slug::getTargetUrl()` with `Slug::getHttpCode()`.

## HandlerRegistry

Route registry that maps route names to handler classes. Merges attribute-scanned handlers with config-based handlers. Config takes precedence over attributes.

### Attribute scanning

When `scanAttributes` is `true`, the registry scans all PHP files in `scanAliases` directories for `#[RoutingHandler]` attributes on classes and public methods. Results are cached with `FileDependency`.

### Config handlers

```php
'configHandlers' => [
    'page' => App\Handlers\PageHandler::class,
    'article' => [App\Handlers\Blog::class, 'article'],
],
```

### Config error handlers

```php
'configErrorHandlers' => [
    'error-404' => ['handler' => App\Handlers\NotFound::class, 'code' => 404],
    'error-5xx' => ['handler' => App\Handlers\ServerError::class, 'min' => 500, 'max' => 599],
],
```

## RoutingHandler attribute

PHP 8 attribute to mark classes or methods as route handlers.

### Normal route (PSR-15 — class level)

```php
#[RoutingHandler(route: 'page')]
final class PageHandler implements RequestHandlerInterface { }
```

### Normal route (Laravel — method level)

```php
final class PageHandler
{
    #[RoutingHandler(route: 'page')]
    public function handle(Request $request, Content|Tag $element): Response { }
}
```

### Error handler

```php
#[RoutingHandler(route: 'error-404', errorCode: 404)]
final class NotFoundHandler { }

#[RoutingHandler(route: 'error-5xx', errorCodesRange: [500, 599])]
final class ServerErrorHandler { }
```

## Handler modes

The registry analyzes handler signatures to determine the dispatch mode:

| Mode | Detection | CMS injection |
|---|---|---|
| `construct` | Class implements `RequestHandlerInterface` | CMS objects injected via constructor, `handle()` called with request |
| `invoke` | Class has `__invoke()` method | CMS objects injected via `__invoke()` parameters |
| `method` | Attribute on method or config `[Class, 'method']` | CMS objects injected via method parameters |

Each middleware has its own `dispatch()` method. `FallbackHandler` and `ThrowableResponseFactory` also have their own dispatch (PSR-7).
