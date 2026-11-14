<?php
// =====================================================
// admin/financial_summary.php - Financial Summary Dashboard
// WITH MEMBER PROFILE PICTURES - FULLY RESPONSIVE
// =====================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/functions.php';
startSecureSession();

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_role'])) {
    header('Location: /admin/login.php');
    exit;
}

$pageTitle = 'আর্থিক সারাংশ';
$conn = conn();

// Get admin info
$admin = getCurrentAdmin();
$somity_name = getSetting('somity_name', 'সোমিটি');

// Get total members for footer
$totalMembers = 0;
try {
    $result = $conn->query("SELECT COUNT(*) as count FROM members WHERE status = 'active'");
    if ($result) {
        $row = $result->fetch_assoc();
        $totalMembers = $row['count'] ?? 0;
    }
} catch (Exception $e) {
    $totalMembers = 0;
}

// ============================================
// HELPER FUNCTION FOR MEMBER PHOTO
// ============================================
function getMemberAvatar($memberId, $photoPath = null, $name = '', $size = 32) {
    if (!empty($photoPath) && file_exists($_SERVER['DOCUMENT_ROOT'] . $photoPath)) {
        return '<img src="' . htmlspecialchars($photoPath) . '" alt="' . htmlspecialchars($name) . '" style="width:' . $size . 'px;height:' . $size . 'px;border-radius:50%;object-fit:cover;border:2px solid #ddd;flex-shrink:0;">';
    }
    
    $firstLetter = mb_substr($name, 0, 1, 'UTF-8');
    $colors = ['#1B4F72', '#2E86C1', '#117A65', '#C0392B', '#8E44AD', '#D35400', '#27AE60', '#2980B9', '#E67E22', '#16A085'];
    $color = $colors[abs(crc32($name)) % count($colors)];
    
    return '<div style="width:' . $size . 'px;height:' . $size . 'px;border-radius:50%;background:' . $color . ';color:white;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:' . ($size * 0.5) . 'px;flex-shrink:0;border:2px solid #ddd;">' . htmlspecialchars($firstLetter) . '</div>';
}

// ============================================
// GET YEAR FILTER
// ============================================
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : 0;
$yearCondition = '';
$yearLabel = 'সর্বকাল';

$availableYears = [];
try {
    $result = $conn->query("SELECT DISTINCT YEAR(deposit_date) as year FROM deposits WHERE deposit_type IN ('regular', 'special', 'share', 'fd') AND amount > 0 ORDER BY year DESC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (!in_array($row['year'], $availableYears)) {
                $availableYears[] = (int)$row['year'];
            }
        }
    }
    
    $result = $conn->query("SELECT DISTINCT YEAR(withdrawal_date) as year FROM withdrawals WHERE status = 'completed' ORDER BY year DESC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (!in_array($row['year'], $availableYears)) {
                $availableYears[] = (int)$row['year'];
            }
        }
    }
    
    $result = $conn->query("SELECT DISTINCT YEAR(expense_date) as year FROM expenses ORDER BY year DESC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (!in_array($row['year'], $availableYears)) {
                $availableYears[] = (int)$row['year'];
            }
        }
    }
    
    $result = $conn->query("SELECT DISTINCT YEAR(payment_date) as year FROM fine_payments ORDER BY year DESC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (!in_array($row['year'], $availableYears)) {
                $availableYears[] = (int)$row['year'];
            }
        }
    }
    
    $result = $conn->query("SELECT DISTINCT YEAR(investment_date) as year FROM investments ORDER BY year DESC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (!in_array($row['year'], $availableYears)) {
                $availableYears[] = (int)$row['year'];
            }
        }
    }
    
    $result = $conn->query("SELECT DISTINCT YEAR(return_date) as year FROM investment_returns ORDER BY year DESC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (!in_array($row['year'], $availableYears)) {
                $availableYears[] = (int)$row['year'];
            }
        }
    }
    
    rsort($availableYears);
    
} catch (Exception $e) {
    $availableYears = [];
}

if ($selectedYear > 0 && in_array($selectedYear, $availableYears)) {
    $yearCondition = " AND YEAR(transaction_date) = $selectedYear";
    $yearLabel = $selectedYear;
} else {
    $selectedYear = 0;
    $yearCondition = '';
    $yearLabel = 'সর্বকাল';
}

// ============================================
// 1. TOTAL DEPOSITS
// ============================================
$totalDeposits = 0;
$depositBreakdown = [];

try {
    $yearFilter = $selectedYear > 0 ? "AND YEAR(deposit_date) = $selectedYear" : "";
    $result = $conn->query("
        SELECT 
            deposit_type,
            COALESCE(SUM(amount), 0) as total
        FROM deposits 
        WHERE deposit_type IN ('regular', 'special', 'share', 'fd') 
        AND amount > 0
        $yearFilter
        GROUP BY deposit_type
        ORDER BY FIELD(deposit_type, 'regular', 'special', 'share', 'fd')
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $depositBreakdown[$row['deposit_type']] = (float)($row['total'] ?? 0);
            $totalDeposits += (float)($row['total'] ?? 0);
        }
    }
} catch (Exception $e) {
    $totalDeposits = 0;
}

// ============================================
// 2. TOTAL FINES PAID
// ============================================
$totalFinesPaid = 0;
$finePaymentsList = [];

try {
    $tableCheck = $conn->query("SHOW TABLES LIKE 'fine_payments'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        $yearFilter = $selectedYear > 0 ? "AND YEAR(fp.payment_date) = $selectedYear" : "";
        $result = $conn->query("
            SELECT COALESCE(SUM(fp.amount), 0) as total 
            FROM fine_payments fp
            WHERE 1=1
            $yearFilter
        ");
        if ($result) {
            $row = $result->fetch_assoc();
            $totalFinesPaid = (float)($row['total'] ?? 0);
        }
        
        $result = $conn->query("
            SELECT 
                fp.*,
                f.member_id,
                m.name as member_name,
                m.member_id as member_code,
                m.photo as member_photo
            FROM fine_payments fp
            JOIN fines f ON f.id = fp.fine_id
            JOIN members m ON m.id = f.member_id
            WHERE 1=1
            $yearFilter
            ORDER BY fp.payment_date DESC, fp.created_at DESC
            LIMIT 10
        ");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $finePaymentsList[] = $row;
            }
        }
    }
} catch (Exception $e) {
    $totalFinesPaid = 0;
}

// ============================================
// 3. TOTAL WITHDRAWALS
// ============================================
$totalWithdrawals = 0;
$withdrawalBreakdown = [];
$recentWithdrawals = [];

try {
    $yearFilter = $selectedYear > 0 ? "AND YEAR(withdrawal_date) = $selectedYear" : "";
    
    $result = $conn->query("
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM withdrawals 
        WHERE status = 'completed'
        $yearFilter
    ");
    if ($result) {
        $row = $result->fetch_assoc();
        $totalWithdrawals = (float)($row['total'] ?? 0);
    }
    
    $result = $conn->query("
        SELECT 
            withdrawal_type,
            COALESCE(SUM(amount), 0) as total,
            COUNT(*) as count
        FROM withdrawals 
        WHERE status = 'completed'
        $yearFilter
        GROUP BY withdrawal_type
        ORDER BY withdrawal_type
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $withdrawalBreakdown[$row['withdrawal_type']] = [
                'total' => (float)($row['total'] ?? 0),
                'count' => (int)($row['count'] ?? 0)
            ];
        }
    }
    
    $result = $conn->query("
        SELECT 
            w.*,
            m.name as member_name,
            m.member_id as member_code,
            m.photo as member_photo
        FROM withdrawals w
        JOIN members m ON m.id = w.member_id
        WHERE w.status = 'completed'
        $yearFilter
        ORDER BY w.withdrawal_date DESC, w.id DESC 
        LIMIT 10
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recentWithdrawals[] = $row;
        }
    }
} catch (Exception $e) {
    $totalWithdrawals = 0;
}

// ============================================
// 4. TOTAL EXPENSES
// ============================================
$totalExpenses = 0;
$expenseBreakdown = [];
$recentExpenses = [];

