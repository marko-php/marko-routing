<?php

declare(strict_types=1);

use Marko\Core\Container\PreferenceRegistry;
use Marko\Routing\Attributes\Delete;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Head;
use Marko\Routing\Attributes\Options;
use Marko\Routing\Attributes\Patch;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Attributes\Put;
use Marko\Routing\Exceptions\RouteConflictException;
use Marko\Routing\PreferenceRouteResolver;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDefinition;
use Marko\Routing\RouteDiscovery;
use Test\NamedRoutes\PostController;
use Test\NamedRoutes\PostPreferenceController;

require_once __DIR__ . '/Fixtures/NamedRoutes/PostController.php';
require_once __DIR__ . '/Fixtures/NamedRoutes/PostPreferenceController.php';

describe('named routes', function (): void {
    it('accepts a name on every route attribute', function (string $attribute): void {
        $instance = new $attribute('/posts', name: 'posts.index');

        expect($instance->name)->toBe('posts.index');
    })->with([Get::class, Post::class, Put::class, Patch::class, Delete::class, Head::class, Options::class]);

    it('defaults the route attribute name to null', function (): void {
        expect((new Get('/posts'))->name)->toBeNull();
    });

    it('stores the route name from the attribute on the definition', function (): void {
        $routes = (new RouteDiscovery())->discoverFromClass(PostController::class);
        $names = array_map(fn (RouteDefinition $route): ?string => $route->name, $routes);

        expect($names)->toBe(['posts.index', 'posts.show', null]);
    });

    it('finds a route by name', function (): void {
        $collection = new RouteCollection();
        $route = new RouteDefinition(
            method: 'GET',
            path: '/posts/{id}',
            controller: 'C',
            action: 'show',
            name: 'posts.show',
        );
        $collection->add($route);

        expect($collection->named('posts.show'))->toBe($route)
            ->and($collection->named('posts.missing'))->toBeNull();
    });

    it('throws a duplicate-name conflict naming both controller actions', function (): void {
        $collection = new RouteCollection();
        $collection->add(
            new RouteDefinition(
                method: 'GET',
                path: '/posts',
                controller: 'App\\PostController',
                action: 'index',
                name: 'posts',
            ),
        );

        try {
            $collection->add(
                new RouteDefinition(
                    method: 'GET',
                    path: '/articles',
                    controller: 'App\\ArticleController',
                    action: 'list',
                    name: 'posts',
                ),
            );
            $this->fail('Expected a RouteConflictException');
        } catch (RouteConflictException $exception) {
            expect($exception->getMessage())->toContain("Duplicate route name 'posts'")
                ->and($exception->getContext())->toContain('App\\PostController::index()')
                ->and($exception->getContext())->toContain('App\\ArticleController::list()')
                ->and($exception->getSuggestion())->not->toBeEmpty();
        }
    });

    it('keeps the route name when a Preference inherits the route', function (): void {
        $registry = new PreferenceRegistry();
        $registry->register(PostController::class, PostPreferenceController::class);
        $resolver = new PreferenceRouteResolver($registry, new RouteDiscovery());

        $routes = $resolver->resolveRoutes(PostPreferenceController::class);
        $byName = [];

        foreach ($routes as $route) {
            $byName[(string) $route->name] = $route->controller;
        }

        expect($byName)->toHaveKey('posts.show')
            ->and($byName['posts.show'])->toBe(PostPreferenceController::class);
    });

    it('copies every field when cloning a definition for another controller', function (): void {
        $route = new RouteDefinition(
            method: 'GET',
            path: '/posts/{id:\d+}',
            controller: 'Parent',
            action: 'show',
            middleware: ['M'],
            name: 'posts.show',
        );

        $copy = $route->withController('Child');

        expect($copy->controller)->toBe('Child')
            ->and($copy->name)->toBe('posts.show')
            ->and($copy->middleware)->toBe(['M'])
            ->and($copy->constraints)->toBe(['id' => '\d+'])
            ->and($route->controller)->toBe('Parent');
    });
});
