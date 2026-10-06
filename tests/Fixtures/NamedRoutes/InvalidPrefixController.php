<?php

declare(strict_types=1);

namespace Test\NamedRoutes;

use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\RoutePrefix;

/**
 * @noinspection PhpUnused - Actions are discovered via route attributes
 */
#[RoutePrefix('admin')]
class InvalidPrefixController
{
    #[Get('/users')]
    public function users(): void {}
}
