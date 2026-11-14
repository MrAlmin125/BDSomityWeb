<?php
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
requireAdmin();
$pageTitle = 'সিস্টেম সেটিং';

// ============================================
// LOGO UPLOAD HANDLER
// ============================================
function uploadLogo($file) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 2 * 1024 * 1024; // 2MB
    
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'লোগো আপলোড করতে ব্যর্থ হয়েছে।'];
    }
    
    $fileType = mime_content_type($file['tmp_name']);
    if (!in_array($fileType, $allowedTypes)) {
        return ['success' => false, 'message' => 'শুধুমাত্র JPG, PNG, GIF, WEBP ফাইল অনুমোদিত।'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'লোগো সাইজ 2MB এর বেশি হতে পারে না।'];
    }
    
    $uploadDir = __DIR__ . '/../images/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'logo_' . time() . '.' . $extension;
    $filePath = $uploadDir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        return ['success' => false, 'message' => 'ফাইল আপলোড করতে ব্যর্থ হয়েছে।'];
    }
    
    $oldLogo = getSetting('logo_url', '');
    if (!empty($oldLogo)) {
        $oldLogoPath = __DIR__ . '/../images/' . basename($oldLogo);
        if (file_exists($oldLogoPath) && is_file($oldLogoPath)) {
            unlink($oldLogoPath);
        }
    }
    
    $logoUrl = '/images/' . $filename;
    $stmt = conn()->prepare("UPDATE settings SET key_value = ? WHERE key_name = 'logo_url'");
    $stmt->bind_param('s', $logoUrl);
    $stmt->execute();
    $stmt->close();
    
    return ['success' => true, 'message' => 'লোগো সফলভাবে আপলোড করা হয়েছে।', 'logo_url' => $logoUrl];
}

function removeLogo() {
    $oldLogo = getSetting('logo_url', '');
    if (!empty($oldLogo)) {
        $oldLogoPath = __DIR__ . '/../images/' . basename($oldLogo);
        if (file_exists($oldLogoPath) && is_file($oldLogoPath)) {
            unlink($oldLogoPath);
        }
    }
    
    $stmt = conn()->prepare("UPDATE settings SET key_value = '' WHERE key_name = 'logo_url'");
    $stmt->execute();
    $stmt->close();
    
    return ['success' => true, 'message' => 'লোগো সরানো হয়েছে।'];
}

// ============================================
// FAVICON UPLOAD HANDLER
// ============================================
function uploadFavicon($file) {
    $allowedTypes = ['image/x-icon', 'image/vnd.microsoft.icon', 'image/png', 'image/jpeg', 'image/gif', 'image/webp'];
    $maxSize = 1 * 1024 * 1024; // 1MB
    
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'ফেভিকন আপলোড করতে ব্যর্থ হয়েছে।'];
    }
    
    $fileType = mime_content_type($file['tmp_name']);
    if (!in_array($fileType, $allowedTypes)) {
        return ['success' => false, 'message' => 'শুধুমাত্র ICO, PNG, JPG, GIF, WEBP ফাইল অনুমোদিত।'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'ফেভিকন সাইজ 1MB এর বেশি হতে পারে না।'];
    }
    
    $uploadDir = __DIR__ . '/../images/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'favicon.' . $extension;
    $filePath = $uploadDir . $filename;
    
    // Delete old favicon if exists
    $oldFavicon = getSetting('favicon_url', '');
    if (!empty($oldFavicon)) {
        $oldFaviconPath = __DIR__ . '/../images/' . basename($oldFavicon);
        if (file_exists($oldFaviconPath) && is_file($oldFaviconPath)) {
            unlink($oldFaviconPath);
        }
    }
    
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        return ['success' => false, 'message' => 'ফাইল আপলোড করতে ব্যর্থ হয়েছে।'];
    }
    
    $faviconUrl = '/images/' . $filename;
    $stmt = conn()->prepare("UPDATE settings SET key_value = ? WHERE key_name = 'favicon_url'");
    $stmt->bind_param('s', $faviconUrl);
    $stmt->execute();
    $stmt->close();
    
    return ['success' => true, 'message' => 'ফেভিকন সফলভাবে আপলোড করা হয়েছে।', 'favicon_url' => $faviconUrl];
}

function removeFavicon() {
    $oldFavicon = getSetting('favicon_url', '');
    if (!empty($oldFavicon)) {
        $oldFaviconPath = __DIR__ . '/../images/' . basename($oldFavicon);
        if (file_exists($oldFaviconPath) && is_file($oldFaviconPath)) {
            unlink($oldFaviconPath);
        }
    }
    
    $stmt = conn()->prepare("UPDATE settings SET key_value = '' WHERE key_name = 'favicon_url'");
    $stmt->execute();
    $stmt->close();
    
    return ['success' => true, 'message' => 'ফেভিকন সরানো হয়েছে।'];
}

// ============================================
// JSON DATA EXPORT SYSTEM
// ============================================
function exportJsonData($table, $limit = null, $offset = null) {
    $conn = conn();
    $sql = "SELECT * FROM $table";
    if ($limit !== null) {
        $sql .= " LIMIT $limit";
        if ($offset !== null) {
            $sql .= " OFFSET $offset";
        }
    }
    $result = $conn->query($sql);
    $data = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    return $data;
}

function getTableCount($table) {
    $conn = conn();
    $result = $conn->query("SELECT COUNT(*) as count FROM $table");
    if ($result) {
        $row = $result->fetch_assoc();
        return $row['count'] ?? 0;
    }
    return 0;
}

