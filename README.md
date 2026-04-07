# Blackcube SSR

Server-side routing bridge for Blackcube CMS. Maps dcore slugs to PHP handlers, injects SEO metadata, handles errors — all through PSR-15 middleware.

[![License](https://img.shields.io/badge/license-BSD--3--Clause-blue.svg)](LICENSE.md)
[![Packagist Version](https://img.shields.io/packagist/v/blackcube/ssr.svg)](https://packagist.org/packages/blackcube/ssr)

## Where ssr sits

```
┌────────────────────────────┐
│ your app (Yii/Slim/Laravel) │
└──────────────┬─────────────┘
               ↓
       ┌──────────────────────┐
       │ ssr ← you are here    │
       │ routing, SEO, handlers│
       └───────────┬──────────┘
                   ↓
             ┌──────────┐
             │  dcore   │
             │ (data)   │
             └──────────┘
                   ↓
                   DB
```

## Quickstart

```bash
composer require blackcube/ssr
```

```php
#[RoutingHandler(route: 'page')]
final class PageHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly Content $content,
        private readonly WebViewRenderer $viewRenderer,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->viewRenderer->render('page', ['content' => $this->content]);
    }
}
```

## Documentation

- [Installation](docs/installation.md) — requirements, configuration, DI wiring
- [Routing](docs/routing.md) — middleware, handler registry, attributes, dispatch modes
- [SEO](docs/seo.md) — Xeo injection, meta tags, Open Graph, Twitter Cards, JSON-LD
- [Errors](docs/errors.md) — fallback handler, throwable factory, error handler registration
- [Integration](docs/integration.md) — PSR and Yii integration, Quill helper

## License

BSD-3-Clause. See [LICENSE.md](LICENSE.md).
