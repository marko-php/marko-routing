<?php

declare(strict_types=1);

use Marko\Routing\MatchedRoute;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDefinition;
use Marko\Routing\RouteMatcher;

it('matches exact static path', function () {
    $collection = new RouteCollection();
    $route = new RouteDefinition(
        method: 'GET',
        path: '/posts',
        controller: 'PostController',
        action: 'index',
    );
    $collection->add($route);

    $matcher = new RouteMatcher($collection);
    $result = $matcher->match('GET', '/posts');

    expect($result)->toBeInstanceOf(MatchedRoute::class)
        ->and($result->route)->toBe($route);
});

it('matches path with single parameter', function () {
    $collection = new RouteCollection();
    $route = new RouteDefinition(
        method: 'GET',
        path: '/posts/{id}',
        controller: 'PostController',
        action: 'show',
    );
    $collection->add($route);

    $matcher = new RouteMatcher($collection);
    $result = $matcher->match('GET', '/posts/123');

    expect($result)->toBeInstanceOf(MatchedRoute::class)
        ->and($result->route)->toBe($route);
});

it('matches path with multiple parameters', function () {
    $collection = new RouteCollection();
    $route = new RouteDefinition(
        method: 'GET',
        path: '/users/{userId}/posts/{postId}',
        controller: 'PostController',
        action: 'show',
    );
    $collection->add($route);

    $matcher = new RouteMatcher($collection);
    $result = $matcher->match('GET', '/users/42/posts/123');

    expect($result)->toBeInstanceOf(MatchedRoute::class)
        ->and($result->route)->toBe($route);
});

it('extracts parameter value from matched path', function () {
    $collection = new RouteCollection();
    $route = new RouteDefinition(
        method: 'GET',
        path: '/posts/{id}',
        controller: 'PostController',
        action: 'show',
    );
    $collection->add($route);

    $matcher = new RouteMatcher($collection);
    $result = $matcher->match('GET', '/posts/123');

    expect($result->parameters)->toBe(['id' => '123']);
});

it('extracts multiple parameter values', function () {
    $collection = new RouteCollection();
    $route = new RouteDefinition(
        method: 'GET',
        path: '/users/{userId}/posts/{postId}',
        controller: 'PostController',
        action: 'show',
    );
    $collection->add($route);

    $matcher = new RouteMatcher($collection);
    $result = $matcher->match('GET', '/users/42/posts/123');

    expect($result->parameters)->toBe([
        'userId' => '42',
        'postId' => '123',
    ]);
});

it('returns null for non-matching path', function () {
    $collection = new RouteCollection();
    $route = new RouteDefinition(
        method: 'GET',
        path: '/posts',
        controller: 'PostController',
        action: 'index',
    );
    $collection->add($route);

    $matcher = new RouteMatcher($collection);
    $result = $matcher->match('GET', '/users');

    expect($result)->toBeNull();
});

it('returns null for wrong HTTP method', function () {
    $collection = new RouteCollection();
    $route = new RouteDefinition(
        method: 'GET',
        path: '/posts',
        controller: 'PostController',
        action: 'index',
    );
    $collection->add($route);

    $matcher = new RouteMatcher($collection);
    $result = $matcher->match('POST', '/posts');

    expect($result)->toBeNull();
});

it('matches correct route when multiple routes have same prefix', function () {
    $collection = new RouteCollection();
    $indexRoute = new RouteDefinition(
        method: 'GET',
        path: '/posts',
        controller: 'PostController',
        action: 'index',
    );
    $showRoute = new RouteDefinition(
        method: 'GET',
        path: '/posts/{id}',
        controller: 'PostController',
        action: 'show',
    );
    $commentsRoute = new RouteDefinition(
        method: 'GET',
        path: '/posts/{id}/comments',
        controller: 'CommentController',
        action: 'index',
    );
    $collection->add($indexRoute);
    $collection->add($showRoute);
    $collection->add($commentsRoute);

    $matcher = new RouteMatcher($collection);

    expect($matcher->match('GET', '/posts')->route)->toBe($indexRoute)
        ->and($matcher->match('GET', '/posts/123')->route)->toBe($showRoute)
        ->and($matcher->match('GET', '/posts/123/comments')->route)->toBe($commentsRoute);
});

it('returns MatchedRoute with route definition and parameters', function () {
    $collection = new RouteCollection();
    $route = new RouteDefinition(
        method: 'GET',
        path: '/posts/{id}',
        controller: 'PostController',
        action: 'show',
    );
    $collection->add($route);

    $matcher = new RouteMatcher($collection);
    $result = $matcher->match('GET', '/posts/456');

    expect($result)->toBeInstanceOf(MatchedRoute::class)
        ->and($result->route)->toBe($route)
        ->and($result->route->controller)->toBe('PostController')
        ->and($result->route->action)->toBe('show')
        ->and($result->parameters)->toBe(['id' => '456']);
});

