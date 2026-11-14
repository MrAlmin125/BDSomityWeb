<?php
// =====================================================
// member/withdrawals.php - Member Withdrawal History (WITH TOGGLE FORM)
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

$member = getCurrentMember();
$mId = $member['id'];
$conn = conn();

// Get somity name
$somityName = getSetting('somity_name') ?: 'সোমিটি';

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

// ============================================
// HANDLE POST REQUESTS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'নিরাপত্তা যাচাই ব্যর্থ।');
        header('Location: withdrawals.php');
        exit;
    }
    
    $action = $_POST['action'] ?? '';
    
    // ============================================
    // REQUEST WITHDRAWAL
    // ============================================
    if ($action === 'request') {
        $amount = (float)($_POST['amount'] ?? 0);
        $withdrawal_type = trim($_POST['withdrawal_type'] ?? 'regular');
        $reason = trim($_POST['reason'] ?? '');
        $note = trim($_POST['note'] ?? '');
        
        // Validation
        if ($amount <= 0) {
            setFlash('danger', 'সঠিক পরিমাণ দিন।');
        } elseif ($amount > $balance) {
            setFlash('danger', 'আপনার ব্যালেন্স অপর্যাপ্ত। উপলব্ধ ব্যালেন্স: ' . formatCurrency($balance));
        } elseif ($amount < 100) {
            setFlash('danger', 'ন্যূনতম উত্তোলন পরিমাণ ৳১০০।');
        } else {
            // Generate receipt number
            $receipt_no = 'WD' . date('ymd') . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
            
            // Insert withdrawal request
            $stmt = $conn->prepare(
                "INSERT INTO withdrawals (member_id, amount, withdrawal_date, withdrawal_type, 
                 reason, receipt_no, status, note, created_by, created_at) 
                 VALUES (?, ?, NOW(), ?, ?, ?, 'pending', ?, ?, NOW())"
            );
            $stmt->bind_param('idssssi', 
                $mId, $amount, $withdrawal_type, $reason, 
                $receipt_no, $note, $mId
            );
            
            if ($stmt->execute()) {
                // Send notification to admin
                sendNotification('withdrawal', 'নতুন উত্তোলন অনুরোধ', 
                    "সদস্য {$member['name']} (ID: {$member['member_id']}) ৳" . number_format($amount, 2) . " উত্তোলনের অনুরোধ করেছেন। রসিদ: $receipt_no", 
                    'admin');
                
                // Send notification to member
                sendNotification('withdrawal', 'উত্তোলন অনুরোধ গৃহীত হয়েছে', 
                    "আপনার ৳" . number_format($amount, 2) . " উত্তোলনের অনুরোধ গৃহীত হয়েছে। রসিদ: $receipt_no", 
                    'member', $mId);
                
                logActivity('withdrawal_request', "সদস্য {$member['name']} উত্তোলন অনুরোধ করেছেন: $receipt_no (৳$amount)");
                
                setFlash('success', "আপনার উত্তোলন অনুরোধ সফলভাবে জমা হয়েছে। রসিদ: $receipt_no");
            } else {
                setFlash('danger', 'উত্তোলন অনুরোধ ব্যর্থ হয়েছে: ' . $conn->error);
            }
        }
        header('Location: withdrawals.php');
        exit;
    }
    
    // ============================================
    // CANCEL WITHDRAWAL REQUEST
    // ============================================
    if ($action === 'cancel') {
        $wdId = (int)($_POST['withdrawal_id'] ?? 0);
        if ($wdId > 0) {
            $check = queryOne("SELECT * FROM withdrawals WHERE id=? AND member_id=? AND status='pending'", 'ii', [$wdId, $mId]);
            if ($check) {
                $conn->query("UPDATE withdrawals SET status='cancelled' WHERE id=$wdId");
                setFlash('success', 'উত্তোলন অনুরোধ বাতিল করা হয়েছে।');
                logActivity('withdrawal_cancel', "সদস্য {$member['name']} উত্তোলন অনুরোধ বাতিল করেছেন: {$check['receipt_no']}");
            } else {
                setFlash('danger', 'উত্তোলন অনুরোধ পাওয়া যায়নি বা ইতিমধ্যে প্রক্রিয়াধীন।');
            }
        }
        header('Location: withdrawals.php');
        exit;
    }
}

