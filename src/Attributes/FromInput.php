<?php

declare(strict_types=1);

namespace Marko\Routing\Attributes;

use Attribute;
use Marko\Routing\Exceptions\MalformedJsonException;
use Marko\Routing\Http\Request;

/**
 * Bind a controller parameter from the request body or, when the body does not
 * carry it, the query string: the same lookup as Request::input().
 *
 *     public function search(#[FromInput] string $term): Response
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
readonly class FromInput extends InputSource
{
    /**
     * @throws MalformedJsonException
     */
    public function read(
        Request $request,
        string $key,
    ): mixed {
        return $request->input($key);
    }
}
