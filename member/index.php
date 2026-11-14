<?php
// =====================================================
// member/index.php - Member Dashboard
// =====================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/functions.php';
startSecureSession();

// Check if member is logged in
if (!isset($_SESSION['member_id'])) {
    header('Location: /member/login.php');
    exit;
}

$mId = $_SESSION['member_id'];
$conn = conn();

// Get complete member profile
$member = queryOne("SELECT * FROM members WHERE id = ?", 'i', [$mId]);

if (!$member) {
    header('Location: /member/login.php');
    exit;
}

// ============================================
// SKIP PASSWORD CHANGE
// ============================================
if (isset($_GET['skip_password']) && $_GET['skip_password'] == 1) {
    $_SESSION['skip_password_change'] = true;
    header('Location: /member/index.php');
    exit;
}

// ============================================
// CHECK IF PASSWORD CHANGE IS REQUIRED (FORCE)
// ============================================
$showPasswordChange = false;
$showPasswordNotice = false;

// If user has skipped, show notice instead of overlay
if (isset($_SESSION['skip_password_change']) && $_SESSION['skip_password_change'] === true) {
    $showPasswordNotice = true;
} else {
    // Only show overlay if password not changed and not skipped
    if (isset($member['password_changed']) && $member['password_changed'] == 0) {
        $showPasswordChange = true;
    }
}

// Get somity name
$somityName = getSetting('somity_name') ?: 'সোমিটি';

// ============================================
// GET MEMBER'S MONTHLY DEPOSIT AMOUNT (FROM REGISTRATION)
// ============================================
$monthlyDepositAmount = isset($member['monthly_deposit']) ? (float)$member['monthly_deposit'] : 0;

// ============================================
// GET MEMBER FINANCIAL SUMMARY
// ============================================
$totalDeposit = 0;
$depositResult = queryOne("SELECT COALESCE(SUM(amount),0) AS total FROM deposits WHERE member_id=?", 'i', [$mId]);
if ($depositResult && isset($depositResult['total'])) {
    $totalDeposit = (float)$depositResult['total'];
}

$totalWithdrawal = 0;
$withdrawalResult = queryOne("SELECT COALESCE(SUM(amount),0) AS total FROM withdrawals WHERE member_id=? AND status='completed'", 'i', [$mId]);
if ($withdrawalResult && isset($withdrawalResult['total'])) {
    $totalWithdrawal = (float)$withdrawalResult['total'];
}

$balance = $totalDeposit - $totalWithdrawal;

$pendingWithdrawal = 0;
$pendingResult = queryOne("SELECT COALESCE(SUM(amount),0) AS total FROM withdrawals WHERE member_id=? AND status='pending'", 'i', [$mId]);
if ($pendingResult && isset($pendingResult['total'])) {
    $pendingWithdrawal = (float)$pendingResult['total'];
}

// Get due summary
$dueSummary = getMemberDueSummary($mId);
$totalDues = 0;
$unpaidMonths = 0;
if (is_array($dueSummary)) {
    $totalDues = isset($dueSummary['total_due']) ? (float)$dueSummary['total_due'] : 0;
    $unpaidMonths = isset($dueSummary['unpaid_months']) ? (int)$dueSummary['unpaid_months'] : 0;
}

// Active loans
$activeLoans = [];
$loansResult = queryAll(
    "SELECT loan_no, principal_amount, outstanding_balance, monthly_installment, status 
     FROM loans WHERE member_id=? AND status='active'",
    'i', [$mId]
);
if (is_array($loansResult)) {
    $activeLoans = $loansResult;
}

$totalLoanBal = 0;
foreach ($activeLoans as $loan) {
    $totalLoanBal += isset($loan['outstanding_balance']) ? (float)$loan['outstanding_balance'] : 0;
}

// Profit
$myProfit = 0;
$profitResult = queryOne("SELECT COALESCE(SUM(profit_amount),0) AS total FROM profit_distributions WHERE member_id=?", 'i', [$mId]);
if ($profitResult && isset($profitResult['total'])) {
    $myProfit = (float)$profitResult['total'];
}

$pendingProfit = 0;
$pendingProfitResult = queryOne("SELECT COALESCE(SUM(profit_amount),0) AS total FROM profit_distributions WHERE member_id=? AND status='pending'", 'i', [$mId]);
if ($pendingProfitResult && isset($pendingProfitResult['total'])) {
    $pendingProfit = (float)$pendingProfitResult['total'];
}

// ============================================
// GET CURRENT MONTH'S DUE STATUS
// ============================================
$currentMonth = date('n');
$currentYear = date('Y');
$isCurrentMonthPaid = false;
$currentMonthStatus = '';

$currentDue = queryOne(
    "SELECT id, amount, status FROM monthly_dues 
     WHERE member_id = ? AND due_month = ? AND due_year = ?",
    'iii', [$mId, $currentMonth, $currentYear]
);

if ($currentDue && isset($currentDue['id'])) {
    $currentMonthStatus = isset($currentDue['status']) ? $currentDue['status'] : 'unpaid';
    if ($currentMonthStatus == 'paid') {
        $isCurrentMonthPaid = true;
    }
}

// ============================================
// GET MEMBER FINES
// ============================================
$totalFine = 0;
$pendingFine = 0;
$paidFine = 0;
$waivedFine = 0;
$fines = [];
$hasFines = false;

$finesResult = queryAll(
    "SELECT * FROM fines WHERE member_id = ? ORDER BY month_year DESC, due_date ASC",
    'i', [$mId]
);

if (is_array($finesResult) && !empty($finesResult)) {
    $fines = $finesResult;
    $hasFines = true;
    
    foreach ($fines as $fine) {
        $fineAmount = isset($fine['fine_amount']) ? (float)$fine['fine_amount'] : 0;
        $paidAmount = isset($fine['paid_amount']) ? (float)$fine['paid_amount'] : 0;
        $waivedAmount = isset($fine['waived_amount']) ? (float)$fine['waived_amount'] : 0;
        $status = isset($fine['status']) ? $fine['status'] : 'pending';
        
        if ($status == 'paid') {
            $paidFine += $fineAmount;
        } elseif ($status == 'waived') {
            $waivedFine += $fineAmount;
        } else {
            $pendingFine += ($fineAmount - $paidAmount - $waivedAmount);
        }
        $totalFine += $fineAmount;
    }
}

// ============================================
// CALCULATE TOTAL PAYABLE
// ============================================
$totalPayable = $monthlyDepositAmount + $pendingFine;

// ============================================
// RECENT TRANSACTIONS
// ============================================
$recentDeposits = [];
$depResult = queryAll(
    "SELECT * FROM deposits WHERE member_id=? ORDER BY deposit_date DESC LIMIT 5",
    'i', [$mId]
);
if (is_array($depResult)) {
    $recentDeposits = $depResult;
}

$recentWithdrawals = [];
$wdResult = queryAll(
    "SELECT * FROM withdrawals WHERE member_id=? AND status='completed' ORDER BY created_at DESC LIMIT 5",
    'i', [$mId]
);
if (is_array($wdResult)) {
    $recentWithdrawals = $wdResult;
}

$pendingWithdrawals = [];
$pendingWdResult = queryAll(
    "SELECT * FROM withdrawals WHERE member_id=? AND status='pending' ORDER BY created_at DESC",
    'i', [$mId]
);
if (is_array($pendingWdResult)) {
    $pendingWithdrawals = $pendingWdResult;
}

$recentDues = [];
$duesResult = queryAll(
    "SELECT * FROM monthly_dues WHERE member_id=? ORDER BY due_year DESC, due_month DESC LIMIT 6",
    'i', [$mId]
);
if (is_array($duesResult)) {
    $recentDues = $duesResult;
}

// ============================================
// NOTIFICATIONS
// ============================================
$notifications = [];
$notifResult = queryAll(
    "SELECT * FROM notifications 
     WHERE (target='all' OR (target='member' AND member_id=?)) 
     AND is_read=0 
     ORDER BY created_at DESC LIMIT 5",
    'i', [$mId]
);
if (is_array($notifResult)) {
    $notifications = $notifResult;
}

