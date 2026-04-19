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
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'DB connection failed']);
    exit;
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function storeSession($pdo, $sessionId, $userId) {
    try {
        $del = $pdo->prepare("DELETE FROM sessions WHERE user_id = ?");
        $del->execute([$userId]);
        $stmt = $pdo->prepare("INSERT INTO sessions (session_id, user_id, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$sessionId, $userId]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Session store failed']);
        exit;
    }
}

function validateSession($pdo, $sessionId) {
    if (empty($sessionId)) return false;
    $stmt = $pdo->prepare("SELECT user_id FROM sessions WHERE session_id = ? LIMIT 1");
    $stmt->execute([$sessionId]);
    $row = $stmt->fetch();
    return $row ? $row['user_id'] : false;
}

function getUserById($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, student_id, role, created_at FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if ($action === 'login') {
        $email    = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
            exit;
        }

        $sessionId = bin2hex(random_bytes(32));
        storeSession($pdo, $sessionId, $user['id']);
        setcookie('ccc_session', $sessionId, time() + 7*24*60*60, '/', '', false, true);

        echo json_encode([
            'success'    => true,
            'session_id' => $sessionId,
            'user'       => [
                'id'         => $user['id'],
                'first_name' => $user['first_name'],
                'last_name'  => $user['last_name'],
                'email'      => $user['email'],
                'student_id' => $user['student_id'],
                'role'       => $user['role'],
                'created_at' => $user['created_at'],
            ]
        ]);

    } elseif ($action === 'login_student') {
        $studentId = $data['student_id'] ?? '';
        $password  = $data['password'] ?? '';

        $stmt = $pdo->prepare("SELECT * FROM users WHERE student_id = ? AND role = 'student' LIMIT 1");
        $stmt->execute([$studentId]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
            exit;
        }

        $sessionId = bin2hex(random_bytes(32));
        storeSession($pdo, $sessionId, $user['id']);
        setcookie('ccc_session', $sessionId, time() + 7*24*60*60, '/', '', false, true);

        echo json_encode([
            'success'    => true,
            'session_id' => $sessionId,
            'user'       => [
                'id'         => $user['id'],
                'first_name' => $user['first_name'],
                'last_name'  => $user['last_name'],
                'email'      => $user['email'],
                'student_id' => $user['student_id'],
                'role'       => $user['role'],
                'created_at' => $user['created_at'],
            ]
        ]);

    } elseif ($action === 'register_admin') {
        $email      = $data['email'] ?? '';
        $firstName  = $data['first_name'] ?? '';
        $lastName   = $data['last_name'] ?? '';
        $password   = $data['password'] ?? '';

        $check = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $check->execute([$email]);
        if ($check->fetchColumn()) {
            echo json_encode(['success' => false, 'error' => 'Account already exists']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO users (email, first_name, last_name, password, role, created_at) VALUES (?, ?, ?, ?, 'admin', NOW())");
        $stmt->execute([$email, $firstName, $lastName, hashPassword($password)]);
        echo json_encode(['success' => true]);

    } elseif ($action === 'register_student') {
        $email     = $data['email'] ?? '';
        $firstName = $data['first_name'] ?? '';
        $lastName  = $data['last_name'] ?? '';
        $password  = $data['password'] ?? '';

        $check = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $check->execute([$email]);
        if ($check->fetchColumn()) {
            echo json_encode(['success' => false, 'error' => 'Account already exists']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO users (email, first_name, last_name, password, role, created_at) VALUES (?, ?, ?, ?, 'student', NOW())");
        $stmt->execute([$email, $firstName, $lastName, hashPassword($password)]);
        echo json_encode(['success' => true]);

    } elseif ($action === 'logout') {
        $sessionId = $_SERVER['HTTP_X_SESSION_TOKEN'] ?? $_COOKIE['ccc_session'] ?? '';
        if (!empty($sessionId)) {
            $stmt = $pdo->prepare("DELETE FROM sessions WHERE session_id = ?");
            $stmt->execute([$sessionId]);
        }
        setcookie('ccc_session', '', time() - 3600, '/');
        echo json_encode(['success' => true]);

    } else {
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }

} elseif ($method === 'GET') {
    if ($action === 'validate_session') {
        $sessionId = $_SERVER['HTTP_X_SESSION_TOKEN'] ?? $_COOKIE['ccc_session'] ?? '';
        $userId = validateSession($pdo, $sessionId);
        if ($userId) {
            $user = getUserById($pdo, $userId);
            if ($user) {
                echo json_encode(['success' => true, 'user' => $user]);
                exit;
            }
        }
        echo json_encode(['success' => false, 'error' => 'Invalid session']);
        exit;
    }
    echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
?>
