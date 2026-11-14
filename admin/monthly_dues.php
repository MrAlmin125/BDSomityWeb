<?php
// =====================================================
// admin/monthly_dues.php - MONTHLY DEPOSIT LIST (WITH COLORED PAID AMOUNT)
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

$pageTitle = 'মাসিক সঞ্চয় তালিকা';
$error = '';
$success = '';

// ============================================
// FIX: Use Previous Month for calculations
// ============================================
$currentMonth = date('n');
$currentYear = date('Y');

// Calculate previous month
$prevMonth = $currentMonth - 1;
$prevYear = $currentYear;
if ($prevMonth == 0) {
    $prevMonth = 12;
    $prevYear = $currentYear - 1;
}

// ---- Helper function to get profile picture ----
function getMemberAvatar($memberId, $photoPath = null) {
    global $conn;
    
    // If photo path is provided and not empty, check if file exists
    if (!empty($photoPath)) {
        $cleanPath = ltrim($photoPath, '/');
        
        // Check multiple possible paths
        $possiblePaths = [
            $_SERVER['DOCUMENT_ROOT'] . '/' . $cleanPath,
            $_SERVER['DOCUMENT_ROOT'] . $photoPath,
            __DIR__ . '/../' . $cleanPath,
            __DIR__ . '/../uploads/members/' . basename($photoPath),
        ];
        
        foreach ($possiblePaths as $fullPath) {
            if (file_exists($fullPath) && is_file($fullPath)) {
                // Return the path relative to document root
                return '/' . ltrim(str_replace($_SERVER['DOCUMENT_ROOT'], '', $fullPath), '/');
            }
        }
    }
    
    // No photo found, return null to use avatar
    return null;
}

// ---- Helper function to get member name initial for avatar ----
function getMemberInitial($name) {
    if (empty($name)) {
        return '?';
    }
    // Get first character and convert to uppercase
    $initial = mb_substr($name, 0, 1, 'UTF-8');
    return strtoupper($initial);
}

// ---- Helper function to get avatar color ----
function getAvatarColor($name) {
    $colors = [
        '#1B4F72', '#2E86C1', '#117A65', '#C0392B', '#8E44AD', 
        '#D35400', '#27AE60', '#2980B9', '#6C3483', '#1A5276',
        '#0E6655', '#922B21', '#7D6608', '#1E8449', '#566573'
    ];
    $index = abs(crc32($name)) % count($colors);
    return $colors[$index];
}

// ---- Helper function to get Bengali month name ----
function getBengaliMonthName($month) {
    $months = [
        1 => 'জানুয়ারি', 2 => 'ফেব্রুয়ারি', 3 => 'মার্চ',
        4 => 'এপ্রিল', 5 => 'মে', 6 => 'জুন',
        7 => 'জুলাই', 8 => 'আগস্ট', 9 => 'সেপ্টেম্বর',
        10 => 'অক্টোবর', 11 => 'নভেম্বর', 12 => 'ডিসেম্বর'
    ];
    return $months[(int)$month] ?? $month;
}

