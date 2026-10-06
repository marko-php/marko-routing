<?php

declare(strict_types=1);

namespace Marko\Routing\Exceptions;

use Marko\Core\Exceptions\HttpExceptionInterface;
use Marko\Core\Exceptions\MarkoException;

/**
 * A required controller parameter could not be resolved from the request.
 * Rendered as 400 Bad Request; the message names only the parameter and type.
 */
class InvalidRouteParameterException extends MarkoException implements HttpExceptionInterface
{
    public static function missingRequired(
        string $paramName,
        string $expectedType,
        string $controller,
        string $action,
    ): self {
        return new self(
            message: "Missing required parameter '$paramName' of type '$expectedType'",
            context: "While dispatching $controller::$action()",
            suggestion: "Send a '$paramName' value from the source its #[FromQuery], #[FromBody] or #[FromInput] attribute names, or give the parameter a default value",
        );
    }

    public function getStatusCode(): int
    {
        return 400;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getResponseData(): array
    {
        return ['message' => $this->getMessage()];
    }
}
