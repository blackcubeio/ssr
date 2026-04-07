<?php

declare(strict_types=1);

namespace Blackcube\Ssr\Tests\Unit;

use Blackcube\Ssr\Interfaces\RouteProviderInterface;
use Blackcube\Ssr\Services\HandlerRegistry;
use Blackcube\Ssr\Tests\Support\Stub\Handlers\ErrorHandler;
use Blackcube\Ssr\Tests\Support\Stub\Handlers\Laravel\PageHandler as LaravelPageHandler;
use Blackcube\Ssr\Tests\Support\Stub\Handlers\PageHandler;
use Blackcube\Ssr\Tests\Support\Stub\InvokeHandler;
use Blackcube\Ssr\Tests\Support\Stub\MethodHandler;
use Blackcube\Ssr\Tests\Support\UnitTester;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Cache\ArrayCache;
use Yiisoft\Cache\Cache;

class HandlerRegistryCest
{
    private function createRegistry(
        array $configHandlers = [],
        array $configErrorHandlers = [],
        bool $scanAttributes = false,
        array $scanAliases = [],
    ): HandlerRegistry {
        return new HandlerRegistry(
            new Aliases(),
            new Cache(new ArrayCache()),
            scanAttributes: $scanAttributes,
            scanAliases: $scanAliases,
            configHandlers: $configHandlers,
            configErrorHandlers: $configErrorHandlers,
        );
    }

    public function testImplementsRouteProviderInterface(UnitTester $I): void
    {
        $registry = $this->createRegistry();
        $I->assertInstanceOf(RouteProviderInterface::class, $registry);
    }

    public function testDefaultsWithoutConfig(UnitTester $I): void
    {
        $registry = $this->createRegistry();

        $I->assertNull($registry->getHandlerInfo('any.route'));
        $I->assertNull($registry->getErrorHandlerInfo(404));
        $I->assertEmpty($registry->getAvailableRoutes());
    }

    public function testConfigHandlerInvoke(UnitTester $I): void
    {
        $registry = $this->createRegistry(configHandlers: [
            'blog.index' => InvokeHandler::class,
        ]);

        $info = $registry->getHandlerInfo('blog.index');
        $I->assertNotNull($info);
        $I->assertSame(InvokeHandler::class, $info['class']);
        $I->assertSame('invoke', $info['mode']);
        $I->assertNull($info['method']);
    }

    public function testConfigHandlerMethod(UnitTester $I): void
    {
        $registry = $this->createRegistry(configHandlers: [
            'blog.show' => [MethodHandler::class, 'show'],
        ]);

        $info = $registry->getHandlerInfo('blog.show');
        $I->assertNotNull($info);
        $I->assertSame(MethodHandler::class, $info['class']);
        $I->assertSame('method', $info['mode']);
        $I->assertSame('show', $info['method']);
    }

    public function testUnknownRouteReturnsNull(UnitTester $I): void
    {
        $registry = $this->createRegistry(configHandlers: [
            'blog.index' => InvokeHandler::class,
        ]);

        $I->assertNull($registry->getHandlerInfo('unknown.route'));
    }

    public function testConfigErrorHandlerExactCode(UnitTester $I): void
    {
        $registry = $this->createRegistry(configErrorHandlers: [
            'error.404' => [
                'handler' => [MethodHandler::class, 'error'],
                'code' => 404,
            ],
        ]);

        $info = $registry->getErrorHandlerInfo(404);
        $I->assertNotNull($info);
        $I->assertSame(MethodHandler::class, $info['class']);
        $I->assertSame('error.404', $info['route']);
    }

    public function testConfigErrorHandlerRange(UnitTester $I): void
    {
        $registry = $this->createRegistry(configErrorHandlers: [
            'error.5xx' => [
                'handler' => [MethodHandler::class, 'error'],
                'min' => 500,
                'max' => 599,
            ],
        ]);

        $I->assertNotNull($registry->getErrorHandlerInfo(503));
        $I->assertNull($registry->getErrorHandlerInfo(404));
    }

    public function testErrorHandlerExactMatchWinsOverRange(UnitTester $I): void
    {
        $registry = $this->createRegistry(configErrorHandlers: [
            'error.5xx' => [
                'handler' => InvokeHandler::class,
                'min' => 500,
                'max' => 599,
            ],
            'error.503' => [
                'handler' => [MethodHandler::class, 'error'],
                'code' => 503,
            ],
        ]);

        $info = $registry->getErrorHandlerInfo(503);
        $I->assertNotNull($info);
        $I->assertSame('error.503', $info['route']);
    }

