<?php
define('DB_HOST', 'sql104.infinityfree.com');
define('DB_USER', 'if0_41690502');
define('DB_PASS', '0mCgqPXFE6s');
define('DB_NAME', 'if0_41690502_CCC');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die($e->getMessage());
}
?>
