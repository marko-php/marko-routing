<?php

declare(strict_types=1);

namespace Marko\Routing\Exceptions;

use Error;
use Marko\Core\Exceptions\MarkoException;
use Marko\Routing\RouteDefinition;

class RouteException extends MarkoException
{
    public static function classNotFoundDuringDiscovery(
        string $filePath,
        string $missingClass,
        Error $previous,
    ): self {
        $package = self::inferPackageName($missingClass);
        $suggestion = $package !== null
            ? "Run: composer require $package"
            : "Ensure the class '$missingClass' is available via Composer autoloading";

        return new self(
            message: "Failed to load controller file: class or interface '$missingClass' not found",
            context: "While discovering routes in '$filePath'. This usually means a required package is missing.",
            suggestion: $suggestion,
            previous: $previous,
        );
    }

    public static function attributeClassNotFound(
        string $controller,
        string $missingClass,
        Error $previous,
    ): self {
        $package = self::inferPackageName($missingClass);
        $suggestion = $package !== null
            ? "Run: composer require $package"
            : "Ensure the class '$missingClass' is available via Composer autoloading";

        return new self(
            message: "Attribute class '$missingClass' not found on controller '$controller'",
            context: 'While instantiating route attributes. This usually means a required package is missing.',
            suggestion: $suggestion,
            previous: $previous,
        );
    }

    public static function ambiguousOverride(
        string $parentClass,
        string $childClass,
        string $method,
    ): self {
        return new self(
            message: "Method '$method' overrides parent but has no #[Route] attribute - unclear if route should be inherited or replaced.",
            context: "Parent: $parentClass::$method(), Child: $childClass::$method()",
            suggestion: "Add #[Route] attribute to explicitly define the route, or use #[InheritRoute] to keep the parent's route configuration.",
        );
    }

    public static function invalidParameter(
        string $path,
        string $parameter,
        string $reason,
    ): self {
        return new self(
            message: "Invalid route parameter '$parameter' in route definition.",
            context: "Path: $path, Error: $reason",
            suggestion: 'Route parameters must use the format {name} or {name:pattern}. Example: {id} or {slug:[a-z-]+}',
        );
    }

    public static function invalidConstraint(
        string $path,
        string $parameter,
        string $constraint,
        string $reason,
    ): self {
        return new self(
            message: "Invalid constraint for route parameter '$parameter': '$constraint'",
            context: "Path: $path, Error: $reason",
            suggestion: 'Write the constraint as a PCRE pattern without delimiters, e.g. {id:\d+} or {slug:[a-z0-9-]+}',
        );
    }

    public static function capturingGroupInConstraint(
        string $path,
        string $parameter,
        string $constraint,
    ): self {
        return new self(
            message: "Constraint for route parameter '$parameter' contains a capturing group: '$constraint'",
            context: "Path: $path",
            suggestion: 'Use a non-capturing group (?:...) instead of (...), e.g. {format:(?:json|xml)}',
        );
    }

    public static function catchAllNotFinal(
        string $path,
        string $parameter,
    ): self {
        return new self(
            message: "Catch-all parameter '$parameter' must be the last segment of the route path",
            context: "Path: $path",
            suggestion: 'Move {' . $parameter . '*} to the end of the path as its own segment, e.g. /files/{' . $parameter . '*}',
        );
    }

    public static function invalidPrefix(
        string $controller,
        string $prefix,
    ): self {
        return new self(
            message: "Route prefix '$prefix' must start with '/'",
            context: "#[RoutePrefix] on $controller",
            suggestion: "Write the prefix as an absolute path, e.g. #[RoutePrefix('/" . ltrim($prefix, '/') . "')]",
        );
    }

    /**
     * @param array<int, string> $stack
     */
    public static function excludedMiddlewareNotInStack(
        RouteDefinition $route,
        string $middleware,
        array $stack,
    ): self {
        $stackList = $stack === [] ? 'none' : implode(', ', $stack);

        return new self(
            message: "Route excludes middleware '$middleware', which is not in its middleware stack",
            context: "Route: $route->method $route->path ($route->controller::$route->action()). Global and route middleware: $stackList",
            suggestion: 'Check the class name in #[WithoutMiddleware], or remove it: the middleware is not registered as global middleware by any installed module and is not on the route.',
        );
    }

    public static function controllerNotFound(
        string $controller,
        string $path,
    ): self {
        return new self(
            message: "Controller class not found: $controller",
            context: "Route path: $path",
            suggestion: 'Verify the class exists and is properly autoloaded. Check the namespace matches the file location.',
        );
    }

    public static function methodNotFound(
        string $controller,
        string $method,
        string $path,
    ): self {
        return new self(
            message: "Method not found: $method",
            context: "Controller: $controller, Route path: $path",
            suggestion: 'Verify the method exists and is public. Route handler methods must be public.',
        );
    }
}
