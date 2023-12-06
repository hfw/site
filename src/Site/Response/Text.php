<?php

namespace Helix\Site\Response;

use Helix\Site;
use Helix\Site\Response;

/**
 * Renders an arbitrary string as strict `text/plain`.
 *
 * This is the default response type, should controllers return strings.
 */
class Text extends Response
{

    /**
     * @var null|string
     */
    public ?string $value;

    /**
     * @param Site $site
     * @param mixed $value
     */
    public function __construct(Site $site, ?string $value = '')
    {
        parent::__construct($site);
        $this->value = $value;
        $this->headers['Content-Type'] = 'text/plain';
        $this->headers['X-Content-Type-Options'] = 'nosniff';
    }

    /**
     * @return void
     */
    public function render(): void
    {
        echo $this->value;
    }
}
