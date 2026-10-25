<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Ssr\Tests\Support\Stub\Handlers\Laravel;

use Blackcube\Dcore\Entities\Content;
use Blackcube\Dcore\Entities\Tag;
use Blackcube\Ssr\Attributes\RoutingHandler;

class PageHandler
{
    #[RoutingHandler(route: 'page-laravel')]
    public function handle(Content|Tag $element): string
    {
        return 'laravel-page';
    }
}
