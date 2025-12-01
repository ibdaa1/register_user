<?php
// register_user.php - مُحدَّث: يدعم CORS و OPTIONS و يعطي رسالة مفيدة على GET
header('Content-Type: application/json; charset=utf-8');

// --- CORS handling ---
$allowedOrigins = ['https://mzmz.rf.gd', 'http://mzmz.rf.gd']; // ضع هنا origins المسموح لها أو اترك فارغا ويسمح بـ *
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if ($origin && in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    // أثناء التطوير يمكنك فك السطر التالي لتمكين كلّ المواقع:
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // preflight response
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'CORS preflight']);
    exit;
}

// Simple helpful text for GET so you don't just see "Method not allowed" when browsing
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'success' => false,
        'message' => 'This endpoint accepts POST only. Use POST with fields: username, email, password. For file uploads use multipart/form-data with documents[].',
        'examples' => [
            'curl_post' => 'curl -X POST https://your-host/api/users/register_user.php -d "username=testuser" -d "email=test@example.com" -d "password=secret"',
            'curl_post_files' => 'curl -X POST https://your-host/api/users/register_user.php -F "username=testuser" -F "email=test@example.com" -F "password=secret" -F "documents[]=@/path/to/file.pdf"',
        ],
    ]);
    exit;
}

// From here only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Load DB
require_once __DIR__ . '/../config/db.php';

// Read incoming form-data fields (supports multipart/form-data)
$username = isset($_POST['username']) ? trim($_POST['username']) : null;
$email = isset($_POST['email']) ? trim($_POST['email']) : null;
$password = isset($_POST['password']) ? $_POST['password'] : null;
$preferred_language = isset($_POST['preferred_language']) ? trim($_POST['preferred_language']) : null;
$country_id = isset($_POST['country_id']) ? intval($_POST['country_id']) : null;

if (!$username || !$email || !$password) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'username, email and password are required']);
    exit;
}

try {
    // Check unique username/email
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1');
    $stmt->execute([':username' => $username, ':email' => $email]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Username or email already exists']);
        exit;
    }

    // Insert user
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $insert = $pdo->prepare('INSERT INTO users (username, email, password_hash, preferred_language, country_id, is_active, created_at, updated_at) VALUES (:username, :email, :password_hash, :preferred_language, :country_id, 1, NOW(), NOW())');
    $insert->execute([
        ':username' => $username,
        ':email' => $email,
        ':password_hash' => $password_hash,
        ':preferred_language' => $preferred_language,
        ':country_id' => $country_id,
    ]);
    $user_id = $pdo->lastInsertId();

    // Ensure user upload dir exists
    // UPLOAD_BASE_DIR is defined in db.php file we added earlier
    if (!defined('UPLOAD_BASE_DIR')) {
        // fallback: compute relative uploads path
        define('UPLOAD_BASE_DIR', realpath(__DIR__ . '/../../uploads') . '/users');
    }
    $userUploadDir = UPLOAD_BASE_DIR . '/' . $user_id;
    if (!is_dir($userUploadDir)) {
        @mkdir($userUploadDir, 0755, true);
    }

    // Handle uploaded documents (input name "documents[]")
    $uploadedDocs = [];
    if (!empty($_FILES['documents'])) {
        // normalize array
        $files = [];
        foreach ($_FILES['documents'] as $k => $list) {
            foreach ($list as $i => $val) {
                $files[$i][$k] = $val;
            }
        }
        foreach ($files as $file) {
            if ($file['error'] !== UPLOAD_ERR_OK) continue;
            $origName = basename($file['name']);
            $ext = pathinfo($origName, PATHINFO_EXTENSION);
            $newName = time() . '_' . bin2hex(random_bytes(6)) . ($ext ? '.' . $ext : '');
            $destPath = $userUploadDir . '/' . $newName;
            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                $content_type = $file['type'] ?: mime_content_type($destPath);
                $file_size = filesize($destPath);
                $checksum = hash_file('sha256', $destPath);
                $storage_key = 'uploads/users/' . $user_id . '/' . $newName;

                // Insert document record
                $insDoc = $pdo->prepare('INSERT INTO documents (owner_type, owner_id, filename, storage_key, content_type, file_size, checksum, status, uploaded_by, uploaded_at) VALUES (:owner_type, :owner_id, :filename, :storage_key, :content_type, :file_size, :checksum, :status, :uploaded_by, NOW())');
                $insDoc->execute([
                    ':owner_type' => 'user',
                    ':owner_id' => $user_id,
                    ':filename' => $origName,
                    ':storage_key' => $storage_key,
                    ':content_type' => $content_type,
                    ':file_size' => $file_size,
                    ':checksum' => $checksum,
                    ':status' => 'pending',
                    ':uploaded_by' => $user_id,
                ]);
                $uploadedDocs[] = [
                    'original' => $origName,
                    'storage_key' => $storage_key,
                ];
            }
        }
    }

    echo json_encode(['success' => true, 'message' => 'User registered', 'user_id' => (int)$user_id, 'uploaded_documents' => $uploadedDocs]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()]);
    exit;
}
?>