// ---- Helper function to check and update dues from deposits ----
function syncDuesWithDeposits($memberId, $year, $month) {
    $conn = conn();
    
    // Check if there's a due record for this member/month/year
    $due = queryOne(
        "SELECT * FROM monthly_dues WHERE member_id = ? AND due_year = ? AND due_month = ?",
        'iii',
        [$memberId, $year, $month]
    );
    
    if (!$due) {
        return false; // No due record exists
    }
    
    // Check if deposit exists for this month/year (ONLY 'regular' deposits)
    $deposit = queryOne(
        "SELECT * FROM deposits WHERE member_id = ? AND YEAR(deposit_date) = ? AND MONTH(deposit_date) = ? AND deposit_type = 'regular'",
        'iii',
        [$memberId, $year, $month]
    );
    
    if ($deposit) {
        // There's a deposit for this month - mark as paid
        $dueAmount = (float)$due['amount'];
        $paidAmount = (float)$deposit['amount'];
        $status = $paidAmount >= $dueAmount ? 'paid' : 'partial';
        $receiptNo = $deposit['receipt_no'];
        
        $stmt = $conn->prepare("
            UPDATE monthly_dues 
            SET paid_amount = ?, 
                paid_date = ?, 
                status = ?, 
                receipt_no = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->bind_param('dsssi', $paidAmount, $deposit['deposit_date'], $status, $receiptNo, $due['id']);
        
        if ($stmt->execute()) {
            logActivity('due_auto_paid', "Due #{$due['id']} auto-paid from deposit #{$deposit['id']} for member $memberId");
            return true;
        }
    } else {
        // No deposit found - ensure due is unpaid
        if ($due['status'] !== 'unpaid') {
            $stmt = $conn->prepare("
                UPDATE monthly_dues 
                SET paid_amount = 0, 
                    paid_date = NULL, 
                    status = 'unpaid', 
                    receipt_no = NULL,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param('i', $due['id']);
            $stmt->execute();
            logActivity('due_reverted', "Due #{$due['id']} reverted to unpaid for member $memberId");
        }
    }
    
    return false;
}

// ---- Generate Deposit Receipt Number ----
function generateDepositReceiptNo($conn) {
    do {
        $receipt = 'DEP-' . date('Ymd') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
    } while (queryOne("SELECT id FROM deposits WHERE receipt_no = ?", 's', [$receipt]));
    return $receipt;
}

// ============================================
// ENSURE ALL MEMBERS HAVE DUES FOR PREVIOUS MONTH
// ============================================
function ensureAllMembersHaveDues($year, $month, $defaultAmount = 500) {
    $conn = conn();
    
    // Get all active members
    $members = queryAll("SELECT id, monthly_deposit FROM members WHERE status = 'active'");
    
    if (empty($members)) {
        return ['created' => 0, 'total' => 0];
    }
    
    $created = 0;
    $total = count($members);
    
    foreach ($members as $member) {
        // Check if due exists
        $exists = queryOne(
            "SELECT id FROM monthly_dues WHERE member_id = ? AND due_year = ? AND due_month = ?",
            'iii',
            [$member['id'], $year, $month]
        );
        
        if (!$exists) {
            // Use member's monthly_deposit or default
            $amount = !empty($member['monthly_deposit']) ? (float)$member['monthly_deposit'] : $defaultAmount;
            
            $stmt = $conn->prepare("
                INSERT INTO monthly_dues (member_id, due_year, due_month, amount, status, created_at) 
                VALUES (?, ?, ?, ?, 'unpaid', NOW())
            ");
            $stmt->bind_param('iiid', $member['id'], $year, $month, $amount);
            
            if ($stmt->execute()) {
                $created++;
                // Check if deposit exists for this month
                syncDuesWithDeposits($member['id'], $year, $month);
            }
        } else {
            // Due exists, just sync with deposits
            syncDuesWithDeposits($member['id'], $year, $month);
        }
    }
    
    return ['created' => $created, 'total' => $total];
}

// ---- Bulk sync for all dues ----
function syncAllDuesForMonth($year, $month) {
    $dues = queryAll(
        "SELECT * FROM monthly_dues WHERE due_year = ? AND due_month = ?",
        'ii',
        [$year, $month]
    );
    
    $updated = 0;
    foreach ($dues as $due) {
        if (syncDuesWithDeposits($due['member_id'], $year, $month)) {
            $updated++;
        }
    }
    
    return $updated;
}

// ---- Handle POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'নিরাপত্তা যাচাই ব্যর্থ।';
    } else {
        $action = $_POST['action'] ?? '';
        
        // Generate dues
        if ($action === 'generate') {
            $year = (int)$_POST['year'];
            $month = (int)$_POST['month'];
            $amount = (float)($_POST['amount'] ?? 500);
            
            if ($year >= 2020 && $month >= 1 && $month <= 12) {
                $result = ensureAllMembersHaveDues($year, $month, $amount);
                
                if ($result['created'] > 0) {
                    $success = "$year সালের " . getBengaliMonthName($month) . " মাসের জন্য {$result['created']} টি নতুন সঞ্চয় তৈরি হয়েছে। মোট সদস্য: {$result['total']}";
                    logActivity('dues_generate', "Generated dues for $year-$month ({$result['created']} new, {$result['total']} total)");
                } else {
                    $success = "সব সদস্যের জন্য ইতিমধ্যে সঞ্চয় রয়েছে। মোট সদস্য: {$result['total']}";
                }
                
                header('Location: monthly_dues.php?month=' . $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT));
                exit;
            } else {
                $error = 'সঠিক বছর ও মাস নির্বাচন করুন।';
            }
        }
        
        // Collect payment
        if ($action === 'collect') {
            $dueId = (int)$_POST['due_id'];
            $paidAmount = (float)$_POST['paid_amount'];
            $paidDate = $_POST['paid_date'] ?? date('Y-m-d');
            $note = $_POST['note'] ?? '';
            
            if ($dueId <= 0) {
                $error = 'সঞ্চয় নির্বাচন করুন।';
            } elseif ($paidAmount <= 0) {
                $error = 'পরিমাণ দিন।';
            } else {
                $due = queryOne("SELECT * FROM monthly_dues WHERE id=?", 'i', [$dueId]);
                if (!$due) {
                    $error = 'সঞ্চয় পাওয়া যায়নি।';
                } else {
                    $conn = conn();
                    
                    // CREATE DEPOSIT ENTRY FOR THIS PAYMENT
                    $depositReceipt = generateDepositReceiptNo($conn);
                    $adminId = $_SESSION['admin_id'] ?? 1;
                    
                    $depositStmt = $conn->prepare("
                        INSERT INTO deposits (member_id, amount, deposit_date, deposit_type, payment_method, receipt_no, note, collected_by)
                        VALUES (?, ?, ?, 'regular', 'cash', ?, ?, ?)
                    ");
                    $depositStmt->bind_param('idsssi', 
                        $due['member_id'], 
                        $paidAmount, 
                        $paidDate, 
                        $depositReceipt, 
                        $note, 
                        $adminId
                    );
                    
                    if ($depositStmt->execute()) {
                        $depositId = $conn->insert_id;
                        
                        // Now update the due
                        $newPaid = (float)$due['paid_amount'] + $paidAmount;
                        $status = $newPaid >= $due['amount'] ? 'paid' : 'partial';
                        
                        $stmt = $conn->prepare("UPDATE monthly_dues SET paid_amount=?, paid_date=?, status=?, receipt_no=?, collected_by=?, note=?, updated_at=NOW() WHERE id=?");
                        $stmt->bind_param('dsssssi', $newPaid, $paidDate, $status, $depositReceipt, $adminId, $note, $dueId);
                        
                        if ($stmt->execute()) {
                            $success = "সঞ্চয় গ্রহণ সফল! রসিদ নং: $depositReceipt";
                            logActivity('dues_collect', "সঞ্চয় গ্রহণ: $depositReceipt, Amount: $paidAmount, Deposit ID: $depositId");
                        } else {
                            $error = 'সঞ্চয় আপডেট ব্যর্থ: ' . $conn->error;
                        }
                    } else {
                        $error = 'ডিপোজিট তৈরি ব্যর্থ: ' . $conn->error;
                    }
                }
            }
        }
        
        // Sync with deposits
        if ($action === 'sync_deposits') {
            $year = (int)$_POST['sync_year'];
            $month = (int)$_POST['sync_month'];
            
            if ($year >= 2020 && $month >= 1 && $month <= 12) {
                // First ensure all members have dues
                ensureAllMembersHaveDues($year, $month);
                
                // Then sync with deposits
                $updated = syncAllDuesForMonth($year, $month);
                $success = "$year সালের " . getBengaliMonthName($month) . " মাসের $updated টি সঞ্চয় ডিপোজিটের সাথে সিঙ্ক করা হয়েছে।";
                logActivity('dues_sync', "Synced dues with deposits for $year-$month ($updated updated)");
            } else {
                $error = 'সঠিক বছর ও মাস নির্বাচন করুন।';
            }
        }
        
        // Edit due
        if ($action === 'edit') {
            $dueId = (int)$_POST['due_id'];
            $amount = (float)$_POST['amount'];
            $note = $_POST['note'] ?? '';
            
            if ($dueId <= 0) {
                $error = 'সঞ্চয় নির্বাচন করুন।';
            } elseif ($amount <= 0) {
                $error = 'সঠিক পরিমাণ দিন।';
            } else {
                $conn = conn();
                $stmt = $conn->prepare("UPDATE monthly_dues SET amount=?, note=?, updated_at=NOW() WHERE id=?");
                $stmt->bind_param('dsi', $amount, $note, $dueId);
                
                if ($stmt->execute()) {
                    $success = "সঞ্চয় আপডেট সফল!";
                    logActivity('dues_edit', "সঞ্চয় এডিট ID: $dueId");
                } else {
                    $error = 'আপডেট ব্যর্থ: ' . $conn->error;
                }
            }
        }
    }
}

// ---- GET: Delete ----
if (isset($_GET['delete']) && $_GET['delete'] > 0) {
    $dueId = (int)$_GET['delete'];
    if (!verifyCSRF($_GET['csrf_token'] ?? '')) {
        $error = 'নিরাপত্তা যাচাই ব্যর্থ।';
    } else {
        $conn = conn();
        $stmt = $conn->prepare("DELETE FROM monthly_dues WHERE id=?");
        $stmt->bind_param('i', $dueId);
        if ($stmt->execute()) {
            $success = 'সঞ্চয় ডিলিট করা হয়েছে।';
            logActivity('dues_delete', "সঞ্চয় ডিলিট ID: $dueId");
        } else {
            $error = 'ডিলিট করতে ব্যর্থ হয়েছে।';
        }
    }
}

// ---- GET Data & Filter Handling ----
// ============================================
// FIX: Default to PREVIOUS MONTH
// ============================================
$getMonthInput = $_GET['month'] ?? $prevYear . '-' . str_pad($prevMonth, 2, '0', STR_PAD_LEFT);

$filterYear = $prevYear;
$filterMonth = $prevMonth;

if (!empty($getMonthInput) && strpos($getMonthInput, '-') !== false) {
    $parts = explode('-', $getMonthInput);
    $filterYear = (int)$parts[0];
    $filterMonth = (int)$parts[1];
}

// Get filter_type from URL (all, due, paid)
$filterType = $_GET['filter_type'] ?? 'all';
$filterStatus = $_GET['status'] ?? '';
$searchTerm = trim($_GET['search'] ?? '');

// Check if a button filter is active (all, due, paid)
$isButtonFilter = $filterType !== 'all';

// Auto-create missing dues for all members for the selected month
$ensureResult = ensureAllMembersHaveDues($filterYear, $filterMonth);

// Then sync with deposits
syncAllDuesForMonth($filterYear, $filterMonth);

// ============================================
// BUILD QUERY BASED ON FILTERS
// ============================================
$duesList = [];

// If button filter is active (due or paid), show ALL months
if ($isButtonFilter) {
    // Get ALL dues from ALL months
    $sql = "SELECT * FROM monthly_dues ORDER BY due_year DESC, due_month DESC, id DESC";
    $duesData = queryAll($sql);
} else {
    // Get dues for the selected month only
    $sql = "SELECT * FROM monthly_dues WHERE due_year = ? AND due_month = ?";
    $params = [$filterYear, $filterMonth];
    $types = 'ii';
    $duesData = queryAll($sql, $types, $params);
}

if (!empty($duesData)) {
    foreach ($duesData as $due) {
        $member = queryOne("SELECT id, name, member_id, phone, monthly_deposit, photo FROM members WHERE id = ?", 'i', [$due['member_id']]);
        
        if ($member) {
            $due['mname'] = $member['name'];
            $due['mid'] = $member['member_id'];
            $due['mphone'] = $member['phone'];
            $due['monthly_deposit'] = $member['monthly_deposit'];
            $due['photo'] = $member['photo'];
            
            // Get profile picture or avatar
            $photoUrl = getMemberAvatar($member['id'], $member['photo']);
            if ($photoUrl) {
                $due['avatar'] = $photoUrl;
                $due['avatar_type'] = 'image';
            } else {
                $due['avatar'] = getMemberInitial($member['name']);
                $due['avatar_color'] = getAvatarColor($member['name']);
                $due['avatar_type'] = 'text';
            }
        } else {
            $due['mname'] = 'সদস্য নাই';
            $due['mid'] = 'N/A';
            $due['mphone'] = '';
            $due['monthly_deposit'] = 0;
            $due['photo'] = null;
            $due['avatar'] = '?';
            $due['avatar_color'] = '#6C3483';
            $due['avatar_type'] = 'text';
        }
        
        // Add month name to the due data
        $due['month_name'] = getBengaliMonthName($due['due_month']);
        $due['month_year'] = $due['due_year'];
        
        // ============================================
        // APPLY FILTERS
        // ============================================
        
        // 1. Filter by type (all, due, paid) - BUTTON FILTER
        if ($filterType === 'due') {
            // Show only unpaid and partial
            if ($due['status'] !== 'unpaid' && $due['status'] !== 'partial') {
                continue;
            }
        } elseif ($filterType === 'paid') {
            // Show only paid
            if ($due['status'] !== 'paid') {
                continue;
            }
        }
        // 'all' shows everything (no extra filter)
        
        // 2. Apply status filter (existing filter)
        if ($filterStatus && $due['status'] != $filterStatus) {
            continue;
        }
        
        // 3. Apply search filter (existing filter)
        if (!empty($searchTerm)) {
            $searchLower = strtolower($searchTerm);
            $nameLower = strtolower($due['mname'] ?? '');
            $midLower = strtolower($due['mid'] ?? '');
            $phoneLower = strtolower($due['mphone'] ?? '');
            $receiptLower = strtolower($due['receipt_no'] ?? '');
            if (strpos($nameLower, $searchLower) === false && 
                strpos($midLower, $searchLower) === false && 
                strpos($phoneLower, $searchLower) === false &&
                strpos($receiptLower, $searchLower) === false) {
                continue;
            }
        }
        
        $duesList[] = $due;
    }
}

// Sort by name
usort($duesList, function($a, $b) {
    return strcmp($a['mname'] ?? '', $b['mname'] ?? '');
});

// Statistics
$total = count($duesList);
$paid = 0;
$unpaid = 0;
$partial = 0;

foreach ($duesList as $d) {
    if ($d['status'] === 'paid') $paid++;
    elseif ($d['status'] === 'partial') $partial++;
    else $unpaid++;
}

$totalAmount = 0;
$totalPaid = 0;
foreach ($duesList as $d) {
    $totalAmount += (float)$d['amount'];
    $totalPaid += (float)$d['paid_amount'];
}
$totalDue = $totalAmount - $totalPaid;

$monthlyDueAmount = 500;

// Month name for display
$displayMonthName = getBengaliMonthName($filterMonth) . ' ' . $filterYear;

// Include header
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container mt-3">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>
            <i data-feather="calendar" style="width:20px;height:20px"></i> 
            মাসিক সঞ্চয় তালিকা
            <?php if ($isButtonFilter): ?>
                <span class="badge bg-warning ms-2">সকল মাস</span>
            <?php else: ?>
                <small class="text-muted" style="font-size:14px;">
                    (<?= $displayMonthName ?>)
                </small>
                <span class="badge bg-info ms-2">পূর্ববর্তী মাস</span>
            <?php endif; ?>
        </h4>
        <div>
            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#syncModal">
                <i data-feather="refresh-cw" style="width:14px;height:14px"></i> ডিপোজিট সিঙ্ক
            </button>
            <button class="btn btn-info btn-sm" onclick="window.print()">
                <i data-feather="printer" style="width:14px;height:14px"></i> প্রিন্ট
            </button>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#generateModal">
                <i data-feather="plus" style="width:14px;height:14px"></i> সঞ্চয় তৈরি
            </button>
        </div>
    </div>

    <!-- Info Banner -->
    <?php if ($ensureResult['created'] > 0 && !$isButtonFilter): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i data-feather="check-circle" style="width:16px;height:16px"></i>
        <strong>স্বয়ংক্রিয়:</strong> <?= $ensureResult['created'] ?> জন সদস্যের জন্য নতুন সঞ্চয় তৈরি করা হয়েছে (<?= $displayMonthName ?>).
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-2">
            <div class="stat-card" style="background: linear-gradient(135deg, #1B4F72, #2E86C1); color: white;">
                <div class="stat-value" style="color: white;"><?= $total ?></div>
                <div class="stat-label" style="color: rgba(255,255,255,0.8);">মোট</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card" style="background: linear-gradient(135deg, #1E8449, #27AE60); color: white;">
                <div class="stat-value" style="color: white;"><?= $paid ?></div>
                <div class="stat-label" style="color: rgba(255,255,255,0.8);">পরিশোধিত</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card" style="background: linear-gradient(135deg, #922B21, #E74C3C); color: white;">
                <div class="stat-value" style="color: white;"><?= $unpaid ?></div>
                <div class="stat-label" style="color: rgba(255,255,255,0.8);">বাকি</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card" style="background: linear-gradient(135deg, #B7950B, #F1C40F); color: white;">
                <div class="stat-value" style="color: white;"><?= $partial ?></div>
                <div class="stat-label" style="color: rgba(255,255,255,0.8);">আংশিক</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card" style="background: linear-gradient(135deg, #0E6655, #1ABC9C); color: white;">
                <div class="stat-value" style="color: white; font-size:16px;"><?= number_format($totalAmount, 2) ?> ৳</div>
                <div class="stat-label" style="color: rgba(255,255,255,0.8);">মোট পরিমাণ</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card" style="background: linear-gradient(135deg, #6C3483, #AF7AC5); color: white;">
                <div class="stat-value" style="color: white; font-size:16px;"><?= number_format($totalDue, 2) ?> ৳</div>
                <div class="stat-label" style="color: rgba(255,255,255,0.8);">মোট বাকি</div>
            </div>
        </div>
    </div>

    <!-- Filters (Existing - Month, Status, Search) -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <input type="hidden" name="filter_type" value="<?= $filterType ?>">
                <div class="col-6 col-md-3">
                    <input type="month" name="month" class="form-control" 
                        value="<?= $filterYear . '-' . str_pad($filterMonth, 2, '0', STR_PAD_LEFT) ?>"
                        onchange="this.form.submit()">
                </div>
                <div class="col-6 col-md-3">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">সব অবস্থা</option>
                        <option value="paid" <?= $filterStatus === 'paid' ? 'selected' : '' ?>>পরিশোধিত</option>
                        <option value="unpaid" <?= $filterStatus === 'unpaid' ? 'selected' : '' ?>>বাকি</option>
                        <option value="partial" <?= $filterStatus === 'partial' ? 'selected' : '' ?>>আংশিক</option>
                    </select>
                </div>
                <div class="col-6 col-md-4">
                    <div class="search-box">
                        <i data-feather="search" class="search-icon"></i>
                        <input type="text" name="search" class="form-control" 
                               placeholder="নাম, আইডি, রসিদ বা ফোন দিয়ে খুঁজুন..." 
                               value="<?= htmlspecialchars($searchTerm) ?>">
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i data-feather="search" style="width:14px;height:14px"></i> খুঁজুন
                    </button>
                </div>
            </form>
        </div>
    </div>
    
   <!-- ============================================ -->
    <!-- 3 FILTER BUTTONS (All / Dues / Paid) -->
    <!-- ============================================ -->
    <div class="mb-3">
        <div class="btn-group" role="group">
            <a href="monthly_dues.php?filter_type=all&status=<?= $filterStatus ?>&search=<?= urlencode($searchTerm) ?>" 
               class="btn btn-outline-primary <?= $filterType === 'all' ? 'active' : '' ?>">
                <i data-feather="list" style="width:14px;height:14px"></i> সব
                <span class="badge bg-secondary ms-1"><?= $total ?></span>
            </a>
            <a href="monthly_dues.php?filter_type=due&status=<?= $filterStatus ?>&search=<?= urlencode($searchTerm) ?>" 
               class="btn btn-outline-danger <?= $filterType === 'due' ? 'active' : '' ?>">
                <i data-feather="alert-triangle" style="width:14px;height:14px"></i> বাকি
                <span class="badge bg-danger ms-1"><?= $unpaid + $partial ?></span>
            </a>
            <a href="monthly_dues.php?filter_type=paid&status=<?= $filterStatus ?>&search=<?= urlencode($searchTerm) ?>" 
               class="btn btn-outline-success <?= $filterType === 'paid' ? 'active' : '' ?>">
                <i data-feather="check-circle" style="width:14px;height:14px"></i> পরিশোধিত
                <span class="badge bg-success ms-1"><?= $paid ?></span>
            </a>
        </div>  
    </div>
    
    <!-- Dues Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="card-title">
                <i data-feather="list" style="width:16px;height:16px"></i>
                <strong>
                    <?php if ($isButtonFilter): ?>
                        সকল মাসের সঞ্চয় তালিকা
                    <?php else: ?>
                        <?= $displayMonthName ?> মাসের সঞ্চয় তালিকা
                    <?php endif; ?>
                </strong>
                <?php if ($filterType === 'due'): ?>
                <span class="badge bg-danger ms-2">শুধু বাকি</span>
                <?php elseif ($filterType === 'paid'): ?>
                <span class="badge bg-success ms-2">শুধু পরিশোধিত</span>
                <?php endif; ?>
                <?php if ($isButtonFilter): ?>
                <span class="badge bg-warning ms-2">সকল মাস</span>
                <?php endif; ?>
            </span>
            <span>
                <span class="badge bg-secondary"><?= count($duesList) ?> জন</span>
                <?php if ($unpaid > 0): ?>
                <span class="badge bg-danger"><?= $unpaid ?> টি বাকি</span>
                <?php endif; ?>
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>সদস্য</th>
                        <th>আইডি</th>
                        <th>মাস</th>
                        <th>মাসিক পরিমাণ</th>
                        <th>পরিমাণ</th>
                        <th>পরিশোধ</th>
                        <th>বাকি</th>
                        <th>অবস্থা</th>
                        <th>রসিদ</th>
                        <th>কার্যক্রম</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($duesList)): ?>
                    <tr>
                        <td colspan="11" class="text-center text-muted py-4">
                            <i data-feather="calendar" style="width:32px;height:32px;display:block;margin:0 auto 10px"></i>
                            <?php if ($isButtonFilter): ?>
                                কোনো সঞ্চয় পাওয়া যায়নি।
                            <?php else: ?>
                                <strong><?= $displayMonthName ?></strong> মাসের কোনো সঞ্চয় নেই।
                            <?php endif; ?>
                            <br>
                            <button class="btn btn-primary btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#generateModal">
                                <i data-feather="plus" style="width:14px;height:14px"></i> সঞ্চয় তৈরি করুন
                            </button>
                        </td>
                    </tr>
                    <?php else: 
                        $counter = 1; 
                        foreach ($duesList as $due): 
                    ?>
                    <tr>
                        <td><?= $counter++ ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <?php if ($due['avatar_type'] === 'image'): ?>
                                    <!-- Show profile image -->
                                    <img src="<?= htmlspecialchars($due['avatar']) ?>?t=<?= time() ?>" 
                                         alt="<?= htmlspecialchars($due['mname'] ?? '') ?>" 
                                         class="member-avatar-img"
                                         onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="member-avatar" style="background:<?= $due['avatar_color'] ?? '#1B4F72' ?>; display:none;">
                                        <?= $due['avatar'] ?? '?' ?>
                                    </div>
                                <?php else: ?>
                                    <!-- Show text avatar -->
                                    <div class="member-avatar" style="background:<?= $due['avatar_color'] ?? '#1B4F72' ?>;">
                                        <?= htmlspecialchars($due['avatar'] ?? '?') ?>
                                    </div>
                                <?php endif; ?>
                                <span style="font-weight:500"><?= htmlspecialchars($due['mname'] ?? 'অজানা') ?></span>
                            </div>
                        </td>
                        <td><code><?= htmlspecialchars($due['mid'] ?? 'N/A') ?></code></td>
                        <td>
                            <?php 
                            // Determine month badge color based on status
                            if ($due['status'] === 'paid'): 
                            ?>
                                <span class="badge bg-success">
                                    <?= htmlspecialchars($due['month_name'] ?? '') ?> <?= $due['month_year'] ?>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-danger">
                                    <?= htmlspecialchars($due['month_name'] ?? '') ?> <?= $due['month_year'] ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($due['monthly_deposit'])): ?>
                                <?= number_format($due['monthly_deposit'], 2) ?> ৳
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= number_format($due['amount'], 2) ?> ৳</td>
                        <td>
                            <?php 
                            // Paid amount - green color and bold when fully paid
                            if ($due['paid_amount'] > 0): 
                                if ($due['status'] === 'paid'):
                            ?>
                                <span style="color: #28a745; font-weight: 700;">
                                    <?= number_format($due['paid_amount'], 2) ?> ৳
                                </span>
                            <?php else: ?>
                                <?= number_format($due['paid_amount'], 2) ?> ৳
                            <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="<?= ($due['amount'] - $due['paid_amount']) > 0 ? 'text-danger' : 'text-success' ?>" style="font-weight:600;">
                            <?= number_format($due['amount'] - $due['paid_amount'], 2) ?> ৳
                        </td>
                        <td>
                            <?php if ($due['status'] === 'paid'): ?>
                                <span class="badge bg-success">✅ পরিশোধিত</span>
                            <?php elseif ($due['status'] === 'partial'): ?>
                                <span class="badge bg-warning text-dark">⏳ আংশিক</span>
                            <?php else: ?>
                                <span class="badge bg-danger">⛔ বাকি</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($due['receipt_no']): ?>
                                <small class="text-muted"><?= htmlspecialchars($due['receipt_no']) ?></small>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <?php if ($due['status'] !== 'paid'): ?>
                                <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#payModal"
                                    onclick="setPayData(<?= $due['id'] ?>, '<?= addslashes($due['mname'] ?? '') ?>', <?= $due['amount'] ?>, <?= $due['paid_amount'] ?>)">
                                    <i data-feather="dollar-sign" style="width:13px;height:13px"></i>
                                </button>
                                <?php endif; ?>
                                
                                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal"
                                    onclick="setEditData(<?= $due['id'] ?>, '<?= addslashes($due['mname'] ?? '') ?>', <?= $due['amount'] ?>, '<?= addslashes($due['note'] ?? '') ?>')">
                                    <i data-feather="edit-2" style="width:13px;height:13px"></i>
                                </button>
                                
                                <button class="btn btn-sm btn-info" onclick="printReceipt('<?= addslashes($due['receipt_no'] ?? '') ?>', '<?= addslashes($due['mname'] ?? '') ?>', <?= $due['amount'] ?>, <?= $due['paid_amount'] ?>, '<?= addslashes($due['status'] ?? '') ?>')">
                                    <i data-feather="printer" style="width:13px;height:13px"></i>
                                </button>
                                
                                <a href="monthly_dues.php?delete=<?= $due['id'] ?>&csrf_token=<?= generateCSRF() ?>" 
                                   class="btn btn-sm btn-outline-danger" 
                                   onclick="return confirm('ডিলিট করবেন?')">
                                    <i data-feather="trash-2" style="width:13px;height:13px"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php 
                        endforeach; 
                    endif; 
                    ?>
                </tbody>
                <?php if (!empty($duesList)): ?>
                <tfoot>
                    <tr style="font-weight:bold;background:#f8f9fa">
                        <td colspan="5" class="text-end">সর্বমোট:</td>
                        <td><?= number_format($totalAmount, 2) ?> ৳</td>
                        <td><?= number_format($totalPaid, 2) ?> ৳</td>
                        <td class="text-danger"><?= number_format($totalDue, 2) ?> ৳</td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<!-- MODAL: Generate Dues -->
<div class="modal fade" id="generateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">সঞ্চয় তৈরি</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="generate">
                    <div class="mb-3">
                        <label>বছর</label>
                        <input type="number" name="year" class="form-control" value="<?= date('Y') ?>" min="2020" max="2050">
                    </div>
                    <div class="mb-3">
                        <label>মাস</label>
                        <select name="month" class="form-select">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $m == date('n') ? 'selected' : '' ?>><?= getBengaliMonthName($m) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>পরিমাণ (টাকা)</label>
                        <input type="number" name="amount" class="form-control" value="<?= $monthlyDueAmount ?>" step="0.01" required>
                        <small class="text-muted">প্রতিটি সদস্যের জন্য ডিফল্ট পরিমাণ (সদস্যের monthly_deposit থাকলে সেটি ব্যবহার হবে)</small>
                    </div>
                    <div class="alert alert-info small">
                        <i data-feather="info" style="width:14px;height:14px"></i>
                        নতুন সঞ্চয় তৈরি হলে সব সক্রিয় সদস্যের জন্য তৈরি হবে। আগের ডিপোজিট চেক করে স্বয়ংক্রিয়ভাবে পরিশোধিত করা হবে।
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
                    <button type="submit" class="btn btn-primary">তৈরি করুন</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: Sync with Deposits -->
<div class="modal fade" id="syncModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">ডিপোজিটের সাথে সিঙ্ক করুন</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="sync_deposits">
                    <div class="mb-3">
                        <label>বছর</label>
                        <input type="number" name="sync_year" class="form-control" value="<?= $filterYear ?>" min="2020" max="2050">
                    </div>
                    <div class="mb-3">
                        <label>মাস</label>
                        <select name="sync_month" class="form-select">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $m == $filterMonth ? 'selected' : '' ?>><?= getBengaliMonthName($m) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="alert alert-warning small">
                        <i data-feather="alert-triangle" style="width:14px;height:14px"></i>
                        এটি সব সঞ্চয় চেক করে ডিপোজিট থাকলে পরিশোধিত, না থাকলে বাকি করবে। অনুপস্থিত সদস্যদের জন্য সঞ্চয় তৈরি করবে।
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
                    <button type="submit" class="btn btn-success">সিঙ্ক করুন</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: Pay Dues -->
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
                    <input type="hidden" name="action" value="collect">
                    <input type="hidden" name="due_id" id="payDueId">
                    
                    <div class="mb-2">
                        <label>সদস্য</label>
                        <p class="fw-bold" id="payMemberName">—</p>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label>মোট</label>
                            <p class="fw-bold" id="payTotal">—</p>
                        </div>
                        <div class="col-6">
                            <label>পরিশোধিত</label>
                            <p class="fw-bold" id="payPaid">—</p>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>পরিমাণ</label>
                        <input type="number" step="0.01" name="paid_amount" id="payAmount" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>তারিখ</label>
                        <input type="date" name="paid_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="mb-3">
                        <label>নোট</label>
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

<!-- MODAL: Edit Dues -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">সঞ্চয় এডিট</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="due_id" id="editDueId">
                    
                    <div class="mb-2">
                        <label>সদস্য</label>
                        <p class="fw-bold" id="editMemberName">—</p>
                    </div>
                    <div class="mb-3">
                        <label>পরিমাণ (টাকা)</label>
                        <input type="number" step="0.01" name="amount" id="editAmount" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>নোট</label>
                        <input type="text" name="note" id="editNote" class="form-control" placeholder="নোট">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
                    <button type="submit" class="btn btn-warning">আপডেট</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- PRINT RECEIPT POPUP MODAL -->
<div class="modal fade" id="receiptModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">সঞ্চয় রসিদ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="receiptContent">
                <!-- Receipt content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary btn-sm" onclick="printReceiptContent()">
                    <i data-feather="printer" style="width:14px;height:14px"></i> প্রিন্ট
                </button>
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">বন্ধ</button>
            </div>
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

/* Member Avatar */
.member-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 600;
    flex-shrink: 0;
    text-transform: uppercase;
}

.member-avatar-img {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
    border: 2px solid #f0f0f0;
}

.receipt-container {
    font-family: 'Courier New', monospace;
    font-size: 14px;
    padding: 20px 15px;
    max-width: 350px;
    margin: 0 auto;
    line-height: 1.6;
}
.receipt-container .receipt-header {
    text-align: center;
    border-bottom: 2px dashed #333;
    padding-bottom: 12px;
    margin-bottom: 15px;
}
.receipt-container .receipt-header h4 {
    margin: 0;
    font-weight: bold;
    font-size: 18px;
    font-family: 'Courier New', monospace;
}
.receipt-container .receipt-header small {
    display: block;
    color: #333;
    font-size: 13px;
    margin-top: 4px;
}
.receipt-container .receipt-body {
    padding: 5px 0;
}
.receipt-container .receipt-row {
    display: flex;
    justify-content: space-between;
    padding: 6px 0;
    border-bottom: 1px dotted #ddd;
}
.receipt-container .receipt-row .label {
    font-weight: bold;
    color: #444;
}
.receipt-container .receipt-row .value {
    text-align: right;
}
.receipt-container .receipt-footer {
    text-align: center;
    border-top: 2px dashed #333;
    padding-top: 12px;
    margin-top: 15px;
    font-size: 14px;
    color: #333;
}

@media print {
    .receipt-container {
        font-size: 12px;
        padding: 10px;
    }
    .receipt-container .receipt-header h4 {
        font-size: 16px;
    }
    .receipt-container .receipt-row {
        padding: 4px 0;
    }
    .btn-group, .btn {
        display: none !important;
    }
    .stat-card {
        box-shadow: none !important;
        border: 1px solid #ddd;
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
    .member-avatar {
        width: 28px;
        height: 28px;
        font-size: 11px;
    }
    .member-avatar-img {
        width: 28px;
        height: 28px;
    }
}
</style>

<script>
function setPayData(id, name, total, paid) {
    document.getElementById('payDueId').value = id;
    document.getElementById('payMemberName').textContent = name || 'অজানা';
    document.getElementById('payTotal').textContent = '৳' + parseFloat(total).toFixed(2);
    document.getElementById('payPaid').textContent = '৳' + parseFloat(paid).toFixed(2);
    var due = parseFloat(total - paid).toFixed(2);
    document.getElementById('payAmount').value = due;
    document.getElementById('payAmount').max = due;
}

function setEditData(id, name, amount, note) {
    document.getElementById('editDueId').value = id;
    document.getElementById('editMemberName').textContent = name || 'অজানা';
    document.getElementById('editAmount').value = parseFloat(amount).toFixed(2);
    document.getElementById('editNote').value = note || '';
}

function printReceipt(receiptNo, memberName, amount, paid, status) {
    const due = parseFloat(amount - paid).toFixed(2);
    const statusText = status === 'paid' ? 'পরিশোধিত' : status === 'partial' ? 'আংশিক' : 'বাকি';
    
    const receiptHTML = `
        <div class="receipt-container">
            <div class="receipt-header">
                <h4>সঞ্চয় রসিদ</h4>
                <small>রসিদ নং: ${receiptNo || 'N/A'}</small>
            </div>
            <div class="receipt-body">
                <div class="receipt-row">
                    <span class="label">সদস্য</span>
                    <span class="value">${memberName || 'অজানা'}</span>
                </div>
                <div class="receipt-row">
                    <span class="label">পরিমাণ</span>
                    <span class="value">৳ ${parseFloat(amount).toFixed(2)}</span>
                </div>
                <div class="receipt-row">
                    <span class="label">পরিশোধ</span>
                    <span class="value">৳ ${parseFloat(paid).toFixed(2)}</span>
                </div>
                <div class="receipt-row">
                    <span class="label">বাকি</span>
                    <span class="value">৳ ${due}</span>
                </div>
                <div class="receipt-row">
                    <span class="label">অবস্থা</span>
                    <span class="value">${statusText}</span>
                </div>
            </div>
            <div class="receipt-footer">
                ধন্যবাদ
            </div>
        </div>
    `;
    
    document.getElementById('receiptContent').innerHTML = receiptHTML;
    var myModal = new bootstrap.Modal(document.getElementById('receiptModal'));
    myModal.show();
}

function printReceiptContent() {
    var content = document.getElementById('receiptContent').innerHTML;
    var win = window.open('', '', 'width=400,height=550');
    win.document.write(`
        <html>
            <head>
                <title>সঞ্চয় রসিদ</title>
                <style>
                    body {
                        font-family: 'Courier New', monospace;
                        margin: 0;
                        padding: 30px 20px;
                        background: #fff;
                    }
                    .receipt-container {
                        max-width: 350px;
                        margin: 0 auto;
                        font-size: 14px;
                        line-height: 1.6;
                    }
                    .receipt-container .receipt-header {
                        text-align: center;
                        border-bottom: 2px dashed #333;
                        padding-bottom: 12px;
                        margin-bottom: 15px;
                    }
                    .receipt-container .receipt-header h4 {
                        margin: 0;
                        font-weight: bold;
                        font-size: 18px;
                        font-family: 'Courier New', monospace;
                    }
                    .receipt-container .receipt-header small {
                        display: block;
                        color: #333;
                        font-size: 13px;
                        margin-top: 4px;
                    }
                    .receipt-container .receipt-body {
                        padding: 5px 0;
                    }
                    .receipt-container .receipt-row {
                        display: flex;
                        justify-content: space-between;
                        padding: 6px 0;
                        border-bottom: 1px dotted #ddd;
                    }
                    .receipt-container .receipt-row .label {
                        font-weight: bold;
                        color: #444;
                    }
                    .receipt-container .receipt-row .value {
                        text-align: right;
                    }
                    .receipt-container .receipt-footer {
                        text-align: center;
                        border-top: 2px dashed #333;
                        padding-top: 12px;
                        margin-top: 15px;
                        font-size: 14px;
                        color: #333;
                    }
                    @media print {
                        body { padding: 20px; }
                        .receipt-container { font-size: 12px; }
                        .receipt-container .receipt-header h4 { font-size: 16px; }
                        .receipt-container .receipt-row { padding: 4px 0; }
                    }
                </style>
            </head>
            <body>
                ${content}
                <script>
                    window.onload = function() { 
                        window.print(); 
                        setTimeout(function() { window.close(); }, 1500);
                    }
                <\/script>
            </body>
        </html>
    `);
    win.document.close();
}
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>