<?php

declare(strict_types=1);

use Marko\Config\Env;

return [
    // Base URL for absolute URLs from UrlGeneratorInterface::route(..., absolute: true),
    // e.g. https://example.com. Never derived from the request's Host header.
    'url' => Env::string('APP_URL', ''),
];
