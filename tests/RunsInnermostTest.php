<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Routing\Attributes\RunsInnermost;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDefinition;
use Marko\Routing\RouteMatcher;
use Marko\Routing\Router;

/**
 * Shared, container-resolved log of the order middleware entered the stack.
 */
class InnermostOrderLog
{
    /** @var array<int, string> */
    public array $entries = [];
}

/**
 * @noinspection PhpUnused - Action is invoked by the router
 */
class InnermostTestController
{
    public function __construct(
        private readonly InnermostOrderLog $log,
    ) {}

    public function index(): Response
    {
        $this->log->entries[] = 'controller';

        return new Response('controller body');
    }
}

class InnermostOuterGlobalMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly InnermostOrderLog $log,
    ) {}

    public function handle(
        Request $request,
        callable $next,
    ): Response {
        $this->log->entries[] = 'outer-global';

        return $next($request);
    }
}

#[RunsInnermost]
class InnermostWrappingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly InnermostOrderLog $log,
    ) {}

    public function handle(
        Request $request,
        callable $next,
    ): Response {
        $this->log->entries[] = 'innermost';
        $next($request);

        return new Response('replaced by innermost');
    }
}

#[RunsInnermost]
class InnermostSecondWrappingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly InnermostOrderLog $log,
    ) {}

    public function handle(
        Request $request,
        callable $next,
    ): Response {
        $this->log->entries[] = 'innermost-second';

        return $next($request);
    }
}

class InnermostRouteMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly InnermostOrderLog $log,
    ) {}

    public function handle(
        Request $request,
        callable $next,
    ): Response {
        $this->log->entries[] = 'route';

        return $next($request)->withHeader('X-Route', 'ran');
    }
}

/**
 * @param array<int, class-string<MiddlewareInterface>> $globalMiddleware
 * @param array<int, class-string<MiddlewareInterface>> $routeMiddleware
 * @param array<int, class-string<MiddlewareInterface>> $withoutMiddleware
 * @return array{router: Router, log: InnermostOrderLog}
 */
function createInnermostRouter(
    array $globalMiddleware,
    array $routeMiddleware = [],
    array $withoutMiddleware = [],
): array {
    $container = new Container(new PreferenceRegistry());
    $log = new InnermostOrderLog();
    $container->instance(InnermostOrderLog::class, $log);

    $collection = new RouteCollection();
    $collection->add(new RouteDefinition(
        method: 'GET',
        path: '/page',
        controller: InnermostTestController::class,
        action: 'index',
        middleware: $routeMiddleware,
        withoutMiddleware: $withoutMiddleware,
    ));

    return [
        'router' => new Router(new RouteMatcher($collection), $container, $globalMiddleware),
        'log' => $log,
    ];
}

function innermostRequest(): Request
{
    return new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/page']);
}

describe('#[RunsInnermost]', function (): void {
    it('targets classes only', function (): void {
        $flags = new ReflectionClass(RunsInnermost::class)->getAttributes(Attribute::class)[0]
            ->newInstance()->flags;

        expect($flags)->toBe(Attribute::TARGET_CLASS);
    });

    it('runs a marked global middleware after route middleware, directly around the controller', function (): void {
        ['router' => $router, 'log' => $log] = createInnermostRouter(
            [InnermostOuterGlobalMiddleware::class, InnermostWrappingMiddleware::class],
            [InnermostRouteMiddleware::class],
        );

        $response = $router->handle(innermostRequest());

        expect($log->entries)->toBe(['outer-global', 'route', 'innermost', 'controller'])
            ->and($response->body())->toBe('replaced by innermost')
            ->and($response->headers()['X-Route'])->toBe('ran');
    });

    it('keeps the declaration order among marked global middleware', function (): void {
        ['router' => $router, 'log' => $log] = createInnermostRouter(
            [
                InnermostSecondWrappingMiddleware::class,
                InnermostOuterGlobalMiddleware::class,
                InnermostWrappingMiddleware::class,
            ],
            [InnermostRouteMiddleware::class],
        );

        $router->handle(innermostRequest());

        expect($log->entries)->toBe(['outer-global', 'route', 'innermost-second', 'innermost', 'controller']);
    });

    it('still lets a route exclude a marked global middleware with #[WithoutMiddleware]', function (): void {
        ['router' => $router, 'log' => $log] = createInnermostRouter(
            [InnermostWrappingMiddleware::class],
            [InnermostRouteMiddleware::class],
            [InnermostWrappingMiddleware::class],
        );

        $response = $router->handle(innermostRequest());

        expect($log->entries)->toBe(['route', 'controller'])
            ->and($response->body())->toBe('controller body');
    });
});