it('handles trailing slashes consistently', function () {
    $collection = new RouteCollection();
    $route = new RouteDefinition(
        method: 'GET',
        path: '/posts',
        controller: 'PostController',
        action: 'index',
    );
    $collection->add($route);

    $matcher = new RouteMatcher($collection);

    // Route defined without trailing slash should match both with and without
    expect($matcher->match('GET', '/posts'))->toBeInstanceOf(MatchedRoute::class)
        ->and($matcher->match('GET', '/posts/'))->toBeInstanceOf(MatchedRoute::class);
});

it('matches root path /', function () {
    $collection = new RouteCollection();
    $route = new RouteDefinition(
        method: 'GET',
        path: '/',
        controller: 'HomeController',
        action: 'index',
    );
    $collection->add($route);

    $matcher = new RouteMatcher($collection);
    $result = $matcher->match('GET', '/');

    expect($result)->toBeInstanceOf(MatchedRoute::class)
        ->and($result->route)->toBe($route)
        ->and($result->parameters)->toBe([]);
});

it('memoizes match() results per (method, path)', function () {
    $collection = new RouteCollection();
    $collection->add(new RouteDefinition(
        method: 'GET',
        path: '/products/{id}',
        controller: 'ProductController',
        action: 'show',
    ));

    $matcher = new RouteMatcher($collection);

    $first = $matcher->match('GET', '/products/42');
    $second = $matcher->match('GET', '/products/42');

    // Same input must return the identical MatchedRoute object (memoized)
    expect($second)->toBe($first);
});

function matcherMemoSize(
    RouteMatcher $matcher,
): int {
    return count((new ReflectionProperty(RouteMatcher::class, 'memo'))->getValue($matcher));
}

it('does not memoize misses', function () {
    $collection = new RouteCollection();
    $collection->add(new RouteDefinition(
        method: 'GET',
        path: '/products/{id}',
        controller: 'ProductController',
        action: 'show',
    ));

    $matcher = new RouteMatcher($collection);

    for ($i = 0; $i < 50; $i++) {
        expect($matcher->match('GET', "/missing/$i"))->toBeNull();
    }

    expect(matcherMemoSize($matcher))->toBe(0);
});

it('keeps the memo bounded after many unique paths', function () {
    $collection = new RouteCollection();
    $collection->add(new RouteDefinition(
        method: 'GET',
        path: '/shows/{id}',
        controller: 'ShowController',
        action: 'show',
    ));

    $matcher = new RouteMatcher($collection);

    for ($i = 0; $i < RouteMatcher::MAX_MEMO_ENTRIES * 2 + 5; $i++) {
        $matcher->match('GET', "/shows/$i");
    }

    expect(matcherMemoSize($matcher))->toBeLessThanOrEqual(RouteMatcher::MAX_MEMO_ENTRIES)
        ->and($matcher->match('GET', '/shows/abc')?->parameters)->toBe(['id' => 'abc']);
});

it('does not memoize static routes', function () {
    $collection = new RouteCollection();
    $collection->add(new RouteDefinition(
        method: 'GET',
        path: '/shows/live',
        controller: 'ShowController',
        action: 'live',
    ));

    $matcher = new RouteMatcher($collection);
    $matcher->match('GET', '/shows/live');

    expect(matcherMemoSize($matcher))->toBe(0);
});

describe('precedence', function (): void {
    it('matches a static route over a dynamic route registered before it', function (): void {
        $collection = new RouteCollection();
        $dynamic = new RouteDefinition(
            method: 'GET',
            path: '/shows/{id}',
            controller: 'ShowController',
            action: 'show',
        );
        $static = new RouteDefinition(
            method: 'GET',
            path: '/shows/live',
            controller: 'ShowController',
            action: 'live',
        );
        $collection->add($dynamic);
        $collection->add($static);

        $matcher = new RouteMatcher($collection);

        expect($matcher->match('GET', '/shows/live')?->route)->toBe($static)
            ->and($matcher->match('GET', '/shows/live/')?->route)->toBe($static)
            ->and($matcher->match('GET', '/shows/42')?->route)->toBe($dynamic);
    });

    it('matches a static route over a dynamic route registered after it', function (): void {
        $collection = new RouteCollection();
        $static = new RouteDefinition(
            method: 'GET',
            path: '/shows/live',
            controller: 'ShowController',
            action: 'live',
        );
        $collection->add($static);
        $collection->add(
            new RouteDefinition(method: 'GET', path: '/shows/{id}', controller: 'ShowController', action: 'show'),
        );

        expect((new RouteMatcher($collection))->match('GET', '/shows/live')?->route)->toBe($static);
    });

    it('matches a more specific dynamic route regardless of registration order', function (): void {
        foreach ([true, false] as $specificFirst) {
            $collection = new RouteCollection();
            $specific = new RouteDefinition(method: 'GET', path: '/a/{x}/c', controller: 'C', action: 'specific');
            $generic = new RouteDefinition(method: 'GET', path: '/a/{x}/{y}', controller: 'C', action: 'generic');

            if ($specificFirst) {
                $collection->add($specific);
                $collection->add($generic);
            } else {
                $collection->add($generic);
                $collection->add($specific);
            }

            $matcher = new RouteMatcher($collection);

            expect($matcher->match('GET', '/a/1/c')?->route)->toBe($specific)
                ->and($matcher->match('GET', '/a/1/d')?->route)->toBe($generic);
        }
    });
});

