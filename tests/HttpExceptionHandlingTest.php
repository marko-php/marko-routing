<?php

declare(strict_types=1);

namespace Marko\Routing\Tests\HttpExceptionHandling;

use Marko\Core\Attributes\Preference;
use Marko\Core\Container\Container;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Core\Discovery\ClassFileParser;
use Marko\Core\Exceptions\HttpExceptionInterface;
use Marko\Routing\Attributes\FromQuery;
use Marko\Routing\Exceptions\HttpException;
use Marko\Routing\Http\ExceptionRenderer;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDefinition;
use Marko\Routing\Router;
use Marko\Routing\RoutingBootstrapper;
use RuntimeException;

class ThrowingController
{
    public function missing(): Response
    {
        throw HttpException::notFound('Post not found.');
    }

    public function crash(): Response
    {
        throw new RuntimeException('Database exploded');
    }

    public function needsCount(
        #[FromQuery]
        int $count,
    ): Response {
        return new Response("count=$count");
    }
}

class AddsHeaderMiddleware implements MiddlewareInterface
{
    public function handle(
        Request $request,
        callable $next,
    ): Response {
        return $next($request)->withHeader('X-Outer', 'decorated');
    }
}

class ForbidsMiddleware implements MiddlewareInterface
{
    public function handle(
        Request $request,
        callable $next,
    ): Response {
        throw HttpException::forbidden();
    }
}

#[Preference(replaces: ExceptionRenderer::class)]
class BrandedExceptionRenderer extends ExceptionRenderer
{
    public function render(
        HttpExceptionInterface $exception,
        Request $request,
    ): Response {
        return new Response('Branded ' . $exception->getStatusCode(), $exception->getStatusCode());
    }
}

/**
 * @param array<string> $globalMiddleware
 * @param array<string> $routeMiddleware
 */
function bootRouter(
    string $action,
    array $globalMiddleware = [],
    array $routeMiddleware = [],
    ?PreferenceRegistry $preferenceRegistry = null,
): Router {
    $preferenceRegistry ??= new PreferenceRegistry();
    $container = new Container($preferenceRegistry);
    $bootstrapper = new RoutingBootstrapper(
        modules: [],
        container: $container,
        preferenceRegistry: $preferenceRegistry,
        classFileParser: new ClassFileParser(),
    );
    $router = $bootstrapper->boot($globalMiddleware);

    $container->get(RouteCollection::class)->add(new RouteDefinition(
        method: 'GET',
        path: '/test',
        controller: ThrowingController::class,
        action: $action,
        middleware: $routeMiddleware,
    ));

    return $router;
}

/**
 * @param array<string, string> $server
 */
function httpRequest(
    array $server = [],
): Request {
    return new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test', ...$server]);
}

describe('HTTP exceptions through Router::handle()', function (): void {
    it('returns a 404 response when a controller throws HttpException::notFound', function (): void {
        $response = bootRouter('missing')->handle(httpRequest());

        expect($response->statusCode())->toBe(404)
            ->and($response->headers()['Content-Type'])->toBe('text/html; charset=utf-8')
            ->and($response->body())->toContain('Post not found.');
    });

    it('returns a JSON body when the request accepts JSON', function (): void {
        $response = bootRouter('missing')->handle(httpRequest(['HTTP_ACCEPT' => 'application/json']));

        expect($response->statusCode())->toBe(404)
            ->and($response->headers()['Content-Type'])->toBe('application/json')
            ->and(json_decode($response->body(), true))->toBe(['message' => 'Post not found.']);
    });

    it('lets outer middleware decorate a response rendered from an inner exception', function (): void {
        $fromController = bootRouter('missing', [AddsHeaderMiddleware::class])->handle(httpRequest());
        $fromMiddleware = bootRouter(
            'missing',
            [AddsHeaderMiddleware::class],
            [ForbidsMiddleware::class],
        )->handle(httpRequest());

        expect($fromController->statusCode())->toBe(404)
            ->and($fromController->headers()['X-Outer'])->toBe('decorated')
            ->and($fromMiddleware->statusCode())->toBe(403)
            ->and($fromMiddleware->headers()['X-Outer'])->toBe('decorated');
    });

    it('propagates non-HTTP exceptions unchanged', function (): void {
        $router = bootRouter('crash', [AddsHeaderMiddleware::class]);

        expect(fn () => $router->handle(httpRequest()))
            ->toThrow(RuntimeException::class, 'Database exploded');
    });

    it('still returns 400 for InvalidRouteParameterException', function (): void {
        $response = bootRouter('needsCount')->handle(httpRequest(['HTTP_ACCEPT' => 'application/json']));

        expect($response->statusCode())->toBe(400)
            ->and(json_decode($response->body(), true))
            ->toBe(['message' => "Missing required parameter 'count' of type 'int'"]);
    });

    it('resolves the renderer from the container so a Preference can replace it', function (): void {
        $preferenceRegistry = new PreferenceRegistry();
        $preferenceRegistry->register(ExceptionRenderer::class, BrandedExceptionRenderer::class);

        $response = bootRouter('missing', preferenceRegistry: $preferenceRegistry)->handle(httpRequest());

        expect($response->statusCode())->toBe(404)
            ->and($response->body())->toBe('Branded 404');
    });
});
