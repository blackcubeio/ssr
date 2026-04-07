<?php

declare(strict_types=1);

/**
 * JsonLdAssetBundle.php
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Ssr\Assets;

use Yiisoft\Assets\AssetBundle;
use Yiisoft\View\WebView;

/**
 * Asset bundle for JSON-LD script tags injection.
 * Populated dynamically via AssetManager::registerCustomized() in YiiSsrRoutingMiddleware.
 */
class JsonLdAssetBundle extends AssetBundle
{
    public ?int $jsPosition = WebView::POSITION_HEAD;
}
