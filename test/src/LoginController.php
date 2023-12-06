<?php

namespace Helix\Site\Test;

use Helix\Site\Controller;
use Helix\Site\Response;
use Helix\Site\Response\View;

class LoginController extends Controller
{

    public function onGet(): Response
    {
        $s = $this->site->getSession();

        // logout whenever
        if ($this['action'] === 'logout') {
            $s->logout();
            return $this->site->redirect('/login');
        }

        // redirect already-authenticated users to their account page
        if ($s->getUser()) {
            return $this->site->redirect('/account');
        }

        // authenticate the user and redirect to their account page
        if ($token = $this['token']) {
            $s->verify($token)->setUser('user@example.com');
            return $this->site->redirect('/account');
        }

        // show the login page
        return new View($this->site, 'view/login.phtml', ['csrf' => $s->getToken()]);
    }
}
