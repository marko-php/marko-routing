<?php

declare(strict_types=1);

namespace Test\StatelessPreference;

use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\WithoutMiddleware;
use Test\StatelessModule\FirstGlobalMiddleware;
use Test\StatelessModule\RouteOnlyMiddleware;

/**
 * @noinspection PhpUnused - Actions are invoked by the router
 */
#[Middleware(RouteOnlyMiddleware::class)]
#[WithoutMiddleware(FirstGlobalMiddleware::class)]
class GuardedParentController
{
    #[Get('/guarded')]
    public function index(): string
    {
        return 'guarded';
    }

    #[Get('/guarded/open')]
    #[WithoutMiddleware(RouteOnlyMiddleware::class)]
    public function open(): string
    {
        return 'open';
    }
}
