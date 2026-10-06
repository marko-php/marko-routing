<?php

declare(strict_types=1);

namespace Marko\Routing\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class RouteConflictException extends MarkoException
{
    public static function duplicateRoute(
        string $path,
        string $method,
        string $existingController,
        string $existingMethod,
        string $newController,
        string $newMethod,
    ): self {
        return new self(
            message: "Duplicate route defined for $method $path",
            context: "Existing: $existingController::$existingMethod(), New: $newController::$newMethod()",
            suggestion: 'Use #[Preference] to replace the existing controller, or change the route path to avoid conflicts.',
        );
    }

    public static function duplicateName(
        string $name,
        string $existingController,
        string $existingMethod,
        string $newController,
        string $newMethod,
    ): self {
        return new self(
            message: "Duplicate route name '$name'",
            context: "Existing: $existingController::$existingMethod(), New: $newController::$newMethod()",
            suggestion: "Route names must be unique. Rename one of the routes, or give the controller a #[RoutePrefix] with a namePrefix so its names don't collide.",
        );
    }
}
