<?php
// =====================================================
// admin/index.php - Complete Dashboard (WITH INVESTMENT RETURNS & FINES PAID)
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

$pageTitle = 'ড্যাশবোর্ড';
$conn = conn();

// ============================================
// HELPER FUNCTION FOR AVATAR GENERATION
// ============================================
function getAvatarColor($name) {
    $colors = ['#1B4F72', '#2E86C1', '#117A65', '#C0392B', '#8E44AD', '#D35400', '#27AE60', '#2980B9', '#E67E22', '#16A085'];
    $hash = 0;
    for ($i = 0; $i < strlen($name); $i++) {
        $hash = ($hash * 31 + ord($name[$i])) & 0xFFFFFFFF;
    }
    return $colors[abs($hash) % count($colors)];
}

function generateAvatar($name, $size = 32) {
    $firstLetter = mb_substr($name, 0, 1, 'UTF-8');
    $color = getAvatarColor($name);
    
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 ' . $size . ' ' . $size . '">';
    $svg .= '<rect width="' . $size . '" height="' . $size . '" rx="' . ($size/2) . '" fill="' . $color . '"/>';
    $fontSize = $size * 0.5;
    $svg .= '<text x="' . ($size/2) . '" y="' . ($size/2 + $size * 0.04) . '" ';
    $svg .= 'font-family="Arial, sans-serif" font-size="' . $fontSize . '" font-weight="bold" fill="white" text-anchor="middle" dominant-baseline="central">';
    $svg .= htmlspecialchars($firstLetter);
    $svg .= '</text>';
    $svg .= '</svg>';
    
    return 'data:image/svg+xml;charset=utf-8,' . rawurlencode($svg);
}

function getMemberPhoto($member) {
    if (!empty($member['photo']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $member['photo'])) {
        return $member['photo'];
    }
    return generateAvatar($member['name'], 32);
}

// ============================================
// FIX: Use Previous Month for calculations
// ============================================
$currentMonth = date('m');
$currentYear = date('Y');

// Calculate previous month
$prevMonth = $currentMonth - 1;
$prevYear = $currentYear;
if ($prevMonth == 0) {
    $prevMonth = 12;
    $prevYear = $currentYear - 1;
}

// Format month name
$monthName = date('F Y', mktime(0, 0, 0, $prevMonth, 1, $prevYear));

// ============================================
// 1. BASIC STATS
// ============================================
$totalMembers = 0;
$totalDeposits = 0;
$totalWithdrawals = 0;
$totalLoans = 0;
$totalDues = 0;
$totalExpenses = 0;
$totalProfit = 0;

// ============================================
// 2. GET REGULAR DEPOSITS (EXCLUDING FINES)
// ============================================
$regularDeposits = 0;
try {
    $result = $conn->query("
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM deposits 
        WHERE deposit_type IN ('regular', 'special', 'share', 'fd') 
        OR deposit_type IS NULL 
        OR deposit_type = ''
    ");
    if ($result) {
        $row = $result->fetch_assoc();
        $regularDeposits = (float)($row['total'] ?? 0);
    }
} catch (Exception $e) {
    $regularDeposits = 0;
}

// ============================================
// 3. GET FINES PAID (FROM FINES TABLE - STATUS 'paid')
// ============================================
$totalFinesPaid = 0;
try {
    $tableCheck = $conn->query("SHOW TABLES LIKE 'fines'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        $result = $conn->query("SELECT COALESCE(SUM(paid_amount), 0) as total FROM fines WHERE status = 'paid'");
        if ($result) {
            $row = $result->fetch_assoc();
            $totalFinesPaid = (float)($row['total'] ?? 0);
        }
    }
} catch (Exception $e) {
    $totalFinesPaid = 0;
}

// ============================================
// 4. GET TOTAL INVESTMENT AMOUNT & RETURNS
// ============================================
$totalInvestmentAmount = 0;
$totalInvestmentReturns = 0;

try {
    $tableCheck = $conn->query("SHOW TABLES LIKE 'investments'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        $result = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM investments");
        if ($result) {
            $row = $result->fetch_assoc();
            $totalInvestmentAmount = (float)($row['total'] ?? 0);
        }
    }
} catch (Exception $e) {
    $totalInvestmentAmount = 0;
}

try {
    $tableCheck = $conn->query("SHOW TABLES LIKE 'investment_returns'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        $result = $conn->query("SELECT COALESCE(SUM(return_amount), 0) as total FROM investment_returns");
        if ($result) {
            $row = $result->fetch_assoc();
            $totalInvestmentReturns = (float)($row['total'] ?? 0);
        }
    }
} catch (Exception $e) {
    $totalInvestmentReturns = 0;
}

// ============================================
// 5. GET WITHDRAWALS
// ============================================
try {
    $result = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM withdrawals WHERE status = 'completed'");
    if ($result) {
        $row = $result->fetch_assoc();
        $totalWithdrawals = (float)($row['total'] ?? 0);
    }
} catch (Exception $e) {
    $totalWithdrawals = 0;
}

// ============================================
// 6. GET EXPENSES (ALL YEARS)
// ============================================
try {
    $result = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM expenses");
    if ($result) {
        $row = $result->fetch_assoc();
        $totalExpenses = (float)($row['total'] ?? 0);
    }
} catch (Exception $e) {
    $totalExpenses = 0;
}
// ============================================
// 7. CALCULATE NET SAVINGS
// Formula: (Regular Deposits + Fines Paid + Investment Returns) - (Withdrawals + Expenses + Investment Amount)
// ============================================
$totalIncome = $regularDeposits + $totalFinesPaid + $totalInvestmentReturns;
$totalOutgoing = $totalWithdrawals + $totalExpenses + $totalInvestmentAmount;
$netSavings = $totalIncome - $totalOutgoing;

// ============================================
// 8. FINE STATISTICS - FROM 'fines' TABLE
// ============================================
$totalPendingFines = 0;
$totalFineAmount = 0;
$totalFinePaid = 0;
$totalFineWaived = 0;
$pendingFineCount = 0;
$partiallyPaidFineCount = 0;
$paidFineCount = 0;
$waivedFineCount = 0;
$overdueFines = 0;

