<?php

class AuthManager {
    protected $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function registerAdmin($email, $firstName, $lastName, $password) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $checkStmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $checkStmt->execute([$email]);
        if ($checkStmt->fetchColumn()) {
            return [ 'success' => false, 'error' => 'Account already exists' ];
        }
        $stmt = $this->pdo->prepare("INSERT INTO users (email, first_name, last_name, password, role, created_at) VALUES (?, ?, ?, ?, 'admin', NOW())");
        $result = $stmt->execute([$email, $firstName, $lastName, $hashed]);
        if ($result) {
            return [ 'success' => true ];
        }
        return [ 'success' => false, 'error' => 'Registration failed' ];
    }

    public function registerUser($email, $firstName, $lastName, $password, $role = 'student') {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $checkStmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ? OR student_id = ?");
        $checkStmt->execute([$email, $email]);
        if ($checkStmt->fetchColumn()) {
            return [ 'success' => false, 'error' => 'Account already exists' ];
        }
        $stmt = $this->pdo->prepare("INSERT INTO users (email, first_name, last_name, password, role, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $result = $stmt->execute([$email, $firstName, $lastName, $hashed, $role]);
        if ($result) {
            return [ 'success' => true ];
        }
        return [ 'success' => false, 'error' => 'Registration failed' ];
    }

    public function loginUser($email, $password) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $stmt2 = $this->pdo->prepare("SELECT * FROM users WHERE student_id = ? LIMIT 1");
            $stmt2->execute([$email]);
            $user = $stmt2->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                return ['success' => false, 'error' => 'Invalid credentials'];
            }
        }

        if (!isset($user['password']) || !password_verify($password, $user['password'])) {
            return ['success' => false, 'error' => 'Invalid credentials'];
        }

        $sessionId = bin2hex(random_bytes(32));
        $this->storeSession($sessionId, $user['id']);
        return [
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'email' => $user['email'],
                'student_id' => $user['student_id'],
                'role' => $user['role'],
                'created_at' => $user['created_at']
            ],
            'session_id' => $sessionId
        ];
    }
public function loginStudent($studentId, $password) {
    $stmt = $this->pdo->prepare("SELECT * FROM users WHERE student_id = ? AND (role = 'student' OR role = 'officer') LIMIT 1");
    $stmt->execute([$studentId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        error_log("No user found with student_id: $studentId");
        return ['success' => false, 'error' => 'Invalid credentials'];
    }

    if (!isset($user['password']) || empty($user['password'])) {
        error_log("User found, but no password set for student_id: $studentId");
        return ['success' => false, 'error' => 'Invalid credentials'];
    }
    
    $result = password_verify($password, $user['password']);
    error_log("Password Verify for $studentId: " . ($result ? 'Passed' : 'Failed'));

    if (!$result) {
        return ['success' => false, 'error' => 'Invalid credentials'];
    }

    $sessionId = bin2hex(random_bytes(32));
    $this->storeSession($sessionId, $user['id']);
    return [
        'success' => true,
        'user' => [
            'id' => $user['id'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'email' => $user['email'],
            'student_id' => $user['student_id'],
            'role' => $user['role'],
            'created_at' => $user['created_at']
        ],
        'session_id' => $sessionId
    ];
}

public function storeSession($sessionId, $userId) {
    $del = $this->pdo->prepare("DELETE FROM sessions WHERE user_id = ?");
    $del->execute([$userId]);
    $stmt = $this->pdo->prepare("INSERT INTO sessions (session_id, user_id, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$sessionId, $userId]);
}
    $stmt = $this->pdo->prepare("INSERT INTO sessions (session_id, user_id, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$sessionId, $userId]);
}
    public function validateSession($sessionId) {
        $stmt = $this->pdo->prepare("SELECT user_id FROM sessions WHERE session_id = ? LIMIT 1");
        $stmt->execute([$sessionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row['user_id'];
        }
        return false;
    }

    public function getUserById($userId) {
        $stmt = $this->pdo->prepare("SELECT id, first_name, last_name, email, student_id, role, created_at FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
