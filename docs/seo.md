# SEO — Xeo injection

## Yii3 — XeoInjection

Reads the Xeo metadata attached to a slug and injects SEO tags into the Yii view layer. Created by `YiiSsrRoutingMiddleware` when Xeo is enabled.

```php
XeoInjection::from(int $slugId, ServerRequestInterface $request, JsonLdBuilderInterface $jsonLdBuilder, ?Closure $jsonLdTransformer, bool $mdAlternate)
```

Implements three Yii view renderer injection interfaces:

| Interface | Purpose |
|---|---|
| `MetaTagsInjectionInterface` | `<meta>` tags (robots, title, description, OG, Twitter) |
| `LinkTagsInjectionInterface` | `<link>` tags (canonical, hreflang, markdown alternate) |
| `LayoutParametersInjectionInterface` | Layout parameters (`pageTitle`, `htmlLang`) |

## Slim — request attributes

`SlimSsrRoutingMiddleware` stores raw Xeo data as PSR-7 request attributes. See [Installation — Accessing Xeo data in templates](installation.md#accessing-xeo-data-in-templates).

| Attribute | Type | Content |
|---|---|---|
| `xeo` | `Xeo` | Title, description, image, robots, canonical, OG, Twitter, alternates |
| `jsonLds` | `array` | JSON-LD structured data arrays |
| `ogUrl` | `string` | Canonical or request URL |
| `mdAlternateUrl` | `string` | Markdown alternate URL |

## Laravel — request attributes

`LaravelSsrRoutingMiddleware` stores Xeo data in `$request->attributes` (Symfony ParameterBag). Access via `$request->attributes->get('xeo')` in handlers, or directly as view variables in Blade templates. See [Installation — Laravel setup](installation.md#laravel-setup).

## Meta tags

### Robots

Generated when `noindex` or `nofollow` is set in Xeo:

```html
<meta name="robots" content="noindex,nofollow">
```

### Title and description

```html
<meta name="title" content="...">
<meta name="description" content="...">
```

Both also generate their Open Graph equivalents (`og:title`, `og:description`).

### Keywords

Xeo keywords (newline-separated) are joined into a comma-separated `<meta name="keywords">` tag.

### Open Graph

When OG is enabled in Xeo:

| Tag | Source |
|---|---|
| `og:title` | Xeo title |
| `og:description` | Xeo description |
| `og:image` | Xeo image |
| `og:type` | Xeo OG type |

### Twitter Cards

When Twitter is enabled in Xeo:

```html
<meta name="twitter:card" content="summary_large_image">
```

## Link tags

### Canonical

When the Xeo has a canonical link, a `<link rel="canonical">` tag is generated. Templated links resolve the `host` placeholder from the current request. Protocol-relative URLs (`//...`) are prefixed with `https:`.

### Hreflang alternates

When the content has translations (via `TranslationGroup`), hreflang `<link>` tags are generated for each language version:

```html
<link href="https://example.com/le-golf" rel="alternate" hreflang="fr">
<link href="https://example.com/golfing" rel="alternate" hreflang="en">
```

### Markdown alternate

Opt-in via `withMdAlternate()`. Advertises the markdown version (`/{slug}.md` served by `MdHandler` in the SSR package):

- **HTTP header** (all implementations):

```
Link: <https://example.com/le-golf.md>; rel="alternate"; type="text/markdown"
```

- **HTML tag** (Yii3 via `XeoInjection::getLinkTags()`):

```html
<link type="text/markdown" href="https://example.com/le-golf.md" rel="alternate">
```

- **Request attribute** (Slim via `mdAlternateUrl`, Laravel via `$request->attributes`): the template renders the `<link>` tag.

## Layout parameters

| Parameter | Source | Description |
|---|---|---|
| `pageTitle` | Xeo title | Page title for `<title>` tag |
| `htmlLang` | Content language | Language code for `<html lang="...">` |

## JSON-LD

JSON-LD data is built via `JsonLdBuilderInterface` from dcore and optionally transformed by a callback:

```php
$middleware->withXeo(function (array $jsonLds): array {
    return $jsonLds;
})
```

### Yii3 — JsonLdAssetBundle

JSON-LD scripts are injected in `<head>` via `AssetManager::registerCustomized()` with `JsonLdAssetBundle`.

### Slim / Laravel — template rendering

JSON-LD is available via request attributes. The template renders the `<script>` tags directly.