// ============================================
// GET WITHDRAWAL HISTORY
// ============================================
$filter = $_GET['filter'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;

$where = "member_id = $mId";
$params = [];
$types = '';

if ($filter === 'pending') {
    $where .= " AND status = 'pending'";
} elseif ($filter === 'completed') {
    $where .= " AND status = 'completed'";
} elseif ($filter === 'cancelled') {
    $where .= " AND status = 'cancelled'";
} elseif ($filter === 'rejected') {
    $where .= " AND status = 'rejected'";
}

// Get total count
$countSql = "SELECT COUNT(*) as total FROM withdrawals WHERE $where";
$countResult = queryOne($countSql, $types, $params);
$totalRecords = $countResult['total'] ?? 0;
$totalPages = ceil($totalRecords / $perPage);
$offset = ($page - 1) * $perPage;

// Get withdrawals
$sql = "SELECT * FROM withdrawals WHERE $where ORDER BY created_at DESC LIMIT ? OFFSET ?";
$types .= 'ii';
$params[] = $perPage;
$params[] = $offset;

$withdrawals = queryAll($sql, $types, $params);
$withdrawals = $withdrawals ?: [];

// ============================================
// GET PENDING WITHDRAWAL COUNT
// ============================================
$pendingCount = queryOne(
    "SELECT COUNT(*) as count FROM withdrawals WHERE member_id=? AND status='pending'",
    'i', [$mId]
)['count'] ?? 0;

// ============================================
// GET MEMBER AVATAR
// ============================================
function getMemberAvatar($member) {
    if (!empty($member['photo']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $member['photo'])) {
        return $member['photo'];
    }
    $name = isset($member['name']) ? $member['name'] : 'Member';
    $firstLetter = mb_substr($name, 0, 1, 'UTF-8');
    $colors = ['#117A65', '#1B4F72', '#2E86C1', '#C0392B', '#8E44AD', '#D35400', '#27AE60'];
    $hash = 0;
    for ($i = 0; $i < strlen($name); $i++) {
        $hash = ($hash * 31 + ord($name[$i])) & 0xFFFFFFFF;
    }
    $color = $colors[abs($hash) % count($colors)];
    
    return 'data:image/svg+xml;charset=utf-8,' . rawurlencode(
        '<svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" viewBox="0 0 60 60">' .
        '<rect width="60" height="60" rx="30" fill="' . $color . '"/>' .
        '<text x="30" y="40" font-family="Arial, sans-serif" font-size="28" font-weight="bold" fill="white" text-anchor="middle">' . $firstLetter . '</text>' .
        '</svg>'
    );
}

$avatar = getMemberAvatar($member);

// ============================================
// GET UNREAD NOTIFICATIONS COUNT
// ============================================
$totalUnread = 0;
$unreadResult = queryOne(
    "SELECT COUNT(*) as count FROM notifications 
     WHERE (target='all' OR (target='member' AND member_id=?)) AND is_read=0",
    'i', [$mId]
);
if ($unreadResult && isset($unreadResult['count'])) {
    $totalUnread = (int)$unreadResult['count'];
}

// ============================================
// HELPER FUNCTIONS
// ============================================
function formatDateTime($datetime) {
    if (empty($datetime)) {
        return '-';
    }
    return date('d M Y, h:i A', strtotime($datetime));
}

function getStatusBadge($status) {
    $badges = [
        'pending' => '<span class="badge-status-pending"><i data-feather="clock" style="width:10px;height:10px"></i> pending</span>',
        'completed' => '<span class="badge-status-completed"><i data-feather="check-circle" style="width:10px;height:10px"></i> সম্পন্ন</span>',
        'cancelled' => '<span class="badge-status-cancelled"><i data-feather="x-circle" style="width:10px;height:10px"></i> বাতিল</span>',
        'rejected' => '<span class="badge-status-rejected"><i data-feather="alert-triangle" style="width:10px;height:10px"></i> প্রত্যাখ্যাত</span>'
    ];
    return $badges[$status] ?? '<span class="badge-status-pending">' . $status . '</span>';
}

function getWithdrawalTypeLabel($type) {
    $labels = [
        'regular' => 'নিয়মিত',
        'special' => 'বিশেষ',
        'emergency' => 'জরুরী',
        'partial' => 'আংশিক'
    ];
    return $labels[$type] ?? ucfirst($type);
}
require_once __DIR__ . '/includes/header.php';
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="theme-color" content="#117A65">
    <title>উত্তোলন — <?= clean($somityName) ?></title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Feather Icons -->
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    
    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #117A65;
            --primary-dark: #0E6655;
            --bg-color: #F0F4F8;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Hind Siliguri', sans-serif;
            background: var(--bg-color);
            color: #1C2833;
            padding-bottom: 70px;
        }
        
        .custom-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 16px 20px;
            position: relative;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 12px rgba(0,0,0,0.15);
        }
        
        .custom-header .header-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            max-width: 700px;
            margin: 0 auto;
        }
        
        .custom-header .header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .custom-header .header-left a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        
        .custom-header .header-left a:hover {
            opacity: 0.8;
        }
        
        .custom-header .header-title {
            font-size: 16px;
            font-weight: 600;
        }
        
       
        
        .main {
            padding: 14px;
            max-width: 700px;
            margin: 0 auto;
        }
        
        .alert {
            border-radius: 12px;
            font-size: 13px;
            padding: 12px 16px;
            margin-bottom: 14px;
            border: none;
        }
        
        .alert-success {
            background: #D5F5E3;
            color: #1E8449;
        }
        
        .alert-danger {
            background: #FDEDEC;
            color: #C0392B;
        }
        
        .alert-warning {
            background: #FEF9E7;
            color: #B7950B;
        }
        
        .alert-info {
            background: #D6EAF8;
            color: #1A5276;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 16px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 12px;
            text-align: center;
            transition: all 0.3s;
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
        
        .stat-card .value {
            font-size: 18px;
            font-weight: 700;
            color: #1C2833;
        }
        
        .stat-card .label {
            font-size: 11px;
            color: #7f8c8d;
        }
        
        .card {
            border: none;
            border-radius: 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            margin-bottom: 14px;
            overflow: hidden;
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
        
        /* ============================================
           TOGGLE BUTTON
           ============================================ */
        .toggle-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 14px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-family: 'Hind Siliguri', sans-serif;
            margin-bottom: 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .toggle-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(17, 122, 101, 0.3);
        }
        
        .toggle-btn .icon {
            transition: transform 0.3s;
        }
        
        .toggle-btn.active .icon {
            transform: rotate(45deg);
        }
        
        /* ============================================
           REQUEST FORM (HIDDEN BY DEFAULT)
           ============================================ */
        .request-form {
            background: white;
            border-radius: 14px;
            padding: 0;
            margin-bottom: 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            max-height: 0;
            overflow: hidden;
            transition: all 0.4s ease-in-out;
            opacity: 0;
        }
        
        .request-form.show {
            max-height: 800px;
            padding: 16px;
            opacity: 1;
        }
        
        .request-form .form-group {
            margin-bottom: 12px;
        }
        
        .request-form .form-group label {
            font-size: 13px;
            font-weight: 500;
            color: #555;
            display: block;
            margin-bottom: 4px;
        }
        
        .request-form .form-group input,
        .request-form .form-group select,
        .request-form .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 13px;
            font-family: 'Hind Siliguri', sans-serif;
            outline: none;
            transition: all 0.3s;
        }
        
        .request-form .form-group input:focus,
        .request-form .form-group select:focus,
        .request-form .form-group textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(17, 122, 101, 0.1);
        }
        
        .request-form .form-group .balance-info {
            font-size: 12px;
            color: #888;
            margin-top: 4px;
        }
        
        .request-form .btn-submit {
            width: 100%;
            padding: 10px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Hind Siliguri', sans-serif;
        }
        
        .request-form .btn-submit:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(17, 122, 101, 0.3);
        }
        
        .request-form .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .filter-section {
            background: white;
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        
        .filter-section .filter-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-section .filter-row select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 13px;
            font-family: 'Hind Siliguri', sans-serif;
            background: white;
            outline: none;
            cursor: pointer;
            flex: 1;
            min-width: 120px;
        }
        
        .filter-section .filter-row select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(17, 122, 101, 0.1);
        }
        
        .filter-section .filter-row .btn-filter {
            padding: 8px 16px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Hind Siliguri', sans-serif;
        }
        
        .filter-section .filter-row .btn-filter:hover {
            background: var(--primary-dark);
        }
        
        .filter-section .filter-row .btn-reset {
            padding: 8px 16px;
            background: #f0f0f0;
            color: #555;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Hind Siliguri', sans-serif;
            text-decoration: none;
        }
        
        .filter-section .filter-row .btn-reset:hover {
            background: #e0e0e0;
        }
        
        .withdrawal-item {
            padding: 14px 16px;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.2s;
        }
        
        .withdrawal-item:hover {
            background: #f8f9fa;
        }
        
        .withdrawal-item:last-child {
            border-bottom: none;
        }
        
        .withdrawal-item .wd-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .withdrawal-item .wd-info {
            flex: 1;
        }
        
        .withdrawal-item .wd-reference {
            font-size: 13px;
            font-weight: 600;
            color: #1C2833;
        }
        
        .withdrawal-item .wd-date {
            font-size: 12px;
            color: #888;
            margin-top: 2px;
        }
        
        .withdrawal-item .wd-details {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }
        
        .withdrawal-item .wd-amount {
            font-size: 16px;
            font-weight: 700;
            text-align: right;
        }
        
        .withdrawal-item .wd-amount.negative {
            color: #E74C3C;
        }
        
        .withdrawal-item .wd-status {
            margin-top: 6px;
        }
        
        .badge-status-pending {
            background: #FEF9E7;
            color: #B7950B;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .badge-status-completed {
            background: #D5F5E3;
            color: #1E8449;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .badge-status-cancelled {
            background: #FDEDEC;
            color: #C0392B;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .badge-status-rejected {
            background: #FDEDEC;
            color: #C0392B;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .empty-state {
            padding: 40px 20px;
            text-align: center;
            color: #999;
        }
        
        .empty-state svg {
            color: #ddd;
            margin-bottom: 12px;
        }
        
        .empty-state .title {
            font-size: 16px;
            font-weight: 600;
            color: #1C2833;
        }
        
        .empty-state .subtitle {
            font-size: 13px;
            color: #999;
            margin-top: 4px;
        }
        
        .pagination-wrap {
            display: flex;
            justify-content: center;
            gap: 6px;
            padding: 12px 0;
            flex-wrap: wrap;
        }
        
        .pagination-wrap a {
            padding: 6px 14px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            color: #555;
            text-decoration: none;
            font-size: 13px;
            transition: all 0.3s;
        }
        
        .pagination-wrap a:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .pagination-wrap a.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .pagination-wrap a.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .pagination-wrap a.disabled:hover {
            background: white;
            color: #555;
            border-color: #ddd;
        }
        
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-top: 1px solid #ddd;
            display: flex;
            z-index: 1000;
            box-shadow: 0 -2px 12px rgba(0,0,0,0.06);
        }
        
        .bottom-nav a {
            flex: 1;
            padding: 10px 0;
            text-align: center;
            color: #888;
            text-decoration: none;
            font-size: 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
            position: relative;
            transition: all 0.3s;
        }
        
        .bottom-nav a.active {
            color: var(--primary);
        }
        
        .bottom-nav a:hover {
            color: var(--primary);
        }
        
        .bottom-nav a svg {
            width: 20px;
            height: 20px;
        }
        
        .bottom-nav .nav-badge {
            position: absolute;
            top: 2px;
            right: 50%;
            transform: translateX(16px);
            background: #E74C3C;
            color: white;
            border-radius: 50%;
            padding: 1px 6px;
            font-size: 8px;
            font-weight: bold;
            min-width: 16px;
            text-align: center;
        }
        
        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
            }
            
            .filter-section .filter-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-section .filter-row select {
                min-width: auto;
            }
            
            .withdrawal-item .wd-header {
                flex-direction: column;
            }
            
            .withdrawal-item .wd-amount {
                text-align: left;
                width: 100%;
            }
            
            .custom-header {
                padding: 12px 16px;
            }
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .card, .filter-section {
            animation: fadeInUp 0.5s ease-out;
        }
    </style>
