<?php

declare(strict_types=1);

use Marko\Routing\Exceptions\RouteConflictException;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDefinition;
use Marko\Routing\RouteMatcher;

it('stores RouteDefinition objects', function () {
    $collection = new RouteCollection();
    $route = new RouteDefinition(
        method: 'GET',
        path: '/posts',
        controller: 'PostController',
        action: 'index',
    );

    $collection->add($route);

    expect($collection->count())->toBe(1);
});

it('indexes routes by method and path', function () {
    $collection = new RouteCollection();
    $route = new RouteDefinition(
        method: 'GET',
        path: '/posts',
        controller: 'PostController',
        action: 'index',
    );

    $collection->add($route);

    expect($collection->has('GET', '/posts'))->toBeTrue();
});

it('retrieves route by method and path', function () {
    $collection = new RouteCollection();
    $route = new RouteDefinition(
        method: 'GET',
        path: '/posts',
        controller: 'PostController',
        action: 'index',
    );

    $collection->add($route);

    expect($collection->get('GET', '/posts'))->toBe($route);
});

it('returns null for non-existent route', function () {
    $collection = new RouteCollection();

    expect($collection->get('GET', '/non-existent'))->toBeNull();
});

it('throws RouteConflictException for duplicate GET routes', function () {
    $collection = new RouteCollection();
    $route1 = new RouteDefinition(
        method: 'GET',
        path: '/posts',
        controller: 'PostController',
        action: 'index',
    );
    $route2 = new RouteDefinition(
        method: 'GET',
        path: '/posts',
        controller: 'AnotherController',
        action: 'list',
    );

    $collection->add($route1);
    $collection->add($route2);
})->throws(RouteConflictException::class);

it('throws RouteConflictException for duplicate POST routes', function () {
    $collection = new RouteCollection();
    $route1 = new RouteDefinition(
        method: 'POST',
        path: '/posts',
        controller: 'PostController',
        action: 'store',
    );
    $route2 = new RouteDefinition(
        method: 'POST',
        path: '/posts',
        controller: 'AnotherController',
        action: 'create',
    );

    $collection->add($route1);
    $collection->add($route2);
})->throws(RouteConflictException::class);

it('allows same path with different methods', function () {
    $collection = new RouteCollection();
    $getRoute = new RouteDefinition(
        method: 'GET',
        path: '/posts',
        controller: 'PostController',
        action: 'index',
    );
    $postRoute = new RouteDefinition(
        method: 'POST',
        path: '/posts',
        controller: 'PostController',
        action: 'store',
    );

    $collection->add($getRoute);
    $collection->add($postRoute);

    expect($collection->count())->toBe(2)
        ->and($collection->get('GET', '/posts'))->toBe($getRoute)
        ->and($collection->get('POST', '/posts'))->toBe($postRoute);
});

it('RouteConflictException includes both controller class names', function () {
    $collection = new RouteCollection();
    $route1 = new RouteDefinition(
        method: 'GET',
        path: '/posts',
        controller: 'App\\Controllers\\PostController',
        action: 'index',
    );
    $route2 = new RouteDefinition(
        method: 'GET',
        path: '/posts',
        controller: 'App\\Controllers\\AnotherController',
        action: 'list',
    );

    $collection->add($route1);

    try {
        $collection->add($route2);
    } catch (RouteConflictException $e) {
        $context = $e->getContext();
        expect(str_contains($context, 'App\\Controllers\\PostController'))->toBeTrue()
            ->and(str_contains($context, 'App\\Controllers\\AnotherController'))->toBeTrue();

        return;
    }

    throw new Exception('Expected RouteConflictException was not thrown');
});

it('returns all routes as array', function () {
    $collection = new RouteCollection();
    $route1 = new RouteDefinition(
        method: 'GET',
        path: '/posts',
        controller: 'PostController',
        action: 'index',
    );
    $route2 = new RouteDefinition(
        method: 'POST',
        path: '/posts',
        controller: 'PostController',
        action: 'store',
    );
    $route3 = new RouteDefinition(
        method: 'GET',
        path: '/users',
        controller: 'UserController',
        action: 'index',
    );

    $collection->add($route1);
    $collection->add($route2);
    $collection->add($route3);

    $allRoutes = $collection->all();

    expect($allRoutes)->toBeArray()
        ->and($allRoutes)->toHaveCount(3)
        ->and($allRoutes)->toContain($route1)
        ->and($allRoutes)->toContain($route2)
        ->and($allRoutes)->toContain($route3);
});

it('RouteConflictException includes the conflicting path', function () {
    $collection = new RouteCollection();
    $route1 = new RouteDefinition(
        method: 'GET',
        path: '/users/{id}/posts',
        controller: 'PostController',
        action: 'index',
    );
    $route2 = new RouteDefinition(
        method: 'GET',
        path: '/users/{id}/posts',
        controller: 'AnotherController',
        action: 'list',
    );

    $collection->add($route1);

    try {
        $collection->add($route2);
    } catch (RouteConflictException $e) {
        expect($e->getMessage())->toContain('/users/{id}/posts');

        return;
    }

    throw new Exception('Expected RouteConflictException was not thrown');
});

