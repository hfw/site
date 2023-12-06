<?php

namespace Helix\Site\Response;

use Helix\Site;
use Helix\Site\HttpError;
use Throwable;

/**
 * Renders an exception in a view.
 */
class ErrorView extends View
{

    /**
     * @var int
     */
    protected int $code = 500;

    /**
     * @var Throwable
     */
    public readonly Throwable $error;

    /**
     * @param Site $site
     * @param Throwable $error
     */
    public function __construct(Site $site, Throwable $error)
    {
        $this->error = $error;
        $template = __DIR__ . '/error.phtml';
        if ($error instanceof HttpError) {
            $this->code = $error->getCode();
            if (file_exists("view/{$this->code}.phtml")) {
                $template = "view/{$this->code}.phtml";
            } elseif (file_exists('view/error.phtml')) {
                $template = 'view/error.phtml';
            }
        }
        parent::__construct($site, $template);
    }

}
