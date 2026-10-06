<?php

declare(strict_types=1);

namespace Marko\Routing\Attributes;

use Attribute;

/**
 * Moves a global middleware to the innermost position of a matched route's
 * stack: after every route middleware, directly around the controller.
 *
 *     #[RunsInnermost]
 *     class LayoutMiddleware implements MiddlewareInterface {}
 *
 * Use it for global middleware that turns the controller's result into the
 * final response (layout rendering, for example). Route middleware then
 * wraps that response, so headers and cookies it adds on the way out are
 * kept, and a route middleware that denies the request (an auth redirect, a
 * 403) answers before the marked middleware runs at all. Marked middleware
 * keep their declaration order among themselves, and `#[WithoutMiddleware]`
 * still excludes them. The attribute is read from the class listed under
 * `globalMiddleware`, not from a Preference that replaces it.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class RunsInnermost {}
