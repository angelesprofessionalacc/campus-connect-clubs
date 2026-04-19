<?php

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$authRoutes = [
    '/api/login'            => 'login',
    '/api/login_student'    => 'login_student',
    '/api/logout'           => 'logout',
    '/api/validate'         => 'validate_session',
    '/api/register_admin'   => 'register_admin',
    '/api/register_student' => 'register_student',
];

$resourceRoutes = [
    '/api/clubs'  => 'clubs.php',
    '/api/events' => 'events.php',
    '/api/users'  => 'users.php',
];

if (isset($authRoutes[$uri])) {
    $_GET['action'] = $authRoutes[$uri];
    require __DIR__ . '/auth.php';
    exit;
}

if (isset($resourceRoutes[$uri])) {
    require __DIR__ . '/' . $resourceRoutes[$uri];
    exit;
}

if ($uri !== '/' && is_file(__DIR__ . $uri)) {
    return false;
}

require __DIR__ . '/index.html';
