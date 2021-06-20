<?php

namespace Helix\Site;

use ArrayAccess;

/**
 * Renders a template with data.
 */
class View implements ArrayAccess, ViewableInterface
{

    /**
     * Extracted to variables upon render.
     *
     * @var array
     */
    protected $data = [];

    /**
     * @var string
     */
    protected $template;

    public function __construct(string $template, array $data = [])
    {
        $this->template = $template;
        $this->data = $data;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->template;
    }

    /**
     * @return string
     */
    public function getContent(): string
    {
        ob_start();
        $this->render();
        return ob_end_clean();
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return isset($this->data[$offset]);
    }

    /**
     * @param mixed $key
     * @return mixed
     */
    public function offsetGet($key)
    {
        return $this->data[$key] ?? null;
    }

    /**
     * @param mixed $key
     * @param mixed $value
     */
    public function offsetSet($key, $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * @param mixed $key
     */
    public function offsetUnset($key): void
    {
        unset($this->data[$key]);
    }

    /**
     * Extracts `$data` to variables, and includes the template.
     * `$this` within the template references the view instance.
     *
     * @return void
     */
    public function render(): void
    {
        extract($this->data);
        include "{$this->template}";
    }
}
