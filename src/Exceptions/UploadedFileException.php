<?php

declare(strict_types=1);

namespace Marko\Routing\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class UploadedFileException extends MarkoException
{
    private const array ERROR_NAMES = [
        UPLOAD_ERR_INI_SIZE => 'UPLOAD_ERR_INI_SIZE',
        UPLOAD_ERR_FORM_SIZE => 'UPLOAD_ERR_FORM_SIZE',
        UPLOAD_ERR_PARTIAL => 'UPLOAD_ERR_PARTIAL',
        UPLOAD_ERR_NO_FILE => 'UPLOAD_ERR_NO_FILE',
        UPLOAD_ERR_NO_TMP_DIR => 'UPLOAD_ERR_NO_TMP_DIR',
        UPLOAD_ERR_CANT_WRITE => 'UPLOAD_ERR_CANT_WRITE',
        UPLOAD_ERR_EXTENSION => 'UPLOAD_ERR_EXTENSION',
    ];

    private const array ERROR_SUGGESTIONS = [
        UPLOAD_ERR_INI_SIZE => 'The file exceeds the upload_max_filesize directive in php.ini. Raise upload_max_filesize (and post_max_size) or upload a smaller file.',
        UPLOAD_ERR_FORM_SIZE => 'The file exceeds the MAX_FILE_SIZE field of the HTML form. Raise MAX_FILE_SIZE or upload a smaller file.',
        UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded. Ask the client to retry the upload.',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded for this field. Check hasFile() before reading the file.',
        UPLOAD_ERR_NO_TMP_DIR => 'PHP has no temporary directory for uploads. Set upload_tmp_dir in php.ini to a writable directory.',
        UPLOAD_ERR_CANT_WRITE => 'PHP failed to write the upload to disk. Check free disk space and permissions on the upload temporary directory.',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the upload. Check the loaded extensions and the PHP error log.',
    ];

    public static function uploadFailed(
        string $clientFilename,
        int $error,
    ): self {
        $errorName = self::ERROR_NAMES[$error] ?? "unknown error code $error";

        return new self(
            message: "The upload of '$clientFilename' failed and the file cannot be used.",
            context: "PHP reported upload error $errorName.",
            suggestion: self::ERROR_SUGGESTIONS[$error] ?? 'Check isValid() before using an uploaded file.',
        );
    }

    public static function alreadyMoved(
        string $clientFilename,
    ): self {
        return new self(
            message: "The uploaded file '$clientFilename' has already been moved.",
            context: 'An uploaded file can be moved exactly once; its temporary file no longer exists after the move.',
            suggestion: 'Move the file once and read it from its new location afterwards.',
        );
    }

    public static function moveFailed(
        string $clientFilename,
        string $targetPath,
        string $reason,
    ): self {
        return new self(
            message: "Could not move the uploaded file '$clientFilename' to '$targetPath'.",
            context: $reason,
            suggestion: 'Make sure the target directory exists and is writable by the PHP process.',
        );
    }

    public static function unreadable(
        string $clientFilename,
        string $tempPath,
    ): self {
        return new self(
            message: "Could not read the uploaded file '$clientFilename'.",
            context: "The temporary file '$tempPath' could not be opened.",
            suggestion: 'Make sure the upload succeeded (isValid()) and the temporary file still exists.',
        );
    }

    public static function multipleFilesForKey(
        string $key,
    ): self {
        return new self(
            message: "The upload field '$key' holds several files, not one.",
            context: 'file() returns a single UploadedFile, but this field was submitted as a multi-file input (e.g. name="' . $key . '[]").',
            suggestion: "Read the field with files('$key') to get every uploaded file.",
        );
    }
}
