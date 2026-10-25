<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
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

/**
 * Injects the Xeo metadata of the current page into the view: meta tags, link tags, layout parameters and JSON-LD.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class XeoInjection implements MetaTagsInjectionInterface, LinkTagsInjectionInterface, LayoutParametersInjectionInterface
{
    private ?Xeo $xeo = null;
    private array $jsonLds = [];

    public function __construct(
        int|Content|Tag $slugIdOrElement,
        private readonly ServerRequestInterface $request,
        ?JsonLdBuilderInterface $jsonLdBuilder = null,
        private readonly ?Closure $jsonLdTransformer = null,
        private readonly bool $mdAlternate = false,
    ) {
        $this->xeo = Xeo::from($slugIdOrElement);

        if (is_int($slugIdOrElement) === true && $jsonLdBuilder !== null) {
            $this->jsonLds = $jsonLdBuilder->build($slugIdOrElement, $request->getUri()->getHost());
        }
    }

    public function getMetaTags(): array
    {
        $tags = [];
        if ($this->xeo !== null) {
            $tags = $this->buildMetaTags($this->xeo);
        }
        return $tags;
    }

    private function buildMetaTags(Xeo $xeo): array
    {
        $tags = [];

        $robots = [];
        if ($xeo->noIndex === true) {
            $robots[] = 'noindex';
        }
        if ($xeo->noFollow === true) {
            $robots[] = 'nofollow';
        }
        if (empty($robots) === false) {
            $tags['robots'] = (new Meta())->name('robots')->content(implode(',', $robots));
        }

        if ($xeo->title !== null && $xeo->title !== '') {
            $tags['title'] = (new Meta())->name('title')->content($xeo->title);
            $tags['og:title'] = (new Meta())->attribute('property', 'og:title')->content($xeo->title);
        }

        if ($xeo->description !== null && $xeo->description !== '') {
            $tags['description'] = (new Meta())->name('description')->content($xeo->description);
            $tags['og:description'] = (new Meta())->attribute('property', 'og:description')->content($xeo->description);
        }

        if ($xeo->keywords !== null && $xeo->keywords !== '') {
            $keywords = [];
            foreach (explode("\n", $xeo->keywords) as $keyword) {
                $keyword = trim($keyword);
                if (empty($keyword) === false) {
                    $keywords[] = $keyword;
                }
            }
            $keywordsList = implode(',', $keywords);
            if ($keywordsList !== '') {
                $tags['keywords'] = (new Meta())->name('keywords')->content($keywordsList);
            }
        }

        if ($xeo->image !== null && $xeo->image !== '') {
            $imageUrl = (string) CacheFile::from($xeo->image);
            if ($imageUrl !== '') {
                if (str_starts_with($imageUrl, '/') === true) {
                    $uri = $this->request->getUri();
                    $imageUrl = $uri->getScheme() . '://' . $uri->getHost() . $imageUrl;
                }
                $tags['og:image'] = (new Meta())->attribute('property', 'og:image')->content($imageUrl);
            }
        }

        if ($xeo->og !== null) {
            $tags['og:type'] = (new Meta())->attribute('property', 'og:type')->content($xeo->og->type);
        }

        $host = $this->request->getUri()->getHost();
        if ($xeo->canonicalLink !== null) {
            $ogUrl = $xeo->canonicalLink->withTemplate('host', $host)->getHref();
            if (str_starts_with($ogUrl, '//') === true) {
                $ogUrl = 'https:' . $ogUrl;
            }
        } else {
            $uri = $this->request->getUri();
            $ogUrl = $uri->getScheme() . '://' . $uri->getHost() . $uri->getPath();
        }
        $tags['og:url'] = (new Meta())->attribute('property', 'og:url')->content($ogUrl);

        if ($xeo->twitter !== null) {
            $tags['twitter:card'] = (new Meta())->name('twitter:card')->content($xeo->twitter->type);
        }

        return $tags;
    }

    public function getLayoutParameters(): array
    {
        $params = [];
        if ($this->xeo !== null) {
            if ($this->xeo->title !== null) {
                $params['pageTitle'] = $this->xeo->title;
            }
            if ($this->xeo->language !== null) {
                $params['htmlLang'] = $this->xeo->language;
            }
        }

        return $params;
    }

    public function getLinkTags(): array
    {
        $links = [];
        if ($this->xeo !== null) {
            $host = $this->request->getUri()->getHost();

            if ($this->xeo->canonicalLink !== null) {
                $href = $this->xeo->canonicalLink->withTemplate('host', $host)->getHref();
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
                $altHref = $alternate['link']->withTemplate('host', $host)->getHref();
                if (str_starts_with($altHref, '//') === true) {
                    $altHref = 'https:' . $altHref;
                }
                $links['hreflang-' . $alternate['language']] = (new Link())
                    ->rel('alternate')
                    ->href($altHref)
                    ->attribute('hreflang', $alternate['language']);
            }
        }

        return $links;
    }

    /**
     * @return array Array of JSON-LD (each element is an associative array)
     */
    public function getJsonLds(): array
    {
        $jsonLds = $this->jsonLds;

        if (empty($jsonLds) === false && $this->jsonLdTransformer !== null) {
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

        $jsStrings = [];
        foreach ($jsonLds as $i => $jsonLd) {
            $script = (new Script())
                ->type('application/ld+json')
                ->content(json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $jsStrings['jsonld-' . $i] = [
                $script,
                WebView::POSITION_HEAD,
            ];
        }

        return $jsStrings;
    }
}
