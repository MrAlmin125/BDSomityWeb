<?php
// =====================================================
// includes/functions.php - Core Helper Functions
// =====================================================

require_once __DIR__ . '/../config/database.php';

// =====================================================
// SESSION MANAGEMENT
// =====================================================
function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Lax');
        session_name(SESSION_NAME);
        session_start();
        
        // Regenerate session ID periodically
        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
        } elseif (time() - $_SESSION['created'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }
    }
}

// =====================================================
// CSRF PROTECTION
// =====================================================
function generateCSRF(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRF(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . generateCSRF() . '">';
}

// =====================================================
// AUTHENTICATION
// =====================================================
function isAdminLoggedIn(): bool {
    return isset($_SESSION['admin_id']) && isset($_SESSION['admin_role']);
}

function isMemberLoggedIn(): bool {
    return isset($_SESSION['member_id']);
}

function requireAdmin(): void {
    if (!isAdminLoggedIn()) {
        header('Location: /admin/login.php');
        exit;
    }
}

function requireMember(): void {
    if (!isMemberLoggedIn()) {
        header('Location: /member/login.php');
        exit;
    }
}

function getCurrentAdmin(): ?array {
    if (!isAdminLoggedIn()) return null;
    $id = (int)$_SESSION['admin_id'];
    $stmt = conn()->prepare("SELECT id, name, email, phone, role, profile_photo, is_active FROM users WHERE id = ? AND is_active = 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    // Update session with profile photo
    if ($user && isset($user['profile_photo'])) {
        $_SESSION['admin']['profile_photo'] = $user['profile_photo'];
    }
    
    return $user;
}

function getCurrentMember(): ?array {
    if (!isMemberLoggedIn()) return null;
    $id = (int)$_SESSION['member_id'];
    $stmt = conn()->prepare("SELECT id, member_id, name, phone, status FROM members WHERE id = ? AND status = 'active'");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// =====================================================
// SECURITY HELPERS
// =====================================================
function clean(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function sanitizePhone(string $phone): string {
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    // Convert 01XXXXXXXXX to 01XXXXXXXXX (BD format)
    if (strlen($phone) === 11 && substr($phone, 0, 2) === '01') {
        return $phone;
    }
    return $phone;
}

function validatePhone(string $phone): bool {
    return preg_match('/^01[3-9]\d{8}$/', $phone);
}

function validateAmount(string $amount): bool {
    return is_numeric($amount) && (float)$amount > 0;
}

function getClientIP(): string {
    $keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}

// =====================================================
// NUMBER & DATE FORMATTING (Bengali)
// =====================================================
function toBengaliNumber(string|int|float $number): string {
    $english = ['0','1','2','3','4','5','6','7','8','9'];
    $bengali = ['০','১','২','৩','৪','৫','৬','৭','৮','৯'];
    return str_replace($english, $bengali, (string)$number);
}

function formatCurrency(float $amount, bool $bengaliNum = false): string {
    $formatted = '৳' . number_format($amount, 2);
    return $bengaliNum ? toBengaliNumber($formatted) : $formatted;
}

function formatDate(string $date, string $format = 'd M, Y'): string {
    if (empty($date) || $date === '0000-00-00') return '-';
    return date($format, strtotime($date));
}

function bengaliMonth(int $month): string {
    $months = [
        1 => 'জানুয়ারি', 2 => 'ফেব্রুয়ারি', 3 => 'মার্চ',
        4 => 'এপ্রিল', 5 => 'মে', 6 => 'জুন',
        7 => 'জুলাই', 8 => 'আগস্ট', 9 => 'সেপ্টেম্বর',
        10 => 'অক্টোবর', 11 => 'নভেম্বর', 12 => 'ডিসেম্বর'
    ];
    return $months[$month] ?? '';
}

function timeAgo(string $datetime): string {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) return 'এইমাত্র';
    if ($diff < 3600) return round($diff/60) . ' মিনিট আগে';
    if ($diff < 86400) return round($diff/3600) . ' ঘণ্টা আগে';
    if ($diff < 2592000) return round($diff/86400) . ' দিন আগে';
    return date('d M Y', $time);
}

// =====================================================
// UNIQUE ID GENERATORS
// =====================================================
function generateMemberId(): string {
    $year = date('y');
    $stmt = conn()->prepare("SELECT COUNT(*) as cnt FROM members WHERE member_id LIKE ?");
    $like = "SM{$year}%";
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $seq = str_pad($row['cnt'] + 1, 4, '0', STR_PAD_LEFT);
    return "SM{$year}{$seq}";
}

function generateLoanNo(): string {
    $year = date('y');
    $month = date('m');
    $stmt = conn()->prepare("SELECT COUNT(*) as cnt FROM loans WHERE loan_no LIKE ?");
    $like = "LN{$year}{$month}%";
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $seq = str_pad($row['cnt'] + 1, 3, '0', STR_PAD_LEFT);
    return "LN{$year}{$month}{$seq}";
}

function generateReceiptNo(string $prefix = 'RC'): string {
    return $prefix . date('ymd') . rand(100, 999);
}

// =====================================================
// LOAN CALCULATION
// =====================================================
function calculateLoan(float $principal, float $annualRate, int $months): array {
    $monthlyRate = ($annualRate / 100) / 12;
    
    if ($monthlyRate == 0) {
        $emi = $principal / $months;
        $totalPayable = $principal;
        $totalInterest = 0;
    } else {
        $emi = $principal * $monthlyRate * pow(1 + $monthlyRate, $months) 
               / (pow(1 + $monthlyRate, $months) - 1);
        $totalPayable = $emi * $months;
        $totalInterest = $totalPayable - $principal;
    }
    
    // Generate schedule
    $schedule = [];
    $balance = $principal;
    for ($i = 1; $i <= $months; $i++) {
        $interestPart = $balance * $monthlyRate;
        $principalPart = $emi - $interestPart;
        $balance -= $principalPart;
        
        $schedule[] = [
            'installment_no' => $i,
            'principal_amount' => round($principalPart, 2),
            'interest_amount' => round($interestPart, 2),
            'total_due' => round($emi, 2),
            'balance' => max(0, round($balance, 2))
        ];
    }
    
    return [
        'monthly_installment' => round($emi, 2),
        'total_payable' => round($totalPayable, 2),
        'total_interest' => round($totalInterest, 2),
        'schedule' => $schedule
    ];
}

// =====================================================
// PROFIT DISTRIBUTION CALCULATION
// =====================================================
function calculateProfitDistribution(int $profitLossId): array {
    $pl = queryOne("SELECT * FROM profit_loss WHERE id = ?", 'i', [$profitLossId]);
    if (!$pl) return [];
    
    // Get all active members with their total deposits
    $members = queryAll(
        "SELECT m.id, m.name, m.member_id, COALESCE(SUM(d.amount),0) AS total_deposit
         FROM members m
         LEFT JOIN deposits d ON d.member_id = m.id AND YEAR(d.deposit_date) <= ?
         WHERE m.status = 'active'
         GROUP BY m.id",
        's', [substr($pl['fiscal_year'], -4)]
    );
    
    $totalDeposits = array_sum(array_column($members, 'total_deposit'));
    $distributable = (float)$pl['distributable_profit'];
    
    $distributions = [];
    foreach ($members as $member) {
        $ratio = $totalDeposits > 0 ? $member['total_deposit'] / $totalDeposits : 0;
        $distributions[] = [
            'member_id' => $member['id'],
            'member_name' => $member['name'],
            'member_deposit' => $member['total_deposit'],
            'distribution_ratio' => $ratio,
            'profit_amount' => round($distributable * $ratio, 2)
        ];
    }
    
    return $distributions;
}

// =====================================================
// DATABASE QUERY HELPERS
// =====================================================
function queryOne(string $sql, string $types = '', array $params = []): ?array {
    $stmt = conn()->prepare($sql);
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result ?: null;
}

function queryAll(string $sql, string $types = '', array $params = []): array {
    $stmt = conn()->prepare($sql);
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function queryCount(string $table, string $where = '1=1', string $types = '', array $params = []): int {
    $sql = "SELECT COUNT(*) as cnt FROM `{$table}` WHERE {$where}";
    $row = queryOne($sql, $types, $params);
    return (int)($row['cnt'] ?? 0);
}

// =====================================================
// ACTIVITY LOG
// =====================================================
function logActivity(string $action, string $description = '', string $userType = 'admin'): void {
    $userId = $userType === 'admin' ? ($_SESSION['admin_id'] ?? 0) : ($_SESSION['member_id'] ?? 0);
    $ip = getClientIP();
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    
    $stmt = conn()->prepare(
        "INSERT INTO activity_logs (user_type, user_id, action, description, ip_address, user_agent) 
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('sissss', $userType, $userId, $action, $description, $ip, $ua);
    $stmt->execute();
}

// =====================================================
// NOTIFICATION HELPERS
// =====================================================
function sendNotification(string $type, string $title, string $message, string $target = 'all', int $memberId = 0): void {
    $stmt = conn()->prepare(
        "INSERT INTO notifications (type, title, message, target, member_id, created_by) VALUES (?, ?, ?, ?, ?, ?)"
    );
    $createdBy = $_SESSION['admin_id'] ?? 0;
    $stmt->bind_param('ssssis', $type, $title, $message, $target, $memberId, $createdBy);
    $stmt->execute();
}

function getUnreadCount(string $userType = 'admin', int $userId = 0): int {
    if ($userType === 'admin') {
        return queryCount('notifications', "target = 'admin' AND is_read = 0");
    }
    return queryCount('notifications', "(target = 'all' OR (target = 'member' AND member_id = ?)) AND is_read = 0", 'i', [$userId]);
}

// =====================================================
// SETTINGS HELPER
// =====================================================
function getSetting(string $key, string $default = ''): string {
    static $cache = [];
    if (!isset($cache[$key])) {
        $row = queryOne("SELECT key_value FROM settings WHERE key_name = ?", 's', [$key]);
        $cache[$key] = $row['key_value'] ?? $default;
    }
    return $cache[$key];
}

// =====================================================
// FLASH MESSAGES
// =====================================================
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function showFlash(): string {
    $flash = getFlash();
    if (!$flash) return '';
    $icons = ['success' => 'check-circle', 'danger' => 'x-circle', 'warning' => 'alert-triangle', 'info' => 'info'];
    $icon = $icons[$flash['type']] ?? 'info';
    return "<div class='alert alert-{$flash['type']} alert-dismissible d-flex align-items-center fade show' role='alert'>
        <i data-feather='{$icon}' class='me-2 flex-shrink-0'></i>
        <div>{$flash['message']}</div>
        <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
    </div>";
}

// =====================================================
// DUE TRACKER
// =====================================================
function generateMonthlyDues(int $year, int $month): int {
    $amount = (float)getSetting('monthly_due_amount', '500');
    $members = queryAll("SELECT id FROM members WHERE status = 'active'");
    $count = 0;
    
    foreach ($members as $member) {
        // Check if already exists
        $exists = queryOne(
            "SELECT id FROM monthly_dues WHERE member_id = ? AND due_year = ? AND due_month = ?",
            'iii', [$member['id'], $year, $month]
        );
        if (!$exists) {
            $stmt = conn()->prepare(
                "INSERT INTO monthly_dues (member_id, due_year, due_month, amount) VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param('iiid', $member['id'], $year, $month, $amount);
            $stmt->execute();
            $count++;
        }
    }
    return $count;
}

function getMemberDueSummary(int $memberId): array {
    return queryOne(
        "SELECT 
            COUNT(*) as total_months,
            SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_months,
            SUM(CASE WHEN status != 'paid' THEN 1 ELSE 0 END) as unpaid_months,
            SUM(CASE WHEN status != 'paid' THEN (amount - paid_amount) ELSE 0 END) as total_due
         FROM monthly_dues WHERE member_id = ?",
        'i', [$memberId]
    ) ?? [];
}

// =====================================================
// PAGINATION HELPER
// =====================================================
function paginate(string $sql, string $types, array $params, int $page = 1, int $perPage = RECORDS_PER_PAGE): array {
    $countSql = "SELECT COUNT(*) as cnt FROM (" . $sql . ") as sub";
    $countRow = queryOne($countSql, $types, $params);
    $total = (int)($countRow['cnt'] ?? 0);
    
    $totalPages = max(1, ceil($total / $perPage));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;
    
    $data = queryAll($sql . " LIMIT {$perPage} OFFSET {$offset}", $types, $params);
    
    return [
        'data' => $data,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => $totalPages
    ];
}

function renderPagination(array $paged, string $baseUrl): string {
    if ($paged['total_pages'] <= 1) return '';
    
    $html = '<nav><ul class="pagination pagination-sm justify-content-center flex-wrap">';
    
    // Previous
    $prevDisabled = $paged['page'] <= 1 ? 'disabled' : '';
    $prevUrl = $baseUrl . '&page=' . ($paged['page'] - 1);
    $html .= "<li class='page-item {$prevDisabled}'><a class='page-link' href='{$prevUrl}'>«</a></li>";
    
    // Pages
    for ($i = max(1, $paged['page']-2); $i <= min($paged['total_pages'], $paged['page']+2); $i++) {
        $active = $i === $paged['page'] ? 'active' : '';
        $url = $baseUrl . '&page=' . $i;
        $html .= "<li class='page-item {$active}'><a class='page-link' href='{$url}'>{$i}</a></li>";
    }
    
    // Next
    $nextDisabled = $paged['page'] >= $paged['total_pages'] ? 'disabled' : '';
    $nextUrl = $baseUrl . '&page=' . ($paged['page'] + 1);
    $html .= "<li class='page-item {$nextDisabled}'><a class='page-link' href='{$nextUrl}'>»</a></li>";
    
    $html .= '</ul></nav>';
    $html .= "<p class='text-center text-muted small'>মোট {$paged['total']} রেকর্ড | পৃষ্ঠা {$paged['page']}/{$paged['total_pages']}</p>";
    return $html;
}

// =====================================================
// PROFILE PHOTO MANAGEMENT
// =====================================================

/**
 * Get admin profile photo HTML
 * @param array $admin Admin data array containing profile_photo and name
 * @param int $size Size in pixels
 * @return string HTML for profile photo
 */
function getAdminProfilePhoto(array $admin, int $size = 40): string {
    if (!empty($admin['profile_photo'])) {
        return '<img src="' . htmlspecialchars($admin['profile_photo']) . '" alt="Profile" style="width:' . $size . 'px;height:' . $size . 'px;border-radius:50%;object-fit:cover;">';
    }
    
    $initial = mb_substr($admin['name'] ?? 'A', 0, 1);
    $colors = ['#1B4F72', '#2E86C1', '#117A65', '#C0392B', '#8E44AD', '#D35400'];
    $color = $colors[abs(crc32($admin['name'] ?? 'A')) % count($colors)];
    $fontSize = $size * 0.5;
    
    return '<div style="width:' . $size . 'px;height:' . $size . 'px;border-radius:50%;background:' . $color . ';color:white;display:inline-flex;align-items:center;justify-content:center;font-size:' . $fontSize . 'px;font-weight:600;">' . htmlspecialchars($initial) . '</div>';
}

/**
 * Get admin profile photo URL or default avatar
 * @param array $admin Admin data array containing profile_photo
 * @return string Profile photo URL or default avatar path
 */
function getAdminProfilePhotoUrl(array $admin): string {
    if (!empty($admin['profile_photo'])) {
        return $admin['profile_photo'];
    }
    return '/assets/images/default-avatar.png';
}

/**
 * Upload and process profile photo
 * @param array $file $_FILES['profile_photo'] array
 * @param int $userId User ID
 * @param string $currentPhoto Current photo path (to delete old)
 * @return array ['success' => bool, 'message' => string, 'path' => string|null]
 */
function uploadProfilePhoto(array $file, int $userId, string $currentPhoto = ''): array {
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'ফাইলের আকার ২MB এর বেশি।',
            UPLOAD_ERR_FORM_SIZE => 'ফাইলের আকার ২MB এর বেশি।',
            UPLOAD_ERR_PARTIAL => 'ফাইল আংশিকভাবে আপলোড হয়েছে।',
            UPLOAD_ERR_NO_FILE => 'কোনো ফাইল আপলোড করা হয়নি।',
            UPLOAD_ERR_NO_TMP_DIR => 'টেম্পোরারি ফোল্ডার অনুপস্থিত।',
            UPLOAD_ERR_CANT_WRITE => 'ডিস্কে ফাইল লেখা সম্ভব হয়নি।',
            UPLOAD_ERR_EXTENSION => 'PHP এক্সটেনশন দ্বারা ফাইল আপলোড বন্ধ করা হয়েছে।',
        ];
        return ['success' => false, 'message' => $errorMessages[$file['error']] ?? 'অজানা ত্রুটি।', 'path' => null];
    }
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) {
        return ['success' => false, 'message' => 'ফাইল টাইপ চেক করা সম্ভব হয়নি।', 'path' => null];
    }
    
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        return ['success' => false, 'message' => 'শুধুমাত্র JPEG, PNG, GIF এবং WebP ছবি আপলোড করা যাবে।', 'path' => null];
    }
    
    // Validate file size (max 2MB)
    $maxSize = 2 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'ফাইলের আকার ২MB এর বেশি হতে পারে না।', 'path' => null];
    }
    
    // Create upload directory
    $uploadDir = __DIR__ . '/../uploads/admin/';
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            return ['success' => false, 'message' => 'আপলোড ডিরেক্টরি তৈরি করা সম্ভব হয়নি।', 'path' => null];
        }
    }
    
    // Generate unique filename
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'admin_' . $userId . '_' . time() . '.' . $extension;
    $filePath = $uploadDir . $filename;
    $relativePath = '/uploads/admin/' . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        return ['success' => false, 'message' => 'ফাইল আপলোড করতে ব্যর্থ হয়েছে।', 'path' => null];
    }
    
    // Delete old photo
    if (!empty($currentPhoto)) {
        $oldPath = __DIR__ . '/..' . $currentPhoto;
        if (file_exists($oldPath)) {
            @unlink($oldPath);
        }
    }
    
    return ['success' => true, 'message' => 'প্রোফাইল ছবি সফলভাবে আপলোড হয়েছে।', 'path' => $relativePath];
}

