<?php

// cli-server file access
if (preg_match('#^/static/?#', $_SERVER['REQUEST_URI'])) {
    return false;
}

include_once '../vendor/autoload.php';

use Helix\Site;
use Helix\Site\HttpError;
use Helix\Site\Test\AccountController;
use Helix\Site\Test\LoginController;
use Helix\Site\View;

$site = new Site;

// 200
$site->get('/', fn() => new View('view/index.phtml'));

// 200
$site->get('/empty', fn() => '');

// 200
$site->get('/echo', function () {
    echo 'echo';
});

// 200
$site->get('/headers', function () use ($site) {
    $site->getResponse()['Content-Type'] = 'text/plain';
    var_export($site->getRequest()->getHeaders());
});

// 200, 206, 404, 416
$site->get('#^/file/(?<name>[^./].*)$#', function (array $path, Site $site) {
    $site->getResponse()->setCacheTtl(10)->file_exit("static/{$path['name']}");
});

// 302
$site->get('/redirect', fn() => $site->getResponse()->redirect_exit('/'));

// 200, 302, 403
$site->get('#^/(?<action>login|logout)(/(?<token>\w+))?$#', LoginController::class);

// 200 or 302
$site->get('/account', AccountController::class);

// 500
$site->get('/error', function () {
    1 / 0; // ErrorException
});

// custom error page with arbitrary code
$site->get('#^/(?<code>[0-9]+)$#', function (array $path) {
    throw new HttpError($path['code']);
});
