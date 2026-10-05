<?php

declare(strict_types=1);

use Marko\Core\Exceptions\HttpExceptionInterface;
use Marko\Core\Exceptions\MarkoException;
use Marko\Routing\Exceptions\HttpException;
use Marko\Routing\Http\HttpStatus;

describe('HttpException', function (): void {
    it('creates an HttpException with status, message and headers', function (): void {
        $exception = new HttpException(
            statusCode: 409,
            message: 'Slug already taken.',
            headers: ['X-Reason' => 'duplicate'],
        );

        expect($exception)
            ->toBeInstanceOf(HttpExceptionInterface::class)
            ->toBeInstanceOf(MarkoException::class)
            ->and($exception->getStatusCode())->toBe(409)
            ->and($exception->getMessage())->toBe('Slug already taken.')
            ->and($exception->getHeaders())->toBe(['X-Reason' => 'duplicate'])
            ->and($exception->getResponseData())->toBe(['message' => 'Slug already taken.']);
    });

    it('merges extra data into the response data', function (): void {
        $exception = new HttpException(
            statusCode: 409,
            message: 'Conflict.',
            data: ['code' => 'slug_taken'],
        );

        expect($exception->getResponseData())->toBe([
            'message' => 'Conflict.',
            'code' => 'slug_taken',
        ]);
    });

    it('defaults the message to the status reason phrase', function (): void {
        expect((new HttpException(404))->getMessage())->toBe('Not Found')
            ->and((new HttpException(503))->getMessage())->toBe('Service Unavailable')
            ->and((new HttpException(499))->getMessage())->toBe('Client Error')
            ->and((new HttpException(599))->getMessage())->toBe('Server Error');
    });

    it('rejects status codes outside the 4xx and 5xx range', function (): void {
        expect(fn () => new HttpException(302))
            ->toThrow(
                InvalidArgumentException::class,
                'HttpException status code must be between 400 and 599, got 302',
            );
    });

    it('creates common statuses through named constructors', function (): void {
        expect(HttpException::badRequest()->getStatusCode())->toBe(400)
            ->and(HttpException::unauthorized()->getStatusCode())->toBe(401)
            ->and(HttpException::forbidden()->getStatusCode())->toBe(403)
            ->and(HttpException::notFound()->getStatusCode())->toBe(404)
            ->and(HttpException::notFound()->getMessage())->toBe('Not Found')
            ->and(HttpException::notFound('Post not found.')->getMessage())->toBe('Post not found.')
            ->and(HttpException::conflict()->getStatusCode())->toBe(409)
            ->and(HttpException::tooManyRequests()->getStatusCode())->toBe(429);
    });

    it('sets the Allow header for methodNotAllowed', function (): void {
        $exception = HttpException::methodNotAllowed(['get', 'HEAD', 'OPTIONS', 'GET']);

        expect($exception->getStatusCode())->toBe(405)
            ->and($exception->getHeaders())->toBe(['Allow' => 'GET, HEAD, OPTIONS']);
    });

    it('sets the Retry-After header for tooManyRequests when given', function (): void {
        expect(HttpException::tooManyRequests(retryAfter: 60)->getHeaders())->toBe(['Retry-After' => '60'])
            ->and(HttpException::tooManyRequests()->getHeaders())->toBeEmpty();
    });
});

describe('HttpStatus', function (): void {
    it('returns the reason phrase for a status code', function (): void {
        expect(HttpStatus::reasonPhrase(419))->toBe('Page Expired')
            ->and(HttpStatus::reasonPhrase(422))->toBe('Unprocessable Content')
            ->and(HttpStatus::reasonPhrase(500))->toBe('Internal Server Error');
    });
});