it('returns routes filtered by HTTP method', function () {
    $collection = new RouteCollection();
    $getRoute1 = new RouteDefinition(
        method: 'GET',
        path: '/posts',
        controller: 'PostController',
        action: 'index',
    );
    $getRoute2 = new RouteDefinition(
        method: 'GET',
        path: '/users',
        controller: 'UserController',
        action: 'index',
    );
    $postRoute = new RouteDefinition(
        method: 'POST',
        path: '/posts',
        controller: 'PostController',
        action: 'store',
    );
    $deleteRoute = new RouteDefinition(
        method: 'DELETE',
        path: '/posts/{id}',
        controller: 'PostController',
        action: 'destroy',
    );

    $collection->add($getRoute1);
    $collection->add($getRoute2);
    $collection->add($postRoute);
    $collection->add($deleteRoute);

    $getRoutes = $collection->byMethod('GET');

    expect($getRoutes)->toBeArray()
        ->and($getRoutes)->toHaveCount(2)
        ->and($getRoutes)->toContain($getRoute1)
        ->and($getRoutes)->toContain($getRoute2)
        ->and($getRoutes)->not->toContain($postRoute)
        ->and($getRoutes)->not->toContain($deleteRoute);
});

/**
 * @param array<int, string> $paths
 * @return array<int, string>
 */
function orderedPaths(
    array $paths,
): array {
    $collection = new RouteCollection();

    foreach ($paths as $index => $path) {
        $collection->add(new RouteDefinition(method: 'GET', path: $path, controller: 'C', action: "a$index"));
    }

    return array_map(fn (RouteDefinition $route): string => $route->path, $collection->byMethod('GET'));
}

describe('precedence', function (): void {
    it('orders static routes before dynamic routes regardless of registration order', function (): void {
        expect(orderedPaths(['/shows/{id}', '/shows/live']))->toBe(['/shows/live', '/shows/{id}'])
            ->and(orderedPaths(['/shows/live', '/shows/{id}']))->toBe(['/shows/live', '/shows/{id}']);
    });

    it('orders dynamic routes by descending static segment count', function (): void {
        expect(orderedPaths(['/a/{x}/{y}', '/a/{x}/c']))->toBe(['/a/{x}/c', '/a/{x}/{y}'])
            ->and(orderedPaths(['/a/{x}/c', '/a/{x}/{y}']))->toBe(['/a/{x}/c', '/a/{x}/{y}']);
    });

    it('orders dynamic routes with equal static segments by descending static prefix length', function (): void {
        expect(orderedPaths(['/{a}/users/list', '/api/{b}/list']))->toBe(['/api/{b}/list', '/{a}/users/list']);
    });

    it('keeps registration order for routes of equal specificity', function (): void {
        expect(orderedPaths(['/posts/{id}', '/posts/{slug}/x', '/pages/{id}']))
            ->toBe(['/posts/{slug}/x', '/posts/{id}', '/pages/{id}']);
    });

    it('tries a constrained route before an unconstrained route with the same static segments', function (): void {
        expect(orderedPaths(['/shows/{slug}', '/shows/{id:\d+}']))->toBe(['/shows/{id:\d+}', '/shows/{slug}']);
    });

    it('still orders by static segment count before constraints', function (): void {
        expect(orderedPaths(['/a/{x:\d+}/{y}', '/a/{x}/c']))->toBe(['/a/{x}/c', '/a/{x:\d+}/{y}']);
    });

    it('tries catch-all routes after other dynamic routes', function (): void {
        expect(orderedPaths(['/docs/{path*}', '/{section}/{page}', '/docs/{page}']))
            ->toBe(['/docs/{page}', '/{section}/{page}', '/docs/{path*}']);
    });

    it('falls through to the next route when a constraint rejects the value', function (): void {
        $collection = new RouteCollection();
        $collection->add(new RouteDefinition(method: 'GET', path: '/shows/{slug}', controller: 'C', action: 'bySlug'));
        $collection->add(new RouteDefinition(method: 'GET', path: '/shows/{id:\d+}', controller: 'C', action: 'byId'));
        $matcher = new RouteMatcher($collection);

        expect($matcher->match('GET', '/shows/42')->route->action)->toBe('byId')
            ->and($matcher->match('GET', '/shows/abc')->route->action)->toBe('bySlug');
    });

    it('returns no match when the only candidate constraint rejects the value', function (): void {
        $collection = new RouteCollection();
        $collection->add(new RouteDefinition(method: 'GET', path: '/shows/{id:\d+}', controller: 'C', action: 'byId'));
        $matcher = new RouteMatcher($collection);

        expect($matcher->match('GET', '/shows/abc'))->toBeNull()
            ->and($matcher->allowedMethods('/shows/abc'))->toBeEmpty();
    });

    it('re-sorts after a route is added', function (): void {
        $collection = new RouteCollection();
        $collection->add(new RouteDefinition(method: 'GET', path: '/shows/{id}', controller: 'C', action: 'show'));
        $collection->byMethod('GET');
        $collection->add(new RouteDefinition(method: 'GET', path: '/shows/live', controller: 'C', action: 'live'));

        expect($collection->byMethod('GET')[0]->path)->toBe('/shows/live');
    });

    it('returns a static route by exact path lookup', function (): void {
        $collection = new RouteCollection();
        $static = new RouteDefinition(method: 'GET', path: '/shows/live', controller: 'C', action: 'live');
        $dynamic = new RouteDefinition(method: 'GET', path: '/shows/{id}', controller: 'C', action: 'show');
        $collection->add($dynamic);
        $collection->add($static);

        expect($collection->staticRoute('GET', '/shows/live'))->toBe($static)
            ->and($collection->staticRoute('GET', '/shows/{id}'))->toBeNull()
            ->and($collection->staticRoute('POST', '/shows/live'))->toBeNull();
    });

    it('lists the distinct methods that have routes', function (): void {
        $collection = new RouteCollection();
        $collection->add(new RouteDefinition(method: 'POST', path: '/a', controller: 'C', action: 'a'));
        $collection->add(new RouteDefinition(method: 'GET', path: '/a', controller: 'C', action: 'b'));
        $collection->add(new RouteDefinition(method: 'GET', path: '/b', controller: 'C', action: 'c'));

        expect($collection->methods())->toBe(['POST', 'GET']);
    });
});
