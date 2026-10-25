<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Ssr\Tests\Support\Stub;

use HttpSoft\Message\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Yiisoft\ErrorHandler\ThrowableResponseFactoryInterface;

/**
 * Simple ThrowableResponseFactoryInterface implementation for testing.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class SimpleThrowableResponseFactory implements ThrowableResponseFactoryInterface
{
    public function create(Throwable $throwable, ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response(500);
        $response->getBody()->write('default-error');

        return $response;
    }
}
