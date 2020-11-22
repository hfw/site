<?php

namespace Helix\Site;

/**
 * A file upload.
 */
class Upload {

    /**
     * The temporary path.
     *
     * @var string
     */
    protected $path;

    /**
     * The client's name for the file.
     *
     * @var string
     */
    protected $remoteName;

    /**
     * The upload's "error" code.
     *
     * @var int
     */
    protected $status;

    /**
     * @param int $status
     * @param string $remoteName
     * @param string $path
     */
    public function __construct (int $status, string $remoteName, string $path) {
        $this->status = $status;
        $this->remoteName = $remoteName;
        $this->path = $path;
    }

    /**
     * Generates and returns an `Error` if the status indicates one took place.
     *
     * @return null|Error
     */
    public function getError () {
        switch ($this->status) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return new Error(413, 'The file is too large.');
            case UPLOAD_ERR_PARTIAL:
                return new Error(400, 'The file was only partially uploaded.');
            case UPLOAD_ERR_NO_FILE:
                return new Error(400, 'No file was sent.');
            case UPLOAD_ERR_NO_TMP_DIR:
                return new Error(507, 'Nowhere to store the file.');
            case UPLOAD_ERR_CANT_WRITE:
                return new Error(507, 'Unable to write the file to storage.');
            case UPLOAD_ERR_EXTENSION:
                return new Error(500, 'The file was rejected by the server.');
            default:
                return null;
        }
    }

    /**
     * @return string
     */
    final public function getPath (): string {
        return $this->path;
    }

    /**
     * @return string
     */
    final public function getRemoteName (): string {
        return $this->remoteName;
    }

    /**
     * @return int
     */
    final public function getSize (): int {
        return filesize($this->path);
    }

    /**
     * @return int
     */
    final public function getStatus (): int {
        return $this->status;
    }

    /**
     * @return string
     */
    final public function getType (): string {
        return mime_content_type($this->path);
    }

    /**
     * @param string $path
     * @return $this
     */
    public function move (string $path) {
        move_uploaded_file($this->path, $path);
        return $this;
    }
}