<?php

declare(strict_types=1);

namespace Marko\Routing\Exceptions;

class UrlGenerationException extends RouteException
{
    /**
     * @param array<int, string> $similarNames
     */
    public static function unknownRoute(
        string $name,
        array $similarNames,
    ): self {
        $suggestion = $similarNames === []
            ? 'Check the name in #[Get(..., name: ...)] (or another route attribute), including any #[RoutePrefix] namePrefix. Run `marko route:list` to see every route name.'
            : "Did you mean '" . implode("', '", $similarNames) . "'? Run `marko route:list` to see every route name.";

        return new self(
            message: "No route named '$name'",
            context: 'While generating a URL',
            suggestion: $suggestion,
        );
    }

    public static function missingParameter(
        string $name,
        string $parameter,
        string $path,
    ): self {
        return new self(
            message: "Missing parameter '$parameter' for route '$name'",
            context: "Route path: $path",
            suggestion: "Pass a non-empty value: route('$name', ['$parameter' => ...])",
        );
    }

    public static function invalidParameterType(
        string $name,
        string $parameter,
        string $type,
    ): self {
        return new self(
            message: "Parameter '$parameter' for route '$name' must be a string, int, float or bool, $type given",
            context: 'While generating a URL',
            suggestion: 'Pass the identifier or slug, not the object or array itself',
        );
    }

    public static function constraintViolation(
        string $name,
        string $parameter,
        string $value,
        string $constraint,
    ): self {
        return new self(
            message: "Parameter '$parameter' for route '$name' does not match its constraint: '$value' is not $constraint",
            context: 'While generating a URL',
            suggestion: 'Pass a value that satisfies the route constraint; the generated URL would not match the route',
        );
    }

    public static function missingBaseUrl(
        string $name,
    ): self {
        return new self(
            message: "Cannot generate an absolute URL for route '$name': no base URL is configured",
            context: 'routing.url is empty (it defaults to the APP_URL environment variable)',
            suggestion: 'Set APP_URL in your environment (e.g. APP_URL=https://example.com), or set routing.url in config/routing.php',
        );
    }
}