describe('HEAD', function (): void {
    it('falls back to the GET route for HEAD when no HEAD route matches', function (): void {
        $collection = new RouteCollection();
        $get = new RouteDefinition(method: 'GET', path: '/posts/{id}', controller: 'PostController', action: 'show');
        $collection->add($get);

        $matched = (new RouteMatcher($collection))->match('HEAD', '/posts/7');

        expect($matched?->route)->toBe($get)
            ->and($matched?->parameters)->toBe(['id' => '7']);
    });

    it('prefers an explicit HEAD route over the GET fallback', function (): void {
        $collection = new RouteCollection();
        $collection->add(
            new RouteDefinition(method: 'GET', path: '/posts', controller: 'PostController', action: 'index'),
        );
        $head = new RouteDefinition(method: 'HEAD', path: '/posts', controller: 'PostController', action: 'head');
        $collection->add($head);

        expect((new RouteMatcher($collection))->match('HEAD', '/posts')?->route)->toBe($head);
    });
});

describe('allowedMethods', function (): void {
    it('returns the methods allowed for a path including HEAD and OPTIONS', function (): void {
        $collection = new RouteCollection();
        $collection->add(
            new RouteDefinition(method: 'DELETE', path: '/posts/{id}', controller: 'C', action: 'destroy'),
        );
        $collection->add(new RouteDefinition(method: 'GET', path: '/posts/{id}', controller: 'C', action: 'show'));
        $collection->add(new RouteDefinition(method: 'PUT', path: '/posts/{id}', controller: 'C', action: 'update'));
        $collection->add(new RouteDefinition(method: 'POST', path: '/posts', controller: 'C', action: 'store'));

        $matcher = new RouteMatcher($collection);

        expect($matcher->allowedMethods('/posts/1'))->toBe(['GET', 'HEAD', 'PUT', 'DELETE', 'OPTIONS'])
            ->and($matcher->allowedMethods('/posts'))->toBe(['POST', 'OPTIONS']);
    });

    it('lists non-standard methods after the standard ones', function (): void {
        $collection = new RouteCollection();
        $collection->add(new RouteDefinition(method: 'PURGE', path: '/cache', controller: 'C', action: 'purge'));
        $collection->add(new RouteDefinition(method: 'GET', path: '/cache', controller: 'C', action: 'show'));

        expect((new RouteMatcher($collection))->allowedMethods('/cache'))->toBe(['GET', 'HEAD', 'OPTIONS', 'PURGE']);
    });

    it('returns no allowed methods for an unknown path', function (): void {
        $collection = new RouteCollection();
        $collection->add(new RouteDefinition(method: 'GET', path: '/posts', controller: 'C', action: 'index'));

        expect((new RouteMatcher($collection))->allowedMethods('/missing'))->toBeEmpty();
    });
});

it('distinguishes cached entries by method and by path', function () {
    $collection = new RouteCollection();
    $getRoute = new RouteDefinition(
        method: 'GET',
        path: '/orders/{id}',
        controller: 'OrderController',
        action: 'show',
    );
    $postRoute = new RouteDefinition(
        method: 'POST',
        path: '/orders/{id}',
        controller: 'OrderController',
        action: 'update',
    );
    $collection->add($getRoute);
    $collection->add($postRoute);

    $matcher = new RouteMatcher($collection);

    $getMatch = $matcher->match('GET', '/orders/1');
    $postMatch = $matcher->match('POST', '/orders/1');
    $otherPath = $matcher->match('GET', '/orders/2');

    expect($getMatch->route)->toBe($getRoute)
        ->and($postMatch->route)->toBe($postRoute)
        ->and($otherPath)->not->toBe($getMatch)
        ->and($otherPath->route)->toBe($getRoute);
});
