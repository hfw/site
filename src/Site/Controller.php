<?php

namespace Helix\Site;

use ArrayAccess;
use Helix\Site;
use LogicException;

/**
 * A controller invoked by {@link Site::route()}
 */
class Controller implements ArrayAccess
{

    /**
     * The matched path from {@link Site::route()}.
     *
     * @var string[]
     */
    public readonly array $route;

    /**
     * @var Site
     */
    public readonly Site $site;

    /**
     * @param Site $site
     * @param string[] $route
     */
    public function __construct(Site $site, array $route)
    {
        $this->site = $site;
        $this->route = $route;
    }

    /**
     * Called by {@link Site::route()} when matched.
     *
     * @return mixed
     */
    public function __invoke(): mixed
    {
        return match ($this->site->request->method) {
            'GET', 'HEAD' => $this->onGet(),
            'POST' => $this->onPost(),
            'PUT' => $this->onPut(),
            'DELETE' => $this->onDelete(),
            default => throw new HttpError(501)
        };
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    final public function offsetExists(mixed $offset): bool
    {
        return isset($this->route[$offset]);
    }

    /**
     * @param mixed $offset
     * @return null|string
     */
    final public function offsetGet(mixed $offset): ?string
    {
        return $this->route[$offset] ?? null;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    final public function offsetSet(mixed $offset, mixed $value): never
    {
        throw new LogicException("The controller's matched route is immutable.");
    }

    /**
     * @param mixed $offset
     */
    final public function offsetUnset(mixed $offset): void
    {
        throw new LogicException("The controller's matched route is immutable.");
    }

    /**
     * Handles `DELETE`
     *
     * Base stub throws `HTTP 501 Not Implemented`
     *
     * @return mixed
     */
    public function onDelete(): mixed
    {
        throw new HttpError(501);
    }

    /**
     * Handles `GET`
     *
     * Base stub throws `HTTP 501 Not Implemented`
     *
     * @return mixed
     */
    public function onGet(): mixed
    {
        throw new HttpError(501);
    }

    /**
     * Handles `HEAD`
     *
     * Base stub returns from {@link self::onGet()},
     * since `HEAD` requests never have a response body rendered anyway.
     *
     * @return mixed
     */
    public function onHead(): mixed
    {
        return $this->onGet();
    }

    /**
     * Handles `POST`
     *
     * Base stub throws `HTTP 501 Not Implemented`
     *
     * @return mixed
     */
    public function onPost(): mixed
    {
        throw new HttpError(501);
    }

    /**
     * Handles `PUT`
     *
     * Base stub throws `HTTP 501 Not Implemented`
     *
     * @return mixed
     */
    public function onPut(): mixed
    {
        throw new HttpError(501);
    }
}
