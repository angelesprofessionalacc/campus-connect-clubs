<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-Session-Token');

define('DB_HOST', getenv('MYSQL_HOST')     ?: 'sql104.infinityfree.com');
define('DB_USER', getenv('MYSQL_USER')     ?: 'if0_41690502');
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

$pdo->exec("CREATE TABLE IF NOT EXISTS club_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    student_id VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    reason TEXT,
    leadership VARCHAR(50),
    status ENUM('Pending','Accepted','Rejected') DEFAULT 'Pending',
    created_at DATETIME DEFAULT NOW()
)");

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

if ($method === 'POST' && $action === 'submit') {
    $data    = json_decode(file_get_contents('php://input'), true);
    $clubId  = $data['club_id'] ?? null;
    if (!$clubId) {
        echo json_encode(['success' => false, 'error' => 'Missing club']);
        exit;
    }
    $stmt = $pdo->prepare("INSERT INTO club_applications (club_id, full_name, student_id, email, phone, reason, leadership, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())");
    $stmt->execute([
        $clubId,
        $data['full_name']   ?? '',
        $data['student_id']  ?? '',
        $data['email']       ?? '',
        $data['phone']       ?? '',
        $data['reason']      ?? '',
        $data['leadership']  ?? '',
    ]);
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);

} elseif ($method === 'GET' && $action === 'list_my') {
    $sid = $user['student_id'] ?? '';
    $email = $user['email'] ?? '';
    if (!$sid && !$email) {
        echo json_encode(['success' => true, 'applications' => []]);
        exit;
    }
    $stmt = $pdo->prepare("SELECT ca.*, c.name AS club_name FROM club_applications ca LEFT JOIN clubs c ON ca.club_id = c.id WHERE ca.student_id = ? OR ca.email = ? ORDER BY ca.created_at DESC");
    $stmt->execute([$sid ?: '', $email ?: '']);
    echo json_encode(['success' => true, 'applications' => $stmt->fetchAll()]);

} elseif ($method === 'GET' && $action === 'list_all') {
    if (!$isAdmin) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    $stmt = $pdo->query("SELECT ca.*, c.name AS club_name FROM club_applications ca LEFT JOIN clubs c ON ca.club_id = c.id ORDER BY ca.created_at DESC");
    echo json_encode(['success' => true, 'applications' => $stmt->fetchAll()]);

} elseif ($method === 'GET' && $action === 'list_for_club') {
    if (!$isAdmin && !$isOfficer) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    $clubId = $_GET['club_id'] ?? null;
    if (!$clubId) {
        echo json_encode(['success' => false, 'error' => 'Missing club_id']);
        exit;
    }
    if ($isOfficer) {
        $check = $pdo->prepare("SELECT id FROM clubs WHERE id = ? AND officer_id = ? LIMIT 1");
        $check->execute([$clubId, $user['id']]);
        if (!$check->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
    }
    $stmt = $pdo->prepare("SELECT * FROM club_applications WHERE club_id = ? ORDER BY created_at DESC");
    $stmt->execute([$clubId]);
    echo json_encode(['success' => true, 'applications' => $stmt->fetchAll()]);

} elseif ($method === 'POST' && $action === 'update_status') {
    if (!$isAdmin && !$isOfficer) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    $data   = json_decode(file_get_contents('php://input'), true);
    $appId  = $data['id']     ?? null;
    $status = $data['status'] ?? null;
    if (!$appId || !in_array($status, ['Pending', 'Accepted', 'Rejected'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
        exit;
    }
    if ($isOfficer) {
        $check = $pdo->prepare("SELECT ca.id FROM club_applications ca JOIN clubs c ON ca.club_id = c.id WHERE ca.id = ? AND c.officer_id = ? LIMIT 1");
        $check->execute([$appId, $user['id']]);
        if (!$check->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
    }
    $stmt = $pdo->prepare("UPDATE club_applications SET status = ? WHERE id = ?");
    $stmt->execute([$status, $appId]);
    echo json_encode(['success' => true]);

} else {
    echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
