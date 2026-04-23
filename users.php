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
if (!$user || $user['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($method === 'GET' && $action === 'list') {
    $stmt = $pdo->query("SELECT id, first_name, last_name, email, student_id, role, 'Active' as status, created_at FROM users ORDER BY created_at DESC");
    echo json_encode(['success' => true, 'users' => $stmt->fetchAll()]);

} elseif ($method === 'POST' && $action === 'create_student') {
    $data = json_decode(file_get_contents('php://input'), true);
    $studentId = $data['student_id'] ?? '';
    $password  = $data['password'] ?? '';

    if (empty($studentId) || empty($password)) {
        echo json_encode(['success' => false, 'error' => 'Missing fields']);
        exit;
    }

    $check = $pdo->prepare("SELECT id FROM users WHERE student_id = ? LIMIT 1");
    $check->execute([$studentId]);
    if ($check->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => 'Student ID already exists']);
        exit;
    }

    $firstName = $data['first_name'] ?? '';
    $lastName  = $data['last_name'] ?? '';
    $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, student_id, password, role, created_at) VALUES (?, ?, ?, ?, 'student', NOW())");
    $stmt->execute([$firstName, $lastName, $studentId, password_hash($password, PASSWORD_DEFAULT)]);
    echo json_encode(['success' => true]);

} elseif ($method === 'POST' && $action === 'change_role') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id   = $data['id'] ?? '';
    $role = $data['role'] ?? '';

    if (!in_array($role, ['student', 'officer', 'admin'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid role']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
    $stmt->execute([$role, $id]);
    echo json_encode(['success' => true]);

} elseif ($method === 'POST' && $action === 'delete') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? '';
    $check = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
    $check->execute([$id]);
    $row = $check->fetch();
    if (!$row || $row['role'] === 'admin') {
        echo json_encode(['success' => false, 'error' => 'Cannot delete this user']);
        exit;
    }
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);

} elseif ($method === 'GET' && $action === 'lookup') {
    $studentId = $_GET['student_id'] ?? '';
    if (empty($studentId)) {
        echo json_encode(['success' => false, 'error' => 'Missing student_id']);
        exit;
    }
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, student_id, role FROM users WHERE student_id = ? LIMIT 1");
    $stmt->execute([$studentId]);
    $user = $stmt->fetch();
    if ($user) {
        echo json_encode(['success' => true, 'user' => $user]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Student not found']);
    }

} else {
    echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
