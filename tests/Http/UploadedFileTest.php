<?php

declare(strict_types=1);

use Marko\Routing\Exceptions\UploadedFileException;
use Marko\Routing\Http\UploadedFile;

/** A 1x1 transparent PNG. */
const UPLOADED_FILE_TEST_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

function uploadedFileTestDirectory(): string
{
    $directory = sys_get_temp_dir() . '/marko-uploaded-file-test-' . bin2hex(random_bytes(6));
    mkdir($directory);

    return $directory;
}

function makeTestUpload(
    string $contents = 'hello upload',
    int $error = UPLOAD_ERR_OK,
    string $clientFilename = 'notes.txt',
    string $clientMediaType = 'text/plain',
): UploadedFile {
    $path = tempnam(sys_get_temp_dir(), 'marko-upload-');
    file_put_contents($path, $contents);

    return new UploadedFile(
        tempPath: $path,
        clientFilename: $clientFilename,
        clientMediaType: $clientMediaType,
        size: strlen($contents),
        error: $error,
    );
}

describe('UploadedFile', function (): void {
    it('exposes client filename, client media type, size, error and temp path', function (): void {
        $file = new UploadedFile(
            tempPath: '/tmp/php1234',
            clientFilename: 'avatar.png',
            clientMediaType: 'image/png',
            size: 2048,
            error: UPLOAD_ERR_OK,
        );

        expect($file->clientFilename())->toBe('avatar.png')
            ->and($file->clientMediaType())->toBe('image/png')
            ->and($file->size())->toBe(2048)
            ->and($file->error())->toBe(UPLOAD_ERR_OK)
            ->and($file->tempPath())->toBe('/tmp/php1234')
            ->and($file->isMoved())->toBeFalse();
    });

    it('is valid only when the error code is UPLOAD_ERR_OK', function (): void {
        expect(makeTestUpload()->isValid())->toBeTrue()
            ->and(makeTestUpload(error: UPLOAD_ERR_INI_SIZE)->isValid())->toBeFalse()
            ->and(makeTestUpload(error: UPLOAD_ERR_PARTIAL)->isValid())->toBeFalse();
    });

    it('moves the file to the target path', function (): void {
        $file = makeTestUpload('moved contents');
        $source = $file->tempPath();
        $target = uploadedFileTestDirectory() . '/stored.txt';

        $file->moveTo($target);

        expect(file_get_contents($target))->toBe('moved contents')
            ->and(file_exists($source))->toBeFalse()
            ->and($file->isMoved())->toBeTrue()
            ->and($file->isValid())->toBeFalse();
    });

    it('throws when moving a file a second time', function (): void {
        $file = makeTestUpload();
        $directory = uploadedFileTestDirectory();
        $file->moveTo($directory . '/first.txt');

        expect(fn () => $file->moveTo($directory . '/second.txt'))
            ->toThrow(UploadedFileException::class, 'has already been moved');
    });

    it('throws when moving an upload that failed with an error code, naming the error', function (): void {
        $file = makeTestUpload(error: UPLOAD_ERR_INI_SIZE);

        try {
            $file->moveTo(uploadedFileTestDirectory() . '/never.txt');
            $this->fail('Expected UploadedFileException');
        } catch (UploadedFileException $e) {
            expect($e->getMessage())->toContain('notes.txt')
                ->and($e->getContext())->toContain('UPLOAD_ERR_INI_SIZE')
                ->and($e->getSuggestion())->toContain('upload_max_filesize');
        }
    });

    it('throws when the target directory does not exist', function (): void {
        $file = makeTestUpload();

        expect(fn () => $file->moveTo('/nonexistent-marko-dir/' . bin2hex(random_bytes(4)) . '/file.txt'))
            ->toThrow(UploadedFileException::class, 'Could not move')
            ->and($file->isMoved())->toBeFalse();
    });

    it('throws when reading the contents after the file was moved', function (): void {
        $file = makeTestUpload();
        $file->moveTo(uploadedFileTestDirectory() . '/stored.txt');

        expect(fn () => $file->contents())->toThrow(UploadedFileException::class, 'has already been moved')
            ->and(fn () => $file->stream())->toThrow(UploadedFileException::class, 'has already been moved');
    });

    it('returns the file contents and a readable stream', function (): void {
        $file = makeTestUpload('stream me');

        $stream = $file->stream();

        expect($file->contents())->toBe('stream me')
            ->and(is_resource($stream))->toBeTrue()
            ->and(stream_get_contents($stream))->toBe('stream me');

        fclose($stream);
    });

    it('detects the real mime type from file contents, ignoring the client media type', function (): void {
        $file = makeTestUpload(
            contents: base64_decode(UPLOADED_FILE_TEST_PNG),
            clientFilename: 'innocent.txt',
            clientMediaType: 'text/plain',
        );

        expect($file->mimeType())->toBe('image/png');
    });

    it('guesses the extension from the real mime type', function (): void {
        $png = makeTestUpload(
            contents: base64_decode(UPLOADED_FILE_TEST_PNG),
            clientFilename: 'photo.exe',
            clientMediaType: 'application/octet-stream',
        );
        $text = makeTestUpload(contents: 'plain words', clientFilename: 'readme.php');

        expect($png->guessExtension())->toBe('png')
            ->and($text->guessExtension())->toBe('txt');
    });
});
