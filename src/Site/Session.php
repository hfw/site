<?php

namespace Helix\Site;

use ArrayAccess;
use Helix\Site;

/**
 * The session.
 */
class Session implements ArrayAccess
{

    /**
     * @var Site
     */
    public readonly Site $site;

    /**
     * Starts the session.
     *
     * @param Site $site
     * @param int $ttl Sessions may only last this many seconds, relative to when they're brand new.
     *                  This only affects new sessions, not resumed sessions.
     *                  A "remember me" login would be a reasonably large number.
     *                  Zero (default) means until the browser closes.
     */
    public function __construct(Site $site, int $ttl = 0)
    {
        $this->site = $site;
        session_cache_limiter(''); // let Response decide the headers
        session_set_cookie_params($ttl, '/', null, !$site->dev, true);
        session_start();
    }

    /**
     * Initializes/returns the random CSRF token.
     *
     * @return string
     */
    public function getToken(): string
    {
        return $this['__csrf'] ??= bin2hex(random_bytes(8));
    }

    /**
     * Returns the stored user, or `null`.
     *
     * @return mixed
     */
    public function getUser(): mixed
    {
        return $this['__user'] ?? null;
    }

    /**
     * Wipes the session.
     */
    public function logout(): void
    {
        setcookie(session_name(), '', 0);
        session_destroy();
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($_SESSION[$offset]);
    }

    /**
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $_SESSION[$offset] ?? null;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $_SESSION[$offset] = $value;
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($_SESSION[$offset]);
    }

    /**
     * Sets the user (logs them in).
     *
     * @param mixed $user
     * @return $this
     */
    public function setUser(mixed $user): static
    {
        $this['__user'] = $user;
        return $this;
    }

    /**
     * Checks the given CSRF token against what we expect.
     *
     * If they don't match, a `403` is thrown.
     *
     * @param mixed $token
     * @return $this
     */
    public function verify(mixed $token): static
    {
        if ($token !== $this->getToken()) {
            throw new HttpError(403, 'Invalid CSRF token.');
        }
        return $this;
    }
}
