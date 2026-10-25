# ssr — Extension

## Extension points

| Point | Where | Use |
|---|---|---|
| Excluded prefixes, xeo, JSON-LD transformer, markdown alternate | `with*()` on any middleware (`src/AbstractSsrRouting.php:64-98`) | per application, no code |
| Special paths | `withSpecialPaths(['robots' => …, 'sitemap' => …, 'llms' => …, 'llms-full' => …, 'md-suffix' => …])` (`:93-98`) | other file names or suffix |
| Disable a special route | subclass, `getHandlerMap()` without that key (`:141-161`) | serve `robots.txt` elsewhere |
| Other special-route handlers | subclass, `getHandlerMap()` returning your classes; a text handler extends `Handlers\AbstractTextHandler` and implements `contentType()` + `generate()` (`src/Handlers/AbstractTextHandler.php:35-36`) | different generation, other services |
| Another framework | extend `AbstractSsrRouting`, call `findHandler()`, `extractSlugId()`, `buildXeoData()`, write the dispatch (the Laravel class is the model, `src/LaravelSsrRoutingMiddleware.php`) | Mezzio, Symfony, … |
| Request / dispatch hooks on PSR | `prepareRequest()` and `prepareDispatch()` are `protected` (`src/PsrSsrRoutingMiddleware.php:81-126`); the Yii class is the example | locale, extra params, other attributes |
| Application handlers | `#[RoutingHandler]` on a class or a public method under `scanAliases`, or `configHandlers` / `configErrorHandlers` (config wins) | one handler per Type route, error routes by code or range |
| Handler parameters | any constructor / `__invoke()` / method parameter outside the tracked CMS types is resolved by the container through `Injector` | services next to the entities |
| Locale on PSR / Laravel | set the `locale` request attribute (`SsrRoutingMiddlewareInterface::LOCALE_ATTRIBUTE`) in your own middleware: the llms handlers read it (`src/Handlers/LlmHandler.php:45`, `src/Handlers/Laravel/LlmHandler.php:33`) | localized `llms.txt` outside Yii |
| JSON-LD | bind `JsonLdBuilderInterface` to another builder; or reshape with the `withXeo()` closure | other structured data |
| Yii error pages | `YiiFallbackHandler` and `YiiThrowableResponseFactory` wired by the application (`yii.md`) | CMS-rendered 404 / 5xx |
| Laravel provider | extend `Laravel\BlackcubeServiceProvider`, override `register()` / `boot()` and the published config | other database, other cache |
| `routes` param | `config/web/params.php:17-23` declares the special paths but `config/web/di.php` does not pass them: wiring it means `'withSpecialPaths()' => [$params['blackcube/ssr']['routes']]` on the `YiiSsrRoutingMiddleware` definition | to complete, not to remove |

## Not extensible

| Element | Reason |
|---|---|
| Resolution order (excluded → special → slug) | `findHandler()` is `protected` but `isExcluded()`, `specialHandler()`, `slugHandler()` are `private` (`src/AbstractSsrRouting.php:125-207`) |
| Slug lookup | `HostHelper::findByHostname()` + `Slug::query()…->active()->one()` inside `slugHandler()` (`:173-181`) |
| Redirect condition | `targetUrl` and `httpCode` both set (`:183-185`) |
| Tracked CMS types and modes | `private const CMS_TYPES` / `ERROR_TYPES`, `private` analysis methods of `HandlerRegistry` (`src/Services/HandlerRegistry.php:47-59`, `:200-296`) |
| Scan cache key and file discovery | `private const CACHE_KEY`, `private getFilesToScan()` / `getClassFromFile()` (`:42`, `:379-434`); one class per file, `.php` only |
| Meta / link tags set | `private buildMetaTags()`, fixed keys in `getLinkTags()` (`src/Services/XeoInjection.php:63-190`) |
| Request attribute names | `xeo`, `ogUrl`, `jsonLds`, `mdAlternateUrl`, `locale` hardcoded in the middlewares |
| Yii error dispatch | `private dispatch()` in `YiiFallbackHandler` and `YiiThrowableResponseFactory`; status forced to 404 / the throwable code |
| Locale sources | session then `Accept-Language`, session key `locale`, supported list static per process (`src/YiiLocaleManager.php:34-37`, `:70-97`) |
| Special-route content | produced by `blackcube/dcore` `Services\Xeo\*`: the ssr handlers only wrap the string |
