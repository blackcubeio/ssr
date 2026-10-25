<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Ssr\Services;

use Blackcube\Dcore\Entities\Content;
use Blackcube\Dcore\Entities\Slug;
use Blackcube\Dcore\Entities\Tag;
use Blackcube\Dcore\Helpers\Element;
use Blackcube\Ssr\Attributes\RoutingHandler;
use Blackcube\Ssr\Interfaces\RouteProviderInterface;
use PhpToken;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Cache\CacheInterface;
use Yiisoft\Cache\Dependency\AnyDependency;
use Yiisoft\Cache\Dependency\FileDependency;

/**
 * Registry for SSR route handlers.
 * Scans for #[RoutingHandler] attributes and merges with config.
 * Pre-analyzes handler signatures: mode (construct/invoke/method) and expected CMS types.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class HandlerRegistry implements RouteProviderInterface
{
    private const CACHE_KEY = 'ssr.handler.a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0'; // sha1('handler-registry-all')

    /**
     * CMS types we track — everything else is DI-resolved.
     */
    private const CMS_TYPES = [
        Content::class,
        Tag::class,
        Slug::class,
        Element::class,
    ];

    /**
     * Additional types tracked for error handlers.
     */
    private const ERROR_TYPES = [
        \Throwable::class,
    ];

    private ?array $handlers = null;
    private ?array $errorHandlers = null;

    public function __construct(
        private readonly Aliases $aliases,
        private readonly CacheInterface $cache,
        private bool $scanAttributes = true,
        private array $scanAliases = [],
        private array $configHandlers = [],
        private array $configErrorHandlers = [],
    ) {}

    /**
     * Get handler info for a route.
     *
     * @return array{class: string, mode: string, method: ?string, expects: array}|null
     */
    public function getHandlerInfo(string $route): ?array
    {
        $this->ensureLoaded();

        return $this->handlers[$route] ?? null;
    }

    /**
     * Get error handler info for a status code.
     * Resolution: exact match first, then range fallback.
     *
     * @return array{class: string, mode: string, method: ?string, expects: array, route: string}|null
     */
    public function getErrorHandlerInfo(int $statusCode): ?array
    {
        $this->ensureLoaded();
        $handlerInfo = null;

        foreach ($this->errorHandlers as $route => $info) {
            if ($handlerInfo === null && $info['code'] === $statusCode) {
                $info['route'] = $route;
                $handlerInfo = $info;
            }
        }

        if ($handlerInfo === null) {
            foreach ($this->errorHandlers as $route => $info) {
                if ($handlerInfo === null && $info['min'] !== null && $info['max'] !== null
                    && $statusCode >= $info['min'] && $statusCode <= $info['max']) {
                    $info['route'] = $route;
                    $handlerInfo = $info;
                }
            }
        }

        return $handlerInfo;
    }

    /**
     * Get error handler info by route name.
     *
     * @return array{class: string, mode: string, method: ?string, expects: array}|null
     */
    public function getErrorHandlerInfoByRoute(string $route): ?array
    {
        $this->ensureLoaded();

        return $this->errorHandlers[$route] ?? null;
    }

    public function getAvailableRoutes(): array
    {
        $this->ensureLoaded();

        return array_keys(array_merge($this->handlers, $this->errorHandlers));
    }

    private function ensureLoaded(): void
    {
        if ($this->handlers === null) {
            $scanned = $this->scanAttributes === true ? $this->scanAll() : [
                'handlers' => [],
                'errorHandlers' => [],
            ];

            $this->handlers = array_merge($scanned['handlers'], $this->analyzeConfigHandlers());
            $this->errorHandlers = array_merge($scanned['errorHandlers'], $this->analyzeConfigErrorHandlers());
        }
    }

    /**
     * Analyze config handlers into the same format as scanned handlers.
     */
    private function analyzeConfigHandlers(): array
    {
        $result = [];

        foreach ($this->configHandlers as $route => $handler) {
            if (is_string($handler) === true) {
                $result[$route] = $this->analyzeClass($handler);
            } elseif (is_array($handler) === true && count($handler) === 2) {
                $result[$route] = $this->analyzeMethod($handler[0], $handler[1]);
            }
        }

        return $result;
    }

    /**
     * Analyze config error handlers.
     */
    private function analyzeConfigErrorHandlers(): array
    {
        $result = [];

        foreach ($this->configErrorHandlers as $route => $handler) {
            $config = is_array($handler) === true && isset($handler['handler']) === true ? $handler : ['handler' => $handler];
            $handlerDef = $config['handler'];

            $info = null;
            if (is_string($handlerDef) === true) {
                $info = $this->analyzeClass($handlerDef, true);
            } elseif (is_array($handlerDef) === true && count($handlerDef) === 2) {
                $info = $this->analyzeMethod($handlerDef[0], $handlerDef[1], true);
            }

            if ($info !== null) {
                $info['code'] = $config['code'] ?? null;
                $info['min'] = $config['min'] ?? null;
                $info['max'] = $config['max'] ?? null;
                $result[$route] = $info;
            }
        }

        return $result;
    }

    /**
     * Analyze a class-level handler (RequestHandlerInterface or __invoke).
     *
     * @return array{class: string, mode: string, method: ?string, expects: array}
     */
    private function analyzeClass(string $class, bool $includeErrorTypes = false): array
    {
        $reflection = new ReflectionClass($class);

        if ($reflection->implementsInterface(RequestHandlerInterface::class) === true) {
            $analysis = [
                'class' => $class,
                'mode' => 'construct',
                'method' => null,
                'expects' => $this->extractCmsTypes($reflection->getConstructor()?->getParameters() ?? [], $includeErrorTypes),
            ];
        } else {
            $method = $reflection->getMethod('__invoke');

            $analysis = [
                'class' => $class,
                'mode' => 'invoke',
                'method' => null,
                'expects' => $this->extractCmsTypes($method->getParameters(), $includeErrorTypes),
            ];
        }

        return $analysis;
    }

    /**
     * Analyze a method-level handler.
     *
     * @return array{class: string, mode: string, method: string, expects: array}
     */
    private function analyzeMethod(string $class, string $methodName, bool $includeErrorTypes = false): array
    {
        $reflection = new ReflectionClass($class);
        $method = $reflection->getMethod($methodName);

        return [
            'class' => $class,
            'mode' => 'method',
            'method' => $methodName,
            'expects' => $this->extractCmsTypes($method->getParameters(), $includeErrorTypes),
        ];
    }

    /**
     * Extract CMS-specific types from method/constructor parameters.
     * Only tracks: Content, Tag, Content|Tag, Slug, Element.
     * When $includeErrorTypes is true, also tracks: Throwable.
     *
     * @param ReflectionParameter[] $parameters
     * @return array List of expected type strings (FQCN or 'Content|Tag')
     */
    private function extractCmsTypes(array $parameters, bool $includeErrorTypes = false): array
    {
        $trackedTypes = $includeErrorTypes === true
            ? array_merge(self::CMS_TYPES, self::ERROR_TYPES)
            : self::CMS_TYPES;

        $expects = [];

        foreach ($parameters as $param) {
            $type = $param->getType();

            if ($type instanceof ReflectionUnionType === true) {
                $names = [];
                foreach ($type->getTypes() as $unionMember) {
                    if ($unionMember instanceof ReflectionNamedType === true) {
                        $names[] = $unionMember->getName();
                    }
                }

                $hasContent = false;
                $hasTag = false;
                foreach ($names as $name) {
                    if (is_a($name, Content::class, true) === true) {
                        $hasContent = true;
                    }
                    if (is_a($name, Tag::class, true) === true) {
                        $hasTag = true;
                    }
                }
                if ($hasContent === true && $hasTag === true) {
                    $expects[] = 'Content|Tag';
                }
            } elseif ($type instanceof ReflectionNamedType === true && $type->isBuiltin() === false) {
                $typeName = $type->getName();
                $isTracked = false;
                foreach ($trackedTypes as $trackedType) {
                    if ($isTracked === false && is_a($typeName, $trackedType, true) === true) {
                        $expects[] = $trackedType;
                        $isTracked = true;
                    }
                }
            }
        }

        return $expects;
    }

    /**
     * Scan all files and return both handlers and error handlers.
     *
     * @return array{handlers: array, errorHandlers: array}
     */
    private function scanAll(): array
    {
        $scanned = [
            'handlers' => [],
            'errorHandlers' => [],
        ];
        $files = $this->getFilesToScan();

        if (empty($files) === false) {
            $dependency = count($files) === 1
                ? new FileDependency($files[0])
                : new AnyDependency(
                    array_map(fn($f) => new FileDependency($f), $files)
                );

            $scanned = $this->cache->getOrSet(
                self::CACHE_KEY,
                fn() => $this->doScanAll($files),
                null,
                $dependency
            );
        }

        return $scanned;
    }

    /**
     * Scan files for RoutingHandler attributes.
     * Routes with isErrorHandler() go to errorHandlers, others to handlers.
     */
    private function doScanAll(array $files): array
    {
        $handlers = [];
        $errorHandlers = [];

        foreach ($files as $file) {
            $class = $this->getClassFromFile($file);
            if ($class !== null) {
                $reflection = new ReflectionClass($class);

                foreach ($reflection->getAttributes(RoutingHandler::class) as $attribute) {
                    $instance = $attribute->newInstance();
                    if ($instance->isErrorHandler() === true) {
                        $info = $this->analyzeClass($class, true);
                        $info['code'] = $instance->errorCode;
                        $info['min'] = $instance->errorCodesRangeMin;
                        $info['max'] = $instance->errorCodesRangeMax;
                        $errorHandlers[$instance->route] = $info;
                    } else {
                        $handlers[$instance->route] = $this->analyzeClass($class);
                    }
                }

                foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                    foreach ($method->getAttributes(RoutingHandler::class) as $attribute) {
                        $instance = $attribute->newInstance();
                        if ($instance->isErrorHandler() === true) {
                            $info = $this->analyzeMethod($class, $method->getName(), true);
                            $info['code'] = $instance->errorCode;
                            $info['min'] = $instance->errorCodesRangeMin;
                            $info['max'] = $instance->errorCodesRangeMax;
                            $errorHandlers[$instance->route] = $info;
                        } else {
                            $handlers[$instance->route] = $this->analyzeMethod($class, $method->getName());
                        }
                    }
                }
            }
        }

        return [
            'handlers' => $handlers,
            'errorHandlers' => $errorHandlers,
        ];
    }

    private function getFilesToScan(): array
    {
        $files = [];

        foreach ($this->scanAliases as $alias) {
            $path = $this->aliases->get($alias);
            if (is_dir($path) === true) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
                );

                foreach ($iterator as $file) {
                    if ($file->isFile() === true && $file->getExtension() === 'php') {
                        $files[] = $file->getPathname();
                    }
                }
            }
        }

        return $files;
    }

    private function getClassFromFile(string $file): ?string
    {
        $tokens = PhpToken::tokenize(file_get_contents($file));
        $count = count($tokens);
        $namespace = '';
        $class = null;

        for ($i = 0; $i < $count; $i++) {
            if ($tokens[$i]->id === T_NAMESPACE) {
                $parts = [];
                for (++$i; $i < $count && $tokens[$i]->text !== ';'; $i++) {
                    if ($tokens[$i]->id === T_NAME_QUALIFIED || $tokens[$i]->id === T_STRING) {
                        $parts[] = $tokens[$i]->text;
                    }
                }
                $namespace = implode('', $parts);
            }

            if ($tokens[$i]->id === T_CLASS && ($i === 0 || $tokens[$i - 1]->id !== T_DOUBLE_COLON)) {
                for (++$i; $i < $count; $i++) {
                    if ($tokens[$i]->id === T_STRING) {
                        $class = $tokens[$i]->text;
                        break 2;
                    }
                }
            }
        }

        $fqcn = null;
        if ($class !== null) {
            $fqcn = $namespace !== '' ? $namespace . '\\' . $class : $class;
        }
        return $fqcn;
    }
}
