<?php
// =====================================================
// admin/delete_profile_photo.php - Delete Profile Photo (FULLY FIXED)
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

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !verifyCSRF($_POST['csrf_token'])) {
    setFlash('error', 'সিকিউরিটি ত্রুটি!');
    header('Location: /admin/profile.php');
    exit;
}

// Check if user has a profile photo
if (empty($admin['profile_photo'])) {
    setFlash('error', 'কোনো প্রোফাইল ছবি নেই।');
    header('Location: /admin/profile.php');
    exit;
}

$photoPath = $admin['profile_photo'];
$fullPath = __DIR__ . '/..' . $photoPath;

// Delete file from server
$fileDeleted = false;
if (file_exists($fullPath)) {
    $fileDeleted = @unlink($fullPath);
} else {
    // File doesn't exist, consider it deleted
    $fileDeleted = true;
}

// Remove from database
$dbUpdated = removeUserProfilePhoto($admin['id']);

// Set flash message based on results
if ($dbUpdated) {
    // Update session
    if (isset($_SESSION['admin'])) {
        unset($_SESSION['admin']['profile_photo']);
    }
    setFlash('success', 'প্রোফাইল ছবি মুছে ফেলা হয়েছে।');
} else {
    setFlash('error', 'ছবি মুছতে ব্যর্থ হয়েছে। ডাটাবেস আপডেট করা যায়নি।');
}

// Redirect back to profile page
header('Location: /admin/profile.php');
exit;
?>