<?php

// cli-server file access
if (preg_match('#^/static/?#', $_SERVER['REQUEST_URI'])) {
    return false;
}

include_once '../vendor/autoload.php';

use Helix\Site;
use Helix\Site\HttpError;
use Helix\Site\Response\JSON;
use Helix\Site\Response\View;
use Helix\Site\Test\AccountController;
use Helix\Site\Test\LoginController;

$site = new Site;

// 200
$site->get('/', fn() => new View($site, 'view/index.phtml'));

// 200
$site->get('/empty', fn() => '');

// 200
$site->get('/echo', function () {
    echo 'echo';
});

$site->get('/json', function ($p, Site $s) {
    return new JSON($s, 'foo');
});

// 200
$site->get('/headers', function () use ($site) {
    var_export($site->request->headers);
});

// 200, 206, 404, 416
$site->get('#^/file/(?<name>[^./].*)$#', function (array $path, Site $site) {
    return $site->redirect("/static/{$path['name']}")->setTtl(10);
});

// 302
$site->get('/redirect', fn() => $site->redirect('/'));

// 200, 302, 403
$site->get('#^/(?<action>login|logout)(/(?<token>\w+))?$#', LoginController::class);

// 200 or 302
$site->get('/account', AccountController::class);

// 500
$site->get('/error', function () {
    $zero = 0;
    echo 1 / $zero; // ErrorException
});

// 405 for GET
$site->put('/put', fn() => 'ok');

// custom error page with arbitrary code
$site->get('#^/(?<code>[0-9]+)$#', function (array $path) {
    throw new HttpError($path['code']);
});
