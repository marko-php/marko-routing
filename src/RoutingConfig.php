<?php

declare(strict_types=1);

namespace Marko\Routing;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;

readonly class RoutingConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    /**
     * Base URL for absolute URLs; empty when none is configured.
     *
     * @throws ConfigNotFoundException
     */
    public function url(): string
    {
        return $this->config->getString('routing.url');
    }
}
