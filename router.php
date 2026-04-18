<?php

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$routes = [
    '/api/login'            => '/auth.php?action=login',
    '/api/login_student'    => '/auth.php?action=login_student',
    '/api/logout'           => '/auth.php?action=logout',
    '/api/validate'         => '/auth.php?action=validate_session',
    '/api/register_admin'   => '/auth.php?action=register_admin',
    '/api/register_student' => '/auth.php?action=register_student',
];

if (isset($routes[$uri])) {
    parse_str(parse_url($routes[$uri], PHP_URL_QUERY), $params);
    $_GET = array_merge($_GET, $params);
    require __DIR__ . '/auth.php';
    exit;
}

if (php_sapi_name() === 'cli-server') {
    if (is_file(__DIR__ . $uri)) {
        return false;
    }
}

http_response_code(404);
echo '404 Not Found';
