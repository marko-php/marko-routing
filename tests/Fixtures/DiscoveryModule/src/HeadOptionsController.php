<?php

declare(strict_types=1);

namespace Test\DiscoveryModule;

use Marko\Routing\Attributes\Head;
use Marko\Routing\Attributes\Options;

class HeadOptionsController
{
    #[Head('/status')]
    public function head(): void {}

    #[Options('/status')]
    public function options(): void {}
}
