<?php

declare(strict_types=1);

namespace Marko\Routing\Attributes;

abstract readonly class Route
{
    /**
     * @param array<int, class-string> $middleware
     * @param string|null $name Unique route name for URL generation, e.g. 'shows.show'
     */
    public function __construct(
        public string $path,
        public array $middleware = [],
        public ?string $name = null,
    ) {}

    abstract public function getMethod(): string;
}
