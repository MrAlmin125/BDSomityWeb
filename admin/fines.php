<?php
// ============================================
// admin/fines.php - COMPLETE FIXED VERSION
// AUTO FINE GENERATION FOR LATE DEPOSITS
// AUTOMATICALLY CHECKS PREVIOUS MONTH
// ============================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/functions.php';
startSecureSession();

if (!isAdminLoggedIn()) {
    header('Location: /admin/login.php');
    exit;
}

$pageTitle = 'ফাইন ব্যবস্থাপনা';
$error = '';
$success = '';
$syncMessage = '';
$debugInfo = '';

// ============================================
// DATABASE SETUP
// ============================================
function setupFineTables($conn) {
    $check = $conn->query("SHOW TABLES LIKE 'fines'");
    if ($check->num_rows == 0) {
        $queries = [];
        
        $queries[] = "
            CREATE TABLE IF NOT EXISTS fines (
                id INT(11) PRIMARY KEY AUTO_INCREMENT,
                member_id INT(11) NOT NULL,
                month_year DATE NOT NULL,
                fine_amount DECIMAL(12,2) NOT NULL DEFAULT 200.00,
                paid_amount DECIMAL(12,2) DEFAULT 0.00,
                waived_amount DECIMAL(12,2) DEFAULT 0.00,
                status ENUM('pending', 'partially_paid', 'paid', 'waived') DEFAULT 'pending',
                due_date DATE NOT NULL,
                paid_date DATE NULL,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
                UNIQUE KEY unique_fine (member_id, month_year),
                INDEX idx_member_month (member_id, month_year),
                INDEX idx_status (status)
            )
        ";
        
        $queries[] = "
            CREATE TABLE IF NOT EXISTS fine_settings (
                id INT(11) PRIMARY KEY AUTO_INCREMENT,
                fine_name VARCHAR(100) NOT NULL DEFAULT 'মাসিক বিলম্ব ফাইন',
                fine_amount DECIMAL(12,2) NOT NULL DEFAULT 200.00,
                due_day INT(2) NOT NULL DEFAULT 10,
                grace_period_days INT(3) NOT NULL DEFAULT 0,
                is_active TINYINT(1) DEFAULT 1,
                last_check_date DATETIME NULL,
                last_check_month DATE NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ";
        
        $queries[] = "
            CREATE TABLE IF NOT EXISTS fine_payments (
                id INT(11) PRIMARY KEY AUTO_INCREMENT,
                fine_id INT(11) NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                payment_date DATE NOT NULL,
                payment_method VARCHAR(50) DEFAULT 'cash',
                transaction_no VARCHAR(100),
                receipt_no VARCHAR(50),
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (fine_id) REFERENCES fines(id) ON DELETE CASCADE,
                INDEX idx_fine (fine_id)
            )
        ";
        
        $queries[] = "
            INSERT IGNORE INTO fine_settings (fine_name, fine_amount, due_day, grace_period_days, last_check_date, last_check_month) 
            VALUES ('মাসিক বিলম্ব ফাইন', 200.00, 10, 0, NULL, NULL)
        ";
        
        foreach ($queries as $query) {
            try {
                $conn->query($query);
            } catch (Exception $e) {
                // Continue with other queries
            }
        }
    }
    
    // Add missing columns if not exists
    $checkCol = $conn->query("SHOW COLUMNS FROM fine_settings LIKE 'last_check_month'");
    if ($checkCol->num_rows == 0) {
        $conn->query("ALTER TABLE fine_settings ADD COLUMN last_check_month DATE NULL AFTER last_check_date");
    }
}

// ============================================
// FINE SYSTEM CLASS - COMPLETE FIXED VERSION
// ============================================
class FineSystem {
    private $conn;
    private $settings;
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->loadSettings();
    }
    
    private function loadSettings() {
        $result = $this->conn->query("SELECT * FROM fine_settings ORDER BY id DESC LIMIT 1");
        $this->settings = $result->fetch_assoc();
        
        if (!$this->settings) {
            $this->settings = [
                'id' => 1,
                'fine_name' => 'মাসিক বিলম্ব ফাইন',
                'fine_amount' => 200.00,
                'due_day' => 10,
                'grace_period_days' => 0,
                'is_active' => 1,
                'last_check_date' => null,
                'last_check_month' => null
            ];
        }
    }
    
    public function getSettings() {
        return $this->settings;
    }
    
    // ============================================
    // FIXED: Auto check - always checks previous month
    // No date restriction - works anytime
    // ============================================
    public function autoCheckAndGenerateFines() {
        $settings = $this->settings;
        
        if (!$settings['is_active']) {
            return ['success' => false, 'message' => 'ফাইন সিস্টেম নিষ্ক্রিয় রয়েছে'];
        }
        
        $fineAmount = (float)$settings['fine_amount'];
        $dueDay = (int)$settings['due_day'];
        
        // Previous month (always check previous month)
        $previousMonth = new DateTime('first day of last month');
        $previousMonthStr = $previousMonth->format('Y-m-01');
        $previousMonthName = $previousMonth->format('F Y');
        $previousMonthStart = $previousMonth->format('Y-m-01');
        $previousMonthEnd = $previousMonth->format('Y-m-t');
        
        $lastCheckMonth = $settings['last_check_month'] ?? null;
        
        // Check if we already processed this month
        $checkMonth = date('Y-m', strtotime($previousMonthStr));
        if ($lastCheckMonth && date('Y-m', strtotime($lastCheckMonth)) >= $checkMonth) {
            return [
                'success' => true, 
                'message' => '✅ এই মাসের জন্য ইতিমধ্যে চেক করা হয়েছে: ' . $previousMonthName,
                'generated' => 0,
                'removed' => 0,
                'skipped' => 0,
                'errors' => 0
            ];
        }
        
        $members = $this->conn->query("SELECT id, name, member_id FROM members WHERE status = 'active' ORDER BY name");
        $generated = 0;
        $skipped = 0;
        $removed = 0;
        $errors = 0;
        $results = [];
        $depositMembers = [];
        $noDepositMembers = [];
        $existingFineMembers = [];
        $removedFineMembers = [];
        
        while ($member = $members->fetch_assoc()) {
            try {
                // Check if member has deposits in previous month
                $depositStmt = $this->conn->prepare("
                    SELECT COUNT(*) as deposit_count,
                           GROUP_CONCAT(DATE_FORMAT(deposit_date, '%d-%m-%Y') SEPARATOR ', ') as deposit_dates
                    FROM deposits 
                    WHERE member_id = ? 
                    AND deposit_date >= ? 
                    AND deposit_date <= ?
                ");
                $depositStmt->bind_param('iss', $member['id'], $previousMonthStart, $previousMonthEnd);
                $depositStmt->execute();
                $depositResult = $depositStmt->get_result()->fetch_assoc();
                $depositCount = $depositResult['deposit_count'] ?? 0;
                $depositDates = $depositResult['deposit_dates'] ?? '';
                
                // Check if fine already exists for this month
                $checkFine = $this->conn->prepare("SELECT id, status, paid_amount FROM fines WHERE member_id = ? AND month_year = ?");
                $checkFine->bind_param('is', $member['id'], $previousMonthStr);
                $checkFine->execute();
                $existingFine = $checkFine->get_result()->fetch_assoc();
                
                // If member has deposits, remove any existing fine
                if ($depositCount > 0) {
                    $depositMembers[] = $member['name'];
                    
                    if ($existingFine && $existingFine['status'] != 'paid') {
                        $this->deleteFine($existingFine['id']);
                        $removed++;
                        $removedFineMembers[] = $member['name'];
                        $results[] = [
                            'member_id' => $member['id'],
                            'name' => $member['name'],
                            'status' => 'removed',
                            'message' => "✅ জমা আছে ({$depositCount} টি) - ফাইন বাদ দেওয়া হয়েছে"
                        ];
                    } else {
                        $skipped++;
                        $results[] = [
                            'member_id' => $member['id'],
                            'name' => $member['name'],
                            'status' => 'skipped',
                            'message' => "✅ জমা আছে ({$depositCount} টি) - ফাইন প্রয়োজন নেই"
                        ];
                    }
                    continue;
                }
                
                // No deposits - check if fine exists
                $noDepositMembers[] = $member['name'];
                
                if ($existingFine) {
                    if ($existingFine['status'] == 'paid') {
                        $skipped++;
                        $results[] = [
                            'member_id' => $member['id'],
                            'name' => $member['name'],
                            'status' => 'paid',
                            'message' => "⚠️ ফাইন আগেই পে করা হয়েছে"
                        ];
                    } else {
                        $existingFineMembers[] = $member['name'];
                        $skipped++;
                        $results[] = [
                            'member_id' => $member['id'],
                            'name' => $member['name'],
                            'status' => 'exists',
                            'message' => "⚠️ ফাইন আগেই তৈরি আছে"
                        ];
                    }
                    continue;
                }
                
                // No deposits and no fine - CREATE NEW FINE
                $dueDate = $previousMonth->format('Y-m-') . str_pad($dueDay, 2, '0', STR_PAD_LEFT);
                $note = "কোনো জমা নেই: {$previousMonthName}";
                
                $stmt = $this->conn->prepare("
                    INSERT INTO fines (member_id, month_year, fine_amount, due_date, status, waived_amount, notes) 
                    VALUES (?, ?, ?, ?, 'pending', 0, ?)
                ");
                $stmt->bind_param('isdss', $member['id'], $previousMonthStr, $fineAmount, $dueDate, $note);
                
                if ($stmt->execute()) {
                    $generated++;
                    $results[] = [
                        'member_id' => $member['id'],
                        'name' => $member['name'],
                        'status' => 'generated',
                        'message' => "❌ কোন জমা নেই - ফাইন তৈরি (৳{$fineAmount})"
                    ];
                } else {
                    $errors++;
                    $results[] = [
                        'member_id' => $member['id'],
                        'name' => $member['name'],
                        'status' => 'error',
                        'message' => 'ফাইন তৈরি করতে ব্যর্থ: ' . $stmt->error
                    ];
                }
                
            } catch (Exception $e) {
                $errors++;
                $results[] = [
                    'member_id' => $member['id'],
                    'name' => $member['name'],
                    'status' => 'error',
                    'message' => 'Error: ' . $e->getMessage()
                ];
            }
        }
        
        // Update last check
        $updateStmt = $this->conn->prepare("
            UPDATE fine_settings 
            SET last_check_date = NOW(),
                last_check_month = ?
            WHERE id = ?
        ");
        $updateStmt->bind_param('si', $previousMonthStr, $settings['id']);
        $updateStmt->execute();
        
        $totalMembers = $members->num_rows;
        $totalDeposited = count($depositMembers);
        $totalNoDeposit = count($noDepositMembers);
        $totalExisting = count($existingFineMembers);
        $totalRemoved = count($removedFineMembers);
        
        // Build summary message
        $summaryMessage = "";
        
        if ($generated == 0 && $removed == 0 && $skipped == $totalMembers) {
            $summaryMessage = "✅ সব সদস্য ইতিমধ্যে প্রক্রিয়াকৃত!\n";
            $summaryMessage .= "📌 মাস: {$previousMonthName}\n";
            $summaryMessage .= "📌 মোট সদস্য: {$totalMembers} জন\n";
            $summaryMessage .= "📌 সবাই জমা দিয়েছেন অথবা ফাইন পে করেছেন - কোন পরিবর্তন নেই ✅\n";
        } else {
            $summaryMessage = "✅ ফাইন চেক সম্পূর্ণ (মাস: {$previousMonthName}):\n";
            $summaryMessage .= "📌 মোট সদস্য: {$totalMembers} জন\n";
            if ($generated > 0) {
                $summaryMessage .= "📌 {$generated} টি নতুন ফাইন তৈরি (জমা নেই)\n";
            }
            if ($removed > 0) {
                $summaryMessage .= "📌 {$removed} টি ফাইন বাদ দেওয়া হয়েছে (জমা থাকায়)\n";
            }
            if ($skipped > 0) {
                $summaryMessage .= "📌 {$skipped} টি স্কিপ (ইতিমধ্যে প্রক্রিয়াকৃত)\n";
            }
            if ($errors > 0) {
                $summaryMessage .= "📌 ❌ {$errors} টি ত্রুটি\n";
            }
            
            if ($totalDeposited > 0) {
                $summaryMessage .= "\n✅ জমা থাকা সদস্যরা: {$totalDeposited} জন\n";
                $summaryMessage .= "👤 " . implode(', ', array_slice($depositMembers, 0, 10));
                if ($totalDeposited > 10) {
                    $summaryMessage .= " (+ " . ($totalDeposited - 10) . " জন)";
                }
            }
            
            if ($generated > 0) {
                $summaryMessage .= "\n\n❌ জমা নেই (ফাইন তৈরি): {$generated} জন\n";
                $noDepositList = array_slice($noDepositMembers, 0, 10);
                $summaryMessage .= "👤 " . implode(', ', $noDepositList);
                if ($generated > 10) {
                    $summaryMessage .= " (+ " . ($generated - 10) . " জন)";
                }
            }
        }
        
        return [
            'success' => true,
            'message' => $summaryMessage,
            'generated' => $generated,
            'removed' => $removed,
            'skipped' => $skipped,
            'errors' => $errors,
            'results' => $results,
            'month' => $previousMonthStr,
            'month_name' => $previousMonthName,
            'total_members' => $totalMembers,
            'total_deposited' => $totalDeposited,
            'total_no_deposit' => $totalNoDeposit,
            'total_removed' => $totalRemoved,
            'deposit_members' => $depositMembers,
            'no_deposit_members' => $noDepositMembers,
            'existing_fine_members' => $existingFineMembers
        ];
    }
    
    public function deleteFine($fineId) {
        $stmt = $this->conn->prepare("DELETE FROM fine_payments WHERE fine_id = ?");
        $stmt->bind_param('i', $fineId);
        $stmt->execute();
        
        $stmt = $this->conn->prepare("DELETE FROM fines WHERE id = ?");
        $stmt->bind_param('i', $fineId);
        return $stmt->execute();
    }
    
    public function generateAllFinesForMonth($monthYear) {
        $monthYear = date('Y-m-01', strtotime($monthYear));
        $monthName = date('F Y', strtotime($monthYear));
        
        $currentMonthStart = date('Y-m-01');
        if ($monthYear == $currentMonthStart) {
            return [
                'success' => false,
                'message' => '❌ বর্তমান মাসের জন্য ফাইন তৈরি করা যাবে না! সদস্যরা এখনো জমা দিচ্ছেন। অনুগ্রহ করে আগের মাস নির্বাচন করুন।',
                'generated' => 0,
                'skipped' => 0,
                'errors' => 0,
                'results' => []
            ];
        }
        
        if (strtotime($monthYear) > strtotime($currentMonthStart)) {
            return [
                'success' => false,
                'message' => '❌ ভবিষ্যত মাসের জন্য ফাইন তৈরি করা যাবে না!',
                'generated' => 0,
                'skipped' => 0,
                'errors' => 0,
                'results' => []
            ];
        }
        
        $settings = $this->settings;
        $fineAmount = (float)$settings['fine_amount'];
        $dueDay = (int)$settings['due_day'];
        $dueDate = date('Y-m-' . str_pad($dueDay, 2, '0', STR_PAD_LEFT), strtotime($monthYear));
        
        $monthStart = $monthYear;
        $monthEnd = date('Y-m-t', strtotime($monthYear));
        
        $members = $this->conn->query("SELECT id, name, member_id FROM members WHERE status = 'active' ORDER BY name");
        
        $totalMembers = $members->num_rows;
        $generated = 0;
        $skipped = 0;
        $errors = 0;
        $results = [];
        $depositMembers = [];
        $noDepositMembers = [];
        $paidMembers = [];
        $existingFineMembers = [];
        $removedMembers = [];
        
        while ($member = $members->fetch_assoc()) {
            try {
                $depositStmt = $this->conn->prepare("
                    SELECT COUNT(*) as deposit_count,
                           GROUP_CONCAT(DATE_FORMAT(deposit_date, '%d-%m-%Y') SEPARATOR ', ') as deposit_dates
                    FROM deposits 
                    WHERE member_id = ? 
                    AND deposit_date >= ? 
                    AND deposit_date <= ?
                ");
                $depositStmt->bind_param('iss', $member['id'], $monthStart, $monthEnd);
                $depositStmt->execute();
                $depositResult = $depositStmt->get_result()->fetch_assoc();
                $depositCount = $depositResult['deposit_count'] ?? 0;
                $depositDates = $depositResult['deposit_dates'] ?? '';
                
                if ($depositCount > 0) {
                    $depositMembers[] = $member['name'];
                    
                    $checkFine = $this->conn->prepare("SELECT id, status FROM fines WHERE member_id = ? AND month_year = ?");
                    $checkFine->bind_param('is', $member['id'], $monthYear);
                    $checkFine->execute();
                    $existingFine = $checkFine->get_result()->fetch_assoc();
                    
                    if ($existingFine && $existingFine['status'] != 'paid') {
                        $this->deleteFine($existingFine['id']);
                        $removedMembers[] = $member['name'];
                        $results[] = [
                            'member_id' => $member['id'],
                            'name' => $member['name'],
                            'status' => 'removed',
                            'message' => "✅ জমা আছে ({$depositCount} টি) - ফাইন বাদ দেওয়া হয়েছে"
                        ];
                    } else if ($existingFine && $existingFine['status'] == 'paid') {
                        $paidMembers[] = $member['name'];
                        $results[] = [
                            'member_id' => $member['id'],
                            'name' => $member['name'],
                            'status' => 'paid',
                            'message' => "✅ জমা আছে এবং ফাইন পে করা হয়েছে"
                        ];
                    } else {
                        $results[] = [
                            'member_id' => $member['id'],
                            'name' => $member['name'],
                            'status' => 'deposited',
                            'message' => "✅ জমা আছে ({$depositCount} টি) - ফাইন প্রয়োজন নেই"
                        ];
                    }
                    continue;
                }
                
                $noDepositMembers[] = $member['name'];
                
                $checkFine = $this->conn->prepare("SELECT id, status FROM fines WHERE member_id = ? AND month_year = ?");
                $checkFine->bind_param('is', $member['id'], $monthYear);
                $checkFine->execute();
                $existingFine = $checkFine->get_result()->fetch_assoc();
                
                if ($existingFine) {
                    if ($existingFine['status'] == 'paid') {
                        $paidMembers[] = $member['name'];
                        $results[] = [
                            'member_id' => $member['id'],
                            'name' => $member['name'],
                            'status' => 'paid',
                            'message' => "⚠️ ফাইন আগেই পে করা হয়েছে"
                        ];
                    } else {
                        $existingFineMembers[] = $member['name'];
                        $results[] = [
                            'member_id' => $member['id'],
                            'name' => $member['name'],
                            'status' => 'exists',
                            'message' => "⚠️ ফাইন আগেই তৈরি আছে"
                        ];
                    }
                    continue;
                }
                
                $note = "ম্যানুয়াল ফাইন তৈরি for {$monthName} (কোন জমা নেই)";
                $stmt = $this->conn->prepare("
                    INSERT INTO fines (member_id, month_year, fine_amount, due_date, status, waived_amount, notes) 
                    VALUES (?, ?, ?, ?, 'pending', 0, ?)
                ");
                $stmt->bind_param('isdss', $member['id'], $monthYear, $fineAmount, $dueDate, $note);
                
                if ($stmt->execute()) {
                    $generated++;
                    $results[] = [
                        'member_id' => $member['id'],
                        'name' => $member['name'],
                        'status' => 'generated',
                        'message' => "❌ কোন জমা নেই - ফাইন তৈরি (৳{$fineAmount})"
                    ];
                } else {
                    $errors++;
                    $results[] = [
                        'member_id' => $member['id'],
                        'name' => $member['name'],
                        'status' => 'error',
                        'message' => 'ফাইন তৈরি করতে ব্যর্থ: ' . $stmt->error
                    ];
                }
                
            } catch (Exception $e) {
                $errors++;
                $results[] = [
                    'member_id' => $member['id'],
                    'name' => $member['name'],
                    'status' => 'error',
                    'message' => 'Error: ' . $e->getMessage()
                ];
            }
        }
        
        $totalDeposited = count($depositMembers);
        $totalNoDeposit = count($noDepositMembers);
        $totalPaid = count($paidMembers);
        $totalExisting = count($existingFineMembers);
        $totalRemoved = count($removedMembers);
        
        $allDeposited = ($totalDeposited == $totalMembers && $generated == 0 && $totalNoDeposit == 0 && $totalExisting == 0);
        $allDepositedWithPaid = ($totalDeposited + $totalPaid == $totalMembers && $generated == 0 && $totalNoDeposit == 0);
        
        $summaryMessage = "";
        
        if ($allDeposited) {
            $summaryMessage = "🎉 **সব সদস্যই জমা দিয়েছেন!** \n";
            $summaryMessage .= "📌 মাস: {$monthName}\n";
            $summaryMessage .= "📌 মোট সদস্য: {$totalMembers} জন\n";
            $summaryMessage .= "📌 সবাই জমা দিয়েছেন - কোন ফাইন তৈরি হয়নি ✅\n";
            $summaryMessage .= "\n👤 জমাদানকারী সদস্যরা: " . implode(', ', array_slice($depositMembers, 0, 15));
            if (count($depositMembers) > 15) {
                $summaryMessage .= " (+ " . (count($depositMembers) - 15) . " জন)";
            }
        } else if ($allDepositedWithPaid) {
            $summaryMessage = "🎉 **সব সদস্যই জমা দিয়েছেন অথবা ফাইন পে করেছেন!** \n";
            $summaryMessage .= "📌 মাস: {$monthName}\n";
            $summaryMessage .= "📌 মোট সদস্য: {$totalMembers} জন\n";
            $summaryMessage .= "📌 {$totalDeposited} জন জমা দিয়েছেন\n";
            $summaryMessage .= "📌 {$totalPaid} জন ফাইন পে করেছেন\n";
            $summaryMessage .= "📌 কোন নতুন ফাইন তৈরি হয়নি ✅\n";
        } else {
            $summaryMessage = "✅ ফাইন তৈরি সম্পূর্ণ (মাস: {$monthName}):\n";
            $summaryMessage .= "📌 মোট সদস্য: {$totalMembers} জন\n";
            $summaryMessage .= "📌 {$generated} টি নতুন ফাইন তৈরি (জমা নেই)\n";
            
            if ($totalDeposited > 0) {
                $summaryMessage .= "📌 {$totalDeposited} জন জমা দিয়েছেন (ফাইন হয়নি)\n";
            }
            if ($totalPaid > 0) {
                $summaryMessage .= "📌 {$totalPaid} জন ফাইন পে করেছেন\n";
            }
            if ($totalExisting > 0) {
                $summaryMessage .= "📌 {$totalExisting} জনের আগে থেকেই ফাইন আছে\n";
            }
            if ($totalRemoved > 0) {
                $summaryMessage .= "📌 {$totalRemoved} জনের ফাইন বাদ দেওয়া হয়েছে (জমা থাকায়)\n";
            }
            if ($skipped > 0) {
                $summaryMessage .= "📌 {$skipped} টি স্কিপ\n";
            }
            if ($errors > 0) {
                $summaryMessage .= "📌 ❌ {$errors} টি ত্রুটি\n";
            }
            
            if ($totalDeposited > 0) {
                $summaryMessage .= "\n✅ জমা থাকা সদস্যরা (ফাইন হয়নি): {$totalDeposited} জন\n";
                $summaryMessage .= "👤 " . implode(', ', array_slice($depositMembers, 0, 10));
                if ($totalDeposited > 10) {
                    $summaryMessage .= " (+ " . ($totalDeposited - 10) . " জন)";
                }
            }
            
            if ($totalNoDeposit > 0) {
                $summaryMessage .= "\n\n❌ জমা নেই (ফাইন তৈরি): {$totalNoDeposit} জন\n";
                $summaryMessage .= "👤 " . implode(', ', array_slice($noDepositMembers, 0, 10));
                if ($totalNoDeposit > 10) {
                    $summaryMessage .= " (+ " . ($totalNoDeposit - 10) . " জন)";
                }
            }
            
            if ($totalPaid > 0) {
                $summaryMessage .= "\n\n✅ ফাইন পে করেছেন: {$totalPaid} জন\n";
                $summaryMessage .= "👤 " . implode(', ', array_slice($paidMembers, 0, 10));
                if ($totalPaid > 10) {
                    $summaryMessage .= " (+ " . ($totalPaid - 10) . " জন)";
                }
            }
        }
        
        return [
            'success' => true,
            'generated' => $generated,
            'skipped' => $skipped,
            'errors' => $errors,
            'results' => $results,
            'month' => $monthYear,
            'month_name' => $monthName,
            'all_deposited' => $allDeposited,
            'all_deposited_or_paid' => $allDepositedWithPaid,
            'total_members' => $totalMembers,
            'total_deposited' => $totalDeposited,
            'total_no_deposit' => $totalNoDeposit,
            'total_paid' => $totalPaid,
            'total_existing' => $totalExisting,
            'total_removed' => $totalRemoved,
            'deposit_members' => $depositMembers,
            'no_deposit_members' => $noDepositMembers,
            'paid_members' => $paidMembers,
            'message' => $summaryMessage
        ];
    }
    
    public function generateFineForMember($memberId, $monthYear, $fineAmount = null) {
        if ($fineAmount === null) {
            $fineAmount = $this->settings['fine_amount'];
        }
        
        $monthYear = date('Y-m-01', strtotime($monthYear));
        $monthName = date('F Y', strtotime($monthYear));
        
        $currentMonthStart = date('Y-m-01');
        if ($monthYear == $currentMonthStart) {
            return ['success' => false, 'message' => '❌ বর্তমান মাসের জন্য ফাইন তৈরি করা যাবে না! সদস্যরা এখনো জমা দিচ্ছেন।'];
        }
        
        if (strtotime($monthYear) > strtotime($currentMonthStart)) {
            return ['success' => false, 'message' => '❌ ভবিষ্যত মাসের জন্য ফাইন তৈরি করা যাবে না!'];
        }
        
        $monthStart = $monthYear;
        $monthEnd = date('Y-m-t', strtotime($monthYear));
        
        $depositStmt = $this->conn->prepare("
            SELECT COUNT(*) as deposit_count
            FROM deposits 
            WHERE member_id = ? 
            AND deposit_date >= ? 
            AND deposit_date <= ?
        ");
        $depositStmt->bind_param('iss', $memberId, $monthStart, $monthEnd);
        $depositStmt->execute();
        $depositResult = $depositStmt->get_result()->fetch_assoc();
        $depositCount = $depositResult['deposit_count'] ?? 0;
        
        if ($depositCount > 0) {
            return ['success' => false, 'message' => "✅ এই সদস্য {$monthName} মাসে জমা দিয়েছেন ({$depositCount} টি), ফাইন প্রয়োজন নেই।"];
        }
        
        $dueDay = $this->settings['due_day'];
        $dueDate = date('Y-m-' . str_pad($dueDay, 2, '0', STR_PAD_LEFT), strtotime($monthYear));
        
        $checkFine = $this->conn->prepare("SELECT id, status FROM fines WHERE member_id = ? AND month_year = ?");
        $checkFine->bind_param('is', $memberId, $monthYear);
        $checkFine->execute();
        $existingFine = $checkFine->get_result()->fetch_assoc();
        
        if ($existingFine) {
            if ($existingFine['status'] == 'paid') {
                return ['success' => false, 'message' => 'এই মাসের ফাইন আগেই পে করা হয়েছে।'];
            }
            return ['success' => false, 'message' => 'এই মাসের জন্য ফাইন আগেই তৈরি করা হয়েছে।'];
        }
        
        $stmt = $this->conn->prepare("
            INSERT INTO fines (member_id, month_year, fine_amount, due_date, status, waived_amount, notes) 
            VALUES (?, ?, ?, ?, 'pending', 0, ?)
        ");
        $note = "ম্যানুয়াল ফাইন তৈরি for {$monthName}";
        $stmt->bind_param('isdss', $memberId, $monthYear, $fineAmount, $dueDate, $note);
        
        if ($stmt->execute()) {
            return [
                'success' => true,
                'message' => "✅ ফাইন সফলভাবে তৈরি করা হয়েছে! (মাস: {$monthName})",
                'fine_id' => $this->conn->insert_id
            ];
        }
        
        return ['success' => false, 'message' => '❌ ফাইন তৈরি করতে ব্যর্থ হয়েছে।'];
    }
    
    public function getMemberFineSummary($search = '', $filterMonth = null, $filterYear = null, $limit = 20, $offset = 0) {
        $sql = "
            SELECT 
                m.id as member_id,
                m.name,
                m.member_id as member_code,
                m.phone,
                m.email,
                m.address,
                m.photo,
                COUNT(f.id) as total_fines_count,
                COALESCE(SUM(f.fine_amount), 0) as total_fine_amount,
                COALESCE(SUM(f.paid_amount), 0) as total_paid_amount,
                COALESCE(SUM(f.waived_amount), 0) as total_waived_amount,
                COALESCE(SUM(f.fine_amount - f.paid_amount - f.waived_amount), 0) as total_remaining,
                COALESCE(SUM(CASE WHEN f.status = 'pending' OR f.status = 'partially_paid' THEN f.fine_amount - f.paid_amount - f.waived_amount ELSE 0 END), 0) as pending_amount,
                COALESCE(SUM(CASE WHEN f.status = 'pending' THEN 1 ELSE 0 END), 0) as pending_count,
                COALESCE(SUM(CASE WHEN f.status = 'paid' THEN 1 ELSE 0 END), 0) as paid_count,
                COALESCE(SUM(CASE WHEN f.status = 'waived' THEN 1 ELSE 0 END), 0) as waived_count,
                COALESCE(SUM(CASE WHEN f.status = 'partially_paid' THEN 1 ELSE 0 END), 0) as partial_count
            FROM members m
            LEFT JOIN fines f ON m.id = f.member_id
            WHERE m.status = 'active'
        ";
        
        $params = [];
        $types = "";
        
        if ($filterMonth && $filterYear) {
            $monthYearStart = $filterYear . '-' . str_pad($filterMonth, 2, '0', STR_PAD_LEFT) . '-01';
            $monthYearEnd = $filterYear . '-' . str_pad($filterMonth, 2, '0', STR_PAD_LEFT) . '-31';
            
            $sql .= " AND (f.month_year >= ? AND f.month_year <= ?)";
            $types .= 'ss';
            $params[] = $monthYearStart;
            $params[] = $monthYearEnd;
        }
        
        if (!empty($search)) {
            $sql .= " AND (m.name LIKE ? OR m.member_id LIKE ? OR m.phone LIKE ?)";
            $types .= 'sss';
            $searchParam = "%$search%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        $sql .= " GROUP BY m.id ORDER BY m.name";
        $sql .= " LIMIT ? OFFSET ?";
        $types .= 'ii';
        $params[] = $limit;
        $params[] = $offset;
        
        $members = [];
        if (!empty($params)) {
            $stmt = $this->conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $members[] = $row;
                }
            }
        }
        
        return $members;
    }
    
    public function getMemberFineSummaryCount($search = '', $filterMonth = null, $filterYear = null) {
        $sql = "
            SELECT COUNT(DISTINCT m.id) as total
            FROM members m
            LEFT JOIN fines f ON m.id = f.member_id
            WHERE m.status = 'active'
        ";
        
        $params = [];
        $types = "";
        
        if ($filterMonth && $filterYear) {
            $monthYearStart = $filterYear . '-' . str_pad($filterMonth, 2, '0', STR_PAD_LEFT) . '-01';
            $monthYearEnd = $filterYear . '-' . str_pad($filterMonth, 2, '0', STR_PAD_LEFT) . '-31';
            $sql .= " AND (f.month_year >= ? AND f.month_year <= ?)";
            $types .= 'ss';
            $params[] = $monthYearStart;
            $params[] = $monthYearEnd;
        }
        
        if (!empty($search)) {
            $sql .= " AND (m.name LIKE ? OR m.member_id LIKE ? OR m.phone LIKE ?)";
            $types .= 'sss';
            $searchParam = "%$search%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        if (!empty($params)) {
            $stmt = $this->conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
                return $result->fetch_assoc()['total'] ?? 0;
            }
        } else {
            $result = $this->conn->query($sql);
            return $result->fetch_assoc()['total'] ?? 0;
        }
        
        return 0;
    }
    
    public function getMonthlyFineStatus($month, $year) {
        $monthYearStart = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01';
        $monthYearEnd = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-31';
        $monthName = date('F Y', strtotime($monthYearStart));
        
        $sql = "
            SELECT 
                m.id as member_id,
                m.name,
                m.member_id as member_code,
                m.phone,
                f.id as fine_id,
                f.fine_amount,
                f.paid_amount,
                f.waived_amount,
                f.status,
                f.due_date,
                f.paid_date,
                COALESCE(
                    (SELECT COUNT(*) FROM deposits d 
                     WHERE d.member_id = m.id 
                     AND d.deposit_date >= ? 
                     AND d.deposit_date <= ?), 
                    0
                ) as deposit_count
            FROM members m
            LEFT JOIN fines f ON m.id = f.member_id 
                AND f.month_year >= ? 
                AND f.month_year <= ?
            WHERE m.status = 'active'
            ORDER BY m.name
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ssss', $monthYearStart, $monthYearEnd, $monthYearStart, $monthYearEnd);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $members = [];
        while ($row = $result->fetch_assoc()) {
            if ($row['deposit_count'] > 0) {
                $row['fine_status'] = 'has_deposit';
                $row['fine_status_label'] = '✅ জমা আছে';
                $row['fine_status_color'] = 'success';
            } elseif ($row['fine_id']) {
                $statusMap = [
                    'pending' => ['label' => '❌ বকেয়া', 'color' => 'danger'],
                    'partially_paid' => ['label' => '🟡 আংশিক', 'color' => 'warning'],
                    'paid' => ['label' => '✅ পরিশোধিত', 'color' => 'success'],
                    'waived' => ['label' => '⏭️ মওকুফ', 'color' => 'secondary']
                ];
                $row['fine_status'] = $row['status'];
                $row['fine_status_label'] = $statusMap[$row['status']]['label'] ?? $row['status'];
                $row['fine_status_color'] = $statusMap[$row['status']]['color'] ?? 'secondary';
            } else {
                $row['fine_status'] = 'no_fine';
                $row['fine_status_label'] = '⏳ ফাইন নেই';
                $row['fine_status_color'] = 'secondary';
            }
            $members[] = $row;
        }
        
        return $members;
    }
    
    public function getOverallStatistics() {
        $sql = "
            SELECT 
                COUNT(*) as total_fines,
                COALESCE(SUM(fine_amount), 0) as total_amount,
                COALESCE(SUM(paid_amount), 0) as total_paid,
                COALESCE(SUM(waived_amount), 0) as total_waived,
                COALESCE(SUM(fine_amount - paid_amount - waived_amount), 0) as total_remaining,
                COUNT(CASE WHEN status = 'pending' OR status = 'partially_paid' THEN 1 END) as pending_count,
                COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_count,
                COUNT(CASE WHEN status = 'waived' THEN 1 END) as waived_count,
                COUNT(CASE WHEN status = 'partially_paid' THEN 1 END) as partial_count
            FROM fines
        ";
        
        $result = $this->conn->query($sql);
        return $result->fetch_assoc();
    }
    
    public function getFineStatusSummary() {
        $sql = "
            SELECT 
                status,
                COUNT(*) as count,
                COALESCE(SUM(fine_amount), 0) as total_amount,
                COALESCE(SUM(paid_amount), 0) as paid_amount,
                COALESCE(SUM(fine_amount - paid_amount - waived_amount), 0) as remaining
            FROM fines
            GROUP BY status
        ";
        $result = $this->conn->query($sql);
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[$row['status']] = $row;
        }
        return $data;
    }
    
    public function getDistinctMonths() {
        $sql = "SELECT DISTINCT DATE_FORMAT(month_year, '%Y-%m') as month_key, 
                DATE_FORMAT(month_year, '%M %Y') as month_label,
                YEAR(month_year) as year,
                MONTH(month_year) as month
                FROM fines 
                ORDER BY month_year DESC";
        $result = $this->conn->query($sql);
        $months = [];
        while ($row = $result->fetch_assoc()) {
            $months[] = $row;
        }
        return $months;
    }
}

// ============================================
// PROCESS ACTIONS
// ============================================
try {
    $conn = conn();
    setupFineTables($conn);
    $fineSystem = new FineSystem($conn);
    $settings = $fineSystem->getSettings();
    
    // Auto check runs on page load - ALWAYS checks previous month
    $autoCheckResult = $fineSystem->autoCheckAndGenerateFines();
    if ($autoCheckResult['success']) {
        if (($autoCheckResult['generated'] ?? 0) > 0 || ($autoCheckResult['removed'] ?? 0) > 0) {
            $syncMessage = $autoCheckResult['message'];
        } elseif (isset($autoCheckResult['message']) && strpos($autoCheckResult['message'], 'ইতিমধ্যে চেক') !== false) {
            $syncMessage = $autoCheckResult['message'];
        }
    }
    
    if (isset($_POST['sync_fines'])) {
        $result = $fineSystem->autoCheckAndGenerateFines();
        if ($result['success']) {
            $syncMessage = $result['message'];
            if (isset($result['debug'])) {
                $debugInfo = $result['debug'];
            }
        } else {
            $error = $result['message'];
        }
    }
    
    if (isset($_POST['generate_all_fines'])) {
        $monthYear = $_POST['month_year'] ?? date('Y-m-01', strtotime('-1 month'));
        $result = $fineSystem->generateAllFinesForMonth($monthYear);
        
        if ($result['success']) {
            $syncMessage = $result['message'];
            if (isset($result['all_deposited'])) {
                $allDeposited = $result['all_deposited'];
            }
            if (isset($result['total_members'])) {
                $totalMembersResult = $result['total_members'];
            }
        } else {
            $error = $result['message'];
        }
    }
    
    if (isset($_POST['generate_member_fine'])) {
        $memberId = (int)($_POST['member_id'] ?? 0);
        $monthYear = $_POST['month_year'] ?? date('Y-m-01', strtotime('-1 month'));
        
        if ($memberId <= 0) {
            $error = '❌ সদস্য নির্বাচন করুন।';
        } else {
            $result = $fineSystem->generateFineForMember($memberId, $monthYear);
            if ($result['success']) {
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }
        }
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
        $fine_name = clean($_POST['fine_name'] ?? 'মাসিক বিলম্ব ফাইন');
        $fine_amount = (float)($_POST['fine_amount'] ?? 200);
        $due_day = (int)($_POST['due_day'] ?? 10);
        $grace_period_days = (int)($_POST['grace_period_days'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $stmt = $conn->prepare("
            UPDATE fine_settings 
            SET fine_name = ?, 
                fine_amount = ?, 
                due_day = ?, 
                grace_period_days = ?,
                is_active = ?
            WHERE id = (SELECT id FROM (SELECT id FROM fine_settings ORDER BY id DESC LIMIT 1) as tmp)
        ");
        
        $stmt->bind_param('sdiii', $fine_name, $fine_amount, $due_day, $grace_period_days, $is_active);
        
        if ($stmt->execute()) {
            $success = '✅ সেটিংস সফলভাবে আপডেট করা হয়েছে!';
            $settings = $fineSystem->getSettings();
        } else {
            $error = '❌ সেটিংস আপডেট করতে ব্যর্থ হয়েছে: ' . $conn->error;
        }
    }
    
    $searchTerm = trim($_GET['search'] ?? '');
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;
    $tab = $_GET['tab'] ?? 'members';
    
    $filterMonth = isset($_GET['filter_month']) ? (int)$_GET['filter_month'] : null;
    $filterYear = isset($_GET['filter_year']) ? (int)$_GET['filter_year'] : null;
    
    $displayMonth = $filterMonth ?: null;
    $displayYear = $filterYear ?: null;
    
    $members = $fineSystem->getMemberFineSummary($searchTerm, $displayMonth, $displayYear, $limit, $offset);
    $totalMembers = $fineSystem->getMemberFineSummaryCount($searchTerm, $displayMonth, $displayYear);
    $totalPages = ceil($totalMembers / $limit);
    
    $monthlyStatus = null;
    if ($filterMonth && $filterYear) {
        $monthlyStatus = $fineSystem->getMonthlyFineStatus($filterMonth, $filterYear);
    }
    
    $availableMonths = $fineSystem->getDistinctMonths();
    
    $overallStats = $fineSystem->getOverallStatistics();
    $totalFines = $overallStats['total_fines'] ?? 0;
    $totalAmount = $overallStats['total_amount'] ?? 0;
    $totalPaid = $overallStats['total_paid'] ?? 0;
    $totalWaived = $overallStats['total_waived'] ?? 0;
    $totalRemaining = $overallStats['total_remaining'] ?? 0;
    $totalPendingCount = $overallStats['pending_count'] ?? 0;
    $totalPaidCount = $overallStats['paid_count'] ?? 0;
    $totalWaivedCount = $overallStats['waived_count'] ?? 0;
    $totalPartialCount = $overallStats['partial_count'] ?? 0;
    
    $statusSummary = $fineSystem->getFineStatusSummary();
    
    $lastCheck = $settings['last_check_date'] ? date('d-m-Y H:i:s', strtotime($settings['last_check_date'])) : 'কখনও চেক করা হয়নি';
    $lastCheckMonth = $settings['last_check_month'] ? date('F Y', strtotime($settings['last_check_month'])) : 'কখনও চেক করা হয়নি';
    $nextDueDate = date('d-m-Y', strtotime(date('Y-m-' . $settings['due_day'])));
    
    $prevMonth = new DateTime('first day of last month');
    $prevMonthName = $prevMonth->format('F Y');
    $prevMonthStart = $prevMonth->format('Y-m-01');
    $prevMonthEnd = $prevMonth->format('Y-m-t');
    
} catch (Exception $e) {
    $error = 'সিস্টেম লোড করতে ব্যর্থ হয়েছে: ' . $e->getMessage();
    $members = [];
    $totalMembers = 0;
    $totalPages = 0;
    $totalFines = 0;
    $totalAmount = 0;
    $totalPaid = 0;
    $totalWaived = 0;
    $totalRemaining = 0;
    $totalPendingCount = 0;
    $totalPaidCount = 0;
    $totalWaivedCount = 0;
    $totalPartialCount = 0;
    $lastCheck = 'কখনও চেক করা হয়নি';
    $lastCheckMonth = 'কখনও চেক করা হয়নি';
    $nextDueDate = date('d-m-Y');
    $statusSummary = [];
    $prevMonthName = date('F Y', strtotime('-1 month'));
    $prevMonthStart = date('Y-m-01', strtotime('-1 month'));
    $prevMonthEnd = date('Y-m-t', strtotime('-1 month'));
    $availableMonths = [];
    $monthlyStatus = null;
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-2 px-md-4" style="padding-bottom: 80px;">
    
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 mt-2 gap-2">
        <h4 class="mb-0 fs-6 fs-md-4">
            <i data-feather="alert-triangle" style="width:20px;height:20px"></i> 
            ফাইন ব্যবস্থাপনা
            <span class="badge bg-primary ms-1"><?= $totalMembers ?></span>
        </h4>
        <div class="d-flex gap-2 flex-wrap">
            <a href="fines.php?tab=members" class="btn btn-outline-primary btn-sm <?= $tab == 'members' ? 'active' : '' ?>">
                <i data-feather="users" style="width:14px;height:14px"></i>
                সদস্য
            </a>
            <button class="btn btn-outline-success btn-sm" onclick="openGenerateAllModal()">
                <i data-feather="users" style="width:14px;height:14px"></i>
                সব ফাইন তৈরি
            </button>
            <a href="fines.php?tab=settings" class="btn btn-outline-secondary btn-sm <?= $tab == 'settings' ? 'active' : '' ?>">
                <i data-feather="settings" style="width:14px;height:14px"></i>
                সেটিংস
            </a>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2 small py-2" role="alert">
        <i data-feather="alert-circle" style="width:16px;height:16px;flex-shrink:0"></i>
        <span><?= htmlspecialchars($error) ?></span>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success d-flex align-items-center gap-2 small py-2" role="alert">
        <i data-feather="check-circle" style="width:16px;height:16px;flex-shrink:0"></i>
        <span><?= htmlspecialchars($success) ?></span>
    </div>
    <?php endif; ?>

    <?php if ($syncMessage): ?>
    <div class="alert alert-<?= strpos($syncMessage, '🎉') !== false ? 'success' : (strpos($syncMessage, '❌') !== false ? 'danger' : 'info') ?> d-flex align-items-center gap-2 small py-2" role="alert" style="white-space: pre-line;">
        <i data-feather="<?= strpos($syncMessage, '🎉') !== false ? 'smile' : (strpos($syncMessage, '❌') !== false ? 'alert-circle' : 'check-circle') ?>" style="width:16px;height:16px;flex-shrink:0;"></i>
        <span><?= nl2br(htmlspecialchars($syncMessage)) ?></span>
    </div>
    <?php endif; ?>

    <?php if ($debugInfo && isset($_POST['sync_fines'])): ?>
    <div class="alert alert-secondary small py-2" role="alert">
        <strong>Debug Info:</strong>
        <pre style="white-space: pre-wrap; font-size: 11px; margin: 5px 0;"><?= htmlspecialchars($debugInfo) ?></pre>
    </div>
    <?php endif; ?>

    <?php if ($tab == 'members'): ?>
    
    <!-- Auto Check Status Bar -->
    <div class="alert alert-info small py-1 mb-2 d-flex flex-wrap justify-content-between align-items-center">
        <span>
            <i data-feather="users" style="width:14px;height:14px"></i>
            <strong>ফাইন নিয়ম:</strong> 
            <span class="badge bg-success ms-1">✅ জমা আছে → ফাইন নেই</span>
            <span class="badge bg-danger ms-1">❌ জমা নেই → ফাইন তৈরি</span>
        </span>
        <span>
            <i data-feather="calendar" style="width:14px;height:14px"></i>
            চেক হচ্ছে: <strong><?= $prevMonthName ?></strong>
            <span class="badge bg-secondary ms-1">ডিউ ডেট: <?= date('d-m-Y', strtotime($prevMonthStart . ' +' . ($settings['due_day'] - 1) . ' days')) ?></span>
        </span>
        <span>
            <i data-feather="clock" style="width:14px;height:14px"></i>
            শেষ চেক: <strong><?= $lastCheck ?></strong>
        </span>
    </div>
    
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-2">
            <div class="stat-card p-2">
                <div class="stat-value fs-5 text-danger"><?= number_format($totalRemaining, 2) ?></div>
                <div class="stat-label small">বকেয়া ফাইন</div>
                <small class="text-muted">(<?= $totalPendingCount ?>টি)</small>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card bg-success-grad p-2">
                <div class="stat-value fs-5 text-success"><?= number_format($totalPaid, 2) ?></div>
                <div class="stat-label small">আদায়কৃত</div>
                <small class="text-muted">(<?= $totalPaidCount ?> টি)</small>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card bg-info-grad p-2">
                <div class="stat-value fs-5 text-info"><?= number_format($totalWaived, 2) ?> টাকা</div>
                <div class="stat-label small">মওকুফ</div>
                <small class="text-muted">(<?= $totalWaivedCount ?>টি)</small>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card bg-warning-grad p-2">
                <div class="stat-value fs-5 text-warning"><?= number_format($totalAmount - $totalPaid - $totalWaived - $totalRemaining, 2) ?></div>
                <div class="stat-label small">আংশিক</div>
                <small class="text-muted">(<?= $totalPartialCount ?>টি)</small>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card bg-danger-grad p-2">
                <div class="stat-value fs-5 text-success"><?= number_format($totalAmount, 2) ?> টাকা</div>
                <div class="stat-label small">মোট ফাইন</div>
                <small class="text-muted">(<?= $totalFines ?>টি)</small>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card bg-purple-grad p-2">
                <small class="text-muted">মোট সদস্য</small>
                <div class="stat-value fs-6 text-primary"><?= $totalMembers ?> জন</div>
                <div class="stat-label small">সক্রিয় সদস্য</div>
                
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- FILTER SECTION - MONTH AND YEAR              -->
    <!-- ============================================ -->
    <div class="card mb-3">
        <div class="card-body p-2">
            <form method="GET" class="row g-1">
                <input type="hidden" name="tab" value="members">
                
                <div class="col-12 col-md-3">
                    <label class="form-label small fw-bold mb-0">মাস</label>
                    <select name="filter_month" class="form-select form-select-sm">
                        <option value="">সব মাস</option>
                        <?php for ($m = 1; $m <= 12; $m++): 
                            $monthName = date('F', mktime(0, 0, 0, $m, 1));
                            $selected = ($filterMonth == $m) ? 'selected' : '';
                        ?>
                        <option value="<?= $m ?>" <?= $selected ?>><?= $monthName ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="col-12 col-md-3">
                    <label class="form-label small fw-bold mb-0">সাল</label>
                    <select name="filter_year" class="form-select form-select-sm">
                        <option value="">সব সাল</option>
                        <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): 
                            $selected = ($filterYear == $y) ? 'selected' : '';
                        ?>
                        <option value="<?= $y ?>" <?= $selected ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="col-8 col-md-4">
                    <label class="form-label small fw-bold mb-0">নাম/আইডি/ফোন</label>
                    <input type="text" name="search" class="form-control form-control-sm" 
                           placeholder="খুঁজুন..." 
                           value="<?= htmlspecialchars($searchTerm) ?>">
                </div>
                
                <div class="col-4 col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i data-feather="filter" style="width:14px;height:14px"></i> ফিল্টার
                    </button>
                </div>
            </form>
            
            <?php if ($filterMonth && $filterYear): ?>
            <div class="mt-2 text-center">
                <span class="badge bg-info">
                    📅 <?= date('F Y', mktime(0, 0, 0, $filterMonth, 1, $filterYear)) ?>
                    <a href="?tab=members" class="text-white ms-2" style="text-decoration:none;">✕</a>
                </span>
                <?php if ($monthlyStatus): 
                    $totalWithDeposit = 0;
                    $totalWithFine = 0;
                    $totalPaidFine = 0;
                    $totalPendingFine = 0;
                    foreach ($monthlyStatus as $m) {
                        if ($m['fine_status'] == 'has_deposit') $totalWithDeposit++;
                        elseif ($m['fine_id']) {
                            if ($m['status'] == 'paid') $totalPaidFine++;
                            elseif ($m['status'] == 'pending' || $m['status'] == 'partially_paid') $totalPendingFine++;
                            $totalWithFine++;
                        }
                    }
                ?>
                <span class="badge bg-success ms-2">✅ জমা আছে: <?= $totalWithDeposit ?></span>
                <span class="badge bg-danger ms-2">❌ বকেয়া: <?= $totalPendingFine ?></span>
                <span class="badge bg-success ms-2">✅ পরিশোধিত: <?= $totalPaidFine ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header py-2">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-1">
                <span class="small fw-bold"><i data-feather="list" style="width:14px;height:14px"></i> সদস্য তালিকা</span>
                <span class="small text-muted">মোট: <?= $totalMembers ?> জন</span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="membersTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>সদস্য</th>
                        <?php if ($filterMonth && $filterYear): ?>
                        <th class="text-center">ফাইন স্ট্যাটাস</th>
                        <th class="text-end">ফাইন</th>
                        <th class="text-end">আদায়</th>
                        <?php else: ?>
                        <th class="text-end">মোট ফাইন</th>
                        <th class="text-end">আদায়কৃত</th>
                        <th class="text-end">মওকুফ</th>
                        <th class="text-end">বকেয়া</th>
                        <?php endif; ?>
                        <th class="text-center">অ্যাকশন</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($members)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-3 small">
                            <i data-feather="users" style="width:24px;height:24px;display:block;margin:0 auto 5px"></i>
                            কোনো সদস্য পাওয়া যায়নি
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($members as $index => $member): 
                        $remaining = ($member['total_remaining'] ?? 0);
                        $photoPath = !empty($member['photo']) ? $member['photo'] : '';
                        $hasPhoto = !empty($member['photo']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $member['photo']);
                        
                        $monthStatus = null;
                        if ($filterMonth && $filterYear && $monthlyStatus) {
                            foreach ($monthlyStatus as $ms) {
                                if ($ms['member_id'] == $member['member_id']) {
                                    $monthStatus = $ms;
                                    break;
                                }
                            }
                        }
                    ?>
                    <tr class="member-row" style="cursor: pointer;" data-member-id="<?= $member['member_id'] ?>">
                        <td><?= $index + 1 + $offset ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <?php if ($hasPhoto): ?>
                                    <img src="<?= htmlspecialchars($photoPath) ?>" 
                                         alt="<?= htmlspecialchars($member['name']) ?>" 
                                         class="profile-avatar"
                                         onerror="this.style.display='none'; this.parentElement.querySelector('.profile-avatar-placeholder').style.display='flex';">
                                    <div class="profile-avatar-placeholder" style="display:none;">
                                        <?= strtoupper(substr($member['name'], 0, 1)) ?>
                                    </div>
                                <?php else: ?>
                                    <div class="profile-avatar-placeholder">
                                        <?= strtoupper(substr($member['name'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <span class="fw-semibold small"><?= htmlspecialchars($member['name']) ?></span>
                                    <br>
                                    <small class="text-muted"><?= htmlspecialchars($member['member_code'] ?? '') ?> | <?= htmlspecialchars($member['phone'] ?? '') ?></small>
                                </div>
                            </div>
                        </td>
                        
                        <?php if ($filterMonth && $filterYear && $monthStatus): ?>
                        <td class="text-center">
                            <span class="badge bg-<?= $monthStatus['fine_status_color'] ?>">
                                <?= $monthStatus['fine_status_label'] ?>
                            </span>
                            <?php if ($monthStatus['deposit_count'] > 0): ?>
                            <br><small class="text-muted">জমা: <?= $monthStatus['deposit_count'] ?> টি</small>
                            <?php endif; ?>
                        </td>
                        <td class="text-end fw-bold">
                            <?php if ($monthStatus['fine_id']): ?>
                                <?= number_format($monthStatus['fine_amount'] ?? 0, 2) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-success">
                            <?php if ($monthStatus['fine_id'] && $monthStatus['paid_amount'] > 0): ?>
                                <?= number_format($monthStatus['paid_amount'] ?? 0, 2) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <?php else: ?>
                        <td class="text-end text-primary fw-bolder"><?= number_format($member['total_fine_amount'] ?? 0, 2) ?></td>
                        <td class="text-end text-success fw-bold"><?= number_format($member['total_paid_amount'] ?? 0, 2) ?></td>
                        <td class="text-end text-warning"><?= number_format($member['total_waived_amount'] ?? 0, 2) ?></td>
                        <td class="text-end text-<?= $remaining > 0 ? 'danger' : 'success' ?> fw-bold">
                            <?= number_format($remaining, 2) ?>
                        </td>
                        <?php endif; ?>
                        
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <a href="fine_details.php?member_id=<?= $member['member_id'] ?>" class="btn btn-outline-info" title="বিস্তারিত দেখুন">
                                    <i data-feather="eye" style="width:14px;height:14px"></i>
                                </a>
                                <button class="btn btn-outline-success" onclick="event.stopPropagation(); openGenerateMemberModal(<?= $member['member_id'] ?>, '<?= htmlspecialchars($member['name']) ?>')" title="ফাইন তৈরি">
                                    <i data-feather="plus-circle" style="width:14px;height:14px"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <div class="card-footer py-2">
            <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?tab=members&page=<?= $page-1 ?>&search=<?= urlencode($searchTerm) ?>&filter_month=<?= $filterMonth ?>&filter_year=<?= $filterYear ?>">পূর্ববর্তী</a>
                    </li>
                    <?php endif; ?>
                    
                    <?php 
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    if ($startPage > 1): ?>
                    <li class="page-item"><a class="page-link" href="?tab=members&page=1&search=<?= urlencode($searchTerm) ?>&filter_month=<?= $filterMonth ?>&filter_year=<?= $filterYear ?>">1</a></li>
                    <?php if ($startPage > 2): ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?tab=members&page=<?= $i ?>&search=<?= urlencode($searchTerm) ?>&filter_month=<?= $filterMonth ?>&filter_year=<?= $filterYear ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                    <li class="page-item"><a class="page-link" href="?tab=members&page=<?= $totalPages ?>&search=<?= urlencode($searchTerm) ?>&filter_month=<?= $filterMonth ?>&filter_year=<?= $filterYear ?>"><?= $totalPages ?></a></li>
                    <?php endif; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?tab=members&page=<?= $page+1 ?>&search=<?= urlencode($searchTerm) ?>&filter_month=<?= $filterMonth ?>&filter_year=<?= $filterYear ?>">পরবর্তী</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>

    <?php else: ?>
    
    <div class="row g-3">
        <div class="col-12 col-lg-7">
            <div class="card">
                <div class="card-header py-2">
                    <span class="small fw-bold"><i data-feather="settings" style="width:14px;height:14px"></i> ফাইন কনফিগারেশন</span>
                </div>
                <div class="card-body">
                    <form method="POST" action="?tab=settings" class="row g-3">
                        
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold">ফাইনের নাম</label>
                            <input type="text" name="fine_name" class="form-control form-control-sm" 
                                   value="<?= htmlspecialchars($settings['fine_name'] ?? 'মাসিক বিলম্ব ফাইন') ?>"
                                   placeholder="যেমন: মাসিক বিলম্ব ফাইন">
                            <small class="text-muted">ফাইনের নাম যা রিপোর্টে দেখাবে</small>
                        </div>
                        
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold">ফাইনের পরিমাণ (টাকা)</label>
                            <input type="number" name="fine_amount" class="form-control form-control-sm" 
                                   value="<?= $settings['fine_amount'] ?? 200 ?>" 
                                   step="0.01" min="0">
                            <small class="text-muted">প্রতি মাসে কত টাকা ফাইন হবে</small>
                        </div>
                        
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold">বকেয়ার তারিখ (মাসের তারিখ)</label>
                            <input type="number" name="due_day" class="form-control form-control-sm" 
                                   value="<?= $settings['due_day'] ?? 10 ?>" 
                                   min="1" max="31">
                            <small class="text-muted">প্রতি মাসের এই তারিখের পর গত মাসের ফাইন তৈরি হবে</small>
                        </div>
                        
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold">গ্রেস পিরিয়ড (দিন)</label>
                            <input type="number" name="grace_period_days" class="form-control form-control-sm" 
                                   value="<?= $settings['grace_period_days'] ?? 0 ?>" 
                                   min="0">
                            <small class="text-muted">বকেয়া হওয়ার পর কত দিন পর্যন্ত ফাইন যোগ করা হবে না</small>
                        </div>
                        
                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" class="form-check-input" 
                                       id="isActive" <?= ($settings['is_active'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="isActive">
                                    <i data-feather="toggle-right" style="width:14px;height:14px"></i>
                                    ফাইন সিস্টেম সক্রিয় রাখুন
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" name="update_settings" class="btn btn-primary btn-sm">
                                <i data-feather="save" style="width:14px;height:14px"></i>
                                সেটিংস সংরক্ষণ করুন
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header py-2">
                    <span class="small fw-bold"><i data-feather="users" style="width:14px;height:14px"></i> ফাইন চেকার</span>
                </div>
                <div class="card-body">
                    <div class="small">
                        <p><i data-feather="info" style="width:14px;height:14px"></i> 
                            <strong>কিভাবে কাজ করে:</strong></p>
                        <ul class="mb-2">
                            <li><strong>✅ জমা আছে → ফাইন নেই</strong> (যে সদস্য জমা দিয়েছে তাদের ফাইন হবে না)</li>
                            <li><strong>❌ জমা নেই → ফাইন তৈরি</strong> (যে সদস্য জমা দেয়নি তাদের ফাইন হবে)</li>
                            <li><strong>🔄 স্বয়ংক্রিয় চেক</strong> প্রতিবার পেজ লোড হলে গত মাসের জন্য চেক করে</li>
                            <li><strong>📅 শুধু আগের মাসের জন্য</strong> ফাইন তৈরি করা যায়</li>
                        </ul>
                        
                        <div class="bg-light p-2 rounded">
                            <div class="row g-2">
                                <div class="col-6">
                                    <span class="text-muted">শেষ চেক:</span>
                                    <br><strong><?= $lastCheck ?></strong>
                                    <?php if ($lastCheckMonth != 'কখনও চেক করা হয়নি'): ?>
                                    <span class="badge bg-info"><?= $lastCheckMonth ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="col-6">
                                    <span class="text-muted">বর্তমান চেক:</span>
                                    <br><strong class="text-primary"><?= $prevMonthName ?></strong>
                                </div>
                                <div class="col-12 mt-1">
                                    <span class="text-muted">নিয়ম:</span>
                                    <br><strong class="text-success">✅ জমা আছে → ফাইন নেই</strong>
                                    <span class="text-danger ms-2">❌ জমা নেই → ফাইন তৈরি</span>
                                </div>
                            </div>
                        </div>
                        
                        <form method="POST" action="?tab=settings" class="mt-2">
                            <button type="submit" name="sync_fines" class="btn btn-success btn-sm w-100" 
                                    onclick="this.innerHTML='<i data-feather=loader style=width:14px;height:14px;animation:spin 1s linear infinite></i> চেক হচ্ছে...'; this.disabled=true; return true;">
                                <i data-feather="refresh-cw" style="width:14px;height:14px"></i>
                                ম্যানুয়ালি চেক করুন (<?= $prevMonthName ?>)
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-12 col-lg-5">
            <div class="card">
                <div class="card-header py-2">
                    <span class="small fw-bold"><i data-feather="bar-chart-2" style="width:14px;height:14px"></i> দ্রুত পরিসংখ্যান</span>
                </div>
                <div class="card-body">
                    <?php
                    $statsTotalMembers = $conn->query("SELECT COUNT(*) as count FROM members WHERE status = 'active'")->fetch_assoc()['count'] ?? 0;
                    $statsTotalFines = $conn->query("SELECT COUNT(*) as count FROM fines WHERE status = 'pending' OR status = 'partially_paid'")->fetch_assoc()['count'] ?? 0;
                    $statsTotalPendingAmount = $conn->query("SELECT COALESCE(SUM(fine_amount - paid_amount - waived_amount), 0) as total FROM fines WHERE status = 'pending' OR status = 'partially_paid'")->fetch_assoc()['total'] ?? 0;
                    $statsTotalPaidAmount = $conn->query("SELECT COALESCE(SUM(paid_amount), 0) as total FROM fines")->fetch_assoc()['total'] ?? 0;
                    ?>
                    <div class="row g-2 text-center">
                        <div class="col-3">
                            <div class="p-2 bg-light rounded">
                                <div class="fw-bold"><?= $statsTotalMembers ?></div>
                                <small class="text-muted">সদস্য</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="p-2 bg-light rounded">
                                <div class="fw-bold text-warning"><?= $statsTotalFines ?></div>
                                <small class="text-muted">বকেয়া</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="p-2 bg-light rounded">
                                <div class="fw-bold text-danger"><?= number_format($statsTotalPendingAmount, 0) ?></div>
                                <small class="text-muted">বকেয়া টাকা</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="p-2 bg-light rounded">
                                <div class="fw-bold text-success"><?= number_format($statsTotalPaidAmount, 0) ?></div>
                                <small class="text-muted">আদায়</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header py-2">
                    <span class="small fw-bold"><i data-feather="pie-chart" style="width:14px;height:14px"></i> ফাইন স্ট্যাটাস</span>
                </div>
                <div class="card-body">
                    <?php if (empty($statusSummary)): ?>
                        <p class="text-center text-muted small">কোন ফাইন ডেটা নেই</p>
                    <?php else: ?>
                        <div class="small">
                            <?php foreach ($statusSummary as $status => $data): ?>
                                <?php 
                                $labels = ['pending' => 'বকেয়া', 'partially_paid' => 'আংশিক', 'paid' => 'পেড', 'waived' => 'মওকুফ'];
                                $colors = ['pending' => 'danger', 'partially_paid' => 'warning', 'paid' => 'success', 'waived' => 'secondary'];
                                ?>
                                <div class="d-flex justify-content-between align-items-center border-bottom py-1">
                                    <span><span class="badge bg-<?= $colors[$status] ?? 'secondary' ?>"><?= $labels[$status] ?? $status ?></span></span>
                                    <span><?= $data['count'] ?? 0 ?> টি</span>
                                    <span><?= number_format($data['remaining'] ?? 0, 2) ?> টাকা</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php endif; ?>
</div>

<!-- Generate All Fines Modal -->
<div class="modal fade" id="generateAllModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title fs-6">
                    <i data-feather="users" style="width:18px;height:18px"></i>
                    সব সদস্যের জন্য ফাইন তৈরি
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="generate_all_fines" value="1">
                    
                    <div class="mb-2">
                        <label class="form-label small fw-bold">মাস নির্বাচন করুন</label>
                        <input type="month" name="month_year" class="form-control form-control-sm" 
                               value="<?= date('Y-m', strtotime('-1 month')) ?>" required>
                        <small class="text-muted text-danger">
                            <strong>⚠️ শুধু আগের মাসের জন্য ফাইন তৈরি করা যায়</strong>
                        </small>
                    </div>
                    
                    <div class="alert alert-info small py-1 mb-0">
                        <i data-feather="info" style="width:14px;height:14px"></i>
                        <strong>নিয়ম:</strong> যাদের ওই মাসে জমা আছে তাদের ফাইন হবে না, শুধু যাদের জমা নেই তাদের ফাইন হবে।
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">বাতিল</button>
                    <button type="submit" class="btn btn-success btn-sm">
                        <i data-feather="check" style="width:14px;height:14px"></i>
                        ফাইন তৈরি করুন
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Generate Member Fine Modal -->
<div class="modal fade" id="generateMemberModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title fs-6">
                    <i data-feather="user-plus" style="width:18px;height:18px"></i>
                    সদস্যের জন্য ফাইন তৈরি
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="generate_member_fine" value="1">
                    <input type="hidden" name="member_id" id="memberFineId" value="">
                    
                    <div class="mb-2">
                        <label class="form-label small fw-bold">সদস্য</label>
                        <p class="form-control-plaintext small" id="memberFineName">-</p>
                    </div>
                    
                    <div class="mb-2">
                        <label class="form-label small fw-bold">মাস নির্বাচন করুন</label>
                        <input type="month" name="month_year" class="form-control form-control-sm" 
                               value="<?= date('Y-m', strtotime('-1 month')) ?>" required>
                        <small class="text-muted text-danger">
                            <strong>⚠️ শুধু আগের মাসের জন্য ফাইন তৈরি করা যায়</strong>
                        </small>
                    </div>
                    
                    <div class="alert alert-info small py-1 mb-0">
                        <i data-feather="info" style="width:14px;height:14px"></i>
                        <strong>নিয়ম:</strong> এই সদস্যের ওই মাসে জমা থাকলে ফাইন হবে না।
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">বাতিল</button>
                    <button type="submit" class="btn btn-success btn-sm">
                        <i data-feather="check" style="width:14px;height:14px"></i>
                        ফাইন তৈরি করুন
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.stat-card {
    background: white;
    border-radius: 8px;
    padding: 8px 10px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.06);
    height: 100%;
    transition: transform 0.2s;
    text-align: center;
}
.stat-card:hover {
    transform: translateY(-2px);
}
.stat-card .stat-value {
    font-size: 18px;
    font-weight: 700;
    line-height: 1.2;
}
.stat-card .stat-label {
    font-size: 10px;
    color: #566573;
    font-weight: 500;
}
.bg-success-grad { background: linear-gradient(135deg, #EAFAF1, #D5F5E3); }
.bg-warning-grad { background: linear-gradient(135deg, #FEF9E7, #FDEBD0); }
.bg-info-grad { background: linear-gradient(135deg, #EAF2F8, #D6EAF8); }
.bg-danger-grad { background: linear-gradient(135deg, #FFF7ED, #FED7AA); }
.bg-purple-grad { background: linear-gradient(135deg, #F3E8FF, #E9D5FF); }

.profile-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #e9ecef;
    background-color: #f8f9fa;
    flex-shrink: 0;
}
.profile-avatar-placeholder {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #1B4F72, #2E86C1);
    color: white;
    font-weight: bold;
    font-size: 16px;
    border: 2px solid #e9ecef;
    flex-shrink: 0;
}

.table th {
    font-size: 9px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    color: #566573;
    font-weight: 700;
    border-bottom: 2px solid #e9ecef;
    padding: 6px 4px;
}
.table td {
    vertical-align: middle;
    padding: 6px 4px;
    font-size: 11px;
}
.table tbody tr:hover {
    background-color: #e8f4f8 !important;
}

.btn-group .btn {
    padding: 2px 6px;
    font-size: 11px;
}

.btn-primary {
    background: linear-gradient(135deg, #1B4F72, #2E86C1);
    border: none;
    padding: 4px 12px;
    font-weight: 500;
    box-shadow: 0 2px 6px rgba(27, 79, 114, 0.3);
    transition: all 0.3s;
    font-size: 12px;
}
.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(27, 79, 114, 0.4);
}
.btn-success {
    background: linear-gradient(135deg, #1E8449, #27AE60);
    border: none;
    padding: 4px 12px;
    font-weight: 500;
    box-shadow: 0 2px 6px rgba(30, 132, 73, 0.3);
    transition: all 0.3s;
    font-size: 12px;
}
.btn-success:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(30, 132, 73, 0.4);
}
.btn-outline-primary {
    border-color: #1B4F72;
    color: #1B4F72;
}
.btn-outline-primary:hover {
    background: linear-gradient(135deg, #1B4F72, #2E86C1);
    color: white;
}
.btn-outline-primary.active {
    background: linear-gradient(135deg, #1B4F72, #2E86C1);
    color: white;
}
.btn-outline-secondary.active {
    background: linear-gradient(135deg, #5D6D7E, #95A5A6);
    color: white;
}
.btn-outline-success {
    border-color: #1E8449;
    color: #1E8449;
}
.btn-outline-success:hover {
    background: linear-gradient(135deg, #1E8449, #27AE60);
    color: white;
}

.modal-content {
    border-radius: 10px;
    border: none;
    box-shadow: 0 8px 30px rgba(0,0,0,0.12);
}
.modal-header {
    background: linear-gradient(135deg, #1B4F72, #2E86C1);
    color: white;
    border-radius: 10px 10px 0 0;
    padding: 10px 16px;
}
.modal-title {
    font-weight: 600;
    font-size: 14px;
}
.modal-body {
    padding: 12px 16px;
}
.modal-footer {
    padding: 8px 16px;
    border-top: 1px solid #e9ecef;
}

.form-control, .form-select {
    border-radius: 6px;
    border: 1px solid #ced4da;
    padding: 6px 10px;
    font-size: 12px;
}
.form-control:focus, .form-select:focus {
    border-color: #2E86C1;
    box-shadow: 0 0 0 2px rgba(46, 134, 193, 0.1);
}
.form-label {
    font-size: 11px;
    margin-bottom: 2px;
}

.alert {
    border-radius: 6px;
    padding: 6px 12px;
    border: none;
    font-size: 12px;
}
.alert-success {
    background: #d4edda;
    color: #155724;
}
.alert-danger {
    background: #f8d7da;
    color: #721c24;
}
.alert-info {
    background: #d1ecf1;
    color: #0c5460;
}

.page-link {
    font-size: 12px;
    padding: 4px 10px;
}
.page-item.active .page-link {
    background: linear-gradient(135deg, #1B4F72, #2E86C1);
    border-color: #1B4F72;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

@media (max-width: 576px) {
    .card-body {
        padding: 12px;
    }
    .form-control-sm {
        font-size: 14px;
    }
    .btn-sm {
        font-size: 13px;
        padding: 6px 10px;
    }
    .stat-card .stat-value {
        font-size: 14px;
    }
    .btn-group .btn {
        padding: 4px 6px;
        font-size: 10px;
    }
}
</style>

<script>
function openGenerateAllModal() {
    new bootstrap.Modal(document.getElementById('generateAllModal')).show();
}

function openGenerateMemberModal(memberId, memberName) {
    document.getElementById('memberFineId').value = memberId;
    document.getElementById('memberFineName').textContent = memberName;
    new bootstrap.Modal(document.getElementById('generateMemberModal')).show();
}

document.addEventListener('DOMContentLoaded', function() {
    // Click on table row to go to fine details
    const rows = document.querySelectorAll('.member-row');
    rows.forEach(row => {
        row.addEventListener('click', function(e) {
            if (e.target.closest('.btn') || e.target.closest('a')) {
                return;
            }
            const memberId = this.dataset.memberId;
            if (memberId) {
                window.location.href = 'fine_details.php?member_id=' + memberId;
            }
        });
    });
    
    // Auto-hide alerts after 8 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.remove();
            }, 500);
        }, 8000);
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>