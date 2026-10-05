<?php

declare(strict_types=1);

namespace Marko\Routing\Attributes;

use Attribute;

/**
 * An explicit HEAD route. Without one, HEAD requests are served by the GET
 * route for the same path with the response body removed.
 */
#[Attribute(Attribute::TARGET_METHOD)]
readonly class Head extends Route
{
    public function getMethod(): string
    {
        return 'HEAD';
    }
}
