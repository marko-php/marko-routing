<?php

declare(strict_types=1);

namespace Test\StatelessModule;

use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;

/**
 * @noinspection PhpUnused - Actions are invoked by the router
 */
class StatefulController
{
    #[Get('/stateful')]
    public function stateful(): Response
    {
        return new Response('stateful');
    }
}
