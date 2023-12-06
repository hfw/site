<?php

namespace Helix\Site\Response;

use Helix\Site;
use Helix\Site\Response;

/**
 * Renders a `PHTML` template with data.
 */
class View extends Response
{

    /**
     * @var array
     */
    protected array $data = [];

    /**
     * @var string
     */
    public readonly string $template;

    /**
     * @param Site $site
     * @param string $template
     * @param array $data
     */
    public function __construct(Site $site, string $template, array $data = [])
    {
        parent::__construct($site);
        $this->template = $template;
        $this->data = $data;
    }

    /**
     * Extracts `$data` to variables, and includes the `$template`.
     *
     * Sub-templates can be included from parent templates,
     * and those sub-templates will have `$data` in their own scope.
     *
     * `$this` from within any template will reference the top view instance.
     *
     * @param string $template
     * @param array $data
     * @return void
     */
    public function inc(string $template, array $data): void
    {
        unset($data['template'], $data['this']);
        extract($data);
        include $template;
    }

    /**
     * Renders the main template/data.
     */
    public function render(): void
    {
        if (!$this->isEmpty()) {
            $this->inc($this->template, $this->data);
        }
    }

}
