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
use Blackcube\Ssr\Tests\Support\Stub\SimpleRequestHandler;
use Blackcube\Ssr\Tests\Support\UnitTester;
use Blackcube\Ssr\YiiFallbackHandler;
use HttpSoft\Message\ServerRequest;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Cache\ArrayCache;
use Yiisoft\Cache\Cache;
use Yiisoft\I18n\Locale;
use Yiisoft\I18n\LocaleProvider;
use Yiisoft\Injector\Injector;

class YiiFallbackHandlerCest
{
    private function createFallbackHandler(SimpleRequestHandler $defaultHandler): YiiFallbackHandler
    {
        $injector = new Injector();
        $registry = new HandlerRegistry(
            new Aliases(),
            new Cache(new ArrayCache()),
        );

        return new YiiFallbackHandler(
            $injector,
            $registry,
            new HandlerDescriptorFactory($registry),
            new LocaleProvider(new Locale('fr')),
            $defaultHandler,
        );
    }

    public function testWithDefaultHandler(UnitTester $I): void
    {
        $handler = $this->createFallbackHandler(new SimpleRequestHandler());

        $request = new ServerRequest();
        $response = $handler->handle($request);

        $I->assertSame(200, $response->getStatusCode());
        $response->getBody()->rewind();
        $I->assertSame('default-handler', $response->getBody()->getContents());
    }
}
