<?php

namespace Helix;

use ErrorException;
use Helix\Site\Auth;
use Helix\Site\Error;
use Helix\Site\Request;
use Helix\Site\Response;
use Throwable;

/**
 * Routing and error handling.
 */
class Site
{

    /**
     * @var Auth
     */
    protected $auth;

    /**
     * @var bool
     */
    protected $dev = false;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Response
     */
    protected $response;

    /**
     * Initializes the system for routing, error handling, and output.
     */
    public function __construct()
    {
        error_reporting(E_ALL);
        set_error_handler([$this, '_onRaise']);
        $this->request = new Request();
        $this->response = new Response($this);
        $this->setDev(php_sapi_name() === 'cli-server');
        set_exception_handler([$this, '_onException']);
        error_reporting(E_RECOVERABLE_ERROR | E_WARNING | E_USER_ERROR | E_USER_WARNING);
    }

    /**
     * Handles uncaught exceptions and exits.
     *
     * @param Throwable $error
     * @return void
     */
    public function _onException(Throwable $error): void
    {
        if (!$error instanceof Error) {
            $this->log(500, "[{$error->getCode()}] {$error}");
        } elseif ($error->getCode() >= 500) {
            $this->log($error->getCode(), $error);
        }
        $this->response->error($error) and exit;
    }

    /**
     * Handles raised PHP errors by throwing or logging them,
     * depending on whether they're in `error_reporting()`
     *
     * @param int $code
     * @param string $message
     * @param string $file
     * @param int $line
     * @throws ErrorException
     */
    public function _onRaise(int $code, string $message, string $file, int $line)
    {
        $type = [
            E_DEPRECATED => 'E_DEPRECATED',
            E_NOTICE => 'E_NOTICE',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_WARNING => 'E_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_USER_WARNING => 'E_USER_WARNING',
        ][$code];
        if (error_reporting() & $code) {
            throw new ErrorException("{$type}: {$message}", $code, 1, $file, $line);
        }
        $this->log($type, "{$message} in {$file}:{$line}");
    }

    /**
     * Routes `DELETE`
     *
     * @param string $path
     * @param callable $controller
     * @return void
     */
    public function delete(string $path, callable $controller): void
    {
        $this->route(['DELETE'], $path, $controller);
    }

    /**
     * Routes `GET` and `HEAD`
     *
     * @param string $path
     * @param callable $controller
     * @return void
     */
    public function get(string $path, callable $controller): void
    {
        $this->route(['GET', 'HEAD'], $path, $controller);
    }

    /**
     * @return Auth
     */
    final public function getAuth()
    {
        return $this->auth ?? $this->auth = new Auth($this);
    }

    /**
     * @return Request
     */
    final public function getRequest()
    {
        return $this->request;
    }

    /**
     * @return Response
     */
    final public function getResponse()
    {
        return $this->response;
    }

    /**
     * @return bool
     */
    final public function isDev(): bool
    {
        return $this->dev;
    }

    /**
     * @param mixed $code
     * @param string $message
     * @return $this
     */
    public function log($code, string $message)
    {
        $now = date('Y-m-d H:i:s');
        $id = $this->response->getId();
        $ip = $this->request->getClient();
        $method = $this->request->getMethod();
        $path = $this->request->getPath();
        $line = "{$now} {$code} {$id} {$ip} {$method} {$path} - {$message}\n\n";
        error_log($line, 3, 'error.log');
        return $this;
    }

    /**
     * Routes `POST`
     *
     * @param string $path
     * @param callable $controller
     */
    public function post(string $path, callable $controller): void
    {
        $this->route(['POST'], $path, $controller);
    }

    /**
     * Routes `PUT`
     *
     * @param string $path
     * @param callable $controller
     * @return void
     */
    public function put(string $path, callable $controller): void
    {
        $this->route(['PUT'], $path, $controller);
    }

    /**
     * Invokes a controller if the HTTP method and path match, and exits.
     * Absolute paths must start with `/`, all other paths are treated as regular expressions.
     *
     * @param string[] $methods
     * @param string $path
     * @param callable $controller `(string[] $match, Site $site):mixed`
     * @return void
     */
    protected function route(array $methods, string $path, callable $controller): void
    {
        $match = [];
        if ($path[0] !== '/') {
            preg_match($path, $this->request->getPath(), $match);
        } elseif ($path === $this->request->getPath()) {
            $match = [$path];
        }
        if ($match) {
            if (in_array($this->request->getMethod(), $methods)) {
                $this->response->setCode(200);
                $content = call_user_func($controller, $match, $this);
                $this->response->mixed($content) and exit;
            }
            $this->response->setCode(405);
        }
    }

    /**
     * @param bool $dev
     * @return $this
     */
    public function setDev(bool $dev)
    {
        $this->dev = $dev;
        return $this;
    }
}
