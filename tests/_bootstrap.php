<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

date_default_timezone_set('Europe/Paris');

defined('STDIN') or define('STDIN', fopen('php://stdin', 'r'));
defined('STDOUT') or define('STDOUT', fopen('php://stdout', 'w'));

require dirname(__DIR__) . '/vendor/autoload.php';

ini_set('display_errors', '1');
error_reporting(E_ALL);

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();
