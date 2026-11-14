<?php
// =====================================================
// admin/member_due_report.php - Member Due Report with Payment (FIXED)
// =====================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/functions.php';
startSecureSession();

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    header('Location: /admin/login.php');
    exit;
}

$pageTitle = 'সদস্য বকেয়া তালিকা';
$error = '';
$success = '';

$conn = conn();

// ============================================
// HELPER: Bengali Month Name
// ============================================
if (!function_exists('bengaliMonth')) {
    function bengaliMonth($month) {
        $months = [
            1 => 'জানুয়ারি', 2 => 'ফেব্রুয়ারি', 3 => 'মার্চ',
            4 => 'এপ্রিল', 5 => 'মে', 6 => 'জুন',
            7 => 'জুলাই', 8 => 'আগস্ট', 9 => 'সেপ্টেম্বর',
            10 => 'অক্টোবর', 11 => 'নভেম্বর', 12 => 'ডিসেম্বর'
        ];
        return $months[(int)$month] ?? $month;
    }
}

// ============================================
// HELPER: Check if month is valid (past month only)
// ============================================
if (!function_exists('isValidDueMonth')) {
    function isValidDueMonth($year, $month) {
        $currentYear = date('Y');
        $currentMonth = date('n');
        
        // ONLY allow months BEFORE the current month (past months only)
        if ($year < $currentYear) {
            return true; // Past year - OK
        }
        if ($year == $currentYear && $month < $currentMonth) {
            return true; // Past month - OK
        }
        
        return false; // Current or future month - NOT ALLOWED
    }
}

// ============================================
// GET MEMBER DUE SUMMARY FUNCTION - FIXED
// ============================================
if (!function_exists('getMemberDueSummary')) {
    function getMemberDueSummary($memberId) {
        $conn = conn();
        
        try {
            // Get current date to exclude current month and future months
            $currentYear = date('Y');
            $currentMonth = date('n');
            
            // Get all dues for this member - ONLY past months (BEFORE current month)
            $dues = queryAll(
                "SELECT * FROM monthly_dues 
                 WHERE member_id = ? 
                 AND (due_year < ? OR (due_year = ? AND due_month < ?))
                 ORDER BY due_year ASC, due_month ASC",
                'iiii',
                [$memberId, $currentYear, $currentYear, $currentMonth]
            );
            
            $totalDue = 0;
            $unpaidMonths = 0;
            $oldestYear = null;
            $oldestMonth = null;
            
            foreach ($dues as $d) {
                $dueAmount = (float)$d['amount'] - (float)$d['paid_amount'];
                if ($dueAmount > 0) {
                    $totalDue += $dueAmount;
                    $unpaidMonths++;
                    
                    if ($oldestYear === null || $d['due_year'] < $oldestYear || 
                        ($d['due_year'] == $oldestYear && $d['due_month'] < $oldestMonth)) {
                        $oldestYear = $d['due_year'];
                        $oldestMonth = $d['due_month'];
                    }
                }
            }
            
            $oldestDue = null;
            if ($oldestYear !== null && $oldestMonth !== null) {
                $oldestDue = $oldestYear . '-' . str_pad($oldestMonth, 2, '0', STR_PAD_LEFT) . '-01';
            }
            
            return [
                'total_due' => $totalDue,
                'unpaid_months' => $unpaidMonths,
                'oldest_due' => $oldestDue,
                'has_due' => $totalDue > 0
            ];
        } catch (Exception $e) {
            return ['total_due' => 0, 'unpaid_months' => 0, 'oldest_due' => null, 'has_due' => false];
        }
    }
}

