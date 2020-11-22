<?php

namespace Helix\Site;

use ArrayAccess;

/**
 * The request.
 */
class Request implements ArrayAccess {

    /**
     * Grouped file uploads (multiple).
     *
     * `[ name => Upload[] ]`
     *
     * @var Upload[][]
     */
    protected $fileGroups = [];

    /**
     * File uploads (singular).
     *
     * `[ name => Upload ]`
     *
     * @var Upload[]
     */
    protected $files = [];

    /**
     * Request headers, keyed in lowercase.
     *
     * @var string[]
     */
    protected $headers = [];

    /**
     * The request path, without arguments, cleaned up.
     *
     * @var string
     */
    protected $path;

    /**
     * Trust client IP forwarding from these proxies.
     *
     * @var string[]
     */
    protected $proxies = [];

    public function __construct () {
        $this->path = Util::path(urldecode(strtok($_SERVER['REQUEST_URI'], '?')));
        $this->headers = array_change_key_case(getallheaders()); // phpstorm bug submitted, ext-apache isn't needed.
        foreach ($_FILES as $name => $info) {
            if (is_array($info['name'])) {
                // php makes file group $info tabular.
                for ($i = 0; $i < count($info['name']); $i++) {
                    $this->fileGroups[$name][$i] = new Upload(
                        $info['error_code'][$i],
                        $info['name'][$i],
                        $info['tmp_name'][$i]
                    );
                }
            }
            else {
                $this->files[$name] = new Upload(
                    $info['error_code'],
                    $info['name'],
                    $info['tmp_name']
                );
            }
        }
    }

    /**
     * @return string
     */
    final public function __toString () {
        return $this->path;
    }

    /**
     * Returns `POST` args merged over `GET` args.
     *
     * @return array
     */
    public function getArgs (): array {
        return array_merge($_GET, $_POST);
    }

    /**
     * Returns the client IP, which may have been forwarded.
     *
     * @return string
     */
    public function getClient () {
        if (in_array($_SERVER['REMOTE_ADDR'], $this->proxies)) {
            return $this['X-Forwarded-For'] ?? $_SERVER['REMOTE_ADDR'];
        }
        return $_SERVER['REMOTE_ADDR'];
    }

    /**
     * @param string $name
     * @return null|Upload
     */
    final public function getFile (string $name) {
        return $this->files[$name] ?? null;
    }

    /**
     * @param string $name
     * @return Upload[]
     */
    final public function getFileGroup (string $name) {
        return $this->fileGroups[$name] ?? [];
    }

    /**
     * @return Upload[][]
     */
    final public function getFileGroups () {
        return $this->fileGroups;
    }

    /**
     * @return Upload[]
     */
    final public function getFiles () {
        return $this->files;
    }

    /**
     * @return string[]
     */
    public function getHeaders (): array {
        return $this->headers;
    }

    /**
     * @return string
     */
    final public function getMethod (): string {
        return $_SERVER['REQUEST_METHOD'];
    }

    /**
     * @return string
     */
    final public function getPath (): string {
        return $this->path;
    }

    /**
     * @return string[]
     */
    public function getProxies (): array {
        return $this->proxies;
    }

    /**
     * @return bool
     */
    final public function isDelete (): bool {
        return $_SERVER['REQUEST_METHOD'] === 'DELETE';
    }

    /**
     * @return bool
     */
    final public function isGet (): bool {
        return $_SERVER['REQUEST_METHOD'] === 'GET';
    }

    /**
     * @return bool
     */
    final public function isHead (): bool {
        return $_SERVER['REQUEST_METHOD'] === 'HEAD';
    }

    /**
     * Whether the request can produce side-effects.
     *
     * @return bool
     */
    final public function isMuting (): bool {
        return !$this->isGet() and !$this->isHead();
    }

    /**
     * @return bool
     */
    final public function isPost (): bool {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    /**
     * Checks for a request header.
     *
     * @param string $key
     * @return bool
     */
    public function offsetExists ($key) {
        return isset($this->headers[strtolower($key)]);
    }

    /**
     * Returns a request header.
     *
     * @param string $key
     * @return null|string
     */
    public function offsetGet ($key) {
        return $this->headers[strtolower($key)] ?? null;
    }

    /**
     * Does nothing.
     *
     * @param mixed $key
     * @param mixed $value
     */
    public function offsetSet ($key, $value) {
        return;
    }

    /**
     * Does nothing.
     *
     * @param mixed $key
     */
    public function offsetUnset ($key) {
        return;
    }

    /**
     * @param string[] $proxies
     * @return $this
     */
    public function setProxies (array $proxies) {
        $this->proxies = $proxies;
        return $this;
    }
}