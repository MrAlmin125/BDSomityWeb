<?php
// =====================================================
// api/login.php - অ্যাডমিন লগইন (মোবাইল অ্যাপের জন্য)
// POST { email, password } → { success, token, admin }
// =====================================================
require_once __DIR__ . '/config.php';

// শুধু POST অনুমোদিত
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('শুধুমাত্র POST অনুমোদিত।', 405);
}

$body = getRequestBody();
$email = trim($body['email'] ?? '');
$password = $body['password'] ?? '';

// ভ্যালিডেশন
if (empty($email) || empty($password)) {
    jsonError('ইমেইল ও পাসওয়ার্ড দিন।', 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonError('সঠিক ইমেইল ঠিকানা দিন।', 400);
}

try {
    // অ্যাডমিন খোঁজ
    $admin = queryOne(
        "SELECT id, name, email, phone, password, role, profile_photo, is_active 
         FROM users 
         WHERE email = ? AND is_active = 1 
         LIMIT 1",
        's',
        [$email]
    );
    
    if (!$admin) {
        // লগইন ব্যর্থ লগ
        logActivity('login_failed', "API login failed: {$email}");
        jsonError('ইমেইল বা পাসওয়ার্ড ভুল।', 401);
    }
    
    // পাসওয়ার্ড যাচাই
    if (!password_verify($password, $admin['password'])) {
        logActivity('login_failed', "API login failed (wrong password): {$email}");
        jsonError('ইমেইল বা পাসওয়ার্ড ভুল।', 401);
    }
    
    // টোকেন তৈরি
    $token = createToken((int)$admin['id'], 30);
    
    // last_login আপডেট
    $conn = conn();
    $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->bind_param('i', $admin['id']);
    $stmt->execute();
    $stmt->close();
    
    // লগ
    logActivity('login', 'Admin logged in via API (mobile)');
    
    jsonResponse(true, [
        'token' => $token,
        'admin' => formatAdmin($admin),
        'expires_in_days' => 30,
    ], 'সফলভাবে লগইন হয়েছে।');
    
} catch (Exception $e) {
    error_log('API Login Error: ' . $e->getMessage());
    jsonError('সিস্টেমে সমস্যা হয়েছে। আবার চেষ্টা করুন।', 500);
}