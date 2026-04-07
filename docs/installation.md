# Installation

```bash
composer require blackcube/ssr
```

## Requirements

- PHP 8.1+
- A DB driver for `yiisoft/db` (`yiisoft/db-mysql`, `yiisoft/db-sqlite`, `yiisoft/db-pgsql`)

## Getting started

The fastest way is to bootstrap from a demo app:

| Framework | Demo repo |
|---|---|
| Yii3 | [blackcube/yii-app](https://github.com/blackcubeio/yii-app) |
| Slim | [blackcube/slim-app](https://github.com/blackcubeio/slim-app) |
| Laravel | [blackcube/laravel-app](https://github.com/blackcubeio/laravel-app) |

For integration into an existing app, `composer require blackcube/ssr` will surface framework-specific suggestions. The detailed setup per framework follows.

## Configuration

### Yii3 — config-plugin

The package uses `config-plugin` for automatic Yii3 registration:

| Config file | Content |
|---|---|
| `config/web/params.php` | Package parameters |
| `config/web/di.php` | RouteProviderInterface, SsrRouteProviderInterface, Xeo services |

### Parameters

```php
'blackcube/ssr' => [
    'scanAttributes' => true,           // scan for #[RoutingHandler] attributes
    'scanAliases' => [],                // aliases to scan (e.g. ['@app/Handlers'])
    'configHandlers' => [],             // route => handler class or [class, method]
    'configErrorHandlers' => [],        // route => error handler config
    'routes' => [                       // override default special route paths
        'robots' => 'robots.txt',
        'sitemap' => 'sitemap.xml',
        'llms' => 'llms.txt',
        'llms-full' => 'llms-full.txt',
        'md-suffix' => '.md',
    ],
],
```

## Yii3 setup

`YiiSsrRoutingMiddleware`, `FallbackHandler`, and `ThrowableResponseFactory` are **not** auto-registered. The consumer application must wire them in its own DI configuration.

```php
// web/di/application.php
use Blackcube\Ssr\FallbackHandler;
use Blackcube\Ssr\Interfaces\SsrRoutingMiddlewareInterface;
use Blackcube\Ssr\YiiSsrRoutingMiddleware;

return [
    SsrRoutingMiddlewareInterface::class => YiiSsrRoutingMiddleware::class,

    FallbackHandler::class => [
        'class' => FallbackHandler::class,
        '__construct()' => [
            'defaultHandler' => Reference::to(NotFoundHandler::class),
        ],
    ],

    Application::class => [
        '__construct()' => [
            'dispatcher' => DynamicReference::to([
                'class' => MiddlewareDispatcher::class,
                'withMiddlewares()' => [
                    [
                        ErrorCatcher::class,
                        SessionMiddleware::class,
                        // ...
                        static function (SsrRoutingMiddlewareInterface $middleware) use ($params): SsrRoutingMiddlewareInterface {
                            return $middleware
                                ->withExcludedPrefixes('dboard/', 'api/')
                                ->withXeo()
                                ->withMdAlternate();
                        },
                        Router::class,
                    ],
                ],
            ]),
        ],
    ],
];
```

## Slim setup

### Dependencies

```bash
composer require blackcube/ssr slim/slim php-di/php-di
```

### Blackcube Injector bootstrap

`blackcube/dcore` uses `Blackcube\Injector\Injector` — a static PSR-11 container accessor. **`Blackcube\Injector\Injector::init($container)` must be called once at application startup, before any dcore entity is used.**

### Container configuration

The SSR package provides base definitions in `config/slim/definitions.php`. The app imports them and overrides app-specific values:

```php
// config/container.php
$blackcube = require '/path/to/vendor/blackcube/ssr/config/slim/definitions.php';

$app = [
    // Database (required)
    ConnectionInterface::class => function () { ... },

    // Handler registry — override scanAliases
    RouteProviderInterface::class => autowire(HandlerRegistry::class)
        ->constructorParameter('scanAttributes', true)
        ->constructorParameter('scanAliases', [dirname(__DIR__) . '/src/Handlers']),

    // SSR middleware — override excludedPrefixes
    SsrRoutingMiddlewareInterface::class => function (ContainerInterface $c) {
        return $c->get(SlimSsrRoutingMiddleware::class)
            ->withExcludedPrefixes('api/')
            ->withXeo()
            ->withMdAlternate();
    },

    // FileProvider — override paths
    FileProvider::class => autowire()
        ->constructorParameter('filesystems', [...])
        ->constructorParameter('defaultAlias', '@blfs'),

    // CacheFile — override paths
    CacheFile::class => autowire()
        ->constructorParameter('cachePath', dirname(__DIR__) . '/www/assets')
        ->constructorParameter('cacheUrl', '/assets'),

    // View layer (Twig, etc.)
    Twig::class => function () { ... },
];

return array_merge($blackcube, $app);
```

### Middleware registration

```php
// www/index.php
$app->addRoutingMiddleware();
$app->add($container->get(SsrRoutingMiddlewareInterface::class));
$app->add(TwigMiddleware::createFromContainer($app, Twig::class));
$app->addErrorMiddleware(true, true, true);
```

### Accessing Xeo data in templates

`SlimSsrRoutingMiddleware` stores Xeo data as PSR-7 request attributes:

| Attribute | Type | Description |
|---|---|---|
| `xeo` | `Xeo\|null` | SEO metadata (title, description, OG, Twitter, canonical, alternates) |
| `jsonLds` | `array` | JSON-LD structured data |
| `ogUrl` | `string` | Canonical or request URL for og:url |
| `mdAlternateUrl` | `string` | Markdown alternate URL (when `withMdAlternate()` is enabled) |

## Laravel setup

### Dependencies

```bash
composer require blackcube/ssr laravel/framework httpsoft/http-message
```

### Service provider

The SSR package provides `Blackcube\Ssr\Laravel\BlackcubeServiceProvider` which registers all Blackcube bindings. Extend it in your app:

```php
// app/Providers/BlackcubeServiceProvider.php
namespace App\Providers;

use Blackcube\Ssr\Laravel\BlackcubeServiceProvider as BaseBlackcubeServiceProvider;

final class BlackcubeServiceProvider extends BaseBlackcubeServiceProvider
{
}
```

Register it in `bootstrap/providers.php`:

```php
return [
    App\Providers\BlackcubeServiceProvider::class,
];
```

### Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=blackcube-config
```

Override values in `config/blackcube.php`:

```php
return [
    'db' => [
        'driver' => env('DB_DRIVER', 'mysql'),
        'host' => env('DB_HOST', 'localhost'),
        'port' => (int) env('DB_PORT', 3306),
        'database' => env('DB_DATABASE'),
        'user' => env('DB_USER'),
        'password' => env('DB_PASSWORD'),
    ],
    'ssr' => [
        'scanAttributes' => true,
        'scanAliases' => [app_path('Handlers')],
        'excludedPrefixes' => ['api/'],
        'xeo' => true,
        'mdAlternate' => true,
    ],
    'fileProvider' => [
        'filesystems' => [
            '@blfs' => ['type' => 'local', 'path' => base_path('data')],
            '@bltmp' => ['type' => 'local', 'path' => storage_path('tmp')],
        ],
        'defaultAlias' => '@blfs',
    ],
    'cacheFile' => [
        'cachePath' => public_path('assets'),
        'cacheUrl' => '/assets',
    ],
];
```

### Middleware registration

Register as global middleware in `bootstrap/app.php`:

```php
use Blackcube\Ssr\LaravelSsrRoutingMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(LaravelSsrRoutingMiddleware::class);
    })
    ->create();
```

### Handlers

Laravel handlers use method-level `#[RoutingHandler]` attributes. CMS entities are injected as method parameters:

```php
use Blackcube\Ssr\Attributes\RoutingHandler;

final class LandingHandler
{
    #[RoutingHandler(route: 'landing')]
    public function handle(Request $request, Content|Tag $element): Response
    {
        return response()->view('landing', [
            'element' => $element,
            'xeo' => $request->attributes->get('xeo'),
            'jsonLds' => $request->attributes->get('jsonLds', []),
            'ogUrl' => $request->attributes->get('ogUrl'),
            'mdAlternateUrl' => $request->attributes->get('mdAlternateUrl'),
        ]);
    }
}
```

### Accessing Xeo data in Blade templates

```blade
@if ($xeo && $xeo->title)
    <meta name="title" content="{{ $xeo->title }}">
    <meta property="og:title" content="{{ $xeo->title }}">
@endif

@foreach ($jsonLds ?? [] as $jsonLd)
    <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endforeach

@if (!empty($mdAlternateUrl))
    <link rel="alternate" type="text/markdown" href="{{ $mdAlternateUrl }}">
@endif
```
