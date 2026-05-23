<?php

declare(strict_types=1);

namespace Blackcube\Ssr\Tests\Support\Stub\Handlers\Laravel;

use Blackcube\Dcore\Entities\Content;
use Blackcube\Dcore\Entities\Tag;
use Blackcube\Ssr\Attributes\RoutingHandler;

final class PageHandler
{
    #[RoutingHandler(route: 'page-laravel')]
    public function handle(Content|Tag $element): string
    {
        return 'laravel-page';
    }
}
