<?php

declare(strict_types=1);

namespace Blackcube\Ssr\Tests\Integration;

use Blackcube\Dcore\Entities\Content;
use Blackcube\Dcore\Entities\Host;
use Blackcube\Dcore\Entities\Slug;
use Blackcube\Dcore\Entities\Type;
use Blackcube\Dcore\Models\GlobalXeo;
use Blackcube\Dcore\Models\ElasticSchema;
use Blackcube\Dcore\Services\HandlerDescriptor;
use Blackcube\Ssr\Handlers;
use Blackcube\Ssr\Interfaces\RouteProviderInterface;
use Blackcube\Ssr\LaravelSsrRoutingMiddleware;
use Blackcube\Ssr\Services\HandlerRegistry;
use Blackcube\Ssr\SlimSsrRoutingMiddleware;
use Blackcube\Ssr\Tests\Support\DatabaseCestTrait;
use Blackcube\Ssr\Tests\Support\IntegrationTester;
use Blackcube\Ssr\YiiSsrRoutingMiddleware;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Cache\ArrayCache;
use Yiisoft\Cache\Cache;
use Yiisoft\Injector\Injector;

/**
 * Integration tests for AbstractSsrRouting::resolve() — Yii, Slim, Laravel.
 * Tests the same resolve() logic through each concrete middleware to verify
 * each framework's handler map produces the correct HandlerDescriptor.
 */
final class ResolveCest
{
    use DatabaseCestTrait;

    private function createRegistry(): HandlerRegistry
    {
        return new HandlerRegistry(
            new Aliases(),
            new Cache(new ArrayCache()),
            scanAttributes: true,
            scanAliases: [dirname(__DIR__) . '/Support/Stub/Handlers'],
        );
    }

    private function createInjector(): Injector
    {
        return new Injector(new \Yiisoft\Di\Container(\Yiisoft\Di\ContainerConfig::create()->withDefinitions([
            \Yiisoft\Db\Connection\ConnectionInterface::class => $this->db,
        ])));
    }

    /**
     * Create all three middlewares configured identically.
     * @return array{yii: YiiSsrRoutingMiddleware, slim: SlimSsrRoutingMiddleware, laravel: LaravelSsrRoutingMiddleware}
     */
    private function createMiddlewares(): array
    {
        $registry = $this->createRegistry();
        $injector = $this->createInjector();

        return [
            'yii' => (new YiiSsrRoutingMiddleware($injector, $registry))
                ->withExcludedPrefixes('admin/')
                ->withXeo()
                ->withMdAlternate(),
            'slim' => (new SlimSsrRoutingMiddleware($injector, $registry))
                ->withExcludedPrefixes('admin/')
                ->withXeo()
                ->withMdAlternate(),
            'laravel' => (new LaravelSsrRoutingMiddleware($injector, $registry))
                ->withExcludedPrefixes('admin/')
                ->withXeo()
                ->withMdAlternate(),
        ];
    }

    /**
     * Call resolve() via reflection (it's protected).
     */
    private function resolve(object $middleware, string $scheme, string $host, string $path): ?HandlerDescriptor
    {
        $ref = new \ReflectionMethod($middleware, 'resolve');
        return $ref->invoke($middleware, $scheme, $host, $path);
    }

    // ========================================
    // Excluded prefix → null
    // ========================================

    public function testExcludedPrefixReturnsNull(IntegrationTester $I): void
    {
        foreach ($this->createMiddlewares() as $name => $middleware) {
            $result = $this->resolve($middleware, 'https', 'localhost', 'admin/dashboard');
            $I->assertNull($result, "$name: excluded prefix should return null");
        }
    }

    // ========================================
    // robots.txt → RobotsHandler
    // ========================================

    public function testRobotsTxtResolvesToHandler(IntegrationTester $I): void
    {
        $middlewares = $this->createMiddlewares();

        foreach ($middlewares as $name => $middleware) {
            $result = $this->resolve($middleware, 'https', 'localhost', 'robots.txt');
            $I->assertNotNull($result, "$name: robots.txt should resolve");

            $expectedClass = match ($name) {
                'laravel' => Handlers\Laravel\RobotsHandler::class,
                default => Handlers\RobotsHandler::class,
            };
            $I->assertSame($expectedClass, $result->getClass(), "$name: wrong handler class");
        }
    }

    // ========================================
    // sitemap.xml → SitemapHandler
    // ========================================

