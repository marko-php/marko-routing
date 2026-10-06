<?php

declare(strict_types=1);

return [
    // Base URL for absolute URLs from UrlGeneratorInterface::route(..., absolute: true),
    // e.g. https://example.com. Never derived from the request's Host header.
    'url' => $_ENV['APP_URL'] ?? '',
];
