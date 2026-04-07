# Blackcube SSR

Server-side routing bridge for Blackcube CMS. Maps dcore slugs to PHP handlers, injects SEO metadata, serves special routes (sitemap, robots, llms, markdown) — framework-agnostic with Yii3, Slim, and Laravel implementations.

## Architecture

```
Request → SsrRoutingMiddleware → resolve() → HandlerDescriptor → dispatch → Response
                                    ↓
                          special routes (sitemap, robots, llms, md, redirect)
                          slug lookup → CMS element → handler
```

`AbstractSsrRouting` provides the shared `resolve()` logic. Each concrete middleware extends it, provides its framework-specific handler classes via `getHandlerMap()`, and dispatches the resolved handler.

| Implementation | Framework | Xeo delivery |
|---|---|---|
| `YiiSsrRoutingMiddleware` | Yii3 (PSR-15) | `XeoInjection` → view renderer injections + `AssetManager` |
| `SlimSsrRoutingMiddleware` | Slim (PSR-15) | `Xeo` + JSON-LD → PSR-7 request attributes |
| `LaravelSsrRoutingMiddleware` | Laravel | `Xeo` + JSON-LD → `$request->attributes` (Symfony ParameterBag) |

## Components

| Component | Description |
|---|---|
| `AbstractSsrRouting` | Shared resolve logic — special routes, redirect, CMS elements, xeo, fluent config |
| `SsrRoutingMiddlewareInterface` | PSR-15 interface — `withExcludedPrefixes()`, `withXeo()`, `withMdAlternate()` |
| `YiiSsrRoutingMiddleware` | Yii3 implementation — Xeo via view renderer injections |
| `SlimSsrRoutingMiddleware` | Slim implementation — Xeo via PSR-7 request attributes |
| `LaravelSsrRoutingMiddleware` | Laravel implementation — Xeo via Symfony request attributes |
| `Handlers\*` | PSR-15 handlers for special routes (sitemap, robots, llms, md, redirect) |
| `Handlers\Laravel\*` | Laravel-native handlers for special routes |
| `HandlerRegistry` | Route registry — attribute scanning + config, handler analysis, caching |
| `XeoInjection` | Yii SEO injection — meta/OG/Twitter tags, canonical link, JSON-LD, layout title |
| `FallbackHandler` | 404 handler — dispatches to CMS error handler or default |
| `ThrowableResponseFactory` | Error factory — resolves status code, dispatches to CMS error handler |
| `RoutingHandler` | PHP attribute — marks classes/methods as route or error handlers |
| `Quill` | HTML helper — cleans Quill editor output (styles, empty tags, spans) |
| `JsonLdAssetBundle` | Yii asset bundle — JSON-LD `<script>` tag injection in `<head>` |
| `Laravel\BlackcubeServiceProvider` | Laravel service provider — registers all Blackcube bindings |

## Documentation

- [Installation](installation.md) — requirements, Yii3 setup, Slim setup, Laravel setup
- [Routing](routing.md) — middleware, handler registry, attributes, dispatch modes
- [SEO](seo.md) — Xeo injection, meta tags, Open Graph, Twitter Cards, JSON-LD
- [Errors](errors.md) — fallback handler, throwable factory, error handler registration
- [Integration](integration.md) — PSR interfaces, Yii integration, Slim integration, Laravel integration, Quill helper