</head>
<body>

<!-- ============================================
     CUSTOM HEADER
     ============================================ -->
<header class="custom-header">
    <div class="header-inner">
        <div class="header-left">
            <a href="/member/index.php">
                <i data-feather="arrow-left" style="width:22px;height:22px"></i>
            </a>
            <span class="header-title">উত্তোলন</span>
        </div> 
    </div>
</header>

<!-- ============================================
     MAIN CONTENT
     ============================================ -->
<div class="main">

    <!-- Flash Messages -->
    <?php $flash = getFlash(); if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
        <?= clean($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

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
        <div class="stat-card">
            <div class="icon purple"><i data-feather="clock" style="width:18px;height:18px"></i></div>
            <div class="value"><?= $pendingCount ?></div>
            <div class="label">pending অনুরোধ</div>
        </div>
    </div>

    <!-- ============================================
         TOGGLE BUTTON - "নতুন উত্তোলন অনুরোধ"
         ============================================ -->
    <button class="toggle-btn" id="toggleFormBtn" onclick="toggleForm()">
        <i data-feather="plus-circle" class="icon" style="width:20px;height:20px"></i>
        <span>নতুন উত্তোলন অনুরোধ</span>
        <i data-feather="chevron-down" style="width:18px;height:18px;transition:transform 0.3s" id="arrowIcon"></i>
    </button>

    <!-- ============================================
         REQUEST WITHDRAWAL FORM (HIDDEN)
         ============================================ -->
    <div class="request-form" id="requestForm">
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="request">
            
            <div class="form-group">
                <label>পরিমাণ (৳) <span class="text-danger">*</span></label>
                <input type="number" name="amount" required step="0.01" min="100" 
                       placeholder="উত্তোলন পরিমাণ" max="<?= $balance ?>">
                <div class="balance-info">উপলব্ধ ব্যালেন্স: <?= formatCurrency($balance) ?></div>
            </div>
            
            <div class="form-group">
                <label>উত্তোলনের ধরন</label>
                <select name="withdrawal_type" class="form-control">
                    <option value="regular">নিয়মিত</option>
                    <option value="special">বিশেষ</option>
                    <option value="emergency">জরুরী</option>
                    <option value="partial">আংশিক</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>কারণ (ঐচ্ছিক)</label>
                <input type="text" name="reason" placeholder="উত্তোলনের কারণ">
            </div>
            
            <div class="form-group">
                <label>মন্তব্য (ঐচ্ছিক)</label>
                <textarea name="note" rows="2" placeholder="বিস্তারিত"></textarea>
            </div>
            
            <button type="submit" class="btn-submit" <?= $balance < 100 ? 'disabled' : '' ?>>
                <i data-feather="send" style="width:14px;height:14px"></i>
                উত্তোলন অনুরোধ করুন
            </button>
            
            <?php if ($balance < 100): ?>
            <div style="text-align:center;margin-top:8px;font-size:12px;color:#E74C3C;">
                <i data-feather="alert-circle" style="width:12px;height:12px"></i>
                উত্তোলনের জন্য ন্যূনতম ৳১০০ ব্যালেন্স প্রয়োজন
            </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- ============================================
         FILTER SECTION
         ============================================ -->
    <div class="filter-section">
        <form method="GET" class="filter-row">
            <select name="filter">
                <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>সব</option>
                <option value="pending" <?= $filter === 'pending' ? 'selected' : '' ?>>pending</option>
                <option value="completed" <?= $filter === 'completed' ? 'selected' : '' ?>>সম্পন্ন</option>
                <option value="cancelled" <?= $filter === 'cancelled' ? 'selected' : '' ?>>বাতিল</option>
                <option value="rejected" <?= $filter === 'rejected' ? 'selected' : '' ?>>প্রত্যাখ্যাত</option>
            </select>
            <button type="submit" class="btn-filter">ফিল্টার</button>
            <?php if ($filter !== 'all'): ?>
            <a href="withdrawals.php" class="btn-reset">রিসেট</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- ============================================
         WITHDRAWAL HISTORY
         ============================================ -->
    <div class="card">
        <div class="card-header">
            <span><i data-feather="list" style="width:14px;height:14px"></i> উত্তোলনের ইতিহাস</span>
            <span style="font-size:12px;color:#888;"><?= $totalRecords ?> টি</span>
        </div>
        
        <?php if (empty($withdrawals)): ?>
        <div class="empty-state">
            <i data-feather="inbox" style="width:48px;height:48px;display:block;margin:0 auto"></i>
            <div class="title">কোনো উত্তোলন নেই</div>
            <div class="subtitle">আপনার উত্তোলন অনুরোধ এখানে দেখাবে</div>
        </div>
        <?php else: ?>
        <?php foreach ($withdrawals as $wd): ?>
        <div class="withdrawal-item">
            <div class="wd-header">
                <div class="wd-info">
                    <div class="wd-reference">
                        <?= !empty($wd['receipt_no']) ? clean($wd['receipt_no']) : 'N/A' ?>
                    </div>
                    <div class="wd-date">
                        <i data-feather="calendar" style="width:12px;height:12px"></i>
                        <?= !empty($wd['created_at']) ? formatDateTime($wd['created_at']) : 
                           (!empty($wd['withdrawal_date']) ? formatDate($wd['withdrawal_date']) : '-') ?>
                    </div>
                    <div class="wd-details">
                        <i data-feather="tag" style="width:12px;height:12px"></i>
                        ধরন: <?= getWithdrawalTypeLabel($wd['withdrawal_type'] ?? 'regular') ?>
                    </div>
                    <?php if (!empty($wd['reason'])): ?>
                    <div class="wd-details" style="color:#888;">
                        <i data-feather="info" style="width:12px;height:12px"></i>
                        কারণ: <?= clean($wd['reason']) ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($wd['note'])): ?>
                    <div class="wd-details" style="color:#888;">
                        <i data-feather="file-text" style="width:12px;height:12px"></i>
                        <?= clean($wd['note']) ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($wd['approved_by']) && !empty($wd['approved_at'])): ?>
                    <div class="wd-details" style="color:#888;font-size:11px;">
                        <i data-feather="user-check" style="width:12px;height:12px"></i>
                        অনুমোদন: <?= formatDateTime($wd['approved_at']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="wd-amount negative">
                        - <?= formatCurrency($wd['amount'] ?? 0) ?>
                    </div>
                    <div class="wd-status">
                        <?= getStatusBadge($wd['status'] ?? 'pending') ?>
                    </div>
                    <?php if (isset($wd['status']) && $wd['status'] === 'pending'): ?>
                    <form method="POST" style="margin-top:6px;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="withdrawal_id" value="<?= $wd['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger" 
                                onclick="return confirm('আপনি কি এই উত্তোলন অনুরোধ বাতিল করতে চান?')"
                                style="font-size:11px;padding:2px 10px;border-radius:6px;">
                            <i data-feather="x" style="width:12px;height:12px"></i>
                            বাতিল করুন
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ============================================
         PAGINATION
         ============================================ -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination-wrap">
        <?php if ($page > 1): ?>
        <a href="?page=<?= $page-1 ?>&filter=<?= $filter ?>">
            <i data-feather="chevron-left" style="width:14px;height:14px"></i>
        </a>
        <?php else: ?>
        <a class="disabled"><i data-feather="chevron-left" style="width:14px;height:14px"></i></a>
        <?php endif; ?>
        
        <?php 
        $start = max(1, $page - 2);
        $end = min($totalPages, $page + 2);
        for ($i = $start; $i <= $end; $i++): 
        ?>
        <a href="?page=<?= $i ?>&filter=<?= $filter ?>" 
           class="<?= $i === $page ? 'active' : '' ?>">
            <?= $i ?>
        </a>
        <?php endfor; ?>
        
        <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page+1 ?>&filter=<?= $filter ?>">
            <i data-feather="chevron-right" style="width:14px;height:14px"></i>
        </a>
        <?php else: ?>
        <a class="disabled"><i data-feather="chevron-right" style="width:14px;height:14px"></i></a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ============================================
     BOTTOM NAVIGATION
     ============================================ -->
<!-- <nav class="bottom-nav">
    <a href="/member/index.php">
        <i data-feather="home"></i>
        <span>হোম</span>
    </a>
    <a href="/member/deposits.php">
        <i data-feather="credit-card"></i>
        <span>সঞ্চয়</span>
    </a>
    <a href="/member/withdrawals.php" class="active">
        <i data-feather="arrow-up-circle"></i>
        <span>উত্তোলন</span>
        <?php if ($pendingCount > 0): ?>
        <span class="nav-badge"><?= $pendingCount ?></span>
        <?php endif; ?>
    </a>
    <a href="/member/dues.php">
        <i data-feather="calendar"></i>
        <span>চাঁদা</span>
    </a>
    <a href="/member/loans.php">
        <i data-feather="briefcase"></i>
        <span>ঋণ</span>
    </a>
</nav> -->

<!-- ============================================
     SCRIPTS
     ============================================ -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Replace Feather Icons
document.addEventListener('DOMContentLoaded', function() {
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
});

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);

// Auto-submit on filter change
document.querySelector('.filter-section select').addEventListener('change', function() {
    this.closest('form').submit();
});

// ============================================
// TOGGLE FORM FUNCTION
// ============================================
function toggleForm() {
    const form = document.getElementById('requestForm');
    const btn = document.getElementById('toggleFormBtn');
    const arrow = document.getElementById('arrowIcon');
    
    form.classList.toggle('show');
    btn.classList.toggle('active');
    
    if (form.classList.contains('show')) {
        arrow.style.transform = 'rotate(180deg)';
        // Scroll to form
        setTimeout(function() {
            form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 100);
    } else {
        arrow.style.transform = 'rotate(0deg)';
    }
}

// Check if form should be open (if there are errors)
<?php if ($flash && isset($flash['type']) && $flash['type'] === 'danger'): ?>
document.addEventListener('DOMContentLoaded', function() {
    // Open form if there was an error
    toggleForm();
});
<?php endif; ?>
</script>

<!-- ============================================
     INCLUDE FOOTER (Bottom Nav)
     ============================================ -->
<?php require_once __DIR__ . '/includes/footer.php'; ?>

</body>
</html>