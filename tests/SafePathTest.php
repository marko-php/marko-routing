<?php

declare(strict_types=1);

use Marko\Core\Exceptions\HttpExceptionInterface;
use Marko\Routing\Exceptions\UnsafePathException;
use Marko\Routing\SafePath;

describe('SafePath', function (): void {
    it('joins a relative path onto a base directory', function (): void {
        expect(SafePath::join('/var/www/storage', 'reports/2026.pdf'))->toBe('/var/www/storage/reports/2026.pdf')
            ->and(SafePath::join('/var/www/storage/', 'reports/2026.pdf'))->toBe('/var/www/storage/reports/2026.pdf')
            ->and(SafePath::join('/', 'etc'))->toBe('/etc');
    });

    it('normalises empty and current-directory segments', function (): void {
        expect(SafePath::join('/srv', 'a//./b/.'))->toBe('/srv/a/b')
            ->and(SafePath::join('/srv', '.'))->toBe('/srv');
    });

    it('resolves parent segments that stay inside the base', function (): void {
        expect(SafePath::join('/srv', 'a/b/../c'))->toBe('/srv/a/c')
            ->and(SafePath::join('/srv', 'a/..'))->toBe('/srv');
    });

    it('refuses a relative path that escapes the base', function (string $relative): void {
        expect(fn () => SafePath::join('/var/www/storage', $relative))
            ->toThrow(UnsafePathException::class, 'escapes');
    })->with(['..', '../secret', '../../etc/passwd', 'a/../../b', 'a/./../..']);

    it('refuses an absolute relative path', function (): void {
        expect(fn () => SafePath::join('/var/www/storage', '/etc/passwd'))
            ->toThrow(UnsafePathException::class, 'absolute');
    });

    it('refuses a NUL byte', function (): void {
        expect(fn () => SafePath::join('/var/www/storage', "report.pdf\0.txt"))
            ->toThrow(UnsafePathException::class, 'NUL');
    });

    it('refuses an empty base directory', function (): void {
        expect(fn () => SafePath::join('', 'report.pdf'))
            ->toThrow(UnsafePathException::class, 'base');
    });

    it('renders an unsafe path as a 404 without echoing the path', function (): void {
        $exception = null;

        try {
            SafePath::join('/var/www/storage', '../../etc/passwd');
        } catch (UnsafePathException $caught) {
            $exception = $caught;
        }

        expect($exception)->toBeInstanceOf(HttpExceptionInterface::class)
            ->and($exception?->getStatusCode())->toBe(404)
            ->and($exception?->getHeaders())->toBe([])
            ->and($exception?->getResponseData())->toBe(['message' => 'Not Found']);
    });
});
