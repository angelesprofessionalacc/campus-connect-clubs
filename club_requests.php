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

$pdo->exec("CREATE TABLE IF NOT EXISTS club_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    officer_id INT NOT NULL,
    club_name VARCHAR(255) NOT NULL,
    category VARCHAR(100),
    description TEXT,
    adviser VARCHAR(255),
    email VARCHAR(255),
    location VARCHAR(255),
    status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
    admin_note VARCHAR(512),
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
$isStudent = $user['role'] === 'student';

if ($method === 'POST' && $action === 'submit') {
    if (!$isOfficer && !$isStudent && !$isAdmin) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data['club_name'])) {
        echo json_encode(['success' => false, 'error' => 'Club name is required']);
        exit;
    }

    $existing = $pdo->prepare("SELECT id, status FROM club_requests WHERE officer_id = ? AND status IN ('Pending','Approved') LIMIT 1");
    $existing->execute([$user['id']]);
    $dup = $existing->fetch();
    if ($dup) {
        $msg = $dup['status'] === 'Approved'
            ? 'Your club request has already been approved.'
            : 'You already have a pending club request. Wait for it to be reviewed before submitting another.';
        echo json_encode(['success' => false, 'error' => $msg]);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO club_requests (officer_id, club_name, category, description, adviser, email, location, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())");
    $stmt->execute([
        $user['id'],
        $data['club_name']   ?? '',
        $data['category']    ?? '',
        $data['description'] ?? '',
        $data['adviser']     ?? '',
        $data['email']       ?? '',
        $data['location']    ?? '',
    ]);
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);

} elseif ($method === 'GET' && $action === 'my_requests') {
    $stmt = $pdo->prepare("SELECT * FROM club_requests WHERE officer_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user['id']]);
    echo json_encode(['success' => true, 'requests' => $stmt->fetchAll()]);

} elseif ($method === 'GET' && $action === 'list') {
    if (!$isAdmin) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    $stmt = $pdo->query("SELECT cr.*, u.first_name, u.last_name, u.student_id FROM club_requests cr JOIN users u ON cr.officer_id = u.id ORDER BY cr.created_at DESC");
    echo json_encode(['success' => true, 'requests' => $stmt->fetchAll()]);

} elseif ($method === 'POST' && $action === 'review') {
    if (!$isAdmin) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    $data   = json_decode(file_get_contents('php://input'), true);
    $reqId  = $data['id']         ?? null;
    $status = $data['status']     ?? null;
    $note   = $data['admin_note'] ?? '';

    if (!$reqId || !in_array($status, ['Approved', 'Rejected'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
        exit;
    }

    $check = $pdo->prepare("SELECT * FROM club_requests WHERE id = ? LIMIT 1");
    $check->execute([$reqId]);
    $row = $check->fetch();
    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'Request not found']);
        exit;
    }

    if ($row['status'] !== 'Pending') {
        echo json_encode(['success' => false, 'error' => 'This request has already been reviewed.']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE club_requests SET status = ?, admin_note = ? WHERE id = ?");
        $stmt->execute([$status, $note, $reqId]);

        if ($status === 'Approved') {
            $ins = $pdo->prepare("INSERT INTO clubs (name, category, description, adviser, email, location, status, officer_id, color, year, created_at) VALUES (?, ?, ?, ?, ?, ?, 'Active', ?, '#3b82f6', ?, NOW())");
            $ins->execute([
                $row['club_name'],
                $row['category'],
                $row['description'],
                $row['adviser'],
                $row['email'],
                $row['location'],
                $row['officer_id'],
                date('Y'),
            ]);

            $promote = $pdo->prepare("UPDATE users SET role = 'officer' WHERE id = ? AND role = 'student'");
            $promote->execute([$row['officer_id']]);
        }

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Transaction failed: ' . $e->getMessage()]);
    }

} else {
    echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
