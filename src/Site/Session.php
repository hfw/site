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
    protected $site;

    /**
     * Starts the session and initializes the CSRF token.
     *
     * @param Site $site
     */
    public function __construct(Site $site)
    {
        $this->site = $site;
        session_set_cookie_params(0, '/', null, !$site->isDev(), true);
        session_start();
        $_SESSION['__csrf'] ??= bin2hex(random_bytes(8));
    }

    /**
     * @return string
     */
    public function getToken(): string
    {
        return $_SESSION['__csrf'];
    }

    /**
     * Returns the stored user, or `null`.
     *
     * @return mixed
     */
    public function getUser()
    {
        return $_SESSION['__user'] ?? null;
    }

    /**
     * Wipes the session.
     */
    public function logout(): void
    {
        setcookie(session_name(), null, 1);
        session_destroy();
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return isset($_SESSION[$offset]);
    }

    /**
     * @param mixed $offset
     * @return mixed Coalesces to `null`
     */
    public function offsetGet($offset)
    {
        return $_SESSION[$offset] ?? null;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value): void
    {
        $_SESSION[$offset] = $value;
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset): void
    {
        unset($_SESSION[$offset]);
    }

    /**
     * Sets the user (logs them in).
     *
     * @param mixed $user
     * @return $this
     */
    public function setUser($user)
    {
        $_SESSION['__user'] = $user;
        return $this;
    }

    /**
     * Checks the given CSRF token against what we expect.
     *
     * If they don't match, a `403` is logged and thrown.
     *
     * @param $token
     * @return $this
     * @throws HttpError
     */
    public function verify($token)
    {
        if ($token !== $this->getToken()) {
            $this->site->log(403, 'Invalid CSRF token.');
            throw new HttpError(403, 'Invalid CSRF token.');
        }
        return $this;
    }
}
