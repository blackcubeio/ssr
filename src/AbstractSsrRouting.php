<?php

declare(strict_types=1);

/**
 * AbstractSsrRouting.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Ssr;

use Blackcube\Dcore\Entities\Content;
use Blackcube\Dcore\Entities\Slug;
use Blackcube\Dcore\Entities\Tag;
use Blackcube\Dcore\Helpers\Xeo;
use Blackcube\Dcore\Interfaces\JsonLdBuilderInterface;
use Blackcube\Dcore\Services\HandlerDescriptor;
use Blackcube\Ssr\Interfaces\RouteProviderInterface;
use Closure;
use Yiisoft\Injector\Injector;

/**
 * Common SSR routing logic shared by all framework middlewares.
 * Resolves special routes, redirects, and CMS elements into HandlerDescriptors.
 * Framework-specific middlewares extend this and provide their handler classes
 * via getHandlerMap().
 */
abstract class AbstractSsrRouting
{
    protected bool $xeoEnabled = false;
    protected bool $mdAlternateEnabled = false;

    /** @var string[] */
    protected array $excludedPrefixes = [];

    /** @var Closure(array): array|null */
    protected ?Closure $jsonLdTransformer = null;

    /**
     * Special route paths. Overridable via withSpecialPaths().
     *
     * @var array<string, string>
     */
    protected array $specialPaths = [
        'robots' => 'robots.txt',
        'sitemap' => 'sitemap.xml',
        'llms' => 'llms.txt',
        'llms-full' => 'llms-full.txt',
        'md-suffix' => '.md',
    ];

    public function __construct(
        protected readonly Injector $injector,
        RouteProviderInterface $handlerRegistry,
        protected readonly ?JsonLdBuilderInterface $jsonLdBuilder = null,
    ) {
        HandlerDescriptor::setRouteResolver(
            fn(string $route) => $handlerRegistry->getHandlerInfo($route)
                ?? $handlerRegistry->getErrorHandlerInfoByRoute($route)
        );
    }

    public function withExcludedPrefixes(string ...$prefixes): static
    {
        $new = clone $this;
        $new->excludedPrefixes = $prefixes;
        return $new;
    }

    public function withXeo(?Closure $jsonLdTransformer = null): static
    {
        $new = clone $this;
        $new->xeoEnabled = true;
        $new->jsonLdTransformer = $jsonLdTransformer;
        return $new;
    }

    public function withMdAlternate(): static
    {
        $new = clone $this;
        $new->mdAlternateEnabled = true;
        return $new;
    }

    /**
     * Override one or more special route paths.
     * Keys: robots, sitemap, llms, llms-full, md-suffix.
     * Missing keys keep their default value.
     *
     * @param array<string, string> $paths
     */
    public function withSpecialPaths(array $paths): static
    {
        $new = clone $this;
        $new->specialPaths = array_merge($new->specialPaths, $paths);
        return $new;
    }

    /**
     * Handler class map — each concrete middleware provides its own classes.
     *
     * Expected keys: 'sitemap', 'robots', 'llms', 'llms-full', 'md', 'redirect'
     *
     * @return array<string, string>
     */
    abstract protected function getHandlerMap(): array;

    /**
     * Resolve a request path to a HandlerDescriptor or null (pass through).
     *
     * Resolution order:
     * 1. Excluded prefixes → null
     * 2. robots.txt → RobotsHandler
     * 3. sitemap.xml → SitemapHandler
     * 4. llms.txt → LlmHandler
     * 5. llms-full.txt → LlmFullHandler
     * 6. *.md → MdHandler
     * 7. Slug lookup → RedirectHandler
     * 8. Slug lookup → CMS element → HandlerDescriptor
     */
    protected function resolve(string $scheme, string $host, string $path): ?HandlerDescriptor
    {
        foreach ($this->excludedPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return null;
            }
        }

        $handlers = $this->getHandlerMap();

        // robots.txt
        if ($path === $this->specialPaths['robots'] && isset($handlers['robots'])) {
            return HandlerDescriptor::simple($handlers['robots'], 'construct');
        }

        // sitemap.xml
        if ($path === $this->specialPaths['sitemap'] && isset($handlers['sitemap'])) {
            return HandlerDescriptor::simple($handlers['sitemap'], 'construct');
        }