$pendingFineAmount = 0;
$partialFineAmount = 0;
$paidFineAmount = 0;
$waivedFineAmount = 0;
$overdueFineAmount = 0;

try {
    $tableCheck = $conn->query("SHOW TABLES LIKE 'fines'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        
        // Total pending fines amount
        $result = $conn->query("
            SELECT 
                COUNT(*) as count,
                COALESCE(SUM(fine_amount - paid_amount - waived_amount), 0) as total_due,
                COALESCE(SUM(fine_amount), 0) as total_fine,
                COALESCE(SUM(paid_amount), 0) as total_paid,
                COALESCE(SUM(waived_amount), 0) as total_waived
            FROM fines 
            WHERE status IN ('pending', 'partially_paid')
        ");
        if ($result) {
            $row = $result->fetch_assoc();
            $totalPendingFines = $row['total_due'] ?? 0;
            $totalFineAmount = $row['total_fine'] ?? 0;
            $totalFinePaid = $row['total_paid'] ?? 0;
            $totalFineWaived = $row['total_waived'] ?? 0;
            $pendingFineCount = $row['count'] ?? 0;
        }

        // Count by status
        $result = $conn->query("SELECT status, COUNT(*) as count FROM fines GROUP BY status");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                if ($row['status'] == 'pending') $pendingFineCount = $row['count'];
                elseif ($row['status'] == 'partially_paid') $partiallyPaidFineCount = $row['count'];
                elseif ($row['status'] == 'paid') $paidFineCount = $row['count'];
                elseif ($row['status'] == 'waived') $waivedFineCount = $row['count'];
            }
        }

        // Pending amount
        $result = $conn->query("SELECT COALESCE(SUM(fine_amount - paid_amount - waived_amount),0) as total FROM fines WHERE status = 'pending'");
        if ($result) {
            $row = $result->fetch_assoc();
            $pendingFineAmount = $row['total'] ?? 0;
        }
        
        // Partial amount
        $result = $conn->query("SELECT COALESCE(SUM(fine_amount - paid_amount - waived_amount),0) as total FROM fines WHERE status = 'partially_paid'");
        if ($result) {
            $row = $result->fetch_assoc();
            $partialFineAmount = $row['total'] ?? 0;
        }
        
        // Paid amount
        $result = $conn->query("SELECT COALESCE(SUM(paid_amount),0) as total FROM fines WHERE status = 'paid'");
        if ($result) {
            $row = $result->fetch_assoc();
            $paidFineAmount = $row['total'] ?? 0;
        }
        
        // Waived amount
        $result = $conn->query("SELECT COALESCE(SUM(waived_amount),0) as total FROM fines WHERE status = 'waived'");
        if ($result) {
            $row = $result->fetch_assoc();
            $waivedFineAmount = $row['total'] ?? 0;
        }

        // Overdue fines
        $result = $conn->query("
            SELECT COUNT(*) as count, COALESCE(SUM(fine_amount - paid_amount - waived_amount),0) as total
            FROM fines 
            WHERE due_date < CURDATE() 
            AND status IN ('pending', 'partially_paid')
        ");
        if ($result) {
            $row = $result->fetch_assoc();
            $overdueFines = $row['count'] ?? 0;
            $overdueFineAmount = $row['total'] ?? 0;
        }
    }
} catch (Exception $e) {
    error_log("Fine statistics error: " . $e->getMessage());
}

// ============================================
// 9. GET PENDING FINES LIST
// ============================================
$pendingFineList = [];

try {
    $tableCheck = $conn->query("SHOW TABLES LIKE 'fines'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        $result = $conn->query("
            SELECT 
                f.*,
                m.name as member_name,
                m.member_id as member_code,
                m.phone,
                m.photo as member_photo,
                (f.fine_amount - f.paid_amount - f.waived_amount) as remaining_amount
            FROM fines f
            JOIN members m ON f.member_id = m.id
            WHERE f.status IN ('pending', 'partially_paid')
            ORDER BY f.due_date ASC
            LIMIT 10
        ");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $pendingFineList[] = $row;
            }
        }
    }
} catch (Exception $e) {
    error_log("Pending fines error: " . $e->getMessage());
}

// ============================================
// 10. GET RECENT FINE PAYMENTS
// ============================================
$recentFineTransactions = [];

try {
    $tableCheck = $conn->query("SHOW TABLES LIKE 'fine_payments'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        $result = $conn->query("
            SELECT 
                fp.*,
                f.id as fine_id,
                f.member_id,
                f.month_year,
                f.fine_amount,
                f.status as fine_status,
                m.name as member_name,
                m.member_id as member_code,
                m.photo as member_photo
            FROM fine_payments fp
            JOIN fines f ON fp.fine_id = f.id
            JOIN members m ON f.member_id = m.id
            ORDER BY fp.payment_date DESC, fp.created_at DESC
            LIMIT 10
        ");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $recentFineTransactions[] = $row;
            }
        }
    }
} catch (Exception $e) {
    error_log("Fine payments error: " . $e->getMessage());
}

// ============================================
// 11. GET TOTAL MEMBERS
// ============================================
try {
    $result = $conn->query("SELECT COUNT(*) as count FROM members WHERE status = 'active'");
    if ($result) {
        $row = $result->fetch_assoc();
        $totalMembers = $row['count'] ?? 0;
    }
} catch (Exception $e) {}

// ============================================
// 12. MONTHLY DEPOSIT INFO (Previous Month)
// ============================================
$depositedThisMonth = 0;
$totalThisMonthDeposit = 0;
$depositedMembers = [];
$notDepositedMembers = [];

