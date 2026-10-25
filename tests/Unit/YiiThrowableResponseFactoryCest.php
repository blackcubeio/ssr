<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Ssr\Tests\Unit;

use Blackcube\Dcore\Services\HandlerDescriptorFactory;
use Blackcube\Ssr\Services\HandlerRegistry;
use Blackcube\Ssr\Tests\Support\Stub\SimpleThrowableResponseFactory;
use Blackcube\Ssr\Tests\Support\UnitTester;
use Blackcube\Ssr\YiiThrowableResponseFactory;
use HttpSoft\Message\ServerRequest;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Cache\ArrayCache;
use Yiisoft\Cache\Cache;
use Yiisoft\I18n\Locale;
use Yiisoft\I18n\LocaleProvider;
use Yiisoft\Injector\Injector;

class YiiThrowableResponseFactoryCest
{
    private function createFactory(): YiiThrowableResponseFactory
    {
        $injector = new Injector();
        $registry = new HandlerRegistry(
            new Aliases(),
            new Cache(new ArrayCache()),
        );

        return new YiiThrowableResponseFactory(
            $injector,
            $registry,
            new HandlerDescriptorFactory($registry),
            new LocaleProvider(new Locale('fr')),
            new SimpleThrowableResponseFactory(),
        );
    }

    public function testDelegatesToDefaultFactory(UnitTester $I): void
    {
        $factory = $this->createFactory();
        $throwable = new \RuntimeException('test error', 500);
        $request = new ServerRequest();

        $response = $factory->create($throwable, $request);

        $I->assertSame(500, $response->getStatusCode());
        $response->getBody()->rewind();
        $I->assertSame('default-error', $response->getBody()->getContents());
    }
}
