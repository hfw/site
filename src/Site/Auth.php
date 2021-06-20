<?php

namespace Helix\Site;

use Exception;
use Helix\Site;

/**
 * Authentication.
 */
class Auth
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
        if (!isset($_SESSION['_token'])) {
            try {
                $_SESSION['_token'] = bin2hex(random_bytes(8));
            } catch (Exception $exception) {
                $_SESSION['_token'] = bin2hex(openssl_random_pseudo_bytes(8));
            }
        }
    }

    /**
     * @return string
     */
    public function getToken(): string
    {
        return $_SESSION['_token'];
    }

    /**
     * Returns the stored user, or `null`.
     *
     * @return mixed
     */
    public function getUser()
    {
        return $_SESSION['_user'] ?? null;
    }

    /**
     * Wipes the session.
     */
    public function logout()
    {
        setcookie(session_name(), null, 1);
        session_destroy();
    }

    /**
     * Sets the user (logs them in).
     *
     * @param mixed $user
     * @return $this
     */
    public function setUser($user)
    {
        $_SESSION['_user'] = $user;
        return $this;
    }

    /**
     * Checks the given CSRF token against what's stored.
     * If they don't match, a `403` is logged and thrown.
     *
     * @param $token
     * @return $this
     * @throws Error
     */
    public function verify($token)
    {
        if ($token !== $this->getToken()) {
            $this->site->log(403, 'Invalid token.');
            throw new Error(403, 'Invalid token.');
        }
        return $this;
    }
}