try {
    $result = $conn->query("
        SELECT COUNT(DISTINCT member_id) as count, COALESCE(SUM(amount),0) as total 
        FROM deposits 
        WHERE MONTH(deposit_date) = $prevMonth AND YEAR(deposit_date) = $prevYear
    ");
    if ($result) {
        $row = $result->fetch_assoc();
        $depositedThisMonth = $row['count'] ?? 0;
        $totalThisMonthDeposit = $row['total'] ?? 0;
    }
    
    $result = $conn->query("
        SELECT m.id, m.name, m.member_id, m.phone, m.monthly_deposit, m.photo,
            d.amount, d.deposit_date
        FROM members m
        INNER JOIN deposits d ON d.member_id = m.id
        WHERE m.status = 'active' 
        AND MONTH(d.deposit_date) = $prevMonth 
        AND YEAR(d.deposit_date) = $prevYear
        AND d.id IN (
            SELECT MAX(id) FROM deposits 
            WHERE member_id = m.id 
            AND MONTH(deposit_date) = $prevMonth 
            AND YEAR(deposit_date) = $prevYear
            GROUP BY member_id
        )
        ORDER BY d.deposit_date DESC
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $depositedMembers[] = $row;
        }
    }
    
    $result = $conn->query("
        SELECT id, name, member_id, phone, monthly_deposit, photo
        FROM members 
        WHERE status = 'active' AND id NOT IN (
            SELECT DISTINCT member_id FROM deposits 
            WHERE MONTH(deposit_date) = $prevMonth AND YEAR(deposit_date) = $prevYear
        )
        ORDER BY name ASC
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $notDepositedMembers[] = $row;
        }
    }
    
} catch (Exception $e) {}

$notDepositedThisMonth = count($notDepositedMembers);

// ============================================
// 13. MONTHLY WITHDRAWAL INFO
// ============================================
$withdrawnThisMonth = 0;
$totalThisMonthWithdrawal = 0;
$withdrawnMembers = [];

try {
    $result = $conn->query("
        SELECT COUNT(DISTINCT member_id) as count, COALESCE(SUM(amount),0) as total 
        FROM withdrawals 
        WHERE MONTH(withdrawal_date) = $prevMonth AND YEAR(withdrawal_date) = $prevYear AND status='completed'
    ");
    if ($result) {
        $row = $result->fetch_assoc();
        $withdrawnThisMonth = $row['count'] ?? 0;
        $totalThisMonthWithdrawal = $row['total'] ?? 0;
    }
    
    $result = $conn->query("
        SELECT m.id, m.name, m.member_id, m.photo,
            w.amount, w.withdrawal_date
        FROM members m
        INNER JOIN withdrawals w ON w.member_id = m.id
        WHERE m.status = 'active' 
        AND MONTH(w.withdrawal_date) = $prevMonth 
        AND YEAR(w.withdrawal_date) = $prevYear 
        AND w.status='completed'
        AND w.id IN (
            SELECT MAX(id) FROM withdrawals 
            WHERE member_id = m.id 
            AND MONTH(withdrawal_date) = $prevMonth 
            AND YEAR(withdrawal_date) = $prevYear
            AND status='completed'
            GROUP BY member_id
        )
        ORDER BY w.withdrawal_date DESC
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $withdrawnMembers[] = $row;
        }
    }
} catch (Exception $e) {}

// ============================================
// 14. RECENT DEPOSITS & WITHDRAWALS
// ============================================
$recentDeposits = [];
$recentWithdrawals = [];

try {
    $result = $conn->query("
        SELECT d.*, m.name as member_name, m.member_id as member_code, m.photo as member_photo
        FROM deposits d
        JOIN members m ON m.id = d.member_id
        ORDER BY d.deposit_date DESC, d.id DESC
        LIMIT 10
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recentDeposits[] = $row;
        }
    }
    
    $result = $conn->query("
        SELECT w.*, m.name as member_name, m.member_id as member_code, m.photo as member_photo
        FROM withdrawals w
        JOIN members m ON m.id = w.member_id
        WHERE w.status = 'completed'
        ORDER BY w.withdrawal_date DESC, w.id DESC
        LIMIT 10
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recentWithdrawals[] = $row;
        }
    }
} catch (Exception $e) {}

// ============================================
// 15. RECENT MEMBERS WITH PHOTOS
// ============================================
$recentMembers = [];
try {
    $result = $conn->query("SELECT id, member_id, name, phone, photo, join_date, status FROM members ORDER BY created_at DESC LIMIT 10");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recentMembers[] = $row;
        }
    }
} catch (Exception $e) {}

// ============================================
// 16. UPCOMING BIRTHDAYS
// ============================================
$upcomingBirthdays = [];
try {
    $result = $conn->query("
        SELECT id, member_id, name, phone, photo, dob 
        FROM members 
        WHERE status = 'active' 
        AND dob IS NOT NULL 
        AND DATE_FORMAT(dob, '%m-%d') BETWEEN DATE_FORMAT(NOW(), '%m-%d') AND DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 7 DAY), '%m-%d')
        ORDER BY DATE_FORMAT(dob, '%m-%d') ASC
        LIMIT 5
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $upcomingBirthdays[] = $row;
        }
    }
} catch (Exception $e) {}

// Get admin info
$admin = getCurrentAdmin();
$somity_name = getSetting('somity_name', 'সোমিটি');

// Include header
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-3">

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h4>
        <i data-feather="home" style="width:20px;height:20px"></i> 
        ড্যাশবোর্ড
        <small class="text-muted">স্বাগতম, <?= clean($admin['name'] ?? 'অ্যাডমিন') ?></small>
    </h4>
    <div>
        <span class="badge bg-primary"><?= date('d M Y') ?></span>
        <span class="badge bg-info ms-2"><?= $monthName ?> এর ডেটা</span>
        <a href="/admin/full_reports.php" class="btn btn-success btn-sm ms-2" title="সব পরিসংখ্যান দেখুন">
            <i data-feather="bar-chart-2" style="width:14px;height:14px"></i>
            সম্পূর্ণ পরিসংখ্যান
        </a>
    </div>
</div>

<!-- ============================================
     STATS CARDS - ROW 1 (Deposits & Members)
     ============================================ -->
