# ssr — SEO

## Yii3: `XeoInjection` (`src/Services/XeoInjection.php`)

Implements the three yii-view-renderer injection interfaces: `MetaTagsInjectionInterface`, `LinkTagsInjectionInterface`, `LayoutParametersInjectionInterface` (`:35`). Constructor (`:40-52`): `int|Content|Tag $slugIdOrElement`, `ServerRequestInterface $request`, `?JsonLdBuilderInterface`, `?Closure $jsonLdTransformer`, `bool $mdAlternate = false`. `Xeo::from($slugIdOrElement)` gives the metadata; JSON-LD is built only when an `int` slug id and a builder are given (`:49-51`), so the error pages (`YiiFallbackHandler`, `YiiThrowableResponseFactory`, which pass the element) carry no JSON-LD.

### `getMetaTags()` (`:54-134`), keyed `yiisoft/html` `Meta` tags

| Key | Emitted when | Content |
|---|---|---|
| `robots` | `noIndex` or `noFollow` | `noindex`, `nofollow`, comma-joined (`:67-76`) |
| `title`, `og:title` | non-empty title | the Xeo title (`:78-81`) |
| `description`, `og:description` | non-empty description | the Xeo description (`:83-86`) |
| `keywords` | non-empty keywords | the newline-separated keywords trimmed and comma-joined (`:88-100`) |
| `og:image` | non-empty image and `CacheFile::from($image)` non-empty | the cache URL, prefixed with `scheme://host` when it starts with `/` (`:102-111`) |
| `og:type` | `$xeo->og !== null` | `$xeo->og->type` (`:113-115`) |
| `og:url` | always | the canonical link on the request host (`//` → `https://`), else `scheme://host/path` (`:117-127`) |
| `twitter:card` | `$xeo->twitter !== null` | `$xeo->twitter->type` (`:129-131`) |

### `getLinkTags()` (`:151-190`), keyed `Link` tags

- `canonical`: `rel="canonical"` on the request host, `//` → `https://` (`:157-163`).
- `alternate-md`: with `$mdAlternate` and a non-empty request path, `rel="alternate" type="text/markdown" href="scheme://host/path.md"` (`:165-175`).
- `hreflang-<lang>`: one per `$xeo->alternates` entry, `rel="alternate" hreflang="<lang>"` on the request host (`:177-186`).

### `getLayoutParameters()` (`:136-149`)

`pageTitle` = Xeo title, `htmlLang` = `$xeo->language`, each when not null.

### JSON-LD (`:195-227`)

`getJsonLds()` applies the transformer when the list is not empty (`:195-204`). `getJsonLdJsStrings()` returns `['jsonld-<i>' => [Script(type application/ld+json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), WebView::POSITION_HEAD]]` (`:211-227`), the shape expected by `AssetManager::registerCustomized(JsonLdAssetBundle::class, ['jsStrings' => …])` from `YiiSsrRoutingMiddleware` (`src/YiiSsrRoutingMiddleware.php:84-89`). `Assets\JsonLdAssetBundle` is an empty `AssetBundle` with `jsPosition = WebView::POSITION_HEAD` (`src/Assets/JsonLdAssetBundle.php:24-26`).

## PSR-15 and Laravel: request attributes

No HTML is produced; the template renders the tags from the attributes set by the middleware (`src/PsrSsrRoutingMiddleware.php:103-113`, `src/LaravelSsrRoutingMiddleware.php:58-67`):

| Attribute | Type | Set when |
|---|---|---|
| `xeo` | dcore `Helpers\Xeo` with expanded links | xeo enabled and `Xeo::from($slugId)` not null |
| `ogUrl` | `string` | same: canonical href, else the request URL |
| `jsonLds` | `array` of arrays | xeo enabled and the builder returned at least one item |
| `mdAlternateUrl` | `string` | markdown alternate enabled and non-empty path |

PSR: `$request->getAttribute('xeo')`; Laravel: `$request->attributes->get('xeo')`.

## Markdown alternate

Enabled by `withMdAlternate()`. On a CMS element page every middleware adds the header `Link: <scheme://host/path.md>; rel="alternate"; type="text/markdown"` (`src/PsrSsrRoutingMiddleware.php:67-72`, `src/LaravelSsrRoutingMiddleware.php:77-83`); Yii also emits the `<link>` tag through `XeoInjection`; PSR and Laravel expose `mdAlternateUrl` for the template. The `.md` URL itself is served by `MdHandler` (`handlers.md`).