/**
 * Delete profile photo from server
 * @param string $photoPath Photo path to delete
 * @return bool Success status
 */
function deleteProfilePhoto(string $photoPath): bool {
    if (empty($photoPath)) {
        return false;
    }
    
    $fullPath = __DIR__ . '/..' . $photoPath;
    if (file_exists($fullPath)) {
        return @unlink($fullPath);
    }
    return false;
}

/**
 * Update user profile photo in database
 * @param int $userId User ID
 * @param string $photoPath Profile photo path
 * @return bool Success status
 */
function updateUserProfilePhoto(int $userId, string $photoPath): bool {
    try {
        $conn = conn();
        $stmt = $conn->prepare("UPDATE users SET profile_photo = ?, profile_photo_updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('si', $photoPath, $userId);
        $result = $stmt->execute();
        $stmt->close();
        
        if ($result && isset($_SESSION['admin_id']) && $_SESSION['admin_id'] == $userId) {
            $_SESSION['admin']['profile_photo'] = $photoPath;
        }
        
        return $result;
    } catch (Exception $e) {
        error_log('Update profile photo error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Remove user profile photo from database
 * @param int $userId User ID
 * @return bool Success status
 */
function removeUserProfilePhoto(int $userId): bool {
    try {
        $conn = conn();
        $stmt = $conn->prepare("UPDATE users SET profile_photo = NULL, profile_photo_updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('i', $userId);
        $result = $stmt->execute();
        $stmt->close();
        
        if ($result && isset($_SESSION['admin_id']) && $_SESSION['admin_id'] == $userId) {
            unset($_SESSION['admin']['profile_photo']);
        }
        
        return $result;
    } catch (Exception $e) {
        error_log('Remove profile photo error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get full URL for profile photo
 * @param string $path Relative path
 * @return string Full URL
 */
function getFullImageUrl(string $path): string {
    if (empty($path)) {
        return '';
    }
    
    if (strpos($path, 'http') === 0) {
        return $path;
    }
    
    if (strpos($path, '/') !== 0) {
        $path = '/' . $path;
    }
    
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    return $protocol . $host . $path;
}

// =====================================================
// BACKUP FUNCTIONS - ডেটাবেস ব্যাকআপ
// =====================================================

/**
 * সমস্ত টেবিলের নাম পাওয়া
 */
function getAllTables(): array {
    $conn = conn();
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    if ($result) {
        while ($row = $result->fetch_array()) {
            $tables[] = $row[0];
        }
    }
    return $tables;
}

/**
 * সম্পূর্ণ SQL ডাম্প তৈরি
 */
function generateSQLDump(array $tables): string {
    $conn = conn();
    $sql = "-- =============================================\n";
    $sql .= "-- DATABASE BACKUP\n";
    $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Database: " . DB_NAME . "\n";
    $sql .= "-- Host: " . DB_HOST . "\n";
    $sql .= "-- =============================================\n\n";
    
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n";
    $sql .= "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n";
    $sql .= "SET AUTOCOMMIT=0;\n";
    $sql .= "START TRANSACTION;\n\n";
    
    foreach ($tables as $table) {
        // টেবিল স্ট্রাকচার
        $result = $conn->query("SHOW CREATE TABLE `$table`");
        if ($result && $row = $result->fetch_assoc()) {
            $sql .= "-- Table structure for table `$table`\n";
            $sql .= "DROP TABLE IF EXISTS `$table`;\n";
            $sql .= $row['Create Table'] . ";\n\n";
        }
        
        // ডাটা এক্সপোর্ট
        $data = $conn->query("SELECT * FROM `$table`");
        if ($data && $data->num_rows > 0) {
            $sql .= "-- Dumping data for table `$table`\n";
            $sql .= "INSERT INTO `$table` VALUES\n";
            
            $rows = [];
            while ($rowData = $data->fetch_assoc()) {
                $values = array_map(function($val) use ($conn) {
                    if ($val === null) return 'NULL';
                    return "'" . $conn->real_escape_string($val) . "'";
                }, array_values($rowData));
                $rows[] = "(" . implode(",", $values) . ")";
            }
            $sql .= implode(",\n", $rows) . ";\n\n";
        }
    }
    
    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    $sql .= "COMMIT;\n";
    
    return $sql;
}

/**
 * ব্যাকআপ ফাইল তৈরি
 */
function createBackup(): array {
    try {
        $tables = getAllTables();
        
        if (empty($tables)) {
            return ['success' => false, 'message' => 'কোন টেবিল পাওয়া যায়নি!'];
        }
        
        $sqlDump = generateSQLDump($tables);
        
        $dateTime = date('Y-m-d_H-i-s');
        $filename = "backup_{$dateTime}.sql";
        $filepath = BACKUP_DIR . $filename;
        
        file_put_contents($filepath, $sqlDump);
        
        // ZIP তৈরি
        $zipFilename = "backup_{$dateTime}.zip";
        $zipPath = BACKUP_DIR . $zipFilename;
        
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
            $zip->addFile($filepath, $filename);
            $zip->close();
            
            // টেম্প SQL ফাইল ডিলিট
            @unlink($filepath);
            
            return [
                'success' => true,
                'zip_path' => $zipPath,
                'zip_filename' => $zipFilename,
                'file_size' => filesize($zipPath),
                'tables' => count($tables)
            ];
        } else {
            @unlink($filepath);
            return ['success' => false, 'message' => 'ZIP ফাইল তৈরি করতে পারেনি!'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * ইমেইলে ব্যাকআপ পাঠান (mail() ফাংশন দিয়ে)
 */
function sendBackupByEmail(string $to, string $subject, string $body, string $attachmentPath, string $attachmentName): bool {
    $from = "noreply@" . $_SERVER['HTTP_HOST'];
    $boundary = md5(time());
    
    $headers = "From: " . $from . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";
    
    $message = "--$boundary\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $message .= $body . "\r\n\r\n";
    
    if (file_exists($attachmentPath)) {
        $fileContent = file_get_contents($attachmentPath);
        $fileContent = chunk_split(base64_encode($fileContent));
        
        $message .= "--$boundary\r\n";
        $message .= "Content-Type: application/zip; name=\"$attachmentName\"\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n";
        $message .= "Content-Disposition: attachment; filename=\"$attachmentName\"\r\n\r\n";
        $message .= $fileContent . "\r\n";
    }
    
    $message .= "--$boundary--";
    
    return mail($to, $subject, $message, $headers);
}

/**
 * শেষ ব্যাকআপের সময় পাওয়া
 */
function getLastBackupTime(): string {
    $files = glob(BACKUP_DIR . "backup_*.zip");
    if (!empty($files)) {
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        return date("d M Y, h:i A", filemtime($files[0]));
    }
    return "কোন ব্যাকআপ নেওয়া হয়নি";
}

/**
 * পুরনো ব্যাকআপ ফাইল পরিষ্কার
 */
function cleanOldBackups(int $days = 30): int {
    $deleted = 0;
    $files = glob(BACKUP_DIR . "backup_*.zip");
    $now = time();
    
    foreach ($files as $file) {
        if ($now - filemtime($file) > ($days * 86400)) {
            @unlink($file);
            $deleted++;
        }
    }
    return $deleted;
}

// =====================================================
// BACKUP TRACKING FUNCTIONS - ব্যাকআপ ট্র্যাকিং
// =====================================================

/**
 * ব্যাকআপ লগ সংরক্ষণ করুন
 */
function logBackupActivity($action, $filename = null, $fileSize = null, $status = 'success', $errorMessage = null) {
    try {
        $adminId = $_SESSION['admin_id'] ?? 0;
        $adminName = $_SESSION['admin_name'] ?? 'অজানা';
        $ip = getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $conn = conn();
        $stmt = $conn->prepare("
            INSERT INTO backup_logs 
            (admin_id, admin_name, action, filename, file_size, ip_address, user_agent, status, error_message, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->bind_param(
            'issssssss', 
            $adminId, 
            $adminName, 
            $action, 
            $filename, 
            $fileSize, 
            $ip, 
            $userAgent, 
            $status, 
            $errorMessage
        );
        
        $stmt->execute();
        $stmt->close();
        
        return true;
    } catch (Exception $e) {
        // লগ না থাকলেও ইগনোর - কিন্তু error_log এ রাখুন
        error_log("Backup Log Error: " . $e->getMessage());
        return false;
    }
}

/**
 * ব্যাকআপ লগ থেকে ডাটা পাওয়া
 */
function getBackupLogs($limit = 50, $offset = 0) {
    try {
        $conn = conn();
        $stmt = $conn->prepare("
            SELECT * FROM backup_logs 
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param('ii', $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * ব্যাকআপ লগ কাউন্ট
 */
function getBackupLogCount() {
    try {
        $conn = conn();
        $result = $conn->query("SELECT COUNT(*) as count FROM backup_logs");
        $row = $result->fetch_assoc();
        return $row['count'] ?? 0;
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * আজকের ব্যাকআপ কাউন্ট
 */
function getTodayBackupCount() {
    try {
        $conn = conn();
        $result = $conn->query("
            SELECT COUNT(*) as count FROM backup_logs 
            WHERE DATE(created_at) = CURDATE()
        ");
        $row = $result->fetch_assoc();
        return $row['count'] ?? 0;
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * সর্বশেষ ব্যাকআপের বিবরণ
 */
function getLastBackupDetails() {
    try {
        $conn = conn();
        $result = $conn->query("
            SELECT * FROM backup_logs 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        return $result->fetch_assoc();
    } catch (Exception $e) {
        return null;
    }
}

/**
 * সপ্তাহের ব্যাকআপ কাউন্ট
 */
function getWeeklyBackupCount() {
    try {
        $conn = conn();
        $result = $conn->query("
            SELECT COUNT(*) as count FROM backup_logs 
            WHERE YEARWEEK(created_at) = YEARWEEK(CURDATE())
        ");
        $row = $result->fetch_assoc();
        return $row['count'] ?? 0;
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * মাসের ব্যাকআপ কাউন্ট
 */
function getMonthlyBackupCount() {
    try {
        $conn = conn();
        $result = $conn->query("
            SELECT COUNT(*) as count FROM backup_logs 
            WHERE MONTH(created_at) = MONTH(CURDATE()) 
            AND YEAR(created_at) = YEAR(CURDATE())
        ");
        $row = $result->fetch_assoc();
        return $row['count'] ?? 0;
    } catch (Exception $e) {
        return 0;
    }
}
?>