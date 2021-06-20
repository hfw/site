<?php

namespace Helix\Site\Test;

use Helix\Site;
use Helix\Site\Controller;
use Helix\Site\View;

class AccountController extends Controller
{

    /**
     * Verifies we have an authenticated user before any request methods are handled.
     *
     * @param Site $site
     * @param string[] $path
     * @param array $extra
     */
    public function __construct(Site $site, array $path, array $extra = [])
    {
        parent::__construct($site, $path, $extra);

        // redirect to the login page if a user isn't logged in
        if (!$site->getSession()->getUser()) {
            $this->redirect_exit('/login');
        }
    }

    /**
     * @return View
     */
    public function get()
    {
        return new View('view/account.phtml', ['session' => $this->site->getSession()]);
    }
}
