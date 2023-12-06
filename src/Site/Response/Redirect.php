<?php

namespace Helix\Site\Response;

use Helix\Site;
use Helix\Site\Response;

/**
 * Redirect (headers only).
 */
class Redirect extends Response
{

    /**
     * @param Site $site
     * @param string $location
     * @param int $code
     */
    public function __construct(Site $site, string $location, int $code = 302)
    {
        parent::__construct($site);
        $this->headers['Location'] = $location;
        $this->code = $code;
    }

    /**
     * @return void
     */
    public function render(): void
    {
        // empty
    }
}
