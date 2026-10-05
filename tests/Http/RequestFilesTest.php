<?php

declare(strict_types=1);

use Marko\Routing\Exceptions\UploadedFileException;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\UploadedFile;

describe('Request files', function (): void {
    beforeEach(function (): void {
        $_SERVER = ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/upload'];
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_FILES = [];
    });

    afterEach(function (): void {
        $_FILES = [];
    });

    it('normalizes a single uploaded file from $_FILES', function (): void {
        $_FILES = [
            'avatar' => [
                'name' => 'avatar.png',
                'full_path' => 'avatar.png',
                'type' => 'image/png',
                'tmp_name' => '/tmp/phpA',
                'error' => UPLOAD_ERR_OK,
                'size' => 1024,
            ],
        ];

        $file = Request::fromGlobals()->file('avatar');

        expect($file)->toBeInstanceOf(UploadedFile::class)
            ->and($file->clientFilename())->toBe('avatar.png')
            ->and($file->clientMediaType())->toBe('image/png')
            ->and($file->tempPath())->toBe('/tmp/phpA')
            ->and($file->error())->toBe(UPLOAD_ERR_OK)
            ->and($file->size())->toBe(1024);
    });

    it('normalizes multiple uploaded files from a files[] input', function (): void {
        $_FILES = [
            'photos' => [
                'name' => ['one.jpg', 'two.jpg'],
                'type' => ['image/jpeg', 'image/jpeg'],
                'tmp_name' => ['/tmp/phpOne', '/tmp/phpTwo'],
                'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_PARTIAL],
                'size' => [100, 200],
            ],
        ];

        $photos = Request::fromGlobals()->files('photos');

        expect($photos)->toHaveCount(2)
            ->and($photos[0]->clientFilename())->toBe('one.jpg')
            ->and($photos[0]->tempPath())->toBe('/tmp/phpOne')
            ->and($photos[1]->clientFilename())->toBe('two.jpg')
            ->and($photos[1]->size())->toBe(200)
            ->and($photos[1]->error())->toBe(UPLOAD_ERR_PARTIAL);
    });

    it('normalizes nested uploaded file inputs', function (): void {
        $_FILES = [
            'user' => [
                'name' => ['avatar' => 'me.png', 'documents' => ['cv.pdf', 'letter.pdf']],
                'type' => ['avatar' => 'image/png', 'documents' => ['application/pdf', 'application/pdf']],
                'tmp_name' => ['avatar' => '/tmp/phpMe', 'documents' => ['/tmp/phpCv', '/tmp/phpLetter']],
                'error' => ['avatar' => UPLOAD_ERR_OK, 'documents' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK]],
                'size' => ['avatar' => 10, 'documents' => [20, 30]],
            ],
        ];

        $request = Request::fromGlobals();
        $files = $request->files();

        expect($files['user']['avatar'])->toBeInstanceOf(UploadedFile::class)
            ->and($request->file('user.avatar')->clientFilename())->toBe('me.png')
            ->and($request->files('user.documents'))->toHaveCount(2)
            ->and($request->files('user.documents')[1]->tempPath())->toBe('/tmp/phpLetter');
    });

    it('skips inputs submitted without a file', function (): void {
        $_FILES = [
            'avatar' => [
                'name' => '',
                'type' => '',
                'tmp_name' => '',
                'error' => UPLOAD_ERR_NO_FILE,
                'size' => 0,
            ],
            'photos' => [
                'name' => ['one.jpg', ''],
                'type' => ['image/jpeg', ''],
                'tmp_name' => ['/tmp/phpOne', ''],
                'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE],
                'size' => [100, 0],
            ],
        ];

        $request = Request::fromGlobals();

        expect($request->file('avatar'))->toBeNull()
            ->and($request->hasFile('avatar'))->toBeFalse()
            ->and($request->files('photos'))->toHaveCount(1)
            ->and(array_keys($request->files()))->toBe(['photos']);
    });

    it('returns a file by dot-notation key and null when missing', function (): void {
        $avatar = new UploadedFile('/tmp/phpMe', 'me.png', 'image/png', 10);
        $request = new Request(files: ['user' => ['avatar' => $avatar]]);

        expect($request->file('user.avatar'))->toBe($avatar)
            ->and($request->file('user.missing'))->toBeNull()
            ->and($request->file('missing'))->toBeNull()
            ->and($request->files('missing'))->toBe([])
            ->and($request->files('user.avatar'))->toBe([$avatar]);
    });

    it('throws when file() targets a multi-file input', function (): void {
        $request = new Request(files: [
            'photos' => [
                new UploadedFile('/tmp/phpOne', 'one.jpg', 'image/jpeg', 100),
                new UploadedFile('/tmp/phpTwo', 'two.jpg', 'image/jpeg', 200),
            ],
        ]);

        expect(fn () => $request->file('photos'))
            ->toThrow(UploadedFileException::class, "The upload field 'photos' holds several files");
    });

    it('reports hasFile for present and missing files', function (): void {
        $request = new Request(files: [
            'avatar' => new UploadedFile('/tmp/phpMe', 'me.png', 'image/png', 10),
            'photos' => [new UploadedFile('/tmp/phpOne', 'one.jpg', 'image/jpeg', 100)],
        ]);

        expect($request->hasFile('avatar'))->toBeTrue()
            ->and($request->hasFile('photos'))->toBeTrue()
            ->and($request->hasFile('missing'))->toBeFalse()
            ->and((new Request())->files())->toBe([]);
    });

    it('preserves files and json through withRoute()', function (): void {
        $avatar = new UploadedFile('/tmp/phpMe', 'me.png', 'image/png', 10);
        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => 'application/json'],
            body: '{"title":"Hello"}',
            files: ['avatar' => $avatar],
        );

        $routed = $request->withRoute('App\\Controller', 'store');

        expect($routed->file('avatar'))->toBe($avatar)
            ->and($routed->json('title'))->toBe('Hello')
            ->and($routed->controller())->toBe('App\\Controller')
            ->and($routed->action())->toBe('store');
    });
});
