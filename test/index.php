<?php

// cli-server file access
if (preg_match('#^/static/?#', $_SERVER['REQUEST_URI'])) {
    return false;
}

require_once '../vendor/autoload.php';

use Helix\Site;
use Helix\Site\Error;
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
    $site->getResponse()->file("static/{$path['name']}");
});

// 302
$site->get('/redirect', function () use ($site) {
    $site->getResponse()->redirect('/');
});

// 200, 302, 403
$site->get('#^/login(/(?<token>\w+))?$#', function (array $path, Site $site) {
    $auth = $site->getAuth();
    $response = $site->getResponse();
    if ($auth->getUser()) {
        return $response->redirect('/account'); // exits
    }
    if (empty($path['token'])) {
        return "<a href=\"/login/{$auth->getToken()}\">login</a>";
    }
    $auth->verify($path['token'])->setUser('user@example.com');
    return $response->redirect('/account'); // exits
});

// 200 or 302
$site->get('/account', function ($path, Site $site) {
    if (!$user = $site->getAuth()->getUser()) {
        return $site->getResponse()->redirect('/login'); // exits
    }
    return "Welcome, {$user}! <a href=\"/logout\">logout</a>";
});

// 302
$site->get('/logout', function ($path, Site $site) {
    $site->getAuth()->logout();
    $site->getResponse()->redirect('/login') and exit;
});

// 500
$site->get('/error', function () {
    $x = $x; // E_NOTICE in error.log
    1 / 0; // E_WARNING throws ErrorException
});

// arbitrary code
$site->get('#^/(?<code>[0-9]+)$#', function (array $path) {
    throw new Error($path['code']);
});
