# Error handling

## FallbackHandler

Replaces the default 404 handler. When a CMS error handler is registered for status code 404, dispatches to it. Otherwise, delegates to the original Yii `NotFoundHandler`.

```php
FallbackHandler::class => [
    'class' => FallbackHandler::class,
    '__construct()' => [
        'defaultHandler' => Reference::to(NotFoundHandler::class),
    ],
],
```

The handler looks up the error handler via `HandlerRegistry::getErrorHandlerInfo(404)`, resolves CMS data through `HandlerDescriptor::findByError()`, dispatches via `AbstractProcessor::dispatch()`, and sets the response status to 404.

## ThrowableResponseFactory

Catches exceptions and dispatches to CMS error handlers based on HTTP status code. Decorates Yii's default `ThrowableResponseFactory`.

SSR does **not** register `ThrowableResponseFactoryInterface` — `yiisoft/error-handler` already provides a default binding. The application must override it in its own `config/web/di/`:

```php
ThrowableResponseFactoryInterface::class => [
    'class' => ThrowableResponseFactory::class,
    '__construct()' => [
        'defaultFactory' => Reference::to(YiiThrowableResponseFactory::class),
    ],
],
```

### Status code resolution

The exception code is used as the HTTP status code when it falls within the 400-599 range. Otherwise, defaults to 500.

### Dispatch chain

1. Resolve status code from the throwable
2. Look up a CMS error handler via `HandlerRegistry::getErrorHandlerInfo($statusCode)`
3. If found, dispatch to the CMS handler with `Throwable` injected alongside CMS data
4. If not found or dispatch fails, fall back to Yii's default factory

The `Throwable` is available to error handlers both by type (`Throwable::class`) and by name (`$exception`).

## Error handler registration

### Via attribute

```php
// Exact code
#[RoutingHandler(route: 'error-404', errorCode: 404)]
final class NotFoundHandler implements RequestHandlerInterface { }

// Code range
#[RoutingHandler(route: 'error-5xx', errorCodesRange: [500, 599])]
final class ServerErrorHandler { }
```

### Via config

```php
'configErrorHandlers' => [
    'error-404' => ['handler' => App\Handlers\NotFound::class, 'code' => 404],
    'error-5xx' => ['handler' => App\Handlers\ServerError::class, 'min' => 500, 'max' => 599],
],
```

### Resolution order

Error handlers are matched by:

1. **Exact match** — `errorCode` or `code` matches the status code
2. **Range fallback** — status code falls within `[min, max]` range
