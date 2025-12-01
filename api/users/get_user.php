<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

// Get token from Authorization header or token parameter
$token = null;
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $m)) {
    $token = $m[1];
}
if (!$token && isset($_GET['token'])) {
    $token = $_GET['token'];
}
if (!$token && isset($_POST['token'])) {
    $token = $_POST['token'];
}

if (!$token) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token required']);
    exit;
}

try {
    // Validate session
    $stmt = $pdo->prepare('SELECT s.user_id, u.username, u.email, u.preferred_language, u.country_id, u.city_id, u.is_verified, u.is_active, u.created_at FROM user_sessions s JOIN users u ON s.user_id = u.id WHERE s.token = :token AND s.revoked = 0 AND (s.expires_at IS NULL OR s.expires_at > NOW()) LIMIT 1');
    $stmt->execute([':token' => $token]);
    $row = $stmt->fetch();
    if (!$row) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid or expired token']);
        exit;
    }
    $user_id = $row['user_id'];

    // Get documents
    $docStmt = $pdo->prepare('SELECT id, filename, storage_key, content_type, file_size, status, uploaded_at FROM documents WHERE owner_type = "user" AND owner_id = :uid ORDER BY uploaded_at DESC');
    $docStmt->execute([':uid' => $user_id]);
    $documents = $docStmt->fetchAll();

    // Optionally get addresses
    $addrStmt = $pdo->prepare('SELECT id, label, full_name, phone, country_id, city_id, state, postal_code, street_address, is_default, created_at FROM addresses WHERE user_id = :uid');
    $addrStmt->execute([':uid' => $user_id]);
    $addresses = $addrStmt->fetchAll();

    $user = [
        'id' => (int)$user_id,
        'username' => $row['username'],
        'email' => $row['email'],
        'preferred_language' => $row['preferred_language'],
        'country_id' => $row['country_id'],
        'city_id' => $row['city_id'],
        'is_verified' => (bool)$row['is_verified'],
        'is_active' => (bool)$row['is_active'],
        'created_at' => $row['created_at'],
    ];

    echo json_encode(['success' => true, 'user' => $user, 'documents' => $documents, 'addresses' => $addresses]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()]);
    exit;
}
?>