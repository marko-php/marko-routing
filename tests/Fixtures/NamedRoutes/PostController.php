<?php

declare(strict_types=1);

namespace Test\NamedRoutes;

use Marko\Routing\Attributes\Get;

/**
 * @noinspection PhpUnused - Actions are discovered via route attributes
 */
class PostController
{
    #[Get('/posts', name: 'posts.index')]
    public function index(): void {}

    #[Get('/posts/{id}', name: 'posts.show')]
    public function show(): void {}

    #[Get('/posts/{id}/comments')]
    public function comments(): void {}
}
