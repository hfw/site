<?php

namespace Helix\Site;

use ArrayAccess;
use Helix\Site;

/**
 * A response object encapsulates the HTTP response headers and body.
 */
class Response implements ArrayAccess
{

    /**
     * @var int
     */
    protected int $code = 200;

    /**
     * Associative values and/or enumerated literal headers.
     *
     * @var string[]
     */
    protected array $headers = [
        'Accept-Ranges' => 'none',
        'Cache-Control' => 'no-store'
    ];

    /**
     * @var Site
     */
    public readonly Site $site;

    /**
     * Last modification time. Ignored when zero.
     *
     * @var int
     */
    protected int $timestamp = 0;

    /**
     * @param Site $site
     */
    public function __construct(Site $site)
    {
        $this->site = $site;
    }

    /**
     * @return int
     */
    final public function getCode(): int
    {
        return $this->code;
    }

    /**
     * @return string[]
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @return int
     */
    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    /**
     * Whether the response is only supposed consist of headers.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->site->request->method === 'HEAD'
            or $this->code === 204
            or $this->code === 205
            or !$this->isModified();
    }

    /**
     * Whether the response body would be considered fresh.
     *
     * @return bool
     */
    public function isModified(): bool
    {
        // Explicit 304 takes precedence over all.
        if ($this->code === 304) {
            return false;
        }
        // If-None-Match takes precedence over If-Modified-Since
        if ($this->site->request['If-None-Match']) {
            return !in_array($this['ETag'], str_getcsv($this->site->request['If-None-Match']), true);
        }
        if ($this->timestamp and $this->site->request['If-Modified-Since']) {
            return $this->timestamp > strtotime($this->site->request['If-Modified-Since']);
        }
        return true;
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    final public function offsetExists(mixed $offset): bool
    {
        return isset($this->headers[$offset]);
    }

    /**
     * @param mixed $offset
     * @return null|string
     */
    final public function offsetGet(mixed $offset): ?string
    {
        return $this->headers[$offset] ?? null;
    }

    /**
     * @param mixed $offset
     * @param string $value
     */
    final public function offsetSet(mixed $offset, $value): void
    {
        $this->headers[$offset] = $value;
    }

    /**
     * @param mixed $offset
     */
    final public function offsetUnset(mixed $offset): void
    {
        unset($this->headers[$offset]);
    }

    /**
     * @return void
     */
    public function render(): void
    {
        flush();
    }

    /**
     * @param int $code
     * @return $this
     */
    public function setCode(int $code): static
    {
        $this->code = $code;
        return $this;
    }

    /**
     * Sets or unsets the timestamp and `Last-Modified` header.
     *
     * @param int $timestamp
     * @return $this
     */
    public function setTimestamp(int $timestamp): static
    {
        if ($timestamp) {
            $this['Last-Modified'] = gmdate('D, d M Y H:i:s T', $timestamp);
        } else {
            unset($this['Last-Modified']);
            $this['Cache-Control'] = 'no-store';
        }
        $this->timestamp = $timestamp;
        return $this;
    }

    /**
     * Specifies how long the response can be cached.
     * The response {@link $timestamp} must already be positive.
     *
     * `Cache-Control` is set to `private` if the {@link Session} has been accessed.
     *
     * @param int $ttl
     * @return $this
     */
    public function setTtl(int $ttl): static
    {
        $this['Cache-Control'] = $this->timestamp > 0 && $ttl > 0
            ? ($this->site->hasSession() ? 'private, ' : '') . "must-revalidate, max-age={$ttl}"
            : 'no-store';
        return $this;
    }

    /**
     * Updates the modification time if the given one is greater.
     *
     * For cached response bodies, this can be called with the modification
     * times of each of the body's components.
     *
     * @param int $timestamp
     * @return $this
     */
    public function touch(int $timestamp): static
    {
        if ($timestamp > $this->timestamp) {
            $this->setTimestamp($timestamp);
        }
        return $this;
    }
}