// ============================================
// JSON DATA IMPORT SYSTEM
// ============================================
function importJsonData($jsonData, $table, $mapping) {
    $conn = conn();
    $imported = 0;
    $errors = [];
    $skipped = 0;
    
    if (!is_array($jsonData)) {
        return ['success' => false, 'message' => 'JSON ডেটা সঠিক নয়।'];
    }
    
    foreach ($jsonData as $row) {
        try {
            $insertData = [];
            foreach ($mapping as $jsonField => $dbField) {
                if (isset($row[$jsonField])) {
                    $insertData[$dbField] = $row[$jsonField];
                }
            }
            
            if (empty($insertData)) {
                $skipped++;
                continue;
            }
            
            $fields = array_keys($insertData);
            $placeholders = array_fill(0, count($fields), '?');
            $sql = "INSERT INTO $table (" . implode(', ', $fields) . ") 
                    VALUES (" . implode(', ', $placeholders) . ")";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                $errors[] = "Query prepare failed: " . $conn->error;
                continue;
            }
            
            $types = '';
            $params = [];
            foreach ($insertData as $value) {
                if (is_int($value)) {
                    $types .= 'i';
                } elseif (is_float($value) || is_double($value)) {
                    $types .= 'd';
                } elseif (is_null($value)) {
                    $types .= 's';
                    $value = NULL;
                } else {
                    $types .= 's';
                }
                $params[] = $value;
            }
            
            $stmt->bind_param($types, ...$params);
            
            if ($stmt->execute()) {
                $imported++;
            } else {
                $errors[] = "Insert failed: " . $stmt->error;
            }
            $stmt->close();
            
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
    
    return [
        'success' => true,
        'imported' => $imported,
        'skipped' => $skipped,
        'errors' => $errors
    ];
}

// ============================================
// HELPER FUNCTION TO SPLIT PHONE NUMBER - FIXED
// ============================================
function splitPhoneNumber($phone) {
    // Remove any spaces, dashes, etc.
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    
    // If it starts with +, extract country code
    if (strpos($phone, '+') === 0) {
        // Remove the + for processing
        $phoneWithoutPlus = substr($phone, 1);
        
        // Known country codes (longest first for proper matching)
        $knownCodes = ['966', '880', '91', '1', '44', '61', '81', '86', '92', '94', '60', '63', '62', '66', '84', '977', '975', '95'];
        
        // Sort by length (longest first)
        usort($knownCodes, function($a, $b) {
            return strlen($b) - strlen($a);
        });
        
        $countryCode = '';
        $number = $phoneWithoutPlus;
        
        // Check for known country codes
        foreach ($knownCodes as $code) {
            if (strpos($phoneWithoutPlus, $code) === 0) {
                $countryCode = '+' . $code;
                $number = substr($phoneWithoutPlus, strlen($code));
                break;
            }
        }
        
        // If no known code found, try to detect (1-4 digits)
        if (empty($countryCode)) {
            // Try 4 digits first, then 3, then 2, then 1
            for ($i = 4; $i >= 1; $i--) {
                if (strlen($phoneWithoutPlus) > $i) {
                    $possibleCode = substr($phoneWithoutPlus, 0, $i);
                    // Check if it's a valid country code (1-4 digits)
                    if (is_numeric($possibleCode) && $possibleCode > 0) {
                        $countryCode = '+' . $possibleCode;
                        $number = substr($phoneWithoutPlus, $i);
                        break;
                    }
                }
            }
        }
        
        // If we have a country code and number
        if (!empty($countryCode) && !empty($number)) {
            return [
                'country_code' => $countryCode,
                'number' => $number
            ];
        }
    }
    
    // If no country code found, return as is
    return [
        'country_code' => '',
        'number' => $phone
    ];
}

// ============================================
// HELPER FUNCTION TO COMBINE PHONE NUMBER
// ============================================
function combinePhoneNumber($countryCode, $number) {
    // Clean inputs
    $countryCode = trim($countryCode);
    $number = trim($number);
    
    // Remove leading 0 from number
    $number = ltrim($number, '0');
    
    // Ensure country code has +
    if (!empty($countryCode) && strpos($countryCode, '+') !== 0) {
        $countryCode = '+' . $countryCode;
    }
    
    // Combine
    if (!empty($countryCode) && !empty($number)) {
        return $countryCode . $number;
    } elseif (!empty($number)) {
        return $number;
    }
    
    return '';
}

// ============================================
// POST HANDLING
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { 
        setFlash('danger', 'নিরাপত্তা যাচাই ব্যর্থ।'); 
        header('Location: settings.php'); 
        exit; 
    }
    $pa = $_POST['action'] ?? '';

    if ($pa === 'update_settings') {
        // Get the phone parts and combine them
        $phoneCountryCode = trim($_POST['phone_country_code'] ?? '');
        $phoneNumber = trim($_POST['phone_number'] ?? '');
        $combinedPhone = combinePhoneNumber($phoneCountryCode, $phoneNumber);
        
        $keys = ['somity_name','somity_address','monthly_due_amount','loan_interest_rate','profit_share_method','reserve_percentage','financial_year_start','messenger_page_id','currency_symbol'];
        
        // Update all settings including the combined phone
        foreach ($keys as $key) {
            if (isset($_POST[$key])) {
                $val = trim($_POST[$key]);
                $stmt = conn()->prepare("UPDATE settings SET key_value=? WHERE key_name=?");
                $stmt->bind_param('ss', $val, $key);
                $stmt->execute();
            }
        }
        
        // Update phone separately with combined value
        if (!empty($combinedPhone)) {
            $stmt = conn()->prepare("UPDATE settings SET key_value=? WHERE key_name='somity_phone'");
            $stmt->bind_param('s', $combinedPhone);
            $stmt->execute();
        }
        
        logActivity('settings_update', 'System settings updated');
        setFlash('success', 'সেটিং সংরক্ষিত হয়েছে।');
        header('Location: settings.php'); 
        exit;
    }

    if ($pa === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $adminId = $_SESSION['admin_id'];

        $admin = queryOne("SELECT password FROM users WHERE id=?", 'i', [$adminId]);
        if (!password_verify($current, $admin['password'])) {
            setFlash('danger', 'বর্তমান পাসওয়ার্ড সঠিক নয়।');
        } elseif ($new !== $confirm) {
            setFlash('danger', 'নতুন পাসওয়ার্ড মিলছে না।');
        } elseif (strlen($new) < 8) {
            setFlash('danger', 'পাসওয়ার্ড কমপক্ষে ৮ অক্ষর হতে হবে।');
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            conn()->query("UPDATE users SET password='$hash' WHERE id=$adminId");
            setFlash('success', 'পাসওয়ার্ড পরিবর্তন হয়েছে।');
        }
        header('Location: settings.php'); 
        exit;
    }

    if ($pa === 'upload_logo') {
        if (isset($_FILES['logo'])) {
            $result = uploadLogo($_FILES['logo']);
            if ($result['success']) {
                setFlash('success', $result['message']);
            } else {
                setFlash('danger', $result['message']);
            }
        } else {
            setFlash('danger', 'কোনো ফাইল আপলোড করা হয়নি।');
        }
        header('Location: settings.php#logo');
        exit;
    }
    
    if ($pa === 'remove_logo') {
        $result = removeLogo();
        if ($result['success']) {
            setFlash('success', $result['message']);
        } else {
            setFlash('danger', $result['message']);
        }
        header('Location: settings.php#logo');
        exit;
    }

    // ============================================
    // FAVICON UPLOAD HANDLERS
    // ============================================
    if ($pa === 'upload_favicon') {
        if (isset($_FILES['favicon'])) {
            $result = uploadFavicon($_FILES['favicon']);
            if ($result['success']) {
                setFlash('success', $result['message']);
            } else {
                setFlash('danger', $result['message']);
            }
        } else {
            setFlash('danger', 'কোনো ফাইল আপলোড করা হয়নি।');
        }
        header('Location: settings.php#favicon');
        exit;
    }
    
    if ($pa === 'remove_favicon') {
        $result = removeFavicon();
        if ($result['success']) {
            setFlash('success', $result['message']);
        } else {
            setFlash('danger', $result['message']);
        }
        header('Location: settings.php#favicon');
        exit;
    }

    if ($pa === 'export_json') {
        $exportType = $_POST['export_type'] ?? '';
        
        if (empty($exportType)) {
            setFlash('danger', 'এক্সপোর্ট টাইপ নির্বাচন করুন।');
            header('Location: settings.php#export');
            exit;
        }
        
        $data = exportJsonData($exportType);
        
        if (empty($data)) {
            setFlash('warning', 'এই টেবিলে কোনো ডেটা নেই।');
            header('Location: settings.php#export');
            exit;
        }
        
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $exportType . '_' . date('Y-m-d_H-i-s') . '.json"');
        header('Content-Length: ' . strlen($json));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo $json;
        exit;
    }

    if ($pa === 'import_json') {
        $importType = $_POST['import_type'] ?? '';
        $jsonData = $_POST['json_data'] ?? '';
        
        if (empty($jsonData)) {
            setFlash('danger', 'JSON ডেটা খালি।');
            header('Location: settings.php#import');
            exit;
        }
        
        $data = json_decode($jsonData, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            setFlash('danger', 'JSON ডেটা সঠিক নয়: ' . json_last_error_msg());
            header('Location: settings.php#import');
            exit;
        }
        
        $mappings = [
            'members' => [
                'id' => 'id', 'member_id' => 'member_id', 'name' => 'name',
                'phone' => 'phone', 'email' => 'email', 'address' => 'address',
                'nid' => 'nid', 'dob' => 'dob', 'join_date' => 'join_date',
                'status' => 'status', 'occupation' => 'occupation',
                'nominee_name' => 'nominee_name', 'nominee_relation' => 'nominee_relation',
                'nominee_phone' => 'nominee_phone', 'created_at' => 'created_at',
                'updated_at' => 'updated_at'
            ],
            'deposits' => [
                'id' => 'id', 'member_id' => 'member_id', 'amount' => 'amount',
                'deposit_date' => 'deposit_date', 'method' => 'method',
                'bank_name' => 'bank_name', 'account_no' => 'account_no',
                'reference_no' => 'reference_no', 'note' => 'note',
                'created_at' => 'created_at'
            ],
            'withdrawals' => [
                'id' => 'id', 'member_id' => 'member_id', 'amount' => 'amount',
                'withdrawal_date' => 'withdrawal_date', 'method' => 'method',
                'bank_name' => 'bank_name', 'account_no' => 'account_no',
                'reference_no' => 'reference_no', 'note' => 'note',
                'status' => 'status', 'approved_by' => 'approved_by',
                'approved_at' => 'approved_at', 'created_at' => 'created_at'
            ],
            'loans' => [
                'id' => 'id', 'member_id' => 'member_id',
                'principal_amount' => 'principal_amount', 'interest_rate' => 'interest_rate',
                'duration_months' => 'duration_months', 'loan_date' => 'loan_date',
                'purpose' => 'purpose', 'status' => 'status',
                'outstanding_balance' => 'outstanding_balance', 'approved_by' => 'approved_by',
                'approved_at' => 'approved_at', 'created_at' => 'created_at'
            ],
            'monthly_dues' => [
                'id' => 'id', 'member_id' => 'member_id', 'due_year' => 'due_year',
                'due_month' => 'due_month', 'amount' => 'amount',
                'paid_amount' => 'paid_amount', 'status' => 'status',
                'paid_date' => 'paid_date', 'created_at' => 'created_at'
            ],
            'expenses' => [
                'id' => 'id', 'title' => 'title', 'amount' => 'amount',
                'expense_date' => 'expense_date', 'category' => 'category',
                'description' => 'description', 'payment_method' => 'payment_method',
                'created_by' => 'created_by', 'created_at' => 'created_at'
            ]
        ];
        
        if (!isset($mappings[$importType])) {
            setFlash('danger', 'অবৈধ ইমপোর্ট টাইপ।');
            header('Location: settings.php#import');
            exit;
        }
        
        if (!is_array($data)) {
            setFlash('danger', 'JSON ডেটা অ্যারে হতে হবে।');
            header('Location: settings.php#import');
            exit;
        }
        
        if (isset($data['id']) || isset($data['member_id']) || isset($data['name'])) {
            $data = [$data];
        }
        
        if (empty($data)) {
            setFlash('warning', 'ইমপোর্ট করার জন্য কোনো ডেটা নেই।');
            header('Location: settings.php#import');
            exit;
        }
        
        $result = importJsonData($data, $importType, $mappings[$importType]);
        
        if ($result['success']) {
            $msg = "✅ {$result['imported']} টি রেকর্ড ইমপোর্ট করা হয়েছে।";
            if ($result['skipped'] > 0) {
                $msg .= " ⚠️ {$result['skipped']} টি রেকর্ড স্কিপ করা হয়েছে।";
            }
            if (!empty($result['errors'])) {
                $msg .= " ❌ ত্রুটি: " . implode(', ', array_slice($result['errors'], 0, 3));
                if (count($result['errors']) > 3) {
                    $msg .= " এবং আরও " . (count($result['errors']) - 3) . " টি ত্রুটি।";
                }
            }
            setFlash('success', $msg);
            logActivity('json_import', "JSON Import: {$result['imported']} records in $importType");
        } else {
            setFlash('danger', $result['message']);
        }
        
        header('Location: settings.php#import');
        exit;
    }
}

