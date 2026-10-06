<?php

declare(strict_types=1);

namespace Marko\Routing\Attributes;

use Attribute;

/**
 * Prefixes the path (and optionally the name) of every route declared in a
 * controller class:
 *
 *     #[RoutePrefix('/api/v1', namePrefix: 'api.v1.')]
 *
 * The prefix applies to the methods its class declares. A subclass, such as a
 * #[Preference], without its own prefix keeps the parent's prefix; inherited
 * methods always keep the prefix of the class that declares them.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class RoutePrefix
{
    public function __construct(
        public string $prefix,
        public string $namePrefix = '',
    ) {}
}
