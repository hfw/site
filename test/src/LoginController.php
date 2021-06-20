<?php

namespace Helix\Site\Test;

use Helix\Site\Controller;
use Helix\Site\View;

class LoginController extends Controller
{

    public function get()
    {
        $session = $this->site->getSession();

        // logout whenever
        if ($this->path['action'] === 'logout') {
            $session->logout();
            $this->redirect_exit('/login');
        }

        // redirect already-authenticated users to their account page
        if ($session->getUser()) {
            $this->redirect_exit('/account');
        }

        // authenticate the user and redirect to their account page
        if ($token = $this['token']) {
            $session->verify($token)->setUser('user@example.com');
            $this->redirect_exit('/account');
        }

        // show the login page
        return new View('view/login.phtml', ['csrf' => $session->getToken()]);
    }
}
