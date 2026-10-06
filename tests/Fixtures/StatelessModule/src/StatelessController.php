<?php

declare(strict_types=1);

namespace Test\StatelessModule;

use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\WithoutMiddleware;
use Marko\Routing\Http\Response;

/**
 * @noinspection PhpUnused - Actions are invoked by the router
 */
#[WithoutMiddleware(FirstGlobalMiddleware::class)]
class StatelessController
{
    #[Get('/stateless')]
    public function stateless(): Response
    {
        return new Response('stateless');
    }

    #[Get('/stateless/bare')]
    #[Middleware(RouteOnlyMiddleware::class)]
    #[WithoutMiddleware([SecondGlobalMiddleware::class, RouteOnlyMiddleware::class])]
    public function bare(): Response
    {
        return new Response('bare');
    }
}
