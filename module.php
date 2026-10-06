<?php

declare(strict_types=1);

use Marko\Routing\RouteCacheContributor;
use Marko\Routing\UrlGenerator;
use Marko\Routing\UrlGeneratorInterface;

return [
    'singletons' => [
        UrlGeneratorInterface::class => UrlGenerator::class,
    ],
    'discovery' => [
        RouteCacheContributor::class,
    ],
];