<div class="row g-2 g-md-3 mb-4">
    <div class="col-6 col-md-4 col-lg-2">
        <div class="stat-card bg-primary-grad text-center">
            <div class="stat-icon"><i data-feather="users"></i></div>
            <div class="stat-value"><?= $totalMembers ?> জন</div>
            <div class="stat-label">সক্রিয় সদস্য</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="stat-card bg-success-grad text-center">
            <div class="stat-icon"><i data-feather="check-circle"></i></div>
            <div class="stat-value"><?= $depositedThisMonth ?> জন</div>
            <div class="stat-label"><i class="fa-regular fa-circle-check fa-beat" style="color:#54A868; font-size:15px; padding:4px; margin-right:3px; vertical-align:middle;"></i> জমা দিয়েছে</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="stat-card bg-danger-grad text-center">
            <div class="stat-icon"><i data-feather="x-circle"></i></div>
            <div class="stat-value"><?= $notDepositedThisMonth ?> জন</div>
            <div class="stat-label"><i class="fa-regular fa-circle-xmark fa-beat" style="color:#DC3545; font-size:15px; padding:4px; margin-right:3px; vertical-align:middle;"></i> জমা দেয়নি</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="stat-card bg-warning-grad text-center">
            <div class="stat-icon"><i data-feather="dollar-sign"></i></div>
            <div class="stat-value">৳<?= number_format($totalThisMonthDeposit, 0) ?></div>
            <div class="stat-label"><?= $monthName ?> এ জমা</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="stat-card bg-teal-grad text-center">
            <div class="stat-icon"><i data-feather="credit-card"></i></div>
            <div class="stat-value">৳<?= number_format($regularDeposits, 0) ?></div>
            <div class="stat-label">মোট জমা ( সঞ্চয় )</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="stat-card bg-purple-grad text-center">
            <div class="stat-icon"><i data-feather="dollar-sign"></i></div>
            <div class="stat-value">৳<?= number_format($totalFinesPaid, 0) ?></div>
            <div class="stat-label"> জরিমানা আদায়</div>
            <small class="text-muted">মোট পরিশোধিত জরিমানা</small>
        </div>
    </div>
</div>

<!-- ============================================
     STATS CARDS - ROW 2 (Net Savings & Investments)
     ============================================ -->
