<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Ssr;

use Blackcube\Dcore\Entities\Content;
use Blackcube\Dcore\Entities\Slug;
use Blackcube\Dcore\Entities\Tag;
use Blackcube\Dcore\Helpers\HostHelper;
use Blackcube\Dcore\Helpers\Xeo;
use Blackcube\Dcore\Interfaces\JsonLdBuilderInterface;
use Blackcube\Dcore\Services\HandlerDescriptor;
use Blackcube\Dcore\Services\HandlerDescriptorFactory;
use Closure;
use Yiisoft\Injector\Injector;

/**
 * Common SSR routing logic shared by all framework middlewares.
 * Maps special routes, redirects, and CMS elements to HandlerDescriptors.
 * Framework-specific middlewares extend this and provide their handler classes
 * via getHandlerMap().
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
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
        protected readonly HandlerDescriptorFactory $handlerDescriptorFactory,
        protected readonly ?JsonLdBuilderInterface $jsonLdBuilder = null,
    ) {}

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
     * Find the handler that must answer a request path: a special route
     * (robots / sitemap / llms / llms-full / *.md), a redirect, or a CMS element.
     * Returns the HandlerDescriptor to run, or null to let the request pass through.
     */
    protected function findHandler(string $host, string $path): ?HandlerDescriptor
    {
        $descriptor = null;
        if ($this->isExcluded($path) === false) {
            $handlers = $this->getHandlerMap();
            $descriptor = $this->specialHandler($path, $handlers)
                ?? $this->slugHandler($host, $path, $handlers);
        }
        return $descriptor;
    }

    private function isExcluded(string $path): bool
    {
        $excluded = false;
        foreach ($this->excludedPrefixes as $prefix) {
            if (str_starts_with($path, $prefix) === true) {
                $excluded = true;
            }
        }
        return $excluded;
    }

    /**
     * Special-route handler: robots.txt / sitemap.xml / llms.txt / llms-full.txt / *.md.
     *
     * @param array<string, string> $handlers
     */
    private function specialHandler(string $path, array $handlers): ?HandlerDescriptor
    {
        $descriptor = null;
        $mdSuffix = $this->specialPaths['md-suffix'];
        if ($path === $this->specialPaths['robots'] && isset($handlers['robots']) === true) {
            $descriptor = $this->handlerDescriptorFactory->simple($handlers['robots'], 'construct');
        } elseif ($path === $this->specialPaths['sitemap'] && isset($handlers['sitemap']) === true) {
            $descriptor = $this->handlerDescriptorFactory->simple($handlers['sitemap'], 'construct');
        } elseif ($path === $this->specialPaths['llms'] && isset($handlers['llms']) === true) {
            $descriptor = $this->handlerDescriptorFactory->simple($handlers['llms'], 'construct');
        } elseif ($path === $this->specialPaths['llms-full'] && isset($handlers['llms-full']) === true) {
            $descriptor = $this->handlerDescriptorFactory->simple($handlers['llms-full'], 'construct');
        } elseif (str_ends_with($path, $mdSuffix) === true && isset($handlers['md']) === true) {
            $realPath = substr($path, 0, -strlen($mdSuffix));
            $descriptor = $this->handlerDescriptorFactory->simple(
                $handlers['md'],
                'construct',
                [],
                fn() => ['path' => $realPath],
            );
        }
        return $descriptor;
    }

    /**
     * Slug-based handler: look up the host + slug, then a redirect or the CMS element.
     *
     * @param array<string, string> $handlers
     */
    private function slugHandler(string $host, string $path, array $handlers): ?HandlerDescriptor
    {
        $descriptor = null;
        $hostModel = HostHelper::findByHostname($host);
        if ($hostModel !== null) {
            $slug = Slug::query()
                ->andWhere([
                    'hostId' => $hostModel->getId(),
                    'path' => $path,
                ])
                ->active()
                ->one();
            if ($slug !== null) {
                $isRedirect = $slug->getTargetUrl() !== null
                    && $slug->getHttpCode() !== null
                    && isset($handlers['redirect']) === true;
                if ($isRedirect === true) {
                    $redirectUrl = $slug->getLink()->withTemplate('host', $host)->getHref();
                    $httpCode = $slug->getHttpCode();
                    $descriptor = $this->handlerDescriptorFactory->simple(
                        $handlers['redirect'],
                        'construct',
                        [],
                        fn() => [
                            'redirectUrl' => $redirectUrl,
                            'httpCode' => $httpCode,
                        ],
                    );
                } else {
                    $element = $slug->getElement();
                    if ($element !== null) {
                        $descriptor = $this->handlerDescriptorFactory->fromElement($element);
                    }
                }
            }
        }
        return $descriptor;
    }

    /**
     * Extract slugId from CMS handler data (for xeo).
     */
    protected function extractSlugId(array $data): ?int
    {
        $slugId = null;
        foreach ($data as $value) {
            if ($slugId === null && ($value instanceof Content === true || $value instanceof Tag === true) && $value->getSlugId() !== null) {
                $slugId = $value->getSlugId();
            }
        }
        return $slugId;
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

        if ($this->xeoEnabled === true && $slugId !== null && $this->jsonLdBuilder !== null) {
            $xeo = Xeo::from($slugId);
            if ($xeo !== null) {
                $this->expandXeoLinks($xeo, $host);
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

        if ($this->mdAlternateEnabled === true && $slugId !== null && $path !== '') {
            $data['mdAlternateUrl'] = $scheme . '://' . $host . '/' . $path . '.md';
        }

        return $data;
    }

    protected function expandXeoLinks(Xeo $xeo, string $host): void
    {
        if ($xeo->canonicalLink !== null) {
            $xeo->canonicalLink = $xeo->canonicalLink->withTemplate('host', $host);
            $href = $xeo->canonicalLink->getHref();
            if (str_starts_with($href, '//') === true) {
                $xeo->canonicalLink = $xeo->canonicalLink->withHref('https:' . $href);
            }
        }

        foreach ($xeo->alternates as $i => $alternate) {
            $link = $alternate['link']->withTemplate('host', $host);
            $href = $link->getHref();
            if (str_starts_with($href, '//') === true) {
                $link = $link->withHref('https:' . $href);
            }
            $xeo->alternates[$i]['link'] = $link;
        }
    }
}