// Load all settings
$settings = [];
$rows = queryAll("SELECT key_name,key_value FROM settings");
foreach ($rows as $r) $settings[$r['key_name']] = $r['key_value'];

// Split the phone number for display - FIXED
$phoneParts = splitPhoneNumber($settings['somity_phone'] ?? '');
$phoneCountryCode = $phoneParts['country_code'] ?? '';
$phoneNumber = $phoneParts['number'] ?? '';

// Table counts for display
$tableCounts = [];
$tables = ['members', 'deposits', 'withdrawals', 'loans', 'monthly_dues', 'expenses'];
foreach ($tables as $table) {
    $tableCounts[$table] = getTableCount($table);
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.settings-section {
    background: white;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    border: 1px solid #eef2f7;
}

.settings-section .section-title {
    font-size: 15px;
    font-weight: 600;
    color: #1B4F72;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 2px solid #eef2f7;
    display: flex;
    align-items: center;
    gap: 8px;
}

.settings-section .section-title i {
    color: #2E86C1;
}

.settings-section .section-title .badge {
    font-size: 10px;
    padding: 3px 10px;
    margin-left: auto;
}

.form-group-modern {
    margin-bottom: 18px;
}

.form-group-modern label {
    font-size: 13px;
    font-weight: 500;
    color: #2C3E50;
    margin-bottom: 6px;
    display: block;
}

.form-group-modern .form-control,
.form-group-modern .form-select {
    border-radius: 8px;
    border: 1.5px solid #e2e8f0;
    padding: 10px 14px;
    transition: all 0.2s;
    font-size: 14px;
}

.form-group-modern .form-control:focus,
.form-group-modern .form-select:focus {
    border-color: #2E86C1;
    box-shadow: 0 0 0 3px rgba(46,134,193,0.1);
}

.form-group-modern .input-group .form-control {
    border-radius: 0 8px 8px 0;
}

.form-group-modern .input-group .input-group-text {
    border-radius: 8px 0 0 8px;
    background: #f8fafc;
    border: 1.5px solid #e2e8f0;
    font-weight: 500;
    font-size: 13px;
    color: #4a5568;
}

/* ============================================
   PHONE PREVIEW - HORIZONTAL ON ALL DEVICES
   ============================================ */
.phone-preview-box {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    gap: 16px;
    padding: 14px 20px;
    background: #f0f7ff;
    border-radius: 12px;
    border: 1.5px solid #2E86C1;
    flex-wrap: nowrap !important;
}

.phone-preview-box .phone-icon {
    width: 44px;
    height: 44px;
    background: #2E86C1;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: white;
    font-size: 18px;
    margin: 0;
}

.phone-preview-box .phone-icon i {
    width: 20px;
    height: 20px;
}

.phone-preview-box .phone-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.phone-preview-box .phone-info .phone-label {
    font-size: 12px;
    color: #718096;
    margin-bottom: 0;
    white-space: nowrap;
}

.phone-preview-box .phone-info .phone-number {
    font-size: 16px;
    font-weight: 600;
    color: #1B4F72;
    word-break: break-all;
    font-family: 'Courier New', monospace;
    letter-spacing: 0.5px;
}

.phone-preview-box .phone-status {
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 500;
    flex-shrink: 0;
    margin: 0;
    white-space: nowrap;
}

.phone-preview-box .phone-status.active {
    background: #d4edda;
    color: #155724;
}

.phone-preview-box .phone-status.inactive {
    background: #f8d7da;
    color: #721c24;
}

/* ============================================
   MESSENGER PREVIEW - HORIZONTAL ON ALL DEVICES
   ============================================ */
.messenger-preview-box {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    gap: 16px;
    padding: 14px 20px;
    background: #f0f7ff;
    border-radius: 12px;
    border: 1.5px solid #00B2FF;
    flex-wrap: nowrap !important;
}

.messenger-preview-box .messenger-icon {
    width: 44px;
    height: 44px;
    background: #00B2FF;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin: 0;
}

.messenger-preview-box .messenger-icon svg {
    width: 22px;
    height: 22px;
    fill: white;
}

.messenger-preview-box .messenger-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.messenger-preview-box .messenger-info .messenger-label {
    font-size: 12px;
    color: #718096;
    margin-bottom: 0;
    white-space: nowrap;
}

.messenger-preview-box .messenger-info .messenger-link {
    font-size: 14px;
    font-weight: 600;
    color: #00B2FF;
    word-break: break-all;
}

.messenger-preview-box .messenger-info .messenger-link a {
    color: #00B2FF;
    text-decoration: none;
}

.messenger-preview-box .messenger-info .messenger-link a:hover {
    text-decoration: underline;
}

.messenger-preview-box .messenger-status {
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 500;
    flex-shrink: 0;
    margin: 0;
    white-space: nowrap;
}

.messenger-preview-box .messenger-status.active {
    background: #d4edda;
    color: #155724;
}

.messenger-preview-box .messenger-status.inactive {
    background: #f8d7da;
    color: #721c24;
}

/* ============================================
   MOBILE RESPONSIVE - STAY HORIZONTAL
   ============================================ */
@media (max-width: 768px) {
    .phone-preview-box,
    .messenger-preview-box {
        gap: 12px;
        padding: 12px 16px;
        flex-wrap: nowrap !important;
    }
    
    .phone-preview-box .phone-icon,
    .messenger-preview-box .messenger-icon {
        width: 38px;
        height: 38px;
    }
    
    .phone-preview-box .phone-icon i,
    .messenger-preview-box .messenger-icon svg {
        width: 18px;
        height: 18px;
    }
    
    .phone-preview-box .phone-info .phone-label,
    .messenger-preview-box .messenger-info .messenger-label {
        font-size: 11px;
        white-space: nowrap;
    }
    
    .phone-preview-box .phone-info .phone-number {
        font-size: 14px;
    }
    
    .messenger-preview-box .messenger-info .messenger-link {
        font-size: 13px;
    }
    
    .phone-preview-box .phone-status,
    .messenger-preview-box .messenger-status {
        font-size: 10px;
        padding: 3px 10px;
    }
}

@media (max-width: 480px) {
    .phone-preview-box,
    .messenger-preview-box {
        gap: 10px;
        padding: 10px 12px;
        flex-wrap: nowrap !important;
    }
    
    .phone-preview-box .phone-icon,
    .messenger-preview-box .messenger-icon {
        width: 34px;
        height: 34px;
    }
    
    .phone-preview-box .phone-icon i,
    .messenger-preview-box .messenger-icon svg {
        width: 16px;
        height: 16px;
    }
    
    .phone-preview-box .phone-info .phone-label,
    .messenger-preview-box .messenger-info .messenger-label {
        font-size: 10px;
        white-space: nowrap;
    }
    
    .phone-preview-box .phone-info .phone-number {
        font-size: 12px;
        letter-spacing: 0.3px;
    }
    
    .messenger-preview-box .messenger-info .messenger-link {
        font-size: 12px;
    }
    
    .phone-preview-box .phone-status,
    .messenger-preview-box .messenger-status {
        font-size: 9px;
        padding: 2px 8px;
    }
}

@media (max-width: 360px) {
    .phone-preview-box,
    .messenger-preview-box {
        gap: 8px;
        padding: 8px 10px;
    }
    
    .phone-preview-box .phone-icon,
    .messenger-preview-box .messenger-icon {
        width: 30px;
        height: 30px;
    }
    
    .phone-preview-box .phone-icon i,
    .messenger-preview-box .messenger-icon svg {
        width: 14px;
        height: 14px;
    }
    
    .phone-preview-box .phone-info .phone-label,
    .messenger-preview-box .messenger-info .messenger-label {
        font-size: 9px;
    }
    
    .phone-preview-box .phone-info .phone-number {
        font-size: 11px;
    }
    
    .messenger-preview-box .messenger-info .messenger-link {
        font-size: 11px;
    }
    
    .phone-preview-box .phone-status,
    .messenger-preview-box .messenger-status {
        font-size: 8px;
        padding: 2px 6px;
    }
}

/* ============================================
   PHONE SPLIT INPUTS - SIDE BY SIDE ON ALL SCREENS
   ============================================ */
.phone-split {
    display: flex;
    flex-direction: row !important;
    gap: 12px;
    align-items: flex-start;
    flex-wrap: nowrap !important;
}

.phone-split .country-code {
    flex: 0 0 120px;
    min-width: 100px;
}

.phone-split .phone-number {
    flex: 1;
    min-width: 0;
}

.phone-split .phone-divider {
    display: flex;
    align-items: center;
    color: #718096;
    font-weight: 600;
    font-size: 18px;
    padding-top: 38px;
    flex-shrink: 0;
}

.phone-helper {
    font-size: 12px;
    color: #718096;
    margin-top: 4px;
}

.phone-helper i {
    width: 14px;
    height: 14px;
    vertical-align: middle;
}

/* ============================================
   MOBILE RESPONSIVE - SIDE BY SIDE (NOT VERTICAL)
   ============================================ */
@media (max-width: 768px) {
    .phone-split {
        flex-direction: row !important;
        gap: 6px;
        align-items: center;
        flex-wrap: nowrap !important;
    }
    
    .phone-split .country-code {
        flex: 0 0 100px;
        min-width: 80px;
    }
    
    .phone-split .phone-number {
        flex: 1;
        min-width: 0;
    }
    
    .phone-split .phone-divider {
        display: flex;
        padding-top: 0;
        font-size: 16px;
        flex-shrink: 0;
        padding: 0 2px;
        align-items: center;
        justify-content: center;
        height: 38px;
    }
    
    .phone-split .country-code .form-control,
    .phone-split .phone-number .form-control {
        font-size: 14px;
        padding: 8px 10px;
        height: 38px;
    }
    
    .phone-split .country-code .input-group-text,
    .phone-split .phone-number .input-group-text {
        padding: 6px 8px;
        font-size: 12px;
    }
    
    .phone-helper {
        font-size: 10px;
    }
}

@media (max-width: 480px) {
    .phone-split {
        gap: 4px;
    }
    
    .phone-split .country-code {
        flex: 0 0 80px;
        min-width: 65px;
    }
    
    .phone-split .phone-divider {
        font-size: 12px;
        padding: 0 1px;
        height: 34px;
    }
    
    .phone-split .country-code .form-control,
    .phone-split .phone-number .form-control {
        font-size: 12px;
        padding: 5px 6px;
        height: 34px;
    }
    
    .phone-split .country-code .input-group-text,
    .phone-split .phone-number .input-group-text {
        padding: 3px 5px;
        font-size: 10px;
    }
    
    .phone-split .country-code .input-group-text i,
    .phone-split .phone-number .input-group-text i {
        width: 12px;
        height: 12px;
    }
}

@media (max-width: 360px) {
    .phone-split .country-code {
        flex: 0 0 70px;
        min-width: 60px;
    }
    
    .phone-split .phone-divider {
        font-size: 10px;
        padding: 0 1px;
        height: 30px;
    }
    
    .phone-split .country-code .form-control,
    .phone-split .phone-number .form-control {
        font-size: 11px;
        padding: 4px 4px;
        height: 30px;
    }
}

/* ============================================
   LOGO PREVIEW
   ============================================ */
.logo-preview-box {
    display: flex;
    align-items: center;
    gap: 24px;
    padding: 20px;
    background: #f8fafc;
    border-radius: 12px;
    border: 2px dashed #e2e8f0;
    flex-wrap: wrap;
}

.logo-preview-box .logo-image {
    width: 100px;
    height: 100px;
    border-radius: 12px;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    border: 1px solid #e2e8f0;
}

.logo-preview-box .logo-image img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 8px;
}

