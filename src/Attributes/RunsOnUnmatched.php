<?php

declare(strict_types=1);

namespace Marko\Routing\Attributes;

use Attribute;

/**
 * Opts a global middleware in to requests that match no route: 404s, 405s
 * and automatic OPTIONS responses (which includes CORS preflights for paths
 * without an explicit OPTIONS route).
 *
 *     #[RunsOnUnmatched]
 *     class CorsMiddleware implements MiddlewareInterface {}
 *
 * Global middleware without it runs only for matched routes, so session
 * start, CSRF checks and authentication never decide the status of a request
 * the router answers with 404 or 405. The attribute is read from the class
 * listed under `globalMiddleware`, not from a Preference that replaces it.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class RunsOnUnmatched {}
