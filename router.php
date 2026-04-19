<?php

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$routes = [
    '/api/login'            => ['file' => 'auth.php',   'action' => 'login'],
    '/api/login_student'    => ['file' => 'auth.php',   'action' => 'login_student'],
    '/api/logout'           => ['file' => 'auth.php',   'action' => 'logout'],
    '/api/validate'         => ['file' => 'auth.php',   'action' => 'validate_session'],
    '/api/register_admin'   => ['file' => 'auth.php',   'action' => 'register_admin'],
    '/api/register_student' => ['file' => 'auth.php',   'action' => 'register_student'],
    '/api/clubs'            => ['file' => 'clubs.php',  'action' => null],
    '/api/events'           => ['file' => 'events.php', 'action' => null],
    '/api/users'            => ['file' => 'users.php',  'action' => null],
];

if (isset($routes[$uri])) {
    $route = $routes[$uri];
    if ($route['action']) {
        $_GET['action'] = $route['action'];
    }
    require __DIR__ . '/' . $route['file'];
    exit;
}


if ($uri !== '/' && is_file(__DIR__ . $uri)) {
    return false;
}


require __DIR__ . '/index.html';
