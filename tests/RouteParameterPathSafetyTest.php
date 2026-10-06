<?php

declare(strict_types=1);

use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDefinition;
use Marko\Routing\RouteMatcher;

function pathSafetyMatcher(
    string ...$paths,
): RouteMatcher {
    $collection = new RouteCollection();

    foreach ($paths as $index => $path) {
        $collection->add(new RouteDefinition(
            method: 'GET',
            path: $path,
            controller: 'FileController',
            action: 'action' . $index,
        ));
    }

    return new RouteMatcher($collection);
}

describe('route parameter path safety', function (): void {
    it('does not match an encoded traversal in a single-segment parameter', function (): void {
        $matcher = pathSafetyMatcher('/files/{file}');

        expect($matcher->match('GET', '/files/..%2F..%2Fetc%2Fpasswd'))->toBeNull()
            ->and($matcher->allowedMethods('/files/..%2F..%2Fetc%2Fpasswd'))->toBe([]);
    });

    it('does not match an encoded slash in a single-segment parameter', function (): void {
        expect(pathSafetyMatcher('/files/{file}')->match('GET', '/files/a%2Fb'))->toBeNull()
            ->and(pathSafetyMatcher('/files/{file}')->match('GET', '/files/a%2fb'))->toBeNull();
    });

    it('does not match a dot segment in a single-segment parameter', function (string $segment): void {
        expect(pathSafetyMatcher('/files/{file}/raw')->match('GET', "/files/$segment/raw"))->toBeNull();
    })->with(['..', '.', '%2E%2E', '%2e', '.%2E']);

    it('does not match a NUL byte in a single-segment parameter', function (): void {
        expect(pathSafetyMatcher('/files/{file}')->match('GET', '/files/report.pdf%00.txt'))->toBeNull();
    });

    it('applies the same rules to a constrained parameter', function (): void {
        $matcher = pathSafetyMatcher('/files/{file:.+}');

        expect($matcher->match('GET', '/files/..%2Fsecret'))->toBeNull()
            ->and($matcher->match('GET', '/files/a/b'))->toBeNull()
            ->and($matcher->match('GET', '/files/a.b')?->parameters)->toBe(['file' => 'a.b']);
    });

    it('still matches values that merely contain dots', function (string $value): void {
        expect(pathSafetyMatcher('/files/{file}')->match('GET', "/files/$value")?->parameters)
            ->toBe(['file' => rawurldecode($value)]);
    })->with(['report.pdf', '...', '..hidden', 'v1..2', '.env', 'a%20b']);

    it('does not match a catch-all containing a dot segment', function (string $path): void {
        expect(pathSafetyMatcher('/assets/{path*}')->match('GET', $path))->toBeNull();
    })->with([
        '/assets/../../etc/passwd',
        '/assets/css/../../../etc/passwd',
        '/assets/..%2F..%2Fetc%2Fpasswd',
        '/assets/css/%2E%2E/secret',
        '/assets/..',
        '/assets/./app.css',
        '/assets/css/.',
    ]);

    it('does not match a catch-all containing a NUL byte', function (): void {
        expect(pathSafetyMatcher('/assets/{path*}')->match('GET', '/assets/app.css%00.php'))->toBeNull();
    });

    it('still lets a catch-all span slashes, including encoded ones', function (): void {
        $matcher = pathSafetyMatcher('/assets/{path*}');

        expect($matcher->match('GET', '/assets/css/app.css')?->parameters)->toBe(['path' => 'css/app.css'])
            ->and($matcher->match('GET', '/assets/css%2Fapp.css')?->parameters)->toBe(['path' => 'css/app.css'])
            ->and($matcher->match('GET', '/assets/.well-known/x..y')?->parameters)->toBe(
                ['path' => '.well-known/x..y'],
            );
    });

    it('falls through to the next route when a value is rejected', function (): void {
        $matched = pathSafetyMatcher('/files/{file}', '/files/{path*}')->match('GET', '/files/a%2Fb');

        expect($matched?->route->path)->toBe('/files/{path*}')
            ->and($matched?->parameters)->toBe(['path' => 'a/b']);
    });

    it('reports whether a decoded value is safe for a parameter', function (): void {
        $route = new RouteDefinition(method: 'GET', path: '/f/{dir}/{path*}', controller: 'C', action: 'a');

        expect($route->acceptsValue('dir', 'reports'))->toBeTrue()
            ->and($route->acceptsValue('dir', 'a/b'))->toBeFalse()
            ->and($route->acceptsValue('dir', '..'))->toBeFalse()
            ->and($route->acceptsValue('dir', "a\0b"))->toBeFalse()
            ->and($route->acceptsValue('path', 'a/b'))->toBeTrue()
            ->and($route->acceptsValue('path', 'a/../b'))->toBeFalse()
            ->and($route->acceptsValue('path', "a/b\0"))->toBeFalse();
    });
});
