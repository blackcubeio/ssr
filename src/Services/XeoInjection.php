<?php

declare(strict_types=1);

/**
 * XeoInjection.php
 *
 * PHP Version 8.1+
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Ssr\Services;

use Blackcube\Dcore\Helpers\Xeo;
use Blackcube\Dcore\Interfaces\JsonLdBuilderInterface;
use Blackcube\Dcore\Models\Content;
use Blackcube\Dcore\Models\Tag;
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
        int|Content|Tag $slugIdOrElement,
        ServerRequestInterface $request,
        ?JsonLdBuilderInterface $jsonLdBuilder = null,
        ?Closure $jsonLdTransformer = null,
        bool $mdAlternate = false,
    ): self {
        $instance = new self($request, $jsonLdTransformer, $mdAlternate);
        $instance->xeo = Xeo::from($slugIdOrElement);

        if (is_int($slugIdOrElement) && $jsonLdBuilder !== null) {
            $instance->jsonLds = $jsonLdBuilder->build($slugIdOrElement, $request->getUri()->getHost());
        }

        return $instance;
    }

    public function getMetaTags(): array
    {
        if ($this->xeo === null) {
            return [];
        }

        $tags = [];

        $robots = [];
        if ($this->xeo->noIndex === true) {
            $robots[] = 'noindex';
        }
        if ($this->xeo->noFollow === true) {
            $robots[] = 'nofollow';
        }
        if (empty($robots) === false) {
            $tags['robots'] = (new Meta())->name('robots')->content(implode(',', $robots));
        }

        if ($this->xeo->title !== null && $this->xeo->title !== '') {
            $tags['title'] = (new Meta())->name('title')->content($this->xeo->title);
            $tags['og:title'] = (new Meta())->attribute('property', 'og:title')->content($this->xeo->title);
        }

        if ($this->xeo->description !== null && $this->xeo->description !== '') {
            $tags['description'] = (new Meta())->name('description')->content($this->xeo->description);
            $tags['og:description'] = (new Meta())->attribute('property', 'og:description')->content($this->xeo->description);
        }

        if ($this->xeo->keywords !== null && $this->xeo->keywords !== '') {
            $keywordsList = implode(',', array_filter(array_map('trim', explode("\n", $this->xeo->keywords))));
            if ($keywordsList !== '') {
                $tags['keywords'] = (new Meta())->name('keywords')->content($keywordsList);
            }
        }

        if ($this->xeo->image !== null && $this->xeo->image !== '') {
            $imageUrl = (string) CacheFile::from($this->xeo->image);
            if ($imageUrl !== '') {
                if (str_starts_with($imageUrl, '/') === true) {
                    $uri = $this->request->getUri();
                    $imageUrl = $uri->getScheme() . '://' . $uri->getHost() . $imageUrl;
                }
                $tags['og:image'] = (new Meta())->attribute('property', 'og:image')->content($imageUrl);
            }
        }

        if ($this->xeo->og !== null) {
            $tags['og:type'] = (new Meta())->attribute('property', 'og:type')->content($this->xeo->og->type);
        }

        $host = $this->request->getUri()->getHost();
        if ($this->xeo->canonicalLink !== null) {
            $link = $this->xeo->canonicalLink;
            if ($link->isTemplated() === true) {
                $link = $link->withTemplate('host', $host);
            }
            $ogUrl = $link->getHref();
            if (str_starts_with($ogUrl, '//') === true) {
                $ogUrl = 'https:' . $ogUrl;
            }
        } else {
            $uri = $this->request->getUri();
            $ogUrl = $uri->getScheme() . '://' . $uri->getHost() . $uri->getPath();
        }
        $tags['og:url'] = (new Meta())->attribute('property', 'og:url')->content($ogUrl);

        if ($this->xeo->twitter !== null) {
            $tags['twitter:card'] = (new Meta())->name('twitter:card')->content($this->xeo->twitter->type);
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

        if ($this->xeo->canonicalLink !== null) {
            $link = $this->xeo->canonicalLink;
            if ($link->isTemplated() === true) {
                $link = $link->withTemplate('host', $host);
            }
            $href = $link->getHref();
            if (str_starts_with($href, '//') === true) {
                $href = 'https:' . $href;
            }
            $links['canonical'] = (new Link())->rel('canonical')->href($href);
        }

        if ($this->mdAlternate === true) {
            $path = ltrim($this->request->getUri()->getPath(), '/');
            if ($path !== '') {
                $uri = $this->request->getUri();
                $mdUrl = $uri->getScheme() . '://' . $uri->getHost() . '/' . $path . '.md';
                $links['alternate-md'] = (new Link())
                    ->rel('alternate')
                    ->href($mdUrl)
                    ->attribute('type', 'text/markdown');
            }
        }

        foreach ($this->xeo->alternates as $alternate) {
            $altLink = $alternate['link'];
            if ($altLink->isTemplated() === true) {
                $altLink = $altLink->withTemplate('host', $host);
            }
            $altHref = $altLink->getHref();
            if (str_starts_with($altHref, '//') === true) {
                $altHref = 'https:' . $altHref;
            }
            $links['hreflang-' . $alternate['language']] = (new Link())
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
        if (empty($this->jsonLds) === true) {
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
        if (empty($jsonLds) === true) {
            return [];
        }

        $jsStrings = [];
        foreach ($jsonLds as $i => $jsonLd) {
            $script = (new Script())
                ->type('application/ld+json')
                ->content(json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $jsStrings['jsonld-' . $i] = [$script, WebView::POSITION_HEAD];
        }

        return $jsStrings;
    }
}
