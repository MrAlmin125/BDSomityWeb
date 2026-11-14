<?php
// =====================================================
// search.php - Member Search System (Exact Match Only)
// =====================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'সদস্য অনুসন্ধান';
$searchResult = null;
$error = '';
$searchTerm = '';
$withdrawals = [];
$deposits = [];
$realBalance = 0;
$totalDeposit = 0;
$totalWithdrawn = 0;
$isAdmin = isset($_SESSION['admin_id']) && isset($_SESSION['admin_role']);

function getMemberBalance($memberId) {
    $totalDeposit = queryOne("SELECT COALESCE(SUM(amount),0) AS t FROM deposits WHERE member_id=?", 'i', [$memberId])['t'] ?? 0;
    $totalWithdrawn = queryOne("SELECT COALESCE(SUM(amount),0) AS t FROM withdrawals WHERE member_id=? AND status='completed'", 'i', [$memberId])['t'] ?? 0;
    return (float)$totalDeposit - (float)$totalWithdrawn;
}

// Handle search - EXACT MATCH ONLY
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $searchTerm = trim($_GET['search']);
    
    try {
        $conn = conn();
        
        $sql = "SELECT m.*, 
                       COALESCE(SUM(d.amount), 0) as total_deposit,
                       (SELECT COUNT(*) FROM loans WHERE member_id = m.id AND status = 'active') as active_loans,
                       (SELECT COUNT(*) FROM loans WHERE member_id = m.id) as total_loans,
                       (SELECT COUNT(*) FROM loans WHERE member_id = m.id AND status = 'completed') as completed_loans
                FROM members m
                LEFT JOIN deposits d ON d.member_id = m.id
                WHERE m.phone = ? OR m.member_id = ?
                GROUP BY m.id
                LIMIT 1";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $searchTerm, $searchTerm);
        $stmt->execute();
        $result = $stmt->get_result();
        $searchResult = $result->fetch_assoc();
        
        if ($searchResult) {
            $memberId = $searchResult['id'];
            
            $stmt = $conn->prepare("
                SELECT id, amount, deposit_date, deposit_type, receipt_no, note
                FROM deposits 
                WHERE member_id = ?
                ORDER BY deposit_date DESC, id DESC
                LIMIT 10
            ");
            $stmt->bind_param('i', $memberId);
            $stmt->execute();
            $result = $stmt->get_result();
            $deposits = $result->fetch_all(MYSQLI_ASSOC);
            
            $stmt = $conn->prepare("
                SELECT id, amount, withdrawal_date, withdrawal_type, reason, receipt_no, status, approved_at
                FROM withdrawals 
                WHERE member_id = ? AND status = 'completed'
                ORDER BY withdrawal_date DESC, id DESC
                LIMIT 10
            ");
            $stmt->bind_param('i', $memberId);
            $stmt->execute();
            $result = $stmt->get_result();
            $withdrawals = $result->fetch_all(MYSQLI_ASSOC);
            
            $totalDeposit = $searchResult['total_deposit'] ?? 0;
            $totalWithdrawn = array_sum(array_column($withdrawals, 'amount'));
            $realBalance = $totalDeposit - $totalWithdrawn;
        } else {
            $error = '❌ "' . htmlspecialchars($searchTerm) . '" দিয়ে কোনো সদস্য খুঁজে পাওয়া যায়নি। সঠিক ফোন নম্বর বা সদস্য আইডি দিন।';
        }
        
    } catch (Exception $e) {
        $error = 'অনুসন্ধান করতে ব্যর্থ হয়েছে: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>সদস্য অনুসন্ধান</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ============================================
           GLOBAL STYLES
           ============================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Hind Siliguri', sans-serif;
            min-height: 100vh;
            background: #0a0a0a;
            position: relative;
            overflow-x: hidden;
        }
        
        /* Animated Background */
        .bg-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            background: linear-gradient(-45deg, #0f0c29, #302b63, #24243e, #1a1a2e);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
        }
        
        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        /* Floating Particles */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            pointer-events: none;
            overflow: hidden;
        }
        
        .particle {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            animation: floatParticle linear infinite;
        }
        
        @keyframes floatParticle {
            0% {
                transform: translateY(100vh) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100vh) rotate(720deg);
                opacity: 0;
            }
        }
        
        .particle:nth-child(1) { width: 6px; height: 6px; left: 10%; animation-duration: 18s; animation-delay: 0s; }
        .particle:nth-child(2) { width: 12px; height: 12px; left: 25%; animation-duration: 22s; animation-delay: 2s; }
        .particle:nth-child(3) { width: 8px; height: 8px; left: 40%; animation-duration: 20s; animation-delay: 4s; }
        .particle:nth-child(4) { width: 15px; height: 15px; left: 55%; animation-duration: 25s; animation-delay: 1s; }
        .particle:nth-child(5) { width: 5px; height: 5px; left: 70%; animation-duration: 16s; animation-delay: 3s; }
        .particle:nth-child(6) { width: 10px; height: 10px; left: 85%; animation-duration: 19s; animation-delay: 5s; }
        .particle:nth-child(7) { width: 7px; height: 7px; left: 50%; animation-duration: 21s; animation-delay: 2.5s; }
        .particle:nth-child(8) { width: 14px; height: 14px; left: 15%; animation-duration: 23s; animation-delay: 4.5s; }
        
        .container {
            position: relative;
            z-index: 2;
            padding: 20px;
        }
        
        /* ============================================
           GLASS HEADER
           ============================================ */
        .glass-header {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 20px 30px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .glass-header .brand {
            display: flex;
            align-items: center;
            gap: 14px;
            color: white;
            text-decoration: none;
            position: relative;
            z-index: 1;
        }
        
        .glass-header .brand-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #2E86C1, #1B4F72);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 700;
            color: white;
            box-shadow: 0 4px 15px rgba(46, 134, 193, 0.3);
            animation: pulseGlow 3s ease-in-out infinite;
        }
        
        @keyframes pulseGlow {
            0%, 100% { box-shadow: 0 4px 15px rgba(46, 134, 193, 0.3); }
            50% { box-shadow: 0 4px 30px rgba(46, 134, 193, 0.6); }
        }
        
        .glass-header .brand-text {
            font-size: 22px;
            font-weight: 700;
            color: white;
            letter-spacing: 0.5px;
        }
        
        .glass-header .brand-sub {
            font-size: 12px;
            opacity: 0.7;
            font-weight: 400;
            display: block;
            background: linear-gradient(90deg, #f1c40f, #e67e22);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .glass-header .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            position: relative;
            z-index: 1;
        }
        
        .glass-header .header-btn {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: white;
            padding: 8px 18px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 6px;
            backdrop-filter: blur(10px);
        }
        
        .glass-header .header-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            border-color: rgba(255, 255, 255, 0.3);
        }
        
        /* ============================================
           MARQUEE TEXT
           ============================================ */
        .marquee-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 10px 0;
            margin-bottom: 25px;
            overflow: hidden;
            position: relative;
        }
        
        .marquee-content {
            display: flex;
            animation: marqueeScroll 25s linear infinite;
            white-space: nowrap;
        }
        
        @keyframes marqueeScroll {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
        
        .marquee-item {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 0 40px;
            color: rgba(255, 255, 255, 0.9);
            font-size: 14px;
            font-weight: 500;
        }
        
        .marquee-item .highlight {
            color: #f1c40f;
            font-weight: 700;
        }
        
        .marquee-item .emoji {
            font-size: 18px;
        }
        
        .marquee-item .separator {
            color: rgba(255, 255, 255, 0.2);
        }
        
        /* ============================================
           SEARCH SECTION
           ============================================ */
        .search-glass {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 35px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            transition: all 0.3s;
        }
        
        .search-title {
            color: white;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            text-align: center;
        }
        
        .search-title .gradient-text {
            background: linear-gradient(135deg, #f1c40f, #e67e22, #f1c40f);
            background-size: 200% 200%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: shimmer 3s ease-in-out infinite;
        }
        
        @keyframes shimmer {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .search-subtitle {
            color: rgba(255, 255, 255, 0.6);
            text-align: center;
            margin-bottom: 25px;
            font-size: 15px;
        }
        
        .search-hint-exact {
            color: rgba(255, 255, 255, 0.4);
            text-align: center;
            font-size: 13px;
            margin-top: 10px;
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .search-hint-exact i {
            color: #f1c40f;
            margin-right: 6px;
        }
        
        .search-hint-exact strong {
            color: rgba(255, 255, 255, 0.8);
        }
        
        .search-box {
            position: relative;
        }
        
        .search-box .search-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            width: 22px;
            height: 22px;
            color: rgba(255, 255, 255, 0.3);
            pointer-events: none;
            transition: all 0.3s;
        }
        
        .search-box input {
            padding-left: 50px;
            border-radius: 14px;
            border: 2px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.05);
            padding: 16px 20px;
            font-size: 16px;
            font-family: 'Hind Siliguri', sans-serif;
            transition: all 0.3s;
            height: 60px;
            color: white;
        }
        
        .search-box input:focus {
            border-color: #2E86C1;
            background: rgba(255, 255, 255, 0.08);
            box-shadow: 0 0 0 4px rgba(46, 134, 193, 0.15);
            outline: none;
        }
        
        .search-box input::placeholder {
            color: rgba(255, 255, 255, 0.4);
            font-size: 15px;
        }
        
        .btn-search {
            height: 60px;
            border-radius: 14px;
            font-size: 16px;
            font-weight: 600;
            background: linear-gradient(135deg, #2E86C1, #1B4F72);
            border: none;
            transition: all 0.3s;
            width: 100%;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .btn-search::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transform: rotate(45deg);
            animation: btnShine 3s infinite;
        }
        
        @keyframes btnShine {
            0% { transform: translateX(-100%) rotate(45deg); }
            100% { transform: translateX(100%) rotate(45deg); }
        }
        
        .btn-search:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(46, 134, 193, 0.4);
        }
        
        /* ============================================
           RESULT CARDS - DARK THEME
           ============================================ */
        .result-glass {
            background: rgba(20, 20, 30, 0.9);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 20px;
            overflow: hidden;
            margin-bottom: 25px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            animation: fadeInUp 0.6s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .result-glass .card-header-custom {
            padding: 16px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            background: rgba(255, 255, 255, 0.02);
        }
        
        .result-glass .card-header-custom .title {
            font-size: 16px;
            font-weight: 600;
            color: white;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .result-glass .card-header-custom .title i {
            color: #2E86C1;
        }
        
        .result-glass .card-header-custom .badge-custom {
            background: linear-gradient(135deg, #27AE60, #2ECC71);
            color: white;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .result-glass .card-body-custom {
            padding: 24px;
        }
        
        /* Profile */
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            font-weight: 700;
            margin: 0 auto 16px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
            border: 3px solid rgba(255, 255, 255, 0.15);
            transition: all 0.3s;
        }
        
        .profile-avatar:hover {
            transform: scale(1.05);
            border-color: rgba(255, 255, 255, 0.3);
        }
        
        .profile-name {
            font-size: 22px;
            font-weight: 700;
            color: white;
            margin-bottom: 4px;
        }
        
        .profile-id {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.5);
            margin-bottom: 12px;
        }
        
        .profile-id code {
            background: rgba(255, 255, 255, 0.08);
            color: rgba(255, 255, 255, 0.8);
            padding: 2px 10px;
            border-radius: 6px;
        }
        
        /* Balance Display */
        .balance-glass {
            padding: 16px 20px;
            border-radius: 14px;
            margin: 12px 0;
            transition: all 0.3s;
            border: 1px solid rgba(255, 255, 255, 0.06);
        }
        
        .balance-glass:hover {
            transform: scale(1.02);
        }
        
        .balance-glass .balance-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            opacity: 0.6;
        }
        
        .balance-glass .balance-amount {
            font-size: 32px;
            font-weight: 700;
        }
        
        .balance-glass .balance-detail {
            font-size: 13px;
            margin-top: 4px;
            opacity: 0.7;
        }
        
        /* Stat Boxes */
        .stat-glass {
            background: rgba(255, 255, 255, 0.04);
            border-radius: 12px;
            padding: 12px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.04);
            transition: all 0.3s;
        }
        
        .stat-glass:hover {
            background: rgba(255, 255, 255, 0.08);
            transform: translateY(-2px);
        }
        
        .stat-glass .stat-number {
            font-size: 20px;
            font-weight: 700;
            color: #2E86C1;
        }
        
        .stat-glass .stat-label {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.5);
            margin-top: 2px;
        }
        
        /* Info Cards */
        .info-glass {
            background: rgba(255, 255, 255, 0.02);
            border-radius: 14px;
            padding: 16px;
            height: 100%;
            border: 1px solid rgba(255, 255, 255, 0.04);
        }
        
        .info-glass .info-title {
            font-size: 14px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            padding-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-glass .info-title i {
            color: #2E86C1;
        }
        
        .info-glass .info-item {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 14px;
        }
        
        .info-glass .info-label {
            color: rgba(255, 255, 255, 0.5);
        }
        
        .info-glass .info-value {
            font-weight: 500;
            color: rgba(255, 255, 255, 0.9);
        }
        
        /* ============================================
           TABLES - FIXED DARK BACKGROUND
           ============================================ */
        .table-glass {
            margin-bottom: 0;
            color: rgba(255, 255, 255, 0.9);
            background: transparent !important;
        }
        
        .table-glass thead th {
            background: rgba(255, 255, 255, 0.04) !important;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.5);
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            padding: 12px 16px;
            letter-spacing: 0.5px;
        }
        
        .table-glass tbody td {
            padding: 12px 16px;
            vertical-align: middle;
            font-size: 14px;
            color: rgba(255, 255, 255, 0.85);
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            background: transparent !important;
        }
        
        .table-glass tbody tr {
            background: transparent !important;
        }
        
        .table-glass tbody tr:hover {
            background: rgba(255, 255, 255, 0.05) !important;
        }
        
        .table-glass tfoot td {
            padding: 12px 16px;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.03);
            color: white;
        }
        
        .table-glass code {
            background: rgba(255, 255, 255, 0.06);
            color: rgba(255, 255, 255, 0.7);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
        }
        
        /* ✅ FIXED: Table container background */
        .table-responsive {
            background: transparent !important;
        }
        
        /* ✅ FIXED: Card body for tables */
        .result-glass .card-body-custom {
            padding: 0;
        }
        
        .result-glass .card-body-custom .table-responsive {
            padding: 0 0 16px 0;
        }
        
        /* ============================================
           ALERT
           ============================================ */
        .alert-glass {
            border-radius: 14px;
            background: rgba(231, 76, 60, 0.12);
            border: 1px solid rgba(231, 76, 60, 0.2);
            color: #f1948a;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            backdrop-filter: blur(10px);
        }
        
        .alert-glass i {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }
        
        /* ============================================
           TIPS
           ============================================ */
        .tips-glass {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 16px;
            padding: 20px 24px;
            margin-top: 20px;
        }
        
        .tips-glass .tips-title {
            color: #f1c40f;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .tips-glass ul {
            color: rgba(255, 255, 255, 0.6);
            font-size: 13px;
            padding-left: 20px;
            margin-top: 8px;
        }
        
        .tips-glass ul li {
            margin-bottom: 4px;
        }
        
        .tips-glass ul li strong {
            color: rgba(255, 255, 255, 0.8);
        }
        
        /* Colors */
        .amount-positive {
            color: #2ECC71;
            font-weight: 600;
        }
        .amount-negative {
            color: #E74C3C;
            font-weight: 600;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 12px;
            }
            
            .glass-header {
                padding: 16px 20px;
                border-radius: 16px;
            }
            
            .glass-header .brand-text {
                font-size: 18px;
            }
            
            .glass-header .brand-icon {
                width: 42px;
                height: 42px;
                font-size: 18px;
            }
            
            .glass-header .header-btn span {
                display: none;
            }
            
            .search-glass {
                padding: 20px;
                border-radius: 16px;
            }
            
            .search-title {
                font-size: 22px;
            }
            
            .search-box input {
                height: 52px;
                font-size: 14px;
                padding-left: 44px;
            }
            
            .btn-search {
                height: 52px;
                font-size: 14px;
            }
            
            .profile-avatar {
                width: 80px;
                height: 80px;
                font-size: 28px;
            }
            
            .profile-name {
                font-size: 18px;
            }
            
            .balance-glass .balance-amount {
                font-size: 24px;
            }
            
            .result-glass .card-body-custom {
                padding: 16px;
            }
            
            .result-glass .card-header-custom {
                padding: 12px 16px;
            }
            
            .marquee-item {
                font-size: 12px;
                padding: 0 20px;
            }
            
            .search-hint-exact {
                font-size: 12px;
            }
            
            .table-glass thead th,
            .table-glass tbody td {
                padding: 8px 10px;
                font-size: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .glass-header .brand-text {
                font-size: 15px;
            }
            
            .glass-header .brand-sub {
                font-size: 10px;
            }
            
            .search-glass {
                padding: 16px;
            }
            
            .search-title {
                font-size: 18px;
            }
            
            .search-subtitle {
                font-size: 13px;
            }
            
            .balance-glass .balance-amount {
                font-size: 20px;
            }
            
            .marquee-item {
                font-size: 11px;
                padding: 0 14px;
            }
            
            .table-glass thead th,
            .table-glass tbody td {
                padding: 6px 8px;
                font-size: 11px;
            }
        }
    </style>
