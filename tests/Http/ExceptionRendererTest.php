<?php

declare(strict_types=1);

use Marko\Core\Exceptions\HttpExceptionInterface;
use Marko\Routing\Exceptions\HttpException;
use Marko\Routing\Http\ExceptionRenderer;
use Marko\Routing\Http\Request;

function exceptionRendererJsonRequest(
    string $accept = 'application/json',
): Request {
    return new Request(server: ['HTTP_ACCEPT' => $accept]);
}

describe('ExceptionRenderer', function (): void {
    it('renders JSON when Accept contains application/json', function (): void {
        $response = (new ExceptionRenderer())->render(
            HttpException::notFound('Post not found.'),
            exceptionRendererJsonRequest('text/html;q=0.9, application/json'),
        );

        expect($response->statusCode())->toBe(404)
            ->and($response->headers()['Content-Type'])->toBe('application/json')
            ->and(json_decode($response->body(), true))->toBe(['message' => 'Post not found.']);
    });

    it('renders JSON when Accept contains a +json media type', function (): void {
        $response = (new ExceptionRenderer())->render(
            HttpException::forbidden(),
            exceptionRendererJsonRequest('application/problem+json'),
        );

        expect($response->headers()['Content-Type'])->toBe('application/json')
            ->and(json_decode($response->body(), true))->toBe(['message' => 'Forbidden']);
    });

    it('renders JSON when Content-Type is JSON and Accept is absent', function (): void {
        $response = (new ExceptionRenderer())->render(
            HttpException::badRequest(),
            new Request(server: ['CONTENT_TYPE' => 'application/json; charset=utf-8']),
        );

        expect($response->headers()['Content-Type'])->toBe('application/json');
    });

    it('renders a minimal escaped HTML page otherwise', function (): void {
        $response = (new ExceptionRenderer())->render(
            HttpException::notFound('<script>alert(1)</script>'),
            new Request(server: ['HTTP_ACCEPT' => 'text/html', 'CONTENT_TYPE' => 'application/json']),
        );

        expect($response->statusCode())->toBe(404)
            ->and($response->headers()['Content-Type'])->toBe('text/html; charset=utf-8')
            ->and($response->body())
            ->toContain('<title>404 Not Found</title>')
            ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
            ->not->toContain('<script>');
    });

    it('applies the exception status and headers', function (): void {
        $response = (new ExceptionRenderer())->render(
            HttpException::methodNotAllowed(['GET', 'HEAD']),
            exceptionRendererJsonRequest(),
        );

        expect($response->statusCode())->toBe(405)
            ->and($response->headers())->toBe([
                'Content-Type' => 'application/json',
                'Allow' => 'GET, HEAD',
            ]);
    });

    it('falls back to the reason phrase when response data has no message', function (): void {
        $exception = new class ('secret internals') extends RuntimeException implements HttpExceptionInterface
        {
            public function getStatusCode(): int
            {
                return 410;
            }

            public function getHeaders(): array
            {
                return [];
            }

            public function getResponseData(): array
            {
                return ['code' => 'gone'];
            }
        };

        $response = (new ExceptionRenderer())->render($exception, exceptionRendererJsonRequest());

        expect(json_decode($response->body(), true))->toBe(['message' => 'Gone', 'code' => 'gone']);
    });

    it('never reads the exception message directly', function (): void {
        $exception = new class ('secret internals') extends RuntimeException implements HttpExceptionInterface
        {
            public function getStatusCode(): int
            {
                return 400;
            }

            public function getHeaders(): array
            {
                return [];
            }

            public function getResponseData(): array
            {
                return [];
            }
        };

        $renderer = new ExceptionRenderer();

        expect($renderer->render($exception, exceptionRendererJsonRequest())->body())->not->toContain('secret')
            ->and($renderer->render($exception, new Request())->body())->not->toContain('secret');
    });
});