    public function testGetErrorHandlerInfoByRoute(UnitTester $I): void
    {
        $registry = $this->createRegistry(configErrorHandlers: [
            'error.404' => [
                'handler' => [MethodHandler::class, 'error'],
                'code' => 404,
            ],
        ]);

        $info = $registry->getErrorHandlerInfoByRoute('error.404');
        $I->assertNotNull($info);
        $I->assertSame(MethodHandler::class, $info['class']);

        $I->assertNull($registry->getErrorHandlerInfoByRoute('unknown'));
    }

    public function testGetAvailableRoutes(UnitTester $I): void
    {
        $registry = $this->createRegistry(
            configHandlers: [
                'blog.index' => InvokeHandler::class,
                'blog.show' => [MethodHandler::class, 'show'],
            ],
            configErrorHandlers: [
                'error.404' => [
                    'handler' => [MethodHandler::class, 'error'],
                    'code' => 404,
                ],
            ],
        );

        $routes = $registry->getAvailableRoutes();
        $I->assertContains('blog.index', $routes);
        $I->assertContains('blog.show', $routes);
        $I->assertContains('error.404', $routes);
        $I->assertCount(3, $routes);
    }

    // ========================================
    // Attribute scanning — PSR-15 handler (class-level)
    // ========================================

    public function testScanAttributesPsr15ConstructMode(UnitTester $I): void
    {
        $registry = $this->createRegistry(
            scanAttributes: true,
            scanAliases: [dirname(__DIR__) . '/Support/Stub/Handlers'],
        );

        $info = $registry->getHandlerInfo('page');
        $I->assertNotNull($info);
        $I->assertSame(PageHandler::class, $info['class']);
        $I->assertSame('construct', $info['mode']);
        $I->assertNull($info['method']);
        $I->assertContains('Content|Tag', $info['expects']);
    }

    // ========================================
    // Attribute scanning — error handler (class-level)
    // ========================================

    public function testScanAttributesErrorHandler(UnitTester $I): void
    {
        $registry = $this->createRegistry(
            scanAttributes: true,
            scanAliases: [dirname(__DIR__) . '/Support/Stub/Handlers'],
        );

        $info = $registry->getErrorHandlerInfo(404);
        $I->assertNotNull($info);
        $I->assertSame(ErrorHandler::class, $info['class']);
        $I->assertSame('construct', $info['mode']);
    }

    // ========================================
    // Attribute scanning — Laravel handler (method-level)
    // ========================================

    public function testScanAttributesLaravelMethodMode(UnitTester $I): void
    {
        $registry = $this->createRegistry(
            scanAttributes: true,
            scanAliases: [dirname(__DIR__) . '/Support/Stub/Handlers/Laravel'],
        );

        $info = $registry->getHandlerInfo('page-laravel');
        $I->assertNotNull($info);
        $I->assertSame(LaravelPageHandler::class, $info['class']);
        $I->assertSame('method', $info['mode']);
        $I->assertSame('handle', $info['method']);
        $I->assertContains('Content|Tag', $info['expects']);
    }

    // ========================================
    // Config handlers win over scanned attributes
    // ========================================

    public function testConfigWinsOverAttributes(UnitTester $I): void
    {
        $registry = $this->createRegistry(
            configHandlers: [
                'page' => InvokeHandler::class,
            ],
            scanAttributes: true,
            scanAliases: [dirname(__DIR__) . '/Support/Stub/Handlers'],
        );

        $info = $registry->getHandlerInfo('page');
        $I->assertNotNull($info);
        $I->assertSame(InvokeHandler::class, $info['class']);
        $I->assertSame('invoke', $info['mode']);
    }

    // ========================================
    // Scan all three frameworks in one registry
    // ========================================

    public function testScanAllHandlerTypes(UnitTester $I): void
    {
        $registry = $this->createRegistry(
            scanAttributes: true,
            scanAliases: [dirname(__DIR__) . '/Support/Stub/Handlers'],
        );

        $routes = $registry->getAvailableRoutes();

        // PSR-15 class-level
        $I->assertContains('page', $routes);
        // Error handler
        $I->assertContains('error-404', $routes);
        // Laravel method-level (in subdirectory)
        $I->assertContains('page-laravel', $routes);
    }
}