try {
    $yearFilter = $selectedYear > 0 ? "AND YEAR(expense_date) = $selectedYear" : "";
    
    $result = $conn->query("
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM expenses
        WHERE 1=1
        $yearFilter
    ");
    if ($result) {
        $row = $result->fetch_assoc();
        $totalExpenses = (float)($row['total'] ?? 0);
    }
    
    $result = $conn->query("
        SELECT 
            category,
            COALESCE(SUM(amount), 0) as total,
            COUNT(*) as count
        FROM expenses 
        WHERE 1=1
        $yearFilter
        GROUP BY category
        ORDER BY category
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $expenseBreakdown[$row['category']] = [
                'total' => (float)($row['total'] ?? 0),
                'count' => (int)($row['count'] ?? 0)
            ];
        }
    }
    
    $result = $conn->query("
        SELECT 
            id,
            title,
            category,
            amount,
            expense_date,
            description
        FROM expenses 
        WHERE 1=1
        $yearFilter
        ORDER BY expense_date DESC, created_at DESC 
        LIMIT 10
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recentExpenses[] = $row;
        }
    }
} catch (Exception $e) {
    $totalExpenses = 0;
}

// ============================================
// 5. INVESTMENT STATS
// ============================================
$totalInvestments = 0;
$totalInvestmentReturns = 0;
$netInvestment = 0;
$investmentCount = 0;
$investmentList = [];

try {
    $yearFilter = $selectedYear > 0 ? "AND YEAR(i.investment_date) = $selectedYear" : "";
    
    $tableCheck = $conn->query("SHOW TABLES LIKE 'investments'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        $result = $conn->query("
            SELECT COALESCE(SUM(amount), 0) as total, COUNT(*) as count
            FROM investments i
            WHERE 1=1
            $yearFilter
        ");
        if ($result) {
            $row = $result->fetch_assoc();
            $totalInvestments = (float)($row['total'] ?? 0);
            $investmentCount = (int)($row['count'] ?? 0);
        }
        
        $result = $conn->query("
            SELECT 
                i.*,
                COALESCE(r.total_return, 0) AS total_return
            FROM investments i
            LEFT JOIN (
                SELECT investment_id, SUM(return_amount) AS total_return 
                FROM investment_returns 
                GROUP BY investment_id
            ) r ON r.investment_id = i.id
            WHERE 1=1
            $yearFilter
            ORDER BY i.investment_date DESC
            LIMIT 10
        ");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $investmentList[] = $row;
            }
        }
    }
} catch (Exception $e) {
    $totalInvestments = 0;
    $investmentCount = 0;
}

try {
    $yearFilter = $selectedYear > 0 ? "AND YEAR(return_date) = $selectedYear" : "";
    
    $tableCheck = $conn->query("SHOW TABLES LIKE 'investment_returns'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        $result = $conn->query("
            SELECT COALESCE(SUM(return_amount), 0) as total 
            FROM investment_returns
            WHERE 1=1
            $yearFilter
        ");
        if ($result) {
            $row = $result->fetch_assoc();
            $totalInvestmentReturns = (float)($row['total'] ?? 0);
        }
    }
} catch (Exception $e) {
    $totalInvestmentReturns = 0;
}

$netInvestment = $totalInvestments - $totalInvestmentReturns;
$roi = 0;
if ($totalInvestments > 0) {
    $roi = ($totalInvestmentReturns / $totalInvestments) * 100;
}

// ============================================
// TOTAL BALANCE CALCULATION
// ============================================
$totalReceivable = $totalDeposits + $totalFinesPaid + $totalInvestmentReturns;
$totalPayable = $totalWithdrawals + $totalExpenses + $totalInvestments;
$totalBalance = $totalReceivable - $totalPayable;

// ============================================
// 6. MONTHLY DATA FOR CHARTS
// ============================================

$monthlyExpenses = [];
try {
    $yearFilter = $selectedYear > 0 ? "AND YEAR(expense_date) = $selectedYear" : "";
    $result = $conn->query("
        SELECT 
            MONTH(expense_date) as month,
            COALESCE(SUM(amount), 0) as total
        FROM expenses 
        WHERE 1=1
        $yearFilter
        GROUP BY MONTH(expense_date)
        ORDER BY month
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $monthlyExpenses[(int)$row['month']] = (float)($row['total'] ?? 0);
        }
    }
} catch (Exception $e) {
    $monthlyExpenses = [];
}

$monthlyWithdrawals = [];
try {
    $yearFilter = $selectedYear > 0 ? "AND YEAR(withdrawal_date) = $selectedYear" : "";
    $result = $conn->query("
        SELECT 
            MONTH(withdrawal_date) as month,
            COALESCE(SUM(amount), 0) as total
        FROM withdrawals 
        WHERE status = 'completed'
        $yearFilter
        GROUP BY MONTH(withdrawal_date)
        ORDER BY month
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $monthlyWithdrawals[(int)$row['month']] = (float)($row['total'] ?? 0);
        }
    }
} catch (Exception $e) {
    $monthlyWithdrawals = [];
}

$monthlyDeposits = [];
try {
    $yearFilter = $selectedYear > 0 ? "AND YEAR(deposit_date) = $selectedYear" : "";
    $result = $conn->query("
        SELECT 
            MONTH(deposit_date) as month,
            COALESCE(SUM(amount), 0) as total
        FROM deposits 
        WHERE deposit_type IN ('regular', 'special', 'share', 'fd')
        AND amount > 0
        $yearFilter
        GROUP BY MONTH(deposit_date)
        ORDER BY month
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $monthlyDeposits[(int)$row['month']] = (float)($row['total'] ?? 0);
        }
    }
} catch (Exception $e) {
    $monthlyDeposits = [];
}

$monthlyInvestments = [];
try {
    $yearFilter = $selectedYear > 0 ? "AND YEAR(investment_date) = $selectedYear" : "";
    $result = $conn->query("
        SELECT 
            MONTH(investment_date) as month,
            COALESCE(SUM(amount), 0) as total
        FROM investments 
        WHERE 1=1
        $yearFilter
        GROUP BY MONTH(investment_date)
        ORDER BY month
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $monthlyInvestments[(int)$row['month']] = (float)($row['total'] ?? 0);
        }
    }
} catch (Exception $e) {
    $monthlyInvestments = [];
}

// ============================================
// 7. LATEST 10 TRANSACTIONS
// ============================================
$latestTransactions = [];

try {
    $yearFilter = $selectedYear > 0 ? "AND YEAR(d.deposit_date) = $selectedYear" : "";
    $result = $conn->query("
        SELECT 
            'deposit' as type,
            d.id,
            d.amount,
            d.deposit_date as transaction_date,
            d.deposit_type as sub_type,
            m.name as member_name,
            m.member_id as member_code,
            m.photo as member_photo,
            d.note as description
        FROM deposits d
        JOIN members m ON m.id = d.member_id
        WHERE d.deposit_type IN ('regular', 'special', 'share', 'fd')
        AND d.amount > 0
        $yearFilter
        ORDER BY d.deposit_date DESC, d.id DESC
        LIMIT 10
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['amount_display'] = '+' . number_format($row['amount'], 0);
            $row['amount_class'] = 'value-positive';
            $row['icon'] = '📥';
            $latestTransactions[] = $row;
        }
    }
} catch (Exception $e) {}

try {
    $yearFilter = $selectedYear > 0 ? "AND YEAR(w.withdrawal_date) = $selectedYear" : "";
    $result = $conn->query("
        SELECT 
            'withdrawal' as type,
            w.id,
            w.amount,
            w.withdrawal_date as transaction_date,
            w.withdrawal_type as sub_type,
            m.name as member_name,
            m.member_id as member_code,
            m.photo as member_photo,
            w.reason as description
        FROM withdrawals w
        JOIN members m ON m.id = w.member_id
        WHERE w.status = 'completed'
        $yearFilter
        ORDER BY w.withdrawal_date DESC, w.id DESC
        LIMIT 10
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['amount_display'] = '-' . number_format($row['amount'], 0);
            $row['amount_class'] = 'value-negative';
            $row['icon'] = '📤';
            $latestTransactions[] = $row;
        }
    }
} catch (Exception $e) {}

try {
    $yearFilter = $selectedYear > 0 ? "AND YEAR(e.expense_date) = $selectedYear" : "";
    $result = $conn->query("
        SELECT 
            'expense' as type,
            e.id,
            e.amount,
            e.expense_date as transaction_date,
            e.category as sub_type,
            NULL as member_name,
            NULL as member_code,
            NULL as member_photo,
            e.title as description
        FROM expenses e
        WHERE 1=1
        $yearFilter
        ORDER BY e.expense_date DESC, e.id DESC
        LIMIT 10
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['amount_display'] = '-' . number_format($row['amount'], 0);
            $row['amount_class'] = 'value-negative';
            $row['icon'] = '💰';
            $latestTransactions[] = $row;
        }
    }
} catch (Exception $e) {}

