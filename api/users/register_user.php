<?php
// htdocs/api/register_user.php

// استدعاء ملف الاتصال بقاعدة البيانات
require_once __DIR__ . '/../config/db.php';

// التأكد من وصول البيانات عبر POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $conn = connectDB();

    // استلام البيانات من النموذج
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $preferred_language = $_POST['preferred_language'] ?? 'en';
    $country_id = $_POST['country_id'] ?? null;
    $city_id = $_POST['city_id'] ?? null;

    // التحقق من البيانات الأساسية
    if (!$username || !$email || !$password) {
        die(json_encode([
            'status' => 'error',
            'message' => 'الحقول الأساسية مطلوبة: اسم المستخدم، البريد، كلمة المرور'
        ]));
    }

    // تشفير كلمة المرور
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // تحضير الاستعلام لإدخال المستخدم
    $stmt = $conn->prepare("INSERT INTO users 
        (username, email, password_hash, preferred_language, country_id, city_id, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");

    $stmt->bind_param(
        "ssssii",
        $username,
        $email,
        $password_hash,
        $preferred_language,
        $country_id,
        $city_id
    );

    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'message' => 'تم تسجيل المستخدم بنجاح',
            'user_id' => $stmt->insert_id
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'حدث خطأ أثناء التسجيل',
            'error' => $stmt->error
        ]);
    }

    $stmt->close();
    $conn->close();

} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'الطريقة غير مدعومة. استخدم POST'
    ]);
}
