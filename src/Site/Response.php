<?php

namespace Helix\Site;

use ArrayAccess;
use DateTimeInterface;
use Helix\Site;
use Throwable;

/**
 * The response.
 */
class Response implements ArrayAccess
{

    /**
     * @var int
     */
    protected $code = 404;

    /**
     * Associative values and/or enumerated literal headers.
     *
     * @var array
     */
    protected $headers = [
        'Accept-Ranges' => 'none',
        'Cache-Control' => 'no-store'
    ];

    /**
     * `X-Response-Id`, also used in logging errors.
     *
     * @var string
     */
    protected $id;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Site
     */
    protected $site;

    /**
     * Last modification time. Ignored when zero.
     *
     * @var int
     */
    protected $timestamp = 0;

    /**
     * @param Site $site
     */
    public function __construct(Site $site)
    {
        $this->site = $site;
        $this->request = $site->getRequest();
        $this->id = uniqid();
        if (isset($this->request['X-Request-Id'])) {
            $this['X-Request-Id'] = $this->request['X-Request-Id'];
        }
        ob_end_clean();
        ob_implicit_flush(); // flush() non-empty output.
        header_register_callback(fn() => $this->_onRender());
        register_shutdown_function(fn() => $this->_onShutdown());
    }

    /**
     * Injects headers before content is put in PHP's SAPI write buffer.
     *
     * @internal
     */
    protected function _onRender(): void
    {
        header_remove('X-Powered-By');
        $this['X-Response-Id'] = $this->id;
        ksort($this->headers);
        foreach ($this->headers as $key => $value) {
            if (is_string($key)) {
                $value = "{$key}: {$value}";
            }
            header($value);
        }
        if (!$this->isModified()) {
            $this->setCode(304);
        }
        http_response_code($this->code);
        if ($this->isEmpty()) {
            exit;
        }
    }

    /**
     * Renders an error if nothing was written to the SAPI yet.
     * Logs the response to the SAPI handler if the site is in dev mode.
     *
     * @internal
     */
    protected function _onShutdown(): void
    {
        if ($this->site->isDev()) {
            $ip = $this->request->getClient();
            $method = $this->request->getMethod();
            $path = $this->request->getPath();
            $line = "{$ip} [{$this->code}]: {$method} {$path}";
            error_log($line, 4);
        }
        if (!headers_sent()) {
            $this->error_exit();
        }
    }

    /**
     * Renders an error and exits.
     *
     * @param null|Throwable $error Defaults to an {@link HttpError} with the current response code.
     */
    public function error_exit(Throwable $error = null): void
    {
        $error = $error ?? new HttpError($this->code);
        $code = $error instanceof HttpError ? $error->getCode() : 500;
        $this->setCode($code);
        $template = __DIR__ . '/error.phtml';
        if (file_exists("view/{$code}.phtml")) {
            $template = "view/{$code}.phtml";
        } elseif (file_exists('view/error.phtml')) {
            $template = 'view/error.phtml';
        }
        $this->view_exit(new View($template, [
            'site' => $this->site,
            'error' => $error
        ]));
        exit;
    }

    /**
     * Outputs a file (or requested range) and exits.
     *
     * @param string $path
     * @param bool $download
     */
    public function file_exit(string $path, bool $download = false): void
    {
        clearstatcache(true, $path);
        if (!file_exists($path)) {
            $this->setCode(404)->error_exit();
        }
        if (!is_file($path) or !is_readable($path)) {
            $this->setCode(403)->error_exit();
        }
        $fh = fopen($path, 'rb');
        flock($fh, LOCK_SH);
        $size = filesize($path);
        $this->setTimestamp(filemtime($path));
        $this['ETag'] = $eTag = (string)filemtime($path);
        $this['Accept-Ranges'] = 'bytes';
        if ($download) {
            $this['Content-Disposition'] = sprintf('attachment; filename="%s"', basename($path));
        }
        $range = $this->request['Range'];
        $ifRange = trim($this->request['If-Range'], '"');
        if (!$range or ($ifRange and $ifRange !== $eTag and strtotime($ifRange) !== $this->timestamp)) {
            $this->setCode(200);
            $this['Content-Length'] = $size;
            $this['Content-Type'] = mime_content_type($path);
            fpassthru($fh);
            flush();
            exit;
        }
        if (preg_match('/^bytes=(?<start>\d+)?-(?<stop>\d+)?$/', $range, $bytes, PREG_UNMATCHED_AS_NULL)) {
            // maximum byte offset = file length - 1
            $max = $size - 1;
            // explicit start byte, or convert a negative offset. "-0" is illegal.
            $start = $bytes['start'] ?? (isset($bytes['stop']) ? $size - $bytes['stop'] : 0);
            // explicit stop byte, or maximum due to negative offset
            $stop = isset($bytes['start']) ? $bytes['stop'] ?? $max : $max;
            if (0 <= $start and $start <= $stop and $stop <= $max) { // the range is valid
                $this->setCode(206); // partial content
                $length = ($stop - $start) + 1;
                $this['Content-Length'] = $length;
                $this['Content-Range'] = "bytes {$start}-{$stop}/{$size}";
                fseek($fh, $start);
                if ($stop === $max) {
                    fpassthru($fh);
                } else {
                    echo fread($fh, $length);
                }
                flush();
                exit;
            }
        }
        $this['Content-Range'] = "bytes */{$size}";
        $this->setCode(416)->error_exit();
    }

