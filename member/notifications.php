<?php
// =====================================================
// member/notifications.php - Member Notifications
// =====================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
requireMember();

// Header Show
require_once __DIR__ . '/includes/header.php';

$member = getCurrentMember();
$mId = $member['id'];
$conn = conn();

// Get somity name
$somityName = getSetting('somity_name') ?: 'সোমিটি';

// ============================================
// HANDLE POST ACTIONS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'নিরাপত্তা যাচাই ব্যর্থ।');
        header('Location: notifications.php');
        exit;
    }
    
    $action = $_POST['action'] ?? '';
    
    // Mark single notification as read
    if ($action === 'mark_read') {
        $notifId = (int)($_POST['notification_id'] ?? 0);
        if ($notifId > 0) {
            $conn->query("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = $notifId AND (member_id = $mId OR target IN ('all', 'member'))");
            setFlash('success', 'নোটিফিকেশন পড়া হয়েছে।');
        }
        header('Location: notifications.php');
        exit;
    }
    
    // Mark all as read
    if ($action === 'mark_all_read') {
        $conn->query("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE (member_id = $mId OR target IN ('all', 'member')) AND is_read = 0");
        setFlash('success', 'সব নোটিফিকেশন পড়া হয়েছে।');
        header('Location: notifications.php');
        exit;
    }
    
    // Delete notification
    if ($action === 'delete') {
        $notifId = (int)($_POST['notification_id'] ?? 0);
        if ($notifId > 0) {
            $conn->query("DELETE FROM notifications WHERE id = $notifId AND (member_id = $mId OR target IN ('all', 'member'))");
            setFlash('success', 'নোটিফিকেশন ডিলিট করা হয়েছে।');
        }
        header('Location: notifications.php');
        exit;
    }
}