.logo-preview-box .logo-info {
    flex: 1;
}

.logo-preview-box .logo-info .logo-status {
    font-size: 13px;
    font-weight: 500;
    margin-bottom: 4px;
}

.logo-preview-box .logo-info .logo-status .badge {
    font-size: 11px;
    padding: 4px 12px;
}

.logo-preview-box .logo-info .logo-detail {
    font-size: 12px;
    color: #718096;
}

.logo-upload-area {
    padding: 20px;
    background: #f8fafc;
    border-radius: 12px;
    border: 2px dashed #e2e8f0;
    transition: all 0.2s;
}

.logo-upload-area:hover {
    border-color: #2E86C1;
    background: #f0f7ff;
}

.logo-upload-area .upload-icon {
    font-size: 32px;
    color: #a0aec0;
    display: block;
    margin-bottom: 8px;
}

/* ============================================
   FAVICON PREVIEW
   ============================================ */
.favicon-preview-box {
    display: flex;
    align-items: center;
    gap: 24px;
    padding: 20px;
    background: #f8fafc;
    border-radius: 12px;
    border: 2px dashed #e2e8f0;
    flex-wrap: wrap;
}

.favicon-preview-box .favicon-image {
    width: 64px;
    height: 64px;
    border-radius: 12px;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    border: 1px solid #e2e8f0;
}

.favicon-preview-box .favicon-image img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 8px;
}

.favicon-preview-box .favicon-info {
    flex: 1;
}

.favicon-preview-box .favicon-info .favicon-status {
    font-size: 13px;
    font-weight: 500;
    margin-bottom: 4px;
}

.favicon-preview-box .favicon-info .favicon-status .badge {
    font-size: 11px;
    padding: 4px 12px;
}

.favicon-preview-box .favicon-info .favicon-detail {
    font-size: 12px;
    color: #718096;
}

.favicon-upload-area {
    padding: 20px;
    background: #f8fafc;
    border-radius: 12px;
    border: 2px dashed #e2e8f0;
    transition: all 0.2s;
}

.favicon-upload-area:hover {
    border-color: #2E86C1;
    background: #f0f7ff;
}

.favicon-upload-area .upload-icon {
    font-size: 32px;
    color: #a0aec0;
    display: block;
    margin-bottom: 8px;
}

