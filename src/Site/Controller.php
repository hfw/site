<?php

namespace Helix\Site;

use ArrayAccess;
use Helix\Site;

/**
 * A controller.
 */
class Controller implements ArrayAccess
{

    /**
     * Extra arguments from the router.
     *
     * @var array
     */
    protected array $extra;

    /**
     * The path's regex match from routing.
     *
     * `ArrayAccess` forwards to this.
     *
     * @var array
     */
    protected $path;

    /**
     * @var Site
     */
    protected $site;

    /**
     * @param Site $site
     * @param string[] $path
     * @param array $extra
     */
    public function __construct(Site $site, array $path, array $extra = [])
    {
        $this->site = $site;
        $this->path = $path;
        $this->extra = $extra;
    }

    /**
     * Throws `HTTP 501 Not Implemented` as a catch-all for any unimplemented non-standard request methods.
     *
     * @param string $method
     * @param array $args
     */
    final public function __call(string $method, array $args)
    {
        throw new HttpError(501);
    }

    /**
     * Handles `DELETE`
     *
     * This stub throws `HTTP 501 Not Implemented`
     *
     * @return void|string|View
     */
    public function delete()
    {
        throw new HttpError(501);
    }

    /**
     * Handles `GET`
     *
     * This stub throws `HTTP 501 Not Implemented`
     *
     * @return void|string|View
     */
    public function get()
    {
        throw new HttpError(501);
    }

    /**
     * Handles `HEAD`
     *
     * This stub returns from {@link Controller::get()}
     */
    public function head()
    {
        return $this->get();
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return isset($this->path[$offset]);
    }

    /**
     * @param mixed $offset
     * @return null|mixed Coalesces to `null`
     */
    public function offsetGet($offset)
    {
        return $this->path[$offset] ?? null;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value): void
    {
        $this->path[$offset] = $value;
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset): void
    {
        unset($this->path[$offset]);
    }

    /**
     * Handles `POST`
     *
     * This stub throws `HTTP 501 Not Implemented`
     *
     * @return void|string|View
     */
    public function post()
    {
        throw new HttpError(501);
    }

    /**
     * Handles `PUT`
     *
     * This stub throws `HTTP 501 Not Implemented`
     *
     * @return void|string|View
     */
    public function put()
    {
        throw new HttpError(501);
    }

    /**
     * Forwards to {@link Response::redirect_exit()}
     *
     * @param string $path
     * @param int $code
     */
    protected function redirect_exit(string $path, int $code = 302)
    {
        $this->site->getResponse()->redirect_exit($path, $code);
    }
}
