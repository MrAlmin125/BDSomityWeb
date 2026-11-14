<?php
// =====================================================
// admin/deposit_members.php - Member Deposit Summary & History (WITH PHOTOS & FINES)
// =====================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
requireAdmin();

$conn = conn();

// ============================================
// BENGALI DATE FORMATTING FUNCTIONS
// ============================================
function formatBanglaDate($dateStr) {
    if (empty($dateStr)) return '-';
    
    $timestamp = strtotime($dateStr);
    if ($timestamp === false || $timestamp <= 0) return '-';
    
    $months = [
        1 => 'জানুয়ারি', 2 => 'ফেব্রুয়ারি', 3 => 'মার্চ', 
        4 => 'এপ্রিল', 5 => 'মে', 6 => 'জুন',
        7 => 'জুলাই', 8 => 'আগস্ট', 9 => 'সেপ্টেম্বর', 
        10 => 'অক্টোবর', 11 => 'নভেম্বর', 12 => 'ডিসেম্বর'
    ];
    
    $day = date('d', $timestamp);
    $month = (int)date('m', $timestamp);
    $year = date('Y', $timestamp);
    
    // Convert digits to Bengali
    $banglaDigits = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];
    $dayBn = str_replace(range(0, 9), $banglaDigits, $day);
    $yearBn = str_replace(range(0, 9), $banglaDigits, $year);
    
    return $dayBn . ' ' . $months[$month] . ' ' . $yearBn;
}

function formatBanglaMonthYear($dateStr) {
    if (empty($dateStr)) return '-';
    
    $timestamp = strtotime($dateStr);
    if ($timestamp === false || $timestamp <= 0) return '-';
    
    $months = [
        1 => 'জানুয়ারি', 2 => 'ফেব্রুয়ারি', 3 => 'মার্চ', 
        4 => 'এপ্রিল', 5 => 'মে', 6 => 'জুন',
        7 => 'জুলাই', 8 => 'আগস্ট', 9 => 'সেপ্টেম্বর', 
        10 => 'অক্টোবর', 11 => 'নভেম্বর', 12 => 'ডিসেম্বর'
    ];
    
    $month = (int)date('m', $timestamp);
    $year = date('Y', $timestamp);
    
    // Convert year to Bengali
    $banglaDigits = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];
    $yearBn = str_replace(range(0, 9), $banglaDigits, $year);
    
    return $months[$month] . ' ' . $yearBn;
}