.btn-modern {
    border-radius: 8px;
    padding: 10px 20px;
    font-weight: 500;
    font-size: 14px;
    transition: all 0.2s;
}

.btn-modern:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.btn-modern-primary {
    background: #1B4F72;
    color: white;
    border: none;
}

.btn-modern-primary:hover {
    background: #2E86C1;
    color: white;
}

.btn-modern-success {
    background: #1E8449;
    color: white;
    border: none;
}

.btn-modern-success:hover {
    background: #27AE60;
    color: white;
}

.btn-modern-danger {
    background: #C0392B;
    color: white;
    border: none;
}

.btn-modern-danger:hover {
    background: #E74C3C;
    color: white;
}

.btn-modern-warning {
    background: #B7770D;
    color: white;
    border: none;
}

.btn-modern-warning:hover {
    background: #F39C12;
    color: white;
}

.btn-modern-outline {
    background: transparent;
    border: 1.5px solid #e2e8f0;
    color: #4a5568;
}

.btn-modern-outline:hover {
    border-color: #2E86C1;
    color: #2E86C1;
    background: #f0f7ff;
}

.sidebar-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    border: 1px solid #eef2f7;
    margin-bottom: 20px;
}

.sidebar-card .card-title {
    font-size: 14px;
    font-weight: 600;
    color: #1B4F72;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.sidebar-card .card-title i {
    color: #2E86C1;
}

.sidebar-card .info-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #f0f2f5;
    font-size: 13px;
}

.sidebar-card .info-item:last-child {
    border-bottom: none;
}

.sidebar-card .info-item .label {
    color: #718096;
}

.sidebar-card .info-item .value {
    font-weight: 500;
    color: #2C3E50;
}

.template-buttons .btn {
    font-size: 11px;
    padding: 4px 12px;
    border-radius: 6px;
}

.import-warning {
    background: #fffbeb;
    border: 1px solid #fcd34d;
    border-radius: 8px;
    padding: 12px 16px;
    font-size: 13px;
}

.import-warning ul {
    margin-top: 6px;
    padding-left: 20px;
}

.import-warning ul li {
    margin-bottom: 2px;
}

/* Currency Symbol */
.currency-preview {
    display: inline-block;
    font-size: 20px;
    font-weight: 700;
    color: #1B4F72;
    background: #eef2f7;
    padding: 4px 12px;
    border-radius: 6px;
    margin-left: 8px;
}

/* ============================================
   RESPONSIVE FOR OTHER ELEMENTS
   ============================================ */
@media (max-width: 768px) {
    .logo-preview-box,
    .favicon-preview-box {
        flex-direction: column;
        text-align: center;
    }
    .logo-preview-box .logo-image,
    .favicon-preview-box .favicon-image {
        width: 80px;
        height: 80px;
    }
    .settings-section {
        padding: 16px;
    }
}
</style>

