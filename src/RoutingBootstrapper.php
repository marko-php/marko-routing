<?php

declare(strict_types=1);

namespace Marko\Routing;

use Error;
use Marko\Core\Attributes\Preference;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Core\Discovery\ClassFileParser;
use Marko\Core\Module\ModuleManifest;
use Marko\Routing\Attributes\Route;
use Marko\Routing\Exceptions\RouteConflictException;
use Marko\Routing\Exceptions\RouteException;
use Marko\Routing\Http\ExceptionRenderer;
use Marko\Routing\Middleware\MiddlewareInterface;
use Psr\Container\ContainerExceptionInterface;
use ReflectionClass;
use ReflectionException;

class RoutingBootstrapper
{
    private RouteCollection $routes;

    private RouteDiscovery $discovery;

    private PreferenceRouteResolver $resolver;

    /**
     * @param array<ModuleManifest> $modules
     */
    public function __construct(
        private readonly array $modules,
        private readonly ContainerInterface $container,
        private readonly PreferenceRegistry $preferenceRegistry,
        private readonly ClassFileParser $classFileParser,
    ) {
        $this->routes = new RouteCollection();
        $this->discovery = new RouteDiscovery();
        $this->resolver = new PreferenceRouteResolver(
            $this->preferenceRegistry,
            $this->discovery,
        );
    }

    /**
     * Bootstrap the routing system: discover routes and register the Router in the container.
     *
     * @param array<class-string<MiddlewareInterface>> $globalMiddleware
     * @throws RouteException|RouteConflictException|ReflectionException|ContainerExceptionInterface
     */
    public function boot(
        array $globalMiddleware = [],
    ): Router {
        // Discover routes from all modules
        $this->discoverRoutes();
        $this->assertExcludedMiddlewareExists($globalMiddleware);

        // Register RouteCollection as singleton instance
        $this->container->instance(RouteCollection::class, $this->routes);

        // Register a single RouteMatcher so the Router and any middleware that
        // inspects routes (e.g. PageCacheMiddleware) share one memoized instance.
        $matcher = new RouteMatcher($this->routes);
        $this->container->instance(RouteMatcherInterface::class, $matcher);

        // Create and register Router. The renderer is resolved through the
        // container so an app-level #[Preference] on ExceptionRenderer applies.
        $router = new Router(
            $matcher,
            $this->container,
            $globalMiddleware,
            $this->container->get(ExceptionRenderer::class),
        );
        $this->container->instance(Router::class, $router);

        return $router;
    }

    /**
     * Every middleware a route excludes must be in its stack (global or route
     * middleware), so a typo or an uninstalled package fails at boot instead
     * of silently excluding nothing.
     *
     * @param array<class-string<MiddlewareInterface>> $globalMiddleware
     * @throws RouteException
     */
    private function assertExcludedMiddlewareExists(
        array $globalMiddleware,
    ): void {
        foreach ($this->routes->all() as $route) {
            $stack = [...$globalMiddleware, ...$route->middleware];

            foreach ($route->withoutMiddleware as $excluded) {
                if (!in_array($excluded, $stack, true)) {
                    throw RouteException::excludedMiddlewareNotInStack($route, $excluded, $stack);
                }
            }
        }
    }

    /**
     * Discover routes from all loaded modules.
     *
     * @throws RouteException|RouteConflictException|ReflectionException
     */
    private function discoverRoutes(): void
    {
        /** @var array<string, bool> $processedControllers */
        $processedControllers = [];

        foreach ($this->modules as $module) {
            $controllerClasses = $this->findControllerClasses($module);

            foreach ($controllerClasses as $className) {
                // Skip if this controller's parent has a Preference that replaces it
                // (we'll process the Preference class instead)
                if ($this->isReplacedByPreference($className)) {
                    continue;
                }

                // Skip if already processed (e.g., as a Preference)
                if (isset($processedControllers[$className])) {
                    continue;
                }

                $processedControllers[$className] = true;

                // Resolve routes considering Preference inheritance
                $routes = $this->resolver->resolveRoutes($className);

                foreach ($routes as $route) {
                    $this->routes->add($route);
                }
            }
        }
    }

    /**
     * Find controller classes in a module's src directory.
     *
     * @return array<string>
     * @throws ReflectionException
     */
    private function findControllerClasses(
        ModuleManifest $module,
    ): array {
        $srcPath = $module->path . '/src';
        if (!is_dir($srcPath)) {
            return [];
        }

        $classes = [];

        foreach ($this->classFileParser->findPhpFiles($srcPath) as $file) {
            $filePath = $file->getPathname();
            $className = $this->classFileParser->extractClassName($filePath);

            if ($className === null) {
                continue;
            }

            // Ensure the class file is loaded
            if (!$this->classFileParser->loadClass($filePath, $className)) {
                continue;
            }

            // Check if the class has any route attributes (including from parent)
            // or if it's a Preference that replaces a controller with routes
            if ($this->isRoutableController($className)) {
                $classes[] = $className;
            }
        }

        return $classes;
    }

    /**
     * Check if a class is a routable controller.
     *
     * A class is routable if:
     * 1. It has route attributes on its own methods, OR
     * 2. It's a Preference that extends a class with route attributes
     *
     * @throws ReflectionException
     */
    private function isRoutableController(
        string $className,
    ): bool {
        $reflection = new ReflectionClass($className);

        // Check if this class itself has route attributes
        if ($this->hasRouteAttributes($reflection)) {
            return true;
        }

        // Check if this is a Preference that extends a controller with routes
        if ($this->isPreferenceForController($reflection)) {
            return true;
        }

        return false;
    }

    /**
     * Check if a class has any route attributes on its own methods (not inherited).
     */
    private function hasRouteAttributes(
        ReflectionClass $reflection,
    ): bool {
        foreach ($reflection->getMethods() as $method) {
            // Only check methods declared in this class, not inherited
            if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
                // But also check if an inherited method has route attributes
                foreach ($method->getAttributes() as $attribute) {
                    try {
                        $instance = $attribute->newInstance();
                    } catch (Error) {
                        continue;
                    }
                    if ($instance instanceof Route) {
                        return true;
                    }
                }
                continue;
            }

            foreach ($method->getAttributes() as $attribute) {
                try {
                    $instance = $attribute->newInstance();
                } catch (Error) {
                    continue;
                }
                if ($instance instanceof Route) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if a class is a Preference that extends a controller with routes.
     *
     * @throws ReflectionException
     */
    private function isPreferenceForController(
        ReflectionClass $reflection,
    ): bool {
        $preferenceAttributes = $reflection->getAttributes(Preference::class);
        if (empty($preferenceAttributes)) {
            return false;
        }

        $preference = $preferenceAttributes[0]->newInstance();
        $replacedClass = $preference->replaces;

        // Check if the replaced class has route attributes
        if (!class_exists($replacedClass)) {
            return false;
        }

        $replacedReflection = new ReflectionClass($replacedClass);

        return $this->hasRouteAttributes($replacedReflection);
    }

    /**
     * Check if a class is replaced by a Preference.
     */
    private function isReplacedByPreference(
        string $className,
    ): bool {
        $preference = $this->preferenceRegistry->getPreference($className);

        return $preference !== null;
    }
}