// ============================================
// AJAX HANDLER - GET MEMBER TRANSACTIONS (WITH FINES)
// ============================================
if (isset($_GET['ajax']) && $_GET['ajax'] === 'transactions' && isset($_GET['member_id'])) {
    header('Content-Type: application/json');
    $memId = (int)$_GET['member_id'];
    $type = $_GET['type'] ?? 'all';
    
    try {
        $member = queryOne("SELECT id, name, member_id FROM members WHERE id = ?", 'i', [$memId]);
        if (!$member) {
            echo json_encode(['success' => false, 'message' => 'সদস্য পাওয়া যায়নি']);
            exit;
        }
        
        $transactions = [];
        
        // Get deposits
        if ($type === 'all' || $type === 'deposit') {
            $depositSql = "SELECT 'deposit' as transaction_type, id, amount, deposit_date as transaction_date, 
                                  receipt_no, note, created_at, deposit_type 
                           FROM deposits WHERE member_id = ? ORDER BY deposit_date DESC LIMIT 100";
            $depositStmt = $conn->prepare($depositSql);
            if ($depositStmt) {
                $depositStmt->bind_param('i', $memId);
                $depositStmt->execute();
                $result = $depositStmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $row['fine_status'] = null;
                    $row['fine_remaining'] = null;
                    $row['fine_month'] = null;
                    $row['payment_method'] = null;
                    $transactions[] = $row;
                }
                $depositStmt->close();
            }
        }
        
        // Get withdrawals
        if ($type === 'all' || $type === 'withdrawal') {
            $withdrawalSql = "SELECT 'withdrawal' as transaction_type, id, amount, withdrawal_date as transaction_date, 
                                    receipt_no, note, created_at, withdrawal_type, status 
                              FROM withdrawals WHERE member_id = ? AND status = 'completed' ORDER BY withdrawal_date DESC LIMIT 100";
            $withdrawalStmt = $conn->prepare($withdrawalSql);
            if ($withdrawalStmt) {
                $withdrawalStmt->bind_param('i', $memId);
                $withdrawalStmt->execute();
                $result = $withdrawalStmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $row['fine_status'] = null;
                    $row['fine_remaining'] = null;
                    $row['fine_month'] = null;
                    $row['payment_method'] = null;
                    $transactions[] = $row;
                }
                $withdrawalStmt->close();
            }
        }
        
        // ============================================
        // GET FINES - Show as POSITIVE with Bengali date from due_date
        // ============================================
        if ($type === 'all' || $type === 'fine') {
            $checkFines = $conn->query("SHOW TABLES LIKE 'fines'");
            if ($checkFines->num_rows > 0) {
                $columns = $conn->query("SHOW COLUMNS FROM fines");
                $fineColumns = [];
                while ($col = $columns->fetch_assoc()) {
                    $fineColumns[] = $col['Field'];
                }
                
                // Get paid or approved fines - show as POSITIVE
                $fineSelect = "SELECT 'fine' as transaction_type, id, fine_amount as amount, 
                               due_date as transaction_date, 
                               due_date as fine_due_date,
                               '' as receipt_no, notes as note, created_at, '' as payment_method, 
                               status as fine_status, month_year as fine_month";
                
                if (in_array('paid_amount', $fineColumns) && in_array('waived_amount', $fineColumns)) {
                    $fineSelect .= ", (fine_amount - paid_amount - waived_amount) as fine_remaining";
                } else {
                    $fineSelect .= ", 0 as fine_remaining";
                }
                
                // Only get paid or partially_paid or waived fines
                $fineSql = $fineSelect . " FROM fines WHERE member_id = ? AND status IN ('paid', 'partially_paid', 'waived') ORDER BY due_date DESC LIMIT 100";
                $fineStmt = $conn->prepare($fineSql);
                if ($fineStmt) {
                    $fineStmt->bind_param('i', $memId);
                    $fineStmt->execute();
                    $result = $fineStmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        // Keep fine amount as POSITIVE (not negative)
                        $row['amount'] = abs(floatval($row['amount']));
                        
                        // Format due_date in Bengali
                        $dueDate = $row['fine_due_date'] ?? '';
                        
                        // Full Bengali date: ১৫ ডিসেম্বর ২০২৫
                        $row['fine_date_bn'] = formatBanglaDate($dueDate);
                        
                        // Month Year in Bengali: ডিসেম্বর ২০২৫
                        $row['fine_month_bn'] = formatBanglaMonthYear($dueDate);
                        
                        $transactions[] = $row;
                    }
                    $fineStmt->close();
                }
            }
        }
        
        // Sort by transaction date
        usort($transactions, function($a, $b) {
            return strtotime($b['transaction_date']) - strtotime($a['transaction_date']);
        });
        
        // Get totals
        $totalDeposit = 0;
        $totalWithdrawal = 0;
        $totalFine = 0;
        foreach ($transactions as $tx) {
            $amount = floatval($tx['amount']);
            if ($tx['transaction_type'] === 'deposit') {
                $totalDeposit += $amount;
            } else if ($tx['transaction_type'] === 'withdrawal') {
                $totalWithdrawal += $amount;
            } else if ($tx['transaction_type'] === 'fine') {
                $totalFine += abs($amount);
            }
        }
        
        // Get pending fines (not yet paid) for display
        $pendingFines = [];
        $pendingFinesSql = "SELECT COUNT(*) as pending_count, COALESCE(SUM(fine_amount - paid_amount - waived_amount), 0) as pending_amount 
                            FROM fines WHERE member_id = ? AND status IN ('pending', 'partially_paid')";
        $pendingStmt = $conn->prepare($pendingFinesSql);
        if ($pendingStmt) {
            $pendingStmt->bind_param('i', $memId);
            $pendingStmt->execute();
            $pendingResult = $pendingStmt->get_result();
            $pendingData = $pendingResult->fetch_assoc();
            $pendingStmt->close();
        } else {
            $pendingData = ['pending_count' => 0, 'pending_amount' => 0];
        }
        
        // Balance = Deposit - Withdrawal + Fine (since fine is now positive/added)
        echo json_encode([
            'success' => true,
            'member' => $member,
            'transactions' => array_slice($transactions, 0, 50),
            'total_deposit' => $totalDeposit,
            'total_withdrawal' => $totalWithdrawal,
            'total_fine' => $totalFine,
            'pending_fine_count' => $pendingData['pending_count'] ?? 0,
            'pending_fine_amount' => $pendingData['pending_amount'] ?? 0,
            'balance' => $totalDeposit - $totalWithdrawal + $totalFine
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false, 
            'message' => 'ত্রুটি: ' . $e->getMessage()
        ]);
    }
    exit;
}

// ============================================
// GET FILTERS
// ============================================
$search = trim($_GET['s'] ?? '');
$status = trim($_GET['status'] ?? 'all');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// ============================================
// GET MEMBER WITH DEPOSIT/WITHDRAWAL/FINE SUMMARIES
// ============================================
$where = "1=1";
$params = [];
$types = "";

// Only show members who have at least one deposit
$where .= " AND EXISTS (SELECT 1 FROM deposits WHERE member_id = m.id)";

if (!empty($search)) {
    $where .= " AND (m.name LIKE ? OR m.member_id LIKE ? OR m.phone LIKE ?)";
    $like = "%$search%";
    $types .= "sss";
    $params = array_merge($params, [$like, $like, $like]);
}

if (!empty($status) && $status !== 'all') {
    $where .= " AND m.status = ?";
    $types .= "s";
    $params[] = $status;
}

