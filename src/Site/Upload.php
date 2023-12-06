<?php

namespace Helix\Site;

/**
 * A file upload.
 *
 * https://www.php.net/manual/en/features.file-upload.post-method.php
 */
class Upload
{

    /**
     * @var int
     */
    public readonly int $error_code;

    /**
     * The `POST` field name used to transport the file.
     *
     * @see https://www.php.net/manual/en/features.file-upload.multiple.php
     * @var string
     */
    public readonly string $group;

    /**
     * The name of the file on the client's machine.
     *
     * @var string
     */
    public readonly string $name;

    /**
     * Path of where the upload is initially stored.
     *
     * @var string
     */
    public readonly string $tmp_name;

    /**
     * @param string $group
     * @param int $error_code
     * @param string $name
     * @param string $tmp_name
     */
    public function __construct(string $group, string $name, int $error_code, string $tmp_name)
    {
        $this->group = $group;
        $this->name = $name;
        $this->error_code = $error_code;
        $this->tmp_name = $tmp_name;
    }

    /**
     * Generates and returns an {@link HttpError} if the status indicates one took place.
     *
     * @return null|HttpError
     */
    public function getError(): ?HttpError
    {
        return match ($this->error_code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => new HttpError(413, 'The file is too large.'),
            UPLOAD_ERR_PARTIAL => new HttpError(400, 'The file was only partially uploaded.'),
            UPLOAD_ERR_NO_FILE => new HttpError(400, 'No file was sent.'),
            UPLOAD_ERR_NO_TMP_DIR => new HttpError(507, 'Nowhere to store the file.'),
            UPLOAD_ERR_CANT_WRITE => new HttpError(507, 'Unable to write the file to storage.'),
            UPLOAD_ERR_EXTENSION => new HttpError(500, 'The file was rejected by the server.'),
            default => null
        };
    }

    /**
     * @return int
     */
    final public function getSize(): int
    {
        return filesize($this->tmp_name);
    }

    /**
     * @return int
     */
    final public function getStatus(): int
    {
        return $this->error_code;
    }

    /**
     * @return string
     */
    final public function getType(): string
    {
        return mime_content_type($this->tmp_name);
    }

    /**
     * @param string $destination
     * @return $this
     */
    public function move(string $destination): static
    {
        move_uploaded_file($this->tmp_name, $destination);
        return $this;
    }

    /**
     * @return false|resource
     */
    public function open()
    {
        return fopen($this->tmp_name, 'r');
    }
}