// ============================================
// GET NOTIFICATIONS WITH FILTERS
// ============================================
$filter = $_GET['filter'] ?? 'all';
$type_filter = $_GET['type'] ?? '';
$search = trim($_GET['s'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

// Build WHERE clause
$where = "((target='all' OR (target='member' AND member_id=$mId)) OR (target='admin' AND 1=0))";
$params = [];
$types = '';

if ($filter === 'unread') {
    $where .= " AND is_read = 0";
} elseif ($filter === 'read') {
    $where .= " AND is_read = 1";
}

if (!empty($type_filter)) {
    $where .= " AND type = ?";
    $types .= 's';
    $params[] = $type_filter;
}

if (!empty($search)) {
    $where .= " AND (title LIKE ? OR message LIKE ?)";
    $like = "%$search%";
    $types .= 'ss';
    $params[] = $like;
    $params[] = $like;
}

// Get total count
$countSql = "SELECT COUNT(*) as total FROM notifications WHERE $where";
$countResult = queryOne($countSql, $types, $params);
$totalRecords = $countResult['total'] ?? 0;
$totalPages = ceil($totalRecords / $perPage);
$offset = ($page - 1) * $perPage;

// Get notifications
$sql = "SELECT * FROM notifications WHERE $where ORDER BY created_at DESC LIMIT ? OFFSET ?";
$types .= 'ii';
$params[] = $perPage;
$params[] = $offset;

$notifications = queryAll($sql, $types, $params);
$notifications = $notifications ?: [];

// Get unread count
$unreadCount = queryOne(
    "SELECT COUNT(*) as count FROM notifications WHERE ((target='all' OR (target='member' AND member_id=$mId))) AND is_read = 0",
    '', []
)['count'] ?? 0;

// ============================================
// GET NOTIFICATION TYPES FOR FILTER
// ============================================
$typesList = queryAll(
    "SELECT DISTINCT type FROM notifications WHERE target='all' OR target='member' OR member_id=$mId ORDER BY type",
    '', []
);
$typesList = $typesList ?: [];

// ============================================
// HELPER FUNCTIONS
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

// Format currency without decimal if no poisha
function formatCurrencyClean($amount) {
    $amount = (float)$amount;
    $hasDecimal = ($amount - floor($amount)) > 0.0001;
    if ($hasDecimal) {
        return '৳' . number_format($amount, 2);
    } else {
        return '৳' . number_format($amount, 0);
    }
}

$avatar = getMemberAvatar($member);
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="theme-color" content="#117A65">
    <title>নোটিফিকেশন — <?= clean($somityName) ?></title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Feather Icons -->
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    
    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #117A65;
            --primary-dark: #0E6655;
            --primary-light: #D5F5E3;
            --primary-gradient: linear-gradient(135deg, #117A65, #0E6655);
            --bg-color: #F0F4F8;
            --card-shadow: 0 4px 20px rgba(0,0,0,0.06);
            --card-hover-shadow: 0 8px 30px rgba(17, 122, 101, 0.12);
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
            padding-bottom: 80px;
        }
        
        /* ============================================
           TOP BAR
           ============================================ */
        .topbar {
            background: var(--primary-gradient);
            color: white;
            padding: 16px 20px;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(17, 122, 101, 0.3);
            border-bottom: 3px solid #1ABC9C;
        }
        
        .topbar .topbar-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            max-width: 700px;
            margin: 0 auto;
        }
        
        .topbar .topbar-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .topbar .topbar-left a {
            color: white;
            text-decoration: none;
            transition: transform 0.3s;
            display: flex;
            align-items: center;
        }
        
        .topbar .topbar-left a:hover {
            transform: scale(1.1);
        }
        
        .topbar .topbar-title {
            font-size: 18px;
            font-weight: 700;
        }
        
        .topbar .topbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .topbar .topbar-right .notif-stat {
            background: rgba(255,255,255,0.15);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .topbar .topbar-right .btn-icon {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            transition: all 0.3s;
            padding: 4px;
            border-radius: 8px;
        }
        
        .topbar .topbar-right .btn-icon:hover {
            background: rgba(255,255,255,0.15);
            transform: scale(1.05);
        }
        
        /* ============================================
           MAIN CONTENT
           ============================================ */
        .main {
            padding: 16px;
            max-width: 700px;
            margin: 0 auto;
        }
        
        /* ============================================
           FLASH MESSAGES
           ============================================ */
        .alert-custom {
            border-radius: 12px;
            font-size: 13px;
            padding: 12px 16px;
            margin-bottom: 16px;
            border: none;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.4s ease;
        }
        
        .alert-custom.alert-success {
            background: #D5F5E3;
            color: #1E8449;
            border-left: 4px solid #1E8449;
        }
        
        .alert-custom.alert-danger {
            background: #FDEDEC;
            color: #C0392B;
            border-left: 4px solid #C0392B;
        }
        
        .alert-custom.alert-warning {
            background: #FEF9E7;
            color: #B7950B;
            border-left: 4px solid #B7950B;
        }
        
        .alert-custom.alert-info {
            background: #D6EAF8;
            color: #1A5276;
            border-left: 4px solid #1A5276;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* ============================================
           STATS CARDS
           ============================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 16px;
        }
        
        .stat-mini {
            background: white;
            border-radius: 12px;
            padding: 12px 14px;
            text-align: center;
            box-shadow: var(--card-shadow);
            transition: all 0.3s;
        }
        
        .stat-mini:hover {
            transform: translateY(-2px);
            box-shadow: var(--card-hover-shadow);
        }
        
        .stat-mini .stat-number {
            font-size: 20px;
            font-weight: 800;
            color: var(--primary);
        }
        
        .stat-mini .stat-label {
            font-size: 10px;
            color: #888;
            margin-top: 2px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-mini .stat-icon {
            font-size: 18px;
            display: block;
            margin-bottom: 2px;
        }
        
        .stat-mini.unread .stat-number { color: #E74C3C; }
        .stat-mini.total .stat-number { color: var(--primary); }
        .stat-mini.read .stat-number { color: #2E86C1; }
        
        /* ============================================
           FILTER SECTION
           ============================================ */
        .filter-section {
            background: white;
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 16px;
            box-shadow: var(--card-shadow);
        }
        
        .filter-section .filter-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-section .filter-row .search-box {
            flex: 1;
            min-width: 150px;
            position: relative;
        }
        
        .filter-section .filter-row .search-box input {
            width: 100%;
            padding: 9px 12px 9px 36px;
            border: 1.5px solid #e5e7eb;
            border-radius: 10px;
            font-size: 13px;
            font-family: 'Hind Siliguri', sans-serif;
            outline: none;
            transition: all 0.3s;
            background: #f8f9fa;
        }
        
        .filter-section .filter-row .search-box input:focus {
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(17, 122, 101, 0.1);
        }
        
        .filter-section .filter-row .search-box .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }
        
        .filter-section .filter-row select {
            padding: 9px 12px;
            border: 1.5px solid #e5e7eb;
            border-radius: 10px;
            font-size: 13px;
            font-family: 'Hind Siliguri', sans-serif;
            background: #f8f9fa;
            outline: none;
            cursor: pointer;
            min-width: 100px;
            transition: all 0.3s;
        }
        
        .filter-section .filter-row select:focus {
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(17, 122, 101, 0.1);
        }
        
        .filter-section .filter-row .btn-filter {
            padding: 9px 18px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Hind Siliguri', sans-serif;
        }
        
        .filter-section .filter-row .btn-filter:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(17, 122, 101, 0.3);
        }
        
        .filter-section .filter-row .btn-reset {
            padding: 9px 16px;
            background: #f0f0f0;
            color: #555;
            border: 1.5px solid #e5e7eb;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Hind Siliguri', sans-serif;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .filter-section .filter-row .btn-reset:hover {
            background: #e0e0e0;
            border-color: #ccc;
        }
        
        /* ============================================
           NOTIFICATION CARDS
           ============================================ */
        .notification-card {
            background: white;
            border-radius: 14px;
            padding: 16px 18px;
            margin-bottom: 10px;
            box-shadow: var(--card-shadow);
            transition: all 0.3s;
            position: relative;
            border-left: 4px solid transparent;
            animation: fadeInUp 0.4s ease forwards;
            opacity: 0;
        }
        
        .notification-card:hover {
            box-shadow: var(--card-hover-shadow);
            transform: translateY(-2px);
        }
        
        .notification-card.unread {
            border-left-color: var(--primary);
            background: linear-gradient(to right, #f8fffe, white);
        }
        
        .notification-card .notif-top {
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }
        
        .notification-card .notif-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 18px;
        }
        
        .notification-card .notif-icon.deposit { background: #D5F5E3; color: #1E8449; }
        .notification-card .notif-icon.withdrawal { background: #FDEDEC; color: #C0392B; }
        .notification-card .notif-icon.announcement { background: #F5EEF8; color: #7D3C98; }
        .notification-card .notif-icon.due_reminder { background: #FEF9E7; color: #B7950B; }
        .notification-card .notif-icon.loan_installment { background: #D6EAF8; color: #1A5276; }
        .notification-card .notif-icon.profit_distribution { background: #E8F8F5; color: #1ABC9C; }
        .notification-card .notif-icon.urgent { background: #FDEDEC; color: #C0392B; }
        .notification-card .notif-icon.general { background: #EBF5FB; color: #2E86C1; }
        .notification-card .notif-icon.system { background: #F4F6F7; color: #5D6D7E; }
        
        .notification-card .notif-body {
            flex: 1;
            min-width: 0;
        }
        
        .notification-card .notif-title {
            font-size: 15px;
            font-weight: 600;
            color: #1C2833;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .notification-card .notif-title .badge-new {
            background: #E74C3C;
            color: white;
            font-size: 8px;
            padding: 1px 8px;
            border-radius: 10px;
            font-weight: 600;
            animation: pulse-badge 2s infinite;
        }
        
        @keyframes pulse-badge {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .notification-card .notif-message {
            font-size: 13px;
            color: #555;
            line-height: 1.6;
        }
        
        .notification-card .notif-message .highlight {
            color: var(--primary);
            font-weight: 600;
        }
        
        .notification-card .notif-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #f0f0f0;
            flex-wrap: wrap;
            gap: 6px;
        }
        
        .notification-card .notif-time {
            font-size: 11px;
            color: #999;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .notification-card .notif-badges {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        
        .notification-card .notif-badges .badge-tag {
            font-size: 9px;
            padding: 2px 10px;
            border-radius: 12px;
            font-weight: 500;
        }
        
        .badge-tag.type {
            background: #f0f0f0;
            color: #555;
        }
        
        .badge-tag.priority-low { background: #D5F5E3; color: #1E8449; }
        .badge-tag.priority-normal { background: #EBF5FB; color: #2E86C1; }
        .badge-tag.priority-high { background: #FEF9E7; color: #B7950B; }
        .badge-tag.priority-urgent { background: #FDEDEC; color: #C0392B; }
        
        .badge-tag.status-unread {
            background: var(--primary);
            color: white;
        }
        
        .badge-tag.status-read {
            background: #f0f0f0;
            color: #888;
        }
        
        .notification-card .notif-actions {
            display: flex;
            gap: 4px;
        }
        
        .notification-card .notif-actions button {
            background: none;
            border: none;
            padding: 4px 8px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            color: #999;
            font-size: 14px;
        }
        
        .notification-card .notif-actions button:hover {
            background: #f0f0f0;
            color: var(--primary);
        }
        
        .notification-card .notif-actions button.btn-delete:hover {
            background: #FDEDEC;
            color: #C0392B;
        }
        
        /* ============================================
           EMPTY STATE
           ============================================ */
        .empty-state {
            background: white;
            border-radius: 14px;
            padding: 50px 20px;
            text-align: center;
            box-shadow: var(--card-shadow);
        }
        
        .empty-state .empty-icon {
            width: 80px;
            height: 80px;
            background: #f0f4f8;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            color: #ccc;
        }
        
        .empty-state .empty-icon svg {
            width: 40px;
            height: 40px;
        }
        
        .empty-state .empty-title {
            font-size: 18px;
            font-weight: 700;
            color: #1C2833;
        }
        
        .empty-state .empty-subtitle {
            font-size: 13px;
            color: #999;
            margin-top: 4px;
        }
        
        /* ============================================
           PAGINATION
           ============================================ */
        .pagination-wrap {
            display: flex;
            justify-content: center;
            gap: 4px;
            margin-top: 16px;
            flex-wrap: wrap;
        }
        
        .pagination-wrap a,
        .pagination-wrap .page-info {
            padding: 8px 14px;
            background: white;
            border: 1.5px solid #e5e7eb;
            border-radius: 8px;
            color: #555;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s;
            font-family: 'Hind Siliguri', sans-serif;
        }
        
        .pagination-wrap a:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            transform: translateY(-1px);
        }
        
        .pagination-wrap a.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .pagination-wrap a.disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        
        .pagination-wrap a.disabled:hover {
            background: white;
            color: #555;
            border-color: #e5e7eb;
            transform: none;
        }
        
        .pagination-wrap .page-info {
            background: transparent;
            border: none;
            color: #999;
            font-size: 12px;
        }
        
        /* ============================================
           QUICK COMPOSE BUTTON
           ============================================ */
        .quick-compose {
            position: fixed;
            bottom: 90px;
            right: 20px;
            z-index: 50;
        }
        
        .quick-compose a {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--primary-gradient);
            color: white;
            padding: 12px 20px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            box-shadow: 0 8px 30px rgba(17, 122, 101, 0.35);
            transition: all 0.3s;
            font-family: 'Hind Siliguri', sans-serif;
        }
        
        .quick-compose a:hover {
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 12px 40px rgba(17, 122, 101, 0.45);
            color: white;
        }
        
        @media (max-width: 480px) {
            .quick-compose a {
                padding: 10px 16px;
                font-size: 12px;
            }
        }
        
        /* ============================================
           RESPONSIVE
           ============================================ */
        @media (max-width: 576px) {
            .filter-section .filter-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-section .filter-row .search-box {
                min-width: auto;
            }
            
            .filter-section .filter-row select {
                min-width: auto;
            }
            
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 6px;
            }
            
            .stat-mini {
                padding: 10px;
            }
            
            .stat-mini .stat-number {
                font-size: 16px;
            }
            
            .stat-mini .stat-label {
                font-size: 8px;
            }
            
            .notification-card {
                padding: 14px;
            }
            
            .notification-card .notif-top {
                gap: 10px;
            }
            
            .notification-card .notif-icon {
                width: 36px;
                height: 36px;
                font-size: 14px;
            }
            
            .notification-card .notif-title {
                font-size: 13px;
            }
            
            .notification-card .notif-message {
                font-size: 12px;
            }
            
            .topbar {
                padding: 12px 16px;
            }
            
            .topbar .topbar-title {
                font-size: 15px;
            }
            
            .topbar .topbar-right .notif-stat {
                font-size: 10px;
                padding: 2px 10px;
            }
        }
        
        @media (max-width: 400px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr 1fr;
                gap: 4px;
            }
            
            .stat-mini {
                padding: 8px 6px;
            }
            
            .stat-mini .stat-number {
                font-size: 14px;
            }
        }
        
        /* ============================================
           ANIMATIONS
           ============================================ */
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
        
        .notification-card:nth-child(1) { animation-delay: 0.05s; }
        .notification-card:nth-child(2) { animation-delay: 0.1s; }
        .notification-card:nth-child(3) { animation-delay: 0.15s; }
        .notification-card:nth-child(4) { animation-delay: 0.2s; }
        .notification-card:nth-child(5) { animation-delay: 0.25s; }
        .notification-card:nth-child(6) { animation-delay: 0.3s; }
        .notification-card:nth-child(7) { animation-delay: 0.35s; }
        .notification-card:nth-child(8) { animation-delay: 0.4s; }
        .notification-card:nth-child(9) { animation-delay: 0.45s; }
        .notification-card:nth-child(10) { animation-delay: 0.5s; }
        
        /* ============================================
           SCROLLBAR
           ============================================ */
        ::-webkit-scrollbar {
            width: 4px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 2px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }
    </style>
</head>
<body>

<!-- ============================================
     TOP BAR
     ============================================ -->
<header class="topbar">
    <div class="topbar-inner">
        <div class="topbar-left">
            <a href="/member/index.php">
                <i data-feather="arrow-left" style="width:22px;height:22px"></i>
            </a>
            <span class="topbar-title">নোটিফিকেশন</span>
        </div>
        <div class="topbar-right">
            <span class="notif-stat">
                <i data-feather="bell" style="width:14px;height:14px"></i>
                <?= $unreadCount ?> অপঠিত
            </span>
            <?php if ($unreadCount > 0): ?>
            <form method="POST" style="display:inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="btn-icon" title="সব পড়া">
                    <i data-feather="check-circle" style="width:20px;height:20px"></i>
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- ============================================
     MAIN CONTENT
     ============================================ -->
<div class="main">

    <!-- Flash Messages -->
    <?php $flash = getFlash(); if ($flash): ?>
    <div class="alert-custom alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
        <i data-feather="<?= $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'danger' ? 'alert-circle' : 'info') ?>" style="width:18px;height:18px"></i>
        <?= clean($flash['message']) ?>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <!-- ============================================
         STATS CARDS
         ============================================ -->
    <div class="stats-grid">
        <div class="stat-mini total">
            <span class="stat-icon">📬</span>
            <div class="stat-number"><?= number_format($totalRecords) ?></div>
            <div class="stat-label">মোট</div>
        </div>
        <div class="stat-mini unread">
            <span class="stat-icon">🔴</span>
            <div class="stat-number"><?= number_format($unreadCount) ?></div>
            <div class="stat-label">অপঠিত</div>
        </div>
        <div class="stat-mini read">
            <span class="stat-icon">✅</span>
            <div class="stat-number"><?= number_format($totalRecords - $unreadCount) ?></div>
            <div class="stat-label">পঠিত</div>
        </div>
    </div>

    <!-- ============================================
         FILTER SECTION
         ============================================ -->
    <div class="filter-section">
        <form method="GET" class="filter-row">
            <div class="search-box">
                <i data-feather="search" class="search-icon" style="width:16px;height:16px"></i>
                <input type="text" name="s" placeholder="খুঁজুন (শিরোনাম বা বার্তা)..." value="<?= htmlspecialchars($search) ?>">
            </div>
            
            <select name="filter">
                <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>সব</option>
                <option value="unread" <?= $filter === 'unread' ? 'selected' : '' ?>>অপঠিত</option>
                <option value="read" <?= $filter === 'read' ? 'selected' : '' ?>>পঠিত</option>
            </select>
            
            <select name="type">
                <option value="">সব ধরন</option>
                <?php foreach ($typesList as $t): ?>
                <option value="<?= $t['type'] ?>" <?= $type_filter === $t['type'] ? 'selected' : '' ?>>
                    <?= ucfirst(str_replace('_', ' ', $t['type'])) ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <button type="submit" class="btn-filter">
                <i data-feather="filter" style="width:14px;height:14px"></i> ফিল্টার
            </button>
            
            <?php if ($search || $filter !== 'all' || $type_filter): ?>
            <a href="notifications.php" class="btn-reset">
                <i data-feather="x" style="width:14px;height:14px"></i> রিসেট
            </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- ============================================
         NOTIFICATIONS LIST
         ============================================ -->
    <?php if (empty($notifications)): ?>
    <div class="empty-state">
        <div class="empty-icon">
            <i data-feather="bell-off"></i>
        </div>
        <div class="empty-title">কোনো নোটিফিকেশন নেই</div>
        <div class="empty-subtitle">আপনার সব নোটিফিকেশন এখানে দেখাবে</div>
    </div>
    <?php else: ?>
    
    <?php foreach ($notifications as $index => $n): 
        // Icon mapping
        $typeIcons = [
            'deposit' => 'arrow-down-circle',
            'withdrawal' => 'arrow-up-circle',
            'announcement' => 'megaphone',
            'due_reminder' => 'calendar',
            'loan_installment' => 'dollar-sign',
            'profit_distribution' => 'pie-chart',
            'urgent' => 'alert-circle',
            'general' => 'bell',
            'system' => 'settings'
        ];
        $icon = $typeIcons[$n['type']] ?? 'bell';
        
        // Priority class
        $priorityClass = [
            'low' => 'badge-tag priority-low',
            'normal' => 'badge-tag priority-normal',
            'high' => 'badge-tag priority-high',
            'urgent' => 'badge-tag priority-urgent'
        ][$n['priority'] ?? 'normal'] ?? 'badge-tag priority-normal';
        
        // Type labels
        $typeLabels = [
            'deposit' => 'সঞ্চয়',
            'withdrawal' => 'উত্তোলন',
            'announcement' => 'ঘোষণা',
            'due_reminder' => 'বকেয়া স্মারক',
            'loan_installment' => 'কিস্তি',
            'profit_distribution' => 'লাভ বণ্টন',
            'urgent' => 'জরুরী',
            'general' => 'সাধারণ',
            'system' => 'সিস্টেম'
        ];
        $typeLabel = $typeLabels[$n['type']] ?? $n['type'];
    ?>
    <div class="notification-card <?= !$n['is_read'] ? 'unread' : '' ?>">
        <div class="notif-top">
            <div class="notif-icon <?= $n['type'] ?>">
                <i data-feather="<?= $icon ?>" style="width:20px;height:20px"></i>
            </div>
            <div class="notif-body">
                <div class="notif-title">
                    <?= clean($n['title']) ?>
                    <?php if (!$n['is_read']): ?>
                    <span class="badge-new">নতুন</span>
                    <?php endif; ?>
                </div>
                <div class="notif-message">
                    <?= clean($n['message']) ?>
                </div>
                <div class="notif-bottom">
                    <div class="notif-time">
                        <i data-feather="clock" style="width:12px;height:12px"></i>
                        <?= timeAgo($n['created_at']) ?>
                        <?php if ($n['read_at']): ?>
                        • পড়া হয়েছে: <?= timeAgo($n['read_at']) ?>
                        <?php endif; ?>
                    </div>
                    <div class="notif-badges">
                        <span class="badge-tag type"><?= $typeLabel ?></span>
                        <?php if (isset($n['priority']) && $n['priority'] !== 'normal'): ?>
                        <span class="badge-tag <?= $priorityClass ?>">
                            <?= ucfirst($n['priority']) ?>
                        </span>
                        <?php endif; ?>
                        <span class="badge-tag <?= $n['is_read'] ? 'status-read' : 'status-unread' ?>">
                            <?= $n['is_read'] ? 'পঠিত' : 'অপঠিত' ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="notif-actions">
                <?php if (!$n['is_read']): ?>
                <form method="POST" style="display:inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="mark_read">
                    <input type="hidden" name="notification_id" value="<?= $n['id'] ?>">
                    <button type="submit" title="পড়া হয়েছে">
                        <i data-feather="check" style="width:16px;height:16px"></i>
                    </button>
                </form>
                <?php endif; ?>
                <form method="POST" style="display:inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="notification_id" value="<?= $n['id'] ?>">
                    <button type="submit" class="btn-delete" title="ডিলিট" onclick="return confirm('আপনি কি এই নোটিফিকেশন ডিলিট করতে চান?')">
                        <i data-feather="trash-2" style="width:16px;height:16px"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <!-- ============================================
         PAGINATION
         ============================================ -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination-wrap">
        <?php if ($page > 1): ?>
        <a href="?page=<?= $page-1 ?>&s=<?= urlencode($search) ?>&filter=<?= $filter ?>&type=<?= $type_filter ?>">
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
        <a href="?page=<?= $i ?>&s=<?= urlencode($search) ?>&filter=<?= $filter ?>&type=<?= $type_filter ?>" 
           class="<?= $i === $page ? 'active' : '' ?>">
            <?= $i ?>
        </a>
        <?php endfor; ?>
        
        <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page+1 ?>&s=<?= urlencode($search) ?>&filter=<?= $filter ?>&type=<?= $type_filter ?>">
            <i data-feather="chevron-right" style="width:14px;height:14px"></i>
        </a>
        <?php else: ?>
        <a class="disabled"><i data-feather="chevron-right" style="width:14px;height:14px"></i></a>
        <?php endif; ?>
        
        <span class="page-info">পৃষ্ঠা <?= $page ?> / <?= $totalPages ?></span>
    </div>
    <?php endif; ?>
    
    <!-- Total count -->
    <div style="text-align:center;font-size:12px;color:#999;margin-top:12px;">
        মোট <?= number_format($totalRecords) ?> টি নোটিফিকেশন
    </div>
    
    <?php endif; ?>
</div>

<!-- ============================================
     QUICK COMPOSE BUTTON
     ============================================ -->
<div class="quick-compose">
    <a href="/member/index.php">
        <i data-feather="edit-3" style="width:18px;height:18px"></i>
        নতুন বার্তা
    </a>
</div>

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
    const alerts = document.querySelectorAll('.alert-custom');
    alerts.forEach(function(alert) {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);

// Auto-submit on filter change
document.querySelectorAll('.filter-section select').forEach(function(select) {
    select.addEventListener('change', function() {
        this.closest('form').submit();
    });
});

// Smooth scroll to top on page load
window.addEventListener('load', function() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
});
</script>

<!-- ============================================
     INCLUDE FOOTER (Bottom Nav)
     ============================================ -->
<?php require_once __DIR__ . '/includes/footer.php'; ?>

</body>
</html>