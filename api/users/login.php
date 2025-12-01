<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// parse input (supports JSON or form-data)
$input = [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?: [];
} else {
    $input = $_POST;
}

$identifier = isset($input['identifier']) ? trim($input['identifier']) : null; // username or email
$password = isset($input['password']) ? $input['password'] : null;

if (!$identifier || !$password) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'identifier and password required']);
    exit;
}

try {
    // Find user by email or username
    $stmt = $pdo->prepare('SELECT id, username, email, password_hash, is_active FROM users WHERE email = :ident OR username = :ident LIMIT 1');
    $stmt->execute([':ident' => $identifier]);
    $user = $stmt->fetch();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        exit;
    }
    if (!$user['is_active']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'User is inactive']);
        exit;
    }
    if (!password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        exit;
    }

    // Create session token
    $token = bin2hex(random_bytes(32));
    $expiresAt = (new DateTime('+30 days'))->format('Y-m-d H:i:s');
    $ins = $pdo->prepare('INSERT INTO user_sessions (user_id, token, user_agent, ip, created_at, expires_at, revoked) VALUES (:user_id, :token, :user_agent, :ip, NOW(), :expires_at, 0)');
    $ins->execute([
        ':user_id' => $user['id'],
        ':token' => $token,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ':expires_at' => $expiresAt,
    ]);

    // Return user data minus password_hash
    $responseUser = [
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
    ];

    echo json_encode(['success' => true, 'token' => $token, 'expires_at' => $expiresAt, 'user' => $responseUser]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()]);
    exit;
}
?>