// Get total count for pagination
$countSql = "SELECT COUNT(*) as total FROM members m WHERE $where";
$countResult = queryOne($countSql, $types, $params);
$totalRecords = $countResult['total'] ?? 0;
$totalPages = ceil($totalRecords / $perPage);
$offset = ($page - 1) * $perPage;

// Main query with summaries (including fines)
$sql = "SELECT 
            m.id,
            m.member_id,
            m.name,
            m.phone,
            m.email,
            m.address,
            m.photo,
            m.status,
            m.monthly_deposit,
            COALESCE((
                SELECT SUM(amount) FROM deposits WHERE member_id = m.id
            ), 0) as total_deposit,
            COALESCE((
                SELECT SUM(amount) FROM withdrawals WHERE member_id = m.id AND status = 'completed'
            ), 0) as total_withdrawal,
            COALESCE((
                SELECT SUM(fine_amount - paid_amount - waived_amount) FROM fines WHERE member_id = m.id AND status IN ('pending', 'partially_paid')
            ), 0) as total_fine_pending,
            COALESCE((
                SELECT SUM(fine_amount) FROM fines WHERE member_id = m.id AND status IN ('paid', 'partially_paid', 'waived')
            ), 0) as total_fine_paid,
            COALESCE((
                SELECT SUM(paid_amount) FROM fines WHERE member_id = m.id AND status IN ('paid', 'partially_paid')
            ), 0) as total_fine_paid_amount,
            COALESCE((
                SELECT SUM(waived_amount) FROM fines WHERE member_id = m.id AND status = 'waived'
            ), 0) as total_fine_waived,
            -- Balance = deposit - withdrawal + paid_fine (fines added as positive)
            COALESCE((
                SELECT SUM(amount) FROM deposits WHERE member_id = m.id
            ), 0) - COALESCE((
                SELECT SUM(amount) FROM withdrawals WHERE member_id = m.id AND status = 'completed'
            ), 0) + COALESCE((
                SELECT SUM(fine_amount) FROM fines WHERE member_id = m.id AND status IN ('paid', 'partially_paid', 'waived')
            ), 0) as current_balance,
            (
                SELECT deposit_date FROM deposits WHERE member_id = m.id ORDER BY deposit_date DESC LIMIT 1
            ) as last_deposit_date,
            (
                SELECT amount FROM deposits WHERE member_id = m.id ORDER BY deposit_date DESC LIMIT 1
            ) as last_deposit_amount,
            (
                SELECT withdrawal_date FROM withdrawals WHERE member_id = m.id AND status = 'completed' ORDER BY withdrawal_date DESC LIMIT 1
            ) as last_withdrawal_date,
            (
                SELECT amount FROM withdrawals WHERE member_id = m.id AND status = 'completed' ORDER BY withdrawal_date DESC LIMIT 1
            ) as last_withdrawal_amount,
            (
                SELECT COUNT(*) FROM deposits WHERE member_id = m.id
            ) as total_deposits_count,
            (
                SELECT COUNT(*) FROM withdrawals WHERE member_id = m.id AND status = 'completed'
            ) as total_withdrawals_count,
            (
                SELECT COUNT(*) FROM fines WHERE member_id = m.id AND status IN ('pending', 'partially_paid')
            ) as pending_fines_count
        FROM members m
        WHERE $where
        ORDER BY m.name ASC
        LIMIT ? OFFSET ?";

$types .= "ii";
$params[] = $perPage;
$params[] = $offset;

$members = queryAll($sql, $types, $params);

// ============================================
// GET AVATAR / PHOTO FUNCTION
// ============================================
function getMemberPhoto($member) {
    if (!empty($member['photo']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $member['photo'])) {
        return $member['photo'];
    }
    return generateAvatar($member['name'], 48);
}

function getAvatarColor($name) {
    $colors = ['#1B4F72', '#2E86C1', '#117A65', '#C0392B', '#8E44AD', '#D35400', '#27AE60', '#2980B9', '#E67E22', '#16A085'];
    $hash = 0;
    for ($i = 0; $i < strlen($name); $i++) {
        $hash = ($hash * 31 + ord($name[$i])) & 0xFFFFFFFF;
    }
    return $colors[abs($hash) % count($colors)];
}

function generateAvatar($name, $size = 48) {
    $firstLetter = mb_substr($name, 0, 1, 'UTF-8');
    $color = getAvatarColor($name);
    
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 ' . $size . ' ' . $size . '">';
    $svg .= '<rect width="' . $size . '" height="' . $size . '" rx="' . ($size/2) . '" fill="' . $color . '"/>';
    $fontSize = $size * 0.45;
    $yPos = $size * 0.66;
    $svg .= '<text x="' . ($size/2) . '" y="' . $yPos . '" font-family="Arial, sans-serif" font-size="' . $fontSize . '" font-weight="bold" fill="white" text-anchor="middle" dominant-baseline="central">' . htmlspecialchars($firstLetter) . '</text>';
    $svg .= '</svg>';
    
    return 'data:image/svg+xml;charset=utf-8,' . rawurlencode($svg);
}

