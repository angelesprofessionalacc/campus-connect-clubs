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

$pdo->exec("CREATE TABLE IF NOT EXISTS club_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    user_id INT NOT NULL,
    joined_at DATETIME DEFAULT NOW(),
    UNIQUE KEY unique_membership (club_id, user_id)
)");

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$user = getSessionUser($pdo);
if (!$user) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
$isAdmin = $user['role'] === 'admin';

if (!$isAdmin && $method !== 'GET') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($method === 'GET' && $action === 'list') {
    $stmt = $pdo->query("SELECT c.*, COUNT(m.id) as member_count FROM clubs c LEFT JOIN club_members m ON c.id = m.club_id GROUP BY c.id ORDER BY c.name ASC");
    $clubs = $stmt->fetchAll();
    $memberStmt = $pdo->query("SELECT club_id, user_id FROM club_members");
    $memberRows = $memberStmt->fetchAll();
    $membersMap = [];
    foreach ($memberRows as $row) {
        $membersMap[$row['club_id']][] = ['user_id' => (int)$row['user_id']];
    }
    foreach ($clubs as &$club) {
        $club['members'] = $membersMap[$club['id']] ?? [];
    }
    echo json_encode(['success' => true, 'clubs' => $clubs]);

} elseif ($method === 'GET' && $action === 'list_members') {
    if (!$isAdmin) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    $clubId = $_GET['club_id'] ?? null;
    if (!$clubId) {
        echo json_encode(['success' => false, 'error' => 'Missing club_id']);
        exit;
    }
    $stmt = $pdo->prepare("SELECT u.id, u.first_name, u.last_name, u.student_id, u.role, m.joined_at FROM club_members m JOIN users u ON m.user_id = u.id WHERE m.club_id = ? ORDER BY m.joined_at ASC");
    $stmt->execute([$clubId]);
    echo json_encode(['success' => true, 'members' => $stmt->fetchAll()]);

} elseif ($method === 'GET' && $action === 'my_memberships') {
    $stmt = $pdo->prepare("SELECT c.id, c.name, c.category, c.color, c.description, c.status, m.joined_at FROM club_members m JOIN clubs c ON m.club_id = c.id WHERE m.user_id = ? ORDER BY m.joined_at DESC");
    $stmt->execute([$user['id']]);
    echo json_encode(['success' => true, 'memberships' => $stmt->fetchAll()]);

} elseif ($method === 'GET' && $action === 'list_my_clubs') {
    if ($user['role'] !== 'officer' && $user['role'] !== 'admin') {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    $stmt = $pdo->prepare("SELECT c.*, COUNT(m.id) as member_count FROM clubs c LEFT JOIN club_members m ON c.id = m.club_id WHERE c.officer_id = ? GROUP BY c.id ORDER BY c.name ASC");
    $stmt->execute([$user['id']]);
    echo json_encode(['success' => true, 'clubs' => $stmt->fetchAll()]);

} elseif ($method === 'POST' && $action === 'create') {
    $data = json_decode(file_get_contents('php://input'), true);
    $stmt = $pdo->prepare("INSERT INTO clubs (name, category, status, adviser, email, description, day, time, location, color, year, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([
        $data['name'] ?? '',
        $data['category'] ?? '',
        $data['status'] ?? 'Active',
        $data['adviser'] ?? '',
        $data['email'] ?? '',
        $data['description'] ?? '',
        $data['day'] ?? '',
        $data['time'] ?? '',
        $data['location'] ?? '',
        $data['color'] ?? '#3b82f6',
        $data['year'] ?? date('Y'),
    ]);
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);

} elseif ($method === 'POST' && $action === 'delete') {
    $data = json_decode(file_get_contents('php://input'), true);
    $stmt = $pdo->prepare("DELETE FROM clubs WHERE id = ?");
    $stmt->execute([$data['id']]);
    echo json_encode(['success' => true]);

} elseif ($method === 'POST' && $action === 'update') {
    $data = json_decode(file_get_contents('php://input'), true);
    $stmt = $pdo->prepare("UPDATE clubs SET name=?, category=?, status=?, adviser=?, email=?, description=?, location=? WHERE id=?");
    $stmt->execute([
        $data['name'] ?? '',
        $data['category'] ?? '',
        $data['status'] ?? 'Active',
        $data['adviser'] ?? '',
        $data['email'] ?? '',
        $data['description'] ?? '',
        $data['location'] ?? '',
        $data['id'],
    ]);
    echo json_encode(['success' => true]);

} else {
    echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