<div class="row g-4">
    <!-- Main Column -->
    <div class="col-12 col-lg-8">
        
        <!-- System Settings -->
        <div class="settings-section">
            <div class="section-title">
                <i data-feather="settings" style="width:18px;height:18px"></i>
                সিস্টেম সেটিং
                <span class="badge bg-secondary">প্রধান</span>
            </div>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="update_settings">

                <div class="form-group-modern">
                    <label>সমিতির নাম</label>
                    <input type="text" name="somity_name" class="form-control" 
                           value="<?= clean($settings['somity_name'] ?? '') ?>" 
                           placeholder="সমিতির নাম লিখুন">
                </div>

                <div class="form-group-modern">
                    <label>ঠিকানা</label>
                    <textarea name="somity_address" class="form-control" rows="2" 
                              placeholder="সমিতির ঠিকানা লিখুন"><?= clean($settings['somity_address'] ?? '') ?></textarea>
                </div>

                <!-- ============================================
                     WHATSAPP PHONE - SPLIT INTO COUNTRY CODE + NUMBER
                     SIDE BY SIDE ON ALL SCREENS
                     ============================================ -->
                <div class="form-group-modern">
                    <label>
                        <span style="background:#25D366; color:white; padding:2px 10px; border-radius:4px; font-size:12px; display:inline-block;">WhatsApp</span>
                        যোগাযোগ নম্বর
                    </label>
                    <div class="phone-split">
                        <div class="country-code">
                            <div class="input-group">
                                <span class="input-group-text" style="background:#f8fafc; border-color:#e2e8f0;">
                                    <i data-feather="flag" style="width:14px;height:14px;"></i>
                                </span>
                                <input type="text" name="phone_country_code" class="form-control" 
                                       value="<?= clean($phoneCountryCode) ?>" 
                                       placeholder="+966" 
                                       style="border-color:#e2e8f0;">
                            </div>
                            <div class="phone-helper">
                                <i data-feather="info" style="width:12px;height:12px;"></i>
                                দেশের কোড
                            </div>
                        </div>
                        
                        <div class="phone-divider">-</div>
                        
                        <div class="phone-number">
                            <div class="input-group">
                                <span class="input-group-text" style="background:#f8fafc; border-color:#e2e8f0;">
                                    <i data-feather="phone" style="width:14px;height:14px;"></i>
                                </span>
                                <input type="tel" name="phone_number" class="form-control" 
                                       value="<?= clean($phoneNumber) ?>" 
                                       placeholder="576000000" 
                                       style="border-color:#e2e8f0;">
                            </div>
                            <div class="phone-helper">
                                <i data-feather="info" style="width:12px;height:12px;"></i>
                                আপনার মোবাইল নম্বর দিন
                            </div>
                        </div>
                    </div>
                    
                    <!-- Phone Preview - HORIZONTAL -->
                    <?php if (!empty($settings['somity_phone'] ?? '')): ?>
                    <div class="phone-preview-box mt-3">
                        <div class="phone-icon">
                            <i data-feather="phone" style="width:20px;height:20px;"></i>
                        </div>
                        <div class="phone-info">
                            <div class="phone-label">আপনার ফোন নাম্বারটি (স্বয়ংক্রিয়)</div>
                            <div class="phone-number">
                                <?= clean($settings['somity_phone']) ?>
                            </div>
                        </div>
                        <span class="phone-status active"><i class="fa-regular fa-circle-check fa-beat" style="color:#54A868; font-size:15px; padding:4px; margin-right:6px; vertical-align:middle;"></i>সক্রিয়</span>
                    </div>
                    <?php else: ?>
                    <div class="phone-preview-box mt-3" style="border-color: #e2e8f0; background: #f8fafc;">
                        <div class="phone-icon" style="background: #a0aec0;">
                            <i data-feather="phone" style="width:20px;height:20px;"></i>
                        </div>
                        <div class="phone-info">
                            <div class="phone-label">আপনার ফোন নাম্বারটি দিন </div>
                            <div class="phone-number" style="color: #a0aec0;">কোনো নম্বর সেট করা নেই</div>
                        </div>
                        <span class="phone-status inactive"><i class="fa-regular fa-circle-xmark fa-beat" style="color:#DC3545; font-size:15px; padding:4px; margin-right:6px; vertical-align:middle;"></i> নিষ্ক্রিয়</span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- ============================================
                     MESSENGER PAGE ID - BELOW WHATSAPP PHONE
                     ============================================ -->
                <div class="form-group-modern">
                    <label>
                        <span style="background:#00B2FF; color:white; padding:2px 10px; border-radius:4px; font-size:12px; display:inline-block;">Messenger</span>
                        পেজ আইডি / ইউজারনেম
                    </label>
                    <div class="input-group">
                        <span class="input-group-text" style="background:#f0f7ff; border-color:#00B2FF; color:#00B2FF; font-weight:500;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="#00B2FF" style="margin-right:6px;">
                                <path d="M12 0C5.373 0 0 5.373 0 12c0 6.016 4.432 10.984 10.206 11.852V15.18H7.137V12h3.069V9.6c0-3.05 1.875-4.72 4.62-4.72 1.337 0 2.738.238 2.738.238v3.01h-1.542c-1.519 0-1.993.94-1.993 1.903V12h3.392l-.542 3.18h-2.85v8.672C19.568 22.984 24 18.016 24 12c0-6.627-5.373-12-12-12z"/>
                            </svg>
                            m.me/
                        </span>
                        <input type="text" name="messenger_page_id" class="form-control" 
                               value="<?= clean($settings['messenger_page_id'] ?? '') ?>" 
                               placeholder="আপনার পেজের আইডি বা ইউজারনেম দিন (যেমন: your_page_name)"
                               style="border-color:#00B2FF;">
                    </div>
                    <div class="form-text mt-1">
                        <i data-feather="info" style="width:12px;height:12px;"></i>
                        শুধু ইউজারনেম বা পেজ আইডি দিন।
                        <?php if (!empty($settings['messenger_page_id'] ?? '')): ?>
                            <br><strong><i class="fa-regular fa-circle-check fa-beat" style="color:#54A868; font-size:15px; padding:4px; margin-right:6px; vertical-align:middle;"></i> বর্তমান লিংক:</strong> 
                            <a href="https://m.me/<?= clean($settings['messenger_page_id']) ?>" target="_blank" style="color:#00B2FF;">
                                https://m.me/<?= clean($settings['messenger_page_id']) ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Messenger Preview - HORIZONTAL -->
                <?php if (!empty($settings['messenger_page_id'] ?? '')): ?>
                <div class="messenger-preview-box mb-3">
                    <div class="messenger-icon">
                        <svg viewBox="0 0 24 24">
                            <path d="M12 0C5.373 0 0 5.373 0 12c0 6.016 4.432 10.984 10.206 11.852V15.18H7.137V12h3.069V9.6c0-3.05 1.875-4.72 4.62-4.72 1.337 0 2.738.238 2.738.238v3.01h-1.542c-1.519 0-1.993.94-1.993 1.903V12h3.392l-.542 3.18h-2.85v8.672C19.568 22.984 24 18.016 24 12c0-6.627-5.373-12-12-12z"/>
                        </svg>
                    </div>
                    <div class="messenger-info">
                        <div class="messenger-label">Messenger লিংক (স্বয়ংক্রিয়)</div>
                        <div class="messenger-link">
                            <a href="https://m.me/<?= clean($settings['messenger_page_id']) ?>" target="_blank">
                                https://m.me/<?= clean($settings['messenger_page_id']) ?>
                            </a>
                        </div>
                    </div>
                    <span class="messenger-status active"><i class="fa-regular fa-circle-check fa-beat" style="color:#54A868; font-size:15px; padding:4px; margin-right:6px; vertical-align:middle;"></i> সক্রিয়</span>
                </div>
                <?php else: ?>
                <div class="messenger-preview-box mb-3" style="border-color: #e2e8f0; background: #f8fafc;">
                    <div class="messenger-icon" style="background: #a0aec0;">
                        <svg viewBox="0 0 24 24">
                            <path d="M12 0C5.373 0 0 5.373 0 12c0 6.016 4.432 10.984 10.206 11.852V15.18H7.137V12h3.069V9.6c0-3.05 1.875-4.72 4.62-4.72 1.337 0 2.738.238 2.738.238v3.01h-1.542c-1.519 0-1.993.94-1.993 1.903V12h3.392l-.542 3.18h-2.85v8.672C19.568 22.984 24 18.016 24 12c0-6.627-5.373-12-12-12z"/>
                        </svg>
                    </div>
                    <div class="messenger-info">
                        <div class="messenger-label">Messenger লিংক</div>
                        <div class="messenger-link" style="color: #a0aec0;">কোনো লিংক সেট করা নেই</div>
                    </div>
                    <span class="messenger-status inactive"><i class="fa-regular fa-circle-xmark fa-beat" style="color:#DC3545; font-size:15px; padding:4px; margin-right:6px; vertical-align:middle;"></i> নিষ্ক্রিয়</span>
                </div>
                <?php endif; ?>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-group-modern">
                            <label>আর্থিক বছর শুরু (মাস)</label>
                            <select name="financial_year_start" class="form-select">
                                <?php for ($m=1;$m<=12;$m++): ?>
                                <option value="<?= $m ?>" <?= ($settings['financial_year_start'] ?? '7') == $m ? 'selected' : '' ?>>
                                    <?= bengaliMonth($m) ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <div class="section-title" style="border-bottom: none; padding-bottom: 0; margin-bottom: 16px; font-size: 14px;">
                    <i data-feather="trending-up" style="width:16px;height:16px"></i>
                    আর্থিক নীতি
                </div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="form-group-modern">
                            <label>মাসিক সঞ্চয় (৳)</label>
                            <div class="input-group">
                                <span class="input-group-text">৳</span>
                                <input type="number" name="monthly_due_amount" class="form-control" 
                                       step="0.01" min="0" value="<?= $settings['monthly_due_amount'] ?? 500 ?>">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group-modern">
                            <label>ঋণের সুদের হার (%/বছর)</label>
                            <div class="input-group">
                                <input type="number" name="loan_interest_rate" class="form-control" 
                                       step="0.1" min="0" value="<?= $settings['loan_interest_rate'] ?? 10 ?>">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group-modern">
                            <label>রিজার্ভ (%)</label>
                            <div class="input-group">
                                <input type="number" name="reserve_percentage" class="form-control" 
                                       step="0.5" min="0" max="50" value="<?= $settings['reserve_percentage'] ?? 10 ?>">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group-modern">
                            <label>লাভ বণ্টনের পদ্ধতি</label>
                            <select name="profit_share_method" class="form-select">
                                <option value="deposit_ratio" <?= ($settings['profit_share_method']??'')=='deposit_ratio'?'selected':'' ?>>
                                    সঞ্চয় অনুপাতে
                                </option>
                                <option value="equal" <?= ($settings['profit_share_method']??'')=='equal'?'selected':'' ?>>
                                    সমান ভাগে
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- ============================================
                     CURRENCY SYMBOL
                     ============================================ -->
                <div class="row g-3 mt-2">
                    <div class="col-md-6">
                        <div class="form-group-modern">
                            <label>মুদ্রার প্রতীক (Currency Symbol)</label>
                            <div class="input-group">
                                <input type="text" name="currency_symbol" class="form-control" 
                                       maxlength="5" value="<?= clean($settings['currency_symbol'] ?? '৳') ?>" 
                                       placeholder="যেমন: ৳, $, ৹">
                                <span class="input-group-text">
                                    <span class="currency-preview"><?= clean($settings['currency_symbol'] ?? '৳') ?></span>
                                </span>
                            </div>
                            <div class="form-text">আপনার পছন্দের মুদ্রার প্রতীক দিন (সর্বোচ্চ ৫ অক্ষর)</div>
                        </div>
                    </div>
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-modern btn-modern-primary px-4">
                        <i data-feather="save" style="width:16px;height:16px;vertical-align:middle;margin-right:6px"></i>
                        সেটিং সংরক্ষণ করুন
                    </button>
                </div>
            </form>
        </div>

        <!-- Logo Management -->
        <div class="settings-section" id="logo">
            <div class="section-title">
                <i data-feather="image" style="width:18px;height:18px"></i>
                লোগো ম্যানেজমেন্ট
                <?php if (!empty($settings['logo_url'] ?? '')): ?>
                    <span class="badge bg-success"><i class="fa-regular fa-circle-check fa-beat" style="color:#54A868; font-size:15px; padding:4px; margin-right:6px; vertical-align:middle;"></i>আপলোড করা হয়েছে</span>
                <?php else: ?>
                    <span class="badge bg-secondary"><i class="fa-regular fa-circle-xmark fa-beat" style="color:#DC3545; font-size:15px; padding:4px; margin-right:6px; vertical-align:middle;"></i> লোগো নেই</span>
                <?php endif; ?>
            </div>

            <?php if (!empty($settings['logo_url'] ?? '')): ?>
            <div class="logo-preview-box mb-4">
                <div class="logo-image">
                    <img src="<?= htmlspecialchars($settings['logo_url']) ?>" alt="Current Logo">
                </div>
                <div class="logo-info">
                    <div class="logo-status">
                        <span class="badge bg-success">বর্তমান লোগো</span>
                    </div>
                    <div class="logo-detail mt-1">
                        <small>লোগোটি সাইডবারে প্রদর্শিত হবে</small>
                    </div>
                    <div class="mt-2">
                        <form method="POST" style="display:inline;">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="remove_logo">
                            <button type="submit" class="btn btn-modern btn-modern-danger btn-sm" 
                                    onclick="return confirm('আপনি কি নিশ্চিত যে লোগো সরাতে চান?')">
                                <i data-feather="trash-2" style="width:14px;height:14px"></i>
                                লোগো সরান
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="logo-preview-box mb-4" style="border-style: dashed;">
                <div class="logo-image" style="background: #f1f3f5;">
                    <i data-feather="image" style="width:40px;height:40px;color:#a0aec0;"></i>
                </div>
                <div class="logo-info">
                    <div class="logo-status">
                        <span class="badge bg-secondary">কোনো লোগো নেই</span>
                    </div>
                    <div class="logo-detail mt-1">
                        <small>আপলোডের মাধ্যমে লোগো যোগ করুন</small>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="logo-upload-area">
                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="upload_logo">
                    
                    <div class="text-center">
                        <i data-feather="upload-cloud" class="upload-icon" style="width:40px;height:40px;color:#a0aec0;"></i>
                        <p class="text-muted mb-2" style="font-size:13px;">
                            নতুন লোগো আপলোড করুন (JPG, PNG, GIF, WEBP | Max: 2MB)
                        </p>
                        <div class="row g-2 justify-content-center">
                            <div class="col-12 col-md-7">
                                <input type="file" name="logo" class="form-control form-control-sm" 
                                       accept="image/jpeg,image/png,image/gif,image/webp" required>
                            </div>
                            <div class="col-12 col-md-3">
                                <button type="submit" class="btn btn-modern btn-modern-primary w-100">
                                    <i data-feather="upload" style="width:14px;height:14px"></i>
                                    আপলোড
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- ============================================
             FAVICON MANAGEMENT SECTION
             ============================================ -->
        <div class="settings-section" id="favicon">
            <div class="section-title">
                <i data-feather="award" style="width:18px;height:18px"></i>
                ফেভিকন ম্যানেজমেন্ট
                <?php if (!empty($settings['favicon_url'] ?? '')): ?>
                    <span class="badge bg-success"><i class="fa-regular fa-circle-check fa-beat" style="color:#54A868; font-size:15px; padding:4px; margin-right:6px; vertical-align:middle;"></i> আপলোড করা হয়েছে</span>
                <?php else: ?>
                    <span class="badge bg-secondary"><i class="fa-regular fa-circle-xmark fa-beat" style="color:#DC3545; font-size:15px; padding:4px; margin-right:6px; vertical-align:middle;"></i> ফেভিকন নেই</span>
                <?php endif; ?>
            </div>

            <?php if (!empty($settings['favicon_url'] ?? '')): ?>
            <div class="favicon-preview-box mb-4">
                <div class="favicon-image">
                    <img src="<?= htmlspecialchars($settings['favicon_url']) ?>" alt="Current Favicon">
                </div>
                <div class="favicon-info">
                    <div class="favicon-status">
                        <span class="badge bg-success">বর্তমান ফেভিকন</span>
                    </div>
                    <div class="favicon-detail mt-1">
                        <small>ফেভিকনটি ব্রাউজারের ট্যাবে প্রদর্শিত হবে</small>
                    </div>
                    <div class="mt-2">
                        <form method="POST" style="display:inline;">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="remove_favicon">
                            <button type="submit" class="btn btn-modern btn-modern-danger btn-sm" 
                                    onclick="return confirm('আপনি কি নিশ্চিত যে ফেভিকন সরাতে চান?')">
                                <i data-feather="trash-2" style="width:14px;height:14px"></i>
                                ফেভিকন সরান
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="favicon-preview-box mb-4" style="border-style: dashed;">
                <div class="favicon-image" style="background: #f1f3f5;">
                    <i data-feather="award" style="width:32px;height:32px;color:#a0aec0;"></i>
                </div>
                <div class="favicon-info">
                    <div class="favicon-status">
                        <span class="badge bg-secondary">কোনো ফেভিকন নেই</span>
                    </div>
                    <div class="favicon-detail mt-1">
                        <small>আপলোডের মাধ্যমে ফেভিকন যোগ করুন (ICO, PNG, JPG, GIF, WEBP | Max: 1MB)</small>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="favicon-upload-area">
                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="upload_favicon">
                    
                    <div class="text-center">
                        <i data-feather="upload-cloud" class="upload-icon" style="width:40px;height:40px;color:#a0aec0;"></i>
                        <p class="text-muted mb-2" style="font-size:13px;">
                            নতুন ফেভিকন আপলোড করুন (ICO, PNG, JPG, GIF, WEBP | Max: 1MB)
                        </p>
                        <div class="row g-2 justify-content-center">
                            <div class="col-12 col-md-7">
                                <input type="file" name="favicon" class="form-control form-control-sm" 
                                       accept="image/x-icon,image/vnd.microsoft.icon,image/png,image/jpeg,image/gif,image/webp" required>
                            </div>
                            <div class="col-12 col-md-3">
                                <button type="submit" class="btn btn-modern btn-modern-primary w-100">
                                    <i data-feather="upload" style="width:14px;height:14px"></i>
                                    আপলোড
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- JSON Export -->
        <div class="settings-section" id="export">
            <div class="section-title">
                <i data-feather="download" style="width:18px;height:18px"></i>
                JSON ডেটা এক্সপোর্ট
                <span class="badge bg-success">📥 ডাউনলোড</span>
            </div>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="export_json">

                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-7">
                        <div class="form-group-modern mb-0">
                            <label>এক্সপোর্ট টাইপ <span class="text-danger">*</span></label>
                            <select name="export_type" class="form-select" required>
                                <option value="">-- সিলেক্ট করুন --</option>
                                <option value="members">📋 সদস্য (<?= number_format($tableCounts['members'] ?? 0) ?> টি)</option>
                                <option value="deposits">💰 সঞ্চয় জমা (<?= number_format($tableCounts['deposits'] ?? 0) ?> টি)</option>
                                <option value="withdrawals">📤 উত্তোলন (<?= number_format($tableCounts['withdrawals'] ?? 0) ?> টি)</option>
                                <option value="loans">🏦 ঋণ (<?= number_format($tableCounts['loans'] ?? 0) ?> টি)</option>
                                <option value="monthly_dues">📅 মাসিক সঞ্চয় (<?= number_format($tableCounts['monthly_dues'] ?? 0) ?> টি)</option>
                                <option value="expenses">📊 খরচ (<?= number_format($tableCounts['expenses'] ?? 0) ?> টি)</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-12 col-md-5">
                        <button type="submit" class="btn btn-modern btn-modern-success w-100">
                            <i data-feather="download" style="width:16px;height:16px;vertical-align:middle;margin-right:6px"></i>
                            JSON ডাউনলোড করুন
                        </button>
                    </div>
                </div>
                <div class="mt-2 text-muted" style="font-size:12px;">
                    <i data-feather="info" style="width:12px;height:12px"></i>
                    নির্বাচিত টেবিলের সব ডেটা JSON ফরম্যাটে ডাউনলোড হবে।
                </div>
            </form>
        </div>

        <!-- JSON Import -->
        <div class="settings-section" id="import">
            <div class="section-title">
                <i data-feather="upload" style="width:18px;height:18px"></i>
                JSON ডেটা ইমপোর্ট
                <span class="badge bg-warning text-dark">⚠️ সতর্কতার সাথে ব্যবহার করুন</span>
            </div>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="import_json">

                <div class="form-group-modern">
                    <label>ইমপোর্ট টাইপ <span class="text-danger">*</span></label>
                    <select name="import_type" class="form-select" required>
                        <option value="">-- সিলেক্ট করুন --</option>
                        <option value="members">📋 সদস্য (Members)</option>
                        <option value="deposits">💰 সঞ্চয় জমা (Deposits)</option>
                        <option value="withdrawals">📤 উত্তোলন (Withdrawals)</option>
                        <option value="loans">🏦 ঋণ (Loans)</option>
                        <option value="monthly_dues">📅 মাসিক সঞ্চয় (Monthly Dues)</option>
                        <option value="expenses">📊 খরচ (Expenses)</option>
                    </select>
                    <div class="form-text">কোন টেবিলে ডেটা ইমপোর্ট করতে চান তা নির্বাচন করুন।</div>
                </div>

                <div class="form-group-modern">
                    <label>JSON ডেটা <span class="text-danger">*</span></label>
                    <textarea name="json_data" class="form-control" rows="6" 
                              placeholder='[{"member_id":"001","name":"রহিম","phone":"017..."},...]' 
                              style="font-family:monospace;font-size:13px;" required></textarea>
                    <div class="form-text">
                        <i data-feather="info" style="width:12px;height:12px"></i> 
                        প্রতিটি রেকর্ডের জন্য JSON অ্যারে ব্যবহার করুন। 
                        <a href="#" onclick="event.preventDefault(); showExample();" class="text-primary">উদাহরণ দেখুন</a>
                    </div>
                </div>

                <div class="import-warning mb-3">
                    <i data-feather="alert-triangle" style="width:16px;height:16px;color:#f59e0b;"></i>
                    <strong>সতর্কতা:</strong>
                    <ul>
                        <li>ইমপোর্ট করার আগে ব্যাকআপ নিন।</li>
                        <li>ডুপ্লিকেট ডেটা ইমপোর্ট হতে পারে।</li>
                        <li>সঠিক ফিল্ড নাম ব্যবহার করুন।</li>
                        <li>JSON ফরম্যাট ঠিক আছে কিনা যাচাই করুন।</li>
                    </ul>
                </div>

                <button type="submit" class="btn btn-modern btn-modern-warning w-100" 
                        onclick="return confirm('আপনি কি নিশ্চিত যে JSON ডেটা ইমপোর্ট করতে চান?')">
                    <i data-feather="upload" style="width:16px;height:16px;vertical-align:middle;margin-right:6px"></i>
                    JSON ডেটা ইমপোর্ট করুন
                </button>
            </form>
        </div>
    </div>

    <!-- Right Sidebar -->
    <div class="col-12 col-lg-4">
        
        <!-- Password Change -->
        <div class="sidebar-card">
            <div class="card-title">
                <i data-feather="lock" style="width:16px;height:16px"></i>
                পাসওয়ার্ড পরিবর্তন
            </div>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="change_password">
                
                <div class="form-group-modern">
                    <label>বর্তমান পাসওয়ার্ড</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                <div class="form-group-modern">
                    <label>নতুন পাসওয়ার্ড</label>
                    <input type="password" name="new_password" class="form-control" required minlength="8">
                </div>
                <div class="form-group-modern">
                    <label>পাসওয়ার্ড নিশ্চিত করুন</label>
                    <input type="password" name="confirm_password" class="form-control" required minlength="8">
                </div>
                <button type="submit" class="btn btn-modern btn-modern-warning w-100">
                    <i data-feather="key" style="width:14px;height:14px;vertical-align:middle;margin-right:6px"></i>
                    পাসওয়ার্ড পরিবর্তন
                </button>
            </form>
        </div>

        <!-- System Info -->
        <div class="sidebar-card">
            <div class="card-title">
                <i data-feather="info" style="width:16px;height:16px"></i>
                সিস্টেম তথ্য
            </div>
            <?php
            $sysInfo = [
                ['PHP সংস্করণ', PHP_VERSION],
                ['MySQL', conn()->server_info],
                ['App সংস্করণ', APP_VERSION],
                ['মোট সদস্য', number_format($tableCounts['members'] ?? 0) . ' জন'],
                ['মোট সঞ্চয়', number_format($tableCounts['deposits'] ?? 0) . ' টি'],
                ['মোট উত্তোলন', number_format($tableCounts['withdrawals'] ?? 0) . ' টি'],
                ['মোট ঋণ', number_format($tableCounts['loans'] ?? 0) . ' টি'],
                ['মোট সঞ্চয় সংগ্রহ', number_format($tableCounts['monthly_dues'] ?? 0) . ' টি'],
                ['মোট খরচ', number_format($tableCounts['expenses'] ?? 0) . ' টি'],
                ['সর্বশেষ আপডেট', date('d M Y H:i')],
            ];
            ?>
            <?php foreach ($sysInfo as [$label,$val]): ?>
            <div class="info-item">
                <span class="label"><?= $label ?></span>
                <span class="value"><?= clean((string)$val) ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Template Download -->
        <div class="sidebar-card">
            <div class="card-title">
                <i data-feather="file" style="width:16px;height:16px"></i>
                টেমপ্লেট ডাউনলোড
            </div>
            <p class="text-muted small">ইমপোর্টের জন্য JSON টেমপ্লেট ডাউনলোড করুন:</p>
            <div class="template-buttons d-flex flex-wrap gap-1">
                <a href="#" onclick="downloadTemplate('members')" class="btn btn-sm btn-outline-primary">📋 সদস্য</a>
                <a href="#" onclick="downloadTemplate('deposits')" class="btn btn-sm btn-outline-success">💰 সঞ্চয়</a>
                <a href="#" onclick="downloadTemplate('withdrawals')" class="btn btn-sm btn-outline-danger">📤 উত্তোলন</a>
                <a href="#" onclick="downloadTemplate('loans')" class="btn btn-sm btn-outline-warning">🏦 ঋণ</a>
                <a href="#" onclick="downloadTemplate('monthly_dues')" class="btn btn-sm btn-outline-info">📅 মাসিক</a>
                <a href="#" onclick="downloadTemplate('expenses')" class="btn btn-sm btn-outline-secondary">📊 খরচ</a>
            </div>
        </div>
    </div>
