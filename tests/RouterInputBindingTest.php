<?php

declare(strict_types=1);

namespace Marko\Routing\Tests\RouterInputBinding;

use Marko\Core\Container\Container;
use Marko\Routing\Attributes\FromBody;
use Marko\Routing\Attributes\FromInput;
use Marko\Routing\Attributes\FromQuery;
use Marko\Routing\Exceptions\RouteException;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDefinition;
use Marko\Routing\RouteMatcher;
use Marko\Routing\Router;
use stdClass;

/**
 * Dispatch a request to `$controller->action()` on `$path` and return the response.
 *
 * @param array<string, string> $server
 * @param array<string, mixed> $query
 * @param array<string, mixed> $post
 */
function dispatchInput(
    object $controller,
    string $uri = '/items',
    string $method = 'GET',
    string $path = '/items',
    array $query = [],
    array $post = [],
    string $body = '',
    array $server = [],
    Container $container = new Container(),
): Response {
    $routes = new RouteCollection();
    $routes->add(new RouteDefinition(
        method: $method,
        path: $path,
        controller: 'App\\Controllers\\InputController',
        action: 'action',
    ));

    $container->instance('App\\Controllers\\InputController', $controller);

    $router = new Router(matcher: new RouteMatcher($routes), container: $container);

    return $router->handle(new Request(
        server: ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri, 'HTTP_ACCEPT' => 'application/json', ...$server],
        query: $query,
        post: $post,
        body: $body,
    ));
}

class ExportController
{
    /** @var array<string, mixed> */
    public array $received = [];

    public function action(
        bool $includeDeleted = false,
        ?int $ownerId = null,
    ): Response {
        $this->received = ['includeDeleted' => $includeDeleted, 'ownerId' => $ownerId];

        return new Response('OK');
    }
}

class QueryController
{
    /** @var array<string, mixed> */
    public array $received = [];

    public function action(
        #[FromQuery]
        int $page = 1,
        #[FromQuery('per_page')]
        ?int $perPage = null,
        #[FromQuery]
        bool $includeDeleted = false,
        #[FromQuery]
        float $ratio = 1.0,
        #[FromQuery]
        ?string $term = null,
        #[FromQuery]
        array $tags = [],
    ): Response {
        $this->received = [
            'page' => $page,
            'perPage' => $perPage,
            'includeDeleted' => $includeDeleted,
            'ratio' => $ratio,
            'term' => $term,
            'tags' => $tags,
        ];

        return new Response('OK');
    }
}

class BodyController
{
    public ?string $title = null;

    public function action(
        #[FromBody]
        string $title,
    ): Response {
        $this->title = $title;

        return new Response('OK');
    }
}

class InputController
{
    public ?string $title = null;

    public function action(
        #[FromInput]
        string $title,
    ): Response {
        $this->title = $title;

        return new Response('OK');
    }
}

class RequiredScalarController
{
    public function action(
        int $page,
    ): Response {
        return new Response("page=$page");
    }
}

class DoubleSourceController
{
    public function action(
        #[FromQuery]
        #[FromBody]
        string $title,
    ): Response {
        return new Response($title);
    }
}

class ObjectInputController
{
    public function action(
        #[FromQuery]
        stdClass $filter,
    ): Response {
        return new Response('OK');
    }
}

class RouteIntController
{
    public ?int $id = null;

    public function action(
        int $id,
    ): Response {
        $this->id = $id;

        return new Response('OK');
    }
}

class DependencyController
{
    public ?stdClass $dependency = null;

    public function action(
        stdClass $dependency,
    ): Response {
        $this->dependency = $dependency;

        return new Response('OK');
    }
}

describe('without an input attribute', function (): void {
    it('keeps the default of an optional param even when the query string names it', function (): void {
        $controller = new ExportController();

        dispatchInput(
            $controller,
            uri: '/items?includeDeleted=1&ownerId=7',
            query: ['includeDeleted' => '1', 'ownerId' => '7'],
        );

        expect($controller->received)->toBe(['includeDeleted' => false, 'ownerId' => null]);
    });

    it('keeps the default of an optional param even when the body names it', function (): void {
        $controller = new ExportController();

        dispatchInput($controller, method: 'POST', post: ['includeDeleted' => '1', 'ownerId' => '7']);

        expect($controller->received)->toBe(['includeDeleted' => false, 'ownerId' => null]);
    });

    it('fails loudly when a required scalar param is neither a route param nor attributed', function (): void {
        expect(fn (): Response => dispatchInput(new RequiredScalarController(), query: ['page' => '3']))
            ->toThrow(RouteException::class, "Controller parameter 'page' cannot be bound");
    });

    it('still binds route params', function (): void {
        $controller = new RouteIntController();

        dispatchInput($controller, uri: '/items/42', path: '/items/{id}');

        expect($controller->id)->toBe(42);
    });

    it('resolves a class typed param from the container', function (): void {
        $controller = new DependencyController();
        $dependency = new stdClass();
        $container = new Container();
        $container->instance(stdClass::class, $dependency);

        dispatchInput($controller, container: $container);

        expect($controller->dependency)->toBe($dependency);
    });
});

