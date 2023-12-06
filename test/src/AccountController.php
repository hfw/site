<?php

namespace Helix\Site\Test;

use Helix\Site;
use Helix\Site\Controller;
use Helix\Site\Response\View;

class AccountController extends Controller
{

    /**
     * Verifies we have an authenticated user before any request methods are handled.
     */
    public function __invoke(): mixed
    {
        // redirect to the login page if a user isn't logged in
        if (!$this->site->getSession()->getUser()) {
            return $this->site->redirect('/login');
        }
        return parent::__invoke();
    }

    /**
     * @return View
     */
    public function onGet(): View
    {
        return new View($this->site, 'view/account.phtml', ['session' => $this->site->getSession()]);
    }
}