<div class="row g-2 g-md-3 mb-4">
    <!-- NET SAVINGS - BIG CARD -->
    <div class="col-12 col-md-6">
        <div class="stat-card text-center" style="background: linear-gradient(135deg, #1B4F72, #2E86C1); color: white; padding: 20px;">
            <div class="stat-icon" style="opacity:0.3;font-size:40px;"><i data-feather="dollar-sign"></i></div>
            <div style="font-size:13px;opacity:0.8;">💰 নেট সঞ্চয়</div>
            <div class="stat-value" style="font-size:36px;color:white;">৳<?= number_format($netSavings, 0) ?></div>
           <div class="stat-label-blance">বর্তমান ব্যালেন্স </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card text-center" style="background: linear-gradient(135deg, #E8F5E9, #C8E6C9);">
            <div class="stat-icon"><i data-feather="trending-up" style="color:#27AE60;"></i></div>
            <div class="stat-value" style="font-size:20px;color:#27AE60;">৳<?= number_format($totalInvestmentReturns, 0) ?></div>
            <div class="stat-label">📈 বিনিয়োগ রিটার্ন</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card text-center" style="background: linear-gradient(135deg, #FEF9E7, #FDEBD0);">
            <div class="stat-icon"><i data-feather="briefcase" style="color:#F39C12;"></i></div>
            <div class="stat-value" style="font-size:20px;color:#F39C12;">৳<?= number_format($totalInvestmentAmount, 0) ?></div>
            <div class="stat-label">💼 মোট বিনিয়োগ</div>
        </div>
    </div>
</div>

<!-- ============================================
     STATS CARDS - ROW 3 (Withdrawals, Expenses, Loans)
     ============================================ -->
<div class="row g-2 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card text-center" style="background: linear-gradient(135deg, #FDEDEC, #FADBD8);">
            <div class="stat-icon"><i data-feather="arrow-up-circle" style="color:#E74C3C;"></i></div>
            <div class="stat-value" style="color:#E74C3C;">৳<?= number_format($totalWithdrawals, 0) ?></div>
            <div class="stat-label">📤 মোট উত্তোলন</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card text-center" style="background: linear-gradient(135deg, #E8F8F5, #D1F2EB);">
            <div class="stat-icon"><i data-feather="shopping-bag" style="color:#1ABC9C;"></i></div>
            <div class="stat-value" style="color:#1ABC9C;">৳<?= number_format($totalExpenses, 0) ?></div>
            <div class="stat-label">📊 এ বছরের খরচ</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card text-center" style="background: linear-gradient(135deg, #F5EEF8, #EBDEF0);">
            <div class="stat-icon"><i data-feather="alert-triangle" style="color:#8E44AD;"></i></div>
            <div class="stat-value" style="color:#8E44AD;">৳<?= number_format($totalPendingFines, 0) ?></div>
            <div class="stat-label">⏳ বকেয়া জরিমানা</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card text-center" style="background: linear-gradient(135deg, #FFF8E1, #FFECB3);">
            <div class="stat-icon"><i data-feather="briefcase" style="color:#F57C00;"></i></div>
            <div class="stat-value" style="color:#F57C00;">৳<?= number_format($totalLoans, 0) ?></div>
            <div class="stat-label">🏦 সক্রিয় ঋণ</div>
        </div>
    </div>
</div>

<!-- ============================================
     FINE STATS CARDS - ROW 4
     ============================================ -->
<div class="row g-2 mb-4">
    <div class="col-6 col-md-2">
        <div class="stat-card text-center" style="background: linear-gradient(135deg, #FFF3E0, #FFE0B2);">
            <div class="stat-icon"><i data-feather="clock" style="color:#E67E22;"></i></div>
            <div class="stat-value" style="font-size:20px;color:#E67E22;">৳<?= number_format($pendingFineAmount, 0) ?></div>
            <div class="stat-label">⏳ পেন্ডিং</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card text-center" style="background: linear-gradient(135deg, #FFF8E1, #FFECB3);">
            <div class="stat-icon"><i data-feather="refresh-cw" style="color:#F57C00;"></i></div>
            <div class="stat-value" style="font-size:20px;color:#F57C00;">৳<?= number_format($partialFineAmount, 0) ?></div>
            <div class="stat-label">🔄 আংশিক</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card text-center" style="background: linear-gradient(135deg, #E8F5E9, #C8E6C9);">
            <div class="stat-icon"><i data-feather="check-circle" style="color:#27AE60;"></i></div>
            <div class="stat-value" style="font-size:20px;color:#27AE60;">৳<?= number_format($paidFineAmount, 0) ?></div>
            <div class="stat-label"><i class="fa-regular fa-circle-check fa-beat" style="color:#54A868; font-size:15px; padding:4px; margin-right:3px; vertical-align:middle;"></i> পরিশোধিত</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card text-center" style="background: linear-gradient(135deg, #E3F2FD, #BBDEFB);">
            <div class="stat-icon"><i data-feather="slash" style="color:#2980B9;"></i></div>
            <div class="stat-value" style="font-size:20px;color:#2980B9;">৳<?= number_format($waivedFineAmount, 0) ?></div>
            <div class="stat-label">⛔ মওকুফ</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card text-center" style="background: linear-gradient(135deg, #FCE4EC, #F8BBD0);">
            <div class="stat-icon"><i data-feather="alert-circle" style="color:#E74C3C;"></i></div>
            <div class="stat-value" style="font-size:20px;color:#E74C3C;">৳<?= number_format($overdueFineAmount, 0) ?></div>
            <div class="stat-label">🔴 মেয়াদোত্তীর্ণ</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card text-center" style="background: linear-gradient(135deg, #F3E5F5, #E1BEE7);">
            <div class="stat-icon"><i data-feather="dollar-sign" style="color:#6C3483;"></i></div>
            <div class="stat-value" style="font-size:20px;color:#6C3483;">৳<?= number_format($totalFineAmount, 0) ?></div>
            <div class="stat-label">মোট জরিমানা</div>
        </div>
    </div>
</div>

<!-- ============================================
     QUICK ACTIONS
     ============================================ -->
<div class="row g-2 mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i data-feather="zap" style="width:16px;height:16px"></i> দ্রুত কাজ</span>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">
                    <a href="/admin/deposits.php" class="btn btn-success btn-sm">
                        <i data-feather="plus-circle" style="width:14px;height:14px"></i> নতুন জমা
                    </a>
                    <a href="/admin/members.php?action=add" class="btn btn-primary btn-sm">
                        <i data-feather="user-plus" style="width:14px;height:14px"></i> নতুন সদস্য
                    </a>
                    <a href="/admin/withdrawals.php" class="btn btn-danger btn-sm">
                        <i data-feather="arrow-up-circle" style="width:14px;height:14px"></i> উত্তোলন
                    </a>
                    <a href="/admin/loans.php?action=add" class="btn btn-warning btn-sm text-dark">
                        <i data-feather="briefcase" style="width:14px;height:14px"></i> ঋণ মঞ্জুর
                    </a>
                    <a href="/admin/fines.php" class="btn btn-purple btn-sm">
                        <i data-feather="alert-triangle" style="width:14px;height:14px"></i> জরিমানা ব্যবস্থাপনা
                    </a>
                    <a href="/admin/fines.php?status=pending" class="btn btn-warning btn-sm text-dark">
                        <i data-feather="clock" style="width:14px;height:14px"></i> বকেয়া জরিমানা
                    </a>
                    <a href="/admin/monthly_dues.php" class="btn btn-info btn-sm text-white">
                        <i data-feather="calendar" style="width:14px;height:14px"></i> সঞ্চয় সংগ্রহ
                    </a>
                    <a href="/admin/members.php" class="btn btn-secondary btn-sm">
                        <i data-feather="users" style="width:14px;height:14px"></i> সদস্য তালিকা
                    </a>
                    <a href="/admin/reports.php" class="btn btn-dark btn-sm">
                        <i data-feather="file-text" style="width:14px;height:14px"></i> রিপোর্ট
                    </a>
                    <a href="/admin/investments.php" class="btn btn-warning btn-sm text-dark">
                        <i data-feather="briefcase" style="width:14px;height:14px"></i> বিনিয়োগ ব্যবস্থাপনা
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
     MAIN CONTENT - 2 COLUMN LAYOUT
     ============================================ -->
<div class="row g-3">

    <!-- LEFT COLUMN -->
    <div class="col-12 col-lg-6">

        <!-- PENDING FINES LIST -->
        <div class="card" style="border-left: 4px solid #E67E22;">
            <div class="card-header" style="background:#FEF9E7;">
                <span class="card-title">
                    <i data-feather="alert-triangle" style="color:#E67E22;"></i> 
                    বকেয়া জরিমানা (<?= count($pendingFineList) ?>)
                </span>
                <a href="/admin/fines.php?status=pending" class="btn btn-outline-warning btn-sm">সব দেখুন</a>
            </div>
            <div class="list-group list-group-flush scrollable-list">
                <?php if (empty($pendingFineList)): ?>
                <div class="list-group-item text-center text-success py-3">
                    <i data-feather="check-circle" style="width:24px;height:24px;display:block;margin:0 auto 6px;"></i>
                    কোনো বকেয়া জরিমানা নেই! 🎉
                </div>
                <?php else: ?>
                <?php foreach ($pendingFineList as $fine): ?>
                <a href="/admin/fines.php?action=view&id=<?= $fine['id'] ?>" class="list-group-item list-group-item-action px-3 py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <img src="<?= getMemberPhoto(['name' => $fine['member_name'], 'photo' => $fine['member_photo']]) ?>" 
                                 alt="Photo" style="width:32px;height:32px;border-radius:50%;object-fit:cover;border:1px solid #ddd;">
                            <div>
                                <div class="fw-semibold" style="font-size:13px;"><?= clean($fine['member_name'] ?? '') ?></div>
                                <div style="font-size:11px;color:#888;">
                                    <code><?= clean($fine['member_code'] ?? '') ?></code>
                                    <span class="ms-2">মেয়াদ: <?= date('d M Y', strtotime($fine['due_date'])) ?></span>
                                    <?php if (strtotime($fine['due_date']) < time()): ?>
                                    <span class="badge bg-danger ms-1">মেয়াদোত্তীর্ণ</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="amount-negative fw-bold">৳<?= number_format($fine['remaining_amount'] ?? 0, 0) ?></div>
                            <div style="font-size:10px;color:#888;">
                                মোট: ৳<?= number_format($fine['fine_amount'], 0) ?>
                                <?php if ($fine['paid_amount'] > 0): ?>
                                | পরিশোধ: ৳<?= number_format($fine['paid_amount'], 0) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- RECENT DEPOSITS -->
        <div class="card mt-3">
            <div class="card-header">
                <span class="card-title"><i data-feather="clock" style="width:16px;height:16px"></i> সর্বশেষ জমা</span>
                <a href="/admin/deposit_list.php" class="btn btn-outline-primary btn-sm">সব দেখুন</a>
            </div>
            <div class="list-group list-group-flush scrollable-list">
                <?php if (empty($recentDeposits)): ?>
                <div class="list-group-item text-center text-muted py-3">কোনো জমা নেই</div>
                <?php else: ?>
                <?php foreach ($recentDeposits as $dep): 
                    $memberData = ['name' => $dep['member_name'], 'photo' => $dep['member_photo'] ?? null];
                ?>
                <a href="/admin/member_profile.php?id=<?= $dep['member_id'] ?>" class="list-group-item list-group-item-action px-3 py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <img src="<?= getMemberPhoto($memberData) ?>" alt="Photo" 
                                 style="width:32px;height:32px;border-radius:50%;object-fit:cover;border:1px solid #ddd;">
                            <div>
                                <div class="fw-semibold" style="font-size:14px;"><?= clean($dep['member_name'] ?? '') ?></div>
                                <div style="font-size:12px;color:#888;">
                                    <code><?= clean($dep['member_code'] ?? '') ?></code>
                                    <span class="ms-2"><?= timeAgo($dep['deposit_date'] ?? date('Y-m-d')) ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="amount-positive fw-bold">৳<?= number_format($dep['amount'] ?? 0, 0) ?></div>
                    </div>
                </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- DEPOSITED MEMBERS -->
        <div class="card mt-3" style="border-left: 4px solid #27AE60;">
            <div class="card-header" style="background:#EAFAF1;">
                <span class="card-title">
                    <i data-feather="check-circle" style="color:#27AE60;"></i> 
                    জমা দিয়েছে (<?= count($depositedMembers) ?>)
                </span>
                <span class="badge bg-success"><?= $monthName ?></span>
            </div>
            <div class="list-group list-group-flush scrollable-list">
                <?php if (empty($depositedMembers)): ?>
                <div class="list-group-item text-center text-muted py-3"><?= $monthName ?> এ কেউ জমা দেয়নি</div>
                <?php else: ?>
                <?php foreach ($depositedMembers as $member): ?>
                <a href="/admin/member_profile.php?id=<?= $member['id'] ?>" class="list-group-item list-group-item-action px-3 py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <img src="<?= getMemberPhoto($member) ?>" alt="Photo" 
                                 style="width:32px;height:32px;border-radius:50%;object-fit:cover;border:1px solid #ddd;">
                            <div>
                                <div class="fw-semibold" style="font-size:13px;"><?= clean($member['name'] ?? '') ?></div>
                                <div style="font-size:11px;color:#888;">
                                    <code><?= clean($member['member_id'] ?? '') ?></code>
                                    <span class="ms-2">মাসিক: ৳<?= number_format($member['monthly_deposit'] ?? 0, 0) ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="amount-positive fw-bold">৳<?= number_format($member['amount'] ?? 0, 0) ?></div>
                    </div>
                </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- UPCOMING BIRTHDAYS -->
        <?php if (!empty($upcomingBirthdays)): ?>
        <div class="card mt-3" style="border-left: 4px solid #8E44AD;">
            <div class="card-header" style="background:#F4ECF7;">
                <span class="card-title">
                    <i data-feather="gift" style="color:#8E44AD;"></i> 
                    আসন্ন জন্মদিন (<?= count($upcomingBirthdays) ?>)
                </span>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($upcomingBirthdays as $member): ?>
                <a href="/admin/member_profile.php?id=<?= $member['id'] ?>" class="list-group-item list-group-item-action px-3 py-2">
                    <div class="d-flex align-items-center gap-2">
                        <img src="<?= getMemberPhoto($member) ?>" alt="Photo" 
                             style="width:32px;height:32px;border-radius:50%;object-fit:cover;border:1px solid #ddd;">
                        <div>
                            <div class="fw-semibold" style="font-size:13px;"><?= clean($member['name'] ?? '') ?></div>
                            <div style="font-size:11px;color:#888;">
                                <code><?= clean($member['member_id'] ?? '') ?></code>
                                <span class="ms-2">🎂 <?= date('d M', strtotime($member['dob'])) ?></span>
                            </div>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- RIGHT COLUMN -->
    <div class="col-12 col-lg-6">

        <!-- RECENT FINE PAYMENTS -->
        <div class="card" style="border-left: 4px solid #8E44AD;">
            <div class="card-header" style="background:#F4ECF7;">
                <span class="card-title">
                    <i data-feather="credit-card" style="color:#8E44AD;"></i> 
                    সর্বশেষ জরিমানা পরিশোধ
                </span>
                <a href="/admin/fine_payments_list.php" class="btn btn-outline-purple btn-sm">সব দেখুন</a>
            </div>
            <div class="list-group list-group-flush scrollable-list">
                <?php if (empty($recentFineTransactions)): ?>
                <div class="list-group-item text-center text-muted py-3">কোনো জরিমানা পরিশোধ নেই</div>
                <?php else: ?>
                <?php foreach ($recentFineTransactions as $ft): 
                    $memberData = ['name' => $ft['member_name'], 'photo' => $ft['member_photo'] ?? null];
                ?>
                <a href="/admin/fines.php?action=view&id=<?= $ft['fine_id'] ?>" class="list-group-item list-group-item-action px-3 py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <img src="<?= getMemberPhoto($memberData) ?>" alt="Photo" 
                                 style="width:32px;height:32px;border-radius:50%;object-fit:cover;border:1px solid #ddd;">
                            <div>
                                <div class="fw-semibold" style="font-size:13px;"><?= clean($ft['member_name'] ?? '') ?></div>
                                <div style="font-size:11px;color:#888;">
                                    <code><?= clean($ft['member_code'] ?? '') ?></code>
                                    <span class="ms-2"><?= timeAgo($ft['payment_date'] ?? date('Y-m-d')) ?></span>
                                    <span class="badge <?= $ft['fine_status'] == 'paid' ? 'bg-success' : ($ft['fine_status'] == 'partially_paid' ? 'bg-warning' : 'bg-secondary') ?> ms-1">
                                        <?= ucfirst(str_replace('_', ' ', $ft['fine_status'])) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="amount-negative fw-bold">-৳<?= number_format($ft['amount'] ?? 0, 0) ?></div>
                    </div>
                </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- RECENT WITHDRAWALS -->
        <div class="card mt-3">
            <div class="card-header">
                <span class="card-title"><i data-feather="arrow-up-circle" style="width:16px;height:16px;color:#E74C3C;"></i> সর্বশেষ উত্তোলন</span>
                <a href="/admin/withdrawals.php" class="btn btn-outline-danger btn-sm">সব দেখুন</a>
            </div>
            <div class="list-group list-group-flush scrollable-list">
                <?php if (empty($recentWithdrawals)): ?>
                <div class="list-group-item text-center text-muted py-3">কোনো উত্তোলন নেই</div>
                <?php else: ?>
                <?php foreach ($recentWithdrawals as $w): 
                    $memberData = ['name' => $w['member_name'], 'photo' => $w['member_photo'] ?? null];
                ?>
                <a href="/admin/member_profile.php?id=<?= $w['member_id'] ?>" class="list-group-item list-group-item-action px-3 py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <img src="<?= getMemberPhoto($memberData) ?>" alt="Photo" 
                                 style="width:32px;height:32px;border-radius:50%;object-fit:cover;border:1px solid #ddd;">
                            <div>
                                <div class="fw-semibold" style="font-size:14px;"><?= clean($w['member_name'] ?? '') ?></div>
                                <div style="font-size:12px;color:#888;">
                                    <code><?= clean($w['member_code'] ?? '') ?></code>
                                    <span class="ms-2"><?= timeAgo($w['withdrawal_date'] ?? date('Y-m-d')) ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="amount-negative fw-bold">-৳<?= number_format($w['amount'] ?? 0, 0) ?></div>
                    </div>
                </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- NON-DEPOSITED MEMBERS -->
        <div class="card mt-3" style="border-left: 4px solid #E74C3C;">
            <div class="card-header" style="background:#FDEDEC;">
                <span class="card-title">
                    <i data-feather="x-circle" style="color:#E74C3C;"></i> 
                    জমা দেয়নি (<?= count($notDepositedMembers) ?>)
                </span>
                <span class="badge bg-danger"><?= $monthName ?></span>
            </div>
            <div class="list-group list-group-flush scrollable-list">
                <?php if (empty($notDepositedMembers)): ?>
                <div class="list-group-item text-center text-success py-3">
                    <i data-feather="check-circle" style="width:24px;height:24px;display:block;margin:0 auto 6px;"></i>
                    সবাই জমা দিয়েছে! 🎉
                </div>
                <?php else: ?>
                <?php foreach ($notDepositedMembers as $member): ?>
                <a href="/admin/member_profile.php?id=<?= $member['id'] ?>" class="list-group-item list-group-item-action px-3 py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <img src="<?= getMemberPhoto($member) ?>" alt="Photo" 
                                 style="width:32px;height:32px;border-radius:50%;object-fit:cover;border:1px solid #ddd;">
                            <div>
                                <div class="fw-semibold" style="font-size:13px;"><?= clean($member['name'] ?? '') ?></div>
                                <div style="font-size:11px;color:#888;">
                                    <code><?= clean($member['member_id'] ?? '') ?></code>
                                    <span class="ms-2">বকেয়া: ৳<?= number_format($member['monthly_deposit'] ?? 0, 0) ?></span>
                                </div>
                            </div>
                        </div>
                        <a href="/admin/deposits.php?member_id=<?= $member['id'] ?? 0 ?>" 
                           class="btn btn-sm btn-success" title="জমা নিন" onclick="event.stopPropagation();">
                            <i data-feather="plus-circle" style="width:14px;height:14px"></i>
                        </a>
                    </div>
                </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- INVESTMENT SUMMARY CARD -->
        <div class="card mt-3" style="border-left: 4px solid #F39C12;">
            <div class="card-header" style="background:#FEF9E7;">
                <span class="card-title">
                    <i data-feather="briefcase" style="color:#F39C12;"></i> 
                    বিনিয়োগ সারাংশ
                </span>
                <a href="/admin/investments.php" class="btn btn-outline-warning btn-sm">বিস্তারিত</a>
            </div>
            <div class="card-body py-3">
                <div class="row g-2 text-center">
                    <div class="col-6">
                        <div class="p-2" style="background:#f8f9fa;border-radius:8px;">
                            <div style="font-size:11px;color:#888;">মোট বিনিয়োগ</div>
                            <div class="fw-bold" style="font-size:18px;color:#E67E22;">৳<?= number_format($totalInvestmentAmount, 0) ?></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2" style="background:#f8f9fa;border-radius:8px;">
                            <div style="font-size:11px;color:#888;">মোট রিটার্ন</div>
                            <div class="fw-bold" style="font-size:18px;color:#27AE60;">৳<?= number_format($totalInvestmentReturns, 0) ?></div>
                        </div>
                    </div>
                </div>
                <?php if ($totalInvestmentAmount > 0): 
                    $roi = ($totalInvestmentReturns / $totalInvestmentAmount) * 100;
                ?>
                <div class="mt-2 text-center">
                    <span class="badge <?= $roi >= 0 ? 'bg-success' : 'bg-danger' ?>" style="font-size:13px;padding:6px 12px;">
                        ROI: <?= number_format($roi, 1) ?>%
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- ============================================
         RECENT MEMBERS - FULL WIDTH WITH PHOTOS
         ============================================ -->
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i data-feather="users" style="width:16px;height:16px"></i> সর্বশেষ সদস্য</span>
                <a href="/admin/members.php" class="btn btn-outline-primary btn-sm">সব দেখুন</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>ছবি</th>
                            <th>সদস্য আইডি</th>
                            <th>নাম</th>
                            <th>ফোন</th>
                            <th>যোগদান</th>
                            <th>অবস্থা</th>
                            <th>কার্যক্রম</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentMembers)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-3">কোনো সদস্য নেই</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($recentMembers as $index => $member): ?>
                        <tr class="clickable-row" data-href="/admin/member_profile.php?id=<?= $member['id'] ?>">
                            <td><?= $index + 1 ?></td>
                            <td>
                                <img src="<?= getMemberPhoto($member) ?>" alt="Photo" 
                                     style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid #eee;">
                            </td>
                            <td><code><?= clean($member['member_id']) ?></code></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <?= clean($member['name']) ?>
                                </div>
                            </td>
                            <td><?= clean($member['phone']) ?></td>
                            <td><?= formatDate($member['join_date']) ?></td>
                            <td>
                                <span class="badge <?= $member['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= $member['status'] === 'active' ? 'সক্রিয়' : 'নিষ্ক্রিয়' ?>
                                </span>
                            </td>
                            <td>
                                <a href="/admin/member_profile.php?id=<?= $member['id'] ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    <i data-feather="eye" style="width:14px;height:14px"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div><!-- /row -->
</div><!-- /container-fluid -->

<style>
/* ============================================
   STAT CARDS - CENTER ALIGNED
   ============================================ */
.stat-card {
    border-radius: 12px;
    padding: 16px 18px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    transition: all 0.3s;
    height: 100%;
    position: relative;
    overflow: hidden;
    text-align: center;
}
.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.1);
}
.stat-card .stat-icon {
    position: absolute;
    top: 10px;
    right: 12px;
    opacity: 0.2;
    font-size: 28px;
}
.stat-card .stat-value {
    font-size: 26px;
    font-weight: 700;
    color: #1B4F72;
    margin-bottom: 2px;
}
.stat-card .stat-label {
    font-size: 12px;
    color: #566573;
    font-weight: 500;
}
.stat-card .text-muted {
    font-size: 10px;
}
.stat-label-blance{
    font-size: 14px;
    color: #fff;
    font-weight: 500;
}
.bg-primary-grad { background: linear-gradient(135deg, #EBF5FB, #D6EAF8); }
.bg-success-grad { background: linear-gradient(135deg, #EAFAF1, #D5F5E3); }
.bg-danger-grad { background: linear-gradient(135deg, #FDEDEC, #FADBD8); }
.bg-warning-grad { background: linear-gradient(135deg, #FEF9E7, #FDEBD0); }
.bg-teal-grad { background: linear-gradient(135deg, #E8F8F5, #D1F2EB); }
.bg-purple-grad { background: linear-gradient(135deg, #F4ECF7, #EBDEF0); }

.btn-purple {
    background: linear-gradient(135deg, #6C3483, #9B59B6);
    color: white;
    border: none;
}
.btn-purple:hover {
    background: linear-gradient(135deg, #5B2C6F, #8E44AD);
    color: white;
}

.btn-outline-purple {
    color: #6C3483;
    border-color: #6C3483;
}
.btn-outline-purple:hover {
    background: #6C3483;
    color: white;
}

/* Cards */
.card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    margin-bottom: 0;
}
.card-header {
    background: white;
    border-bottom: 1px solid #f0f0f0;
    padding: 12px 18px;
    border-radius: 12px 12px 0 0 !important;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}
.card-title {
    font-size: 14px;
    font-weight: 600;
    color: #2C3E50;
    display: flex;
    align-items: center;
    gap: 6px;
}
.card-body {
    padding: 14px 16px;
}

.amount-positive { color: #27AE60; font-weight: 600; }
.amount-negative { color: #E74C3C; font-weight: 600; }

/* ============================================
   SCROLLABLE LIST
   ============================================ */
.scrollable-list {
    max-height: 260px;
    overflow-y: auto;
}

/* Custom Scrollbar */
.scrollable-list::-webkit-scrollbar {
    width: 4px;
}
.scrollable-list::-webkit-scrollbar-track {
    background: #f0f0f0;
    border-radius: 4px;
}
.scrollable-list::-webkit-scrollbar-thumb {
    background: #2E86C1;
    border-radius: 4px;
}
.scrollable-list::-webkit-scrollbar-thumb:hover {
    background: #1B4F72;
}

/* List Group Item */
.list-group-item {
    border-left: none;
    border-right: none;
    border-color: #f0f0f0;
    padding: 8px 14px;
}
.list-group-item:first-child {
    border-top: none;
}
.list-group-item:last-child {
    border-bottom: none;
}

.list-group-item-action {
    transition: all 0.2s;
    cursor: pointer;
}
.list-group-item-action:hover {
    background: #f0f8ff !important;
    transform: translateX(4px);
}

/* Table */
.table {
    font-size: 13px;
    margin-bottom: 0;
}
.table th {
    font-weight: 600;
    color: #566573;
    border-bottom: 2px solid #f0f0f0;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.table td {
    vertical-align: middle;
    padding: 8px 12px;
}
.table-hover tbody tr:hover {
    background: #f8f9fa;
}

/* Clickable Row */
.clickable-row {
    cursor: pointer;
}
.clickable-row:hover {
    background-color: #f0f8ff !important;
}

/* Responsive */
@media (max-width: 768px) {
    .stat-card .stat-value {
        font-size: 20px;
    }
    .stat-card {
        padding: 12px 14px;
    }
    .card-header {
        padding: 10px 14px;
    }
    .card-body {
        padding: 12px 14px;
    }
    .table td, .table th {
        padding: 6px 8px;
        font-size: 12px;
    }
    .scrollable-list {
        max-height: 200px;
    }
    .list-group-item {
        padding: 6px 12px;
    }
}

/* Margin utilities */
.mt-3 { margin-top: 0.75rem !important; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Clickable rows for table
    document.querySelectorAll('.clickable-row').forEach(function(row) {
        row.addEventListener('click', function() {
            const href = this.dataset.href;
            if (href) {
                window.location.href = href;
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>