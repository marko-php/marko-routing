<?php

declare(strict_types=1);

namespace Marko\Routing\Attributes;

use Attribute;
use Marko\Routing\Exceptions\MalformedJsonException;
use Marko\Routing\Http\Request;

/**
 * Bind a controller parameter from the request body: the JSON body for JSON
 * requests, form data otherwise. The query string is never consulted.
 *
 *     public function store(#[FromBody] string $title): Response
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
readonly class FromBody extends InputSource
{
    /**
     * @throws MalformedJsonException
     */
    public function read(
        Request $request,
        string $key,
    ): mixed {
        return $request->isJson() ? $request->json($key) : $request->post($key);
    }
}
