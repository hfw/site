<?php

namespace Helix;

use Closure;
use ErrorException;
use Helix\Site\Controller;
use Helix\Site\HttpError;
use Helix\Site\Request;
use Helix\Site\Response;
use Helix\Site\Response\ErrorView;
use Helix\Site\Response\Redirect;
use Helix\Site\Response\Text;
use Helix\Site\Session;
use Throwable;

/**
 * Routing and error handling.
 */
class Site
{

    /**
     * Whether the site was instantiated in dev-mode.
     *
     * Enables verbose logging and exposed errors.
     *
     * Defaults to whether we're running in the `cli-server`.
     *
     * @var bool
     */
    public readonly bool $dev;

    /**
     * Timestamp of when the instance was created.
     *
     * @var int
     */
    public readonly int $now;

    /**
     * Trust client IP forwarding from these proxy IP addresses.
     *
     * @var string[]
     */
    protected array $proxies = [];

    /**
     * @var Request
     */
    public readonly Request $request;

    /**
     * The tentative response.
     *
     * This is initialized to `404`, potentially set to `405` during routing,
     * then set to the actual response from a matched controller.
     *
     * @var Response
     */
    protected Response $response;

    /**
     * @var Session
     */
    protected readonly Session $session;

    /**
     * A unique ID per instance, used in logging and the response.
     *
     * @var string
     */
    public readonly string $trace;

    /**
     * Initializes the system for routing, error handling, and output.
     *
     * @param null|bool $dev
     */
    public function __construct(bool $dev = null)
    {
        error_reporting(E_ALL);
        $this->now = time();
        $this->dev = $dev ?? (php_sapi_name() === 'cli-server');
        ob_end_clean(); // disable implied output buffer.
        ob_implicit_flush(); // flush() immediately on output
        header_register_callback($this->respond_headers(...));
        set_exception_handler($this->_onException(...)); // log and exit
        set_error_handler($this->_onWarning(...)); // throw to _onException
        register_shutdown_function($this->_onShutdown(...)); // ensure response
        $this->request = $this->factory(Request::class, $this);
        $this->response = $this->factory(Text::class, $this)->setCode(404); // assume no route
        $this->trace = uniqid();
    }

    /**
     * Logs uncaught exceptions and responds with an {@link ErrorView}.
     *
     * @param Throwable $error
     * @internal
     */
    protected function _onException(Throwable $error): never
    {
        if ($error instanceof HttpError) {
            if ($error->getCode() >= 500) {
                $this->_onException_log($error->getCode(), $error);
            }
        } else {
            $this->_onException_log(500, "[{$error->getCode()}] {$error}");
        }
        $this->respond($error);
    }

    /**
     * Logs exceptions to `error.log`
     *
     * @param string $code
     * @param string $message
     * @return $this
     * @internal
     */
    protected function _onException_log(string $code, string $message): static
    {
        error_log(sprintf("%s %s %s %s %s %s - %s\n\n",
            date('Y-m-d H:i:s'),
            $code,
            $this->trace,
            $this->request->getIp(),
            $this->request->method,
            $this->request->path,
            $message
        ), 3, 'error.log');
        return $this;
    }

    /**
     * Ensures a response is sent.
     *
     * @internal
     */
    protected function _onShutdown(): void
    {
        if (!headers_sent()) { // respond() wasn't called
            if ($fatal = error_get_last()) { // unhandled, must be fatal
                $this->_onException(new ErrorException($fatal['message'], $fatal['type'], 1, $fatal['file'], $fatal['line']));
            }
            $this->respond(new HttpError($this->response->getCode())); // 404/405
        }
    }

    /**
     * Throws raised PHP warnings/notices.
     *
     * @param int $code
     * @param string $message
     * @param string $file
     * @param int $line
     * @throws ErrorException
     * @internal
     */
    protected function _onWarning(int $code, string $message, string $file, int $line): never
    {
        error_clear_last();
        throw new ErrorException($message, $code, 1, $file, $line);
    }

    /**
     * Routes `DELETE`
     *
     * @param string $path
     * @param class-string<Controller>|Controller|Closure(array $matched, static $this):mixed $controller
     */
    public function delete(string $path, string|Controller|Closure $controller): void
    {
        $this->route(['DELETE'], $path, $controller);
    }

