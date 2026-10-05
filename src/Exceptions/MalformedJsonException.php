<?php

declare(strict_types=1);

namespace Marko\Routing\Exceptions;

use Marko\Core\Exceptions\HttpExceptionInterface;
use Marko\Core\Exceptions\MarkoException;

/**
 * The request declared a JSON body that cannot be decoded. Rendered as
 * 400 Bad Request by the routing pipeline wherever it is thrown.
 */
class MalformedJsonException extends MarkoException implements HttpExceptionInterface
{
    public static function fromBody(
        int $bodyLength,
        string $decoderError,
    ): self {
        return new self(
            message: 'The request body is malformed JSON and cannot be decoded.',
            context: "The request declared a JSON Content-Type, but decoding the body ($bodyLength bytes) failed: $decoderError",
            suggestion: 'Send a valid JSON document as the request body, or send a different Content-Type if the body is not JSON.',
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
