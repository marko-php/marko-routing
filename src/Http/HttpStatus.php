<?php

declare(strict_types=1);

namespace Marko\Routing\Http;

/**
 * Reason phrases for HTTP status codes.
 */
class HttpStatus
{
    /**
     * @var array<int, string>
     */
    public const array REASON_PHRASES = [
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Content Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => "I'm a teapot",
        419 => 'Page Expired',
        421 => 'Misdirected Request',
        422 => 'Unprocessable Content',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Too Early',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
    ];

    /**
     * The reason phrase for a status code; unlisted codes get their class name
     * ("Client Error" for 4xx, "Server Error" for 5xx).
     */
    public static function reasonPhrase(
        int $statusCode,
    ): string {
        return self::REASON_PHRASES[$statusCode] ?? match (intdiv($statusCode, 100)) {
            1 => 'Informational',
            2 => 'Success',
            3 => 'Redirection',
            4 => 'Client Error',
            default => 'Server Error',
        };
    }
}
