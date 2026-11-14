<?php
// ============================================
// admin/fine_details.php - Single Member Fine Management
// Complete Fine Management for Individual Member
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

$pageTitle = 'সদস্যের ফাইন বিবরণ';
$error = '';
$success = '';

// ============================================
// DATABASE SETUP
// ============================================
function setupFineTables($conn) {
    try {
        $check = $conn->query("SHOW COLUMNS FROM fines LIKE 'waived_amount'");
        if ($check->num_rows == 0) {
            $conn->query("ALTER TABLE fines ADD COLUMN waived_amount DECIMAL(12,2) DEFAULT 0.00 AFTER paid_amount");
        }
    } catch (Exception $e) {
        // Column might already exist
    }
    
    // Ensure deposits table has required columns
    try {
        $checkRef = $conn->query("SHOW COLUMNS FROM deposits LIKE 'reference_id'");
        if ($checkRef->num_rows == 0) {
            $conn->query("ALTER TABLE deposits ADD COLUMN reference_id INT(11) NULL AFTER deposit_type");
        }
        
        $checkRefType = $conn->query("SHOW COLUMNS FROM deposits LIKE 'reference_type'");
        if ($checkRefType->num_rows == 0) {
            $conn->query("ALTER TABLE deposits ADD COLUMN reference_type VARCHAR(50) NULL AFTER reference_id");
        }
    } catch (Exception $e) {
        // Continue
    }
    
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
                fine_name VARCHAR(100) NOT NULL DEFAULT 'Monthly Late Fee',
                fine_amount DECIMAL(12,2) NOT NULL DEFAULT 200.00,
                due_day INT(2) NOT NULL DEFAULT 10,
                grace_period_days INT(3) NOT NULL DEFAULT 0,
                is_active TINYINT(1) DEFAULT 1,
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
            INSERT IGNORE INTO fine_settings (fine_name, fine_amount, due_day, grace_period_days) 
            VALUES ('Monthly Late Fee', 200.00, 10, 0)
        ";
        
        foreach ($queries as $query) {
            try {
                $conn->query($query);
            } catch (Exception $e) {
                // Continue with other queries
            }
        }
    }
}

