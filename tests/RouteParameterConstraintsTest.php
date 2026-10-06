<?php

declare(strict_types=1);

use Marko\Routing\Exceptions\RouteException;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDefinition;
use Marko\Routing\RouteMatcher;

function constraintRoute(
    string $path,
): RouteDefinition {
    return new RouteDefinition(
        method: 'GET',
        path: $path,
        controller: 'ConstraintController',
        action: 'show',
    );
}

describe('constrained and catch-all parameters', function (): void {
    it('compiles a constrained parameter into its regex', function (): void {
        $route = constraintRoute('/shows/{id:\d+}');

        expect($route->parameters)->toBe(['id'])
            ->and($route->constraints)->toBe(['id' => '\d+'])
            ->and($route->regex)->toBe('#^/shows/(?P<id>\d+)$#')
            ->and(preg_match($route->regex, '/shows/42'))->toBe(1);
    });

    it('matches a catch-all parameter across slashes', function (): void {
        $collection = new RouteCollection();
        $collection->add(constraintRoute('/docs/{path*}'));

        $matched = (new RouteMatcher($collection))->match('GET', '/docs/a/b/c');

        expect($matched)->not->toBeNull()
            ->and($matched->parameters)->toBe(['path' => 'a/b/c'])
            ->and($matched->route->catchAll)->toBe('path');
    });

    it('rejects a value that does not satisfy the parameter constraint', function (): void {
        $collection = new RouteCollection();
        $collection->add(constraintRoute('/shows/{id:\d+}'));

        expect((new RouteMatcher($collection))->match('GET', '/shows/abc'))->toBeNull();
    });

    it('supports braces inside a constraint regex', function (): void {
        $route = constraintRoute('/archive/{year:\d{4}}/{slug}');

        expect($route->parameters)->toBe(['year', 'slug'])
            ->and($route->constraints)->toBe(['year' => '\d{4}'])
            ->and($route->staticSegmentCount)->toBe(1)
            ->and(preg_match($route->regex, '/archive/2026/hello'))->toBe(1)
            ->and(preg_match($route->regex, '/archive/26/hello'))->toBe(0);
    });

    it('throws when a catch-all parameter is not the final segment', function (): void {
        constraintRoute('/docs/{path*}/edit');
    })->throws(RouteException::class, "Catch-all parameter 'path' must be the last segment");

    it('throws when a catch-all parameter does not fill its whole segment', function (): void {
        constraintRoute('/docs/page-{path*}');
    })->throws(RouteException::class, "Catch-all parameter 'path' must be the last segment");

    it('throws when a constraint regex is invalid', function (): void {
        constraintRoute('/shows/{id:[0-9}');
    })->throws(RouteException::class, "Invalid constraint for route parameter 'id'");

    it('throws when a constraint regex contains a capturing group', function (): void {
        constraintRoute('/shows/{id:(\d+)}');
    })->throws(RouteException::class, "Constraint for route parameter 'id' contains a capturing group");

    it('allows non-capturing groups in a constraint regex', function (): void {
        $route = constraintRoute('/files/{name:(?:a|b)+}');

        expect(preg_match($route->regex, '/files/abba'))->toBe(1);
    });

    it('throws when a parameter name is not a valid identifier', function (): void {
        constraintRoute('/shows/{show-id}');
    })->throws(RouteException::class, "Invalid route parameter 'show-id'");

    it('throws when a parameter name is used twice', function (): void {
        constraintRoute('/shows/{id}/episodes/{id}');
    })->throws(RouteException::class, "Invalid route parameter 'id'");

    it('throws when a parameter brace is not closed', function (): void {
        constraintRoute('/shows/{id');
    })->throws(RouteException::class, "Invalid route parameter '{id'");

    it('escapes a hash inside a constraint regex', function (): void {
        $route = constraintRoute('/tags/{tag:[a-z#]+}');

        expect(preg_match($route->regex, '/tags/c#'))->toBe(1);
    });
});
