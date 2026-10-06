<?php

declare(strict_types=1);

namespace Test\NamedRoutes;

use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\RoutePrefix;

/**
 * Preference for ApiShowController with its own #[RoutePrefix]: the prefix
 * applies to methods this class declares; inherited methods keep the parent's.
 *
 * @noinspection PhpUnused - Actions are discovered via route attributes
 */
#[RoutePrefix('/api/v2', namePrefix: 'api.v2.')]
class ApiShowV2Controller extends ApiShowController
{
    #[Get('/shows/{id}', name: 'shows.show')]
    public function show(): void {}
}