// ============================================
// CHART DATA
// ============================================
$chartLabels = [];
$chartValues = [];

try {
    $chartResult = queryAll(
        "SELECT 
            DATE_FORMAT(deposit_date, '%b %Y') AS label,
            DATE_FORMAT(deposit_date, '%Y-%m') AS month_key,
            SUM(amount) AS total
         FROM deposits 
         WHERE member_id = ? 
         AND deposit_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
         GROUP BY DATE_FORMAT(deposit_date, '%Y-%m')
         ORDER BY MIN(deposit_date) ASC",
        'i', [$mId]
    );
    
    if (is_array($chartResult) && !empty($chartResult)) {
        foreach ($chartResult as $row) {
            $chartLabels[] = $row['label'];
            $chartValues[] = (float)$row['total'];
        }
    } else {
        for ($i = 5; $i >= 0; $i--) {
            $month = date('M Y', strtotime("-{$i} months"));
            $chartLabels[] = $month;
            $chartValues[] = 0;
        }
    }
} catch (Exception $e) {
    for ($i = 5; $i >= 0; $i--) {
        $month = date('M Y', strtotime("-{$i} months"));
        $chartLabels[] = $month;
        $chartValues[] = 0;
    }
}

// ============================================
// PROFIT DISTRIBUTIONS
// ============================================
$profits = [];
$profitResult2 = queryAll(
    "SELECT * FROM profit_distributions 
     WHERE member_id=? ORDER BY fiscal_year DESC LIMIT 5",
    'i', [$mId]
);
if (is_array($profitResult2)) {
    $profits = $profitResult2;
}

// ============================================
// CHECK IF NOTICE SHOULD BE SHOWN
// ============================================
$showDepositNotice = false;
$noticeType = '';
$today = (int)date('j');
$currentMonthNum = (int)date('n');

$unpaidCheck = queryOne(
    "SELECT id, status FROM monthly_dues 
     WHERE member_id = ? AND due_month = ? AND due_year = ? 
     AND status IN ('unpaid', 'partial')",
    'iii', [$mId, $currentMonthNum, $currentYear]
);

if ($unpaidCheck && isset($unpaidCheck['id'])) {
    if ($today >= 25 && $today <= 31) {
        $showDepositNotice = true;
        $noticeType = 'first_notice';
    }
    if ($today >= 1 && $today <= 10) {
        $showDepositNotice = true;
        $noticeType = 'second_notice';
    }
}

// Check if member has photo
$hasPhoto = !empty($member['photo']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $member['photo']);

$pageTitle = 'ড্যাশবোর্ড';

// ============================================
// INCLUDE HEADER
// ============================================
require_once __DIR__ . '/includes/header.php';
?>

<!-- ============================================
     INDEX.PHP CUSTOM CSS
     ============================================ -->
<style>
/* ============================================
   GLOBAL - HIDE BODY SCROLL WHEN OVERLAY ACTIVE
   ============================================ */
<?php if ($showPasswordChange): ?>
body {
    overflow: hidden;
    height: 100vh;
}
<?php endif; ?>

/* ============================================
   GREETING CARD
   ============================================ */
.greeting-card {
    background: linear-gradient(135deg, #117A65, #0E6655);
    border-radius: 16px;
    padding: 20px 24px;
    color: white;
    margin-bottom: 16px;
    box-shadow: 0 4px 12px rgba(17, 122, 101, 0.3);
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    justify-content: space-between;
}

/* ============================================
   PROFILE PHOTO WITH SPINNING WHITE BORDER
   ============================================ */
.greeting-card .profile-photo {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    padding: 3px;
    transition: all 0.3s ease;
}

/* Inner photo container */
.greeting-card .profile-photo .photo-inner {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    overflow: hidden;
    background: rgba(255,255,255,0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    z-index: 1;
}

.greeting-card .profile-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
}

.greeting-card .profile-photo .avatar-text {
    font-size: 32px;
    font-weight: 700;
    color: white;
}

/* ============================================
   SPINNING WHITE BORDER
   ============================================ */
.greeting-card .profile-photo::before {
    content: '';
    position: absolute;
    inset: -2px;
    border-radius: 50%;
    border: 2px solid transparent;
    border-top: 2px solid rgba(255, 255, 255, 0.8);
    border-right: 2px solid rgba(255, 255, 255, 0.3);
    border-bottom: 2px solid rgba(255, 255, 255, 0.1);
    border-left: 2px solid rgba(255, 255, 255, 0.4);
    animation: spinBorder 3s linear infinite;
    z-index: 0;
}

@keyframes spinBorder {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Second subtle ring for depth */
.greeting-card .profile-photo::after {
    content: '';
    position: absolute;
    inset: -6px;
    border-radius: 50%;
    border: 1px solid rgba(255, 255, 255, 0.08);
    animation: pulseRing 3s ease-out infinite;
    z-index: 0;
}

@keyframes pulseRing {
    0% {
        transform: scale(0.95);
        opacity: 0.5;
    }
    50% {
        transform: scale(1.05);
        opacity: 0.1;
    }
    100% {
        transform: scale(0.95);
        opacity: 0.5;
    }
}

/* Hover Effects - Speed up border */
.greeting-card .profile-photo:hover::before {
    animation-duration: 1.5s;
    border-top: 2px solid rgba(255, 255, 255, 1);
    border-right: 2px solid rgba(255, 255, 255, 0.5);
    border-bottom: 2px solid rgba(255, 255, 255, 0.2);
    border-left: 2px solid rgba(255, 255, 255, 0.5);
}

.greeting-card .profile-photo:hover {
    transform: scale(1.05);
}

.greeting-card .profile-photo:hover .photo-inner {
    transform: scale(1.02);
}

/* ============================================
   GREETING CARD CONTENT
   ============================================ */
.greeting-card .greeting-content { flex: 1; min-width: 0; }
.greeting-card .greeting-text { font-size: 13px; opacity: 0.9; }
.greeting-card .member-name { font-size: 20px; font-weight: 700; margin: 2px 0; }
.greeting-card .member-id { font-size: 13px; opacity: 0.8; display: flex; align-items: center; gap: 6px; }
.greeting-card .member-id svg { width: 16px; height: 16px; }
.greeting-card .member-phone { font-size: 12px; opacity: 0.7; display: flex; align-items: center; gap: 4px; margin-top: 2px; }

.greeting-actions {
    flex-shrink: 0;
}

.btn-public-dashboard {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: rgba(255,255,255,0.2);
    color: white;
    border: 2px solid rgba(255,255,255,0.3);
    border-radius: 10px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s ease;
    backdrop-filter: blur(4px);
    font-family: 'Hind Siliguri', sans-serif;
}

.btn-public-dashboard:hover {
    background: rgba(255,255,255,0.35);
    border-color: white;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(255,255,255,0.2);
}

.btn-public-dashboard i {
    width: 18px;
    height: 18px;
}

/* ============================================
   STATS GRID
   ============================================ */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-bottom: 16px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 12px;
    text-align: center;
    transition: all 0.3s;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.1);
}

.stat-card .icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 6px;
}

