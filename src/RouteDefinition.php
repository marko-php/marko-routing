<?php

declare(strict_types=1);

namespace Marko\Routing;

readonly class RouteDefinition
{
    /** @var array<int, string> */
    public array $parameters;

    public string $regex;

    /** True when the path has no parameters and can be matched by exact string lookup. */
    public bool $isStatic;

    /** Number of path segments that contain no parameter; higher sorts first among dynamic routes. */
    public int $staticSegmentCount;

    /** Length of the path before its first parameter; the tie-breaker after static segment count. */
    public int $staticPrefixLength;

    /**
     * @param array<int, string> $middleware
     */
    public function __construct(
        public string $method,
        public string $path,
        public string $controller,
        public string $action,
        public array $middleware = [],
    ) {
        $this->parameters = $this->extractParameters($path);
        $this->regex = $this->buildRegex($path);
        $this->isStatic = $this->parameters === [];
        $this->staticSegmentCount = count(array_filter(
            explode('/', $path),
            fn (string $segment): bool => $segment !== '' && !str_contains($segment, '{'),
        ));
        $firstParameter = strpos($path, '{');
        $this->staticPrefixLength = $firstParameter === false ? strlen($path) : $firstParameter;
    }

    /**
     * @return array<int, string>
     */
    private function extractParameters(
        string $path,
    ): array {
        preg_match_all('/\{([^}]+)\}/', $path, $matches);

        return $matches[1];
    }

    private function buildRegex(
        string $path,
    ): string {
        $parts = preg_split('/(\{[^}]+\})/', $path, -1, PREG_SPLIT_DELIM_CAPTURE);
        $pattern = '';

        foreach ($parts as $part) {
            if (preg_match('/^\{([^}]+)\}$/', $part, $matches)) {
                $pattern .= '(?P<' . $matches[1] . '>[^/]+)';
            } else {
                $pattern .= preg_quote($part, '#');
            }
        }

        return '#^' . $pattern . '$#';
    }
}
