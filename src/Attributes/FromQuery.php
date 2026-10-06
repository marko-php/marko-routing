<?php

declare(strict_types=1);

namespace Marko\Routing\Attributes;

use Attribute;
use Marko\Routing\Http\Request;

/**
 * Bind a controller parameter from the query string.
 *
 *     public function index(#[FromQuery] int $page = 1): Response
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
readonly class FromQuery extends InputSource
{
    public function read(
        Request $request,
        string $key,
    ): mixed {
        return $request->query($key);
    }
}
