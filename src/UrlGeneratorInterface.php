<?php

declare(strict_types=1);

namespace Marko\Routing;

use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Routing\Exceptions\UrlGenerationException;

interface UrlGeneratorInterface
{
    /**
     * Build the URL for a named route.
     *
     * Values fill the path placeholders (URL-encoded; slashes are kept in a
     * catch-all). Parameters the path does not use become the query string.
     * With $absolute, the URL is prefixed with the configured base URL
     * (`routing.url`, from APP_URL).
     *
     * @param array<string, mixed> $parameters Path values must be string, int, float or bool
     * @throws UrlGenerationException|ConfigNotFoundException UrlGenerationException when the route is unknown, a parameter is missing or invalid, or no base URL is configured for an absolute URL
     */
    public function route(
        string $name,
        array $parameters = [],
        bool $absolute = false,
    ): string;
}