$pageTitle = 'সদস্য সঞ্চয় তালিকা';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ============================================
     CSS STYLES
     ============================================ -->
<style>
.member-card {
    transition: all 0.3s ease;
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}
.member-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
}
.member-card .card-header {
    background: white;
    border-bottom: 1px solid #f0f0f0;
    padding: 12px 18px;
    border-radius: 12px 12px 0 0 !important;
}
.member-card .card-body {
    padding: 14px 16px;
}
.member-photo {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
    border: 2px solid #f0f0f0;
}
.avatar-circle {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 20px;
    flex-shrink: 0;
    border: 2px solid #f0f0f0;
}
.stat-box {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 8px 12px;
    text-align: center;
}
.stat-box .stat-label {
    font-size: 11px;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.stat-box .stat-value {
    font-size: 16px;
    font-weight: 600;
    margin-top: 2px;
}
.stat-box .stat-value.positive { color: #27AE60; }
.stat-box .stat-value.negative { color: #E74C3C; }
.stat-box .stat-value.primary { color: #2E86C1; }
.stat-box .stat-value.warning { color: #F39C12; }

.clickable-row {
    cursor: pointer;
    transition: background 0.2s;
}
.clickable-row:hover {
    background: #f0f7ff !important;
}

/* Transaction Modal */
.transaction-item {
    padding: 10px 14px;
    border-bottom: 1px solid #f0f0f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.transaction-item:last-child {
    border-bottom: none;
}
.transaction-item .tx-type {
    font-weight: 600;
    font-size: 13px;
}
.transaction-item .tx-type.deposit { color: #27AE60; }
.transaction-item .tx-type.withdrawal { color: #E74C3C; }
.transaction-item .tx-type.fine { color: #F39C12; }
.transaction-item .tx-amount {
    font-weight: 600;
    font-size: 14px;
}
.transaction-item .tx-amount.deposit { color: #27AE60; }
.transaction-item .tx-amount.withdrawal { color: #E74C3C; }
.transaction-item .tx-amount.fine { color: #F39C12; }
.transaction-item .tx-date {
    font-size: 12px;
    color: #6c757d;
}
.transaction-item .tx-meta {
    font-size: 11px;
    color: #6c757d;
}
.transaction-item .tx-status {
    font-size: 10px;
    padding: 1px 8px;
    border-radius: 10px;
}
.tx-status.pending { background: #fef9e7; color: #f39c12; }
.tx-status.paid { background: #d5f5e3; color: #27ae60; }
.tx-status.waived { background: #e8daef; color: #8e44ad; }
.tx-status.partially_paid { background: #fdebd0; color: #e67e22; }

/* Pending fine amount in RED */
#pendingFineAmountModal {
    font-weight: 700;
}
.tx-status.paid { 
    background: none; 
    color: #27ae60; 
}
.tx-status.partially_paid { 
    background: #fdebd0; 
    color: #e67e22; 
}
.tx-status.pending { 
    background: #fadbd8; 
    color: #c0392b; 
}
.tx-status.waived { 
    background: #e8daef; 
    color: #8e44ad; 
}

.search-box {
    position: relative;
}
.search-box .search-icon {
    position: absolute;
    left: 8px;
    top: 50%;
    transform: translateY(-50%);
    width: 14px;
    height: 14px;
    color: #999;
}
.search-box input {
    padding-left: 28px;
}

/* Responsive */
@media (max-width: 768px) {
    .stat-box .stat-value {
        font-size: 14px;
    }
    .member-card .card-header {
        padding: 10px 14px;
    }
    .member-card .card-body {
        padding: 12px 14px;
    }
    .member-photo, .avatar-circle {
        width: 40px;
        height: 40px;
        font-size: 16px;
    }
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.empty-state {
    padding: 60px 20px;
    text-align: center;
}
.empty-state i {
    font-size: 48px;
    color: #ddd;
    margin-bottom: 15px;
}
.empty-state h5 {
    color: #6c757d;
    margin-bottom: 10px;
}
.empty-state p {
    color: #adb5bd;
}
</style>

<!-- ============================================
     FLASH MESSAGES
     ============================================ -->
<?php $flash = getFlash(); if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show m-3" role="alert">
    <i data-feather="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-circle' ?>" 
       style="width:16px;height:16px;vertical-align:middle"></i>
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- ============================================
     MAIN CONTENT
     ============================================ -->
<div class="container-fluid px-4">
    <div class="row">
        <div class="col-12">
            <div class="card member-card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span class="card-title">
                            <i data-feather="users" style="width:18px;height:18px"></i>
                            সদস্য সঞ্চয় তালিকা
                            <span class="badge bg-secondary ms-2"><?= $totalRecords ?> জন</span>
                            <small class="text-muted ms-2" style="font-size:12px;font-weight:normal;">
                                (শুধুমাত্র যাদের জমা আছে)
                            </small>
                        </span>
                        <div>
                            <a href="deposits.php" class="btn btn-primary btn-sm">
                                <i data-feather="plus-circle" style="width:14px;height:14px"></i>
                                নতুন জমা
                            </a>
                            <a href="withdrawals.php" class="btn btn-warning btn-sm">
                                <i data-feather="minus-circle" style="width:14px;height:14px"></i>
                                নতুন উত্তোলন
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="card-body">
                    <!-- Filters -->
                    <form method="GET" class="row g-2 mb-3" id="filterForm">
                        <div class="col-12 col-md-4">
                            <div class="search-box">
                                <i data-feather="search" class="search-icon"></i>
                                <input type="text" name="s" class="form-control form-control-sm" 
                                       placeholder="নাম, আইডি বা ফোন দিয়ে খুঁজুন..." 
                                       value="<?= htmlspecialchars($search) ?>">
                            </div>
                        </div>
                        <div class="col-6 col-md-2">
                            <select name="status" class="form-select form-select-sm">
                                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>সব</option>
                                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>সক্রিয়</option>
                                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>নিষ্ক্রিয়</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100">
                                <i data-feather="search" style="width:14px;height:14px"></i> খুঁজুন
                            </button>
                        </div>
                        <?php if ($search || $status !== 'all'): ?>
                        <div class="col-12 col-md-2">
                            <a href="deposit_members.php" class="btn btn-outline-secondary btn-sm w-100">
                                <i data-feather="x" style="width:14px;height:14px"></i> রিসেট
                            </a>
                        </div>
                        <?php endif; ?>
                    </form>

                    <!-- Member Grid -->
                    <div class="row g-3">
                        <?php if (empty($members)): ?>
                        <div class="col-12">
                            <div class="empty-state">
                                <i data-feather="users"></i>
                                <h5>কোনো সদস্য পাওয়া যায়নি</h5>
                                <p>বর্তমানে কোনো সদস্যের জমা নেই।</p>
                            </div>
                        </div>
                        <?php else: ?>
                        <?php foreach ($members as $m): 
                            $photoUrl = '';
                            if (!empty($m['photo']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $m['photo'])) {
                                $photoUrl = $m['photo'];
                            }
                        ?>
                        <div class="col-12 col-md-6 col-xl-4">
                            <div class="card member-card clickable-row" data-id="<?= $m['id'] ?>" style="cursor:pointer;">
                                <div class="card-body">
                                    <div class="d-flex align-items-start gap-3">
                                        <?php if (!empty($photoUrl)): ?>
                                        <img src="<?= htmlspecialchars($photoUrl) ?>" 
                                             alt="<?= htmlspecialchars($m['name']) ?>" 
                                             class="member-photo"
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div class="avatar-circle" style="display:none; background-color: <?= getAvatarColor($m['name']) ?>;">
                                            <?= mb_substr($m['name'], 0, 1, 'UTF-8') ?>
                                        </div>
                                        <?php else: ?>
                                        <div class="avatar-circle" style="background-color: <?= getAvatarColor($m['name']) ?>;">
                                            <?= mb_substr($m['name'], 0, 1, 'UTF-8') ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div class="flex-grow-1 min-width-0">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-0 text-truncate" style="font-weight:600;">
                                                        <?= htmlspecialchars($m['name']) ?>
                                                    </h6>
                                                    <small class="text-muted">
                                                        <?= htmlspecialchars($m['member_id']) ?>
                                                        <?php if (!empty($m['phone'])): ?>
                                                        • <?= htmlspecialchars($m['phone']) ?>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                                <?php if ($m['status'] === 'active'): ?>
                                                <i class="fa-regular fa-circle-check fa-beat" style="color:#54A868; font-size:15px; padding:4px; margin-right:3px; vertical-align:middle;"></i>
                                                <?php else: ?>
                                                <i class="fa-regular fa-circle-xmark fa-beat" style="color:#DC3545; font-size:15px; padding:4px; margin-right:3px; vertical-align:middle;"></i>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($m['pending_fines_count'] > 0): ?>
                                            <span class="badge bg-warning text-dark mt-1">
                                                <i data-feather="alert-triangle" style="width:12px;height:12px"></i>
                                                <?= $m['pending_fines_count'] ?> টি বকেয়া ফাইন
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="row g-1 mt-3">
                                        <div class="col-4">
                                            <div class="stat-box">
                                                <div class="stat-label">মোট সঞ্চয় </div>
                                                <div class="stat-value positive"><?= formatCurrency($m['total_deposit']) ?></div>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="stat-box">
                                                <div class="stat-label">মোট উত্তোলন</div>
                                                <div class="stat-value negative"><?= formatCurrency($m['total_withdrawal']) ?></div>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="stat-box">
                                                <div class="stat-label">পরিশোধিত জরিমানা </div>
                                                <div class="stat-value warning"><?= formatCurrency($m['total_fine_paid']) ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row g-1 mt-1">
                                        <div class="col-6">
                                            <div class="stat-box">
                                                <div class="stat-label">বকেয়া জরিমানা</div>
                                                <div class="stat-value text-danger" style="font-size:13px;"><?= formatCurrency($m['total_fine_pending']) ?></div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="stat-box">
                                                <div class="stat-label">বর্তমান ব্যালেন্স</div>
                                                <div class="stat-value primary"><?= formatCurrency($m['current_balance']) ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between mt-2 pt-2 border-top" style="font-size:12px;color:#6c757d;">
                                        <div>
                                            <i data-feather="arrow-down-circle" style="width:12px;height:12px" class="text-success"></i>
                                            শেষ জমা: <?= $m['last_deposit_date'] ? date('d M Y', strtotime($m['last_deposit_date'])) : '-' ?>
                                        </div>
                                        <div>
                                            <i data-feather="arrow-up-circle" style="width:12px;height:12px" class="text-danger"></i>
                                            শেষ উত্তোলন: <?= $m['last_withdrawal_date'] ? date('d M Y', strtotime($m['last_withdrawal_date'])) : '-' ?>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-2 pt-2 border-top d-flex justify-content-end gap-1" onclick="event.stopPropagation();">
                                        <button class="btn btn-outline-primary btn-sm view-transactions" data-id="<?= $m['id'] ?>" data-name="<?= htmlspecialchars($m['name']) ?>">
                                            <i data-feather="book-open" style="width:13px;height:13px"></i>
                                            লেনদেন
                                        </button>
                                        <a href="deposits.php?member_id=<?= $m['id'] ?>" class="btn btn-outline-success btn-sm">
                                            <i data-feather="arrow-down" style="width:13px;height:13px"></i>
                                            জমা
                                        </a>
                                        <a href="withdrawals.php?member_id=<?= $m['id'] ?>" class="btn btn-outline-danger btn-sm">
                                            <i data-feather="arrow-up" style="width:13px;height:13px"></i>
                                            উত্তোলন
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div class="mt-4">
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center mb-0">
                                <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page-1 ?>&s=<?= urlencode($search) ?>&status=<?= $status ?>">
                                        <i data-feather="chevron-left" style="width:14px;height:14px"></i>
                                    </a>
                                </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&s=<?= urlencode($search) ?>&status=<?= $status ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page+1 ?>&s=<?= urlencode($search) ?>&status=<?= $status ?>">
                                        <i data-feather="chevron-right" style="width:14px;height:14px"></i>
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <div class="text-center text-muted small mt-2">
                            মোট <?= $totalRecords ?> টি রেকর্ড (পৃষ্ঠা <?= $page ?> / <?= $totalPages ?>)
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
     TRANSACTION HISTORY MODAL
     ============================================ -->
<div class="modal fade" id="transactionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i data-feather="book-open" style="width:18px;height:18px"></i>
                    <span id="modalMemberName">লেনদেন ইতিহাস</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs mb-3" id="transactionTabs">
                    <li class="nav-item">
                        <button class="nav-link active" data-type="all" onclick="loadTransactions('all')">
                            <i data-feather="list" style="width:14px;height:14px"></i> সব
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-type="deposit" onclick="loadTransactions('deposit')">
                            <i data-feather="arrow-down" style="width:14px;height:14px" class="text-success"></i> জমা
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-type="withdrawal" onclick="loadTransactions('withdrawal')">
                            <i data-feather="arrow-up" style="width:14px;height:14px" class="text-danger"></i> উত্তোলন
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-type="fine" onclick="loadTransactions('fine')">
                            <i data-feather="alert-triangle" style="width:14px;height:14px" class="text-warning"></i> ফাইন
                        </button>
                    </li>
                </ul>
                
                <div class="row g-2 mb-3" id="transactionSummary">
                    <div class="col-4">
                        <div class="stat-box">
                            <div class="stat-label">মোট জমা</div>
                            <div class="stat-value positive" id="totalDepositModal">৳ 0</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stat-box">
                            <div class="stat-label">মোট উত্তোলন</div>
                            <div class="stat-value negative" id="totalWithdrawalModal">৳ 0</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stat-box">
                            <div class="stat-label">আদায়কৃত ফাইন</div>
                            <div class="stat-value warning" id="totalFineModal">৳ 0</div>
                        </div>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <div class="stat-box">
                            <div class="stat-label">বকেয়া ফাইন</div>
                            <div class="stat-value warning" id="pendingFineAmountModal">৳ 0</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-box">
                            <div class="stat-label">বর্তমান ব্যালেন্স</div>
                            <div class="stat-value primary" id="balanceModal" style="font-size:20px;">৳ 0</div>
                        </div>
                    </div>
                </div>
                
                <div id="transactionList" style="max-height:400px;overflow-y:auto;">
                    <div class="text-center text-muted py-4">
                        <i data-feather="loader" style="width:32px;height:32px;display:block;margin:0 auto 10px;animation:spin 2s linear infinite;"></i>
                        <p>লোড হচ্ছে...</p>
                    </div>
                </div>
                
                <div class="mt-3 text-center">
                    <a href="deposits.php" class="btn btn-outline-success btn-sm" id="viewAllDeposits">
                        <i data-feather="arrow-down" style="width:14px;height:14px"></i> সব জমা দেখুন
                    </a>
                    <a href="withdrawals.php" class="btn btn-outline-danger btn-sm" id="viewAllWithdrawals">
                        <i data-feather="arrow-up" style="width:14px;height:14px"></i> সব উত্তোলন দেখুন
                    </a>
                    <a href="fine_details.php" class="btn btn-outline-warning btn-sm" id="viewAllFines">
                        <i data-feather="alert-triangle" style="width:14px;height:14px"></i> সব ফাইন দেখুন
                    </a>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বন্ধ</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
     JAVASCRIPT
     ============================================ -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
let currentMemberId = 0;
let currentMemberName = '';

function loadTransactions(type = 'all') {
    if (!currentMemberId) {
        $('#transactionList').html(`<div class="alert alert-warning">কোন সদস্য নির্বাচন করা হয়নি!</div>`);
        return;
    }
    
    $('#transactionTabs .nav-link').removeClass('active');
    $(`#transactionTabs .nav-link[data-type="${type}"]`).addClass('active');
    
    $('#transactionList').html(`
        <div class="text-center text-muted py-4">
            <i data-feather="loader" style="width:32px;height:32px;display:block;margin:0 auto 10px;animation:spin 2s linear infinite;"></i>
            <p>লোড হচ্ছে...</p>
        </div>
    `);
    
    const url = 'deposit_members.php?ajax=transactions&member_id=' + currentMemberId + '&type=' + type;
    
    $.ajax({
        url: url,
        method: 'GET',
        dataType: 'json',
        timeout: 30000,
        success: function(response) {
            if (response.success) {
                displayTransactions(response);
            } else {
                $('#transactionList').html(`<div class="alert alert-danger">${response.message || 'ডেটা লোড করতে ব্যর্থ হয়েছে'}</div>`);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            $('#transactionList').html(`
                <div class="alert alert-danger">
                    <strong>ত্রুটি:</strong> সার্ভার থেকে ডেটা লোড করতে ব্যর্থ হয়েছে<br>
                    <small class="text-muted">দয়া করে পৃষ্ঠাটি রিফ্রেশ করে আবার চেষ্টা করুন</small>
                </div>
            `);
        }
    });
}

function displayTransactions(data) {
    const transactions = data.transactions || [];
    
    $('#totalDepositModal').text(formatCurrency(data.total_deposit || 0));
    $('#totalWithdrawalModal').text(formatCurrency(data.total_withdrawal || 0));
    $('#totalFineModal').text(formatCurrency(data.total_fine || 0));
    
    // Pending fine in RED
    const pendingAmount = parseFloat(data.pending_fine_amount || 0);
    $('#pendingFineAmountModal').text(formatCurrency(pendingAmount));
    if (pendingAmount > 0) {
        $('#pendingFineAmountModal').css('color', '#DC3545');
        $('#pendingFineAmountModal').css('font-weight', '700');
    } else {
        $('#pendingFineAmountModal').css('color', '#F39C12');
        $('#pendingFineAmountModal').css('font-weight', '600');
    }
    
    $('#balanceModal').text(formatCurrency(data.balance || 0));
    
    if (transactions.length === 0) {
        $('#transactionList').html(`
            <div class="text-center text-muted py-4">
                <i data-feather="inbox" style="width:32px;height:32px;display:block;margin:0 auto 10px;opacity:0.5;"></i>
                <p>কোনো লেনদেন পাওয়া যায়নি</p>
            </div>
        `);
        return;
    }
    
    let html = '<div class="transaction-list">';
    
    transactions.forEach(tx => {
        const isDeposit = tx.transaction_type === 'deposit';
        const isWithdrawal = tx.transaction_type === 'withdrawal';
        const isFine = tx.transaction_type === 'fine';
        
        let typeClass = '', typeLabel = '', icon = '', colorClass = '';
        let amount = parseFloat(tx.amount) || 0;
        
        if (isDeposit) {
            typeClass = 'deposit'; typeLabel = 'জমা'; icon = 'arrow-down'; colorClass = 'text-success';
        } else if (isWithdrawal) {
            typeClass = 'withdrawal'; typeLabel = 'উত্তোলন'; icon = 'arrow-up'; colorClass = 'text-danger';
        } else if (isFine) {
            typeClass = 'fine'; typeLabel = 'ফাইন'; icon = 'alert-triangle'; colorClass = 'text-warning';
            amount = Math.abs(amount);
        }
        
        // Status badge for fines with Bengali labels
        let statusBadge = '';
        if (isFine && tx.fine_status) {
            const statusMap = { 
                'pending': 'বকেয়া', 
                'partially_paid': 'আংশিক', 
                'paid': '<span class="paid-icon-only"><i data-feather="check-circle" style= " width:14px; height:14px; position: relative; top: -1.5px;"></i></span>', 
                'waived': 'মওকুফ' 
            };
            const statusClass = tx.fine_status === 'paid' ? 'paid' : 
                               tx.fine_status === 'waived' ? 'waived' : 
                               tx.fine_status === 'partially_paid' ? 'partially_paid' : 'pending';
            statusBadge = `<span class="tx-status ${statusClass}">${statusMap[tx.fine_status] || tx.fine_status}</span>`;
        }
        
        let fineRemaining = '';
        if (isFine && tx.fine_remaining !== null && tx.fine_remaining > 0) {
            fineRemaining = `<div class="tx-meta">বাকি: ${formatCurrency(tx.fine_remaining)}</div>`;
        }
        
        // Use Bengali dates from PHP
        let displayMonth = tx.fine_month_bn || '-';
        let displayDate = tx.fine_date_bn || '-';
        
        // If PHP didn't provide Bengali dates, try to format in JavaScript
        if ((!tx.fine_month_bn || tx.fine_month_bn === '-') && tx.transaction_date) {
            try {
                const dateObj = new Date(tx.transaction_date);
                if (!isNaN(dateObj.getTime())) {
                    displayMonth = dateObj.toLocaleDateString('bn-BD', { month: 'long', year: 'numeric' });
                    displayDate = dateObj.toLocaleDateString('bn-BD', { day: '2-digit', month: 'long', year: 'numeric' });
                }
            } catch(e) {
                displayMonth = tx.transaction_date;
                displayDate = tx.transaction_date;
            }
        }
        
        // Show fine as PLUS (not minus)
        let amountDisplay = '';
        if (isDeposit) {
            amountDisplay = '+' + formatCurrency(amount);
        } else if (isWithdrawal) {
            amountDisplay = '-' + formatCurrency(amount);
        } else if (isFine) {
            amountDisplay = '+' + formatCurrency(amount);
        }
        
        // For fines, show Bengali date
        let dateDisplay = '';
        if (isFine) {
            dateDisplay = displayDate || displayMonth || '-';
        } else {
            dateDisplay = formatDate(tx.transaction_date);
        }
        
        html += `
            <div class="transaction-item">
                <div class="d-flex align-items-center gap-2">
                    <i data-feather="${icon}" style="width:16px;height:16px" class="${colorClass}"></i>
                    <div>
                        <div class="tx-type ${typeClass}">
                            ${typeLabel}
                            ${isFine ? ' • ' + displayMonth : ''}
                            ${statusBadge}
                        </div>
                        <div class="tx-meta">
                            ${isFine ? 'মাস: ' + displayMonth : 'রসিদ: ' + (tx.receipt_no || '-')}
                            ${tx.deposit_type ? ' • ' + tx.deposit_type : ''}
                            ${tx.withdrawal_type ? ' • ' + tx.withdrawal_type : ''}
                            ${tx.status && !isFine ? ' • ' + tx.status : ''}
                        </div>
                        ${fineRemaining}
                        ${tx.note ? `<div class="tx-meta">${tx.note}</div>` : ''}
                    </div>
                </div>
                <div class="text-end">
                    <div class="tx-amount ${typeClass}">
                        ${amountDisplay}
                    </div>
                    <div class="tx-date">${dateDisplay}</div>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    
    $('#viewAllDeposits').attr('href', `deposits.php?member_id=${currentMemberId}`);
    $('#viewAllWithdrawals').attr('href', `withdrawals.php?member_id=${currentMemberId}`);
    $('#viewAllFines').attr('href', `fine_details.php?member_id=${currentMemberId}`);
    
    $('#transactionList').html(html);
    
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
}

function formatCurrency(amount) {
    return '৳ ' + parseFloat(amount).toFixed(2);
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    try {
        const d = new Date(dateStr);
        return d.toLocaleDateString('bn-BD', { day: '2-digit', month: 'long', year: 'numeric' });
    } catch(e) {
        return dateStr;
    }
}

function openTransactionModal(memberId, memberName) {
    currentMemberId = memberId;
    currentMemberName = memberName;
    $('#modalMemberName').text(memberName + ' - লেনদেন ইতিহাস');
    $('#transactionModal').modal('show');
    loadTransactions('all');
}

$(document).ready(function() {
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
    
    $('.clickable-row').on('click', function(e) {
        if ($(e.target).closest('.btn, .btn-group, a').length) return;
        const id = $(this).data('id');
        const name = $(this).find('h6').text().trim();
        if (id) openTransactionModal(id, name);
    });
    
    $('.view-transactions').on('click', function(e) {
        e.stopPropagation();
        const id = $(this).data('id');
        const name = $(this).data('name');
        if (id) openTransactionModal(id, name);
    });
    
    $('#filterForm select').on('change', function() {
        this.closest('form').submit();
    });
    
    $(document).keydown(function(e) {
        if (e.key === 'Escape') $('#transactionModal').modal('hide');
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>