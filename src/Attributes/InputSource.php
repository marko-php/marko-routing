<?php

declare(strict_types=1);

namespace Marko\Routing\Attributes;

use Marko\Routing\Exceptions\MalformedJsonException;
use Marko\Routing\Http\Request;

/**
 * Base for the attributes that opt a controller parameter in to request input.
 *
 * Without #[FromQuery], #[FromBody] or #[FromInput], the router binds a
 * parameter only from the route path, the Request object, or the container,
 * never from data the client sends.
 */
abstract readonly class InputSource
{
    /**
     * @param string|null $name The input key to read; defaults to the parameter name
     */
    public function __construct(
        public ?string $name = null,
    ) {}

    /**
     * Read the raw value for `$key`, or null when the request does not carry it.
     *
     * @throws MalformedJsonException
     */
    abstract public function read(
        Request $request,
        string $key,
    ): mixed;
}
