<?php

declare(strict_types=1);

namespace Marko\Routing;

interface RouteMatcherInterface
{
    /**
     * Find the route that handles a request, or null when none does.
     *
     * A HEAD request with no HEAD route matches the GET route for the same path.
     */
    public function match(
        string $method,
        string $path,
    ): ?MatchedRoute;

    /**
     * Methods with a route matching the path, for an `Allow` header.
     *
     * Includes HEAD when GET is allowed and always OPTIONS; empty when no
     * route of any method matches the path.
     *
     * @return array<int, string>
     */
    public function allowedMethods(
        string $path,
    ): array;
}