    /**
     * Centralized `new`.
     *
     * @template T
     * @param class-string<T> $class
     * @param mixed ...$args
     * @return T
     */
    public function factory(string $class, ...$args)
    {
        return new $class(...$args);
    }

    /**
     * Routes `GET` and `HEAD`
     *
     * @param string $path
     * @param class-string<Controller>|Controller|Closure(array $matched, static $this):mixed $controller
     */
    public function get(string $path, string|Controller|Closure $controller): void
    {
        $this->route(['GET', 'HEAD'], $path, $controller);
    }

    /**
     * @return string[]
     */
    public function getProxies(): array
    {
        return $this->proxies;
    }

    /**
     * @param int $ttl {@link Session::__construct()}
     * @return Session
     */
    final public function getSession(int $ttl = 0): Session
    {
        return $this->session ??= $this->factory(Session::class, $this, $ttl);
    }

    /**
     * Whether the session was started/accessed.
     * @return bool
     */
    final public function hasSession(): bool
    {
        return isset($this->session);
    }

    /**
     * Routes `POST`
     *
     * @param string $path
     * @param class-string<Controller>|Controller|Closure(array $matched, static $this):mixed $controller
     */
    public function post(string $path, string|Controller|Closure $controller): void
    {
        $this->route(['POST'], $path, $controller);
    }

    /**
     * Routes `PUT`
     *
     * @param string $path
     * @param class-string<Controller>|Controller|Closure(array $matched, static $this):mixed $controller
     */
    public function put(string $path, string|Controller|Closure $controller): void
    {
        $this->route(['PUT'], $path, $controller);
    }

    /**
     * Factory alias.
     *
     * @param string $location
     * @param int $code
     * @return Redirect
     */
    public function redirect(string $location, int $code = 302): Redirect
    {
        return $this->factory(Redirect::class, $this, $location, $code);
    }

    /**
     * @param null|string|Response|Throwable $response
     * @return never
     */
    protected function respond(mixed $response): never
    {
        if ($response instanceof Response) {
            $this->response = $response;
        } elseif ($response instanceof Throwable) {
            $this->response = $this->factory(ErrorView::class, $this, $response);
        } else {
            $this->response->value = $response;
        }
        if (!$this->response->isModified()) {
            $this->response->setCode(304);
        }
        if ($this->dev) {
            // log to sapi
            error_log(sprintf('%s [%s]: %s %s',
                $this->request->getIp(),
                $this->response->getCode(),
                $this->request->method,
                $this->request->path
            ), 4);
        }
        if (!$this->response->isEmpty()) {
            $this->response->render();
        }
        flush();
        exit;
    }

    /**
     * @return void
     */
    protected function respond_headers(): void
    {
        header_remove('X-Powered-By');
        foreach ($this->response->getHeaders() as $key => $value) {
            if (is_string($key)) {
                $value = "{$key}: {$value}";
            }
            header($value);
        }
        if (isset($this->request['X-Request-Id'])) {
            header("X-Request-Id: {$this->request['X-Request-Id']}");
        }
        header("X-Response-Id: {$this->trace}");
        http_response_code($this->response->getCode());
    }

    /**
     * Invokes a controller if the HTTP method and path match, and responds with what it returns.
     *
     * Absolute paths must start with `/`, all other paths are treated as regular expressions.
     * Therefore, do not enclose regular expressions within `/`.
     *
     * @param string[] $methods
     * @param string $path
     * @param class-string<Controller>|Controller|Closure(array $matched, static $this):mixed $controller
     */
    public function route(array $methods, string $path, string|Controller|Closure $controller): void
    {
        $matched = [];
        if ($path[0] !== '/') {
            preg_match($path, $this->request->path, $matched);
        } elseif ($path === $this->request->path) {
            $matched = [$path];
        }
        if ($matched) {
            if (in_array($this->request->method, $methods)) {
                $this->response->setCode(200);
                if (is_string($controller) and is_a($controller, Controller::class, true)) {
                    $controller = $this->factory($controller, $this, $matched);
                }
                /** @var Controller|Closure $controller */
                $this->respond($controller->__invoke($matched, $this));
            }
            $this->response->setCode(405); // matched but no method supported, continue to next route.
        }
    }

    /**
     * @param string[] $proxies
     * @return $this
     */
    public function setProxies(array $proxies): static
    {
        $this->proxies = $proxies;
        return $this;
    }
}
