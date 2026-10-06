<?php

declare(strict_types=1);

namespace Test\NamedRoutes;

use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\RoutePrefix;

/**
 * @noinspection PhpUnused - Actions are discovered via route attributes
 */
#[RoutePrefix('/api/v1/', namePrefix: 'api.v1.')]
class ApiShowController
{
    #[Get('/shows/{id}', name: 'shows.show')]
    public function show(): void {}

    #[Get('shows')]
    public function index(): void {}

    #[Get('/', name: 'root')]
    public function root(): void {}
}
