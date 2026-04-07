<?php

declare(strict_types=1);

/**
 * params.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

return [
    'blackcube/ssr' => [
        'scanAttributes' => true,
        'scanAliases' => [],
        'configHandlers' => [],
        'configErrorHandlers' => [],
        'routes' => [
            'robots' => 'robots.txt',
            'sitemap' => 'sitemap.xml',
            'llms' => 'llms.txt',
            'llms-full' => 'llms-full.txt',
            'md-suffix' => '.md',
        ],
    ],
];
