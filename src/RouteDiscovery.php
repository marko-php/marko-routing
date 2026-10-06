<?php

declare(strict_types=1);

namespace Marko\Routing;

use Error;
use Marko\Core\Exceptions\MarkoException;
use Marko\Core\Module\ModuleManifest;
use Marko\Routing\Attributes\DisableRoute;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Route;
use Marko\Routing\Attributes\RoutePrefix;
use Marko\Routing\Attributes\WithoutMiddleware;
use Marko\Routing\Exceptions\RouteException;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

class RouteDiscovery
{
    /**
     * Discover routes in a module's src directory.
     *
     * @return array<RouteDefinition>
     */
    public function discoverInModule(
        ModuleManifest $manifest,
    ): array {
        return [];
    }

    /**
     * Discover routes from a specific class.
     *
     * @param class-string $className
     * @return array<RouteDefinition>
     * @throws ReflectionException|RouteException
     */
    public function discoverFromClass(
        string $className,
    ): array {
        $routes = [];
        $reflection = new ReflectionClass($className);
        // Class-level attributes come from the class and every ancestor, so a
        // #[Preference] subclass keeps its parent's middleware and exclusions.
        $hierarchy = $this->classHierarchy($reflection);
        $classMiddleware = array_values(array_unique(array_merge(
            ...array_map(fn (ReflectionClass $class): array => $this->getClassMiddleware($class), $hierarchy),
        )));
        $classWithoutMiddleware = array_merge(
            ...array_map(fn (ReflectionClass $class): array => $this->getWithoutMiddleware($class), $hierarchy),
        );

        foreach ($reflection->getMethods() as $method) {
            if ($this->isRouteDisabled($method)) {
                continue;
            }

            foreach ($method->getAttributes() as $attribute) {
                try {
                    $instance = $attribute->newInstance();
                } catch (Error $e) {
                    $missingClass = MarkoException::extractMissingClass($e);
                    if ($missingClass !== null) {
                        // Skip attributes from uninstalled Marko packages
                        if (MarkoException::inferPackageName($missingClass) !== null) {
                            continue;
                        }
                        throw RouteException::attributeClassNotFound($className, $missingClass, $e);
                    }
                    throw $e;
                }
                if ($instance instanceof Route) {
                    $methodMiddleware = $this->getMethodMiddleware($method);
                    $prefix = $this->resolvePrefix($method->getDeclaringClass());
                    $routes[] = new RouteDefinition(
                        method: $instance->getMethod(),
                        path: $this->joinPrefix($prefix?->prefix ?? '', $instance->path),
                        controller: $className,
                        action: $method->getName(),
                        middleware: array_merge($classMiddleware, $instance->middleware, $methodMiddleware),
                        name: $instance->name === null ? null : ($prefix?->namePrefix ?? '') . $instance->name,
                        withoutMiddleware: array_values(array_unique(array_merge(
                            $classWithoutMiddleware,
                            $this->getWithoutMiddleware($method),
                        ))),
                    );
                }
            }
        }

        return $routes;
    }

    /**
     * The #[RoutePrefix] for a method's declaring class, or for its nearest
     * ancestor that has one.
     *
     * @throws RouteException When the prefix does not start with a slash
     */
    private function resolvePrefix(
        ReflectionClass $class,
    ): ?RoutePrefix {
        $current = $class;

        while ($current !== false) {
            $attributes = $current->getAttributes(RoutePrefix::class);

            if ($attributes !== []) {
                $prefix = $attributes[0]->newInstance();

                if (!str_starts_with($prefix->prefix, '/')) {
                    throw RouteException::invalidPrefix($current->getName(), $prefix->prefix);
                }

                return $prefix;
            }

            $current = $current->getParentClass();
        }

        return null;
    }

    private function joinPrefix(
        string $prefix,
        string $path,
    ): string {
        if ($prefix === '') {
            return $path;
        }

        $joined = rtrim($prefix, '/') . '/' . ltrim($path, '/');

        return $joined === '/' ? $joined : rtrim($joined, '/');
    }

    /**
     * The class and its ancestors, root ancestor first.
     *
     * @return array<int, ReflectionClass>
     */
    private function classHierarchy(
        ReflectionClass $reflection,
    ): array {
        $hierarchy = [];
        $current = $reflection;

        while ($current !== false) {
            array_unshift($hierarchy, $current);
            $current = $current->getParentClass();
        }

        return $hierarchy;
    }

    /**
     * Middleware excluded by #[WithoutMiddleware] on a class or method.
     *
     * @return array<int, string>
     */
    private function getWithoutMiddleware(
        ReflectionClass|ReflectionMethod $reflection,
    ): array {
        return array_merge(...array_map(
            fn (ReflectionAttribute $attribute): array => $attribute->newInstance()->middleware,
            $reflection->getAttributes(WithoutMiddleware::class),
        ));
    }

    /**
     * Get middleware defined at the class level.
     *
     * @return array<string>
     * @throws RouteException
     */
    private function getClassMiddleware(
        ReflectionClass $reflection,
    ): array {
        $middlewareAttributes = $reflection->getAttributes(Middleware::class);
        if (empty($middlewareAttributes)) {
            return [];
        }

        try {
            $middleware = $middlewareAttributes[0]->newInstance();
        } catch (Error $e) {
            $missingClass = MarkoException::extractMissingClass($e);
            if ($missingClass !== null) {
                if (MarkoException::inferPackageName($missingClass) !== null) {
                    return [];
                }
                throw RouteException::attributeClassNotFound($reflection->getName(), $missingClass, $e);
            }
            throw $e;
        }

        return $middleware->middleware;
    }

    /**
     * Get middleware defined at the method level.
     *
     * @return array<string>
     * @throws RouteException
     */
    private function getMethodMiddleware(
        ReflectionMethod $method,
    ): array {
        $middlewareAttributes = $method->getAttributes(Middleware::class);
        if (empty($middlewareAttributes)) {
            return [];
        }

        try {
            $middleware = $middlewareAttributes[0]->newInstance();
        } catch (Error $e) {
            $missingClass = MarkoException::extractMissingClass($e);
            if ($missingClass !== null) {
                if (MarkoException::inferPackageName($missingClass) !== null) {
                    return [];
                }
                $controller = $method->getDeclaringClass()->getName();
                throw RouteException::attributeClassNotFound($controller, $missingClass, $e);
            }
            throw $e;
        }

        return $middleware->middleware;
    }

    /**
     * Check if a method has the DisableRoute attribute.
     */
    private function isRouteDisabled(
        ReflectionMethod $method,
    ): bool {
        return !empty($method->getAttributes(DisableRoute::class));
    }
}
