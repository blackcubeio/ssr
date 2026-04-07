<?php

declare(strict_types=1);

/**
 * XeoInjection.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Ssr\Services;

use Blackcube\Dcore\Helpers\Xeo;
use Blackcube\Dcore\Interfaces\JsonLdBuilderInterface;
use Blackcube\FileProvider\CacheFile;
use Closure;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Html\Tag\Link;
use Yiisoft\Html\Tag\Meta;
use Yiisoft\Html\Tag\Script;
use Yiisoft\View\WebView;
use Yiisoft\Yii\View\Renderer\LayoutParametersInjectionInterface;
use Yiisoft\Yii\View\Renderer\LinkTagsInjectionInterface;
use Yiisoft\Yii\View\Renderer\MetaTagsInjectionInterface;

final class XeoInjection implements MetaTagsInjectionInterface, LinkTagsInjectionInterface, LayoutParametersInjectionInterface
{
    private ?Xeo $xeo = null;
    private array $jsonLds = [];

    private function __construct(
        private readonly ServerRequestInterface $request,
        private readonly ?Closure $jsonLdTransformer = null,
        private readonly bool $mdAlternate = false,
    ) {}

    public static function from(
        int $slugId,
        ServerRequestInterface $request,
        JsonLdBuilderInterface $jsonLdBuilder,
        ?Closure $jsonLdTransformer = null,
        bool $mdAlternate = false,
    ): self {
        $instance = new self($request, $jsonLdTransformer, $mdAlternate);
        $instance->xeo = Xeo::fromSlugId($slugId);

        $host = $request->getUri()->getHost();
        $instance->jsonLds = $jsonLdBuilder->build($slugId, $host);

        return $instance;
    }

    public function getMetaTags(): array
    {
        if ($this->xeo === null) {
            return [];
        }

        $tags = [];

        // Robots
        $robots = [];
        if ($this->xeo->noIndex) {
            $robots[] = 'noindex';
        }
        if ($this->xeo->noFollow) {
            $robots[] = 'nofollow';
        }
        if (!empty($robots)) {
            $tags['robots'] = Meta::tag()->name('robots')->content(implode(',', $robots));
        }

        // Title
        if ($this->xeo->title) {
            $tags['title'] = Meta::tag()->name('title')->content($this->xeo->title);
            $tags['og:title'] = Meta::tag()->attribute('property', 'og:title')->content($this->xeo->title);
        }

        // Description
        if ($this->xeo->description) {
            $tags['description'] = Meta::tag()->name('description')->content($this->xeo->description);
            $tags['og:description'] = Meta::tag()->attribute('property', 'og:description')->content($this->xeo->description);
        }

        // Keywords
        if ($this->xeo->keywords) {
            $keywordsList = implode(',', array_filter(array_map('trim', explode("\n", $this->xeo->keywords))));
            if ($keywordsList !== '') {
                $tags['keywords'] = Meta::tag()->name('keywords')->content($keywordsList);
            }
        }

        // Image (og:image)
        if ($this->xeo->image) {
            $imageUrl = (string) CacheFile::from($this->xeo->image);
            if ($imageUrl !== '') {
                if (str_starts_with($imageUrl, '/')) {
                    $uri = $this->request->getUri();
                    $imageUrl = $uri->getScheme() . '://' . $uri->getAuthority() . $imageUrl;
                }
                $tags['og:image'] = Meta::tag()->attribute('property', 'og:image')->content($imageUrl);
            }
        }

        // Open Graph
        if ($this->xeo->og !== null) {
            $tags['og:type'] = Meta::tag()->attribute('property', 'og:type')->content($this->xeo->og->type);
        }

        // og:url (canonical if available, otherwise current URL)
        $host = $this->request->getUri()->getHost();
        if ($this->xeo->canonicalLink !== null) {
            $link = $this->xeo->canonicalLink;
            if ($link->isTemplated()) {
                $link = $link->withTemplate('host', $host);
            }
            $ogUrl = $link->getHref();
            if (str_starts_with($ogUrl, '//')) {
                $ogUrl = 'https:' . $ogUrl;
            }
        } else {
            $ogUrl = (string) $this->request->getUri();
        }
        $tags['og:url'] = Meta::tag()->attribute('property', 'og:url')->content($ogUrl);

        // Twitter
        if ($this->xeo->twitter !== null) {
            $tags['twitter:card'] = Meta::tag()->name('twitter:card')->content($this->xeo->twitter->type);
        }

        return $tags;
    }

    public function getLayoutParameters(): array
    {
        if ($this->xeo === null) {
            return [];
        }

        $params = [];
        if ($this->xeo->title !== null) {
            $params['pageTitle'] = $this->xeo->title;
        }
        if ($this->xeo->language !== null) {
            $params['htmlLang'] = $this->xeo->language;
        }

        return $params;
    }

    public function getLinkTags(): array
    {
        if ($this->xeo === null) {
            return [];
        }

        $links = [];
        $host = $this->request->getUri()->getHost();

        // Canonical
        if ($this->xeo->canonicalLink !== null) {
            $link = $this->xeo->canonicalLink;
            if ($link->isTemplated()) {
                $link = $link->withTemplate('host', $host);
            }
            $href = $link->getHref();
            if (str_starts_with($href, '//')) {
                $href = 'https:' . $href;
            }
            $links['canonical'] = Link::tag()->rel('canonical')->href($href);
        }

        // Markdown alternate
        if ($this->mdAlternate) {
            $path = ltrim($this->request->getUri()->getPath(), '/');
            if ($path !== '') {
                $uri = $this->request->getUri();
                $mdUrl = $uri->getScheme() . '://' . $uri->getHost() . '/' . $path . '.md';
                $links['alternate-md'] = Link::tag()
                    ->rel('alternate')
                    ->href($mdUrl)
                    ->attribute('type', 'text/markdown');
            }
        }

        // Hreflang alternates
        foreach ($this->xeo->alternates as $alternate) {
            $altLink = $alternate['link'];
            if ($altLink->isTemplated()) {
                $altLink = $altLink->withTemplate('host', $host);
            }
            $altHref = $altLink->getHref();
            if (str_starts_with($altHref, '//')) {
                $altHref = 'https:' . $altHref;
            }
            $links['hreflang-' . $alternate['language']] = Link::tag()
                ->rel('alternate')
                ->href($altHref)
                ->attribute('hreflang', $alternate['language']);
        }

        return $links;
    }

    /**
     * @return array Array de JSON-LD (chaque élément = un array associatif)
     */
    public function getJsonLds(): array
    {
        if (empty($this->jsonLds)) {
            return [];
        }

        $jsonLds = $this->jsonLds;

        if ($this->jsonLdTransformer !== null) {
            $jsonLds = ($this->jsonLdTransformer)($jsonLds);
        }

        return $jsonLds;
    }

    /**
     * JSON-LD as jsStrings config for AssetBundle registration.
     *
     * @return array<string, array{0: Script, 1: int}> keyed jsStrings ready for registerCustomized()
     */
    public function getJsonLdJsStrings(): array
    {
        $jsonLds = $this->getJsonLds();
        if (empty($jsonLds)) {
            return [];
        }

        $jsStrings = [];
        foreach ($jsonLds as $i => $jsonLd) {
            $script = Script::tag()
                ->type('application/ld+json')
                ->content(json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $jsStrings['jsonld-' . $i] = [$script, WebView::POSITION_HEAD];
        }

        return $jsStrings;
    }
}
