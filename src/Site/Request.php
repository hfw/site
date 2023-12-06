<?php

namespace Helix\Site;

use ArrayAccess;
use Helix\Site;
use LogicException;

/**
 * The request.
 */
class Request implements ArrayAccess
{

    /**
     * Request headers, keyed in lowercase.
     *
     * @var string[]
     */
    public readonly array $headers;

    /**
     * HTTP method.
     *
     * @var string
     */
    public readonly string $method;

    /**
     * The request path, without arguments, cleaned up.
     *
     * @var string
     */
    public readonly string $path;

    /**
     * @var Site
     */
    public readonly Site $site;

    /**
     * `POST` file uploads.
     *
     * @var Upload[]
     */
    public readonly array $uploads;

    /**
     * Constructs using CGI data.
     */
    public function __construct(Site $site)
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD']);
        $this->site = $site;
        $this->path = Util::path(urldecode(strtok($_SERVER['REQUEST_URI'], '?')));
        $this->headers = array_change_key_case(getallheaders());
        $uploads = [];
        foreach ($_FILES as $group => $file) {
            if (is_array($file['name'])) {
                // php makes file groups an inside-out table. unwrap it.
                for ($i = 0; $i < count($file['name']); $i++) {
                    $uploads[] = $site->factory(Upload::class,
                        $group,
                        $file['name'][$i],
                        $file['error_code'][$i],
                        $file['tmp_name'][$i]
                    );
                }
            } else {
                $uploads[] = $site->factory(Upload::class,
                    $group,
                    $file['name'],
                    $file['error_code'],
                    $file['tmp_name']
                );
            }
        }
        $this->uploads = $uploads;
    }

    /**
     * @return string
     */
    final public function __toString()
    {
        return $this->path;
    }

    /**
     * Returns the client IP, which may have been forwarded.
     *
     * @return string
     */
    public function getIp(): string
    {
        if (in_array($_SERVER['REMOTE_ADDR'], $this->site->getProxies())) {
            return $this['X-Forwarded-For'] ?? $_SERVER['REMOTE_ADDR'];
        }
        return $_SERVER['REMOTE_ADDR'];
    }

    /**
     * @param string $group
     * @return Upload[]
     */
    final public function getUploads(string $group): array
    {
        return array_filter($this->uploads, fn(Upload $upload) => $upload->group === $group);
    }

    /**
     * Checks for a request header.
     *
     * @param string $offset
     * @return bool
     */
    final public function offsetExists($offset): bool
    {
        return isset($this->headers[strtolower($offset)]);
    }

    /**
     * Returns a request header.
     *
     * @param string $offset
     * @return null|string
     */
    final public function offsetGet($offset): ?string
    {
        return $this->headers[strtolower($offset)] ?? null;
    }

    /**
     * Throws.
     *
     * @param mixed $offset
     * @param mixed $value
     * @throws LogicException
     */
    final public function offsetSet(mixed $offset, mixed $value): never
    {
        throw new LogicException('Request headers are immutable.');
    }

    /**
     * Throws.
     *
     * @param mixed $offset
     * @throws LogicException
     */
    final public function offsetUnset(mixed $offset): never
    {
        throw new LogicException('Request headers are immutable.');
    }

}