// ============================================
// HANDLE PAYMENT
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pay') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'নিরাপত্তা যাচাই ব্যর্থ।';
    } else {
        $memberId = (int)$_POST['member_id'];
        $year = (int)$_POST['year'];
        $month = (int)$_POST['month'];
        $amount = (float)$_POST['amount'];
        $paidDate = $_POST['paid_date'] ?? date('Y-m-d');
        $note = $_POST['note'] ?? '';
        
        if ($memberId <= 0 || $year < 2020 || $month < 1 || $month > 12) {
            $error = 'সঠিক তথ্য দিন।';
        } elseif ($amount <= 0) {
            $error = 'পরিমাণ দিন।';
        } else {
            // CRITICAL: Prevent payment for current or future months
            $currentYear = date('Y');
            $currentMonth = date('n');
            
            if ($year > $currentYear || ($year == $currentYear && $month >= $currentMonth)) {
                $error = 'বর্তমান বা ভবিষ্যতের মাসের জন্য পেমেন্ট গ্রহণ করা যাবে না!';
            } else {
                // Check if due exists
                $due = queryOne(
                    "SELECT * FROM monthly_dues WHERE member_id = ? AND due_year = ? AND due_month = ?",
                    'iii',
                    [$memberId, $year, $month]
                );
                
                if (!$due) {
                    // Only create due if it's a valid past month
                    if (isValidDueMonth($year, $month)) {
                        $stmt = $conn->prepare("INSERT INTO monthly_dues (member_id, due_year, due_month, amount, status, created_at) VALUES (?, ?, ?, 500, 'unpaid', NOW())");
                        $stmt->bind_param('iii', $memberId, $year, $month);
                        $stmt->execute();
                        
                        // Get the new due
                        $due = queryOne(
                            "SELECT * FROM monthly_dues WHERE member_id = ? AND due_year = ? AND due_month = ?",
                            'iii',
                            [$memberId, $year, $month]
                        );
                    } else {
                        $error = 'ভুল মাস নির্বাচন করা হয়েছে!';
                    }
                }
                
                if ($due) {
                    // Generate deposit receipt
                    do {
                        $receipt = 'DEP-' . date('Ymd') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
                    } while (queryOne("SELECT id FROM deposits WHERE receipt_no = ?", 's', [$receipt]));
                    
                    $adminId = $_SESSION['admin_id'] ?? 1;
                    
                    // CRITICAL FIX: Always use 'regular' for monthly due payments
                    // 'fine' deposits should NOT be counted as monthly due payments
                    $depositStmt = $conn->prepare("
                        INSERT INTO deposits (member_id, amount, deposit_date, deposit_type, payment_method, receipt_no, note, collected_by)
                        VALUES (?, ?, ?, 'regular', 'cash', ?, ?, ?)
                    ");
                    $depositStmt->bind_param('idsssi', $memberId, $amount, $paidDate, $receipt, $note, $adminId);
                    
                    if ($depositStmt->execute()) {
                        // Update due
                        $newPaid = (float)$due['paid_amount'] + $amount;
                        $status = $newPaid >= (float)$due['amount'] ? 'paid' : 'partial';
                        
                        $updateStmt = $conn->prepare("
                            UPDATE monthly_dues 
                            SET paid_amount = ?, paid_date = ?, status = ?, receipt_no = ?, collected_by = ?, note = ?, updated_at = NOW()
                            WHERE id = ?
                        ");
                        $updateStmt->bind_param('dsssssi', $newPaid, $paidDate, $status, $receipt, $adminId, $note, $due['id']);
                        $updateStmt->execute();
                        
                        $success = "সঞ্চয় গ্রহণ সফল! রসিদ নং: $receipt";
                        logActivity('member_payment', "Member #$memberId paid for $year-$month, Receipt: $receipt");
                    } else {
                        $error = 'পেমেন্ট ব্যর্থ: ' . $conn->error;
                    }
                }
            }
        }
    }
}

// Get search parameters
$searchTerm = trim($_GET['search'] ?? '');
$selectedMemberId = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;

// Build member query with search
$memberWhere = "status = 'active'";
$memberParams = [];
$memberTypes = '';

if (!empty($searchTerm)) {
    $memberWhere .= " AND (name LIKE ? OR member_id LIKE ? OR phone LIKE ?)";
    $like = "%$searchTerm%";
    $memberParams = [$like, $like, $like];
    $memberTypes = 'sss';
}

// Get all active members with search
$members = queryAll(
    "SELECT id, member_id, name, phone, email, photo, join_date, created_at FROM members WHERE $memberWhere ORDER BY name",
    $memberTypes,
    $memberParams
);

// If a specific member is selected, get their details
$memberData = null;
$dueHistory = [];
$depositHistory = [];
$withdrawalHistory = [];
$summary = [
    'total_months' => 0,
    'paid_months' => 0,
    'unpaid_months' => 0,
    'total_amount' => 0,
    'total_paid' => 0,
    'total_due' => 0,
    'total_deposit' => 0,
    'total_withdrawal' => 0,
    'balance' => 0
];

if ($selectedMemberId > 0) {
    // Get member details
    $memberData = queryOne("SELECT * FROM members WHERE id = ?", 'i', [$selectedMemberId]);
    
    if ($memberData) {
        // ============================================
        // GET DEPOSIT HISTORY - ONLY REGULAR DEPOSITS
        // ============================================
        // CRITICAL FIX: Only fetch 'regular' deposits for monthly due payments
        // 'fine' deposits should NOT be counted as monthly due payments
        $deposits = queryAll(
            "SELECT * FROM deposits WHERE member_id = ? AND deposit_type = 'regular' ORDER BY deposit_date DESC",
            'i',
            [$selectedMemberId]
        );
        
        $totalDeposit = 0;
        foreach ($deposits as $dep) {
            $totalDeposit += (float)$dep['amount'];
            $depositHistory[] = [
                'id' => $dep['id'],
                'amount' => (float)$dep['amount'],
                'deposit_date' => $dep['deposit_date'],
                'payment_method' => $dep['payment_method'] ?? 'cash',
                'receipt_no' => $dep['receipt_no'] ?? '',
                'deposit_type' => $dep['deposit_type'] ?? 'regular',
                'note' => $dep['note'] ?? '',
                'collected_by' => $dep['collected_by'] ?? '',
                'created_at' => $dep['created_at'] ?? ''
            ];
        }
        
        // ============================================
        // GET WITHDRAWAL HISTORY
        // ============================================
        $withdrawals = queryAll(
            "SELECT * FROM withdrawals WHERE member_id = ? AND status = 'completed' ORDER BY withdrawal_date DESC",
            'i',
            [$selectedMemberId]
        );
        
        $totalWithdrawal = 0;
        foreach ($withdrawals as $wd) {
            $totalWithdrawal += (float)$wd['amount'];
            $withdrawalHistory[] = [
                'id' => $wd['id'],
                'amount' => (float)$wd['amount'],
                'withdrawal_date' => $wd['withdrawal_date'],
                'payment_method' => $wd['payment_method'] ?? 'cash',
                'transaction_no' => $wd['transaction_no'] ?? '',
                'status' => $wd['status'] ?? 'completed',
                'note' => $wd['note'] ?? '',
                'receipt_no' => $wd['receipt_no'] ?? '',
                'created_at' => $wd['created_at'] ?? ''
            ];
        }
        
        $balance = $totalDeposit - $totalWithdrawal;
        
        // ============================================
        // GET MONTHLY DUE HISTORY - ONLY PAST MONTHS
        // ============================================
        // Get current date to filter out current and future months
        $currentYear = date('Y');
        $currentMonth = date('n');
        
        // CRITICAL: Get existing dues ONLY for PAST months (BEFORE current month)
        $dues = queryAll(
            "SELECT * FROM monthly_dues 
             WHERE member_id = ? 
             AND (due_year < ? OR (due_year = ? AND due_month < ?))
             ORDER BY due_year DESC, due_month DESC",
            'iiii',
            [$selectedMemberId, $currentYear, $currentYear, $currentMonth]
        );
        
        // Build due history from actual database records
        $dueHistory = [];
        $totalAmount = 0;
        $totalPaid = 0;
        $paidMonths = 0;
        $unpaidMonths = 0;
        $counter = 1;
        
        // If no dues found (up to previous month), create from join date (but ONLY past months)
        if (empty($dues) && $memberData['join_date']) {
            $joinDate = $memberData['join_date'];
            $startYear = (int)date('Y', strtotime($joinDate));
            $startMonth = (int)date('n', strtotime($joinDate));
            
            // Build array first, then sort desc
            $tempHistory = [];
            $tempYear = $startYear;
            $tempMonth = $startMonth;
            
            // Calculate previous month (end at month BEFORE current)
            $endYear = $currentYear;
            $endMonth = $currentMonth - 1;
            if ($endMonth == 0) {
                $endMonth = 12;
                $endYear--;
            }
            
            // IMPORTANT: Only loop up to PREVIOUS month (excludes current and future)
            while ($tempYear < $endYear || ($tempYear == $endYear && $tempMonth <= $endMonth)) {
                // CRITICAL FIX: Only check for 'regular' deposits, NOT 'fine'
                $deposit = queryOne(
                    "SELECT * FROM deposits WHERE member_id = ? AND YEAR(deposit_date) = ? AND MONTH(deposit_date) = ? AND deposit_type = 'regular'",
                    'iii',
                    [$selectedMemberId, $tempYear, $tempMonth]
                );
                
                if ($deposit) {
                    $paidAmount = (float)$deposit['amount'];
                    $status = $paidAmount >= 500 ? 'paid' : 'partial';
                    $paidMonths++;
                } else {
                    $paidAmount = 0;
                    $status = 'unpaid';
                    $unpaidMonths++;
                }
                
                $dueRemaining = 500 - $paidAmount;
                $totalAmount += 500;
                $totalPaid += $paidAmount;
                
                $tempHistory[] = [
                    'id' => $counter,
                    'due_year' => $tempYear,
                    'due_month' => $tempMonth,
                    'month_name' => bengaliMonth($tempMonth),
                    'amount' => 500,
                    'status' => $status,
                    'paid_amount' => $paidAmount,
                    'receipt_no' => $deposit['receipt_no'] ?? null,
                    'paid_date' => $deposit['deposit_date'] ?? null,
                    'due_remaining' => $dueRemaining,
                    'payment_made' => ($deposit !== null)
                ];
                
                $counter++;
                $tempMonth++;
                if ($tempMonth > 12) {
                    $tempMonth = 1;
                    $tempYear++;
                }
            }
            
            // Sort descending by year, then month
            usort($tempHistory, function($a, $b) {
                if ($a['due_year'] != $b['due_year']) {
                    return $b['due_year'] - $a['due_year'];
                }
                return $b['due_month'] - $a['due_month'];
            });
            
            $dueHistory = $tempHistory;
            
        } else {
            // Use existing dues (already filtered to only past months)
            foreach ($dues as $d) {
                $year = (int)$d['due_year'];
                $month = (int)$d['due_month'];
                
                $dueAmount = (float)$d['amount'];
                $paidAmount = (float)$d['paid_amount'];
                $dueRemaining = $dueAmount - $paidAmount;
                $status = $d['status'];
                
                // If there's a remaining balance, status should be partial or unpaid
                if ($dueRemaining > 0 && $status === 'paid') {
                    $status = 'partial';
                }
                
                $totalAmount += $dueAmount;
                $totalPaid += $paidAmount;
                
                if ($status === 'paid') {
                    $paidMonths++;
                } elseif ($status === 'partial') {
                    $paidMonths++;
                } else {
                    $unpaidMonths++;
                }
                
                $dueHistory[] = [
                    'id' => $counter,
                    'due_year' => $year,
                    'due_month' => $month,
                    'month_name' => bengaliMonth($month),
                    'amount' => $dueAmount,
                    'status' => $status,
                    'paid_amount' => $paidAmount,
                    'receipt_no' => $d['receipt_no'],
                    'paid_date' => $d['paid_date'],
                    'due_remaining' => $dueRemaining,
                    'payment_made' => ($paidAmount > 0)
                ];
                
                $counter++;
            }
        }
        
        // Update summary
        $summary['total_months'] = count($dueHistory);
        $summary['paid_months'] = $paidMonths;
        $summary['unpaid_months'] = $unpaidMonths;
        $summary['total_amount'] = $totalAmount;
        $summary['total_paid'] = $totalPaid;
        $summary['total_due'] = $totalAmount - $totalPaid;
        $summary['total_deposit'] = $totalDeposit;
        $summary['total_withdrawal'] = $totalWithdrawal;
        $summary['balance'] = $balance;
    }
}

// Include header
require_once __DIR__ . '/../includes/header.php';
?>

<!-- HTML Section Starts Here -->
<div class="container-fluid px-4 mt-3">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>
            <i data-feather="users" style="width:20px;height:20px"></i> 
            সদস্য বকেয়া তালিকা
        </h4>
        <div>
            <button class="btn btn-info btn-sm" onclick="window.print()">
                <i data-feather="printer" style="width:14px;height:14px"></i> প্রিন্ট
            </button>
            <a href="members.php" class="btn btn-outline-secondary btn-sm">
                <i data-feather="arrow-left" style="width:14px;height:14px"></i> সদস্য তালিকা
            </a>
        </div>
    </div>

    <?php if ($flash = getFlash()): ?>
    <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
        <i data-feather="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-circle' ?>" style="width:16px;height:16px;vertical-align:middle"></i>
        <?= htmlspecialchars($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i data-feather="alert-circle" style="width:16px;height:16px;vertical-align:middle"></i>
        <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i data-feather="check-circle" style="width:16px;height:16px;vertical-align:middle"></i>
        <?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Search and Member List -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="form-label fw-bold">খুঁজুন</label>
                    <div class="search-box">
                        <i data-feather="search" class="search-icon"></i>
                        <input type="text" name="search" class="form-control" 
                               placeholder="নাম, আইডি বা ফোন দিয়ে খুঁজুন..." 
                               value="<?= htmlspecialchars($searchTerm) ?>">
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label fw-bold">সদস্য নির্বাচন করুন</label>
                    <select name="member_id" class="form-select select2-searchable" id="memberSelect">
                        <option value="">-- সকল সদস্য --</option>
                        <?php foreach ($members as $m): ?>
                        <option value="<?= $m['id'] ?>" <?= $selectedMemberId == $m['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m['name']) ?> (<?= $m['member_id'] ?>) - <?= $m['phone'] ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i data-feather="search" style="width:16px;height:16px"></i> দেখুন
                    </button>
                    <a href="member_due_report.php" class="btn btn-outline-secondary">
                        <i data-feather="refresh-cw" style="width:16px;height:16px"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <?php if ($selectedMemberId > 0 && $memberData): ?>
    <!-- ============================================ -->
    <!-- INDIVIDUAL MEMBER VIEW -->
    <!-- ============================================ -->
    
    <!-- Member Info with Photo/Avatar -->
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="card-title">
                <i data-feather="user" style="width:16px;height:16px"></i>
                সদস্য তথ্য
            </span>
            <div>
                <span class="badge bg-info">
                    <i data-feather="calendar" style="width:12px;height:12px"></i>
                    যোগদান: <?= date('M Y', strtotime($memberData['join_date'] ?? date('Y-m-d'))) ?>
                </span>
                <a href="members.php?action=edit&id=<?= $selectedMemberId ?>" class="btn btn-sm btn-outline-primary ms-2">
                    <i data-feather="edit-2" style="width:14px;height:14px"></i> প্রোফাইল এডিট
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <!-- Profile Photo / Avatar -->
                <div class="col-md-2 text-center">
                    <?php 
                    if (!empty($memberData['photo'])): 
                        $photoPath = $memberData['photo'];
                        if (file_exists($_SERVER['DOCUMENT_ROOT'] . $photoPath)):
                    ?>
                        <img src="<?= $photoPath ?>" alt="Profile Photo" 
                             style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid #1B4F72;">
                    <?php 
                        else:
                    ?>
                            <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#1B4F72,#2E86C1);
                                        color:white;display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:bold;
                                        margin:0 auto;border:3px solid #1B4F72;">
                                <?= strtoupper(substr(trim($memberData['name'] ?? 'U'), 0, 1)) ?>
                            </div>
                    <?php 
                        endif;
                    else: 
                    ?>
                        <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#1B4F72,#2E86C1);
                                    color:white;display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:bold;
                                    margin:0 auto;border:3px solid #1B4F72;">
                            <?= strtoupper(substr(trim($memberData['name'] ?? 'U'), 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-10">
                    <div class="row">
                        <div class="col-md-3">
                            <small class="text-muted">নাম</small>
                            <div><strong><?= htmlspecialchars($memberData['name']) ?></strong></div>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">সদস্য আইডি</small>
                            <div><strong><?= htmlspecialchars($memberData['member_id']) ?></strong></div>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">ফোন</small>
                            <div><strong><?= htmlspecialchars($memberData['phone'] ?? 'N/A') ?></strong></div>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">ইমেইল</small>
                            <div><strong><?= htmlspecialchars($memberData['email'] ?? 'N/A') ?></strong></div>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-3">
                            <small class="text-muted">পিতা</small>
                            <div><?= htmlspecialchars($memberData['father_name'] ?? 'N/A') ?></div>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">মাতা</small>
                            <div><?= htmlspecialchars($memberData['mother_name'] ?? 'N/A') ?></div>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">স্বামী/স্ত্রী</small>
                            <div><?= htmlspecialchars($memberData['spouse_name'] ?? 'N/A') ?></div>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">এনআইডি</small>
                            <div><?= htmlspecialchars($memberData['nid'] ?? 'N/A') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-2">
            <div class="stat-card" style="background: linear-gradient(135deg, #1B4F72, #2E86C1); color: white;">
                <div class="stat-value" style="color: white;"><?= $summary['total_months'] ?></div>
                <div class="stat-label" style="color: rgba(255,255,255,0.8);">মোট মাস</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card" style="background: linear-gradient(135deg, #1E8449, #27AE60); color: white;">
                <div class="stat-value" style="color: white;"><?= $summary['paid_months'] ?></div>
                <div class="stat-label" style="color: rgba(255,255,255,0.8);">পরিশোধিত</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card" style="background: linear-gradient(135deg, #922B21, #E74C3C); color: white;">
                <div class="stat-value" style="color: white;"><?= $summary['unpaid_months'] ?></div>
                <div class="stat-label" style="color: rgba(255,255,255,0.8);">বকেয়া</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card" style="background: linear-gradient(135deg, #0E6655, #1ABC9C); color: white;">
                <div class="stat-value" style="color: white; font-size:16px;"><?= number_format($summary['total_deposit'], 2) ?> ৳</div>
                <div class="stat-label" style="color: rgba(255,255,255,0.8);">মোট জমা</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card" style="background: linear-gradient(135deg, #6C3483, #AF7AC5); color: white;">
                <div class="stat-value" style="color: white; font-size:16px;"><?= number_format($summary['total_withdrawal'], 2) ?> ৳</div>
                <div class="stat-label" style="color: rgba(255,255,255,0.8);">মোট উত্তোলন</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card" style="background: linear-gradient(135deg, #B7950B, #F1C40F); color: white;">
                <div class="stat-value" style="color: white; font-size:16px;"><?= number_format($summary['balance'], 2) ?> ৳</div>
                <div class="stat-label" style="color: rgba(255,255,255,0.8);">বর্তমান ব্যালেন্স</div>
            </div>
        </div>
    </div>

    <!-- Tabs: Due History, Deposit History & Withdrawal History -->
    <div class="card">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#dueTab" type="button">
                        <i data-feather="calendar" style="width:14px;height:14px"></i> বকেয়া ইতিহাস
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#depositTab" type="button">
                        <i data-feather="trending-up" style="width:14px;height:14px"></i> জমা ইতিহাস
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#withdrawalTab" type="button">
                        <i data-feather="arrow-up" style="width:14px;height:14px"></i> উত্তোলন ইতিহাস
                    </button>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content">
                <!-- ============================================ -->
                <!-- DUE HISTORY TAB -->
                <!-- ============================================ -->
                <div class="tab-pane fade show active" id="dueTab">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>মাস</th>
                                    <th>সাল</th>
                                    <th>পরিমাণ</th>
                                    <th>পরিশোধ</th>
                                    <th>বকেয়া</th>
                                    <th>অবস্থা</th>
                                    <th>রসিদ নং</th>
                                    <th>কার্যক্রম</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($dueHistory)): ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">
                                        <i data-feather="inbox" style="width:32px;height:32px;display:block;margin:0 auto 10px"></i>
                                        কোনো সঞ্চয় রেকর্ড পাওয়া যায়নি
                                    </td>
                                </tr>
                                <?php else: 
                                    $counter = 1;
                                    foreach ($dueHistory as $d): 
                                ?>
                                <tr class="<?= $d['status'] === 'unpaid' ? 'table-danger' : ($d['status'] === 'partial' ? 'table-warning' : '') ?>">
                                    <td><?= $counter++ ?></td>
                                    <td><?= $d['month_name'] ?></td>
                                    <td><?= $d['due_year'] ?></td>
                                    <td><?= number_format($d['amount'], 2) ?> ৳</td>
                                    <td>
                                        <?php if ($d['paid_amount'] > 0): ?>
                                            <?= number_format($d['paid_amount'], 2) ?> ৳
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="<?= $d['due_remaining'] > 0 ? 'text-danger' : 'text-success' ?>" style="font-weight:600;">
                                        <?= number_format($d['due_remaining'], 2) ?> ৳
                                    </td>
                                    <td>
                                        <?php if ($d['status'] === 'paid'): ?>
                                            <span class="badge bg-success">✅ পরিশোধিত</span>
                                        <?php elseif ($d['status'] === 'partial'): ?>
                                            <span class="badge bg-warning text-dark">⏳ আংশিক</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">⛔ বকেয়া</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($d['receipt_no']): ?>
                                            <small><?= htmlspecialchars($d['receipt_no']) ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($d['status'] !== 'paid'): ?>
                                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#payModal"
                                            onclick="setPayData(<?= $selectedMemberId ?>, <?= $d['due_year'] ?>, <?= $d['due_month'] ?>, <?= $d['due_remaining'] ?>, '<?= addslashes($memberData['name']) ?>')">
                                            <i data-feather="dollar-sign" style="width:13px;height:13px"></i> পে
                                        </button>
                                        <?php else: ?>
                                            <span class="text-muted">✅ সম্পন্ন</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                            <?php if (!empty($dueHistory)): ?>
                            <tfoot>
                                <tr style="font-weight:bold;background:#f8f9fa">
                                    <td colspan="3" class="text-end">সর্বমোট:</td>
                                    <td><?= number_format($summary['total_amount'], 2) ?> ৳</td>
                                    <td><?= number_format($summary['total_paid'], 2) ?> ৳</td>
                                    <td class="text-danger"><?= number_format($summary['total_due'], 2) ?> ৳</td>
                                    <td colspan="3"></td>
                                </tr>
                            </tfoot>
                            <?php endif; ?>
                        </table>
                    </div>
                    
                    <!-- Quick Summary Badges -->
                    <div class="row g-2 mt-3">
                        <div class="col-12 col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6><i data-feather="alert-circle" style="width:16px;height:16px;color:#dc3545"></i> বকেয়া/আংশিক মাসসমূহ</h6>
                                    <?php
                                    $unpaidMonths = array_filter($dueHistory, function($d) {
                                        return $d['status'] === 'unpaid' || $d['status'] === 'partial';
                                    });
                                    ?>
                                    <?php if (empty($unpaidMonths)): ?>
                                        <p class="text-success mb-0">🎉 এই সদস্যের কোনো বকেয়া নেই!</p>
                                    <?php else: ?>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php foreach ($unpaidMonths as $d): ?>
                                                <span class="badge bg-danger">
                                                    <?= $d['month_name'] ?> <?= $d['due_year'] ?>
                                                    <?php if ($d['status'] === 'partial'): ?>
                                                        <span class="badge bg-warning text-dark">আংশিক</span>
                                                    <?php endif; ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                        <small class="text-muted">মোট <?= count($unpaidMonths) ?> মাস বকেয়া/আংশিক</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6><i data-feather="check-circle" style="width:16px;height:16px;color:#28a745"></i> পরিশোধিত মাসসমূহ</h6>
                                    <?php
                                    $paidMonths = array_filter($dueHistory, function($d) {
                                        return $d['status'] === 'paid';
                                    });
                                    ?>
                                    <?php if (empty($paidMonths)): ?>
                                        <p class="text-muted mb-0">কোনো পরিশোধিত মাস নেই</p>
                                    <?php else: ?>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php foreach ($paidMonths as $d): ?>
                                                <span class="badge bg-success">
                                                    <?= $d['month_name'] ?> <?= $d['due_year'] ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                        <small class="text-muted">মোট <?= count($paidMonths) ?> মাস পরিশোধিত</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- DEPOSIT HISTORY TAB -->
                <!-- ============================================ -->
                <div class="tab-pane fade" id="depositTab">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>তারিখ</th>
                                    <th>পরিমাণ</th>
                                    <th>পদ্ধতি</th>
                                    <th>রসিদ নং</th>
                                    <th>ধরন</th>
                                    <th>মন্তব্য</th>
                                    <th>সংগ্রহকারী</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($depositHistory)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i data-feather="inbox" style="width:32px;height:32px;display:block;margin:0 auto 10px"></i>
                                        কোনো জমা রেকর্ড পাওয়া যায়নি
                                    </td>
                                </tr>
                                <?php else: 
                                    $counter = 1;
                                    foreach ($depositHistory as $dep): 
                                ?>
                                <tr>
                                    <td><?= $counter++ ?></td>
                                    <td><?= date('d M Y', strtotime($dep['deposit_date'])) ?></td>
                                    <td class="text-success" style="font-weight:600;">+ <?= number_format($dep['amount'], 2) ?> ৳</td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php 
                                            $methods = [
                                                'cash' => 'নগদ',
                                                'bank' => 'ব্যাংক',
                                                'mobile' => 'মোবাইল',
                                                'check' => 'চেক',
                                                'online' => 'অনলাইন'
                                            ];
                                            echo $methods[$dep['payment_method']] ?? ucfirst($dep['payment_method']);
                                            ?>
                                        </span>
                                    </td>
                                    <td><small><?= htmlspecialchars($dep['receipt_no'] ?? '—') ?></small></td>
                                    <td>
                                        <span class="badge bg-success">
                                            <?= $dep['deposit_type'] === 'regular' ? 'নিয়মিত' : 'বিশেষ' ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($dep['note'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($dep['collected_by'] ?? '—') ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                            <?php if (!empty($depositHistory)): ?>
                            <tfoot>
                                <tr style="font-weight:bold;background:#f8f9fa">
                                    <td colspan="2" class="text-end">সর্বমোট জমা:</td>
                                    <td class="text-success"><?= number_format($summary['total_deposit'], 2) ?> ৳</td>
                                    <td colspan="5"></td>
                                </tr>
                            </tfoot>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- WITHDRAWAL HISTORY TAB -->
                <!-- ============================================ -->
                <div class="tab-pane fade" id="withdrawalTab">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>তারিখ</th>
                                    <th>পরিমাণ</th>
                                    <th>পদ্ধতি</th>
                                    <th>লেনদেন নম্বর</th>
                                    <th>অবস্থা</th>
                                    <th>মন্তব্য</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($withdrawalHistory)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i data-feather="inbox" style="width:32px;height:32px;display:block;margin:0 auto 10px"></i>
                                        কোনো উত্তোলন রেকর্ড পাওয়া যায়নি
                                    </td>
                                </tr>
                                <?php else: 
                                    $counter = 1;
                                    foreach ($withdrawalHistory as $wd): 
                                ?>
                                <tr>
                                    <td><?= $counter++ ?></td>
                                    <td><?= date('d M Y', strtotime($wd['withdrawal_date'])) ?></td>
                                    <td class="text-danger" style="font-weight:600;">- <?= number_format($wd['amount'], 2) ?> ৳</td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php 
                                            $methods = [
                                                'cash' => 'নগদ',
                                                'bank' => 'ব্যাংক',
                                                'mobile' => 'মোবাইল',
                                                'check' => 'চেক',
                                                'online' => 'অনলাইন'
                                            ];
                                            echo $methods[$wd['payment_method']] ?? ucfirst($wd['payment_method']);
                                            ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($wd['transaction_no'] ?? '—') ?></td>
                                    <td>
                                        <span class="badge bg-success">✅ সম্পন্ন</span>
                                    </td>
                                    <td><?= htmlspecialchars($wd['note'] ?? '—') ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                            <?php if (!empty($withdrawalHistory)): ?>
                            <tfoot>
                                <tr style="font-weight:bold;background:#f8f9fa">
                                    <td colspan="2" class="text-end">সর্বমোট উত্তোলন:</td>
                                    <td class="text-danger"><?= number_format($summary['total_withdrawal'], 2) ?> ৳</td>
                                    <td colspan="4"></td>
                                </tr>
                            </tfoot>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- ============================================ -->
    <!-- DEFAULT: LIST OF ALL MEMBERS -->
    <!-- ============================================ -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="card-title">
                <i data-feather="list" style="width:16px;height:16px"></i>
                সকল সদস্যের বকেয়া সংক্ষিপ্ত তালিকা
            </span>
            <span>
                <span class="badge bg-secondary"><?= count($members) ?> জন সদস্য</span>
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>সদস্য</th>
                        <th>আইডি</th>
                        <th>ফোন</th>
                        <th>যোগদান</th>
                        <th>বকেয়া মাস</th>
                        <th>মোট বকেয়া</th>
                        <th>কার্যক্রম</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($members)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            <i data-feather="users" style="width:32px;height:32px;display:block;margin:0 auto 10px"></i>
                            কোনো সদস্য পাওয়া যায়নি
                        </td>
                    </tr>
                    <?php else: 
                        $counter = 1;
                        foreach ($members as $m): 
                            $dueSummary = getMemberDueSummary($m['id']);
                    ?>
                    <tr class="clickable-row" data-href="member_due_report.php?member_id=<?= $m['id'] ?>" style="cursor:pointer;">
                        <td><?= $counter++ ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <?php 
                                if (!empty($m['photo'])): 
                                    $photoPath = $m['photo'];
                                    if (file_exists($_SERVER['DOCUMENT_ROOT'] . $photoPath)):
                                ?>
                                    <img src="<?= $photoPath ?>" alt="Profile Photo" 
                                         style="width:32px;height:32px;border-radius:50%;object-fit:cover;border:1px solid #ddd;">
                                <?php 
                                    else:
                                ?>
                                        <div class="member-avatar" style="background:#1B4F72; color:white; width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:14px;">
                                            <?= strtoupper(substr(trim($m['name']), 0, 1)) ?>
                                        </div>
                                    <?php 
                                    endif;
                                else: 
                                ?>
                                    <div class="member-avatar" style="background:#1B4F72; color:white; width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:14px;">
                                        <?= strtoupper(substr(trim($m['name']), 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                <span style="font-weight:500"><?= htmlspecialchars($m['name']) ?></span>
                            </div>
                        </td>
                        <td><code><?= htmlspecialchars($m['member_id']) ?></code></td>
                        <td><?= htmlspecialchars($m['phone'] ?? '—') ?></td>
                        <td><small><?= date('M Y', strtotime($m['join_date'] ?? date('Y-m-d'))) ?></small></td>
                        <td>
                            <span class="badge <?= ($dueSummary['unpaid_months'] ?? 0) > 0 ? 'bg-danger' : 'bg-success' ?>">
                                <?= $dueSummary['unpaid_months'] ?? 0 ?> মাস
                            </span>
                        </td>
                        <td class="<?= ($dueSummary['total_due'] ?? 0) > 0 ? 'text-danger' : 'text-success' ?>" style="font-weight:600;">
                            <?= number_format($dueSummary['total_due'] ?? 0, 2) ?> ৳
                        </td>
                        <td>
                            <a href="member_due_report.php?member_id=<?= $m['id'] ?>" class="btn btn-sm btn-primary">
                                <i data-feather="eye" style="width:14px;height:14px"></i> বিস্তারিত
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ============================================ -->
<!-- PAYMENT MODAL -->
<!-- ============================================ -->
<div class="modal fade" id="payModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">সঞ্চয় গ্রহণ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="pay">
                    <input type="hidden" name="member_id" id="payMemberId">
                    <input type="hidden" name="year" id="payYear">
                    <input type="hidden" name="month" id="payMonth">
                    
                    <div class="mb-3">
                        <label class="form-label">সদস্য</label>
                        <p class="fw-bold" id="payMemberName">—</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">মাস</label>
                        <p class="fw-bold" id="payMonthName">—</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">পরিমাণ (৳)</label>
                        <input type="number" step="0.01" name="amount" id="payAmount" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">তারিখ</label>
                        <input type="date" name="paid_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">নোট</label>
                        <input type="text" name="note" class="form-control" placeholder="ঐচ্ছিক নোট">
                    </div>
                    <div class="alert alert-success small">
                        <i data-feather="info" style="width:14px;height:14px"></i>
                        সঞ্চয় গ্রহণ করলে স্বয়ংক্রিয়ভাবে ডিপোজিট তৈরি হবে।
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
                    <button type="submit" class="btn btn-success">গ্রহণ করুন</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.stat-card {
    border-radius: 12px;
    padding: 14px 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    height: 100%;
    transition: transform 0.2s;
}
.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}
.stat-card .stat-value {
    font-size: 22px;
    font-weight: 700;
}
.stat-card .stat-label {
    font-size: 12px;
    font-weight: 500;
}

.search-box {
    position: relative;
}
.search-box .search-icon {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    width: 16px;
    height: 16px;
    color: #999;
}
.search-box input {
    padding-left: 34px;
}

.member-avatar {
    font-size: 13px;
    flex-shrink: 0;
}

.clickable-row:hover {
    background: #f0f7ff !important;
}

.select2-container {
    width: 100% !important;
}
.select2-container--default .select2-selection--single {
    height: 38px !important;
    border: 1px solid #ced4da !important;
    border-radius: 8px !important;
    background: #fff !important;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 36px !important;
    padding-left: 12px !important;
    color: #212529 !important;
    font-size: 14px !important;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 36px !important;
}
.select2-container--default .select2-dropdown {
    border: 1px solid #ced4da !important;
    border-radius: 8px !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important;
    z-index: 9999 !important;
}
.select2-container--default .select2-search--dropdown .select2-search__field {
    border: 1px solid #ced4da !important;
    border-radius: 6px !important;
    padding: 8px 12px !important;
}

.nav-tabs .nav-link {
    color: #495057;
    border: none;
    border-bottom: 3px solid transparent;
    padding: 10px 20px;
    font-weight: 500;
}
.nav-tabs .nav-link:hover {
    border-color: transparent;
    background: #f8f9fa;
}
.nav-tabs .nav-link.active {
    border-bottom: 3px solid #1B4F72;
    color: #1B4F72;
    background: transparent;
}
.nav-tabs .nav-link i {
    margin-right: 6px;
}

.table-danger td {
    background-color: #fff5f5 !important;
}
.table-warning td {
    background-color: #fffbf0 !important;
}

@media print {
    .btn, .btn-group, .no-print {
        display: none !important;
    }
    .stat-card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
    .card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
    .nav-tabs {
        display: none !important;
    }
    .tab-pane {
        display: block !important;
    }
    .tab-pane.fade {
        opacity: 1 !important;
    }
    .modal {
        display: none !important;
    }
}

@media (max-width: 768px) {
    .table td, .table th {
        padding: 6px 8px;
        font-size: 12px;
    }
    .card-header {
        padding: 10px 14px;
    }
    .card-body {
        padding: 12px 14px;
    }
    .nav-tabs .nav-link {
        padding: 8px 12px;
        font-size: 13px;
    }
}
</style>

<!-- Select2 CDN -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    $('.select2-searchable').select2({
        placeholder: '-- সকল সদস্য --',
        allowClear: true,
        width: '100%',
        language: {
            searching: function() {
                return 'খুঁজছেন...';
            },
            noResults: function() {
                return 'কোনো সদস্য পাওয়া যায়নি';
            },
            inputTooShort: function() {
                return 'অনুগ্রহ করে আরও অক্ষর টাইপ করুন';
            }
        }
    });
    
    $('.clickable-row').on('click', function() {
        window.location = $(this).data('href');
    });
});

function setPayData(memberId, year, month, amount, memberName) {
    document.getElementById('payMemberId').value = memberId;
    document.getElementById('payYear').value = year;
    document.getElementById('payMonth').value = month;
    document.getElementById('payMemberName').textContent = memberName || 'অজানা';
    document.getElementById('payMonthName').textContent = getMonthName(month) + ' ' + year;
    document.getElementById('payAmount').value = amount;
}

function getMonthName(month) {
    const months = {
        1: 'জানুয়ারি', 2: 'ফেব্রুয়ারি', 3: 'মার্চ',
        4: 'এপ্রিল', 5: 'মে', 6: 'জুন',
        7: 'জুলাই', 8: 'আগস্ট', 9: 'সেপ্টেম্বর',
        10: 'অক্টোবর', 11: 'নভেম্বর', 12: 'ডিসেম্বর'
    };
    return months[month] || month;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>