</div>

<script>
// ============================================
// JSON TEMPLATE EXAMPLES
// ============================================
const templates = {
    'members': [
        {
            "member_id": "001",
            "name": "আব্দুর রহিম",
            "phone": "01712345678",
            "email": "rahim@example.com",
            "address": "ঢাকা, বাংলাদেশ",
            "nid": "1234567890123",
            "dob": "1980-01-15",
            "join_date": "2024-01-01",
            "status": "active",
            "occupation": "ব্যবসায়ী",
            "nominee_name": "মরিয়ম বেগম",
            "nominee_relation": "স্ত্রী",
            "nominee_phone": "01787654321"
        },
        {
            "member_id": "002",
            "name": "মোঃ করিম",
            "phone": "01798765432",
            "email": "karim@example.com",
            "address": "চট্টগ্রাম, বাংলাদেশ",
            "nid": "9876543210987",
            "dob": "1985-05-20",
            "join_date": "2024-01-15",
            "status": "active",
            "occupation": "চাকুরিজীবী"
        }
    ],
    'deposits': [
        {
            "member_id": 1,
            "amount": 1000.00,
            "deposit_date": "2024-01-01",
            "method": "cash",
            "bank_name": "",
            "account_no": "",
            "reference_no": "REF001",
            "note": "জানুয়ারি সঞ্চয়"
        }
    ],
    'withdrawals': [
        {
            "member_id": 1,
            "amount": 2000.00,
            "withdrawal_date": "2024-01-10",
            "method": "cash",
            "bank_name": "",
            "account_no": "",
            "reference_no": "WTH001",
            "note": "ব্যক্তিগত প্রয়োজনে",
            "status": "completed"
        }
    ],
    'loans': [
        {
            "member_id": 1,
            "principal_amount": 50000.00,
            "interest_rate": 10.00,
            "duration_months": 12,
            "loan_date": "2024-01-01",
            "purpose": "ব্যবসার জন্য",
            "status": "active",
            "outstanding_balance": 50000.00
        }
    ],
    'monthly_dues': [
        {
            "member_id": 1,
            "due_year": 2024,
            "due_month": 1,
            "amount": 500.00,
            "paid_amount": 500.00,
            "status": "paid"
        }
    ],
    'expenses': [
        {
            "title": "অফিস ভাড়া",
            "amount": 5000.00,
            "expense_date": "2024-01-01",
            "category": "ভাড়া",
            "description": "জানুয়ারি মাসের অফিস ভাড়া",
            "payment_method": "cash"
        }
    ]
};

function showExample() {
    const type = document.querySelector('select[name="import_type"]').value || 'members';
    const data = templates[type] || templates['members'];
    document.querySelector('textarea[name="json_data"]').value = JSON.stringify(data, null, 2);
}

function downloadTemplate(type) {
    const data = templates[type] || templates['members'];
    const blob = new Blob([JSON.stringify(data, null, 2)], {type: 'application/json'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = type + '_template.json';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>