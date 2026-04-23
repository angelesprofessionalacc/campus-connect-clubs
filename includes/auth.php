public function loginStudent($studentId, $password) {
    $stmt = $this->pdo->prepare("SELECT * FROM users WHERE student_id = ? AND (role = 'student' OR role = 'officer') LIMIT 1");
    $stmt->execute([$studentId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return ['success' => false, 'error' => 'Invalid credentials'];
    }
    if (!isset($user['password']) || !password_verify($password, $user['password'])) {
        error_log('Login failed for student_id: ' . $studentId . ', password_verify result: ' . (password_verify($password, $user['password']) ? 'true' : 'false'));
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
