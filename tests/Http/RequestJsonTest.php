<?php

declare(strict_types=1);

use Marko\Routing\Exceptions\MalformedJsonException;
use Marko\Routing\Http\Request;

function jsonRequest(
    string $body,
    string $contentType = 'application/json',
    array $query = [],
): Request {
    return new Request(
        server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/', 'CONTENT_TYPE' => $contentType],
        query: $query,
        body: $body,
    );
}

describe('Request JSON', function (): void {
    it('detects application/json content type as json', function (): void {
        expect(jsonRequest('{}')->isJson())->toBeTrue()
            ->and(jsonRequest('{}', 'application/json; charset=utf-8')->isJson())->toBeTrue()
            ->and(jsonRequest('{}', 'Application/JSON')->isJson())->toBeTrue();
    });

    it('detects structured +json content types such as application/vnd.api+json as json', function (): void {
        expect(jsonRequest('{}', 'application/vnd.api+json')->isJson())->toBeTrue()
            ->and(jsonRequest('{}', 'application/problem+json; charset=utf-8')->isJson())->toBeTrue();
    });

    it('does not treat form content types as json', function (): void {
        expect(jsonRequest('a=1', 'application/x-www-form-urlencoded')->isJson())->toBeFalse()
            ->and(jsonRequest('a=1', 'multipart/form-data; boundary=x')->isJson())->toBeFalse()
            ->and((new Request())->isJson())->toBeFalse();
    });

    it('reports wantsJson when the Accept header asks for json', function (): void {
        $wantsJson = new Request(server: ['HTTP_ACCEPT' => 'application/json']);
        $wantsApiJson = new Request(server: ['HTTP_ACCEPT' => 'text/html;q=0.9, application/vnd.api+json']);
        $wantsHtml = new Request(server: ['HTTP_ACCEPT' => 'text/html,application/xhtml+xml']);

        expect($wantsJson->wantsJson())->toBeTrue()
            ->and($wantsApiJson->wantsJson())->toBeTrue()
            ->and($wantsHtml->wantsJson())->toBeFalse()
            ->and((new Request())->wantsJson())->toBeFalse();
    });

    it('returns the decoded json body', function (): void {
        $request = jsonRequest('{"title":"Hello","count":3}');

        expect($request->json())->toBe(['title' => 'Hello', 'count' => 3])
            ->and($request->json('title'))->toBe('Hello')
            ->and($request->json('count'))->toBe(3);
    });

    it('reads nested json values with dot-notation keys', function (): void {
        $request = jsonRequest('{"user":{"email":"ada@example.com","roles":["admin","editor"]}}');

        expect($request->json('user.email'))->toBe('ada@example.com')
            ->and($request->json('user.roles.1'))->toBe('editor')
            ->and($request->json('user'))->toBe(['email' => 'ada@example.com', 'roles' => ['admin', 'editor']]);
    });

    it('returns the default for a missing json key', function (): void {
        $request = jsonRequest('{"user":{"email":"ada@example.com"}}');

        expect($request->json('missing'))->toBeNull()
            ->and($request->json('missing', 'fallback'))->toBe('fallback')
            ->and($request->json('user.name', 'anonymous'))->toBe('anonymous')
            ->and($request->json('user.email.domain', 'none'))->toBe('none');
    });

    it('returns an empty array from json() for an empty json body', function (): void {
        $request = jsonRequest('');

        expect($request->json())->toBe([])
            ->and($request->json('title', 'default'))->toBe('default');
    });

    it('does not decode the body of a non-json request', function (): void {
        $request = jsonRequest('not json at all', 'text/plain');

        expect($request->json())->toBe([])
            ->and($request->json('title', 'default'))->toBe('default');
    });

    it('throws MalformedJsonException when a malformed json body is accessed', function (): void {
        $request = jsonRequest('{"title": ');

        expect(fn () => $request->json())->toThrow(MalformedJsonException::class)
            ->and(fn () => $request->json('title'))->toThrow(MalformedJsonException::class)
            ->and(fn () => $request->input('title'))->toThrow(MalformedJsonException::class);
    });

    it('includes the body length and decoder error in MalformedJsonException', function (): void {
        $request = jsonRequest('{"title": ');

        try {
            $request->json();
            $this->fail('Expected MalformedJsonException');
        } catch (MalformedJsonException $e) {
            expect($e->getMessage())->toContain('malformed JSON')
                ->and($e->getContext())->toContain('10 bytes')
                ->and($e->getContext())->toContain('Syntax error')
                ->and($e->getSuggestion())->not->toBe('');
        }
    });

    it('reads input from the json body for json requests', function (): void {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => 'application/json'],
            post: ['title' => 'from post'],
            body: '{"title":"from json","meta":{"tag":"php"}}',
        );

        expect($request->input('title'))->toBe('from json')
            ->and($request->input('meta.tag'))->toBe('php');
    });

    it('reads input from post data for form requests', function (): void {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
            post: ['title' => 'from post'],
            body: 'title=from+post',
        );

        expect($request->input('title'))->toBe('from post');
    });

    it('falls back to the query string from input()', function (): void {
        $request = jsonRequest('{"title":"Hello"}', query: ['page' => '2', 'title' => 'ignored']);

        expect($request->input('page'))->toBe('2')
            ->and($request->input('title'))->toBe('Hello')
            ->and($request->input('missing'))->toBeNull()
            ->and($request->input('missing', 'default'))->toBe('default');
    });

    it('merges query and body from input() with no key, body winning', function (): void {
        $request = jsonRequest('{"title":"Hello","count":3}', query: ['page' => '2', 'title' => 'ignored']);

        expect($request->input())->toBe(['page' => '2', 'title' => 'Hello', 'count' => 3]);
    });
});
