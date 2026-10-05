<?php

declare(strict_types=1);

namespace Marko\Routing\Attributes;

use Attribute;

/**
 * An explicit OPTIONS route. Without one, an OPTIONS request to a path that
 * other routes match receives an automatic 204 with an `Allow` header.
 */
#[Attribute(Attribute::TARGET_METHOD)]
readonly class Options extends Route
{
    public function getMethod(): string
    {
        return 'OPTIONS';
    }
}
