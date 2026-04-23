<?php

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

error_log('[ROUTER] uri=' . $uri . ' method=' . $_SERVER['REQUEST_METHOD']);

if (preg_match('/\.php$/', $uri)) {
    error_log('[ROUTER] passing to PHP natively: ' . $uri);
    return false;
}

$authRoutes = [
    '/api/login'            => 'login',
    '/api/login_student'    => 'login_student',
    '/api/logout'           => 'logout',
    '/api/validate'         => 'validate_session',
    '/api/register_admin'   => 'register_admin',
    '/api/register_student' => 'register_student',
];

$resourceRoutes = [
    '/api/clubs'          => 'clubs.php',
    '/api/events'         => 'events.php',
    '/api/users'          => 'users.php',
    '/api/activities'     => 'activities.php',
    '/api/announcements'  => 'announcements.php',
    '/api/applications'   => 'applications.php',
    '/api/club_requests'  => 'club_requests.php',
];

if (isset($authRoutes[$uri])) {
    error_log('[ROUTER] auth route matched: ' . $uri . ' -> action=' . $authRoutes[$uri]);
    $_GET['action'] = $authRoutes[$uri];
    require __DIR__ . '/auth.php';
    exit;
}

if (isset($resourceRoutes[$uri])) {
    error_log('[ROUTER] resource route matched: ' . $uri);
    require __DIR__ . '/' . $resourceRoutes[$uri];
    exit;
}

$filePath = __DIR__ . $uri;

if ($uri !== '/' && file_exists($filePath) && !is_dir($filePath)) {
    error_log('[ROUTER] serving static file: ' . $filePath);
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

error_log('[ROUTER] no match, serving index.html for uri=' . $uri);
require __DIR__ . '/index.html';
