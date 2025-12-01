<?php
// htdocs/api/test_db.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


require_once 'config/db.php';

$conn = connectDB();

// اختبار الاتصال
if ($conn->connect_error) {
    die("فشل الاتصال: " . $conn->connect_error);
}

// استعلام صحيح لجلب الوقت الحالي
$sql = "SELECT NOW() AS `current_time`"; // لاحظ backticks حول اسم العمود
$result = $conn->query($sql);

if ($result) {
    $row = $result->fetch_assoc();
    echo "تم الاتصال بقاعدة البيانات بنجاح! الوقت الحالي: " . $row['current_time'];
} else {
    echo "حدث خطأ في الاستعلام: " . $conn->error;
}
?>
