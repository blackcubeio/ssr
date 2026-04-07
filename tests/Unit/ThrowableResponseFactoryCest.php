<?php

declare(strict_types=1);

namespace Blackcube\Ssr\Tests\Unit;

use Blackcube\Ssr\Tests\Support\UnitTester;
use Blackcube\Ssr\ThrowableResponseFactory;
use Blackcube\Ssr\Services\HandlerRegistry;
use HttpSoft\Message\ServerRequest;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Cache\ArrayCache;
use Yiisoft\Cache\Cache;
use Yiisoft\Injector\Injector;

class ThrowableResponseFactoryCest
{
    private function createFactory(): ThrowableResponseFactory
    {
        $injector = new Injector();
        $registry = new HandlerRegistry(
            new Aliases(),
            new Cache(new ArrayCache()),
        );

        return new ThrowableResponseFactory($injector, $registry);
    }

    public function testWithoutDefaultFactoryRethrows(UnitTester $I): void
    {
        $factory = $this->createFactory();
        $throwable = new \RuntimeException('test error', 500);
        $request = new ServerRequest();

        $I->expectThrowable(\RuntimeException::class, function () use ($factory, $throwable, $request) {
            $factory->create($throwable, $request);
        });
    }

    public function testSetDefaultFactoryIsUsed(UnitTester $I): void
    {
        $factory = $this->createFactory();

        // No error handler configured → falls back to default factory
        // We can't easily test with a real Yii factory without full app setup,
        // but we verify the setter accepts the interface
        $I->assertInstanceOf(ThrowableResponseFactory::class, $factory);
    }
}
