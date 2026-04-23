<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-Session-Token');

define('DB_HOST', getenv('MYSQL_HOST') ?: 'sql104.infinityfree.com');
define('DB_USER', getenv('MYSQL_USER') ?: 'if0_41690502');
define('DB_PASS', getenv('MYSQL_PASSWORD') ?: '0mCgqPXFE6s');
define('DB_NAME', getenv('MYSQL_DATABASE') ?: 'if0_41690502_CCC');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'DB connection failed']);
    exit;
}

function getSessionUser($pdo) {
    $sessionId = $_SERVER['HTTP_X_SESSION_TOKEN'] ?? $_COOKIE['ccc_session'] ?? '';
    if (empty($sessionId)) return false;
    $stmt = $pdo->prepare("SELECT u.* FROM sessions s JOIN users u ON s.user_id = u.id WHERE s.session_id = ? LIMIT 1");
    $stmt->execute([$sessionId]);
    return $stmt->fetch();
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$user = getSessionUser($pdo);
if (!$user) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
$isAdmin   = $user['role'] === 'admin';
$isOfficer = $user['role'] === 'officer';

if (!$isAdmin && !$isOfficer && $method !== 'GET') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($method === 'GET' && $action === 'list') {
    $stmt = $pdo->query("SELECT a.*, c.name as club_name FROM announcements a LEFT JOIN clubs c ON a.club_id = c.id ORDER BY a.created_at DESC");
    echo json_encode(['success' => true, 'announcements' => $stmt->fetchAll()]);

} elseif ($method === 'POST' && $action === 'create') {
    $data = json_decode(file_get_contents('php://input'), true);

    $clubId = null;
    if (!empty($data['club'])) {
        $cs = $pdo->prepare("SELECT id FROM clubs WHERE name = ? LIMIT 1");
        $cs->execute([$data['club']]);
        $row = $cs->fetch();
        if ($row) $clubId = $row['id'];
    }

    if ($isOfficer && $clubId) {
        $own = $pdo->prepare("SELECT id FROM clubs WHERE id = ? AND officer_id = ? LIMIT 1");
        $own->execute([$clubId, $user['id']]);
        if (!$own->fetch()) {
            echo json_encode(['success' => false, 'error' => 'You can only post announcements for your own club']);
            exit;
        }
    }

    $stmt = $pdo->prepare("INSERT INTO announcements (title, club_id, status, body, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([
        $data['title'] ?? '',
        $clubId,
        $data['status'] ?? 'Published',
        $data['body'] ?? '',
    ]);
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);

} elseif ($method === 'POST' && $action === 'delete') {
    $data = json_decode(file_get_contents('php://input'), true);
    if ($isOfficer) {
        $own = $pdo->prepare("SELECT a.id FROM announcements a JOIN clubs c ON a.club_id = c.id WHERE a.id = ? AND c.officer_id = ? LIMIT 1");
        $own->execute([$data['id'], $user['id']]);
        if (!$own->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
    }
    $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
    $stmt->execute([$data['id']]);
    echo json_encode(['success' => true]);

} elseif ($method === 'POST' && $action === 'update') {
    $data = json_decode(file_get_contents('php://input'), true);
    $clubId = null;
    if (!empty($data['club'])) {
        $cs = $pdo->prepare("SELECT id FROM clubs WHERE name = ? LIMIT 1");
        $cs->execute([$data['club']]);
        $row = $cs->fetch();
        if ($row) $clubId = $row['id'];
    }
    if ($isOfficer) {
        $own = $pdo->prepare("SELECT a.id FROM announcements a JOIN clubs c ON a.club_id = c.id WHERE a.id = ? AND c.officer_id = ? LIMIT 1");
        $own->execute([$data['id'], $user['id']]);
        if (!$own->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
    }
    $stmt = $pdo->prepare("UPDATE announcements SET title=?, club_id=?, status=?, body=? WHERE id=?");
    $stmt->execute([
        $data['title'] ?? '',
        $clubId,
        $data['status'] ?? 'Published',
        $data['body'] ?? '',
        $data['id'],
    ]);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
