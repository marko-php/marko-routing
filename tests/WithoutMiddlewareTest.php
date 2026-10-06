<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Core\Discovery\ClassFileParser;
use Marko\Core\Module\ModuleManifest;
use Marko\Routing\Attributes\WithoutMiddleware;
use Marko\Routing\Exceptions\RouteException;
use Marko\Routing\Http\Request;
use Marko\Routing\PreferenceRouteResolver;
use Marko\Routing\RouteDefinition;
use Marko\Routing\RouteDiscovery;
use Marko\Routing\Router;
use Marko\Routing\RoutingBootstrapper;
use Test\StatelessModule\AbstractRecordingMiddleware;
use Test\StatelessModule\FirstGlobalMiddleware;
use Test\StatelessModule\RouteOnlyMiddleware;
use Test\StatelessModule\SecondGlobalMiddleware;
use Test\StatelessModule\StatelessController;
use Test\StatelessPreference\GuardedChildController;
use Test\StatelessPreference\GuardedParentController;

foreach (['AbstractRecordingMiddleware', 'FirstGlobalMiddleware', 'SecondGlobalMiddleware', 'RouteOnlyMiddleware', 'StatelessController', 'StatefulController'] as $fixture) {
    require_once __DIR__ . "/Fixtures/StatelessModule/src/$fixture.php";
}

require_once __DIR__ . '/Fixtures/StatelessPreference/src/GuardedParentController.php';
require_once __DIR__ . '/Fixtures/StatelessPreference/src/GuardedChildController.php';

/**
 * @param array<int, class-string> $globalMiddleware
 */
function bootStatelessRouter(
    array $globalMiddleware,
    string $module = 'StatelessModule',
    ?PreferenceRegistry $registry = null,
): Router {
    $registry ??= new PreferenceRegistry();
    $bootstrapper = new RoutingBootstrapper(
        modules: [new ModuleManifest(name: 'test/stateless', version: '1.0.0', path: __DIR__ . "/Fixtures/$module")],
        container: new Container($registry),
        preferenceRegistry: $registry,
        classFileParser: new ClassFileParser(),
    );

    return $bootstrapper->boot($globalMiddleware);
}

/**
 * @return array<string, RouteDefinition> Routes of GuardedChildController keyed by action
 */
function statelessPreferenceRoutes(): array
{
    $registry = new PreferenceRegistry();
    $registry->register(GuardedParentController::class, GuardedChildController::class);
    $routes = [];

    foreach ((new PreferenceRouteResolver($registry, new RouteDiscovery()))->resolveRoutes(
        GuardedChildController::class,
    ) as $route) {
        $routes[$route->action] = $route;
    }

    return $routes;
}

function statelessRequest(
    string $path,
): Request {
    return new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $path]);
}

describe('#[WithoutMiddleware]', function (): void {
    it('accepts a single class or a list of classes on classes and methods', function (): void {
        $flags = (new ReflectionClass(WithoutMiddleware::class))->getAttributes(
            Attribute::class,
        )[0]->newInstance()->flags;

        expect((new WithoutMiddleware('A'))->middleware)->toBe(['A'])
            ->and((new WithoutMiddleware(['A', 'B']))->middleware)->toBe(['A', 'B'])
            ->and($flags)->toBe(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD);
    });

    it('collects excluded middleware from class and method attributes', function (): void {
        $routes = (new RouteDiscovery())->discoverFromClass(StatelessController::class);
        $excluded = [];

        foreach ($routes as $route) {
            $excluded[$route->action] = $route->withoutMiddleware;
        }

        expect($excluded)->toBe([
            'stateless' => [FirstGlobalMiddleware::class],
            'bare' => [FirstGlobalMiddleware::class, SecondGlobalMiddleware::class, RouteOnlyMiddleware::class],
        ]);
    });

    it('defaults to no excluded middleware on a route definition', function (): void {
        $route = new RouteDefinition(method: 'GET', path: '/', controller: 'C', action: 'a');

        expect($route->withoutMiddleware)->toBeEmpty();
    });

    it('skips an excluded global middleware for the route', function (): void {
        $router = bootStatelessRouter([FirstGlobalMiddleware::class, SecondGlobalMiddleware::class]);

        $response = $router->handle(statelessRequest('/stateless'));

        expect($response->body())->toBe('stateless')
            ->and($response->headers()['X-Ran'])->toBe('SecondGlobalMiddleware');
    });

    it('still runs the excluded middleware on other routes', function (): void {
        $router = bootStatelessRouter([FirstGlobalMiddleware::class, SecondGlobalMiddleware::class]);

        $response = $router->handle(statelessRequest('/stateful'));

        expect($response->headers()['X-Ran'])->toBe('SecondGlobalMiddleware,FirstGlobalMiddleware');
    });

    it('skips an excluded route middleware', function (): void {
        $router = bootStatelessRouter([FirstGlobalMiddleware::class, SecondGlobalMiddleware::class]);

        $response = $router->handle(statelessRequest('/stateless/bare'));

        expect($response->body())->toBe('bare')
            ->and($response->headers())->not->toHaveKey('X-Ran');
    });

    it('runs no global middleware without RunsOnUnmatched for unmatched requests', function (): void {
        $router = bootStatelessRouter([FirstGlobalMiddleware::class, SecondGlobalMiddleware::class]);
        AbstractRecordingMiddleware::$ran = [];

        $response = $router->handle(statelessRequest('/missing'));

        expect($response->statusCode())->toBe(404)
            ->and(AbstractRecordingMiddleware::$ran)->toBe([]);
    });

    it('keeps class-level excluded middleware on routes inherited through a Preference', function (): void {
        $routes = statelessPreferenceRoutes();

        expect($routes['index']->withoutMiddleware)->toBe([FirstGlobalMiddleware::class])
            ->and($routes['open']->withoutMiddleware)->toBe(
                [FirstGlobalMiddleware::class, RouteOnlyMiddleware::class],
            );
    });

    it('keeps the parent class-level middleware on routes inherited through a Preference', function (): void {
        $routes = statelessPreferenceRoutes();

        expect($routes['index']->middleware)->toBe([RouteOnlyMiddleware::class])
            ->and($routes['index']->controller)->toBe(GuardedChildController::class);
    });

    it(
        'does not throw at boot when a Preference inherits a route that excludes its parent class middleware',
        function (): void {
            $registry = new PreferenceRegistry();
            $registry->register(GuardedParentController::class, GuardedChildController::class);

            $router = bootStatelessRouter(
                [FirstGlobalMiddleware::class, SecondGlobalMiddleware::class],
                'StatelessPreference',
                $registry,
            );
            $response = $router->handle(statelessRequest('/guarded/open'));

            expect($response->body())->toBe('open')
                ->and($response->headers()['X-Ran'])->toBe('SecondGlobalMiddleware');
        },
    );

    it('throws at boot when a route excludes middleware that is not in its stack', function (): void {
        try {
            bootStatelessRouter([SecondGlobalMiddleware::class]);
            $this->fail('Expected a RouteException');
        } catch (RouteException $exception) {
            expect($exception->getMessage())->toContain(FirstGlobalMiddleware::class)
                ->and($exception->getContext())->toContain('GET /stateless')
                ->and($exception->getContext())->toContain(StatelessController::class . '::stateless()')
                ->and($exception->getSuggestion())->toContain('#[WithoutMiddleware]');
        }
    });
});
