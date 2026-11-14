<?php
// =====================================================
// admin/upload_profile_photo.php - Profile Photo Upload
// =====================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/functions.php';
startSecureSession();

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    header('Location: /admin/login.php');
    exit;
}

$admin = getCurrentAdmin();
if (!$admin) {
    header('Location: /admin/logout.php');
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/profile.php');
    exit;
}

// Check CSRF token
if (!isset($_POST['csrf_token']) || !verifyCSRF($_POST['csrf_token'])) {
    setFlash('error', 'সিকিউরিটি ত্রুটি!');
    header('Location: /admin/profile.php');
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] === UPLOAD_ERR_NO_FILE) {
    setFlash('error', 'কোনো ফাইল নির্বাচন করা হয়নি।');
    header('Location: /admin/profile.php');
    exit;
}

$file = $_FILES['profile_photo'];

// Validate file type
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
if ($finfo === false) {
    setFlash('error', 'ফাইল টাইপ চেক করা সম্ভব হয়নি।');
    header('Location: /admin/profile.php');
    exit;
}

$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    setFlash('error', 'শুধুমাত্র JPEG, PNG, GIF এবং WebP ছবি আপলোড করা যাবে।');
    header('Location: /admin/profile.php');
    exit;
}

// Validate file size (max 2MB)
$maxSize = 2 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    setFlash('error', 'ফাইলের আকার ২MB এর বেশি হতে পারে না।');
    header('Location: /admin/profile.php');
    exit;
}

// Create upload directory if it doesn't exist
$uploadDir = __DIR__ . '/../uploads/admin/';
if (!file_exists($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        setFlash('error', 'আপলোড ডিরেক্টরি তৈরি করা সম্ভব হয়নি।');
        header('Location: /admin/profile.php');
        exit;
    }
}

// Generate unique filename
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$filename = 'admin_' . $admin['id'] . '_' . time() . '.' . $extension;
$filePath = $uploadDir . $filename;
$relativePath = '/uploads/admin/' . $filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $filePath)) {
    setFlash('error', 'ফাইল আপলোড করতে ব্যর্থ হয়েছে।');
    header('Location: /admin/profile.php');
    exit;
}

// Delete old profile photo if exists
if (!empty($admin['profile_photo'])) {
    $oldFilePath = __DIR__ . '/..' . $admin['profile_photo'];
    if (file_exists($oldFilePath)) {
        @unlink($oldFilePath);
    }
}

// Update database
try {
    $conn = conn();
    $stmt = $conn->prepare("UPDATE users SET profile_photo = ?, profile_photo_updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('si', $relativePath, $admin['id']);
    
    if ($stmt->execute()) {
        // Update session
        $_SESSION['admin']['profile_photo'] = $relativePath;
        setFlash('success', 'প্রোফাইল ছবি সফলভাবে আপলোড হয়েছে।');
    } else {
        // Delete uploaded file if database update fails
        @unlink($filePath);
        setFlash('error', 'ডাটাবেস আপডেট করতে ব্যর্থ হয়েছে।');
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    // Delete uploaded file on error
    @unlink($filePath);
    setFlash('error', 'ত্রুটি: ' . $e->getMessage());
}

header('Location: /admin/profile.php');
exit;
?>