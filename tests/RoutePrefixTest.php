<?php

declare(strict_types=1);

use Marko\Core\Container\PreferenceRegistry;
use Marko\Routing\Attributes\RoutePrefix;
use Marko\Routing\Exceptions\RouteException;
use Marko\Routing\PreferenceRouteResolver;
use Marko\Routing\RouteDefinition;
use Marko\Routing\RouteDiscovery;
use Test\NamedRoutes\ApiShowController;
use Test\NamedRoutes\ApiShowPreferenceController;
use Test\NamedRoutes\ApiShowV2Controller;
use Test\NamedRoutes\InvalidPrefixController;

require_once __DIR__ . '/Fixtures/NamedRoutes/ApiShowController.php';
require_once __DIR__ . '/Fixtures/NamedRoutes/ApiShowPreferenceController.php';
require_once __DIR__ . '/Fixtures/NamedRoutes/ApiShowV2Controller.php';
require_once __DIR__ . '/Fixtures/NamedRoutes/InvalidPrefixController.php';

/**
 * @param array<int, RouteDefinition> $routes
 * @return array<string, array{path: string, name: ?string, controller: string}>
 */
function routesByAction(
    array $routes,
): array {
    $byAction = [];

    foreach ($routes as $route) {
        $byAction[$route->action] = ['path' => $route->path, 'name' => $route->name, 'controller' => $route->controller];
    }

    return $byAction;
}

describe('#[RoutePrefix]', function (): void {
    it('is a class-level attribute with an optional name prefix', function (): void {
        $attribute = new RoutePrefix('/api');

        expect($attribute->prefix)->toBe('/api')
            ->and($attribute->namePrefix)->toBe('')
            ->and((new ReflectionClass(RoutePrefix::class))->getAttributes(Attribute::class)[0]->newInstance()->flags)
            ->toBe(Attribute::TARGET_CLASS);
    });

    it('prepends the class prefix to every route path', function (): void {
        $routes = routesByAction((new RouteDiscovery())->discoverFromClass(ApiShowController::class));

        expect($routes['show']['path'])->toBe('/api/v1/shows/{id}');
    });

    it('normalizes slashes when joining prefix and path', function (): void {
        $routes = routesByAction((new RouteDiscovery())->discoverFromClass(ApiShowController::class));

        expect($routes['index']['path'])->toBe('/api/v1/shows')
            ->and($routes['root']['path'])->toBe('/api/v1');
    });

    it('prepends the name prefix to named routes', function (): void {
        $routes = routesByAction((new RouteDiscovery())->discoverFromClass(ApiShowController::class));

        expect($routes['show']['name'])->toBe('api.v1.shows.show')
            ->and($routes['root']['name'])->toBe('api.v1.root');
    });

    it('leaves unnamed routes unnamed when a name prefix is set', function (): void {
        $routes = routesByAction((new RouteDiscovery())->discoverFromClass(ApiShowController::class));

        expect($routes['index']['name'])->toBeNull();
    });

    it('keeps the parent prefix on routes inherited through a Preference', function (): void {
        $registry = new PreferenceRegistry();
        $registry->register(ApiShowController::class, ApiShowPreferenceController::class);
        $resolver = new PreferenceRouteResolver($registry, new RouteDiscovery());

        $routes = routesByAction($resolver->resolveRoutes(ApiShowPreferenceController::class));

        expect($routes['index'])->toBe([
            'path' => '/api/v1/shows',
            'name' => null,
            'controller' => ApiShowPreferenceController::class,
        ])->and($routes['root']['name'])->toBe('api.v1.root');
    });

    it('applies the parent prefix to a Preference override without its own prefix', function (): void {
        $registry = new PreferenceRegistry();
        $registry->register(ApiShowController::class, ApiShowPreferenceController::class);
        $resolver = new PreferenceRouteResolver($registry, new RouteDiscovery());

        $routes = routesByAction($resolver->resolveRoutes(ApiShowPreferenceController::class));

        expect($routes['show'])->toBe([
            'path' => '/api/v1/shows/{id:\d+}',
            'name' => 'api.v1.shows.show',
            'controller' => ApiShowPreferenceController::class,
        ]);
    });

    it('applies a Preference prefix only to the methods the Preference declares', function (): void {
        $registry = new PreferenceRegistry();
        $registry->register(ApiShowController::class, ApiShowV2Controller::class);
        $resolver = new PreferenceRouteResolver($registry, new RouteDiscovery());

        $routes = routesByAction($resolver->resolveRoutes(ApiShowV2Controller::class));

        expect($routes['show']['path'])->toBe('/api/v2/shows/{id}')
            ->and($routes['show']['name'])->toBe('api.v2.shows.show')
            ->and($routes['index']['path'])->toBe('/api/v1/shows');
    });

    it('throws when a prefix does not start with a slash', function (): void {
        (new RouteDiscovery())->discoverFromClass(InvalidPrefixController::class);
    })->throws(RouteException::class, "Route prefix 'admin' must start with '/'");
});