    /**
     * @return int
     */
    final public function getCode(): int
    {
        return $this->code;
    }

    /**
     * @return string
     */
    final public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return int
     */
    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    /**
     * Whether the response will only consist of headers.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->request->isHead() or in_array($this->code, [204, 205, 304]);
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
        if ($this->request['If-None-Match']) {
            return !in_array($this['ETag'], str_getcsv($this->request['If-None-Match']), true);
        }
        if ($this->timestamp and $this->request['If-Modified-Since']) {
            return $this->timestamp > strtotime($this->request['If-Modified-Since']);
        }
        return true;
    }

    /**
     * Renders mixed content and exits.
     *
     * @param mixed $content
     */
    public function mixed_exit($content): void
    {
        if ($content instanceof View) {
            $this->view_exit($content);
        } elseif ($content instanceof Throwable) {
            $this->error_exit($content);
        } else {
            echo $content;
            flush();
            exit;
        }
    }

    /**
     * @param mixed $key
     * @return bool
     */
    public function offsetExists($key)
    {
        return isset($this->headers[$key]);
    }

    /**
     * @param mixed $key
     * @return null|string
     */
    public function offsetGet($key)
    {
        return $this->headers[$key] ?? null;
    }

    /**
     * @param mixed $key
     * @param string $value
     */
    public function offsetSet($key, $value)
    {
        $this->headers[$key] = $value;
    }

    /**
     * @param mixed $key
     */
    public function offsetUnset($key)
    {
        unset($this->headers[$key]);
    }

    /**
     * Issues a redirect and exits.
     *
     * @param string $location
     * @param int $code
     */
    public function redirect_exit(string $location, int $code = 302): void
    {
        $this->setCode($code);
        $this['Location'] = $location;
        flush();
        exit;
    }

    /**
     * Specifies how long the response can be cached by the client.
     *
     * @param int|DateTimeInterface $ttl Zero or negative time means "don't cache"
     * @return $this
     */
    public function setCacheTtl($ttl)
    {
        if ($ttl instanceof DateTimeInterface) {
            $ttl = $ttl->getTimestamp() - time();
        }
        $this['Cache-Control'] = $ttl > 0 ? "must-revalidate, max-age={$ttl}" : 'no-store';
        return $this;
    }

    /**
     * @param int $code
     * @return $this
     */
    public function setCode(int $code)
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
    public function setTimestamp(int $timestamp)
    {
        if ($timestamp) {
            $this['Last-Modified'] = gmdate('D, d M Y H:i:s T', $timestamp);
        } else {
            unset($this['Last-Modified']);
        }
        $this->timestamp = $timestamp;
        return $this;
    }

    /**
     * Updates the modification time if the given one is greater.
     *
     * @param int $timestamp
     * @return $this
     */
    public function touch(int $timestamp)
    {
        if ($timestamp > $this->timestamp) {
            $this->setTimestamp($timestamp);
        }
        return $this;
    }

    /**
     * Renders a view and exits.
     *
     * If the request is `HEAD` this skips rendering content and only outputs headers.
     *
     * @param View $view
     */
    public function view_exit(View $view): void
    {
        $this->setCacheTtl($view->getCacheTtl());
        if (!$this->request->isHead()) {
            $view->render();
        }
        flush();
        exit;
    }
}
