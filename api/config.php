<?php
// =====================================================
// api/config.php - API Configuration & Helpers
// =====================================================

// CORS - মোবাইল অ্যাপ থেকে কল করার জন্য
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

// OPTIONS preflight রিকোয়েস্ট হ্যান্ডেল
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ত্রুটি লগ (প্রোডাকশনে display_errors বন্ধ)
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/functions.php';

// =====================================================
// JSON RESPONSE HELPER
// =====================================================
function jsonResponse(bool $success, $data = null, string $message = '', int $code = 200): void {
    http_response_code($code);
    $response = ['success' => $success];
    if ($message !== '') $response['message'] = $message;
    if ($data !== null) $response['data'] = $data;
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function jsonError(string $message, int $code = 400): void {
    jsonResponse(false, null, $message, $code);
}

// =====================================================
// REQUEST BODY পার্স
// =====================================================
function getRequestBody(): array {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
    
    return $_POST;
}

// =====================================================
// AUTHORIZATION HEADER পড়া
// =====================================================
function getBearerToken(): ?string {
    // Apache-এ Authorization হেডার সরাসরি পাওয়া যায় না
    $headers = null;
    
    if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER['Authorization']);
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
    } elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        $requestHeaders = array_combine(
            array_map('ucwords', array_keys($requestHeaders)),
            array_values($requestHeaders)
        );
        if (isset($requestHeaders['Authorization'])) {
            $headers = trim($requestHeaders['Authorization']);
        }
    }
    
    if (!empty($headers) && preg_match('/Bearer\s(\S+)/i', $headers, $matches)) {
        return $matches[1];
    }
    
    return null;
}

// =====================================================
// টোকেন যাচাই - টোকেনের সাথে অ্যাডমিন ডেটা রিটার্ন
// =====================================================
function verifyToken(?string $token = null): ?array {
    if ($token === null) {
        $token = getBearerToken();
    }
    
    if (empty($token)) {
        return null;
    }
    
    $row = queryOne(
        "SELECT t.id as token_id, t.user_id, t.expires_at,
                u.id, u.name, u.email, u.phone, u.role, u.profile_photo, u.is_active
         FROM api_tokens t
         JOIN users u ON u.id = t.user_id
         WHERE t.token = ? AND t.expires_at > NOW() AND u.is_active = 1
         LIMIT 1",
        's',
        [$token]
    );
    
    if (!$row) {
        return null;
    }
    
    // last_used আপডেট
    $conn = conn();
    $stmt = $conn->prepare("UPDATE api_tokens SET last_used = NOW() WHERE id = ?");
    $stmt->bind_param('i', $row['token_id']);
    $stmt->execute();
    $stmt->close();
    
    return $row;
}

// =====================================================
// টোকেন বাধ্যতামূলক - না থাকলে 401
// =====================================================
function requireToken(): array {
    $admin = verifyToken();
    if (!$admin) {
        jsonError('অনুমোদন প্রয়োজন। আবার লগইন করুন।', 401);
    }
    return $admin;
}

// =====================================================
// নতুন টোকেন তৈরি
// =====================================================
function createToken(int $userId, int $daysValid = 30): string {
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime("+{$daysValid} days"));
    
    // পুরনো টোকেন মুছুন (ঐচ্ছিক - একাধিক ডিভাইসের জন্য রাখতে চাইলে বাদ দিন)
    $conn = conn();
    $stmt = $conn->prepare("DELETE FROM api_tokens WHERE user_id = ? AND expires_at < NOW()");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();
    
    $stmt = $conn->prepare("INSERT INTO api_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->bind_param('iss', $userId, $token, $expiresAt);
    if (!$stmt->execute()) {
        jsonError('টোকেন তৈরি করতে ব্যর্থ হয়েছে।', 500);
    }
    $stmt->close();
    
    return $token;
}

// =====================================================
// অ্যাডমিন ফরম্যাট (অ্যাপের জন্য পরিষ্কার ডেটা)
// =====================================================
function formatAdmin(array $admin): array {
    $photo = $admin['profile_photo'] ?? '';
    if (!empty($photo) && strpos($photo, 'http') !== 0) {
        $photo = APP_URL . '/' . ltrim($photo, '/');
    }
    
    return [
        'id' => (int)$admin['id'],
        'name' => $admin['name'] ?? '',
        'email' => $admin['email'] ?? '',
        'phone' => $admin['phone'] ?? '',
        'role' => $admin['role'] ?? 'admin',
        'profile_photo' => $photo,
    ];
}