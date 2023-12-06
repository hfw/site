<?php

namespace Helix\Site\Response;

use Helix\Site;
use Helix\Site\Response;

/**
 * Renders JSON.
 */
class JSON extends Response
{
    /**
     * @var mixed
     */
    protected mixed $data;

    /**
     * @var int
     */
    protected int $depth;

    /**
     * @var int
     */
    protected int $options = JSON_PRETTY_PRINT;

    /**
     * @param Site $site
     * @param mixed $data
     * @param int $options
     * @param int $depth
     */
    public function __construct(Site $site, mixed $data, int $options = 0, int $depth = 512)
    {
        parent::__construct($site);
        $this->data = $data;
        $this->options |= $options;
        $this->depth = $depth;
        $this->headers['Content-Type'] = 'application/json';
        $this->headers['X-Content-Type-Options'] = 'nosniff';
    }

    /**
     * @return int
     */
    public function getOptions(): int
    {
        return $this->options;
    }

    /**
     * @return void
     */
    public function render(): void
    {
        echo json_encode($this->data, $this->options, $this->depth);
    }

    /**
     * @param int $options
     * @return $this
     */
    public function setOptions(int $options): static
    {
        $this->options = $options;
        return $this;
    }
}
