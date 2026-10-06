<?php

declare(strict_types=1);

namespace Marko\Routing\Exceptions;

use Marko\Core\Exceptions\HttpExceptionInterface;
use Marko\Core\Exceptions\MarkoException;

/**
 * SafePath::join() refused a path. Rendered as 404 Not Found; the rejected
 * path is in the message and context for logs, never in the response.
 */
class UnsafePathException extends MarkoException implements HttpExceptionInterface
{
    public static function escapesBase(
        string $base,
        string $relative,
    ): self {
        return new self(
            message: "Path '$relative' escapes the base directory '$base'",
            context: 'While joining a request-supplied path with SafePath::join()',
            suggestion: 'Request paths must stay inside the base directory; this is usually a traversal attempt',
        );
    }

    public static function absolutePath(
        string $base,
        string $relative,
    ): self {
        return new self(
            message: "Path '$relative' is absolute; SafePath::join() takes a path relative to '$base'",
            context: 'While joining a request-supplied path with SafePath::join()',
            suggestion: 'Pass a relative path, or strip the leading slash if the value is known to be relative',
        );
    }

    public static function nulByte(
        string $base,
    ): self {
        return new self(
            message: 'Path contains a NUL byte',
            context: "While joining a request-supplied path onto '$base' with SafePath::join()",
            suggestion: 'NUL bytes truncate paths in some filesystem calls; reject the request',
        );
    }

    public static function emptyBase(): self
    {
        return new self(
            message: 'SafePath::join() needs a non-empty base directory',
            context: 'While joining a request-supplied path with SafePath::join()',
            suggestion: 'Pass the directory the path must stay inside, e.g. storage_path or an absolute path from config',
        );
    }

    public function getStatusCode(): int
    {
        return 404;
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
        return ['message' => 'Not Found'];
    }
}