    public function testSitemapXmlResolvesToHandler(IntegrationTester $I): void
    {
        $middlewares = $this->createMiddlewares();

        foreach ($middlewares as $name => $middleware) {
            $result = $this->resolve($middleware, 'https', 'localhost', 'sitemap.xml');
            $I->assertNotNull($result, "$name: sitemap.xml should resolve");

            $expectedClass = match ($name) {
                'laravel' => Handlers\Laravel\SitemapHandler::class,
                default => Handlers\SitemapHandler::class,
            };
            $I->assertSame($expectedClass, $result->getClass(), "$name: wrong handler class");
        }
    }

    // ========================================
    // *.md → MdHandler + correct path
    // ========================================

    public function testMdResolvesToHandlerWithPath(IntegrationTester $I): void
    {
        $middlewares = $this->createMiddlewares();

        foreach ($middlewares as $name => $middleware) {
            $result = $this->resolve($middleware, 'https', 'localhost', 'mon-article.md');
            $I->assertNotNull($result, "$name: .md should resolve");

            $expectedClass = match ($name) {
                'laravel' => Handlers\Laravel\MdHandler::class,
                default => Handlers\MdHandler::class,
            };
            $I->assertSame($expectedClass, $result->getClass(), "$name: wrong handler class");

            $data = $result->getData();
            $I->assertSame('mon-article', $data['path'] ?? null, "$name: wrong path in data");
        }
    }

    // ========================================
    // Slug redirect → RedirectHandler + url + code
    // ========================================

    public function testSlugRedirectResolvesToHandler(IntegrationTester $I): void
    {
        // Create a host
        $host = Host::query()->andWhere(['id' => 1])->one();
        if ($host === null) {
            $host = new \Blackcube\Dcore\Models\Host();
            $host->setName('localhost');
            $host->setActive(true);
            $host->save();
        }

        // Create a redirect slug
        $slug = new \Blackcube\Dcore\Models\Slug();
        $slug->setHostId($host->getId());
        $slug->setPath('old-page');
        $slug->setTargetUrl('https://example.com/new-page');
        $slug->setHttpCode(301);
        $slug->setActive(true);
        $slug->save();

        $middlewares = $this->createMiddlewares();

        foreach ($middlewares as $name => $middleware) {
            $result = $this->resolve($middleware, 'https', 'localhost', 'old-page');
            $I->assertNotNull($result, "$name: redirect slug should resolve");

            $expectedClass = match ($name) {
                'laravel' => Handlers\Laravel\RedirectHandler::class,
                default => Handlers\RedirectHandler::class,
            };
            $I->assertSame($expectedClass, $result->getClass(), "$name: wrong handler class");

            $data = $result->getData();
            $I->assertSame('https://example.com/new-page', $data['redirectUrl'] ?? null, "$name: wrong redirect URL");
            $I->assertSame(301, $data['httpCode'] ?? null, "$name: wrong HTTP code");
        }
    }

    // ========================================
    // CMS element → app handler from Type
    // ========================================

    public function testCmsElementResolvesToTypeHandler(IntegrationTester $I): void
    {
        // Create host
        $host = Host::query()->andWhere(['id' => 1])->one();
        if ($host === null) {
            $host = new \Blackcube\Dcore\Models\Host();
            $host->setName('localhost');
            $host->setActive(true);
            $host->save();
        }

        // Create type with handler 'page'
        $type = new \Blackcube\Dcore\Models\Type();
        $type->setName('Page');
        $type->setHandler('page');
        $type->save();

        // Create slug
        $slug = new \Blackcube\Dcore\Models\Slug();
        $slug->setHostId($host->getId());
        $slug->setPath('ma-page');
        $slug->setActive(true);
        $slug->save();

        // Create content linked to slug and type
        $content = new \Blackcube\Dcore\Models\Content();
        $content->setName('Ma Page');
        $content->setTypeId($type->getId());
        $content->setSlugId($slug->getId());
        $content->setActive(true);
        $content->save();

        $middlewares = $this->createMiddlewares();

        foreach ($middlewares as $name => $middleware) {
            $result = $this->resolve($middleware, 'https', 'localhost', 'ma-page');
            $I->assertNotNull($result, "$name: CMS element should resolve");
            $I->assertSame('construct', $result->getMode(), "$name: should be construct mode (PSR-15 handler)");
        }
    }

    // ========================================
    // Unknown path → null
    // ========================================

    public function testUnknownPathReturnsNull(IntegrationTester $I): void
    {
        foreach ($this->createMiddlewares() as $name => $middleware) {
            $result = $this->resolve($middleware, 'https', 'localhost', 'does-not-exist');
            $I->assertNull($result, "$name: unknown path should return null");
        }
    }
}
