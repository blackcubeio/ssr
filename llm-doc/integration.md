# ssr — Integration

## Split

- Package: the routing core, the three middlewares, the handler registry and attribute, the special-route handlers, the Yii SEO injection and error pieces, the Laravel provider.
- Application: a configured `blackcube/dcore` (connection, `Blackcube\Injector\Injector::init()`, cache), the application handlers (one per Type route, plus error routes), the view layer, PSR-17 factories for the PSR-15 handlers, the error wiring on Yii (`yii.md`), and the templates that render the SEO attributes on PSR / Laravel.

## Yii3 (config-plugin)

`config/configuration.php:12-15`: `params-web` → `web/params.php`, `di-web` → `web/di.php`.

| Param (`blackcube/ssr`, `config/web/params.php:12-24`) | Default | Use |
|---|---|---|
| `scanAttributes` | `true` | scan `scanAliases` for `#[RoutingHandler]` |
| `scanAliases` | `[]` | aliases of the handler directories, e.g. `['@src/Handlers']` |
| `configHandlers` | `[]` | `route => Class` or `[Class, 'method']` |
| `configErrorHandlers` | `[]` | `route => ['handler' => …, 'code' => …]` or `['handler' => …, 'min' => …, 'max' => …]` |
| `routes` | the five special paths | declared, not read by the package (see `extension.md`) |

`config/web/di.php:26-48`: `RouteProviderInterface` → `HandlerRegistry` with the four params; `SsrRouteProviderInterface` (dcore) → `RouteProviderInterface`; `YiiSsrRoutingMiddleware` with `localeManager => Reference::to(YiiLocaleManager::class)`; `SsrRoutingMiddlewareInterface` → `YiiSsrRoutingMiddleware`; `RobotsService`, `SitemapService`, `LlmsService`, `MdService` bound to themselves. `YiiLocaleManager` is autowired (`LocaleProvider`, `SessionInterface`, optional `TranslatorInterface`).

The application puts the middleware in its dispatcher, after `SessionMiddleware` and before `Router`, configured through a closure:

```php
static function (SsrRoutingMiddlewareInterface $middleware): SsrRoutingMiddlewareInterface {
    return $middleware
        ->withExcludedPrefixes('dboard/', 'api/')
        ->withXeo()
        ->withMdAlternate();
},
```

Suggested yiisoft packages for this binding (`composer.json:38-46`): aliases, assets, cache, html, i18n, request-provider, session, view, yii-view-renderer.

## PSR-15 (PHP-DI)

`config/psr/definitions.php:40-77` is a base definition set the application merges and overrides: `CacheInterface` (array cache), yiisoft `Injector` on the container, `JsonLdBuilderInterface` → dcore `JsonLdBuilder`, the dcore `ElasticMdService` and Xeo services, `RouteProviderInterface` → `HandlerRegistry` (`scanAttributes` true, `scanAliases` empty: override with the handler directory), `SsrRouteProviderInterface`, `HandlerDescriptorFactory`, `PsrSsrRoutingMiddleware`, `SsrRoutingMiddlewareInterface` → the PSR middleware `->withXeo()->withMdAlternate()`, `FileProvider` / `FileProviderInterface` (empty filesystems, `@blfs`), `CacheFile` (empty path, `/assets`). The application adds the database connection, the view layer and `Blackcube\Injector\Injector::init($container)` at startup, then `$app->add($container->get(SsrRoutingMiddlewareInterface::class))`. Handlers implement `RequestHandlerInterface` and receive the entities through their constructor (`construct` mode).

## Laravel

`Laravel\BlackcubeServiceProvider` (`src/Laravel/BlackcubeServiceProvider.php:53`): `register()` (`:55`) merges `config/laravel/blackcube.php` under `blackcube`, binds `Aliases`, the PSR-17 factories (`httpsoft/http-message`, `singletonIf`), a yiisoft/db MySQL `Driver` + `Connection` from `blackcube.db`, `CacheInterface`, yiisoft `Injector`, `JsonLdBuilderInterface`, `RouteProviderInterface` → `HandlerRegistry` with the `blackcube.ssr` params, `HandlerDescriptorFactory`, `FileProvider` and `CacheFile` from `blackcube.fileProvider` / `blackcube.cacheFile`, the dcore Xeo services, and `LaravelSsrRoutingMiddleware` built from `excludedPrefixes`, `xeo`, `mdAlternate`. `boot()` (`:132`) publishes the config under the `blackcube-config` tag, calls `Blackcube\Injector\Injector::init($this->app)` and `ConnectionProvider::set(...)`.

`config/laravel/blackcube.php:17-52`: `db` (`dsn`, `user`, `password` from `env()`), `ssr` (`scanAttributes`, `scanAliases`, `configHandlers`, `configErrorHandlers`, `excludedPrefixes = ['api/']`, `xeo = true`, `mdAlternate = true`), `fileProvider` (`@blfs` → `base_path('data')`, `@bltmp` → `storage_path('tmp')`), `cacheFile` (`public_path('assets')`, `/assets`). The application extends the provider, lists it in `bootstrap/providers.php`, appends `LaravelSsrRoutingMiddleware` in `bootstrap/app.php`, and writes handlers with method-level attributes (`method` mode). `illuminate/http` is a `suggest` (`composer.json:51`).

## `Helpers\Quill` (`src/Helpers/Quill.php`)

Static helper for HTML produced by the Quill editor. `cleanHtml(?string, bool $removeStyles = true, bool $removeEmptyTags = true, bool $removeSpan = true): ?string` (`:49-78`): drops `style="…"`, the `ql-ui` spans, rewrites `<ol>` lists whose items carry `data-list="bullet"` into `<ul>` and strips the `data-list` attributes (`:62-69`, `:80-85`), removes empty tags, unwraps `<span>`. `toRaw(?string, array $keepTags = ['p']): ?string` (`:27-38`): `cleanHtml()` then `strip_tags()` keeping `$keepTags`.

## Tests

Two Codeception suites, `Unit` (`HandlerRegistryCest`, `YiiFallbackHandlerCest`, `YiiThrowableResponseFactoryCest`) and `Integration` (`FindHandlerCest`), run with `vendor/bin/codecept run`; `tests/_bootstrap.php` builds the container and the database connection from the `.env` (`vlucas/phpdotenv`, `yiisoft/db-mysql` in `require-dev`).
