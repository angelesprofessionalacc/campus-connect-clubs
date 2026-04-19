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

$filePath = __DIR__ . $uri;

if ($uri !== '/' && file_exists($filePath) && !is_dir($filePath)) {
    $ext = pathinfo($filePath, PATHINFO_EXTENSION);
    $mimeTypes = [
        'html' => 'text/html',
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
        'json' => 'application/json',
        'woff' => 'font/woff',
        'woff2'=> 'font/woff2',
    ];
    if (isset($mimeTypes[$ext])) {
        header('Content-Type: ' . $mimeTypes[$ext]);
    }
    readfile($filePath);
    exit;
}

require __DIR__ . '/index.html';
