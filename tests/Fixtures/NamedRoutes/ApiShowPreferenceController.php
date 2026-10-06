<?php

declare(strict_types=1);

namespace Test\NamedRoutes;

use Marko\Routing\Attributes\Get;

/**
 * Preference for ApiShowController without its own #[RoutePrefix]: inherited
 * routes and the overridden route both keep the parent's prefix.
 *
 * @noinspection PhpUnused - Actions are discovered via route attributes
 */
class ApiShowPreferenceController extends ApiShowController
{
    #[Get('/shows/{id:\d+}', name: 'shows.show')]
    public function show(): void {}
}