.stat-card .icon.green { background: #D5F5E3; color: #1E8449; }
.stat-card .icon.blue { background: #D6EAF8; color: #1A5276; }
.stat-card .icon.orange { background: #FEF9E7; color: #B7950B; }
.stat-card .icon.red { background: #FDEDEC; color: #C0392B; }
.stat-card .icon.purple { background: #F5EEF8; color: #7D3C98; }
.stat-card .icon.gold { background: #FEF9E7; color: #D4AC0D; }
.stat-card .icon.teal { background: #E8F8F5; color: #0E6655; }

.stat-card .value {
    font-size: 18px;
    font-weight: 700;
    color: #1C2833;
}

.stat-card .value.total-payable {
    color: #C0392B;
    font-size: 20px;
}

.stat-card .label {
    font-size: 11px;
    color: #7f8c8d;
}

/* Highlight Card */
.stat-card.highlight-card {
    background: linear-gradient(135deg, #FDEDEC, #FADBD8);
    border: 2px solid #E74C3C;
    box-shadow: 0 4px 16px rgba(231, 76, 60, 0.2);
}

.stat-card.highlight-card .value {
    color: #C0392B;
}

/* Monthly Deposit Card */
.stat-card.monthly-card {
    border: 2px solid #117A65;
    background: linear-gradient(135deg, #E8F8F5, #D5F5E3);
}

.stat-card.monthly-card .value {
    color: #1E8449;
}

.status-badge {
    font-size: 10px;
    padding: 1px 8px;
    border-radius: 10px;
    display: inline-block;
    margin-top: 2px;
}
.status-badge.paid { background: #D5F5E3; color: #1E8449; }
.status-badge.unpaid { background: #FDEDEC; color: #C0392B; }
.status-badge.partial { background: #FEF9E7; color: #B7950B; }

/* ============================================
   CARDS
   ============================================ */
.card {
    border: none;
    border-radius: 14px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    margin-bottom: 14px;
    overflow: hidden;
    background: white;
}

.card-header {
    background: white;
    border-bottom: 1px solid #eee;
    padding: 12px 16px;
    font-weight: 600;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 14px;
}

.card-header a {
    font-size: 12px;
    color: #117A65;
    text-decoration: none;
}

.card-header a:hover { text-decoration: underline; }

.card-body {
    padding: 0;
}

/* ============================================
   LIST ITEMS
   ============================================ */
.list-item {
    padding: 12px 16px;
    border-bottom: 1px solid #f0f0f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.list-item:last-child { border-bottom: none; }

.list-item .item-title { font-size: 13px; font-weight: 500; }
.list-item .item-sub { font-size: 11px; color: #888; }
.list-item .amount-positive { color: #27AE60; font-weight: 600; }
.list-item .amount-negative { color: #E74C3C; font-weight: 600; }
.list-item .amount-due { color: #E74C3C; font-weight: 600; font-size: 15px; }

/* ============================================
   BADGES
   ============================================ */
.badge-status-paid { background: #D5F5E3; color: #1E8449; padding: 2px 10px; border-radius: 12px; font-size: 11px; }
.badge-status-unpaid { background: #FDEDEC; color: #C0392B; padding: 2px 10px; border-radius: 12px; font-size: 11px; }
.badge-status-partial { background: #FEF9E7; color: #B7950B; padding: 2px 10px; border-radius: 12px; font-size: 11px; }
.badge-status-pending { background: #FEF9E7; color: #B7950B; padding: 2px 10px; border-radius: 12px; font-size: 11px; }
.badge-status-completed { background: #D5F5E3; color: #1E8449; padding: 2px 10px; border-radius: 12px; font-size: 11px; }
.badge-status-waived { background: #F5EEF8; color: #7D3C98; padding: 2px 10px; border-radius: 12px; font-size: 11px; }

/* ============================================
   NOTIFICATIONS
   ============================================ */
.notification-item {
    padding: 12px 16px;
    border-bottom: 1px solid #f0f0f0;
    display: flex;
    gap: 12px;
    align-items: flex-start;
}

.notification-item:last-child { border-bottom: none; }

.notification-item .notif-icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #e8f4f8;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: #117A65;
}

.notification-item .notif-title { font-size: 13px; font-weight: 600; }
.notification-item .notif-message { font-size: 12px; color: #666; }
.notification-item .notif-time { font-size: 11px; color: #999; margin-top: 2px; }

/* ============================================
   EMPTY STATE
   ============================================ */
.empty-state {
    padding: 24px 16px;
    text-align: center;
    color: #999;
}

/* ============================================
   CHART
   ============================================ */
.chart-container {
    padding: 12px 16px 16px;
    height: 220px;
    position: relative;
}

.chart-container canvas {
    width: 100% !important;
    height: 100% !important;
}

.chart-empty {
    padding: 40px 16px;
    text-align: center;
    color: #999;
}

.chart-empty svg {
    width: 48px;
    height: 48px;
    color: #ddd;
    display: block;
    margin: 0 auto 10px;
}

/* ============================================
   ALERTS
   ============================================ */
.alert {
    border-radius: 12px;
    font-size: 13px;
    padding: 12px 16px;
    margin-bottom: 14px;
    border: none;
}
.alert-success { background: #D5F5E3; color: #1E8449; }
.alert-danger { background: #FDEDEC; color: #C0392B; }
.alert-warning { background: #FEF9E7; color: #B7950B; }
.alert-info { background: #D6EAF8; color: #1A5276; }

/* Password Notice Alert */
.alert-password-notice {
    background: #FEF9E7;
    border: 2px solid #F39C12;
    color: #7D6608;
    border-radius: 12px;
    padding: 12px 16px;
    margin-bottom: 14px;
    display: <?= $showPasswordNotice ? 'flex' : 'none' ?>;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
}

.alert-password-notice .notice-content {
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-password-notice .notice-content svg {
    color: #E67E22;
    flex-shrink: 0;
}

.alert-password-notice .btn-change-password-notice {
    background: #F39C12;
    color: white;
    padding: 8px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    border: none;
    cursor: pointer;
}

.alert-password-notice .btn-change-password-notice:hover {
    background: #E67E22;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(243, 156, 18, 0.3);
}

/* ============================================
   BLUR CONTENT
   ============================================ */
.blur-content {
    opacity: 0.15;
    pointer-events: none;
    filter: blur(8px);
    user-select: none;
    transition: all 0.3s ease;
}

/* ============================================
   SCROLLING NOTICE
   ============================================ */
.deposit-notice {
    background: linear-gradient(90deg, #FEF9E7, #FDEBD0);
    border: 2px solid #F39C12;
    border-radius: 12px;
    padding: 12px 16px;
    margin-bottom: 14px;
    overflow: hidden;
    position: relative;
    box-shadow: 0 2px 8px rgba(243, 156, 18, 0.2);
    display: <?= $showDepositNotice ? 'block' : 'none' ?>;
}

.deposit-notice .notice-scroll {
    display: flex;
    align-items: center;
    gap: 20px;
    animation: scrollNotice 20s linear infinite;
    white-space: nowrap;
}

@keyframes scrollNotice {
    0% { transform: translateX(100%); }
    100% { transform: translateX(-100%); }
}

.deposit-notice .notice-icon {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #B7950B;
    font-weight: 600;
    font-size: 14px;
    flex-shrink: 0;
}

.deposit-notice .notice-icon svg { width: 20px; height: 20px; }
.deposit-notice .notice-text { font-size: 14px; font-weight: 500; color: #7D6608; }
.deposit-notice .notice-text .highlight { color: #C0392B; font-weight: 700; }
.deposit-notice .notice-text .highlight-warning { color: #E67E22; font-weight: 700; }

/* ============================================
   PASSWORD OVERLAY
   ============================================ */
.password-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.75);
    backdrop-filter: blur(12px);
    z-index: 99999;
    display: <?= $showPasswordChange ? 'flex' : 'none' ?>;
    align-items: center;
    justify-content: center;
    padding: 20px;
    animation: fadeIn 0.4s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(40px) scale(0.95); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

.password-card {
    background: white;
    border-radius: 24px;
    padding: 36px 32px;
    max-width: 440px;
    width: 100%;
    box-shadow: 0 30px 80px rgba(0,0,0,0.5);
    animation: slideUp 0.5s ease-out;
    position: relative;
    overflow: hidden;
}

.password-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 6px;
    background: linear-gradient(90deg, #E74C3C, #F39C12, #E74C3C);
    background-size: 200% 100%;
    animation: gradientMove 3s ease-in-out infinite;
}

@keyframes gradientMove {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

.password-card .warning-header {
    background: #FDEDEC;
    border-radius: 12px;
    padding: 12px 16px;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 12px;
    border-left: 5px solid #E74C3C;
}

.password-card .warning-header .icon { color: #E74C3C; flex-shrink: 0; }
.password-card .warning-header .text { font-size: 14px; color: #C0392B; font-weight: 600; }

.password-card .lock-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: #FDEDEC;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
    color: #E74C3C;
    border: 3px solid #FADBD8;
}

.password-card .lock-icon svg { width: 40px; height: 40px; }

.password-card h4 { 
    text-align: center; 
    color: #1C2833; 
    font-weight: 700; 
    margin-bottom: 6px; 
    font-size: 22px;
}

.password-card .sub-text { 
    text-align: center; 
    color: #7f8c8d; 
    font-size: 14px; 
    margin-bottom: 20px; 
    line-height: 1.6;
}

.password-card .sub-text strong { 
    color: #E74C3C; 
    background: #FDEDEC;
    padding: 2px 12px;
    border-radius: 6px;
    font-weight: 700;
}

.password-card .alert-warning-custom {
    background: #FEF9E7;
    border: 1px solid #F9E79F;
    border-radius: 10px;
    padding: 10px 14px;
    font-size: 13px;
    color: #7D6608;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.password-card .alert-warning-custom strong { color: #C0392B; }

.password-card .form-group { margin-bottom: 16px; }

.password-card .form-group label { 
    font-size: 13px; 
    font-weight: 600; 
    color: #2C3E50; 
    margin-bottom: 5px; 
    display: block; 
}

.password-card .form-group .input-group {
    border: 2px solid #E5E7EB;
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s;
    background: white;
    display: flex;
    align-items: center;
}

.password-card .form-group .input-group:focus-within {
    border-color: #E74C3C;
    box-shadow: 0 0 0 4px rgba(231, 76, 60, 0.12);
}

.password-card .form-group .input-group .input-icon {
    padding: 0 14px;
    display: flex;
    align-items: center;
    color: #95A5A6;
    background: #F8F9FA;
    border-right: 2px solid #E5E7EB;
    height: 100%;
    min-height: 48px;
}

.password-card .form-group .input-group .input-icon svg { width: 18px; height: 18px; }

.password-card .form-group input {
    border: none;
    padding: 12px 16px;
    font-size: 14px;
    flex: 1;
    outline: none;
    background: transparent;
    font-family: 'Hind Siliguri', sans-serif;
    width: 100%;
    min-height: 48px;
}

.password-card .form-group input:focus { box-shadow: none; }

.password-card .btn-change-password {
    width: 100%;
    padding: 16px;
    background: linear-gradient(135deg, #E74C3C, #C0392B);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 700;
    font-family: 'Hind Siliguri', sans-serif;
    transition: all 0.3s;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-top: 4px;
}

.password-card .btn-change-password:hover { 
    transform: translateY(-3px); 
    box-shadow: 0 10px 30px rgba(231, 76, 60, 0.4); 
}

.password-card .btn-change-password:disabled { 
    opacity: 0.5; 
    cursor: not-allowed; 
    transform: none !important; 
    box-shadow: none !important; 
}

/* Skip Button */
.password-card .btn-skip {
    width: 100%;
    padding: 16px;
    background: #95A5A6;
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 700;
    font-family: 'Hind Siliguri', sans-serif;
    transition: all 0.3s;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-top: 8px;
    text-decoration: none;
    text-align: center;
}

.password-card .btn-skip:hover { 
    background: #7F8C8D;
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(149, 165, 166, 0.4);
    color: white;
    text-decoration: none;
}

.password-card .btn-skip i {
    width: 18px;
    height: 18px;
}

.password-card .password-error {
    color: #E74C3C;
    font-size: 13px;
    margin-top: 6px;
    display: none;
    align-items: center;
    gap: 6px;
}

.password-card .password-error.show { display: flex; }

.password-card .password-match {
    color: #27AE60;
    font-size: 13px;
    margin-top: 6px;
    display: none;
    align-items: center;
    gap: 6px;
}

.password-card .password-match.show { display: flex; }

.password-strength {
    margin-top: 8px;
    height: 4px;
    border-radius: 4px;
    background: #E5E7EB;
    overflow: hidden;
    transition: all 0.3s;
}

.password-strength .bar {
    height: 100%;
    width: 0%;
    border-radius: 4px;
    transition: all 0.4s ease;
}

.password-strength .bar.weak { width: 33%; background: #E74C3C; }
.password-strength .bar.medium { width: 66%; background: #F39C12; }
.password-strength .bar.strong { width: 100%; background: #27AE60; }

.password-strength-labels {
    display: flex;
    justify-content: space-between;
    font-size: 10px;
    color: #95A5A6;
    margin-top: 2px;
}

.force-notice {
    text-align: center;
    padding: 12px;
    background: #FDEDEC;
    border-radius: 10px;
    margin-top: 14px;
    font-size: 13px;
    color: #C0392B;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-weight: 500;
    border: 1px dashed #E74C3C;
}

/* ============================================
   RESPONSIVE
   ============================================ */
@media (max-width: 768px) {
    .stats-grid { grid-template-columns: repeat(3, 1fr); gap: 8px; }
    .alert-password-notice { flex-direction: column; text-align: center; }
    .alert-password-notice .notice-content { justify-content: center; font-size: 11px; }
}

@media (max-width: 576px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 8px; }
    .stat-card .value { font-size: 16px; }
    .stat-card .value.total-payable { font-size: 18px; }
    .list-item { padding: 10px 14px; font-size: 13px; }
    
    .greeting-card {
        padding: 16px;
        flex-direction: column;
        text-align: center;
    }
    .greeting-card .profile-photo {
        width: 60px;
        height: 60px;
        padding: 2px;
    }
    .greeting-card .profile-photo .avatar-text {
        font-size: 24px;
    }
    .greeting-card .profile-photo::before {
        inset: -1px;
        border-width: 1.5px;
    }
    .greeting-card .profile-photo::after {
        inset: -4px;
        border-width: 1px;
    }
    .greeting-card .member-name { font-size: 18px; }
    .greeting-card .member-id { justify-content: center; }
    .greeting-card .member-phone { justify-content: center; }
    .greeting-actions {
        width: 100%;
        margin-top: 8px;
    }
    .btn-public-dashboard {
        width: 100%;
        justify-content: center;
        font-size: 13px;
        padding: 8px 16px;
    }
    
    .chart-container { height: 180px; }
    .password-card { padding: 24px 20px; margin: 10px; }
    .password-card h4 { font-size: 20px; }
    .password-card .sub-text { font-size: 13px; }
    .deposit-notice .notice-scroll { animation-duration: 25s; }
    .deposit-notice .notice-text { font-size: 12px; }
}

@media (max-width: 400px) {
    .stats-grid { gap: 6px; }
    .stat-card { padding: 8px; }
    .stat-card .value { font-size: 14px; }
    .stat-card .value.total-payable { font-size: 16px; }
    .stat-card .label { font-size: 10px; }
    .stat-card .icon { width: 28px; height: 28px; }
    .stat-card .icon svg { width: 14px; height: 14px; }
    .chart-container { height: 150px; }
    .btn-public-dashboard { font-size: 12px; padding: 6px 12px; }
    .btn-public-dashboard i { width: 14px; height: 14px; }
    .password-card { padding: 20px 16px; }
    .password-card h4 { font-size: 18px; }
    .greeting-card .profile-photo {
        width: 50px;
        height: 50px;
        padding: 2px;
    }
    .greeting-card .profile-photo .avatar-text {
        font-size: 20px;
    }
    .greeting-card .profile-photo::before {
        inset: -1px;
        border-width: 1.5px;
    }
    .greeting-card .profile-photo::after {
        inset: -3px;
        border-width: 1px;
    }
}
</style>

<!-- ============================================
     PASSWORD OVERLAY (ONLY NEW PASSWORD - NO CURRENT PASSWORD)
     ============================================ -->
<?php if ($showPasswordChange): ?>
<div class="password-overlay" id="passwordOverlay">
    <div class="password-card">
        <div class="warning-header">
            <span class="icon"><i data-feather="alert-triangle" style="width:22px;height:22px;"></i></span>
            <span class="text">পাসওয়ার্ড পরিবর্তন আবশ্যক!</span>
        </div>
        
        <div class="lock-icon"><i data-feather="lock"></i></div>
        <h4>🔐 নিরাপদ পাসওয়ার্ড সেট করুন</h4>
        <p class="sub-text">
            হ্যালো<strong> মিঃ <?= isset($member['name']) ? clean($member['name']) : 'সদস্য' ?> </strong>, আপনার বর্তমান পাসওয়ার্ডটি ডিফল্ট।
<?php /* <?= isset($member['phone']) ? $member['phone'] : '01XXXXXXXXX' ?> */ ?>
           <!-- </strong> ব্যবহার করা হচ্ছে।<br> -->
            অ্যাকাউন্ট নিরাপদ রাখতে এখনই একটি নতুন পাসওয়ার্ড সেট করুন।
        </p>
        
        <div class="alert-warning-custom">
            <i data-feather="info" style="width:18px;height:18px;flex-shrink:0;"></i>
            আপনার বর্তমান পাসওয়ার্ড: <strong><?= isset($member['phone']) ? $member['phone'] : 'মোবাইল নম্বর' ?></strong>
            <br>
           <!--  <small style="color:#888;font-size:12px;">(আপনার বর্তমান পাসওয়ার্ডটি আপনার মোবাইল নম্বর)</small> -->
        </div>
        
        <form method="POST" action="/member/change_password.php">
            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
            <input type="hidden" name="force_change" value="1">
            <input type="hidden" name="skip_current_password" value="1">
            
            <div class="form-group">
                <label>নতুন পাসওয়ার্ড:</label>
                <div class="input-group">
                    <span class="input-icon"><i data-feather="lock"></i></span>
                    <input type="password" name="new_password" id="newPassword" placeholder="৮+ অক্ষর, সংখ্যা ও বিশেষ চিহ্ন" required minlength="8" autofocus>
                </div>
                <div class="password-strength"><div class="bar" id="strengthBar"></div></div>
                <div class="password-strength-labels">
                    <span>দুর্বল</span>
                    <span>মাঝারি</span>
                    <span>শক্তিশালী</span>
                </div>
            </div>
            
            <div class="form-group">
                <label>পাসওয়ার্ড নিশ্চিত করুন:</label>
                <div class="input-group">
                    <span class="input-icon"><i data-feather="check-circle"></i></span>
                    <input type="password" name="confirm_password" id="confirmPassword" placeholder="আবার পাসওয়ার্ড দিন" required>
                </div>
                <div class="password-error" id="passwordError">
                    <i data-feather="alert-circle" style="width:14px;height:14px;"></i> 
                    পাসওয়ার্ড মিলছে না!
                </div>
                <div class="password-match" id="passwordMatch">
                    <i data-feather="check-circle" style="width:14px;height:14px;"></i> 
                    পাসওয়ার্ড মিলেছে ✅
                </div>
            </div>
            
            <button type="submit" class="btn-change-password" id="submitBtn">
                <i data-feather="save" style="width:18px;height:18px;"></i> 
                পাসওয়ার্ড পরিবর্তন করুন
            </button>
        </form>
        
        <!-- SKIP BUTTON -->
        <a href="/member/index.php?skip_password=1" class="btn-skip">
            <i data-feather="arrow-right" style="width:18px;height:18px;"></i>
            এখনই নয়, পরে করব
        </a>
        
        <div class="force-notice" style="margin-top:12px;">
            <i data-feather="info" style="width:16px;height:16px;"></i>
            পরে করলে আবার এই নোটিশ দেখানো হবে।
        </div>
    </div>
</div>
<?php endif; ?>
    <!-- ============================================
         PASSWORD CHANGE NOTICE (IF SKIPPED)
         ============================================ -->
    <?php if ($showPasswordNotice): ?>
    <div class="alert-password-notice" id="passwordNotice">
        <div class="notice-content">
            <i data-feather="alert-triangle" style="width:20px;height:20px;"></i>
            <div>
                <strong>নিরাপত্তা সতর্কতা:</strong> 
                দয়া করে আপনার পাসওয়ার্ড পরিবর্তন করুন।
               <!-- <small style="display:block;color:#7f8c8d;font-size:11px;margin-top:2px;">
                    ডিফল্ট পাসওয়ার্ড: <?= isset($member['phone']) ? $member['phone'] : 'মোবাইল নম্বর' ?>
                </small> -->
            </div> 
        </div>
        <a href="/member/change_password.php" class="btn-change-password-notice">
            <i data-feather="lock" style="width:16px;height:16px;"></i>
            পাসওয়ার্ড পরিবর্তন করুন
        </a>
    </div>
    <?php endif; ?>

    <!-- ============================================
         SCROLLING DEPOSIT NOTICE
         ============================================ -->
    <?php if ($showDepositNotice): ?>
    <div class="deposit-notice" id="depositNotice">
        <div class="notice-scroll">
            <?php if ($noticeType == 'first_notice'): ?>
            <span class="notice-icon"><i data-feather="clock"></i> ⏰</span>
            <span class="notice-text">
                <span class="highlight">মাসের ৩০ তারিখ</span> থেকে সতর্কতা: আপনার জমা খুব শীঘ্রই আসছে। দয়া করে পরিশোধের জন্য প্রস্তুত থাকুন।
            </span>
            <span style="color:#B7950B; margin: 0 10px;">|</span>
            <?php endif; ?>
            
            <?php if ($noticeType == 'second_notice'): ?>
            <span class="notice-icon"><i data-feather="calendar"></i> 📅</span>
            <span class="notice-text">
                নতুন মাসের <span class="highlight">১ তারিখ</span> থেকে জরুরি: <span class="highlight-warning">১০ তারিখের</span> মধ্যে জমা দিন। না দিলে <span class="highlight">২০০ টাকা</span> জরিমানা প্রযোজ্য!
            </span>
            <span style="color:#B7950B; margin: 0 10px;">|</span>
            <span class="notice-icon"><i data-feather="alert-triangle"></i> ⚠️</span>
            <span class="notice-text">
                <span class="highlight">১০ তারিখ</span> এর পর জমা দিলে <span class="highlight">২০০ টাকা</span> জরিমানা দিতে হবে। দ্রুত জমা দিন!
            </span>
            <span style="color:#B7950B; margin: 0 10px;">|</span>
            <?php endif; ?>
            
            <span class="notice-icon"><i data-feather="info"></i> ℹ️</span>
            <span class="notice-text">
                আপনার বর্তমান মাসের (<?= bengaliMonth($currentMonth) ?> <?= $currentYear ?>) চাঁদা বাকি আছে। দয়া করে জমা দিন।
            </span>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================
         GREETING CARD - WITH SPINNING WHITE BORDER
         ============================================ -->
    <div class="greeting-card">
        <div class="profile-photo">
            <div class="photo-inner">
                <?php if ($hasPhoto): ?>
                <img src="<?= $member['photo'] ?>" alt="Profile Photo">
                <?php else: ?>
                <span class="avatar-text"><?= mb_substr($member['name'] ?? 'M', 0, 1) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="greeting-content">
            <div class="greeting-text">
                <?php
                $hour = date('H');
                if ($hour < 12) echo '🌅 সুপ্রভাত';
                elseif ($hour < 17) echo '☀️ শুভ অপরাহ্ন';
                else echo '🌙 শুভ সন্ধ্যা';
                ?>
            </div>
            <div class="member-name"><?= isset($member['name']) ? clean($member['name']) : 'Member' ?></div>
            <div class="member-id">
                <i data-feather="hash" style="width:16px;height:16px"></i>
                সদস্য আইডি: <?= isset($member['member_id']) ? clean($member['member_id']) : 'N/A' ?>
            </div>
            <?php if (!empty($member['phone'])): ?>
            <div class="member-phone">
                <i data-feather="phone" style="width:14px;height:14px"></i>
                <?= clean($member['phone']) ?>
            </div>
            <?php endif; ?>
            <?php if ($isCurrentMonthPaid): ?>
            <div style="margin-top:4px;font-size:12px;background:rgba(255,255,255,0.15);padding:2px 12px;border-radius:12px;display:inline-block;">
                ✅ এই মাসের সঞ্চয় পরিশোধিত
            </div>
            <?php endif; ?>
        </div>
        
        <div class="greeting-actions">
            <a href="../member/public_dashboard.php" class="btn-public-dashboard">
                <i data-feather="users" style="width:16px;height:16px"></i>
                সদস্য ড্যাশবোর্ড
            </a>
        </div>
    </div>

    <!-- ============================================
         STATISTICS
         ============================================ -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="icon green"><i data-feather="arrow-down-circle" style="width:18px;height:18px"></i></div>
            <div class="value"><?= formatCurrency($totalDeposit) ?></div>
            <div class="label">মোট জমা</div>
        </div>
        <div class="stat-card">
            <div class="icon red"><i data-feather="arrow-up-circle" style="width:18px;height:18px"></i></div>
            <div class="value"><?= formatCurrency($totalWithdrawal) ?></div>
            <div class="label">মোট উত্তোলন</div>
        </div>
        <div class="stat-card">
            <div class="icon blue"><i data-feather="credit-card" style="width:18px;height:18px"></i></div>
            <div class="value"><?= formatCurrency($balance) ?></div>
            <div class="label">বর্তমান ব্যালেন্স</div>
        </div>
        
        <div class="stat-card monthly-card">
            <div class="icon teal"><i data-feather="calendar" style="width:18px;height:18px"></i></div>
            <div class="value"><?= formatCurrency($monthlyDepositAmount) ?></div>
            <div class="label">
                <strong>মাসিক সঞ্চয়</strong>
                <span style="font-size:10px;color:#888;display:block;">(রেজিস্ট্রেশন থেকে)</span>
            </div>
        </div>
        
        <?php if ($hasFines): ?>
        <div class="stat-card" style="<?= ($pendingFine > 0) ? 'border:2px solid #E74C3C;' : '' ?>">
            <div class="icon red"><i data-feather="alert-triangle" style="width:18px;height:18px"></i></div>
            <div class="value" style="color:#E74C3C;"><?= formatCurrency($pendingFine) ?></div>
            <div class="label">
                pending জরিমানা
                <?php if ($pendingFine > 0): ?>
                <span class="status-badge unpaid">⚠️ বাকি</span>
                <?php else: ?>
                <span class="status-badge paid">✅ পরিশোধিত</span>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="stat-card">
            <div class="icon green"><i data-feather="check-circle" style="width:18px;height:18px"></i></div>
            <div class="value" style="color:#27AE60;">✅</div>
            <div class="label">কোন জরিমানা নেই</div>
        </div>
        <?php endif; ?>
        
        <div class="stat-card">
            <div class="icon orange"><i data-feather="clock" style="width:18px;height:18px"></i></div>
            <div class="value"><?= formatCurrency($pendingWithdrawal) ?></div>
            <div class="label">pending উত্তোলন</div>
        </div>
        
        <?php if ($hasFines): ?>
        <div class="stat-card highlight-card">
            <div class="icon gold"><i data-feather="dollar-sign" style="width:18px;height:18px"></i></div>
            <div class="value total-payable"><?= formatCurrency($totalPayable) ?></div>
            <div class="label" style="font-weight:600;color:#C0392B;">
                <i data-feather="info" style="width:12px;height:12px"></i> 
                মোট পরিশোধযোগ্য
            </div>
        </div>
        <?php endif; ?>
        
        <div class="stat-card">
            <div class="icon green"><i data-feather="check-circle" style="width:18px;height:18px"></i></div>
            <div class="value" style="color:#27AE60;"><?= formatCurrency($paidFine) ?></div>
            <div class="label">পরিশোধিত জরিমানা</div>
        </div>
        <div class="stat-card">
            <div class="icon purple"><i data-feather="minus-circle" style="width:18px;height:18px"></i></div>
            <div class="value" style="color:#8E44AD;"><?= formatCurrency($waivedFine) ?></div>
            <div class="label">মওকুফকৃত জরিমানা</div>
        </div>
    </div>

    <!-- ============================================
         TOTAL PAYABLE BREAKDOWN
         ============================================ -->
    <?php if ($hasFines): ?>
    <div class="card" style="border-left: 4px solid #E74C3C; background:#FFF5F5;">
        <div class="card-header" style="background:#FFF5F5;">
            <span style="color:#C0392B;">
                <i data-feather="pie-chart" style="width:14px;height:14px"></i> 
                পরিশোধযোগ্য হিসাব (এই মাস)
            </span>
            <span style="font-size:12px;color:#E74C3C;font-weight:700;">
                মোট: <?= formatCurrency($totalPayable) ?>
            </span>
        </div>
        <div style="padding:10px 16px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;font-size:13px;">
            <div style="text-align:center;padding:8px;background:white;border-radius:8px;border:2px solid #117A65;">
                <div style="color:#1E8449;font-weight:600;font-size:16px;">
                    <?= formatCurrency($monthlyDepositAmount) ?>
                </div>
                <small style="color:#888;">
                    মাসিক সঞ্চয়
                    <span style="color:#117A65;font-weight:600;">(রেজিস্ট্রেশন)</span>
                </small>
            </div>
            <div style="text-align:center;padding:8px;background:white;border-radius:8px;border:2px solid <?= ($pendingFine > 0) ? '#E74C3C' : '#27AE60' ?>;">
                <div style="color:<?= ($pendingFine > 0) ? '#E74C3C' : '#27AE60' ?>;font-weight:600;font-size:16px;">
                    + <?= formatCurrency($pendingFine) ?>
                </div>
                <small style="color:#888;">
                    pending জরিমানা
                    <?php if ($pendingFine == 0): ?>
                    ✅
                    <?php endif; ?>
                </small>
            </div>
            <div style="text-align:center;padding:8px;background:#E74C3C;border-radius:8px;color:white;">
                <div style="font-weight:700;font-size:18px;"><?= formatCurrency($totalPayable) ?></div>
                <small style="color:#FFD0D0;">মোট পরিশোধযোগ্য</small>
            </div>
        </div>
        <div style="padding:8px 16px 12px;text-align:center;font-size:12px;color:#888;border-top:1px solid #f0f0f0;">
            <i data-feather="info" style="width:12px;height:12px"></i> 
            মাসিক সঞ্চয় (রেজিস্ট্রেশন) + pending জরিমানা = মোট পরিশোধযোগ্য টাকা
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================
         PENDING WITHDRAWALS
         ============================================ -->
    <?php if (!empty($pendingWithdrawals)): ?>
    <div class="card" style="border-left: 4px solid #F39C12;">
        <div class="card-header" style="background:#FEF9E7;">
            <span><i data-feather="clock" style="width:14px;height:14px"></i> ⏳ pending উত্তোলন</span>
            <span style="font-size:12px;color:#B7950B;"><?= count($pendingWithdrawals) ?> টি</span>
        </div>
        <?php foreach ($pendingWithdrawals as $wd): ?>
        <div class="list-item" style="background:#FFFDF7;">
            <div>
                <div class="item-title"><?= formatDate($wd['created_at']) ?></div>
                <div class="item-sub">রেফারেন্স: <?= $wd['reference_no'] ?? '-' ?> <span class="badge-status-pending ms-1">⏳ pending</span></div>
            </div>
            <div style="text-align:right">
                <div class="amount-negative">- <?= formatCurrency($wd['amount']) ?></div>
                <div style="font-size:11px;color:#888;"><?= ucfirst($wd['payment_method']) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
        <div style="padding:10px 16px;background:#FFFDF7;border-top:1px solid #f0f0f0;text-align:center;font-size:12px;color:#888;">
            <i data-feather="info" style="width:12px;height:12px"></i> আপনার উত্তোলন অনুরোধ pending আছে। প্রশাসকের অনুমোদনের অপেক্ষায়।
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================
         NOTIFICATIONS
         ============================================ -->
    <?php if (!empty($notifications)): ?>
    <div class="card">
        <div class="card-header">
            <span><i data-feather="bell" style="width:14px;height:14px"></i> নোটিফিকেশন</span>
            <a href="/member/notifications.php">সব দেখুন →</a>
        </div>
        <?php foreach ($notifications as $n): ?>
        <div class="notification-item">
            <div class="notif-icon"><i data-feather="bell" style="width:16px;height:16px"></i></div>
            <div class="notif-content">
                <div class="notif-title"><?= isset($n['title']) ? clean($n['title']) : '' ?></div>
                <div class="notif-message"><?= isset($n['message']) ? clean($n['message']) : '' ?></div>
                <div class="notif-time"><?= isset($n['created_at']) ? timeAgo($n['created_at']) : '' ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ============================================
         ACTIVE LOANS
         ============================================ -->
    <?php if (!empty($activeLoans)): ?>
    <div class="card">
        <div class="card-header">
            <span><i data-feather="briefcase" style="width:14px;height:14px"></i> সক্রিয় ঋণ</span>
            <a href="/member/loans.php">সব দেখুন →</a>
        </div>
        <?php foreach ($activeLoans as $loan): ?>
        <div class="list-item">
            <div>
                <div class="item-title"><?= isset($loan['loan_no']) ? $loan['loan_no'] : 'N/A' ?></div>
                <div class="item-sub">কিস্তি: <?= isset($loan['monthly_installment']) ? formatCurrency($loan['monthly_installment']) : '৳ 0' ?>/মাস</div>
            </div>
            <div style="text-align:right">
                <div class="amount-negative"><?= isset($loan['outstanding_balance']) ? formatCurrency($loan['outstanding_balance']) : '৳ 0' ?></div>
                <div style="font-size:11px;color:#888">বকেয়া</div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ============================================
         MONTHLY DUES
         ============================================ -->
    <div class="card">
        <div class="card-header">
            <span><i data-feather="calendar" style="width:14px;height:14px"></i> মাসিক সঞ্চয়</span>
            <a href="/member/dues.php">সব দেখুন →</a>
        </div>
        <?php if (empty($recentDues)): ?>
        <div class="empty-state">কোনো সঞ্চয়ের তথ্য পাওয়া যায়নি</div>
        <?php else: ?>
        <?php foreach ($recentDues as $d): 
            $status = isset($d['status']) ? $d['status'] : 'unpaid';
            $statusClass = '';
            $statusText = '';
            if ($status == 'paid') {
                $statusClass = 'badge-status-paid';
                $statusText = 'পরিশোধিত';
            } elseif ($status == 'partial') {
                $statusClass = 'badge-status-partial';
                $statusText = 'আংশিক';
            } else {
                $statusClass = 'badge-status-unpaid';
                $statusText = 'অপরিশোধিত';
            }
            $isCurrent = (isset($d['due_month']) && $d['due_month'] == $currentMonth && isset($d['due_year']) && $d['due_year'] == $currentYear);
        ?>
        <div class="list-item" style="<?= $isCurrent ? 'background:#FFFDF7;border-left:3px solid #F39C12;' : '' ?>">
            <div>
                <div class="item-title">
                    <?= isset($d['due_month']) ? bengaliMonth($d['due_month']) : '' ?> <?= isset($d['due_year']) ? $d['due_year'] : '' ?>
                    <?php if ($isCurrent): ?>
                    <span style="font-size:10px;background:#F39C12;color:white;padding:1px 8px;border-radius:10px;margin-left:4px;">বর্তমান</span>
                    <?php endif; ?>
                </div>
                <div class="item-sub">
                    <span class="<?= $statusClass ?>"><?= $statusText ?></span>
                </div>
            </div>
            <div class="amount-due">
                <?= isset($d['amount']) ? formatCurrency($d['amount']) : '৳ 0.00' ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ============================================
         FINES LIST (ONLY IF HAS FINES)
         ============================================ -->
    <?php if ($hasFines): ?>
    <div class="card">
        <div class="card-header">
            <span><i data-feather="alert-triangle" style="width:14px;height:14px"></i> জরিমানার তালিকা</span>
            <a href="/member/fines.php">সব দেখুন →</a>
        </div>
        <?php 
        $displayFines = array_slice($fines, 0, 5);
        foreach ($displayFines as $fine): 
            $fineAmount = isset($fine['fine_amount']) ? (float)$fine['fine_amount'] : 0;
            $paidAmount = isset($fine['paid_amount']) ? (float)$fine['paid_amount'] : 0;
            $waivedAmount = isset($fine['waived_amount']) ? (float)$fine['waived_amount'] : 0;
            $remaining = $fineAmount - $paidAmount - $waivedAmount;
            $status = isset($fine['status']) ? $fine['status'] : 'pending';
            
            $statusClass = '';
            $statusText = '';
            if ($status == 'paid') {
                $statusClass = 'badge-status-paid';
                $statusText = '✅ পরিশোধিত';
            } elseif ($status == 'waived') {
                $statusClass = 'badge-status-waived';
                $statusText = '⏳ মওকুফকৃত';
            } elseif ($status == 'partially_paid') {
                $statusClass = 'badge-status-partial';
                $statusText = '🔄 আংশিক';
            } else {
                $statusClass = 'badge-status-unpaid';
                $statusText = '⏳ pending';
            }
        ?>
        <div class="list-item" style="<?= ($status == 'pending' && $remaining > 0) ? 'border-left: 4px solid #E74C3C;' : ($status == 'partially_paid' ? 'border-left: 4px solid #F39C12;' : '') ?>">
            <div>
                <div class="item-title">
                    <?= isset($fine['month_year']) ? date('F Y', strtotime($fine['month_year'])) : '' ?>
                    <?php if (!empty($fine['notes'])): ?>
                    <small style="font-weight:normal;color:#888;font-size:11px;display:block;">📝 <?= clean($fine['notes']) ?></small>
                    <?php endif; ?>
                </div>
                <div class="item-sub" style="margin-top:4px;">
                    <span class="<?= $statusClass ?>"><?= $statusText ?></span>
                    <?php if ($status == 'partially_paid'): ?>
                    <span style="font-size:11px;color:#888;margin-left:6px;">
                        পরিশোধ: <?= formatCurrency($paidAmount) ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($status == 'waived'): ?>
                    <span style="font-size:11px;color:#888;margin-left:6px;">
                        মওকুফ: <?= formatCurrency($waivedAmount) ?>
                    </span>
                    <?php endif; ?>
                    <?php if (!empty($fine['due_date']) && $status != 'paid' && $status != 'waived'): ?>
                    <span style="font-size:11px;color:#E74C3C;margin-left:6px;">
                        📅 শেষ তারিখ: <?= formatDate($fine['due_date']) ?>
                    </span>
                    <?php endif; ?>
                    <?php if (!empty($fine['paid_date']) && $status == 'paid'): ?>
                    <span style="font-size:11px;color:#27AE60;margin-left:6px;">
                        ✅ পরিশোধ: <?= formatDate($fine['paid_date']) ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <div style="text-align:right;">
                <?php if ($status == 'paid'): ?>
                <div class="amount-positive"><?= formatCurrency($fineAmount) ?></div>
                <div style="font-size:10px;color:#27AE60;">পরিশোধিত</div>
                <?php elseif ($status == 'waived'): ?>
                <div style="color:#8E44AD;font-weight:600;"><?= formatCurrency($fineAmount) ?></div>
                <div style="font-size:10px;color:#8E44AD;">মওকুফকৃত</div>
                <?php else: ?>
                <div class="amount-due"><?= formatCurrency($remaining) ?></div>
                <div style="font-size:10px;color:#888;">জরিমানা: <?= formatCurrency($fineAmount) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if (count($fines) > 5): ?>
        <div style="padding:8px 16px;text-align:center;font-size:12px;color:#888;border-top:1px solid #f0f0f0;">
            এবং আরও <?= count($fines) - 5 ?> টি জরিমানা আছে
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ============================================
         RECENT DEPOSITS
         ============================================ -->
    <div class="card">
        <div class="card-header">
            <span><i data-feather="credit-card" style="width:14px;height:14px"></i> সর্বশেষ সঞ্চয়</span>
            <a href="/member/deposits.php">সব দেখুন →</a>
        </div>
        <?php if (empty($recentDeposits)): ?>
        <div class="empty-state">কোনো সঞ্চয় নেই</div>
        <?php else: ?>
        <?php foreach ($recentDeposits as $dep): ?>
        <div class="list-item">
            <div>
                <div class="item-title"><?= isset($dep['deposit_date']) ? formatDate($dep['deposit_date']) : '' ?></div>
                <div class="item-sub">রসিদ: <?= isset($dep['receipt_no']) ? $dep['receipt_no'] : '-' ?></div>
            </div>
            <div class="amount-positive">+ <?= isset($dep['amount']) ? formatCurrency($dep['amount']) : '৳ 0' ?></div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ============================================
         RECENT WITHDRAWALS
         ============================================ -->
    <?php if (!empty($recentWithdrawals)): ?>
    <div class="card">
        <div class="card-header">
            <span><i data-feather="arrow-up-circle" style="width:14px;height:14px"></i> সম্পন্ন উত্তোলন</span>
            <a href="/member/withdrawals.php">সব দেখুন →</a>
        </div>
        <?php foreach ($recentWithdrawals as $wd): ?>
        <div class="list-item">
            <div>
                <div class="item-title"><?= isset($wd['created_at']) ? formatDate($wd['created_at']) : '' ?></div>
                <div class="item-sub">রেফারেন্স: <?= isset($wd['reference_no']) ? $wd['reference_no'] : '-' ?></div>
            </div>
            <div style="text-align:right">
                <div class="amount-negative">- <?= isset($wd['amount']) ? formatCurrency($wd['amount']) : '৳ 0' ?></div>
                <div style="font-size:11px;color:#888;"><span class="badge-status-completed">✅ সম্পন্ন</span></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ============================================
         CHART
         ============================================ -->
    <div class="card">
        <div class="card-header">
            <span><i data-feather="bar-chart-2" style="width:14px;height:14px"></i> গত ৬ মাসের সঞ্চয়</span>
            <?php 
            $hasData = false;
            foreach ($chartValues as $val) {
                if ($val > 0) { $hasData = true; break; }
            }
            ?>
            <?php if (!$hasData): ?>
            <span class="badge bg-secondary">কোনো ডেটা নেই</span>
            <?php endif; ?>
        </div>
        <div class="chart-container">
            <?php if ($hasData): ?>
            <canvas id="depositChart"></canvas>
            <?php else: ?>
            <div class="chart-empty">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                </svg>
                <p>গত ৬ মাসে কোনো সঞ্চয় নেই</p>
                <small class="text-muted">আপনার প্রথম সঞ্চয় করুন</small>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================
         PROFIT DISTRIBUTIONS
         ============================================ -->
    <?php if (!empty($profits)): ?>
    <div class="card">
        <div class="card-header">
            <span><i data-feather="pie-chart" style="width:14px;height:14px"></i> লাভ বণ্টন</span>
        </div>
        <?php foreach ($profits as $p): ?>
        <div class="list-item">
            <div>
                <div class="item-title"><?= isset($p['fiscal_year']) ? $p['fiscal_year'] : '' ?></div>
                <div class="item-sub"><?= isset($p['distribution_ratio']) ? number_format($p['distribution_ratio'] * 100, 2) : 0 ?>% অনুপাত</div>
            </div>
            <div style="text-align:right">
                <div class="amount-positive"><?= isset($p['profit_amount']) ? formatCurrency($p['profit_amount']) : '৳ 0' ?></div>
                <div style="font-size:11px;color:#888"><?= isset($p['status']) && $p['status'] == 'paid' ? '✅ প্রদান' : '⏳ বকেয়া' ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div><!-- /main-content -->

<!-- ============================================
     INCLUDE FOOTER (Bottom Nav)
     ============================================ -->
<?php require_once __DIR__ . '/includes/footer.php'; ?>

<!-- ============================================
     CHART.JS SCRIPT - ADDED AT THE BOTTOM
     ============================================ -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check if chart container exists and has data
    const canvas = document.getElementById('depositChart');
    if (!canvas) {
        console.log('Chart canvas not found');
        return;
    }
    
    // Get chart data from PHP
    const labels = <?= json_encode($chartLabels) ?>;
    const values = <?= json_encode($chartValues) ?>;
    
    // Check if there's any data to show
    const hasData = values.some(v => v > 0);
    if (!hasData) {
        console.log('No data to display in chart');
        return;
    }
    
    // Create the chart with Bengali support
    try {
        const ctx = canvas.getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'সঞ্চয় (৳)',
                    data: values,
                    backgroundColor: [
                        'rgba(17, 122, 101, 0.7)',
                        'rgba(17, 122, 101, 0.7)',
                        'rgba(17, 122, 101, 0.7)',
                        'rgba(17, 122, 101, 0.7)',
                        'rgba(17, 122, 101, 0.7)',
                        'rgba(17, 122, 101, 0.7)'
                    ],
                    borderColor: '#117A65',
                    borderWidth: 2,
                    borderRadius: 6,
                    barPercentage: 0.6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return '৳ ' + context.parsed.y.toFixed(2);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '৳' + value;
                            },
                            font: {
                                size: 10
                            }
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 10
                            }
                        }
                    }
                }
            }
        });
        console.log('✅ Chart loaded successfully with data:', labels, values);
    } catch (error) {
        console.error('Chart creation error:', error);
    }
});

// ============================================
// PASSWORD FORM VALIDATION
// ============================================
<?php if ($showPasswordChange): ?>
document.addEventListener('DOMContentLoaded', function() {
    const newPassword = document.getElementById('newPassword');
    const confirmPassword = document.getElementById('confirmPassword');
    const passwordError = document.getElementById('passwordError');
    const passwordMatch = document.getElementById('passwordMatch');
    const strengthBar = document.getElementById('strengthBar');
    const submitBtn = document.getElementById('submitBtn');
    
    // Password strength checker
    newPassword.addEventListener('input', function() {
        const val = this.value;
        let strength = 0;
        
        if (val.length >= 8) strength++;
        if (val.match(/[a-z]+/)) strength++;
        if (val.match(/[A-Z]+/)) strength++;
        if (val.match(/[0-9]+/)) strength++;
        if (val.match(/[$@#&!]+/)) strength++;
        
        strengthBar.className = 'bar';
        if (val.length === 0) {
            strengthBar.style.width = '0%';
        } else if (strength <= 2) {
            strengthBar.classList.add('weak');
        } else if (strength <= 3) {
            strengthBar.classList.add('medium');
        } else {
            strengthBar.classList.add('strong');
        }
        
        checkPasswordMatch();
    });
    
    // Password match checker
    function checkPasswordMatch() {
        const newVal = newPassword.value;
        const confirmVal = confirmPassword.value;
        
        if (confirmVal.length === 0) {
            passwordError.classList.remove('show');
            passwordMatch.classList.remove('show');
            return;
        }
        
        if (newVal === confirmVal) {
            passwordError.classList.remove('show');
            passwordMatch.classList.add('show');
            submitBtn.disabled = false;
        } else {
            passwordError.classList.add('show');
            passwordMatch.classList.remove('show');
            submitBtn.disabled = true;
        }
    }
    
    confirmPassword.addEventListener('input', checkPasswordMatch);
});
<?php endif; ?>
</script>