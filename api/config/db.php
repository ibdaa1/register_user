<?php
// htdocs/api/config/db.php
// ملف الاتصال بقاعدة البيانات

$servername = "sql311.infinityfree.com";
$username   = "if0_39652926";
$password   = "Mohd28332";
$dbname     = "if0_39652926_qooqz";

// إنشاء الاتصال
$conn = new mysqli($servername, $username, $password, $dbname);

// ضبط المنطقة الزمنية
$conn->query("SET time_zone = '+00:00'");

// التحقق من الاتصال
if ($conn->connect_error) {
    $error_msg = $conn->connect_errno . ': ' . $conn->connect_error;
    error_log("فشل الاتصال بقاعدة البيانات: " . $error_msg);

    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        die(json_encode([
            'status' => 'error',
            'message' => 'فشل الاتصال بقاعدة البيانات / Database connection failed',
            'error_details' => $error_msg
        ]));
    } else {
        die("فشل الاتصال بقاعدة البيانات: " . $error_msg);
    }
}

// ضبط الترميز على UTF8 لدعم العربية والإنجليزية
$conn->set_charset("utf8mb4");

// دالة لإرجاع الاتصال لأي ملف PHP آخر
function connectDB() {
    global $conn;
    return $conn;
}
?>
