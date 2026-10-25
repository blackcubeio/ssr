# Locale management

## YiiLocaleManager

Centralized locale state for Yii apps. Single source for `set` / `get` / `applyLocale`. Session-backed persistence between requests. Validates against `Language::query()->active()` from dcore.

```php
use Blackcube\Ssr\YiiLocaleManager;

final class YiiLocaleManager
{
    public function set(string $lang): void;
    public function get(): string;
    public function applyLocale(ServerRequestInterface $request): void;
}
```

## Resolution order

`applyLocale()` is called at the start of every request by `YiiSsrRoutingMiddleware`. Order:

1. **Session** — `$session->get('locale')` from the previous request, if valid.
2. **Accept-Language** — last-resort fallback when the session has nothing. Parsed via `Locale::acceptFromHttp()` + `Locale::getPrimaryLanguage()`.
3. **Nothing** — the `LocaleProvider` keeps its default.

The URL path is **never** parsed. The locale is set strictly from a resolved CMS `Content`/`Tag`, then persisted in session for subsequent requests.

## Set / get cycle

| Step | Caller | Effect |
|---|---|---|
| 1 | `YiiSsrRoutingMiddleware::process()` | `$localeManager->applyLocale($request)` — restore from session/Accept-Language |
| 2 | `YiiSsrRoutingMiddleware::process()` | When a valid `Content`/`Tag` is resolved from a slug: `$localeManager->set($element->getLanguageId())` — posts to `LocaleProvider` and session |
| 2b | `YiiSsrRoutingMiddleware::process()` | Exposes the resolved locale as the `SsrRoutingMiddlewareInterface::LOCALE_ATTRIBUTE` request attribute, so path-less handlers (e.g. `LlmHandler`) can read it via `$request->getAttribute(...)`. Absent on Slim/Laravel, which have no locale cycle. |
| 3 | `YiiFallbackHandler` / `YiiThrowableResponseFactory` | Read `$localeProvider->get()->language()` (already posted upstream) and pass it to `HandlerDescriptor::fromError($route, $languageId)` |
| 4 | Error handlers | Render with the current locale. Fallbacks **never** set the locale. |

## Supported locales

Validated against the dcore `Language` table:

```php
Language::query()->active()->each()
```

The list is loaded **once per process** (static cache), so `isSupported()` is O(1) after the first call.

## Wiring

`YiiLocaleManager` is auto-instantiable — its dependencies are `LocaleProvider` (yiisoft/i18n), `SessionInterface` (yiisoft/session) and an optional `TranslatorInterface` (yiisoft/translator).

Inject it explicitly into `YiiSsrRoutingMiddleware` via DI:

```php
YiiSsrRoutingMiddleware::class => [
    'class' => YiiSsrRoutingMiddleware::class,
    '__construct()' => [
        'localeManager' => Reference::to(YiiLocaleManager::class),
    ],
],
```

`YiiSsrRoutingMiddleware` accepts `?YiiLocaleManager $localeManager = null` — passing `null` disables the locale cycle (legacy mode).

## Requirements

- `yiisoft/i18n` (`^2.0`) — `LocaleProvider`, `Locale`
- `yiisoft/session` (`^3.0`) — `SessionInterface`
- `yiisoft/translator` (optional) — `TranslatorInterface`, locale posted on set/applyLocale
- `SessionMiddleware` placed before `YiiSsrRoutingMiddleware` in the middleware stack