describe('#[FromQuery]', function (): void {
    it('binds query string values and keeps defaults for absent ones', function (): void {
        $controller = new QueryController();

        dispatchInput($controller, query: ['page' => '3', 'per_page' => '25', 'term' => 'wire', 'tags' => ['a', 'b']]);

        expect($controller->received)->toBe([
            'page' => 3,
            'perPage' => 25,
            'includeDeleted' => false,
            'ratio' => 1.0,
            'term' => 'wire',
            'tags' => ['a', 'b'],
        ]);
    });

    it('ignores body values', function (): void {
        $controller = new QueryController();

        dispatchInput($controller, method: 'POST', post: ['page' => '9']);

        expect($controller->received['page'])->toBe(1);
    });

    it('casts bool values strictly', function (string $raw, bool $expected): void {
        $controller = new QueryController();

        dispatchInput($controller, query: ['includeDeleted' => $raw]);

        expect($controller->received['includeDeleted'])->toBe($expected);
    })->with([
        ['1', true],
        ['true', true],
        ['on', true],
        ['yes', true],
        ['0', false],
        ['false', false],
        ['no', false],
        ['off', false],
    ]);

    it('casts float values strictly', function (): void {
        $controller = new QueryController();

        dispatchInput($controller, query: ['ratio' => '0.25']);

        expect($controller->received['ratio'])->toBe(0.25);
    });

    it(
        'returns 400 for a value that does not fit the declared type',
        function (string $key, mixed $raw, string $type): void {
            $response = dispatchInput(new QueryController(), query: [$key => $raw]);

            expect($response->statusCode())->toBe(400)
                ->and(json_decode($response->body(), true))
                ->toBe(['message' => "Invalid value for parameter '$key': expected $type"]);
        },
    )->with([
        'non-numeric int' => ['page', 'abc', 'int'],
        'decimal int' => ['page', '1.5', 'int'],
        'array for int' => ['page', ['1'], 'int'],
        'non-boolean bool' => ['includeDeleted', 'maybe', 'bool'],
        'array for bool' => ['includeDeleted', ['1'], 'bool'],
        'non-numeric float' => ['ratio', 'half', 'float'],
        'array for string' => ['term', ['x'], 'string'],
        'string for array' => ['tags', 'a', 'array'],
    ]);
});

describe('#[FromBody]', function (): void {
    it('binds a form field', function (): void {
        $controller = new BodyController();

        dispatchInput($controller, method: 'POST', post: ['title' => 'The Wire']);

        expect($controller->title)->toBe('The Wire');
    });

    it('binds a json field', function (): void {
        $controller = new BodyController();

        dispatchInput(
            $controller,
            method: 'POST',
            body: '{"title":"The Wire"}',
            server: ['CONTENT_TYPE' => 'application/json'],
        );

        expect($controller->title)->toBe('The Wire');
    });

    it('does not fall back to the query string', function (): void {
        $response = dispatchInput(
            new BodyController(),
            method: 'POST',
            uri: '/items?title=query',
            query: ['title' => 'query'],
        );

        expect($response->statusCode())->toBe(400)
            ->and(json_decode($response->body(), true))
            ->toBe(['message' => "Missing required parameter 'title' of type 'string'"]);
    });

    it('returns 400 for a json object sent to a string param', function (): void {
        $response = dispatchInput(
            new BodyController(),
            method: 'POST',
            body: '{"title":{"nested":true}}',
            server: ['CONTENT_TYPE' => 'application/json'],
        );

        expect($response->statusCode())->toBe(400)
            ->and(json_decode($response->body(), true))
            ->toBe(['message' => "Invalid value for parameter 'title': expected string"]);
    });
});

describe('#[FromInput]', function (): void {
    it('reads the body first', function (): void {
        $controller = new InputController();

        dispatchInput(
            $controller,
            method: 'POST',
            uri: '/items?title=query',
            query: ['title' => 'query'],
            post: ['title' => 'body'],
        );

        expect($controller->title)->toBe('body');
    });

    it('falls back to the query string', function (): void {
        $controller = new InputController();

        dispatchInput($controller, uri: '/items?title=query', query: ['title' => 'query']);

        expect($controller->title)->toBe('query');
    });
});

describe('misconfigured parameters', function (): void {
    it('rejects more than one input attribute on a param', function (): void {
        expect(fn (): Response => dispatchInput(new DoubleSourceController(), query: ['title' => 'x']))
            ->toThrow(RouteException::class, "Controller parameter 'title' has more than one input attribute");
    });

    it('rejects an input attribute on a type that cannot be built from input', function (): void {
        expect(fn (): Response => dispatchInput(new ObjectInputController(), query: ['filter' => 'x']))
            ->toThrow(RouteException::class, "Controller parameter 'filter' has type 'stdClass'");
    });
});

describe('route params', function (): void {
    it('returns 400 for a route value that does not fit an int param', function (): void {
        $response = dispatchInput(new RouteIntController(), uri: '/items/abc', path: '/items/{id}');

        expect($response->statusCode())->toBe(400)
            ->and(json_decode($response->body(), true))
            ->toBe(['message' => "Invalid value for parameter 'id': expected int"]);
    });
});
