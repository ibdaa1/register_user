<?php
// Simple debug endpoint to inspect incoming request method, headers, body, POST and FILES.
// Use this to confirm what the client actually sends to the server.
header('Content-Type: application/json; charset=utf-8');

$headers = [];
// getallheaders may not exist in some SAPIs, so guard it
if (function_exists('getallheaders')) {
    $headers = getallheaders();
} else {
    foreach ($_SERVER as $name => $value) {
        if (substr($name, 0, 5) == 'HTTP_') {
            $h = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
            $headers[$h] = $value;
        }
    }
}

$raw = @file_get_contents('php://input');
$response = [
    'method' => $_SERVER['REQUEST_METHOD'] ?? '',
    'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
    'headers' => $headers,
    'get' => $_GET,
    'post' => $_POST,
    'raw_body' => $raw,
    'files' => [],
];

if (!empty($_FILES)) {
    foreach ($_FILES as $k => $fileinfo) {
        $response['files'][$k] = $fileinfo;
    }
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;
?>