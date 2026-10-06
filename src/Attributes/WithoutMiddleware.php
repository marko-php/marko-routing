<?php

declare(strict_types=1);

namespace Marko\Routing\Attributes;

use Attribute;

/**
 * Removes middleware from a route's stack: global middleware declared by
 * modules as well as route middleware. On a class it applies to every route
 * the class declares; class and method exclusions are combined.
 *
 *     #[WithoutMiddleware(SessionMiddleware::class)]
 *
 * Excluding a middleware that is not in the route's stack fails at boot.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
readonly class WithoutMiddleware
{
    /**
     * @var array<class-string>
     */
    public array $middleware;

    /**
     * @param class-string|array<class-string> $middleware
     */
    public function __construct(
        string|array $middleware,
    ) {
        $this->middleware = is_array($middleware) ? $middleware : [$middleware];
    }
}