try {
    $tableCheck = $conn->query("SHOW TABLES LIKE 'fine_payments'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        $yearFilter = $selectedYear > 0 ? "AND YEAR(fp.payment_date) = $selectedYear" : "";
        $result = $conn->query("
            SELECT 
                'fine_payment' as type,
                fp.id,
                fp.amount,
                fp.payment_date as transaction_date,
                'fine' as sub_type,
                m.name as member_name,
                m.member_id as member_code,
                m.photo as member_photo,
                NULL as description
            FROM fine_payments fp
            JOIN fines f ON f.id = fp.fine_id
            JOIN members m ON m.id = f.member_id
            WHERE 1=1
            $yearFilter
            ORDER BY fp.payment_date DESC, fp.created_at DESC
            LIMIT 10
        ");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $row['amount_display'] = '+' . number_format($row['amount'], 0);
                $row['amount_class'] = 'value-positive';
                $row['icon'] = '⚖️';
                $latestTransactions[] = $row;
            }
        }
    }
} catch (Exception $e) {}

try {
    $yearFilter = $selectedYear > 0 ? "AND YEAR(i.investment_date) = $selectedYear" : "";
    $result = $conn->query("
        SELECT 
            'investment' as type,
            i.id,
            i.amount,
            i.investment_date as transaction_date,
            i.investment_type as sub_type,
            NULL as member_name,
            NULL as member_code,
            NULL as member_photo,
            i.title as description
        FROM investments i
        WHERE 1=1
        $yearFilter
        ORDER BY i.investment_date DESC, i.id DESC
        LIMIT 10
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['amount_display'] = '-' . number_format($row['amount'], 0);
            $row['amount_class'] = 'value-negative';
            $row['icon'] = '💼';
            $latestTransactions[] = $row;
        }
    }
} catch (Exception $e) {}

usort($latestTransactions, function($a, $b) {
    return strtotime($b['transaction_date']) - strtotime($a['transaction_date']);
});

$latestTransactions = array_slice($latestTransactions, 0, 10);

// ============================================
// 8. GET LATEST 10 DEPOSITS
// ============================================
$latestDeposits = [];
try {
    $yearFilter = $selectedYear > 0 ? "AND YEAR(d.deposit_date) = $selectedYear" : "";
    $result = $conn->query("
        SELECT 
            d.*,
            m.name as member_name,
            m.member_id as member_code,
            m.photo as member_photo
        FROM deposits d
        JOIN members m ON m.id = d.member_id
        WHERE d.deposit_type IN ('regular', 'special', 'share', 'fd')
        AND d.amount > 0
        $yearFilter
        ORDER BY d.deposit_date DESC, d.id DESC
        LIMIT 10
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $latestDeposits[] = $row;
        }
    }
} catch (Exception $e) {}

