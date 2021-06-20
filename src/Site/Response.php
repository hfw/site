<?php

namespace Helix\Site;

use ArrayAccess;
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
        'Cache-Control' => 'no-cache'
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
        header_register_callback([$this, '_onRender']);
        register_shutdown_function([$this, '_onShutdown']);
    }

    /**
     * Injects headers before content is put in PHP's SAPI write buffer.
     *
     * @return void
     */
    public function _onRender(): void
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
     * @return void
     */
    public function _onShutdown(): void
    {
        if ($this->site->isDev()) {
            $ip = $this->request->getClient();
            $method = $this->request->getMethod();
            $path = $this->request->getPath();
            $line = "{$ip} [{$this->code}]: {$method} {$path}";
            error_log($line, 4);
        }
        if (!headers_sent()) {
            $this->error() and exit;
        }
    }

    /**
     * Renders an error and exits.
     *
     * @param Throwable $error Defaults to an `Error` with the current response code.
     */
    public function error(Throwable $error = null): void
    {
        $error = $error ?? new Error($this->code);
        $code = 500;
        if ($error instanceof Error) {
            $code = $error->getCode();
        }
        $this->setCode($code);
        $template = __DIR__ . '/error.phtml';
        if (file_exists("view/{$code}.phtml")) {
            $template = "view/{$code}.phtml";
        } elseif (file_exists('view/error.phtml')) {
            $template = 'view/error.phtml';
        }
        $this->view(new View($template, [
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
    public function file(string $path, bool $download = false): void
    {
        clearstatcache(true, $path);
        if (!file_exists($path)) {
            $this->setCode(404)->error() and exit;
        }
        if (!is_file($path) or !is_readable($path)) {
            $this->setCode(403)->error() and exit;
        }
        $fh = fopen($path, 'rb');
        flock($fh, LOCK_SH);
        $size = filesize($path);
        $this->setTimestamp(filemtime($path));
        $this['ETag'] = $eTag = filemtime($path);
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
        if (preg_match('/^bytes=(\d+)?-(\d+)?$/', $range, $range, PREG_UNMATCHED_AS_NULL)) {
            $max = $size - 1;
            $start = $range[1] ?? (isset($range[2]) ? $size - $range[2] : 0);
            $stop = isset($range[1]) ? $range[2] ?? $max : $max;
            if (0 <= $start and $start <= $stop and $stop <= $max) {
                $this->setCode(206);
                $this['Content-Length'] = $length = $stop - $start + 1;
                $this['Content-Range'] = "bytes {$start}-{$stop}/{$size}";
                fseek($fh, $start);
                if ($stop === $max) {
                    fpassthru($fh);
                    flush();
                    exit;
                }
                while (!feof($fh)) {
                    echo fread($fh, 8192);
                }
                flush();
                exit;
            }
        }
        $this['Content-Range'] = "bytes */{$size}";
        $this->setCode(416)->error() and exit;
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
    public function mixed($content): void
    {
        if ($content instanceof ViewableInterface) {
            $this->view($content) and exit;
        }
        if ($content instanceof Throwable) {
            $this->error($content) and exit;
        }
        echo $content;
        flush();
        exit;
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
    public function redirect(string $location, $code = 302)
    {
        $this->setCode($code);
        $this['Location'] = $location;
        flush();
        exit;
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
     * @param ViewableInterface $view
     */
    public function view(ViewableInterface $view): void
    {
        $view->render();
        flush();
        exit;
    }
}
