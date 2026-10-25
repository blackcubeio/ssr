<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Ssr\Tests\Integration;

use Blackcube\Dcore\Entities\Content;
use Blackcube\Dcore\Entities\Host;
use Blackcube\Dcore\Entities\Slug;
use Blackcube\Dcore\Entities\Type;
use Blackcube\Dcore\Models\GlobalXeo;
use Blackcube\Dcore\Models\ElasticSchema;
use Blackcube\Dcore\Services\HandlerDescriptor;
use Blackcube\Dcore\Services\HandlerDescriptorFactory;
use Blackcube\Ssr\Handlers;
use Blackcube\Ssr\LaravelSsrRoutingMiddleware;
use Blackcube\Ssr\Services\HandlerRegistry;
use Blackcube\Ssr\PsrSsrRoutingMiddleware;
use Blackcube\Ssr\Tests\Support\DatabaseCestTrait;
use Blackcube\Ssr\Tests\Support\IntegrationTester;
use Blackcube\Ssr\YiiSsrRoutingMiddleware;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Cache\ArrayCache;
use Yiisoft\Cache\Cache;
use Yiisoft\Injector\Injector;

/**
 * Integration tests for AbstractSsrRouting::findHandler() — Yii, Psr, Laravel.
 * Tests the same findHandler() logic through each concrete middleware to verify
 * each framework's handler map produces the correct HandlerDescriptor.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class FindHandlerCest
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
     * @return array{yii: YiiSsrRoutingMiddleware, psr: PsrSsrRoutingMiddleware, laravel: LaravelSsrRoutingMiddleware}
     */
    private function createMiddlewares(): array
    {
        $factory = new HandlerDescriptorFactory($this->createRegistry());
        $injector = $this->createInjector();

        return [
            'yii' => (new YiiSsrRoutingMiddleware($injector, $factory))
                ->withExcludedPrefixes('admin/')
                ->withXeo()
                ->withMdAlternate(),
            'psr' => (new PsrSsrRoutingMiddleware($injector, $factory))
                ->withExcludedPrefixes('admin/')
                ->withXeo()
                ->withMdAlternate(),
            'laravel' => (new LaravelSsrRoutingMiddleware($injector, $factory))
                ->withExcludedPrefixes('admin/')
                ->withXeo()
                ->withMdAlternate(),
        ];
    }

    /**
     * Call findHandler() via reflection (it's protected).
     */
    private function callFindHandler(object $middleware, string $host, string $path): ?HandlerDescriptor
    {
        $ref = new \ReflectionMethod($middleware, 'findHandler');
        return $ref->invoke($middleware, $host, $path);
    }


    public function testExcludedPrefixReturnsNull(IntegrationTester $I): void
    {
        foreach ($this->createMiddlewares() as $name => $middleware) {
            $result = $this->callFindHandler($middleware, 'localhost', 'admin/dashboard');
            $I->assertNull($result, $name . ': excluded prefix should return null');
        }
    }


    public function testRobotsTxtMapsToHandler(IntegrationTester $I): void
    {
        $middlewares = $this->createMiddlewares();

        foreach ($middlewares as $name => $middleware) {
            $result = $this->callFindHandler($middleware, 'localhost', 'robots.txt');
            $I->assertNotNull($result, $name . ': robots.txt should map to a handler');

            $expectedClass = match ($name) {
                'laravel' => Handlers\Laravel\RobotsHandler::class,
                default => Handlers\RobotsHandler::class,
            };
            $I->assertSame($expectedClass, $result->getClass(), $name . ': wrong handler class');
        }
    }


    public function testSitemapXmlMapsToHandler(IntegrationTester $I): void
    {
        $middlewares = $this->createMiddlewares();

        foreach ($middlewares as $name => $middleware) {
            $result = $this->callFindHandler($middleware, 'localhost', 'sitemap.xml');
            $I->assertNotNull($result, $name . ': sitemap.xml should map to a handler');

            $expectedClass = match ($name) {
                'laravel' => Handlers\Laravel\SitemapHandler::class,
                default => Handlers\SitemapHandler::class,
            };
            $I->assertSame($expectedClass, $result->getClass(), $name . ': wrong handler class');
        }
    }


    public function testMdMapsToHandlerWithPath(IntegrationTester $I): void
    {
        $middlewares = $this->createMiddlewares();

        foreach ($middlewares as $name => $middleware) {
            $result = $this->callFindHandler($middleware, 'localhost', 'mon-article.md');
            $I->assertNotNull($result, $name . ': .md should map to a handler');

            $expectedClass = match ($name) {
                'laravel' => Handlers\Laravel\MdHandler::class,
                default => Handlers\MdHandler::class,
            };
            $I->assertSame($expectedClass, $result->getClass(), $name . ': wrong handler class');

            $data = $result->getData();
            $I->assertSame('mon-article', $data['path'] ?? null, $name . ': wrong path in data');
        }
    }


    public function testSlugRedirectMapsToHandler(IntegrationTester $I): void
    {
        $host = Host::query()->andWhere(['id' => 1])->one();
        if ($host === null) {
            $host = new \Blackcube\Dcore\Models\Host();
            $host->setName('localhost');
            $host->setActive(true);
            $host->save();
        }

        $slug = new \Blackcube\Dcore\Models\Slug();
        $slug->setHostId($host->getId());
        $slug->setPath('old-page');
        $slug->setTargetUrl('https://example.com/new-page');
        $slug->setHttpCode(301);
        $slug->setActive(true);
        $slug->save();

        $middlewares = $this->createMiddlewares();

        foreach ($middlewares as $name => $middleware) {
            $result = $this->callFindHandler($middleware, 'localhost', 'old-page');
            $I->assertNotNull($result, $name . ': redirect slug should map to a handler');

            $expectedClass = match ($name) {
                'laravel' => Handlers\Laravel\RedirectHandler::class,
                default => Handlers\RedirectHandler::class,
            };
            $I->assertSame($expectedClass, $result->getClass(), $name . ': wrong handler class');

            $data = $result->getData();
            $I->assertSame('https://example.com/new-page', $data['redirectUrl'] ?? null, $name . ': wrong redirect URL');
            $I->assertSame(301, $data['httpCode'] ?? null, $name . ': wrong HTTP code');
        }
    }


    public function testCmsElementMapsToTypeHandler(IntegrationTester $I): void
    {
        $host = Host::query()->andWhere(['id' => 1])->one();
        if ($host === null) {
            $host = new \Blackcube\Dcore\Models\Host();
            $host->setName('localhost');
            $host->setActive(true);
            $host->save();
        }

        $type = new \Blackcube\Dcore\Models\Type();
        $type->setName('Page');
        $type->setHandler('page');
        $type->save();

        $slug = new \Blackcube\Dcore\Models\Slug();
        $slug->setHostId($host->getId());
        $slug->setPath('ma-page');
        $slug->setActive(true);
        $slug->save();

        $content = new \Blackcube\Dcore\Models\Content();
        $content->setName('Ma Page');
        $content->setTypeId($type->getId());
        $content->setSlugId($slug->getId());
        $content->setActive(true);
        $content->save();

        $middlewares = $this->createMiddlewares();

        foreach ($middlewares as $name => $middleware) {
            $result = $this->callFindHandler($middleware, 'localhost', 'ma-page');
            $I->assertNotNull($result, $name . ': CMS element should map to a handler');
            $I->assertSame('construct', $result->getMode(), $name . ': should be construct mode (PSR-15 handler)');
        }
    }


    public function testUnknownPathReturnsNull(IntegrationTester $I): void
    {
        foreach ($this->createMiddlewares() as $name => $middleware) {
            $result = $this->callFindHandler($middleware, 'localhost', 'does-not-exist');
            $I->assertNull($result, $name . ': unknown path should return null');
        }
    }
}