require_once __DIR__ . '/../includes/header.php';
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title><?= clean($somity_name) ?> - <?= $pageTitle ?></title>
    <style>
        /* ============================================
           RESPONSIVE FINANCIAL SUMMARY STYLES
           ============================================ */
        
        .summary-card {
            border-radius: 12px;
            padding: 16px 14px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            transition: all 0.3s ease;
            height: 100%;
            position: relative;
            overflow: hidden;
            border: none;
        }
        .summary-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        }
        .summary-card .card-icon {
            position: absolute;
            top: 8px;
            right: 10px;
            opacity: 0.12;
            font-size: 36px;
        }
        .summary-card .card-value {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 2px;
            word-wrap: break-word;
        }
        .summary-card .card-label {
            font-size: 12px;
            font-weight: 500;
            color: #566573;
        }
        .summary-card .card-sub {
            font-size: 10px;
            color: #888;
            margin-top: 4px;
        }
        .summary-card .card-sub .badge {
            font-size: 9px;
            padding: 2px 6px;
        }
        
        .card-balance {
            background: linear-gradient(135deg, #E8F8F5, #D1F2EB);
            border-left: 4px solid #1ABC9C;
        }
        .card-balance .card-value { color: #0E6655; }
        
        .card-deposits {
            background: linear-gradient(135deg, #EAF2F8, #D6EAF8);
            border-left: 4px solid #2E86C1;
        }
        .card-deposits .card-value { color: #1B4F72; }
        
        .card-expenses {
            background: linear-gradient(135deg, #FDEDEC, #FADBD8);
            border-left: 4px solid #E74C3C;
        }
        .card-expenses .card-value { color: #C0392B; }
        
        .card-withdrawals {
            background: linear-gradient(135deg, #FEF9E7, #FDEBD0);
            border-left: 4px solid #F39C12;
        }
        .card-withdrawals .card-value { color: #D35400; }
        
        .card-fines {
            background: linear-gradient(135deg, #F4ECF7, #EBDEF0);
            border-left: 4px solid #8E44AD;
        }
        .card-fines .card-value { color: #6C3483; }
        
        .card-investments {
            background: linear-gradient(135deg, #F4ECF7, #EBDEF0);
            border-left: 4px solid #8E44AD;
        }
        .card-investments .card-value { color: #6C3483; }
        .card-investments .card-value.net { color: #6C3483; }
        
        .detail-card {
            background: white;
            border-radius: 10px;
            padding: 12px 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            height: 100%;
        }
        .detail-card .detail-title {
            font-size: 12px;
            font-weight: 600;
            color: #2C3E50;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .detail-card .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #f5f5f5;
            font-size: 12px;
        }
        .detail-card .detail-item:last-child {
            border-bottom: none;
        }
        .detail-card .detail-item .label {
            color: #566573;
            font-size: 12px;
        }
        .detail-card .detail-item .value {
            font-weight: 600;
            font-size: 12px;
        }
        .detail-card .detail-item .value .sub-text {
            font-weight: 400;
            color: #888;
            font-size: 10px;
        }
        
        .badge-balance { background: #1ABC9C; color: white; }
        .badge-deposit { background: #2E86C1; color: white; }
        .badge-expense { background: #E74C3C; color: white; }
        .badge-withdrawal { background: #F39C12; color: white; }
        .badge-fine { background: #8E44AD; color: white; }
        .badge-investment { background: #8E44AD; color: white; }
        
        .list-item {
            padding: 8px 12px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
        }
        .list-item:last-child { border-bottom: none; }
        .list-item .item-info { 
            font-size: 12px; 
            flex: 1;
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
        }
        .list-item .item-info .member-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            flex-shrink: 0;
            overflow: hidden;
            border: 2px solid #e8ecf1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f0f4f8;
        }
        .list-item .item-info .member-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .list-item .item-info .member-avatar .avatar-text {
            font-size: 11px;
            font-weight: 600;
            color: white;
        }
        .list-item .item-info .details {
            flex: 1;
            min-width: 0;
        }
        .list-item .item-info .details .name { 
            font-weight: 600; 
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 4px;
            flex-wrap: wrap;
            word-break: break-word;
        }
        .list-item .item-info .details .sub { 
            font-size: 10px; 
            color: #888;
            display: flex;
            align-items: center;
            gap: 4px;
            flex-wrap: wrap;
        }
        .list-item .item-info .details .sub code {
            font-size: 9px;
            background: #f0f0f0;
            padding: 1px 4px;
            border-radius: 3px;
        }
        .list-item .item-amount { 
            font-weight: 600; 
            white-space: nowrap; 
            margin-left: 6px;
            font-size: 12px;
        }
        
        .scrollable-list {
            max-height: 250px;
            overflow-y: auto;
        }
        .scrollable-list::-webkit-scrollbar {
            width: 3px;
        }
        .scrollable-list::-webkit-scrollbar-track {
            background: #f0f0f0;
            border-radius: 3px;
        }
        .scrollable-list::-webkit-scrollbar-thumb {
            background: #2E86C1;
            border-radius: 3px;
        }
        
        .value-positive { color: #27AE60; }
        .value-negative { color: #E74C3C; }
        .value-neutral { color: #2C3E50; }
        
        /* ============================================
           CHART BARS - WITH HORIZONTAL SCROLL ON MOBILE
           ============================================ */
        .chart-wrapper {
            overflow: visible;
        }
        
        .chart-bar-container {
            display: flex;
            gap: 3px;
            align-items: flex-end;
            height: 120px;
            padding: 8px 0;
            overflow: visible;
        }
        .chart-bar-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            height: 100%;
            min-width: 0;
        }
        .chart-bar-value {
            font-size: 7px;
            color: #888;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }
        .chart-bar {
            width: 100%;
            border-radius: 3px 3px 0 0;
            transition: all 0.3s;
            min-height: 3px;
            max-width: 20px;
            margin: 0 auto;
        }
        .chart-bar-label {
            font-size: 7px;
            color: #888;
            margin-top: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
            text-align: center;
            width: 100%;
        }
        
        /* MOBILE: Horizontal scroll for charts */
        @media (max-width: 480px) {
            .chart-wrapper {
                overflow-x: auto;
                overflow-y: hidden;
                -webkit-overflow-scrolling: touch;
                scroll-snap-type: x mandatory;
                padding: 4px 0;
                margin: 0 -10px;
                padding-left: 10px;
                padding-right: 10px;
            }
            
            .chart-wrapper::-webkit-scrollbar {
                height: 3px;
            }
            .chart-wrapper::-webkit-scrollbar-track {
                background: #f0f0f0;
                border-radius: 10px;
            }
            .chart-wrapper::-webkit-scrollbar-thumb {
                background: #2E86C1;
                border-radius: 10px;
            }
            
            .chart-bar-container {
                gap: 4px;
                height: 80px;
                padding: 4px 0;
                min-width: max-content;
                width: auto;
            }
            
            .chart-bar-wrapper {
                flex: 0 0 22px;
                min-width: 18px;
            }
            
            .chart-bar-value {
                font-size: 5px;
            }
            .chart-bar {
                max-width: 14px;
                min-height: 2px;
            }
            .chart-bar-label {
                font-size: 5px;
                margin-top: 2px;
            }
        }
        
        @media (max-width: 360px) {
            .chart-bar-container {
                height: 60px;
                gap: 3px;
            }
            .chart-bar-wrapper {
                flex: 0 0 16px;
                min-width: 14px;
            }
            .chart-bar {
                max-width: 10px;
            }
            .chart-bar-label {
                font-size: 4px;
            }
            .chart-bar-value {
                font-size: 4px;
            }
        }
        
        /* Tablet and above - normal display */
        @media (min-width: 576px) {
            .chart-bar-container {
                height: 120px;
                gap: 3px;
            }
            .chart-bar-wrapper {
                flex: 1;
                min-width: 0;
            }
            .chart-bar {
                max-width: 16px;
            }
            .chart-bar-label {
                font-size: 7px;
            }
            .chart-bar-value {
                font-size: 7px;
            }
        }
        
        @media (min-width: 768px) {
            .chart-bar-container {
                height: 140px;
                gap: 3px;
            }
            .chart-bar {
                max-width: 18px;
            }
            .chart-bar-label {
                font-size: 8px;
            }
            .chart-bar-value {
                font-size: 8px;
            }
        }
        
        @media (min-width: 992px) {
            .chart-bar-container {
                height: 150px;
            }
            .chart-bar {
                max-width: 22px;
            }
            .chart-bar-label {
                font-size: 9px;
            }
            .chart-bar-value {
                font-size: 9px;
            }
        }
        
        .transaction-icon {
            font-size: 14px;
            margin-right: 2px;
        }
        
        .year-filter-box {
            background: white;
            border-radius: 10px;
            padding: 10px 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .year-filter-box .filter-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
        }
        .year-filter-box select {
            padding: 6px 10px;
            border-radius: 6px;
            border: 1px solid #ddd;
            font-size: 13px;
            background: white;
            flex: 1;
            min-width: 120px;
        }
        .year-filter-box .filter-label {
            font-weight: 500;
            color: #566573;
            font-size: 13px;
        }
        .year-filter-box .btn-reset {
            padding: 4px 12px;
            border-radius: 6px;
            border: none;
            background: #E8F8F5;
            color: #0E6655;
            font-weight: 500;
            font-size: 12px;
            text-decoration: none;
            transition: all 0.3s;
            white-space: nowrap;
        }
        .year-filter-box .btn-reset:hover {
            background: #D1F2EB;
            color: #0E6655;
        }
        .year-filter-box .badge-year {
            background: #1ABC9C;
            color: white;
            padding: 3px 10px;
            border-radius: 16px;
            font-size: 12px;
            white-space: nowrap;
        }
        
        .member-avatar-sm {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            flex-shrink: 0;
            overflow: hidden;
            border: 2px solid #e8ecf1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f0f4f8;
        }
        .member-avatar-sm img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .member-avatar-sm .avatar-text {
            font-size: 10px;
            font-weight: 600;
            color: white;
        }
        
        .member-cell {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .member-cell .member-name {
            font-weight: 600;
            font-size: 13px;
        }
        
        .net-investment-card {
            background: linear-gradient(135deg, #F4ECF7, #EBDEF0);
            border-left: 4px solid #8E44AD;
        }
        .net-investment-card .card-value {
            color: #6C3483;
        }
        .roi-badge {
            background: #8E44AD;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 10px;
        }
        .roi-positive { color: #27AE60; }
        .roi-negative { color: #E74C3C; }
        
        /* Responsive breakpoints */
        @media (min-width: 576px) {
            .summary-card .card-value {
                font-size: 26px;
            }
            .summary-card {
                padding: 18px 16px;
            }
            .detail-card .detail-item {
                font-size: 13px;
            }
            .list-item .item-info {
                font-size: 13px;
            }
            .list-item .item-amount {
                font-size: 13px;
            }
        }
        
        @media (min-width: 768px) {
            .summary-card .card-value {
                font-size: 30px;
            }
            .summary-card {
                padding: 20px 18px;
            }
            .summary-card .card-icon {
                font-size: 44px;
                top: 12px;
                right: 14px;
            }
            .year-filter-box {
                flex-direction: row;
                align-items: center;
                padding: 12px 18px;
            }
            .year-filter-box .filter-row {
                flex: 1;
            }
            .list-item .item-info .member-avatar {
                width: 32px;
                height: 32px;
            }
            .scrollable-list {
                max-height: 280px;
            }
        }
        
        @media (min-width: 992px) {
            .summary-card .card-value {
                font-size: 32px;
            }
            .summary-card {
                padding: 24px 20px;
            }
            .summary-card .card-icon {
                font-size: 48px;
                top: 16px;
                right: 16px;
            }
        }
        
        /* Small screens - extra adjustments */
        @media (max-width: 480px) {
            .summary-card .card-value {
                font-size: 18px;
            }
            .summary-card {
                padding: 12px 10px;
            }
            .summary-card .card-icon {
                font-size: 28px;
                top: 6px;
                right: 8px;
            }
            .summary-card .card-label {
                font-size: 10px;
            }
            .summary-card .card-sub {
                font-size: 9px;
            }
            .summary-card .card-sub .badge {
                font-size: 8px;
                padding: 1px 5px;
            }
            
            .detail-card {
                padding: 10px 12px;
            }
            .detail-card .detail-item {
                font-size: 11px;
                padding: 4px 0;
            }
            .detail-card .detail-title {
                font-size: 11px;
                margin-bottom: 8px;
            }
            
            .list-item {
                padding: 6px 10px;
            }
            .list-item .item-info {
                font-size: 11px;
                gap: 6px;
            }
            .list-item .item-info .member-avatar {
                width: 24px;
                height: 24px;
            }
            .list-item .item-info .details .name {
                font-size: 11px;
            }
            .list-item .item-info .details .sub {
                font-size: 9px;
            }
            .list-item .item-amount {
                font-size: 11px;
            }
            
            .year-filter-box select {
                font-size: 12px;
                padding: 4px 8px;
            }
            .year-filter-box .filter-label {
                font-size: 12px;
            }
            .year-filter-box .badge-year {
                font-size: 11px;
                padding: 2px 8px;
            }
            .year-filter-box .btn-reset {
                font-size: 11px;
                padding: 3px 10px;
            }
            
            .badge {
                font-size: 9px;
                padding: 2px 6px;
            }
            .card-header .card-title {
                font-size: 12px;
            }
            .card-header .badge {
                font-size: 9px;
            }
            
            h4 {
                font-size: 16px !important;
            }
            h4 small {
                font-size: 11px !important;
                display: block;
            }
            
            .container-fluid {
                padding-left: 8px !important;
                padding-right: 8px !important;
            }
            .g-3 {
                gap: 0.5rem !important;
            }
            
            .scrollable-list {
                max-height: 180px;
            }
        }
        
        @media (max-width: 360px) {
            .summary-card .card-value {
                font-size: 16px;
            }
            .summary-card {
                padding: 10px 8px;
            }
            .summary-card .card-icon {
                font-size: 24px;
                top: 4px;
                right: 6px;
            }
            .detail-card .detail-item {
                font-size: 10px;
                padding: 3px 0;
            }
            .list-item .item-info {
                font-size: 10px;
            }
            .list-item .item-amount {
                font-size: 10px;
            }
            .scrollable-list {
                max-height: 150px;
            }
        }
        
        /* Page header responsive */
        .page-header-mobile {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 16px;
        }
        .page-header-mobile h4 {
            font-size: 18px;
            margin: 0;
        }
        .page-header-mobile h4 small {
            font-size: 12px;
            color: #888;
            display: block;
        }
        .page-header-mobile .header-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        
        @media (max-width: 480px) {
            .page-header-mobile h4 {
                font-size: 16px;
            }
            .page-header-mobile h4 small {
                font-size: 11px;
            }
            .page-header-mobile .header-actions .btn {
                font-size: 11px;
                padding: 4px 10px;
            }
            .page-header-mobile .header-actions .badge {
                font-size: 10px;
                padding: 3px 8px;
            }
        }
        
        /* Card height fix for scrollable lists */
        .card-scrollable {
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .card-scrollable .card-header {
            flex-shrink: 0;
        }
        .card-scrollable .scrollable-list {
            flex: 1;
        }
    </style>
</head>
<body>

<div class="container-fluid px-2 px-sm-3">

<!-- Page Header - Mobile Responsive -->
<div class="page-header-mobile">
    <h4>
        <i data-feather="dollar-sign" style="width:18px;height:18px;"></i> 
        <?= $pageTitle ?>
        <small>আর্থিক সারাংশ</small>
    </h4>
    <div class="header-actions">
        <span class="badge bg-primary"><?= date('d M Y') ?></span>
        <a href="/admin/reports.php" class="btn btn-outline-primary btn-sm">
            <i data-feather="file-text" style="width:12px;height:12px;"></i>
            <span class="d-none d-sm-inline">রিপোর্ট</span>
        </a>
    </div>
</div>

<!-- ============================================
     YEAR FILTER - MOBILE FRIENDLY
     ============================================ -->
<div class="year-filter-box mb-3 mb-md-4">
    <div class="filter-row">
        <span class="filter-label">
            <i data-feather="calendar" style="width:14px;height:14px;"></i>
            বছর:
        </span>
        <form method="GET" action="" style="display:flex;align-items:center;gap:6px;flex:1;flex-wrap:wrap;">
            <select name="year" onchange="this.form.submit()" style="flex:1;min-width:100px;">
                <option value="0" <?= $selectedYear == 0 ? 'selected' : '' ?>>সর্বকাল</option>
                <?php foreach ($availableYears as $year): ?>
                <option value="<?= $year ?>" <?= $selectedYear == $year ? 'selected' : '' ?>><?= $year ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($selectedYear > 0): ?>
            <a href="?year=0" class="btn-reset">
                <i data-feather="x" style="width:12px;height:12px;"></i>
                <span class="d-none d-sm-inline">রিসেট</span>
            </a>
            <?php endif; ?>
        </form>
    </div>
    <div class="text-end">
        <span class="badge-year">
            <i data-feather="calendar" style="width:12px;height:12px;"></i>
            <?= $yearLabel ?>
        </span>
    </div>
</div>

<!-- ============================================
     MAIN SUMMARY CARDS - 6 CARDS
     ============================================ -->
<div class="row g-2 g-sm-3 mb-3 mb-md-4">
    
    <!-- Card 1: Total Balance -->
    <div class="col-6 col-sm-6 col-lg-2">
        <div class="summary-card card-balance">
            <div class="card-icon"><i data-feather="credit-card"></i></div>
            <div class="card-label">মোট ব্যালেন্স</div>
            <div class="card-value <?= $totalBalance >= 0 ? 'value-positive' : 'value-negative' ?>">
                ৳<?= number_format($totalBalance, 0) ?>
            </div>
        </div>
    </div>
    
    <!-- Card 2: Total Deposits -->
    <div class="col-6 col-sm-6 col-lg-2">
        <div class="summary-card card-deposits">
            <div class="card-icon"><i data-feather="credit-card"></i></div>
            <div class="card-label">মোট জমা</div>
            <div class="card-value value-positive">
                ৳<?= number_format($totalDeposits, 0) ?>
            </div>
            <div class="card-sub">
                <?php if (!empty($depositBreakdown)): ?>
                    <?php 
                    $labels = ['regular' => 'রেগুলার', 'special' => 'স্পেশাল', 'share' => 'শেয়ার', 'fd' => 'এফডি'];
                    $colors = ['regular' => '#27AE60', 'special' => '#F39C12', 'share' => '#8E44AD', 'fd' => '#2980B9'];
                    $parts = [];
                    $count = 0;
                    foreach ($depositBreakdown as $type => $amount) {
                        if ($count < 2) {
                            $parts[] = '<span class="badge" style="background:' . ($colors[$type] ?? '#888') . ';color:#fff;">' . ($labels[$type] ?? $type) . ': ৳' . number_format($amount, 0) . '</span>';
                        }
                        $count++;
                    }
                    echo implode(' ', $parts);
                    if (count($depositBreakdown) > 2) {
                        echo ' <span class="badge bg-secondary">+' . (count($depositBreakdown) - 2) . ' more</span>';
                    }
                ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Card 3: Total Expenses -->
    <div class="col-6 col-sm-6 col-lg-2">
        <div class="summary-card card-expenses">
            <div class="card-icon"><i data-feather="shopping-bag"></i></div>
            <div class="card-label">মোট খরচ</div>
            <div class="card-value value-negative">
                -৳<?= number_format($totalExpenses, 0) ?>
            </div>
            <div class="card-sub">
                <?php 
                $currentMonthNum = (int)date('n');
                $thisMonthExpense = $monthlyExpenses[$currentMonthNum] ?? 0;
                ?>
                <?php if ($thisMonthExpense > 0): ?>
                এই মাস: ৳<?= number_format($thisMonthExpense, 0) ?>
                <?php else: ?>
                <span class="text-muted">কোন খরচ নেই</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Card 4: Total Withdrawals -->
    <div class="col-6 col-sm-6 col-lg-2">
        <div class="summary-card card-withdrawals">
            <div class="card-icon"><i data-feather="arrow-up-circle"></i></div>
            <div class="card-label">মোট উত্তোলন</div>
            <div class="card-value value-negative">
                -৳<?= number_format($totalWithdrawals, 0) ?>
            </div>
            <div class="card-sub">
                <?php 
                $thisMonthWithdrawal = $monthlyWithdrawals[$currentMonthNum] ?? 0;
                ?>
                <?php if ($thisMonthWithdrawal > 0): ?>
                এই মাস: ৳<?= number_format($thisMonthWithdrawal, 0) ?>
                <?php else: ?>
                <span class="text-muted">কোন উত্তোলন নেই</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Card 5: Total Fines Paid -->
    <div class="col-6 col-sm-6 col-lg-2">
        <div class="summary-card card-fines">
            <div class="card-icon"><i data-feather="alert-triangle"></i></div>
            <div class="card-label">মোট জরিমানা পরিশোধ</div>
            <div class="card-value value-neutral">
                ৳<?= number_format($totalFinesPaid, 0) ?>
            </div>
        </div>
    </div>
    
    <!-- Card 6: Total Investment -->
    <div class="col-6 col-sm-6 col-lg-2">
        <div class="summary-card card-investments">
            <div class="card-icon"><i data-feather="briefcase"></i></div>
            <div class="card-label">মোট বিনিয়োগ আছে</div>
            <div class="card-value">
                ৳<?= number_format($netInvestment, 0) ?>
            </div>
            <div class="card-sub">
                <span class="badge" style="background:#8E44AD;color:#fff;">রিটার্ন: ৳<?= number_format($totalInvestmentReturns, 0) ?></span>
            </div>
        </div>
    </div>
    
</div>

<!-- ============================================
     NET INVESTMENT CARD
     ============================================ -->
<div class="row g-2 g-sm-3 mb-3 mb-md-4">
    <div class="col-12">
        <div class="summary-card net-investment-card" style="border-left: 4px solid #8E44AD; background: linear-gradient(135deg, #F4ECF7, #EBDEF0);">
            <div class="card-icon"><i data-feather="trending-up" style="font-size: 40px;"></i></div>
            <div class="row align-items-center">
                <div class="col-6 col-sm-4 col-md-3">
                    <div class="card-label" style="font-size: 14px; font-weight: 600; color: #6C3483;">
                        <i data-feather="briefcase" style="width:16px;height:16px;"></i>
                       মোট বিনিয়োগ
                    </div>
                    <div class="card-value net" style="font-size: 28px; font-weight: 700; color: #6C3483;">
                        ৳<?= number_format($totalInvestments, 0) ?>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-md-3">
                    <div class="card-label" style="font-size: 12px; color: #6C3483;">নেট বিনিয়োগ</div>
                    <div class="card-value" style="font-size: 20px; font-weight: 600; color: #6C3483;">
                        ৳<?= number_format($netInvestment, 0) ?>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-md-3">
                    <div class="card-label" style="font-size: 12px; color: #6C3483;">মোট রিটার্ন</div>
                    <div class="card-value" style="font-size: 20px; font-weight: 600; color: #27AE60;">
                        +৳<?= number_format($totalInvestmentReturns, 0) ?>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-md-3">
                    <div class="card-label" style="font-size: 12px; color: #6C3483;">ROI</div>
                    <div class="card-value" style="font-size: 20px; font-weight: 600; color: <?= $roi >= 0 ? '#27AE60' : '#E74C3C' ?>;">
                        <?= number_format($roi, 1) ?>%
                        <?php if ($roi > 0): ?>
                        <span style="font-size: 14px;">📈</span>
                        <?php elseif ($roi < 0): ?>
                        <span style="font-size: 14px;">📉</span>
                        <?php else: ?>
                        <span style="font-size: 14px;">➖</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="card-sub mt-2" style="font-size: 11px; color: #6C3483;">
                <i data-feather="info" style="width:12px;height:12px;"></i>
                নেট বিনিয়োগ = মোট বিনিয়োগ - মোট রিটার্ন
                <?php if ($investmentCount > 0): ?>
                | মোট প্রকল্প: <?= $investmentCount ?> টি
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
     BREAKDOWN DETAILS - 4 COLUMN
     ============================================ -->
<div class="row g-2 g-sm-3 mb-3 mb-md-4">
    
    <!-- Deposit Breakdown -->
    <div class="col-6 col-md-3">
        <div class="detail-card">
            <div class="detail-title">
                <i data-feather="layers" style="width:14px;height:14px;"></i>
                জমার ধরন
            </div>
            <?php
            $depositLabels = [
                'regular' => 'রেগুলার',
                'special' => 'স্পেশাল',
                'share' => 'শেয়ার',
                'fd' => 'এফডি'
            ];
            $depositColors = [
                'regular' => '#27AE60',
                'special' => '#F39C12',
                'share' => '#8E44AD',
                'fd' => '#2980B9'
            ];
            ?>
            <?php if (!empty($depositBreakdown)): ?>
                <?php foreach ($depositBreakdown as $type => $amount): ?>
                <div class="detail-item">
                    <span class="label">
                        <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?= $depositColors[$type] ?? '#888' ?>;margin-right:4px;"></span>
                        <?= $depositLabels[$type] ?? ucfirst($type) ?>
                    </span>
                    <span class="value value-positive">৳<?= number_format($amount, 0) ?></span>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
            <div class="text-center text-muted py-2" style="font-size:12px;">কোনো জমা নেই</div>
            <?php endif; ?>
            <div class="detail-item" style="font-weight:700;border-top:2px solid #e0e0e0;padding-top:6px;margin-top:2px;">
                <span class="label">মোট</span>
                <span class="value value-positive">৳<?= number_format($totalDeposits, 0) ?></span>
            </div>
        </div>
    </div>
    
    <!-- Expense Breakdown -->
    <div class="col-6 col-md-3">
        <div class="detail-card">
            <div class="detail-title">
                <i data-feather="shopping-bag" style="width:14px;height:14px;"></i>
                খরচের ধরন
            </div>
            <?php if (!empty($expenseBreakdown)): ?>
                <?php 
                $expenseLabels = [
                    'office' => 'অফিস',
                    'salary' => 'বেতন',
                    'maintenance' => 'রক্ষণাবেক্ষণ',
                    'event' => 'ইভেন্ট',
                    'other' => 'অন্যান্য'
                ];
                $count = 0;
                foreach ($expenseBreakdown as $category => $data):
                    if ($count < 3):
                ?>
                <div class="detail-item">
                    <span class="label">
                        <?= $expenseLabels[$category] ?? ucfirst($category) ?>
                        <span class="sub-text">(<?= $data['count'] ?>)</span>
                    </span>
                    <span class="value value-negative">-৳<?= number_format($data['total'], 0) ?></span>
                </div>
                <?php 
                    endif;
                    $count++;
                endforeach; 
                if (count($expenseBreakdown) > 3):
                ?>
                <div class="detail-item">
                    <span class="label text-muted">+ আরও <?= count($expenseBreakdown) - 3 ?> ধরন</span>
                </div>
                <?php endif; ?>
            <?php else: ?>
            <div class="text-center text-muted py-2" style="font-size:12px;">কোনো খরচ নেই</div>
            <?php endif; ?>
            <div class="detail-item" style="font-weight:700;border-top:2px solid #e0e0e0;padding-top:6px;margin-top:2px;">
                <span class="label">মোট</span>
                <span class="value value-negative">-৳<?= number_format($totalExpenses, 0) ?></span>
            </div>
        </div>
    </div>
    
    <!-- Withdrawal Breakdown -->
    <div class="col-6 col-md-3">
        <div class="detail-card">
            <div class="detail-title">
                <i data-feather="arrow-up-circle" style="width:14px;height:14px;"></i>
                উত্তোলনের ধরন
            </div>
            <?php if (!empty($withdrawalBreakdown)): ?>
                <?php 
                $withdrawalLabels = [
                    'partial' => 'আংশিক',
                    'full' => 'পূর্ণ',
                    'profit' => 'মুনাফা',
                    'emergency' => 'জরুরি'
                ];
                $count = 0;
                foreach ($withdrawalBreakdown as $type => $data):
                    if ($count < 3):
                ?>
                <div class="detail-item">
                    <span class="label">
                        <?= $withdrawalLabels[$type] ?? ucfirst($type) ?>
                        <span class="sub-text">(<?= $data['count'] ?>)</span>
                    </span>
                    <span class="value value-negative">-৳<?= number_format($data['total'], 0) ?></span>
                </div>
                <?php 
                    endif;
                    $count++;
                endforeach;
                if (count($withdrawalBreakdown) > 3):
                ?>
                <div class="detail-item">
                    <span class="label text-muted">+ আরও <?= count($withdrawalBreakdown) - 3 ?> ধরন</span>
                </div>
                <?php endif; ?>
            <?php else: ?>
            <div class="text-center text-muted py-2" style="font-size:12px;">কোনো উত্তোলন নেই</div>
            <?php endif; ?>
            <div class="detail-item" style="font-weight:700;border-top:2px solid #e0e0e0;padding-top:6px;margin-top:2px;">
                <span class="label">মোট</span>
                <span class="value value-negative">-৳<?= number_format($totalWithdrawals, 0) ?></span>
            </div>
        </div>
    </div>
    
    <!-- Investment Summary -->
    <div class="col-6 col-md-3">
        <div class="detail-card">
            <div class="detail-title">
                <i data-feather="briefcase" style="width:14px;height:14px;"></i>
                বিনিয়োগ সারাংশ
            </div>
            <div class="detail-item">
                <span class="label">মোট বিনিয়োগ</span>
                <span class="value" style="color:#6C3483;">-৳<?= number_format($totalInvestments, 0) ?></span>
            </div>
            <div class="detail-item">
                <span class="label">মোট রিটার্ন</span>
                <span class="value value-positive">+৳<?= number_format($totalInvestmentReturns, 0) ?></span>
            </div>
            <div class="detail-item" style="font-weight:700;border-top:2px solid #e0e0e0;padding-top:6px;margin-top:2px;background:#F4ECF7;border-radius:4px;padding:6px 8px;">
                <span class="label" style="font-weight:700;color:#6C3483;">
                    <i data-feather="trending-up" style="width:12px;height:12px;"></i>
                    নেট বিনিয়োগ
                </span>
                <span class="value" style="font-weight:700;color:#6C3483;">
                    ৳<?= number_format($netInvestment, 0) ?>
                </span>
            </div>
            <div class="detail-item">
                <span class="label">মোট প্রকল্প</span>
                <span class="value"><?= $investmentCount ?></span>
            </div>
            <?php if ($totalInvestments > 0): ?>
            <div class="detail-item">
                <span class="label">ROI</span>
                <span class="value <?= $roi >= 0 ? 'value-positive' : 'value-negative' ?>">
                    <?= number_format($roi, 1) ?>%
                    <?php if ($roi > 0): ?>
                    <span style="font-size:12px;">📈</span>
                    <?php elseif ($roi < 0): ?>
                    <span style="font-size:12px;">📉</span>
                    <?php endif; ?>
                </span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
</div>

<!-- ============================================
     MONTHLY CHARTS - WITH HORIZONTAL SCROLL ON MOBILE
     ============================================ -->
<div class="row g-2 g-sm-3 mb-3 mb-md-4">
    <!-- Chart 1: Monthly Deposits -->
    <div class="col-6 col-md-3">
        <div class="detail-card">
            <div class="detail-title">
                <i data-feather="bar-chart-2" style="width:14px;height:14px;"></i>
                মাসিক জমা
            </div>
            <?php if (!empty($monthlyDeposits)): ?>
            <?php 
            $maxDeposit = max($monthlyDeposits);
            $monthNames = ['জানু', 'ফেব্রু', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];
            $shortMonthNames = ['জা', 'ফে', 'মা', 'এ', 'মে', 'জু', 'জু', 'আ', 'সে', 'অ', 'ন', 'ডি'];
            ?>
            <div class="chart-wrapper">
                <div class="chart-bar-container">
                    <?php for ($m = 1; $m <= 12; $m++): 
                        $amount = $monthlyDeposits[$m] ?? 0;
                        $height = $maxDeposit > 0 ? ($amount / $maxDeposit) * 100 : 0;
                    ?>
                    <div class="chart-bar-wrapper">
                        <div class="chart-bar-value"><?= $amount > 0 ? '৳'.number_format($amount,0) : '' ?></div>
                        <div class="chart-bar" style="height:<?= max(2, $height) ?>%;background:<?= $amount > 0 ? '#2E86C1' : '#f0f0f0' ?>;min-height:<?= $amount > 0 ? '6px' : '3px' ?>;"></div>
                        <div class="chart-bar-label">
                            <?php 
                            if (strlen((string)$monthNames[$m-1]) > 4) {
                                echo $shortMonthNames[$m-1];
                            } else {
                                echo $monthNames[$m-1];
                            }
                            ?>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
            <div class="text-center text-muted" style="font-size:10px;">
                মোট: ৳<?= number_format(array_sum($monthlyDeposits), 0) ?>
            </div>
            <?php else: ?>
            <div class="text-center text-muted py-2" style="font-size:12px;">কোনো ডেটা নেই</div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Chart 2: Monthly Expenses -->
    <div class="col-6 col-md-3">
        <div class="detail-card">
            <div class="detail-title">
                <i data-feather="bar-chart-2" style="width:14px;height:14px;"></i>
                মাসিক খরচ
            </div>
            <?php if (!empty($monthlyExpenses)): ?>
            <?php 
            $maxExpense = max($monthlyExpenses);
            $monthNames = ['জানু', 'ফেব্রু', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];
            $shortMonthNames = ['জা', 'ফে', 'মা', 'এ', 'মে', 'জু', 'জু', 'আ', 'সে', 'অ', 'ন', 'ডি'];
            ?>
            <div class="chart-wrapper">
                <div class="chart-bar-container">
                    <?php for ($m = 1; $m <= 12; $m++): 
                        $amount = $monthlyExpenses[$m] ?? 0;
                        $height = $maxExpense > 0 ? ($amount / $maxExpense) * 100 : 0;
                    ?>
                    <div class="chart-bar-wrapper">
                        <div class="chart-bar-value"><?= $amount > 0 ? '৳'.number_format($amount,0) : '' ?></div>
                        <div class="chart-bar" style="height:<?= max(2, $height) ?>%;background:<?= $amount > 0 ? '#E74C3C' : '#f0f0f0' ?>;min-height:<?= $amount > 0 ? '6px' : '3px' ?>;"></div>
                        <div class="chart-bar-label">
                            <?php 
                            if (strlen((string)$monthNames[$m-1]) > 4) {
                                echo $shortMonthNames[$m-1];
                            } else {
                                echo $monthNames[$m-1];
                            }
                            ?>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
            <div class="text-center text-muted" style="font-size:10px;">
                মোট: ৳<?= number_format(array_sum($monthlyExpenses), 0) ?>
            </div>
            <?php else: ?>
            <div class="text-center text-muted py-2" style="font-size:12px;">কোনো ডেটা নেই</div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Chart 3: Monthly Withdrawals -->
    <div class="col-6 col-md-3">
        <div class="detail-card">
            <div class="detail-title">
                <i data-feather="bar-chart-2" style="width:14px;height:14px;"></i>
                মাসিক উত্তোলন
            </div>
            <?php if (!empty($monthlyWithdrawals)): ?>
            <?php 
            $maxWithdrawal = max($monthlyWithdrawals);
            $monthNames = ['জানু', 'ফেব্রু', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];
            $shortMonthNames = ['জা', 'ফে', 'মা', 'এ', 'মে', 'জু', 'জু', 'আ', 'সে', 'অ', 'ন', 'ডি'];
            ?>
            <div class="chart-wrapper">
                <div class="chart-bar-container">
                    <?php for ($m = 1; $m <= 12; $m++): 
                        $amount = $monthlyWithdrawals[$m] ?? 0;
                        $height = $maxWithdrawal > 0 ? ($amount / $maxWithdrawal) * 100 : 0;
                    ?>
                    <div class="chart-bar-wrapper">
                        <div class="chart-bar-value"><?= $amount > 0 ? '৳'.number_format($amount,0) : '' ?></div>
                        <div class="chart-bar" style="height:<?= max(2, $height) ?>%;background:<?= $amount > 0 ? '#F39C12' : '#f0f0f0' ?>;min-height:<?= $amount > 0 ? '6px' : '3px' ?>;"></div>
                        <div class="chart-bar-label">
                            <?php 
                            if (strlen((string)$monthNames[$m-1]) > 4) {
                                echo $shortMonthNames[$m-1];
                            } else {
                                echo $monthNames[$m-1];
                            }
                            ?>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
            <div class="text-center text-muted" style="font-size:10px;">
                মোট: ৳<?= number_format(array_sum($monthlyWithdrawals), 0) ?>
            </div>
            <?php else: ?>
            <div class="text-center text-muted py-2" style="font-size:12px;">কোনো ডেটা নেই</div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Chart 4: Monthly Investments -->
    <div class="col-6 col-md-3">
        <div class="detail-card">
            <div class="detail-title">
                <i data-feather="bar-chart-2" style="width:14px;height:14px;"></i>
                মাসিক বিনিয়োগ
            </div>
            <?php if (!empty($monthlyInvestments)): ?>
            <?php 
            $maxInvestment = max($monthlyInvestments);
            $monthNames = ['জানু', 'ফেব্রু', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];
            $shortMonthNames = ['জা', 'ফে', 'মা', 'এ', 'মে', 'জু', 'জু', 'আ', 'সে', 'অ', 'ন', 'ডি'];
            ?>
            <div class="chart-wrapper">
                <div class="chart-bar-container">
                    <?php for ($m = 1; $m <= 12; $m++): 
                        $amount = $monthlyInvestments[$m] ?? 0;
                        $height = $maxInvestment > 0 ? ($amount / $maxInvestment) * 100 : 0;
                    ?>
                    <div class="chart-bar-wrapper">
                        <div class="chart-bar-value"><?= $amount > 0 ? '৳'.number_format($amount,0) : '' ?></div>
                        <div class="chart-bar" style="height:<?= max(2, $height) ?>%;background:<?= $amount > 0 ? '#8E44AD' : '#f0f0f0' ?>;min-height:<?= $amount > 0 ? '6px' : '3px' ?>;"></div>
                        <div class="chart-bar-label">
                            <?php 
                            if (strlen((string)$monthNames[$m-1]) > 4) {
                                echo $shortMonthNames[$m-1];
                            } else {
                                echo $monthNames[$m-1];
                            }
                            ?>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
            <div class="text-center text-muted" style="font-size:10px;">
                মোট: ৳<?= number_format(array_sum($monthlyInvestments), 0) ?>
            </div>
            <?php else: ?>
            <div class="text-center text-muted py-2" style="font-size:12px;">কোনো ডেটা নেই</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ============================================
     SCROLLABLE DIVS - 4 COLUMN (জমা, খরচ, উত্তোলন, জরিমানা)
     SHOWING ALL 10 RECORDS IN EACH SECTION
     ============================================ -->
<div class="row g-2 g-sm-3 mb-3 mb-md-4">
    
    <!-- Recent Deposits - Show ALL 10 -->
    <div class="col-6 col-md-3">
        <div class="card h-100 card-scrollable">
            <div class="card-header" style="padding: 8px 12px;">
                <div style="display:flex; align-items:center; gap:6px; width:100%;">
                    <i data-feather="credit-card" style="color:#2E86C1;width:14px;height:14px;"></i>
                    <span style="font-weight:600; font-size:12px; color:#2C3E50;"> জমা</span>
                    <span style="font-weight:700; color:#1B4F72; font-size:13px; margin-left:auto; text-align:center; flex:1;">
                        ৳<?= number_format($totalDeposits, 0) ?>
                    </span>
                    <span class="badge" style="background:#2E86C1; color:white; border-radius:50%; width:26px; height:26px; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; padding:0; flex-shrink:0;">
                        <?= count($latestDeposits) ?>
                    </span>
                </div>
            </div>
            <div class="scrollable-list">
                <?php if (empty($latestDeposits)): ?>
                <div class="text-center text-muted py-3" style="font-size:12px;">কোনো জমা নেই</div>
                <?php else: ?>
                <?php foreach ($latestDeposits as $dep): ?>
                <div class="list-item" style="padding:5px 10px;">
                    <div class="item-info">
                        <?= getMemberAvatar($dep['member_id'] ?? null, $dep['member_photo'] ?? null, $dep['member_name'] ?? '', 24) ?>
                        <div class="details">
                            <div class="name" style="font-size:10px;"><?= clean($dep['member_name'] ?? '') ?></div>
                            <div class="sub" style="font-size:8px;">
                                <?= date('d M Y', strtotime($dep['deposit_date'])) ?>
                                <?php if (!empty($dep['deposit_type'])): ?>
                                <span class="badge" style="background:#2E86C1;color:#fff;font-size:7px;padding:1px 4px;">
                                    <?= ucfirst($dep['deposit_type']) ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="item-amount value-positive" style="font-size:10px;">+৳<?= number_format($dep['amount'], 0) ?></div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Recent Expenses - Show ALL 10 -->
    <div class="col-6 col-md-3">
        <div class="card h-100 card-scrollable">
            <div class="card-header" style="padding: 8px 12px;">
                <div style="display:flex; align-items:center; gap:6px; width:100%;">
                    <i data-feather="shopping-bag" style="color:#E74C3C;width:14px;height:14px;"></i>
                    <span style="font-weight:600; font-size:12px; color:#2C3E50;"> খরচ</span>
                    <span style="font-weight:700; color:#C0392B; font-size:13px; text-align:center; flex:1;">
                        -৳<?= number_format($totalExpenses, 0) ?>
                    </span>
                    <span class="badge" style="background:#E74C3C; color:white; border-radius:50%; width:26px; height:26px; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; padding:0; flex-shrink:0;">
                        <?= count($recentExpenses) ?>
                    </span>
                </div>
            </div>
            <div class="scrollable-list">
                <?php if (empty($recentExpenses)): ?>
                <div class="text-center text-muted py-3" style="font-size:12px;">কোনো খরচ নেই</div>
                <?php else: ?>
                <?php foreach ($recentExpenses as $exp): ?>
                <div class="list-item" style="padding:5px 10px;">
                    <div class="item-info">
                        <div style="width:24px;height:24px;border-radius:50%;background:#fce4ec;display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0;">💸</div>
                        <div class="details">
                            <div class="name" style="font-size:10px;"><?= clean($exp['title'] ?? '') ?></div>
                            <div class="sub" style="font-size:8px;">
                                <?= date('d M Y', strtotime($exp['expense_date'])) ?>
                                <?php if (!empty($exp['category'])): ?>
                                <span class="badge" style="background:#E74C3C;color:#fff;font-size:7px;padding:1px 4px;">
                                    <?= ucfirst($exp['category']) ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="item-amount value-negative" style="font-size:10px;">-৳<?= number_format($exp['amount'], 0) ?></div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Recent Withdrawals - Show ALL 10 -->
    <div class="col-6 col-md-3">
        <div class="card h-100 card-scrollable">
            <div class="card-header" style="padding: 8px 12px;">
                <div style="display:flex; align-items:center; gap:6px; width:100%;">
                    <i data-feather="arrow-up-circle" style="color:#F39C12;width:14px;height:14px;"></i>
                    <span style="font-weight:600; font-size:12px; color:#2C3E50;"> উত্তোলন</span>
                    <span style="font-weight:700; color:#D35400; font-size:13px; text-align:center; flex:1;">
                        -৳<?= number_format($totalWithdrawals, 0) ?>
                    </span>
                    <span class="badge" style="background:#F39C12; color:white; border-radius:50%; width:26px; height:26px; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; padding:0; flex-shrink:0;">
                        <?= count($recentWithdrawals) ?>
                    </span>
                </div>
            </div>
            <div class="scrollable-list">
                <?php if (empty($recentWithdrawals)): ?>
                <div class="text-center text-muted py-3" style="font-size:12px;">কোনো উত্তোলন নেই</div>
                <?php else: ?>
                <?php foreach ($recentWithdrawals as $w): ?>
                <div class="list-item" style="padding:5px 10px;">
                    <div class="item-info">
                        <?= getMemberAvatar($w['member_id'] ?? null, $w['member_photo'] ?? null, $w['member_name'] ?? '', 24) ?>
                        <div class="details">
                            <div class="name" style="font-size:10px;"><?= clean($w['member_name'] ?? '') ?></div>
                            <div class="sub" style="font-size:8px;">
                                <?= date('d M Y', strtotime($w['withdrawal_date'])) ?>
                                <?php if (!empty($w['withdrawal_type'])): ?>
                                <span class="badge" style="background:#F39C12;color:#fff;font-size:7px;padding:1px 4px;">
                                    <?= ucfirst($w['withdrawal_type']) ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="item-amount value-negative" style="font-size:10px;">-৳<?= number_format($w['amount'], 0) ?></div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Recent Fine Payments - Show ALL 10 -->
    <div class="col-6 col-md-3">
        <div class="card h-100 card-scrollable">
            <div class="card-header" style="padding: 8px 12px;">
                <div style="display:flex; align-items:center; gap:6px; width:100%;">
                    <i data-feather="alert-triangle" style="color:#8E44AD;width:14px;height:14px;"></i>
                    <span style="font-weight:600; font-size:12px; color:#2C3E50;"> জরিমানা</span>
                    <span style="font-weight:700; color:#6C3483; font-size:13px; text-align:center; flex:1;">
                        ৳<?= number_format($totalFinesPaid, 0) ?>
                    </span>
                    <span class="badge" style="background:#8E44AD; color:white; border-radius:50%; width:26px; height:26px; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; padding:0; flex-shrink:0;">
                        <?= count($finePaymentsList) ?>
                    </span>
                </div>
            </div>
            <div class="scrollable-list">
                <?php if (empty($finePaymentsList)): ?>
                <div class="text-center text-muted py-3" style="font-size:12px;">কোনো জরিমানা পরিশোধ নেই</div>
                <?php else: ?>
                <?php foreach ($finePaymentsList as $fp): ?>
                <div class="list-item" style="padding:5px 10px;">
                    <div class="item-info">
                        <?= getMemberAvatar($fp['member_id'] ?? null, $fp['member_photo'] ?? null, $fp['member_name'] ?? '', 24) ?>
                        <div class="details">
                            <div class="name" style="font-size:10px;"><?= clean($fp['member_name'] ?? '') ?></div>
                            <div class="sub" style="font-size:8px;">
                                <?= date('d M Y', strtotime($fp['payment_date'])) ?>
                                <span class="badge" style="background:#8E44AD;color:#fff;font-size:7px;padding:1px 4px;">
                                    পরিশোধ
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="item-amount value-neutral" style="font-size:10px;">৳<?= number_format($fp['amount'], 0) ?></div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
</div>
<!-- ============================================
     QUICK SUMMARY FOOTER
     ============================================ -->
<div class="row mt-3 mt-md-4">
    <div class="col-12">
        <div class="card" style="background: #f8f9fa;">
            <div class="card-body text-center text-muted" style="font-size:11px;padding:10px 12px;">
                <i data-feather="info" style="width:14px;height:14px;"></i>
                ডেটা শেষ আপডেট: <?= date('d M Y, h:i A') ?>
                <span class="d-none d-sm-inline mx-2">|</span>
                <br class="d-sm-none">
                <strong>দেখানো হচ্ছে:</strong> <?= $yearLabel ?>
                <span class="d-none d-sm-inline mx-2">|</span>
                <br class="d-sm-none">
                <strong>মোট সদস্য:</strong> <?= $totalMembers ?>
                <span class="d-none d-sm-inline mx-2">|</span>
                <br class="d-sm-none">
                <strong>নেট ব্যালেন্স:</strong> 
                <span class="<?= $totalBalance >= 0 ? 'text-success' : 'text-danger' ?>">
                    ৳<?= number_format($totalBalance, 0) ?>
                </span>
                <span class="d-none d-sm-inline mx-2">|</span>
                <br class="d-sm-none">
                <strong>নেট বিনিয়োগ:</strong>
                <span class="<?= $netInvestment >= 0 ? 'text-success' : 'text-danger' ?>">
                    ৳<?= number_format($netInvestment, 0) ?>
                </span>
            </div>
        </div>
    </div>
</div>

</div><!-- /container-fluid -->

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>