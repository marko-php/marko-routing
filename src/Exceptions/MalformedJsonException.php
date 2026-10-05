<?php

declare(strict_types=1);

namespace Marko\Routing\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class MalformedJsonException extends MarkoException
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
}
