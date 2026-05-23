<?php

declare(strict_types=1);

namespace Blackcube\Ssr\Tests\Unit;

use Blackcube\Ssr\FallbackHandler;
use Blackcube\Ssr\Services\HandlerRegistry;
use Blackcube\Ssr\Tests\Support\Stub\SimpleRequestHandler;
use Blackcube\Ssr\Tests\Support\UnitTester;
use HttpSoft\Message\ServerRequest;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Cache\ArrayCache;
use Yiisoft\Cache\Cache;
use Yiisoft\Injector\Injector;

class FallbackHandlerCest
{
    private function createFallbackHandler(?SimpleRequestHandler $defaultHandler = null): FallbackHandler
    {
        $injector = new Injector();
        $registry = new HandlerRegistry(
            new Aliases(),
            new Cache(new ArrayCache()),
        );

        return new FallbackHandler($injector, $registry, defaultHandler: $defaultHandler);
    }

    public function testWithDefaultHandler(UnitTester $I): void
    {
        $handler = $this->createFallbackHandler(new SimpleRequestHandler());

        $request = new ServerRequest();
        $response = $handler->handle($request);

        // No error handler configured → delegates to default
        $I->assertSame(200, $response->getStatusCode());
        $response->getBody()->rewind();
        $I->assertSame('default-handler', $response->getBody()->getContents());
    }

    public function testWithoutDefaultHandlerThrows(UnitTester $I): void
    {
        $handler = $this->createFallbackHandler();

        $I->expectThrowable(\RuntimeException::class, function () use ($handler) {
            $handler->handle(new ServerRequest());
        });
    }
}