</head>
<body>

<!-- Animated Background -->
<div class="bg-animation"></div>

<!-- Floating Particles -->
<div class="particles">
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
</div>

<div class="container">

    <!-- ============================================
         GLASS HEADER
         ============================================ -->
    <header class="glass-header">
        <div class="row align-items-center">
            <div class="col-md-6">
                <a href="/" class="brand">
                    <div class="brand-icon">স</div>
                    <div>
                        <div class="brand-text">
                            <?= getSetting('somity_name') ?>
                            <span class="brand-sub">✨ সদস্য অনুসন্ধান</span>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-6 text-md-end mt-3 mt-md-0">
                <div class="header-actions justify-content-center justify-content-md-end">
                    <?php if ($isAdmin): ?>
                    <a href="/admin/dashboard.php" class="header-btn">
                        <i data-feather="grid"></i>
                        <span>ড্যাশবোর্ড</span>
                    </a>
                    <?php else: ?>
                    <a href="/" class="header-btn">
                        <i data-feather="home"></i>
                        <span>হোম</span>
                    </a>
                    <?php endif; ?>
                    <?php if (!$isAdmin): ?>
                    <a href="/admin/login.php" class="header-btn">
                        <i data-feather="log-in"></i>
                        <span>লগইন</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- ============================================
         MARQUEE TEXT
         ============================================ -->
    <div class="marquee-container">
        <div class="marquee-content">
            <span class="marquee-item">
                <span class="emoji">🌟</span>
                <span>স্বাগতম! সদস্য অনুসন্ধান সিস্টেম</span>
                <span class="separator">|</span>
                <span class="emoji">📱</span>
                <span>সম্পূর্ণ ফোন নম্বর দিয়ে খুঁজুন</span>
                <span class="separator">|</span>
                <span class="emoji">🆔</span>
                <span>সম্পূর্ণ সদস্য আইডি দিয়ে খুঁজুন</span>
                <span class="separator">|</span>
                <span class="emoji">💰</span>
                <span>রিয়েল টাইম <span class="highlight">ব্যালেন্স</span> দেখুন</span>
                <span class="separator">|</span>
                <span class="emoji">📊</span>
                <span>জমা ও উত্তোলনের <span class="highlight">ইতিহাস</span></span>
                <span class="separator">|</span>
                <span class="emoji">✨</span>
                <span>দ্রুত ও নির্ভুল <span class="highlight">অনুসন্ধান</span></span>
                <span class="separator">|</span>
                <span class="emoji">🔒</span>
                <span>নিরাপদ <span class="highlight">ডেটা</span></span>
                <span class="separator">|</span>
                <span class="emoji">🚀</span>
                <span>২৪/৭ <span class="highlight">সেবা</span></span>
                <span class="separator">|</span>
            </span>
            <span class="marquee-item">
                <span class="emoji">🌟</span>
                <span>স্বাগতম! সদস্য অনুসন্ধান সিস্টেম</span>
                <span class="separator">|</span>
                <span class="emoji">📱</span>
                <span>সম্পূর্ণ ফোন নম্বর দিয়ে খুঁজুন</span>
                <span class="separator">|</span>
                <span class="emoji">🆔</span>
                <span>সম্পূর্ণ সদস্য আইডি দিয়ে খুঁজুন</span>
                <span class="separator">|</span>
                <span class="emoji">💰</span>
                <span>রিয়েল টাইম <span class="highlight">ব্যালেন্স</span> দেখুন</span>
                <span class="separator">|</span>
                <span class="emoji">📊</span>
                <span>জমা ও উত্তোলনের <span class="highlight">ইতিহাস</span></span>
                <span class="separator">|</span>
                <span class="emoji">✨</span>
                <span>দ্রুত ও নির্ভুল <span class="highlight">অনুসন্ধান</span></span>
                <span class="separator">|</span>
                <span class="emoji">🔒</span>
                <span>নিরাপদ <span class="highlight">ডেটা</span></span>
                <span class="separator">|</span>
                <span class="emoji">🚀</span>
                <span>২৪/৭ <span class="highlight">সেবা</span></span>
                <span class="separator">|</span>
            </span>
        </div>
    </div>

    <!-- ============================================
         SEARCH SECTION
         ============================================ -->
    <div class="search-glass">
        <h2 class="search-title">
            🔍 <span class="gradient-text">সদস্য অনুসন্ধান</span>
        </h2>
        <p class="search-subtitle">
            সম্পূর্ণ ফোন নম্বর বা সদস্য আইডি দিয়ে খুঁজুন
        </p>
        
        <div class="search-hint-exact">
            <i data-feather="info"></i>
            <strong>সঠিক মিল প্রয়োজন:</strong> শুধুমাত্র সম্পূর্ণ ফোন নম্বর (যেমন: 017XXXXXXXX) বা সম্পূর্ণ সদস্য আইডি (যেমন: SM20240001) দিয়ে খুঁজুন।
            <span style="color:rgba(255,255,255,0.3);">|</span>
            <span style="color:#f1c40f;">আংশিক (01, 017, SM) দিয়ে খুঁজলে ফলাফল পাওয়া যাবে না।</span>
        </div>
        
        <form method="GET" class="row g-3 mt-3">
            <div class="col-md-9">
                <div class="search-box">
                    <i data-feather="search" class="search-icon"></i>
                    <input type="text" name="search" class="form-control" 
                           placeholder="সম্পূর্ণ ফোন নম্বর বা সদস্য আইডি দিন (যেমন: 017XXXXXXXX বা SM20240001)" 
                           value="<?= htmlspecialchars($searchTerm) ?>"
                           required>
                </div>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-search">
                    <i data-feather="search" style="width:18px;height:18px"></i>
                    খুঁজুন
                </button>
            </div>
        </form>
    </div>

    <!-- ============================================
         ERROR MESSAGE
         ============================================ -->
    <?php if ($error): ?>
    <div class="alert-glass">
        <i data-feather="alert-circle"></i>
        <span><?= htmlspecialchars($error) ?></span>
    </div>
    <?php endif; ?>

    <!-- ============================================
         SEARCH RESULT
         ============================================ -->
    <?php if ($searchResult): ?>
    <div class="result-glass">
        <div class="card-header-custom">
            <span class="title">
                <i data-feather="user"></i>
                সদস্য বিস্তারিত
            </span>
            <span class="badge-custom">✅ পাওয়া গেছে</span>
        </div>
        
        <div class="card-body-custom">
            <div class="row g-4">
                <!-- Profile Column -->
                <div class="col-md-4 text-center">
                    <div class="profile-avatar" style="background:<?= getColor($searchResult['name']) ?>">
                        <?= mb_substr($searchResult['name'], 0, 2) ?>
                    </div>
                    <h5 class="profile-name"><?= clean($searchResult['name']) ?></h5>
                    <p class="profile-id">
                        <code><?= clean($searchResult['member_id']) ?></code>
                        <span class="badge <?= $searchResult['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>">
                            <?= $searchResult['status'] === 'active' ? '✅ সক্রিয়' : '⛔ নিষ্ক্রিয়' ?>
                        </span>
                    </p>
                    
                    <!-- Balance -->
                    <div class="balance-glass" style="background: <?= $realBalance >= 0 ? 'rgba(46,204,113,0.15)' : 'rgba(231,76,60,0.15)' ?>; border-color: <?= $realBalance >= 0 ? 'rgba(46,204,113,0.2)' : 'rgba(231,76,60,0.2)' ?>;">
                        <div class="balance-label" style="color: <?= $realBalance >= 0 ? '#2ECC71' : '#E74C3C' ?>;">
                            <?= $realBalance >= 0 ? '💰 উপলব্ধ ব্যালেন্স' : '⚠️ নেগেটিভ ব্যালেন্স' ?>
                        </div>
                        <div class="balance-amount" style="color: <?= $realBalance >= 0 ? '#2ECC71' : '#E74C3C' ?>;">
                            <?= formatCurrency($realBalance) ?>
                        </div>
                        <div class="balance-detail" style="color: rgba(255,255,255,0.5);">
                            মোট জমা: <?= formatCurrency($totalDeposit) ?> | উত্তোলন: <?= formatCurrency($totalWithdrawn) ?>
                        </div>
                    </div>
                    
                    <!-- Stats -->
                    <div class="row g-2 mt-3">
                        <div class="col-4">
                            <div class="stat-glass">
                                <div class="stat-number"><?= count($deposits) ?></div>
                                <div class="stat-label">জমা</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="stat-glass">
                                <div class="stat-number"><?= count($withdrawals) ?></div>
                                <div class="stat-label">উত্তোলন</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="stat-glass">
                                <div class="stat-number"><?= $searchResult['total_loans'] ?? 0 ?></div>
                                <div class="stat-label">ঋণ</div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($isAdmin): ?>
                    <div class="d-grid gap-2 mt-3">
                        <a href="/admin/members.php?action=view&id=<?= $searchResult['id'] ?>" 
                           class="btn btn-outline-light btn-sm" style="border-radius:10px;border-color:rgba(255,255,255,0.15);">
                            <i data-feather="eye" style="width:14px;height:14px"></i> সম্পূর্ণ প্রোফাইল
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Details Column -->
                <div class="col-md-8">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="info-glass">
                                <div class="info-title">
                                    <i data-feather="user"></i> ব্যক্তিগত তথ্য
                                </div>
                                <div class="info-item">
                                    <span class="info-label">সদস্য আইডি</span>
                                    <span class="info-value"><code><?= clean($searchResult['member_id']) ?></code></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">নাম</span>
                                    <span class="info-value"><?= clean($searchResult['name']) ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">ফোন</span>
                                    <span class="info-value"><?= clean($searchResult['phone']) ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">ইমেইল</span>
                                    <span class="info-value"><?= clean($searchResult['email'] ?? 'N/A') ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">ঠিকানা</span>
                                    <span class="info-value"><?= clean($searchResult['address'] ?? 'N/A') ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-glass">
                                <div class="info-title">
                                    <i data-feather="briefcase"></i> অ্যাকাউন্ট তথ্য
                                </div>
                                <div class="info-item">
                                    <span class="info-label">যোগদান</span>
                                    <span class="info-value"><?= formatDate($searchResult['join_date']) ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">অবস্থা</span>
                                    <span class="info-value">
                                        <span class="badge <?= $searchResult['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>">
                                            <?= $searchResult['status'] === 'active' ? 'সক্রিয়' : 'নিষ্ক্রিয়' ?>
                                        </span>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">মোট জমা</span>
                                    <span class="info-value amount-positive"><?= formatCurrency($totalDeposit) ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">মোট উত্তোলন</span>
                                    <span class="info-value amount-negative"><?= formatCurrency($totalWithdrawn) ?></span>
                                </div>
                                <hr style="margin:8px 0;border-color:rgba(255,255,255,0.06);">
                                <div class="info-item">
                                    <span class="info-label">মোট ঋণ</span>
                                    <span class="info-value"><?= $searchResult['total_loans'] ?? 0 ?> টি</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">সক্রিয় ঋণ</span>
                                    <span class="info-value"><?= $searchResult['active_loans'] ?? 0 ?> টি</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">পরিশোধিত ঋণ</span>
                                    <span class="info-value"><?= $searchResult['completed_loans'] ?? 0 ?> টি</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================
         DEPOSIT HISTORY - DARK TABLE
         ============================================ -->
    <div class="result-glass">
        <div class="card-header-custom" style="border-bottom: 1px solid rgba(255,255,255,0.06);">
            <span class="title">
                <i data-feather="credit-card"></i> জমার ইতিহাস
            </span>
            <span class="badge bg-secondary" style="background:rgba(255,255,255,0.1)!important;color:rgba(255,255,255,0.7);"><?= count($deposits) ?> টি</span>
            <?php if ($isAdmin): ?>
            <a href="/admin/deposits.php?member_id=<?= $searchResult['id'] ?>" 
               class="btn btn-sm" style="background:rgba(46,204,113,0.15);color:#2ECC71;border-color:rgba(46,204,113,0.2);border-radius:8px;">
                <i data-feather="plus" style="width:14px;height:14px"></i> নতুন জমা
            </a>
            <?php endif; ?>
        </div>
        <div class="card-body-custom" style="padding:0;">
            <div class="table-responsive" style="padding:0;">
                <table class="table table-glass">
                    <thead>
                        <tr>
                            <th>তারিখ</th>
                            <th>পরিমাণ</th>
                            <th>ধরন</th>
                            <th>রসিদ নং</th>
                            <th>মন্তব্য</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($deposits)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4" style="color:rgba(255,255,255,0.3);">
                                <i data-feather="inbox" style="width:32px;height:32px;display:block;margin:0 auto 10px;opacity:0.3;"></i>
                                এই সদস্যের কোনো জমা নেই।
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($deposits as $d): ?>
                        <tr>
                            <td>
                                <?= formatDate($d['deposit_date']) ?>
                                <br><small style="color:rgba(255,255,255,0.3);"><?= timeAgo($d['deposit_date']) ?></small>
                            </td>
                            <td class="amount-positive"><?= formatCurrency($d['amount']) ?></td>
                            <td>
                                <?php 
                                $typeLabels = [
                                    'regular' => '<span class="badge" style="background:rgba(46,134,193,0.2);color:#2E86C1;">নিয়মিত</span>',
                                    'special' => '<span class="badge" style="background:rgba(241,196,15,0.2);color:#f1c40f;">বিশেষ</span>',
                                    'share' => '<span class="badge" style="background:rgba(46,204,113,0.2);color:#2ECC71;">শেয়ার</span>',
                                    'fd' => '<span class="badge" style="background:rgba(155,89,182,0.2);color:#9B59B6;">FD</span>'
                                ];
                                echo $typeLabels[$d['deposit_type']] ?? $d['deposit_type'];
                                ?>
                            </td>
                            <td><code><?= clean($d['receipt_no']) ?></code></td>
                            <td style="color:rgba(255,255,255,0.6);"><?= clean($d['note'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!empty($deposits)): ?>
                        <tfoot>
                            <tr>
                                <td colspan="1" class="text-end">সর্বমোট:</td>
                                <td class="amount-positive">
                                    <?= formatCurrency(array_sum(array_column($deposits, 'amount'))) ?>
                                </td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ============================================
         WITHDRAWAL HISTORY - DARK TABLE
         ============================================ -->
    <div class="result-glass">
        <div class="card-header-custom" style="border-bottom: 1px solid rgba(255,255,255,0.06);">
            <span class="title">
                <i data-feather="arrow-up-circle"></i> উত্তোলনের ইতিহাস
            </span>
            <span class="badge bg-secondary" style="background:rgba(255,255,255,0.1)!important;color:rgba(255,255,255,0.7);"><?= count($withdrawals) ?> টি</span>
            <?php if ($isAdmin): ?>
            <a href="/admin/withdrawals.php?member_id=<?= $searchResult['id'] ?>" 
               class="btn btn-sm" style="background:rgba(241,196,15,0.15);color:#f1c40f;border-color:rgba(241,196,15,0.2);border-radius:8px;">
                <i data-feather="plus" style="width:14px;height:14px"></i> নতুন উত্তোলন
            </a>
            <?php endif; ?>
        </div>
        <div class="card-body-custom" style="padding:0;">
            <div class="table-responsive" style="padding:0;">
                <table class="table table-glass">
                    <thead>
                        <tr>
                            <th>তারিখ</th>
                            <th>পরিমাণ</th>
                            <th>ধরন</th>
                            <th>রসিদ নং</th>
                            <th>কারণ</th>
                            <th>অবস্থা</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($withdrawals)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4" style="color:rgba(255,255,255,0.3);">
                                <i data-feather="inbox" style="width:32px;height:32px;display:block;margin:0 auto 10px;opacity:0.3;"></i>
                                এই সদস্যের কোনো উত্তোলন নেই।
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($withdrawals as $w): ?>
                        <tr>
                            <td>
                                <?= formatDate($w['withdrawal_date']) ?>
                                <br><small style="color:rgba(255,255,255,0.3);"><?= timeAgo($w['withdrawal_date']) ?></small>
                            </td>
                            <td class="amount-negative"><?= formatCurrency($w['amount']) ?></td>
                            <td>
                                <?php 
                                $wTypeMap = [
                                    'partial' => '<span class="badge" style="background:rgba(52,152,219,0.2);color:#3498DB;">আংশিক</span>',
                                    'full' => '<span class="badge" style="background:rgba(155,89,182,0.2);color:#9B59B6;">সম্পূর্ণ</span>',
                                    'profit' => '<span class="badge" style="background:rgba(46,204,113,0.2);color:#2ECC71;">লাভ</span>',
                                    'emergency' => '<span class="badge" style="background:rgba(231,76,60,0.2);color:#E74C3C;">জরুরি</span>'
                                ];
                                echo $wTypeMap[$w['withdrawal_type']] ?? $w['withdrawal_type'];
                                ?>
                            </td>
                            <td><code><?= clean($w['receipt_no']) ?></code></td>
                            <td style="color:rgba(255,255,255,0.6);max-width:150px;word-wrap:break-word;">
                                <?= clean($w['reason'] ?? '-') ?>
                            </td>
                            <td>
                                <span class="badge" style="background:rgba(46,204,113,0.2);color:#2ECC71;">✅ সম্পন্ন</span>
                                <?php if ($w['approved_at']): ?>
                                <br><small style="color:rgba(255,255,255,0.3);font-size:9px;"><?= formatDate($w['approved_at'], 'd M Y') ?></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!empty($withdrawals)): ?>
                        <tfoot>
                            <tr>
                                <td colspan="1" class="text-end">সর্বমোট:</td>
                                <td class="amount-negative">
                                    <?= formatCurrency(array_sum(array_column($withdrawals, 'amount'))) ?>
                                </td>
                                <td colspan="4"></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================
         TIPS SECTION
         ============================================ -->
    <div class="tips-glass">
        <div class="d-flex align-items-start gap-3">
            <i data-feather="lightbulb" style="width:24px;height:24px;color:#f1c40f;flex-shrink:0;"></i>
            <div>
                <h6 class="tips-title">
                    <i data-feather="info" style="width:16px;height:16px;"></i>
                    অনুসন্ধান টিপস
                </h6>
                <ul>
                    <li><strong>✅ সম্পূর্ণ ফোন নম্বর:</strong> 017XXXXXXXX বা 019XXXXXXXX ফরম্যাটে লিখুন</li>
                    <li><strong>✅ সম্পূর্ণ সদস্য আইডি:</strong> SM20240001 ফরম্যাটে লিখুন</li>
               <!-- <li><strong>❌ আংশিক অনুসন্ধান:</strong> 01, 017, SM ইত্যাদি দিয়ে খুঁজলে ফলাফল পাওয়া যাবে না</li>   -->
                    <li><strong>💡 টিপ:</strong> সঠিক ফলাফল পেতে সম্পূর্ণ নম্বর বা আইডি ব্যবহার করুন</li>
                </ul>
            </div>
        </div>
    </div>

</div>

<!-- ============================================
     SCRIPTS
     ============================================ -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Initialize Feather Icons
    feather.replace();
    
    // Auto focus on search input
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput && !searchInput.value) {
            searchInput.focus();
        }
    });
</script>

<?php
// Helper function for avatar color
function getColor($name) {
    $colors = ['#1B4F72', '#2E86C1', '#117A65', '#C0392B', '#8E44AD', '#D35400', '#27AE60', '#2980B9'];
    return $colors[abs(crc32($name)) % count($colors)];
}

// Include footer
require_once __DIR__ . '/includes/footer.php';
?>