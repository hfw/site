<?php

namespace Helix;

use ErrorException;
use Helix\Site\Session;
use Helix\Site\Controller;
use Helix\Site\HttpError;
use Helix\Site\Request;
use Helix\Site\Response;
use Throwable;

/**
 * Routing and error handling.
 */
class Site
{

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
     * @var Session
     */
    protected $session;

    /**
     * Initializes the system for routing, error handling, and output.
     */
    public function __construct()
    {
        error_reporting(E_ALL);
        register_shutdown_function(fn() => $this->_onShutdown());
        set_error_handler(fn(...$args) => $this->_onRaise(...$args));
        set_exception_handler(fn(Throwable $error) => $this->_onException($error));
        $this->request = new Request();
        $this->response = new Response($this);
        $this->setDev(php_sapi_name() === 'cli-server');
    }

    /**
     * Handles uncaught exceptions and exits.
     *
     * @param Throwable $error
     * @internal
     */
    protected function _onException(Throwable $error): void
    {
        if ($error instanceof HttpError) {
            if ($error->getCode() >= 500) {
                $this->log($error->getCode(), $error);
            }
        } else {
            $this->log(500, "[{$error->getCode()}] {$error}");
        }
        $this->response->error_exit($error);
    }

    /**
     * Handles raised PHP errors (`E_NOTICE`, `E_WARNING`, etc) by throwing them.
     *
     * @param int $code
     * @param string $message
     * @param string $file
     * @param int $line
     * @throws ErrorException
     * @internal
     */
    protected function _onRaise(int $code, string $message, string $file, int $line)
    {
        error_clear_last();
        throw new ErrorException($message, $code, 1, $file, $line);
    }

    /**
     * A last-ditch effort to catch fatal PHP errors which aren't intercepted by {@link Site::_onRaise()}
     *
     * @internal
     */
    protected function _onShutdown(): void
    {
        if ($fatal = error_get_last()) {
            // can't throw, we're shutting down. call the exception handler directly.
            $fatal = new ErrorException($fatal['message'], $fatal['type'], 1, $fatal['file'], $fatal['line']);
            $this->_onException($fatal);
        }
    }

    /**
     * Routes `DELETE`
     *
     * @param string $path
     * @param string|callable $controller
     * @param array $extra
     */
    public function delete(string $path, $controller, array $extra = []): void
    {
        $this->route(['DELETE'], $path, $controller, $extra);
    }

    /**
     * Routes `GET` and `HEAD`
     *
     * @param string $path
     * @param string|callable $controller
     * @param array $extra
     */
    public function get(string $path, $controller, array $extra = []): void
    {
        $this->route(['GET', 'HEAD'], $path, $controller, $extra);
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
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @return Session
     */
    final public function getSession()
    {
        return $this->session ??= new Session($this);
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
     * @param string|callable $controller
     * @param array $extra
     */
    public function post(string $path, $controller, array $extra = []): void
    {
        $this->route(['POST'], $path, $controller, $extra);
    }

    /**
     * Routes `PUT`
     *
     * @param string $path
     * @param string|callable $controller
     * @param array $extra
     */
    public function put(string $path, $controller, array $extra = []): void
    {
        $this->route(['PUT'], $path, $controller, $extra);
    }

    /**
     * Invokes a controller if the HTTP method and path match, and exits.
     * Absolute paths must start with `/`, all other paths are treated as regular expressions.
     *
     * @param string[] $methods
     * @param string $path
     * @param string|callable $controller Controller class, or callable.
     * @param array $extra
     */
    public function route(array $methods, string $path, $controller, array $extra): void
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
                $this->route_call_exit($match, $controller, $extra);
            }
            $this->response->setCode(405);
        }
    }

    /**
     * @param string[] $path
     * @param string|callable $controller
     * @param array $extra
     * @uses Controller::delete()
     * @uses Controller::get()
     * @uses Controller::post()
     * @uses Controller::put()
     * @uses Controller::__call()
     */
    protected function route_call_exit(array $path, $controller, array $extra): void
    {
        if (is_string($controller)) {
            assert(is_a($controller, Controller::class, true));
            /** @var Controller $controller */
            $controller = new $controller($this, $path, $extra);
            $method = $this->request->getMethod();
            $content = $controller->{$method}(); // calls are not case sensitive
        } else {
            assert(is_callable($controller));
            $content = call_user_func($controller, $path, $this);
        }
        $this->response->mixed_exit($content);
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