        // llms.txt
        if ($path === $this->specialPaths['llms'] && isset($handlers['llms'])) {
            return HandlerDescriptor::simple($handlers['llms'], 'construct');
        }

        // llms-full.txt
        if ($path === $this->specialPaths['llms-full'] && isset($handlers['llms-full'])) {
            return HandlerDescriptor::simple($handlers['llms-full'], 'construct');
        }

        // Markdown (.md)
        $mdSuffix = $this->specialPaths['md-suffix'];
        if (str_ends_with($path, $mdSuffix) && isset($handlers['md'])) {
            $realPath = substr($path, 0, -strlen($mdSuffix));
            return HandlerDescriptor::simple(
                $handlers['md'],
                'construct',
                [],
                fn() => ['path' => $realPath],
            );
        }

        // Slug lookup
        $hostModel = HandlerDescriptor::resolveHost($host);
        if ($hostModel === null) {
            return null;
        }

        $slug = Slug::query()
            ->andWhere(['hostId' => $hostModel->getId(), 'path' => $path])
            ->active()
            ->one();

        if ($slug === null) {
            return null;
        }

        // Redirect
        if ($slug->getTargetUrl() !== null && $slug->getHttpCode() !== null && isset($handlers['redirect'])) {
            $link = $slug->getLink();
            if ($link->isTemplated()) {
                $link = $link->withTemplate('host', $host);
            }
            $redirectUrl = $link->getHref();
            $httpCode = $slug->getHttpCode();

            return HandlerDescriptor::simple(
                $handlers['redirect'],
                'construct',
                [],
                fn() => ['redirectUrl' => $redirectUrl, 'httpCode' => $httpCode],
            );
        }

        // CMS element
        $element = $slug->getElement();
        if ($element === null) {
            return null;
        }

        return HandlerDescriptor::fromElement($element);
    }

    /**
     * Extract slugId from CMS handler data (for xeo).
     */
    protected function extractSlugId(array $data): ?int
    {
        foreach ($data as $value) {
            if (($value instanceof Content || $value instanceof Tag) && $value->getSlugId() !== null) {
                return $value->getSlugId();
            }
        }
        return null;
    }

    /**
     * Build xeo data for a CMS element.
     *
     * @return array{xeo: ?Xeo, ogUrl: ?string, jsonLds: array, mdAlternateUrl: ?string}
     */
    protected function buildXeoData(
        ?int $slugId,
        string $scheme,
        string $host,
        string $path,
        string $requestUrl,
    ): array {
        $data = [
            'xeo' => null,
            'ogUrl' => null,
            'jsonLds' => [],
            'mdAlternateUrl' => null,
        ];

        if ($this->xeoEnabled && $slugId !== null && $this->jsonLdBuilder !== null) {
            $xeo = Xeo::fromSlugId($slugId);
            if ($xeo !== null) {
                $this->resolveXeoLinks($xeo, $host);
                $data['xeo'] = $xeo;
                $data['ogUrl'] = $xeo->canonicalLink !== null
                    ? $xeo->canonicalLink->getHref()
                    : $requestUrl;
            }

            $jsonLds = $this->jsonLdBuilder->build($slugId, $host);
            if ($this->jsonLdTransformer !== null) {
                $jsonLds = ($this->jsonLdTransformer)($jsonLds);
            }
            $data['jsonLds'] = $jsonLds;
        }

        if ($this->mdAlternateEnabled && $slugId !== null && $path !== '') {
            $data['mdAlternateUrl'] = $scheme . '://' . $host . '/' . $path . '.md';
        }

        return $data;
    }

    protected function resolveXeoLinks(Xeo $xeo, string $host): void
    {
        if ($xeo->canonicalLink !== null) {
            if ($xeo->canonicalLink->isTemplated()) {
                $xeo->canonicalLink = $xeo->canonicalLink->withTemplate('host', $host);
            }
            $href = $xeo->canonicalLink->getHref();
            if (str_starts_with($href, '//')) {
                $xeo->canonicalLink = $xeo->canonicalLink->withHref('https:' . $href);
            }
        }

        foreach ($xeo->alternates as $i => $alternate) {
            $link = $alternate['link'];
            if ($link->isTemplated()) {
                $link = $link->withTemplate('host', $host);
            }
            $href = $link->getHref();
            if (str_starts_with($href, '//')) {
                $link = $link->withHref('https:' . $href);
            }
            $xeo->alternates[$i]['link'] = $link;
        }
    }
}