// ============================================
// FINE SYSTEM CLASS
// ============================================
class FineSystem {
    private $conn;
    private $settings;
    private $adminId;
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->loadSettings();
        $this->ensureWaivedColumn();
        $this->ensureDepositColumns();
        $this->adminId = $_SESSION['admin_id'] ?? null;
    }
    
    private function ensureWaivedColumn() {
        try {
            $check = $this->conn->query("SHOW COLUMNS FROM fines LIKE 'waived_amount'");
            if ($check->num_rows == 0) {
                $this->conn->query("ALTER TABLE fines ADD COLUMN waived_amount DECIMAL(12,2) DEFAULT 0.00 AFTER paid_amount");
            }
        } catch (Exception $e) {
            // Column might already exist
        }
    }
    
    private function ensureDepositColumns() {
        try {
            $checkRef = $this->conn->query("SHOW COLUMNS FROM deposits LIKE 'reference_id'");
            if ($checkRef->num_rows == 0) {
                $this->conn->query("ALTER TABLE deposits ADD COLUMN reference_id INT(11) NULL AFTER deposit_type");
            }
            
            $checkRefType = $this->conn->query("SHOW COLUMNS FROM deposits LIKE 'reference_type'");
            if ($checkRefType->num_rows == 0) {
                $this->conn->query("ALTER TABLE deposits ADD COLUMN reference_type VARCHAR(50) NULL AFTER reference_id");
            }
        } catch (Exception $e) {
            // Continue
        }
    }
    
    private function loadSettings() {
        $result = $this->conn->query("SELECT * FROM fine_settings WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
        $this->settings = $result->fetch_assoc();
        
        if (!$this->settings) {
            $this->settings = [
                'fine_amount' => 200.00,
                'due_day' => 10,
                'grace_period_days' => 0,
                'fine_name' => 'Monthly Late Fee'
            ];
        }
    }
    
    public function getSettings() {
        return $this->settings;
    }
    
    public function getMemberFines($memberId, $filters = []) {
        $sql = "SELECT f.*, m.name, m.member_id as member_code, m.phone, m.email, m.address 
                FROM fines f 
                JOIN members m ON f.member_id = m.id 
                WHERE f.member_id = ?";
        $params = [];
        $types = "i";
        $params[] = $memberId;
        
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $sql .= " AND f.status = ?";
            $types .= 's';
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['month_year'])) {
            $sql .= " AND f.month_year = ?";
            $types .= 's';
            $params[] = $filters['month_year'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (f.notes LIKE ? OR DATE_FORMAT(f.month_year, '%M %Y') LIKE ? OR DATE_FORMAT(f.month_year, '%Y-%m') LIKE ?)";
            $types .= 'sss';
            $search = "%" . $filters['search'] . "%";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $sql .= " ORDER BY f.month_year DESC";
        
        $stmt = $this->conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
        return [];
    }
    
    public function getMemberStats($memberId) {
        $check = $this->conn->query("SHOW COLUMNS FROM fines LIKE 'waived_amount'");
        $hasWaived = $check->num_rows > 0;
        
        if ($hasWaived) {
            $sql = "
                SELECT 
                    COUNT(*) as total_fines,
                    COALESCE(SUM(fine_amount), 0) as total_amount,
                    COALESCE(SUM(paid_amount), 0) as paid_amount,
                    COALESCE(SUM(waived_amount), 0) as waived_amount,
                    COALESCE(SUM(fine_amount - paid_amount - waived_amount), 0) as remaining,
                    COALESCE(SUM(CASE WHEN status = 'pending' OR status = 'partially_paid' THEN fine_amount - paid_amount - waived_amount ELSE 0 END), 0) as pending_amount,
                    COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) as pending_count,
                    COALESCE(SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END), 0) as paid_count,
                    COALESCE(SUM(CASE WHEN status = 'waived' THEN 1 ELSE 0 END), 0) as waived_count,
                    COALESCE(SUM(CASE WHEN status = 'partially_paid' THEN 1 ELSE 0 END), 0) as partial_count
                FROM fines 
                WHERE member_id = ?
            ";
        } else {
            $sql = "
                SELECT 
                    COUNT(*) as total_fines,
                    COALESCE(SUM(fine_amount), 0) as total_amount,
                    COALESCE(SUM(paid_amount), 0) as paid_amount,
                    0 as waived_amount,
                    COALESCE(SUM(fine_amount - paid_amount), 0) as remaining,
                    COALESCE(SUM(CASE WHEN status = 'pending' OR status = 'partially_paid' THEN fine_amount - paid_amount ELSE 0 END), 0) as pending_amount,
                    COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) as pending_count,
                    COALESCE(SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END), 0) as paid_count,
                    COALESCE(SUM(CASE WHEN status = 'waived' THEN 1 ELSE 0 END), 0) as waived_count,
                    COALESCE(SUM(CASE WHEN status = 'partially_paid' THEN 1 ELSE 0 END), 0) as partial_count
                FROM fines 
                WHERE member_id = ?
            ";
        }
        
        $stmt = $this->conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $memberId);
            $stmt->execute();
            return $stmt->get_result()->fetch_assoc();
        }
        return null;
    }
    
    public function getMemberInfo($memberId) {
        $stmt = $this->conn->prepare("SELECT * FROM members WHERE id = ?");
        $stmt->bind_param('i', $memberId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
    
    public function getFinePayments($fineId) {
        $stmt = $this->conn->prepare("
            SELECT * FROM fine_payments 
            WHERE fine_id = ? 
            ORDER BY payment_date DESC
        ");
        $stmt->bind_param('i', $fineId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    public function generateMemberFine($memberId, $monthYear = null) {
        if ($monthYear === null) {
            $monthYear = date('Y-m-01');
        }
        
        $monthYear = date('Y-m-01', strtotime($monthYear));
        $dueDay = $this->settings['due_day'];
        $dueDate = date('Y-m-' . $dueDay, strtotime($monthYear));
        $fineAmount = $this->settings['fine_amount'];
        
        $memberInfo = $this->conn->prepare("SELECT name, member_id FROM members WHERE id = ?");
        $memberInfo->bind_param('i', $memberId);
        $memberInfo->execute();
        $member = $memberInfo->get_result()->fetch_assoc();
        
        $checkFine = $this->conn->prepare("SELECT id, status FROM fines WHERE member_id = ? AND month_year = ?");
        $checkFine->bind_param('is', $memberId, $monthYear);
        $checkFine->execute();
        $existingFine = $checkFine->get_result()->fetch_assoc();
        
        $nextMonth = date('Y-m-01', strtotime($monthYear . ' +1 month'));
        $depositStmt = $this->conn->prepare("
            SELECT deposit_date, amount, deposit_type 
            FROM deposits 
            WHERE member_id = ? 
            AND deposit_date >= ? 
            AND deposit_date < ?
            AND deposit_type IN ('regular', 'special', 'monthly', 'deposit')
            ORDER BY deposit_date ASC 
            LIMIT 1
        ");
        $depositStmt->bind_param('iss', $memberId, $monthYear, $nextMonth);
        $depositStmt->execute();
        $depositData = $depositStmt->get_result()->fetch_assoc();
        
        $depositedOnTime = false;
        $depositDate = null;
        $hasDeposit = false;
        
        if ($depositData) {
            $hasDeposit = true;
            $depositDate = $depositData['deposit_date'];
            if (strtotime($depositDate) <= strtotime($dueDate)) {
                $depositedOnTime = true;
            }
        }
        
        if ($depositedOnTime) {
            if ($existingFine) {
                $this->deleteFine($existingFine['id']);
                return [
                    'success' => true,
                    'message' => "সদস্য সময়ে জমা দিয়েছেন ({$depositDate}), ফাইন স্বয়ংক্রিয়ভাবে বাদ দেওয়া হয়েছে।",
                    'action' => 'removed'
                ];
            }
            
            return [
                'success' => false,
                'message' => "সদস্য সময়ে জমা দিয়েছেন ({$depositDate}), ফাইন প্রয়োজন নেই।",
                'action' => 'skipped'
            ];
        }
        
        if (!$hasDeposit || !$depositedOnTime) {
            if ($existingFine) {
                if ($existingFine['status'] == 'paid') {
                    return [
                        'success' => false,
                        'message' => 'ফাইন আগেই পে করা হয়েছে।'
                    ];
                }
                return [
                    'success' => false,
                    'message' => 'এই মাসের জন্য ফাইন আগেই তৈরি করা হয়েছে।'
                ];
            }
            
            $note = $hasDeposit ? 
                "Late deposit: Deposited on {$depositDate} (Due date: {$dueDate}) - Fine applied" : 
                "No deposit found for this month - Fine applied";
            
            $stmt = $this->conn->prepare("
                INSERT INTO fines (member_id, month_year, fine_amount, due_date, status, waived_amount, notes) 
                VALUES (?, ?, ?, ?, 'pending', 0, ?)
            ");
            $stmt->bind_param('isdss', $memberId, $monthYear, $fineAmount, $dueDate, $note);
            
            if ($stmt->execute()) {
                $fineId = $this->conn->insert_id;
                return [
                    'success' => true,
                    'message' => $hasDeposit ? 
                        "ফাইন তৈরি হয়েছে (জমা দেরিতে হয়েছে: {$depositDate})" : 
                        "ফাইন তৈরি হয়েছে (এই মাসে কোনো জমা নেই)",
                    'fine_id' => $fineId,
                    'amount' => $fineAmount,
                    'due_date' => $dueDate,
                    'action' => 'created'
                ];
            }
        }
        
        return [
            'success' => false,
            'message' => 'ফাইন তৈরি করতে ব্যর্থ হয়েছে।'
        ];
    }
    
    public function generateAllFines($monthYear = null) {
        if ($monthYear === null) {
            $monthYear = date('Y-m-01');
        }
        
        $monthYear = date('Y-m-01', strtotime($monthYear));
        $dueDay = $this->settings['due_day'];
        $dueDate = date('Y-m-' . $dueDay, strtotime($monthYear));
        $fineAmount = $this->settings['fine_amount'];
        
        $members = $this->conn->query("SELECT id, name FROM members WHERE status = 'active'");
        
        $generated = 0;
        $removed = 0;
        $skipped = 0;
        $errors = 0;
        $results = [];
        $nextMonth = date('Y-m-01', strtotime($monthYear . ' +1 month'));
        
        while ($member = $members->fetch_assoc()) {
            $checkFine = $this->conn->prepare("SELECT id, status FROM fines WHERE member_id = ? AND month_year = ?");
            $checkFine->bind_param('is', $member['id'], $monthYear);
            $checkFine->execute();
            $existingFine = $checkFine->get_result()->fetch_assoc();
            
            $depositStmt = $this->conn->prepare("
                SELECT deposit_date, amount, deposit_type 
                FROM deposits 
                WHERE member_id = ? 
                AND deposit_date >= ? 
                AND deposit_date < ?
                AND deposit_type IN ('regular', 'special', 'monthly', 'deposit')
                ORDER BY deposit_date ASC 
                LIMIT 1
            ");
            $depositStmt->bind_param('iss', $member['id'], $monthYear, $nextMonth);
            $depositStmt->execute();
            $depositData = $depositStmt->get_result()->fetch_assoc();
            
            $depositedOnTime = false;
            $depositDate = null;
            $hasDeposit = false;
            
            if ($depositData) {
                $hasDeposit = true;
                $depositDate = $depositData['deposit_date'];
                if (strtotime($depositDate) <= strtotime($dueDate)) {
                    $depositedOnTime = true;
                }
            }
            
            if ($depositedOnTime) {
                if ($existingFine) {
                    $this->deleteFine($existingFine['id']);
                    $removed++;
                    $results[] = [
                        'member_id' => $member['id'],
                        'name' => $member['name'],
                        'status' => 'removed',
                        'message' => "Fine removed - deposited on {$depositDate}"
                    ];
                } else {
                    $skipped++;
                    $results[] = [
                        'member_id' => $member['id'],
                        'name' => $member['name'],
                        'status' => 'skipped',
                        'message' => "Deposited on time - no fine needed"
                    ];
                }
                continue;
            }
            
            if ($existingFine) {
                $skipped++;
                $results[] = [
                    'member_id' => $member['id'],
                    'name' => $member['name'],
                    'status' => 'exists',
                    'message' => "Fine already exists"
                ];
                continue;
            }
            
            $note = $hasDeposit ? 
                "Late deposit: Deposited on {$depositDate} (Due date: {$dueDate})" : 
                "No deposit found for this month";
            
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
                    'message' => $hasDeposit ? "Late deposit - fine applied" : "No deposit - fine applied"
                ];
            } else {
                $errors++;
                $results[] = [
                    'member_id' => $member['id'],
                    'name' => $member['name'],
                    'status' => 'error',
                    'message' => 'Failed to create fine'
                ];
            }
        }
        
        return [
            'success' => true,
            'generated' => $generated,
            'removed' => $removed,
            'skipped' => $skipped,
            'errors' => $errors,
            'results' => $results,
            'month' => $monthYear,
            'message' => "{$generated} ফাইন তৈরি, {$removed} ফাইন বাদ, {$skipped} স্কিপ"
        ];
    }
    
    // ============================================
    // PAY FINE - FIXED WITH DEPOSIT TYPE 'fine'
    // ============================================
    public function payFine($fineId, $amount, $paymentMethod = 'cash', $transactionNo = null, $receiptNo = null, $notes = null) {
        $stmt = $this->conn->prepare("SELECT * FROM fines WHERE id = ?");
        $stmt->bind_param('i', $fineId);
        $stmt->execute();
        $fine = $stmt->get_result()->fetch_assoc();
        
        if (!$fine) {
            return ['success' => false, 'message' => 'Fine not found'];
        }
        
        $check = $this->conn->query("SHOW COLUMNS FROM fines LIKE 'waived_amount'");
        $hasWaived = $check->num_rows > 0;
        $waivedAmount = $hasWaived ? ($fine['waived_amount'] ?? 0) : 0;
        
        $remaining = $fine['fine_amount'] - $fine['paid_amount'] - $waivedAmount;
        
        if ($amount > $remaining) {
            return ['success' => false, 'message' => 'Amount exceeds remaining balance'];
        }
        
        $this->conn->begin_transaction();
        
        try {
            $newPaidAmount = $fine['paid_amount'] + $amount;
            $status = 'partially_paid';
            $paidDate = null;
            
            if ($newPaidAmount >= ($fine['fine_amount'] - $waivedAmount)) {
                $status = 'paid';
                $paidDate = date('Y-m-d');
            }
            
            // 1. Update fines table
            $updateStmt = $this->conn->prepare("
                UPDATE fines 
                SET paid_amount = ?, status = ?, paid_date = ? 
                WHERE id = ?
            ");
            $updateStmt->bind_param('dssi', $newPaidAmount, $status, $paidDate, $fineId);
            $updateStmt->execute();
            
            // 2. Insert into fine_payments
            $payStmt = $this->conn->prepare("
                INSERT INTO fine_payments (fine_id, amount, payment_date, payment_method, transaction_no, receipt_no, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $paymentDate = date('Y-m-d');
            $payStmt->bind_param('idsssss', $fineId, $amount, $paymentDate, $paymentMethod, $transactionNo, $receiptNo, $notes);
            $payStmt->execute();
            $paymentId = $this->conn->insert_id;
            
            // 3. INSERT INTO DEPOSITS WITH 'fine' TYPE AND REFERENCE
            $checkDepositTable = $this->conn->query("SHOW TABLES LIKE 'deposits'");
            if ($checkDepositTable->num_rows > 0) {
                $memberId = $fine['member_id'];
                $depositNote = "Fine payment for fine ID: $fineId" . ($notes ? " - $notes" : "");
                $collectedBy = $this->adminId;
                
                // Check if reference_id and reference_type columns exist
                $checkRef = $this->conn->query("SHOW COLUMNS FROM deposits LIKE 'reference_id'");
                $hasReference = $checkRef->num_rows > 0;
                
                if ($hasReference) {
                    $depositStmt = $this->conn->prepare("
                        INSERT INTO deposits (
                            member_id, 
                            amount, 
                            deposit_date, 
                            deposit_type, 
                            payment_method, 
                            transaction_no, 
                            receipt_no, 
                            note, 
                            collected_by,
                            reference_id,
                            reference_type
                        ) VALUES (?, ?, ?, 'fine', ?, ?, ?, ?, ?, ?, 'fine_payment')
                    ");
                    
                    $depositStmt->bind_param(
                        'idsdssssi',
                        $memberId,
                        $amount,
                        $paymentDate,
                        $paymentMethod,
                        $transactionNo,
                        $receiptNo,
                        $depositNote,
                        $collectedBy,
                        $paymentId  // reference_id
                    );
                } else {
                    $depositStmt = $this->conn->prepare("
                        INSERT INTO deposits (
                            member_id, 
                            amount, 
                            deposit_date, 
                            deposit_type, 
                            payment_method, 
                            transaction_no, 
                            receipt_no, 
                            note, 
                            collected_by
                        ) VALUES (?, ?, ?, 'fine', ?, ?, ?, ?, ?)
                    ");
                    
                    $depositStmt->bind_param(
                        'idsdssssi',
                        $memberId,
                        $amount,
                        $paymentDate,
                        $paymentMethod,
                        $transactionNo,
                        $receiptNo,
                        $depositNote,
                        $collectedBy
                    );
                }
                
                if (!$depositStmt->execute()) {
                    throw new Exception("Failed to insert into deposits: " . $depositStmt->error);
                }
                
                $depositId = $this->conn->insert_id;
            } else {
                error_log("WARNING: deposits table not found - fine payment recorded but not deposited");
            }
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => '✅ Payment recorded successfully and deposited!',
                'new_status' => $status,
                'payment_id' => $paymentId,
                'deposit_id' => $depositId ?? null
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'message' => 'Payment failed: ' . $e->getMessage()];
        }
    }
    
    // ============================================
    // DELETE FINE - COMPLETELY FIXED
    // ============================================
    public function deleteFine($fineId) {
        // Step 1: Get fine details
        $fineStmt = $this->conn->prepare("SELECT member_id FROM fines WHERE id = ?");
        $fineStmt->bind_param('i', $fineId);
        $fineStmt->execute();
        $fine = $fineStmt->get_result()->fetch_assoc();
        
        if ($fine) {
            // Step 2: Get all fine payment IDs for this fine
            $paymentsStmt = $this->conn->prepare("SELECT id FROM fine_payments WHERE fine_id = ?");
            $paymentsStmt->bind_param('i', $fineId);
            $paymentsStmt->execute();
            $paymentIds = $paymentsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // Step 3: Delete deposits using reference_id (MOST RELIABLE)
            foreach ($paymentIds as $payment) {
                // Delete by reference_id and reference_type
                $delRefStmt = $this->conn->prepare("
                    DELETE FROM deposits 
                    WHERE reference_id = ? 
                    AND reference_type = 'fine_payment'
                ");
                $delRefStmt->bind_param('i', $payment['id']);
                $delRefStmt->execute();
                
                // Also delete by member_id and deposit_type (fallback)
                $delFallbackStmt = $this->conn->prepare("
                    DELETE FROM deposits 
                    WHERE member_id = ? 
                    AND deposit_type = 'fine'
                    AND reference_id = ?
                ");
                $delFallbackStmt->bind_param('ii', $fine['member_id'], $payment['id']);
                $delFallbackStmt->execute();
            }
            
            // Step 4: Delete by note pattern (last resort)
            $notePattern = "Fine payment for fine ID: $fineId%";
            $delNoteStmt = $this->conn->prepare("
                DELETE FROM deposits 
                WHERE member_id = ? 
                AND deposit_type = 'fine' 
                AND note LIKE ?
            ");
            $delNoteStmt->bind_param('is', $fine['member_id'], $notePattern);
            $delNoteStmt->execute();
        }
        
        // Step 5: Delete fine payments
        $stmt = $this->conn->prepare("DELETE FROM fine_payments WHERE fine_id = ?");
        $stmt->bind_param('i', $fineId);
        $stmt->execute();
        
        // Step 6: Delete the fine
        $stmt = $this->conn->prepare("DELETE FROM fines WHERE id = ?");
        $stmt->bind_param('i', $fineId);
        $result = $stmt->execute();
        
        // Step 7: Log the deletion
        if ($result) {
            error_log("FINE DELETED: Fine ID #{$fineId} and all linked deposits removed");
        }
        
        return $result;
    }
    
    public function waiveFine($fineId, $amount = null, $notes = null) {
        $stmt = $this->conn->prepare("SELECT * FROM fines WHERE id = ?");
        $stmt->bind_param('i', $fineId);
        $stmt->execute();
        $fine = $stmt->get_result()->fetch_assoc();
        
        if (!$fine) {
            return ['success' => false, 'message' => 'Fine not found'];
        }
        
        $check = $this->conn->query("SHOW COLUMNS FROM fines LIKE 'waived_amount'");
        $hasWaived = $check->num_rows > 0;
        
        if (!$hasWaived) {
            $this->conn->query("ALTER TABLE fines ADD COLUMN waived_amount DECIMAL(12,2) DEFAULT 0.00 AFTER paid_amount");
            $waivedAmount = 0;
        } else {
            $waivedAmount = $fine['waived_amount'] ?? 0;
        }
        
        if ($amount === null) {
            $amount = $fine['fine_amount'] - $fine['paid_amount'] - $waivedAmount;
        }
        
        $newWaivedAmount = $waivedAmount + $amount;
        $status = 'partially_paid';
        
        if ($newWaivedAmount >= ($fine['fine_amount'] - $fine['paid_amount'])) {
            $status = 'waived';
        }
        
        $waiveNotes = $notes ?: 'Fine waived by admin';
        $currentNotes = $fine['notes'] ?? '';
        $newNotes = $currentNotes ? $currentNotes . ' | Waived: ' . $waiveNotes : 'Waived: ' . $waiveNotes;
        
        $stmt = $this->conn->prepare("
            UPDATE fines 
            SET waived_amount = ?, status = ?, notes = ? 
            WHERE id = ?
        ");
        $stmt->bind_param('dssi', $newWaivedAmount, $status, $newNotes, $fineId);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Fine waived successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to waive fine'];
    }
    
    public function updateFine($fineId, $data) {
        $stmt = $this->conn->prepare("
            UPDATE fines 
            SET fine_amount = ?, due_date = ?, notes = ? 
            WHERE id = ?
        ");
        $stmt->bind_param('dssi', $data['fine_amount'], $data['due_date'], $data['notes'], $fineId);
        return $stmt->execute();
    }
    
    public function getFineDetails($fineId) {
        $stmt = $this->conn->prepare("
            SELECT f.*, m.name, m.member_id, m.phone 
            FROM fines f 
            JOIN members m ON f.member_id = m.id 
            WHERE f.id = ?
        ");
        $stmt->bind_param('i', $fineId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
    
    public function getReceiptData($fineId) {
        $stmt = $this->conn->prepare("
            SELECT f.*, m.name, m.member_id, m.phone, m.address,
                   (SELECT COALESCE(SUM(amount), 0) FROM fine_payments WHERE fine_id = f.id) as total_paid,
                   (SELECT COUNT(*) FROM fine_payments WHERE fine_id = f.id) as payment_count
            FROM fines f 
            JOIN members m ON f.member_id = m.id 
            WHERE f.id = ?
        ");
        $stmt->bind_param('i', $fineId);
        $stmt->execute();
        $fine = $stmt->get_result()->fetch_assoc();
        
        if ($fine) {
            $payStmt = $this->conn->prepare("
                SELECT * FROM fine_payments 
                WHERE fine_id = ? 
                ORDER BY payment_date DESC
            ");
            $payStmt->bind_param('i', $fineId);
            $payStmt->execute();
            $fine['payments'] = $payStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
        
        return $fine;
    }
    
    public function generateFineForMember($memberId, $monthYear, $fineAmount = null) {
        if ($fineAmount === null) {
            $fineAmount = $this->settings['fine_amount'];
        }
        
        $monthYear = date('Y-m-01', strtotime($monthYear));
        $dueDay = $this->settings['due_day'];
        $dueDate = date('Y-m-' . $dueDay, strtotime($monthYear));
        
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
        $note = "Manual fine created for " . date('F Y', strtotime($monthYear));
        $stmt->bind_param('isdss', $memberId, $monthYear, $fineAmount, $dueDate, $note);
        
        if ($stmt->execute()) {
            return [
                'success' => true,
                'message' => 'ফাইন সফলভাবে তৈরি করা হয়েছে!',
                'fine_id' => $this->conn->insert_id
            ];
        }
        
        return ['success' => false, 'message' => 'ফাইন তৈরি করতে ব্যর্থ হয়েছে।'];
    }
}

// ============================================
// AJAX HANDLERS
// ============================================
try {
    $conn = conn();
    setupFineTables($conn);
    $fineSystem = new FineSystem($conn);
    
    if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_fine_edit' && isset($_GET['id'])) {
        header('Content-Type: application/json');
        
        try {
            $fineId = (int)$_GET['id'];
            $fine = $fineSystem->getFineDetails($fineId);
            
            if (!$fine) {
                echo json_encode(['success' => false, 'message' => 'Fine not found']);
                exit;
            }
            
            echo json_encode([
                'success' => true,
                'id' => $fine['id'],
                'member_name' => $fine['name'],
                'member_id' => $fine['member_id'],
                'month_year' => $fine['month_year'],
                'month' => date('F Y', strtotime($fine['month_year'])),
                'fine_amount' => $fine['fine_amount'],
                'due_date' => $fine['due_date'],
                'notes' => $fine['notes'],
                'status' => $fine['status']
            ]);
            exit;
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
    
    if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_receipt' && isset($_GET['id'])) {
        header('Content-Type: application/json');
        $fineId = (int)$_GET['id'];
        $data = $fineSystem->getReceiptData($fineId);
        
        if ($data) {
            $settings = $fineSystem->getSettings();
            $data['settings'] = $settings;
            echo json_encode(['success' => true, 'data' => $data]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Fine not found']);
        }
        exit;
    }
    
    $memberId = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;
    
    if ($memberId <= 0) {
        $_SESSION['error'] = 'সদস্য নির্বাচন করুন';
        header('Location: fines_members.php');
        exit;
    }
    
    $statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
    $monthFilter = isset($_GET['month']) ? $_GET['month'] : '';
    $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    if (isset($_GET['generate_fine']) && $_GET['generate_fine'] === 'yes') {
        if (!verifyCSRF($_GET['csrf_token'] ?? '')) {
            $error = 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে।';
        } else {
            $monthYear = isset($_GET['month_year']) ? $_GET['month_year'] : date('Y-m-01');
            $result = $fineSystem->generateFineForMember($memberId, $monthYear);
            if ($result['success']) {
                $success = $result['message'] . " (মাস: " . date('F Y', strtotime($monthYear)) . ")";
                logActivity('fine_generated', "Fine generated for member #{$memberId} for {$monthYear}");
                header("Location: fine_details.php?member_id={$memberId}&success=" . urlencode($success));
                exit;
            } else {
                $error = $result['message'];
            }
        }
    }
    
    if (isset($_GET['generate_all_fines']) && $_GET['generate_all_fines'] === 'yes') {
        if (!verifyCSRF($_GET['csrf_token'] ?? '')) {
            $error = 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে।';
        } else {
            $monthYear = isset($_GET['month_year']) ? $_GET['month_year'] : date('Y-m-01');
            $result = $fineSystem->generateAllFines($monthYear);
            if ($result['success']) {
                $success = "ফাইন তৈরি সম্পূর্ণ:";
                $success .= " {$result['generated']} টি তৈরি";
                if ($result['removed'] > 0) {
                    $success .= ", {$result['removed']} টি বাদ (সময়ে জমা দেওয়ায়)";
                }
                if ($result['skipped'] > 0) {
                    $success .= ", {$result['skipped']} টি স্কিপ";
                }
                if ($result['errors'] > 0) {
                    $success .= ", {$result['errors']} টি ত্রুটি";
                }
                logActivity('all_fines_generated', "All fines generated for {$monthYear}");
                header("Location: fine_details.php?member_id={$memberId}&success=" . urlencode($success));
                exit;
            } else {
                $error = 'ফাইন তৈরি করতে ব্যর্থ হয়েছে।';
            }
        }
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_fine'])) {
        if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
            $error = 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে।';
        } else {
            $fineId = (int)($_POST['fine_id'] ?? 0);
            $amount = (float)($_POST['amount'] ?? 0);
            $paymentMethod = $_POST['payment_method'] ?? 'cash';
            $transactionNo = $_POST['transaction_no'] ?? null;
            $receiptNo = $_POST['receipt_no'] ?? null;
            $notes = $_POST['payment_notes'] ?? null;
            
            if ($amount <= 0) {
                $error = 'সঠিক পরিমাণ দিন।';
            } else {
                $result = $fineSystem->payFine($fineId, $amount, $paymentMethod, $transactionNo, $receiptNo, $notes);
                if ($result['success']) {
                    $success = $result['message'];
                    logActivity('fine_payment', "Fine payment recorded for fine #{$fineId}");
                    header("Location: fine_details.php?member_id={$memberId}&success=" . urlencode($success));
                    exit;
                } else {
                    $error = $result['message'];
                }
            }
        }
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_fine'])) {
        if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
            $error = 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে।';
        } else {
            $fineId = (int)($_POST['fine_id'] ?? 0);
            $data = [
                'fine_amount' => (float)($_POST['fine_amount'] ?? 0),
                'due_date' => $_POST['due_date'] ?? date('Y-m-d'),
                'notes' => $_POST['notes'] ?? null
            ];
            
            if ($data['fine_amount'] <= 0) {
                $error = 'সঠিক পরিমাণ দিন।';
            } else {
                if ($fineSystem->updateFine($fineId, $data)) {
                    $success = 'ফাইন সফলভাবে আপডেট করা হয়েছে।';
                    logActivity('fine_updated', "Fine #{$fineId} updated");
                    header("Location: fine_details.php?member_id={$memberId}&success=" . urlencode($success));
                    exit;
                } else {
                    $error = 'ফাইন আপডেট করতে ব্যর্থ হয়েছে।';
                }
            }
        }
    }
    
    if (isset($_GET['delete']) && $_GET['delete'] > 0) {
        if (!verifyCSRF($_GET['csrf_token'] ?? '')) {
            $error = 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে।';
        } else {
            $fineId = (int)$_GET['delete'];
            if ($fineSystem->deleteFine($fineId)) {
                $success = 'ফাইন এবং সংশ্লিষ্ট ডিপোজিট ডিলিট করা হয়েছে।';
                logActivity('fine_deleted', "Fine #{$fineId} deleted with linked deposits");
                header("Location: fine_details.php?member_id={$memberId}&success=" . urlencode($success));
                exit;
            } else {
                $error = 'ফাইন ডিলিট করতে ব্যর্থ হয়েছে।';
            }
        }
    }
    
    if (isset($_GET['waive']) && $_GET['waive'] > 0) {
        if (!verifyCSRF($_GET['csrf_token'] ?? '')) {
            $error = 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে।';
        } else {
            $fineId = (int)$_GET['waive'];
            $notes = isset($_GET['notes']) ? $_GET['notes'] : null;
            $result = $fineSystem->waiveFine($fineId, null, $notes);
            if ($result['success']) {
                $success = $result['message'];
                logActivity('fine_waived', "Fine #{$fineId} waived");
                header("Location: fine_details.php?member_id={$memberId}&success=" . urlencode($success));
                exit;
            } else {
                $error = $result['message'];
            }
        }
    }
    
    $memberInfo = $fineSystem->getMemberInfo($memberId);
    if (!$memberInfo) {
        $_SESSION['error'] = 'সদস্য পাওয়া যায়নি';
        header('Location: fines_members.php');
        exit;
    }
    
    $filters = [];
    if ($statusFilter !== 'all') {
        $filters['status'] = $statusFilter;
    }
    if ($monthFilter) {
        $filters['month_year'] = date('Y-m-01', strtotime($monthFilter));
    }
    if ($searchTerm) {
        $filters['search'] = $searchTerm;
    }
    
    $fines = $fineSystem->getMemberFines($memberId, $filters);
    $stats = $fineSystem->getMemberStats($memberId);
    
    $totalFines = count($fines);
    $totalAmount = array_sum(array_column($fines, 'fine_amount'));
    $totalPaid = array_sum(array_column($fines, 'paid_amount'));
    $totalWaived = array_sum(array_column($fines, 'waived_amount') ?: array_fill(0, count($fines), 0));
    $totalRemaining = $totalAmount - $totalPaid - $totalWaived;
    
    $statusLabels = [
        'pending' => 'বকেয়া',
        'partially_paid' => 'আংশিক',
        'paid' => 'পেইড',
        'waived' => 'মওকুফ'
    ];
    
    $statusColors = [
        'pending' => 'danger',
        'partially_paid' => 'warning',
        'paid' => 'success',
        'waived' => 'secondary'
    ];
    
    if (isset($_GET['success'])) {
        $success = $_GET['success'];
    }
    
    $settings = $fineSystem->getSettings();
    
    $photoFileName = !empty($memberInfo['photo']) ? $memberInfo['photo'] : '';
    
    if (!empty($photoFileName)) {
        if (strpos($photoFileName, '/') !== false) {
            $photoFileName = basename($photoFileName);
        }
        $photoPath = '../uploads/members/' . $photoFileName;
        $photoUrl = '/uploads/members/' . $photoFileName;
        $fullServerPath = $_SERVER['DOCUMENT_ROOT'] . '/uploads/members/' . $photoFileName;
        $hasPhoto = file_exists($fullServerPath);
    } else {
        $photoPath = '';
        $photoUrl = '';
        $hasPhoto = false;
    }
    
} catch (Exception $e) {
    $error = 'সিস্টেম লোড করতে ব্যর্থ হয়েছে: ' . $e->getMessage();
    $fines = [];
    $stats = ['pending_count' => 0, 'pending_amount' => 0, 'paid_amount' => 0, 'waived_count' => 0, 'waived_amount' => 0, 'total_amount' => 0, 'total_fines' => 0];
    $hasPhoto = false;
    $photoPath = '';
    $photoUrl = '';
}

// Include header
require_once __DIR__ . '/../includes/header.php';
?>

<!-- HTML CONTENT - Same as before (no changes needed) -->
<div class="container-fluid px-2 px-md-4" style="max-height: 100vh; overflow-y: auto; padding-bottom: 80px;">
    
    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 mt-2 gap-2">
        <h4 class="mb-0 fs-6 fs-md-4">
            <i data-feather="user" style="width:20px;height:20px"></i> 
            ফাইন বিবরণ
        </h4>
        <div class="d-flex gap-1 flex-wrap">
            <a href="fines.php?tab=members" class="btn btn-outline-secondary btn-sm">
                <i data-feather="arrow-left" style="width:14px;height:14px"></i>
                ফিরে যান
            </a>
            <button class="btn btn-success btn-sm" onclick="openGenerateFineModal()">
                <i data-feather="plus-circle" style="width:14px;height:14px"></i>
                ফাইন তৈরি করুন
            </button>
            <button class="btn btn-warning btn-sm" onclick="generateAllFines()">
                <i data-feather="refresh-cw" style="width:14px;height:14px"></i>
                সব ফাইন তৈরি
            </button>
        </div>
    </div>

    <!-- Member Info -->
    <div class="card mb-3">
        <div class="card-body p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="flex-shrink-0">
                    <?php if ($hasPhoto && !empty($photoUrl)): ?>
                        <img src="<?= htmlspecialchars($photoUrl) ?>" 
                             alt="<?= htmlspecialchars($memberInfo['name']) ?>" 
                             class="profile-avatar-large"
                             onerror="this.style.display='none'; this.parentElement.querySelector('.profile-avatar-placeholder-large').style.display='flex';">
                        <div class="profile-avatar-placeholder-large" style="display:none;">
                            <?= strtoupper(substr($memberInfo['name'], 0, 1)) ?>
                        </div>
                    <?php else: ?>
                        <div class="profile-avatar-placeholder-large">
                            <?= strtoupper(substr($memberInfo['name'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="flex-grow-1">
                    <h5 class="mb-1"><?= htmlspecialchars($memberInfo['name']) ?></h5>
                    <div class="row g-1 small">
                        <div class="col-6 col-md-3">
                            <span class="text-muted">সদস্য আইডি:</span>
                            <span class="fw-bold"><?= htmlspecialchars($memberInfo['member_id']) ?></span>
                        </div>
                        <div class="col-6 col-md-3">
                            <span class="text-muted">ফোন:</span>
                            <span class="fw-bold"><?= htmlspecialchars($memberInfo['phone'] ?? 'N/A') ?></span>
                        </div>
                        <div class="col-6 col-md-3">
                            <span class="text-muted">ইমেইল:</span>
                            <span class="fw-bold"><?= htmlspecialchars($memberInfo['email'] ?? 'N/A') ?></span>
                        </div>
                        <div class="col-6 col-md-3">
                            <span class="text-muted">ঠিকানা:</span>
                            <span class="fw-bold"><?= htmlspecialchars($memberInfo['address'] ?? 'N/A') ?></span>
                        </div>
                    </div>
                    <div class="mt-1">
                        <small class="text-muted">ফাইনের নিয়ম: প্রতি মাসের <?= $settings['due_day'] ?? 10 ?> তারিখের মধ্যে জমা না দিলে <strong><?= number_format($settings['fine_amount'] ?? 200, 2) ?></strong> টাকা ফাইন হবে।</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="stat-card p-2">
                <div class="stat-value fs-5 text-warning"><?= $stats['pending_count'] ?? 0 ?>টি</div>
                <div class="stat-label small">বকেয়া ফাইন</div>
                <small class="text-muted"><?= number_format($stats['pending_amount'] ?? 0, 2) ?> টাকা</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card bg-success-grad p-2">
                <div class="stat-value fs-5 text-success"><?= number_format($stats['paid_amount'] ?? 0, 2) ?></div>
                <div class="stat-label small">আদায়কৃত</div>
                <small class="text-muted"><?= $stats['paid_count'] ?? 0 ?> টি ফাইন</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card bg-warning-grad p-2">
                <div class="stat-value fs-5 text-warning"><?= $stats['waived_count'] ?? 0 ?>টি</div>
                <div class="stat-label small">মওকুফ</div>
                <small class="text-muted"><?= number_format($stats['waived_amount'] ?? 0, 2) ?> টাকা</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card p-2">
                <div class="stat-value fs-5"><?= number_format($stats['total_amount'] ?? 0, 2) ?></div>
                <div class="stat-label small">মোট ফাইন</div>
                <small class="text-muted"><?= $stats['total_fines'] ?? 0 ?> টি</small>
            </div>
        </div>
    </div>

    <!-- Filter & Search -->
    <div class="card mb-3">
        <div class="card-body p-2">
            <form method="GET" class="row g-1" id="filterForm">
                <input type="hidden" name="member_id" value="<?= $memberId ?>">
                
                <div class="col-3 col-md-2">
                    <select name="status" class="form-select form-select-sm" onchange="document.getElementById('filterForm').submit()">
                        <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>সব</option>
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>বকেয়া</option>
                        <option value="partially_paid" <?= $statusFilter === 'partially_paid' ? 'selected' : '' ?>>আংশিক</option>
                        <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>পেড</option>
                        <option value="waived" <?= $statusFilter === 'waived' ? 'selected' : '' ?>>মওকুফ</option>
                    </select>
                </div>
                <div class="col-3 col-md-2">
                    <input type="month" name="month" class="form-control form-control-sm" 
                           value="<?= $monthFilter ?>" onchange="document.getElementById('filterForm').submit()">
                </div>
                <div class="col-4 col-md-5">
                    <input type="text" name="search" class="form-control form-control-sm" 
                           placeholder="নোট বা মাস দিয়ে খুঁজুন..." 
                           value="<?= htmlspecialchars($searchTerm) ?>">
                </div>
                <div class="col-2 col-md-3">
                    <button type="submit" class="btn btn-primary btn-sm w-100">খুঁজুন</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Messages -->
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

    <!-- Fines Table -->
    <div class="card">
        <div class="card-header py-2">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-1">
                <span class="small fw-bold"><i data-feather="list" style="width:14px;height:14px"></i> ফাইন ট্রানজেকশন</span>
                <span class="small text-muted">মোট: <?= count($fines) ?> টি</span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>মাস</th>
                        <th class="text-end">ফাইন</th>
                        <th class="text-end">আদায়কৃত</th>
                        <th class="text-end">মওকুফ</th>
                        <th class="text-end">বাকি</th>
                        <th>স্ট্যাটাস</th>
                        <th>অ্যাকশন</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($fines)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-3 small">
                            <i data-feather="inbox" style="width:24px;height:24px;display:block;margin:0 auto 5px"></i>
                            কোনো ফাইন ট্রানজেকশন নেই
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($fines as $index => $fine): 
                        $remaining = $fine['fine_amount'] - $fine['paid_amount'] - ($fine['waived_amount'] ?? 0);
                    ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= date('F Y', strtotime($fine['month_year'])) ?></td>
                        <td class="text-end fw-bold"><?= number_format($fine['fine_amount'], 2) ?></td>
                        <td class="text-end text-success"><?= number_format($fine['paid_amount'], 2) ?></td>
                        <td class="text-end text-warning"><?= number_format($fine['waived_amount'] ?? 0, 2) ?></td>
                        <td class="text-end text-<?= $remaining > 0 ? 'danger' : 'success' ?> fw-bold">
                            <?= number_format($remaining, 2) ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= $statusColors[$fine['status']] ?>">
                                <?= $statusLabels[$fine['status']] ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <?php if ($fine['status'] != 'paid' && $fine['status'] != 'waived' && $remaining > 0): ?>
                                <button class="btn btn-outline-success p-1" 
                                        onclick="payFine(<?= $fine['id'] ?>, '<?= htmlspecialchars($memberInfo['name']) ?>', <?= $remaining ?>)" 
                                        title="পে করুন">
                                    <i data-feather="dollar-sign" style="width:12px;height:12px"></i>
                                </button>
                                <?php endif; ?>
                                
                                <button class="btn btn-outline-primary p-1" 
                                        onclick="editFine(<?= $fine['id'] ?>)" 
                                        title="সম্পাদনা করুন">
                                    <i data-feather="edit-2" style="width:12px;height:12px"></i>
                                </button>
                                
                                <button class="btn btn-outline-secondary p-1" 
                                        onclick="printReceipt(<?= $fine['id'] ?>)" 
                                        title="রসিদ প্রিন্ট">
                                    <i data-feather="printer" style="width:12px;height:12px"></i>
                                </button>
                                
                                <?php if ($fine['status'] != 'waived' && $remaining > 0): ?>
                                <a href="?member_id=<?= $memberId ?>&waive=<?= $fine['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" 
                                   class="btn btn-outline-warning p-1" 
                                   onclick="return confirm('এই ফাইন মওকুফ করতে চান?')"
                                   title="মওকুফ">
                                    <i data-feather="slash" style="width:12px;height:12px"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($fine['status'] != 'paid'): ?>
                                <a href="?member_id=<?= $memberId ?>&delete=<?= $fine['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" 
                                   class="btn btn-outline-danger p-1" 
                                   onclick="return confirm('এই ফাইন এবং সংশ্লিষ্ট ডিপোজিট ডিলিট করতে চান?')"
                                   title="ডিলিট">
                                    <i data-feather="trash-2" style="width:12px;height:12px"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modals -->
<!-- Generate Fine Modal -->
<div class="modal fade" id="generateFineModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title fs-6">
                    <i data-feather="calendar" style="width:18px;height:18px"></i>
                    ফাইন তৈরি করুন
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="GET" id="generateFineForm">
                <div class="modal-body">
                    <input type="hidden" name="member_id" value="<?= $memberId ?>">
                    <input type="hidden" name="generate_fine" value="yes">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                    <div class="member-info-card mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="flex-shrink-0">
                                <?php if ($hasPhoto && !empty($photoUrl)): ?>
                                    <img src="<?= htmlspecialchars($photoUrl) ?>" 
                                         alt="<?= htmlspecialchars($memberInfo['name']) ?>" 
                                         class="member-avatar-md"
                                         onerror="this.style.display='none'; this.parentElement.querySelector('.member-avatar-placeholder-md').style.display='flex';">
                                    <div class="member-avatar-placeholder-md" style="display:none;">
                                        <?= strtoupper(substr($memberInfo['name'], 0, 1)) ?>
                                    </div>
                                <?php else: ?>
                                    <div class="member-avatar-placeholder-md">
                                        <?= strtoupper(substr($memberInfo['name'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="fw-semibold"><?= htmlspecialchars($memberInfo['name']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($memberInfo['member_id']) ?></small>
                                <br>
                                <small class="text-muted"><i data-feather="phone" style="width:12px;height:12px"></i> <?= htmlspecialchars($memberInfo['phone'] ?? '') ?></small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="calendar-container mb-3">
                        <label class="form-label small fw-bold d-flex align-items-center gap-1">
                            <i data-feather="calendar" style="width:14px;height:14px"></i>
                            মাস ও বছর নির্বাচন করুন
                        </label>
                        <input type="month" name="month_year" class="form-control form-control-lg calendar-input" 
                               value="<?= date('Y-m') ?>" required>
                        <small class="text-muted">ফাইন তৈরি করতে চান এমন মাস নির্বাচন করুন</small>
                    </div>
                    
                    <div class="info-box mb-3">
                        <div class="row g-2 text-center">
                            <div class="col-6">
                                <div class="info-item">
                                    <div class="info-label small text-muted">ফাইনের পরিমাণ</div>
                                    <div class="info-value fw-bold text-primary"><?= number_format($settings['fine_amount'] ?? 200, 2) ?> টাকা</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="info-item">
                                    <div class="info-label small text-muted">নির্ধারিত তারিখ</div>
                                    <div class="info-value fw-bold"><?= $settings['due_day'] ?? 10 ?> তারিখ</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="quick-months">
                        <label class="form-label small fw-bold">দ্রুত নির্বাচন</label>
                        <div class="d-flex flex-wrap gap-1">
                            <button type="button" class="btn btn-outline-secondary btn-sm quick-month" data-month="<?= date('Y-m') ?>">
                                চলতি মাস
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm quick-month" data-month="<?= date('Y-m', strtotime('-1 month')) ?>">
                                গত মাস
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm quick-month" data-month="<?= date('Y-m', strtotime('-2 month')) ?>">
                                ২ মাস আগে
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm quick-month" data-month="<?= date('Y-m', strtotime('-3 month')) ?>">
                                ৩ মাস আগে
                            </button>
                        </div>
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

<!-- Pay Fine Modal -->
<div class="modal fade" id="payModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title fs-6">
                    <i data-feather="dollar-sign" style="width:18px;height:18px"></i>
                    ফাইন পে করুন
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body" style="overflow-y: auto; max-height: 70vh;">
                    <?= csrfField() ?>
                    <input type="hidden" name="pay_fine" value="1">
                    <input type="hidden" name="fine_id" id="payFineId" value="">
                    <input type="hidden" name="member_id" value="<?= $memberId ?>">
                    
                    <div class="mb-2">
                        <label class="form-label small fw-bold">সদস্য</label>
                        <p class="form-control-plaintext small" id="payMemberName">-</p>
                    </div>
                    
                    <div class="mb-2">
                        <label class="form-label small fw-bold">বাকি পরিমাণ</label>
                        <p class="form-control-plaintext small fw-bold text-danger" id="payRemaining">-</p>
                    </div>
                    
                    <div class="mb-2">
                        <label class="form-label small fw-bold">পরিমাণ <span class="text-danger">*</span></label>
                        <input type="number" name="amount" id="payAmount" class="form-control form-control-sm" 
                               step="0.01" min="0.01" required>
                    </div>
                    
                    <div class="mb-2">
                        <label class="form-label small fw-bold">পদ্ধতি</label>
                        <select name="payment_method" class="form-select form-select-sm">
                            <option value="cash">নগদ</option>
                            <option value="bank">ব্যাংক ট্রান্সফার</option>
                            <option value="mobile">মোবাইল ব্যাংকিং</option>
                            <option value="cheque">চেক</option>
                            <option value="online">অনলাইন</option>
                        </select>
                    </div>
                    
                    <div class="mb-2">
                        <label class="form-label small fw-bold">ট্রান্সেকশন নং</label>
                        <input type="text" name="transaction_no" class="form-control form-control-sm">
                    </div>
                    
                    <div class="mb-2">
                        <label class="form-label small fw-bold">রসিদ নং</label>
                        <input type="text" name="receipt_no" class="form-control form-control-sm">
                    </div>
                    
                    <div class="mb-2">
                        <label class="form-label small fw-bold">নোট</label>
                        <textarea name="payment_notes" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">বাতিল</button>
                    <button type="submit" class="btn btn-success btn-sm">
                        <i data-feather="check" style="width:14px;height:14px"></i>
                        পে করুন
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Fine Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title fs-6">
                    <i data-feather="edit-2" style="width:18px;height:18px"></i>
                    ফাইন সম্পাদনা করুন
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="editModalBody" style="overflow-y: auto; max-height: 70vh;">
                <div class="text-center py-3">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Print Receipt Modal -->
<div class="modal fade" id="printModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title fs-6">
                    <i data-feather="printer" style="width:18px;height:18px"></i>
                    ফাইন রসিদ
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="printContent" style="overflow-y: auto; max-height: 70vh;">
                <div class="text-center py-3">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">বন্ধ</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
                    <i data-feather="printer" style="width:14px;height:14px"></i>
                    প্রিন্ট করুন
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* ============================================
   STYLES
   ============================================ */
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

.profile-avatar-large {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #e9ecef;
    background-color: #f8f9fa;
    flex-shrink: 0;
}
.profile-avatar-placeholder-large {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #1B4F72, #2E86C1);
    color: white;
    font-weight: bold;
    font-size: 30px;
    border: 3px solid #e9ecef;
    flex-shrink: 0;
}

.member-avatar-md {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    flex-shrink: 0;
}
.member-avatar-placeholder-md {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #1B4F72, #2E86C1);
    color: white;
    font-weight: bold;
    font-size: 24px;
    border: 3px solid #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    flex-shrink: 0;
}

.member-info-card {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border-radius: 10px;
    padding: 12px 16px;
    border-left: 4px solid #2E86C1;
}

.calendar-input {
    border: 2px solid #e9ecef;
    border-radius: 10px;
    padding: 10px 14px;
    font-size: 16px;
    background: white;
    transition: all 0.3s;
}
.calendar-input:focus {
    border-color: #2E86C1;
    box-shadow: 0 0 0 3px rgba(46, 134, 193, 0.15);
}
.calendar-input::-webkit-calendar-picker-indicator {
    padding: 5px;
    border-radius: 5px;
    cursor: pointer;
}
.calendar-input::-webkit-calendar-picker-indicator:hover {
    background: #e9ecef;
}

.info-box {
    background: linear-gradient(135deg, #EAFAF1, #D5F5E3);
    border-radius: 10px;
    padding: 10px 14px;
    border: 1px solid #A9DFBF;
}

.info-item .info-label {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.info-item .info-value {
    font-size: 16px;
    margin-top: 2px;
}

.quick-month {
    font-size: 11px;
    padding: 4px 10px;
    border-radius: 20px;
    border-color: #dee2e6;
    transition: all 0.2s;
}
.quick-month:hover {
    background: #2E86C1;
    color: white;
    border-color: #2E86C1;
}
.quick-month.active {
    background: #2E86C1;
    color: white;
    border-color: #2E86C1;
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
    background-color: #f8f9fa;
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

@media print {
    .btn, .modal-footer, .modal-header .btn-close {
        display: none !important;
    }
    .modal-content {
        border: none !important;
        box-shadow: none !important;
    }
    .modal-header {
        background: white !important;
        color: black !important;
        border-bottom: 2px solid #000 !important;
    }
    .modal {
        position: static !important;
        display: block !important;
    }
    .modal-dialog {
        margin: 0 !important;
        max-width: 100% !important;
    }
    .modal-body {
        padding: 20px !important;
    }
}

@media (max-width: 576px) {
    .stat-card .stat-value {
        font-size: 16px;
    }
    .table td, .table th {
        font-size: 10px;
        padding: 4px 2px;
    }
    .btn-group .btn {
        padding: 2px 4px;
    }
    .profile-avatar-large {
        width: 50px;
        height: 50px;
    }
    .profile-avatar-placeholder-large {
        width: 50px;
        height: 50px;
        font-size: 22px;
    }
    .member-avatar-md {
        width: 44px;
        height: 44px;
    }
    .member-avatar-placeholder-md {
        width: 44px;
        height: 44px;
        font-size: 18px;
    }
    .modal-dialog {
        margin: 10px;
    }
}
</style>

<script>
function openGenerateFineModal() {
    new bootstrap.Modal(document.getElementById('generateFineModal')).show();
}

document.addEventListener('DOMContentLoaded', function() {
    const quickMonthBtns = document.querySelectorAll('.quick-month');
    const monthInput = document.querySelector('.calendar-input');
    
    quickMonthBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const month = this.dataset.month;
            if (monthInput) {
                monthInput.value = month;
            }
            quickMonthBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
        });
    });
    
    const currentMonth = new Date().toISOString().slice(0, 7);
    quickMonthBtns.forEach(btn => {
        if (btn.dataset.month === currentMonth) {
            btn.classList.add('active');
        }
    });
    
    if (monthInput) {
        monthInput.addEventListener('change', function() {
            quickMonthBtns.forEach(b => b.classList.remove('active'));
        });
    }
});

function payFine(fineId, memberName, remaining) {
    document.getElementById('payFineId').value = fineId;
    document.getElementById('payMemberName').textContent = memberName;
    document.getElementById('payRemaining').textContent = remaining.toFixed(2) + ' টাকা';
    document.getElementById('payAmount').value = remaining;
    document.getElementById('payAmount').max = remaining;
    document.getElementById('payAmount').min = 0.01;
    new bootstrap.Modal(document.getElementById('payModal')).show();
}

function editFine(fineId) {
    const modal = new bootstrap.Modal(document.getElementById('editModal'));
    const body = document.getElementById('editModalBody');
    
    body.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2 small text-muted">ডেটা লোড হচ্ছে...</p>
        </div>
    `;
    
    modal.show();
    
    fetch('fine_details.php?ajax=get_fine_edit&id=' + fineId + '&t=' + Date.now())
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                body.innerHTML = `
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="update_fine" value="1">
                        <input type="hidden" name="fine_id" value="${data.id}">
                        <input type="hidden" name="member_id" value="<?= $memberId ?>">
                        
                        <div class="mb-2">
                            <label class="form-label small fw-bold">সদস্য</label>
                            <p class="form-control-plaintext small">${data.member_name} (${data.member_id})</p>
                        </div>
                        
                        <div class="mb-2">
                            <label class="form-label small fw-bold">মাস</label>
                            <p class="form-control-plaintext small">${data.month}</p>
                        </div>
                        
                        <div class="mb-2">
                            <label class="form-label small fw-bold">ফাইনের পরিমাণ <span class="text-danger">*</span></label>
                            <input type="number" name="fine_amount" class="form-control form-control-sm" 
                                   step="0.01" min="0" value="${data.fine_amount}" required>
                        </div>
                        
                        <div class="mb-2">
                            <label class="form-label small fw-bold">নির্ধারিত তারিখ</label>
                            <input type="date" name="due_date" class="form-control form-control-sm" 
                                   value="${data.due_date}">
                        </div>
                        
                        <div class="mb-2">
                            <label class="form-label small fw-bold">নোট</label>
                            <textarea name="notes" class="form-control form-control-sm" rows="3">${data.notes || ''}</textarea>
                        </div>
                        
                        <div class="modal-footer py-2 px-0">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">বাতিল</button>
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i data-feather="save" style="width:14px;height:14px"></i>
                                আপডেট করুন
                            </button>
                        </div>
                    </form>
                `;
            } else {
                body.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
            }
        })
        .catch(error => {
            body.innerHTML = `
                <div class="alert alert-danger">
                    <i data-feather="alert-circle" style="width:16px;height:16px"></i>
                    ডেটা লোড করতে ব্যর্থ হয়েছে
                </div>
            `;
        });
}

function printReceipt(fineId) {
    const modal = new bootstrap.Modal(document.getElementById('printModal'));
    const content = document.getElementById('printContent');
    
    content.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2 small text-muted">রসিদ লোড হচ্ছে...</p>
        </div>
    `;
    
    modal.show();
    
    fetch('fine_details.php?ajax=get_receipt&id=' + fineId + '&t=' + Date.now())
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const d = data.data;
                const remaining = d.fine_amount - d.paid_amount - (d.waived_amount || 0);
                const statusLabels = {
                    'pending': 'বকেয়া',
                    'partially_paid': 'আংশিক',
                    'paid': 'পেড',
                    'waived': 'মওকুফ'
                };
                
                let html = `
                    <div style="font-family: 'Courier New', monospace; padding: 20px; max-width: 400px; margin: 0 auto;">
                        <div style="text-align: center; border-bottom: 2px dashed #000; padding-bottom: 10px; margin-bottom: 10px;">
                            <h4 style="margin: 0; font-size: 18px;">${d.settings?.fine_name || 'Al Baraka Cooperative Society'}</h4>
                            <p style="margin: 2px 0; font-size: 12px;">ফাইন রসিদ</p>
                            <p style="margin: 2px 0; font-size: 11px;">রসিদ নং: #${d.id}</p>
                            <p style="margin: 2px 0; font-size: 11px;">তারিখ: ${new Date().toLocaleDateString('bn-BD')}</p>
                        </div>
                        
                        <div style="margin-bottom: 10px;">
                            <p style="margin: 3px 0; font-size: 12px;"><strong>সদস্য:</strong> ${d.name}</p>
                            <p style="margin: 3px 0; font-size: 12px;"><strong>সদস্য আইডি:</strong> ${d.member_id}</p>
                            <p style="margin: 3px 0; font-size: 12px;"><strong>ফোন:</strong> ${d.phone || 'N/A'}</p>
                            ${d.address ? `<p style="margin: 3px 0; font-size: 12px;"><strong>ঠিকানা:</strong> ${d.address}</p>` : ''}
                        </div>
                        
                        <div style="border-top: 1px dashed #000; border-bottom: 1px dashed #000; padding: 10px 0; margin-bottom: 10px;">
                            <p style="margin: 3px 0; font-size: 12px;"><strong>মাস:</strong> ${new Date(d.month_year).toLocaleDateString('bn-BD', { month: 'long', year: 'numeric' })}</p>
                            <p style="margin: 3px 0; font-size: 12px;"><strong>ফাইনের পরিমাণ:</strong> ${Number(d.fine_amount).toFixed(2)} টাকা</p>
                            <p style="margin: 3px 0; font-size: 12px;"><strong>পেড:</strong> ${Number(d.paid_amount).toFixed(2)} টাকা</p>
                            <p style="margin: 3px 0; font-size: 12px;"><strong>মওকুফ:</strong> ${Number(d.waived_amount || 0).toFixed(2)} টাকা</p>
                            <p style="margin: 3px 0; font-size: 12px;"><strong>বাকি:</strong> ${Number(remaining).toFixed(2)} টাকা</p>
                            <p style="margin: 3px 0; font-size: 12px;"><strong>স্ট্যাটাস:</strong> ${statusLabels[d.status] || d.status}</p>
                            <p style="margin: 3px 0; font-size: 12px;"><strong>নির্ধারিত তারিখ:</strong> ${new Date(d.due_date).toLocaleDateString('bn-BD')}</p>
                            ${d.paid_date ? `<p style="margin: 3px 0; font-size: 12px;"><strong>পেডের তারিখ:</strong> ${new Date(d.paid_date).toLocaleDateString('bn-BD')}</p>` : ''}
                        </div>
                `;
                
                if (d.payments && d.payments.length > 0) {
                    html += `
                        <div style="margin-bottom: 10px;">
                            <p style="margin: 3px 0; font-size: 12px; font-weight: bold;">পেমেন্ট ইতিহাস:</p>
                            <table style="width: 100%; font-size: 11px; border-collapse: collapse;">
                                <thead>
                                    <tr style="border-bottom: 1px solid #000;">
                                        <th style="text-align: left; padding: 2px;">তারিখ</th>
                                        <th style="text-align: right; padding: 2px;">পরিমাণ</th>
                                        <th style="text-align: left; padding: 2px;">পদ্ধতি</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;
                    
                    d.payments.forEach(p => {
                        html += `
                            <tr style="border-bottom: 1px dotted #ccc;">
                                <td style="padding: 2px;">${new Date(p.payment_date).toLocaleDateString('bn-BD')}</td>
                                <td style="text-align: right; padding: 2px;">${Number(p.amount).toFixed(2)}</td>
                                <td style="padding: 2px;">${p.payment_method}</td>
                            </tr>
                        `;
                    });
                    
                    html += `
                                </tbody>
                            </table>
                        </div>
                    `;
                }
                
                html += `
                        <div style="text-align: center; border-top: 2px dashed #000; padding-top: 10px; margin-top: 10px; font-size: 11px;">
                            <p style="margin: 2px 0;">ধন্যবাদ</p>
                            <p style="margin: 2px 0;">${d.settings?.fine_name || 'Al Baraka Cooperative Society'}</p>
                            <p style="margin: 2px 0; font-size: 10px; color: #666;">এই রসিদটি কম্পিউটার দ্বারা জেনারেট করা হয়েছে</p>
                        </div>
                    </div>
                `;
                
                content.innerHTML = html;
            } else {
                content.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
            }
        })
        .catch(error => {
            content.innerHTML = `
                <div class="alert alert-danger">
                    <i data-feather="alert-circle" style="width:16px;height:16px"></i>
                    রসিদ লোড করতে ব্যর্থ হয়েছে
                </div>
            `;
        });
}

function generateAllFines() {
    if (!confirm('সকল সক্রিয় সদস্যের জন্য ফাইন তৈরি করতে চান?\n\nযারা ১০ তারিখের আগে জমা দিয়েছেন তাদের ফাইন স্বয়ংক্রিয়ভাবে বাদ দেওয়া হবে।')) {
        return;
    }
    
    const month = prompt('কোন মাসের জন্য ফাইন তৈরি করতে চান? (YYYY-MM ফরম্যাটে লিখুন)\nউদাহরণ: 2026-07\n\nখালি রাখলে বর্তমান মাসের জন্য তৈরি হবে।');
    
    let url = 'fine_details.php?member_id=<?= $memberId ?>&generate_all_fines=yes&csrf_token=<?= $_SESSION['csrf_token'] ?>';
    
    if (month && month.trim() !== '') {
        url += '&month_year=' + month.trim() + '-01';
    }
    
    window.location.href = url;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>