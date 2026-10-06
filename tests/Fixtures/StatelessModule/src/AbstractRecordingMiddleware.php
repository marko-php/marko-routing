<?php

declare(strict_types=1);

namespace Test\StatelessModule;

use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;

/**
 * Records its short class name in $ran before calling the next handler, and
 * appends it to the X-Ran response header afterwards.
 */
abstract class AbstractRecordingMiddleware implements MiddlewareInterface
{
    /** @var array<int, string> */
    public static array $ran = [];

    public function handle(
        Request $request,
        callable $next,
    ): Response {
        $parts = explode('\\', static::class);
        $shortName = end($parts);
        self::$ran[] = $shortName;

        $response = $next($request);
        $ran = trim(($response->headers()['X-Ran'] ?? '') . ',' . $shortName, ',');

        return $response->withHeader('X-Ran', $ran);
    }
}
