<?php
// =====================================================
// public_dashboard.php - Public Dashboard (VIEW ONLY)
// FOR LOGGED IN MEMBERS - NO ACTIONS, ONLY DATA VIEW
// WITH FINES SYSTEM & INVESTMENT DISPLAY & EXPENSES
// =====================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/functions.php';
startSecureSession();

// ============================================
// CHECK IF MEMBER IS LOGGED IN
// ============================================
$isLoggedIn = isset($_SESSION['member_id']);
$memberData = null;
$memberName = 'অতিথি';
$memberId = '';
$memberPhoto = '';
$hasPhoto = false;

if ($isLoggedIn) {
    $mId = $_SESSION['member_id'];
    $conn = conn();
    $memberData = queryOne("SELECT * FROM members WHERE id = ?", 'i', [$mId]);
    if ($memberData) {
        $memberName = $memberData['name'] ?? 'সদস্য';
        $memberId = $memberData['member_id'] ?? '';
        $memberPhoto = $memberData['photo'] ?? '';
        
        // Check if photo exists
        if (!empty($memberPhoto) && file_exists($_SERVER['DOCUMENT_ROOT'] . $memberPhoto)) {
            $hasPhoto = true;
        }
    }
}

$pageTitle = 'পাবলিক ড্যাশবোর্ড';
$conn = conn();

// ============================================
// GET SOMITY NAME AND LOGO FROM DATABASE
// ============================================
$somity_name = getSetting('somity_name', 'সমিতি');
$logo_url = getSetting('logo_url', '');

// Build full logo URL
$fullLogoUrl = '';
if (!empty($logo_url)) {
    if (strpos($logo_url, 'http://') === 0 || strpos($logo_url, 'https://') === 0) {
        $fullLogoUrl = $logo_url;
    } else {
        $logoPath = ltrim($logo_url, '/');
        if (strpos($logoPath, 'images/') === 0 || strpos($logoPath, 'uploads/') === 0) {
            $fullLogoUrl = '/' . $logoPath;
        } else {
            $fullLogoUrl = '/images/' . $logoPath;
        }
    }
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
// HELPER: GET MEMBER PHOTO
// ============================================
function getMemberPhotoUrl($memberId, $photoPath = null, $name = '') {
    if (!empty($photoPath) && file_exists($_SERVER['DOCUMENT_ROOT'] . $photoPath)) {
        return $photoPath;
    }
    // Generate avatar with first letter
    $firstLetter = mb_substr($name, 0, 1, 'UTF-8');
    $colors = ['#1B4F72', '#2E86C1', '#117A65', '#C0392B', '#8E44AD', '#D35400', '#27AE60', '#2980B9'];
    $color = $colors[abs(crc32($name)) % count($colors)];
    
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 40 40">
        <circle cx="20" cy="20" r="20" fill="' . $color . '"/>
        <text x="20" y="22" font-family="Arial" font-size="18" font-weight="bold" fill="white" text-anchor="middle" dominant-baseline="central">' . htmlspecialchars($firstLetter) . '</text>
    </svg>';
    return 'data:image/svg+xml;charset=utf-8,' . rawurlencode($svg);
}

// ============================================
// 1. BASIC STATS
// ============================================
$totalMembers = 0;
$totalDeposits = 0;
$totalFineDeposits = 0;
$totalWithdrawals = 0;
$totalLoans = 0;
$totalDues = 0;
$totalExpenses = 0;
$totalProfit = 0;
$mainBalance = 0;

try {
    $result = $conn->query("SELECT COUNT(*) as count FROM members WHERE status = 'active'");
    if ($result) {
        $row = $result->fetch_assoc();
        $totalMembers = $row['count'] ?? 0;
    }
} catch (Exception $e) {}

// ✅ মোট ডিপোজিট (regular)
try {
    $result = $conn->query("SELECT COALESCE(SUM(amount),0) as total FROM deposits WHERE deposit_type = 'regular'");
    if ($result) {
        $row = $result->fetch_assoc();
        $totalDeposits = $row['total'] ?? 0;
    }
} catch (Exception $e) {}

// ✅ মোট ফাইনস ডিপোজিট
try {
    $result = $conn->query("SELECT COALESCE(SUM(paid_amount),0) as total FROM fines WHERE status = 'paid'");
    if ($result) {
        $row = $result->fetch_assoc();
        $totalFineDeposits = $row['total'] ?? 0;
    }
} catch (Exception $e) {}

// Total Withdrawals
try {
    $result = $conn->query("SELECT COALESCE(SUM(amount),0) as total FROM withdrawals WHERE status='completed'");
    if ($result) {
        $row = $result->fetch_assoc();
        $totalWithdrawals = $row['total'] ?? 0;
    }
} catch (Exception $e) {}

// ============================================
// TOTAL EXPENSES (ALL YEARS - FIXED)
// ============================================
try {
    $result = $conn->query("
        SELECT COALESCE(SUM(amount), 0) AS total
        FROM expenses
    ");
    if ($result) {
        $row = $result->fetch_assoc();
        $totalExpenses = $row['total'] ?? 0;
    }
} catch (Exception $e) {
    $totalExpenses = 0;
}

// ============================================
// INVESTMENT STATS
// ============================================
$totalInvestments = 0;
$totalInvestmentReturns = 0;
$totalInvestmentsCount = 0;
$activeInvestments = 0;
$completedInvestments = 0;
$paidInvestments = 0;
$investmentList = [];

try {
    // Check if investments table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'investments'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        // Total investment amount
        $result = $conn->query("SELECT COALESCE(SUM(amount),0) as total FROM investments");
        if ($result) {
            $row = $result->fetch_assoc();
            $totalInvestments = $row['total'] ?? 0;
        }
        
        // Total investment count
        $result = $conn->query("SELECT COUNT(*) as count FROM investments");
        if ($result) {
            $row = $result->fetch_assoc();
            $totalInvestmentsCount = $row['count'] ?? 0;
        }
        
        // Status wise investments
        $result = $conn->query("SELECT status, COUNT(*) as count FROM investments GROUP BY status");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                if ($row['status'] == 'active' || $row['status'] == 'partial') {
                    $activeInvestments += $row['count'];
                } elseif ($row['status'] == 'completed') {
                    $completedInvestments += $row['count'];
                } elseif ($row['status'] == 'paid') {
                    $paidInvestments += $row['count'];
                }
            }
        }
        
        // Investment list with returns
        $result = $conn->query("
            SELECT 
                i.*,
                COALESCE(r.total_return, 0) AS total_return,
                CASE 
                    WHEN i.amount > 0 THEN (i.expected_profit / i.amount) * 100 
                    ELSE 0 
                END AS profit_percent,
                (i.amount + i.expected_profit) AS expected_total
            FROM investments i
            LEFT JOIN (
                SELECT investment_id, SUM(return_amount) AS total_return 
                FROM investment_returns 
                GROUP BY investment_id
            ) r ON r.investment_id = i.id
            ORDER BY i.investment_date DESC
            LIMIT 20
        ");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $investmentList[] = $row;
            }
        }
    }
    
    // Check if investment_returns table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'investment_returns'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        $result = $conn->query("SELECT COALESCE(SUM(return_amount),0) as total FROM investment_returns");
        if ($result) {
            $row = $result->fetch_assoc();
            $totalInvestmentReturns = $row['total'] ?? 0;
        }
    }
} catch (Exception $e) {
    error_log("Investment statistics error: " . $e->getMessage());
}
// ============================================
// সঞ্চয় + জরিমানা + ইনভেস্টমেন্ট রিটার্ন
// ============================================
$totalMemberDeposits = $totalDeposits + $totalFineDeposits + $totalInvestmentReturns;
// ============================================
// ✅ MAIN BALANCE
// ============================================
$mainBalance = ($totalDeposits + $totalFineDeposits + $totalInvestmentReturns) - ($totalWithdrawals + $totalExpenses + $totalInvestments);

// ============================================
// Total Dues - Previous Month's non-depositors
// ============================================
try {
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(monthly_deposit), 0) as total_dues
        FROM members 
        WHERE status = 'active' 
        AND id NOT IN (
            SELECT DISTINCT member_id 
            FROM deposits 
            WHERE MONTH(deposit_date) = ? AND YEAR(deposit_date) = ?
        )
    ");
    $stmt->bind_param('ss', $prevMonth, $prevYear);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $totalDues = $row['total_dues'] ?? 0;
    }
    $stmt->close();
    
} catch (Exception $e) {
    $totalDues = 0;
}

// Total Profit (Current Year from profit_loss table)
try {
    $currentYear = date('Y');
    $nextYear = $currentYear + 1;
    $fiscalYear = $currentYear . '-' . $nextYear;
    $stmt = $conn->prepare("SELECT COALESCE(net_profit,0) as profit FROM profit_loss WHERE fiscal_year = ? AND status = 'final'");
    $stmt->bind_param('s', $fiscalYear);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $totalProfit = $row['profit'] ?? 0;
    }
    $stmt->close();
} catch (Exception $e) {}

// ============================================
// FINE STATISTICS - FROM 'fines' TABLE
// ============================================
$totalFineAmount = 0;
$totalFinePaid = 0;
$totalFineWaived = 0;
$totalFinePending = 0;
$totalFineCount = 0;
$pendingFineCount = 0;
$partiallyPaidFineCount = 0;
$paidFineCount = 0;
$waivedFineCount = 0;
$overdueFines = 0;

// Fine amount by status (for cards)
$pendingFineAmount = 0;
$partialFineAmount = 0;
$paidFineAmount = 0;
$waivedFineAmount = 0;
$overdueFineAmount = 0;

try {
    // Check if fines table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'fines'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        
        // Fine statistics from fines table
        $result = $conn->query("
            SELECT 
                COUNT(*) as total_count,
                COALESCE(SUM(fine_amount), 0) as total_fine,
                COALESCE(SUM(paid_amount), 0) as total_paid,
                COALESCE(SUM(waived_amount), 0) as total_waived,
                COALESCE(SUM(fine_amount - paid_amount - waived_amount), 0) as total_pending
            FROM fines
        ");
        if ($result) {
            $row = $result->fetch_assoc();
            $totalFineCount = $row['total_count'] ?? 0;
            $totalFineAmount = $row['total_fine'] ?? 0;
            $totalFinePaid = $row['total_paid'] ?? 0;
            $totalFineWaived = $row['total_waived'] ?? 0;
            $totalFinePending = $row['total_pending'] ?? 0;
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

        // ✅ Pending amount
        $result = $conn->query("SELECT COALESCE(SUM(fine_amount - paid_amount - waived_amount),0) as total FROM fines WHERE status = 'pending'");
        if ($result) {
            $row = $result->fetch_assoc();
            $pendingFineAmount = $row['total'] ?? 0;
        }
        
        // ✅ Partial amount
        $result = $conn->query("SELECT COALESCE(SUM(fine_amount - paid_amount - waived_amount),0) as total FROM fines WHERE status = 'partially_paid'");
        if ($result) {
            $row = $result->fetch_assoc();
            $partialFineAmount = $row['total'] ?? 0;
        }
        
        // ✅ Paid amount
        $result = $conn->query("SELECT COALESCE(SUM(paid_amount),0) as total FROM fines WHERE status = 'paid'");
        if ($result) {
            $row = $result->fetch_assoc();
            $paidFineAmount = $row['total'] ?? 0;
        }
        
        // ✅ Waived amount
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
// PENDING FINES LIST (VIEW ONLY)
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
// RECENT FINE PAYMENTS (VIEW ONLY)
// ============================================
$recentFinePayments = [];

try {
    $txCheck = $conn->query("SHOW TABLES LIKE 'fine_payments'");
    if ($txCheck && $txCheck->num_rows > 0) {
        $result = $conn->query("
            SELECT 
                fp.*,
                f.id as fine_id,
                f.member_id,
                f.fine_amount,
                f.status as fine_status,
                m.name as member_name,
                m.member_id as member_code,
                m.photo as member_photo
            FROM fine_payments fp
            JOIN fines f ON fp.fine_id = f.id
            JOIN members m ON f.member_id = m.id
            ORDER BY fp.payment_date DESC
            LIMIT 10
        ");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $recentFinePayments[] = $row;
            }
        }
    }
} catch (Exception $e) {
    error_log("Fine payments error: " . $e->getMessage());
}

// ============================================
// RECENT EXPENSES (VIEW ONLY)
// ============================================
$recentExpenses = [];
try {
    $result = $conn->query("
        SELECT * FROM expenses 
        ORDER BY expense_date DESC, created_at DESC 
        LIMIT 10
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recentExpenses[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Expenses error: " . $e->getMessage());
}

// ============================================
// MONTHLY DEPOSIT INFO (Previous Month)
// ============================================
$depositedThisMonth = 0;
$totalThisMonthDeposit = 0;
$depositedMembers = [];
$notDepositedMembers = [];

try {
    // Count deposited in previous month
    $result = $conn->query("
        SELECT COUNT(DISTINCT member_id) as count, COALESCE(SUM(amount),0) as total 
        FROM deposits 
        WHERE MONTH(deposit_date) = $prevMonth AND YEAR(deposit_date) = $prevYear
        AND deposit_type = 'regular'
    ");
    if ($result) {
        $row = $result->fetch_assoc();
        $depositedThisMonth = $row['count'] ?? 0;
        $totalThisMonthDeposit = $row['total'] ?? 0;
    }
    
    // Get deposited members list
    $result = $conn->query("
        SELECT m.id, m.name, m.member_id, m.phone, m.monthly_deposit, m.photo,
            d.amount, d.deposit_date
        FROM members m
        INNER JOIN deposits d ON d.member_id = m.id
        WHERE m.status = 'active' 
        AND MONTH(d.deposit_date) = $prevMonth 
        AND YEAR(d.deposit_date) = $prevYear
        AND d.deposit_type = 'regular'
        AND d.id IN (
            SELECT MAX(id) FROM deposits 
            WHERE member_id = m.id 
            AND MONTH(deposit_date) = $prevMonth 
            AND YEAR(deposit_date) = $prevYear
            AND deposit_type = 'regular'
            GROUP BY member_id
        )
        ORDER BY d.deposit_date DESC
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $depositedMembers[] = $row;
        }
    }
    
    // Get non-deposited members
    $result = $conn->query("
        SELECT id, name, member_id, phone, monthly_deposit, photo
        FROM members 
        WHERE status = 'active' AND id NOT IN (
            SELECT DISTINCT member_id FROM deposits 
            WHERE MONTH(deposit_date) = $prevMonth AND YEAR(deposit_date) = $prevYear
            AND deposit_type = 'regular'
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
// MONTHLY WITHDRAWAL INFO (Previous Month)
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
// RECENT DEPOSITS & WITHDRAWALS
// ============================================
$recentDeposits = [];
$recentWithdrawals = [];

try {
    $result = $conn->query("
        SELECT d.*, m.name as member_name, m.member_id as member_code, m.photo
        FROM deposits d
        JOIN members m ON m.id = d.member_id
        WHERE d.deposit_type = 'regular'
        ORDER BY d.deposit_date DESC, d.id DESC
        LIMIT 10
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recentDeposits[] = $row;
        }
    }
    
    $result = $conn->query("
        SELECT w.*, m.name as member_name, m.member_id as member_code, m.photo
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
// RECENT MEMBERS - Show 10 with Photo
// ============================================
$recentMembers = [];
try {
    $result = $conn->query("
        SELECT id, name, member_id, join_date, status, photo 
        FROM members 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recentMembers[] = $row;
        }
    }
} catch (Exception $e) {}

// ============================================
// UPCOMING BIRTHDAYS (Next 7 days)
// ============================================
$upcomingBirthdays = [];
try {
    $result = $conn->query("
        SELECT id, member_id, name, phone, dob, photo 
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

// ============================================
// HELPER FUNCTION FOR COLOR
// ============================================
function getColor($name) {
    $colors = ['#1B4F72', '#2E86C1', '#117A65', '#C0392B', '#8E44AD', '#D35400', '#27AE60', '#2980B9'];
    return $colors[abs(crc32($name)) % count($colors)];
}

// ============================================
// HELPER: RENDER MEMBER AVATAR
// ============================================
function renderMemberAvatar($photo, $name, $size = 32) {
    $firstLetter = mb_substr($name, 0, 1, 'UTF-8');
    $color = getColor($name);
    
    if (!empty($photo) && file_exists($_SERVER['DOCUMENT_ROOT'] . $photo)) {
        return '<img src="' . htmlspecialchars($photo) . '" alt="' . htmlspecialchars($name) . '" style="width:' . $size . 'px;height:' . $size . 'px;border-radius:50%;object-fit:cover;border:2px solid #ddd;flex-shrink:0;">';
    } else {
        return '<div style="width:' . $size . 'px;height:' . $size . 'px;border-radius:50%;background:' . $color . ';color:white;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:' . ($size * 0.5) . 'px;flex-shrink:0;">' . htmlspecialchars($firstLetter) . '</div>';
    }
}

// ============================================
// HELPER: GET STATUS COLOR
// ============================================
function getInvestmentStatusColor($status) {
    $colors = [
        'active' => 'primary',
        'partial' => 'warning',
        'completed' => 'success',
        'paid' => 'info',
        'failed' => 'danger'
    ];
    return $colors[$status] ?? 'secondary';
}

function getInvestmentStatusLabel($status) {
    $labels = [
        'active' => 'সক্রিয়',
        'partial' => 'আংশিক',
        'completed' => 'সম্পন্ন',
        'paid' => 'পেইড',
        'failed' => 'ব্যর্থ'
    ];
    return $labels[$status] ?? $status;
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= clean($somity_name) ?> - পাবলিক ড্যাশবোর্ড</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Feather Icons -->
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <!-- Google Fonts: Bengali -->
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        :root {
            --primary: #0F6F5C;
            --primary-light: #198754;
            --surface: #F4F6F9;
            --text-dark: #198754;
            --text-muted: #000;
            --border: #0f6f5c;
            --success: #1E8449;
            --danger: #C0392B;
            --warning: #B7950B;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Hind Siliguri', sans-serif;
            background: var(--surface);
            color: var(--text-dark);
            font-size: 15px;
            line-height: 1.6;
            margin: 0;
            padding: 0 0 50px 0;
        }

        .top-nav {
            background: white;
            border-bottom: 2px solid var(--primary);
            padding: 12px 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .top-nav .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }

        .top-nav .nav-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: var(--primary);
        }

        .top-nav .nav-brand:hover {
            text-decoration: none;
            color: var(--primary);
        }

        .top-nav .nav-brand .brand-icon {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
            background: #f0f0f0;
            border: 2px solid var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .top-nav .nav-brand .brand-icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .top-nav .nav-brand .brand-icon .no-logo {
            font-size: 22px;
            font-weight: 700;
            color: var(--primary);
            background: #EBF5FB;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .top-nav .nav-brand .brand-text {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary);
        }

        .top-nav .nav-brand .brand-text small {
            font-size: 13px;
            font-weight: 400;
            color: var(--text-muted);
            display: block;
        }

        .top-nav .nav-right {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .top-nav .nav-right .user-info {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #e8f8f5;
            padding: 4px 14px 4px 4px;
            border-radius: 30px;
        }

        .top-nav .nav-right .user-info .user-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
            border: 2px solid var(--primary-light);
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .top-nav .nav-right .user-info .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .top-nav .nav-right .user-info .user-avatar .avatar-text {
            font-size: 14px;
            font-weight: 600;
            color: white;
        }

        .top-nav .nav-right .user-info .user-details {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }

        .top-nav .nav-right .user-info .user-name {
            font-weight: 600;
            font-size: 14px;
            color: var(--text-darked);
        }

        .top-nav .nav-right .user-info .user-id {
            font-size: 11px;
            color: var(--text-muted);
        }
        
        .user-info-link {
          text-decoration: none;
          color: inherit;
          display: block;
        }

        .user-info-link:hover {
          text-decoration: none;
        }

        .top-nav .nav-right .public-badge {
            background: #E8F8F5;
            color: #117A65;
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .top-nav .nav-right .public-badge i {
            width: 16px;
            height: 16px;
        }

        .top-nav .nav-right .btn-dashboard {
            padding: 6px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            border: none;
            background: var(--primary);
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .top-nav .nav-right .btn-dashboard:hover {
            background: var(--primary-light);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(27, 79, 114, 0.3);
        }

        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px 20px 40px 20px;
        }

        .welcome-banner {
            background: #0F6F5C;
            border-radius: 16px;
            padding: 24px 30px;
            color: white;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }

        .welcome-banner .welcome-text {
            font-size: 20px;
            font-weight: 600;
        }

        .welcome-banner .welcome-text small {
            font-size: 14px;
            font-weight: 400;
            opacity: 0.85;
            display: block;
            margin-top: 4px;
        }

        .welcome-banner .month-badge {
            background: rgba(255,255,255,0.2);
            padding: 8px 20px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 500;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.2);
        }

        .stat-card {
            border-radius: 12px;
            padding: 16px 18px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            transition: all 0.3s;
            height: 100%;
            position: relative;
            overflow: hidden;
            text-align: center;
            background: white;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
        }
        .stat-card .stat-icon {
            position: absolute;
            top: 10px;
            right: 12px;
            opacity: 0.15;
            font-size: 32px;
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

        .stat-card.bg-primary-grad { background: linear-gradient(135deg, #EBF5FB, #D6EAF8); }
        .stat-card.bg-success-grad { background: linear-gradient(135deg, #EAFAF1, #D5F5E3); }
        .stat-card.bg-danger-grad { background: linear-gradient(135deg, #FDEDEC, #FADBD8); }
        .stat-card.bg-warning-grad { background: linear-gradient(135deg, #FEF9E7, #FDEBD0); }
        .stat-card.bg-teal-grad { background: linear-gradient(135deg, #E8F8F5, #D1F2EB); }
        .stat-card.bg-purple-grad { background: linear-gradient(135deg, #F4ECF7, #EBDEF0); }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            margin-bottom: 0;
            background: white;
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

        .scrollable-list {
            max-height: 260px;
            overflow-y: auto;
        }
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

        .list-group-item {
            border-left: none;
            border-right: none;
            border-color: #f0f0f0;
            padding: 8px 14px;
        }
        .list-group-item:first-child { border-top: none; }
        .list-group-item:last-child { border-bottom: none; }

        .amount-positive { color: #27AE60; font-weight: 600; }
        .amount-negative { color: #E74C3C; font-weight: 600; }

        .member-avatar-wrapper {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            flex-shrink: 0;
            overflow: hidden;
            border: 2px solid #e8ecf1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f0f4f8;
        }

        .member-avatar-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .member-avatar-wrapper .avatar-text {
            font-size: 13px;
            font-weight: 600;
            color: white;
        }

        .member-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .member-item .member-info {
            flex: 1;
            min-width: 0;
        }

        .member-item .member-info .name {
            font-weight: 600;
            font-size: 13px;
            color: var(--text-dark);
            word-break: break-word;
        }

        .member-item .member-info .sub {
            font-size: 11px;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .member-item .member-info .sub code {
            font-size: 10px;
            background: #f0f0f0;
            padding: 1px 6px;
            border-radius: 4px;
        }

        .fine-status-card {
            background: linear-gradient(135deg, #F8F9FA, #E9ECEF);
            border-radius: 12px;
            border-left: 4px solid #6C3483;
        }

        .fine-status-item {
            display: inline-block;
            padding: 8px 16px;
            margin: 4px 6px 4px 0;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .fine-status-item:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .fine-status-item.pending {
            background-color: #FFF3E0;
            color: #E67E22;
            border: 1px solid #FFE0B2;
        }

        .fine-status-item.partial {
            background-color: #E3F2FD;
            color: #1976D2;
            border: 1px solid #BBDEFB;
        }

        .fine-status-item.paid {
            background-color: #E8F5E9;
            color: #388E3C;
            border: 1px solid #C8E6C9;
        }

        .fine-status-item.waived {
            background-color: #F5F5F5;
            color: #616161;
            border: 1px solid #E0E0E0;
        }

        .fine-status-count {
            font-weight: 700;
            font-size: 16px;
            margin-left: 4px;
        }

        .progress {
            display: flex;
            height: 32px;
            overflow: hidden;
            font-size: 12px;
            background-color: #f0f0f0;
            border-radius: 8px;
            margin-top: 10px;
        }

        .progress-bar {
            display: flex;
            flex-direction: column;
            justify-content: center;
            text-align: center;
            white-space: nowrap;
            transition: width 0.6s ease;
            font-weight: 600;
            font-size: 12px;
            color: #fff;
            padding: 0 8px;
        }

        .progress-bar.bg-warning {
            background: linear-gradient(135deg, #FF9800, #F57C00);
        }

        .progress-bar.bg-info {
            background: linear-gradient(135deg, #2196F3, #1976D2);
        }

        .progress-bar.bg-success {
            background: linear-gradient(135deg, #4CAF50, #388E3C);
        }

        .progress-bar.bg-secondary {
            background: linear-gradient(135deg, #6c757d, #495057);
        }
        .bg-secondary{
            background-color: #198754 !important;
        }
        .badge {
            padding: 6px 12px;
            font-size: 13px;
            font-weight: 500;
            border-radius: 6px;
            display: inline-block;
        }

        .badge-warning {
            background-color: #FF9800;
            color: #fff;
        }

        .badge-info {
            background-color: #2196F3;
            color: #fff;
        }

        .badge-success {
            background-color: #4CAF50;
            color: #fff;
        }

        .badge-secondary {
            background-color: #6c757d;
            color: #fff;
        }

        .badge-purple {
            background-color: #6C3483;
            color: #fff;
        }

        .table {
            font-size: 14px;
            margin-bottom: 0;
        }
        .table th {
            font-weight: 600;
            color: #566573;
            border-bottom: 2px solid #f0f0f0;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .table td {
            vertical-align: middle;
            padding: 10px 12px;
        }
        .table-hover tbody tr:hover {
            background: #f8f9fa;
        }

        .table .member-name {
            font-weight: 600;
            font-size: 15px;
        }

        .table .member-avatar-sm {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #ddd;
            flex-shrink: 0;
        }

        .table .avatar-placeholder-sm {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 12px;
            flex-shrink: 0;
        }

        .table .member-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .investment-progress {
            height: 6px;
            border-radius: 10px;
            margin-top: 4px;
        }

        .investment-progress .progress-bar {
            height: 6px;
            border-radius: 10px;
            font-size: 0;
        }

        .no-action {
            cursor: default !important;
        }
        .no-action:hover {
            background: transparent !important;
            transform: none !important;
        }

        /* ============================================
           FIX: Add space at bottom for footer
           ============================================ */
        .row.g-3:last-child {
            margin-bottom: 10px;
        }

        @media (max-width: 992px) {
            .stat-card .stat-value {
                font-size: 20px;
            }
        }

        @media (max-width: 768px) {
            .stat-card .stat-value {
                font-size: 18px;
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
            .table .member-name {
                font-size: 13px;
            }
            .scrollable-list {
                max-height: 200px;
            }
            .list-group-item {
                padding: 6px 12px;
            }
            .top-nav {
                padding: 10px 14px;
            }
            .top-nav .nav-brand .brand-text {
                font-size: 16px;
            }
            .top-nav .nav-brand .brand-text small {
                font-size: 11px;
            }
            .top-nav .nav-brand .brand-icon {
                width: 34px;
                height: 34px;
            }
            .welcome-banner {
                padding: 16px 20px;
                flex-direction: column;
                text-align: center;
            }
            .welcome-banner .welcome-text {
                font-size: 17px;
            }
            .main-content {
                padding: 12px 12px 30px 12px;
            }
            .top-nav .nav-right .user-info .user-name {
                font-size: 12px;
            }
            .top-nav .nav-right .user-info .user-id {
                display: none;
            }
            .top-nav .nav-right .btn-dashboard {
                font-size: 12px;
                padding: 4px 12px;
            }
            .top-nav .nav-right .public-badge {
                font-size: 10px;
                padding: 4px 10px;
            }
            .top-nav .nav-right .user-info {
                padding: 3px 10px 3px 3px;
            }
            .top-nav .nav-right .user-info .user-avatar {
                width: 28px;
                height: 28px;
            }
            .member-item .member-info .name {
                font-size: 12px;
            }
            .member-item .member-info .sub {
                font-size: 10px;
            }
            .member-avatar-wrapper {
                width: 26px;
                height: 26px;
            }
            .table .member-avatar-sm {
                width: 24px;
                height: 24px;
            }
            .table .avatar-placeholder-sm {
                width: 24px;
                height: 24px;
                font-size: 10px;
            }
            .fine-status-item {
                padding: 4px 10px;
                font-size: 11px;
                margin: 2px 4px 2px 0;
            }
            .fine-status-count {
                font-size: 13px;
            }
        }

        @media (max-width: 480px) {
            .top-nav .nav-container {
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
            }
            .top-nav .nav-right {
                justify-content: center;
            }
            .stat-card .stat-value {
                font-size: 16px;
            }
            .table td, .table th {
                padding: 4px 6px;
                font-size: 11px;
            }
            .table .member-name {
                font-size: 12px;
            }
            .top-nav .nav-right .user-info .user-avatar {
                width: 24px;
                height: 24px;
            }
            .top-nav .nav-right .user-info .user-name {
                font-size: 11px;
            }
            .member-avatar-wrapper {
                width: 22px;
                height: 22px;
            }
            .member-item .member-info .name {
                font-size: 11px;
            }
            .member-item .member-info .sub {
                font-size: 9px;
            }
            .table .member-avatar-sm {
                width: 20px;
                height: 20px;
            }
            .table .avatar-placeholder-sm {
                width: 20px;
                height: 20px;
                font-size: 8px;
            }
            .fine-status-item {
                padding: 3px 8px;
                font-size: 10px;
            }
            .fine-status-count {
                font-size: 11px;
            }
            .main-content {
                padding: 10px 10px 20px 10px;
            }
        }

        .mt-3 { margin-top: 0.75rem !important; }
        .mb-3 { margin-bottom: 0.75rem !important; }
        .ms-2 { margin-left: 0.5rem !important; }
    </style>
</head>
<body>

<!-- ============================================
     TOP NAVIGATION - WITH LOGO & USER PROFILE
     ============================================ -->
<nav class="top-nav">
    <div class="nav-container">
        <!-- Brand with Logo -->
        <a href="/member/index.php" class="nav-brand">
            <div class="brand-icon">
                <?php if (!empty($fullLogoUrl)): ?>
                    <img src="<?= htmlspecialchars($fullLogoUrl) ?>" alt="Logo">
                <?php else: ?>
                    <span class="no-logo"><?= mb_substr($somity_name, 0, 1) ?></span>
                <?php endif; ?>
            </div>
            <span class="brand-text">
                <?= clean($somity_name) ?>
                <small>সদস্য প্যানেল</small>
            </span>
        </a>
        
        <div class="nav-right">
            <!-- User Info with Profile Photo -->
            <a href="profile.php?id=<?= urlencode($memberId) ?>" class="user-info-link">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php if ($hasPhoto && !empty($memberPhoto)): ?>
                            <img src="<?= htmlspecialchars($memberPhoto) ?>" alt="Profile">
                        <?php else: ?>
                            <span class="avatar-text"><?= mb_substr($memberName, 0, 1) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="user-details">
                        <span class="user-name"><?= clean($memberName) ?></span>
                        <?php if (!empty($memberId)): ?>
                            <span class="user-id"><?= clean($memberId) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
            <!-- Public Badge -->
            <span class="public-badge">
                <i data-feather="eye"></i>
                শুধু দেখুন
            </span>
            
            <!-- Dashboard Button -->
            <a href="/member/index.php" class="btn-dashboard">
                <i data-feather="home" style="width:16px;height:16px"></i>
                ড্যাশবোর্ড
            </a>
        </div>
    </div>
</nav>

<!-- ============================================
     MAIN CONTENT
     ============================================ -->
<div class="main-content">

    <!-- WELCOME BANNER -->
    <div class="welcome-banner">
        <div class="welcome-text">
            👋 স্বাগতম, <?= clean($memberName) ?>
            <small><?= $monthName ?> মাসের আপডেট ডেটা</small>
        </div>
        <div class="month-badge">
            <i data-feather="calendar" style="width:16px;height:16px;"></i>
            <?= $monthName ?>
        </div>
    </div>

    <!-- ============================================
         STATS CARDS - ROW 1
         ============================================ -->
    <div class="row g-2 g-md-3 mb-4">
        <div class="col-6 col-md-4 col-lg-2">
            <div class="stat-card bg-primary-grad text-center no-action">
                <div class="stat-icon"><i data-feather="users"></i></div>
                <div class="stat-value"><?= $totalMembers ?> জন</div>
                <div class="stat-label">সক্রিয় সদস্য</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="stat-card bg-success-grad text-center no-action">
                <div class="stat-icon"><i data-feather="check-circle"></i></div>
                <div class="stat-value"><?= $depositedThisMonth ?> জন</div>
                <div class="stat-label"><i class="fa-solid fa-circle-check fa-beat-fade" style="color:#22c55e;font-size:12px;vertical-align:middle;margin-right:3px;"></i> জমা দিয়েছে</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="stat-card bg-danger-grad text-center no-action">
                <div class="stat-icon"><i data-feather="x-circle"></i></div>
                <div class="stat-value"><?= $notDepositedThisMonth ?> জন</div>
                <div class="stat-label"><i class="fa-solid fa-circle-xmark fa-beat-fade" style="color:#dc3545;font-size:12px;vertical-align:middle;margin-right:3px;"></i> জমা দেয়নি</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="stat-card bg-warning-grad text-center no-action">
                <div class="stat-icon"><i data-feather="dollar-sign"></i></div>
                <div class="stat-value">৳<?= number_format($totalThisMonthDeposit, 0) ?></div>
                <div class="stat-label"><?= $monthName ?> এ জমা</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="stat-card bg-teal-grad text-center no-action">
                <div class="stat-icon"><i data-feather="credit-card"></i></div>
                <div class="stat-value">৳<?= number_format($totalDeposits, 0) ?></div>
                <div class="stat-label">নিয়মিত জমা</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="stat-card bg-purple-grad text-center no-action">
                <div class="stat-icon"><i data-feather="dollar-sign"></i></div>
                <div class="stat-value">৳<?= number_format($mainBalance, 0) ?></div>
                <div class="stat-label">মূল ব্যালেন্স</div>
            </div>
        </div>
    </div>
    
    <!-- ============================================
         STATS CARDS - ROW 1 (UPDATED WITH NEW VCARD)
         ============================================ -->
    <div class="row g-2 g-md-3 mb-4">
        <div class="col-6 col-md-4 col-lg-2">
            <div class="stat-card" style="background: linear-gradient(135deg, #FDEDEC,#D4AC0D);">
                <div class="stat-icon">
                    <i class="fa-solid fa-coins"></i>
                </div>
                <div class="stat-value">
                    ৳<?= number_format($totalMemberDeposits, 0) ?>
                </div>
                <div class="stat-label">🪙 মোট সদস্য জমা</div>
                <small class="text-muted" style="font-size:10px;display:block;margin-top:2px;">
                    সঞ্চয় + জরিমানা + রিটার্ন
                </small>
            </div>
        </div>
    </div>

    <!-- ============================================
         STATS CARDS - ROW 2
         ============================================ -->
    <div class="row g-2 mb-4">
        <div class="col-6 col-md-2">
            <div class="stat-card text-center no-action" style="background: linear-gradient(135deg, #FDEDEC, #FADBD8);">
                <div class="stat-icon"><i data-feather="arrow-up-circle" style="color:#E74C3C;"></i></div>
                <div class="stat-value"><?= $withdrawnThisMonth ?> জন</div>
                <div class="stat-label">📤 উত্তোলন করেছেন</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card text-center no-action" style="background: linear-gradient(135deg, #FEF9E7, #FDEBD0);">
                <div class="stat-icon"><i data-feather="dollar-sign" style="color:#D35400;"></i></div>
                <div class="stat-value">৳<?= number_format($totalThisMonthWithdrawal, 0) ?></div>
                <div class="stat-label">📤 <?= $monthName ?> এ উত্তোলন</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card text-center no-action" style="background: linear-gradient(135deg, #E8F8F5, #D1F2EB);">
                <div class="stat-icon"><i data-feather="shopping-bag" style="color:#1ABC9C;"></i></div>
                <div class="stat-value">৳<?= number_format($totalExpenses, 0) ?></div>
                <div class="stat-label">📊 মোট খরচ ( সকল বছরের )</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card text-center no-action" style="background: linear-gradient(135deg, #F5EEF8, #EBDEF0);">
                <div class="stat-icon"><i data-feather="alert-triangle" style="color:#8E44AD;"></i></div>
                <div class="stat-value">৳<?= number_format($totalFinePending, 0) ?></div>
                <div class="stat-label">⏳ বকেয়া জরিমানা</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card text-center no-action" style="background: linear-gradient(135deg, #FEF9E7, #FDEBD0);">
                <div class="stat-icon"><i data-feather="briefcase" style="color:#D35400;"></i></div>
                <div class="stat-value">৳<?= number_format($totalInvestments, 0) ?></div>
                <div class="stat-label">💼 মোট বিনিয়োগ</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card text-center no-action" style="background: linear-gradient(135deg, #EAF2F8, #D6EAF8);">
                <div class="stat-icon"><i data-feather="trending-up" style="color:#2E86C1;"></i></div>
                <div class="stat-value">৳<?= number_format($totalInvestmentReturns, 0) ?></div>
                <div class="stat-label">📈 মোট রিটার্ন</div>
            </div>
        </div>
    </div>

    <!-- ============================================
         FINE STATS CARDS - ROW 3
         ============================================ -->
    <div class="row g-2 mb-4">
        <div class="col-6 col-md-2">
            <div class="stat-card text-center no-action" style="background: linear-gradient(135deg, #FFF3E0, #FFE0B2);">
                <div class="stat-value" style="font-size:20px;"><?= $pendingFineCount ?></div>
                <div class="stat-label">⏳ পেন্ডিং</div>
                <small class="text-muted">৳<?= number_format($pendingFineAmount, 0) ?></small>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card text-center no-action" style="background: linear-gradient(135deg, #FFF8E1, #FFECB3);">
                <div class="stat-value" style="font-size:20px;"><?= $partiallyPaidFineCount ?></div>
                <div class="stat-label">🔄 আংশিক পরিশোধ</div>
                <small class="text-muted">৳<?= number_format($partialFineAmount, 0) ?></small>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card text-center no-action" style="background: linear-gradient(135deg, #E8F5E9, #C8E6C9);">
                <div class="stat-value" style="font-size:20px;"><?= $paidFineCount ?></div>
                <div class="stat-label"><i class="fa-solid fa-circle-check fa-beat-fade" style="color:#22c55e;font-size:12px;vertical-align:middle;margin-right:3px;"></i> পরিশোধিত</div>
                <small class="text-muted">৳<?= number_format($paidFineAmount, 0) ?></small>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card text-center no-action" style="background: linear-gradient(135deg, #E3F2FD, #BBDEFB);">
                <div class="stat-value" style="font-size:20px;"><?= $waivedFineCount ?></div>
                <div class="stat-label">⛔ মওকুফ</div>
                <small class="text-muted">৳<?= number_format($waivedFineAmount, 0) ?></small>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card text-center no-action" style="background: linear-gradient(135deg, #FCE4EC, #F8BBD0);">
                <div class="stat-value" style="font-size:20px;"><?= $overdueFines ?></div>
                <div class="stat-label">🔴 মেয়াদোত্তীর্ণ</div>
                <small class="text-muted">৳<?= number_format($overdueFineAmount, 0) ?></small>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card text-center no-action" style="background: linear-gradient(135deg, #F3E5F5, #E1BEE7);">
                <div class="stat-value" style="font-size:20px;">৳<?= number_format($totalFinePaid, 0) ?></div>
                <div class="stat-label">মোট জরিমানা পরিশোধিত</div>
                <small class="text-muted">সব পরিশোধিত জরিমানা</small>
            </div>
        </div>
    </div>

    <!-- ============================================
         FINE STATUS BREAKDOWN
         ============================================ -->
    <div class="row g-2 g-md-3 mb-3">
        <div class="col-12">
            <div class="card fine-status-card">
                <div class="card-body">
                    <h6 class="mb-3" style="color: #6C3483; font-weight: 600;">
                        <i data-feather="bar-chart-2" style="width:18px;height:18px"></i> 
                        📊 জরিমানা অবস্থা
                    </h6>
                    <div class="row g-2">
                        <div class="col-6 col-md-3">
                            <span class="fine-status-item pending">
                                ⏳ পেন্ডিং: <span class="fine-status-count"><?= $pendingFineCount ?></span>
                                <small> (৳<?= number_format($pendingFineAmount, 0) ?>)</small>
                            </span>
                        </div>
                        <div class="col-6 col-md-3">
                            <span class="fine-status-item partial">
                                🔄 আংশিক: <span class="fine-status-count"><?= $partiallyPaidFineCount ?></span>
                                <small> (৳<?= number_format($partialFineAmount, 0) ?>)</small>
                            </span>
                        </div>
                        <div class="col-6 col-md-3">
                            <span class="fine-status-item paid">
                                ✅ পরিশোধিত: <span class="fine-status-count"><?= $paidFineCount ?></span>
                                <small> (৳<?= number_format($paidFineAmount, 0) ?>)</small>
                            </span>
                        </div>
                        <div class="col-6 col-md-3">
                            <span class="fine-status-item waived">
                                ⛔ মওকুফ: <span class="fine-status-count"><?= $waivedFineCount ?></span>
                                <small> (৳<?= number_format($waivedFineAmount, 0) ?>)</small>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Progress Bar -->
                    <div class="mt-3">
                        <div class="progress">
                            <?php if ($totalFineCount > 0): 
                                $pending_pct = ($pendingFineCount / $totalFineCount) * 100;
                                $partial_pct = ($partiallyPaidFineCount / $totalFineCount) * 100;
                                $paid_pct = ($paidFineCount / $totalFineCount) * 100;
                                $waived_pct = ($waivedFineCount / $totalFineCount) * 100;
                            ?>
                            <?php if ($pending_pct > 0): ?>
                            <div class="progress-bar bg-warning" style="width: <?= $pending_pct ?>%;" title="পেন্ডিং">
                                ⏳ <?= round($pending_pct) ?>%
                            </div>
                            <?php endif; ?>
                            <?php if ($partial_pct > 0): ?>
                            <div class="progress-bar bg-info" style="width: <?= $partial_pct ?>%;" title="আংশিক">
                                🔄 <?= round($partial_pct) ?>%
                            </div>
                            <?php endif; ?>
                            <?php if ($paid_pct > 0): ?>
                            <div class="progress-bar bg-success" style="width: <?= $paid_pct ?>%;" title="পরিশোধিত">
                                ✅ <?= round($paid_pct) ?>%
                            </div>
                            <?php endif; ?>
                            <?php if ($waived_pct > 0): ?>
                            <div class="progress-bar bg-secondary" style="width: <?= $waived_pct ?>%;" title="মওকুফ">
                                ⛔ <?= round($waived_pct) ?>%
                            </div>
                            <?php endif; ?>
                            <?php else: ?>
                            <div class="progress-bar bg-secondary" style="width:100%;">
                                📭 কোনো জরিমানা নেই
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="mt-2 text-center" style="font-size:12px; color:#888;">
                            মোট জরিমানা: <?= $totalFineCount ?> টি | 
                            বাকি: ৳<?= number_format($totalFinePending, 0) ?> | 
                            পরিশোধ: ৳<?= number_format($totalFinePaid, 0) ?>
                        </div>
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
                    <span class="badge bg-secondary">শুধু দেখুন</span>
                </div>
                <div class="list-group list-group-flush scrollable-list">
                    <?php if (empty($pendingFineList)): ?>
                    <div class="list-group-item text-center text-success py-3 no-action">
                        <i data-feather="check-circle" style="width:24px;height:24px;display:block;margin:0 auto 6px;"></i>
                        কোনো বকেয়া জরিমানা নেই! 🎉
                    </div>
                    <?php else: ?>
                    <?php foreach ($pendingFineList as $fine): ?>
                    <div class="list-group-item no-action">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="member-item">
                                <?= renderMemberAvatar($fine['member_photo'] ?? null, $fine['member_name'] ?? '', 32) ?>
                                <div class="member-info">
                                    <div class="name"><?= clean($fine['member_name'] ?? '') ?></div>
                                    <div class="sub">
                                        <code><?= clean($fine['member_code'] ?? '') ?></code>
                                        <span>মেয়াদ: <?= date('d M Y', strtotime($fine['due_date'])) ?></span>
                                        <?php if (strtotime($fine['due_date']) < time()): ?>
                                        <span class="badge bg-danger">মেয়াদোত্তীর্ণ</span>
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
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- RECENT DEPOSITS -->
            <div class="card mt-3">
                <div class="card-header">
                    <span class="card-title"><i data-feather="clock" style="width:16px;height:16px"></i> সর্বশেষ জমা</span>
                    <span class="badge bg-secondary">শুধু দেখুন</span>
                </div>
                <div class="list-group list-group-flush scrollable-list">
                    <?php if (empty($recentDeposits)): ?>
                    <div class="list-group-item text-center text-muted py-3 no-action">কোনো জমা নেই</div>
                    <?php else: ?>
                    <?php foreach ($recentDeposits as $dep): ?>
                    <div class="list-group-item no-action">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="member-item">
                                <?= renderMemberAvatar($dep['photo'] ?? null, $dep['member_name'] ?? '', 32) ?>
                                <div class="member-info">
                                    <div class="name"><?= clean($dep['member_name'] ?? '') ?></div>
                                    <div class="sub">
                                        <code><?= clean($dep['member_code'] ?? '') ?></code>
                                        <span><?= timeAgo($dep['deposit_date'] ?? date('Y-m-d')) ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="amount-positive fw-bold">৳<?= number_format($dep['amount'] ?? 0, 0) ?></div>
                        </div>
                    </div>
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
                    <div class="list-group-item text-center text-muted py-3 no-action"><?= $monthName ?> এ কেউ জমা দেয়নি</div>
                    <?php else: ?>
                    <?php foreach ($depositedMembers as $member): ?>
                    <div class="list-group-item no-action">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="member-item">
                                <?= renderMemberAvatar($member['photo'] ?? null, $member['name'] ?? '', 32) ?>
                                <div class="member-info">
                                    <div class="name"><?= clean($member['name'] ?? '') ?></div>
                                    <div class="sub">
                                        <code><?= clean($member['member_id'] ?? '') ?></code>
                                        <span>মাসিক: ৳<?= number_format($member['monthly_deposit'] ?? 0, 0) ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="amount-positive fw-bold">৳<?= number_format($member['amount'] ?? 0, 0) ?></div>
                        </div>
                    </div>
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
                    <div class="list-group-item no-action">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="member-item">
                                <?= renderMemberAvatar($member['photo'] ?? null, $member['name'] ?? '', 32) ?>
                                <div class="member-info">
                                    <div class="name"><?= clean($member['name'] ?? '') ?></div>
                                    <div class="sub">
                                        <code><?= clean($member['member_id'] ?? '') ?></code>
                                        <span>🎂 <?= date('d M', strtotime($member['dob'])) ?></span>
                                    </div>
                                </div>
                            </div>
                            <span class="badge bg-purple">আসছে</span>
                        </div>
                    </div>
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
                    <span class="badge bg-secondary">শুধু দেখুন</span>
                </div>
                <div class="list-group list-group-flush scrollable-list">
                    <?php if (empty($recentFinePayments)): ?>
                    <div class="list-group-item text-center text-muted py-3 no-action">কোনো জরিমানা পরিশোধ নেই</div>
                    <?php else: ?>
                    <?php foreach ($recentFinePayments as $fp): ?>
                    <div class="list-group-item no-action">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="member-item">
                                <?= renderMemberAvatar($fp['member_photo'] ?? null, $fp['member_name'] ?? '', 32) ?>
                                <div class="member-info">
                                    <div class="name"><?= clean($fp['member_name'] ?? '') ?></div>
                                    <div class="sub">
                                        <code><?= clean($fp['member_code'] ?? '') ?></code>
                                        <span><?= timeAgo($fp['payment_date'] ?? date('Y-m-d')) ?></span>
                                        <span class="badge <?= $fp['fine_status'] == 'paid' ? 'bg-success' : ($fp['fine_status'] == 'partially_paid' ? 'bg-warning' : 'bg-secondary') ?>">
                                            <?= ucfirst(str_replace('_', ' ', $fp['fine_status'])) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="amount-negative fw-bold">-৳<?= number_format($fp['amount'] ?? 0, 0) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- RECENT WITHDRAWALS -->
            <div class="card mt-3">
                <div class="card-header">
                    <span class="card-title"><i data-feather="arrow-up-circle" style="width:16px;height:16px;color:#E74C3C;"></i> সর্বশেষ উত্তোলন</span>
                    <span class="badge bg-secondary">শুধু দেখুন</span>
                </div>
                <div class="list-group list-group-flush scrollable-list">
                    <?php if (empty($recentWithdrawals)): ?>
                    <div class="list-group-item text-center text-muted py-3 no-action">কোনো উত্তোলন নেই</div>
                    <?php else: ?>
                    <?php foreach ($recentWithdrawals as $w): ?>
                    <div class="list-group-item no-action">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="member-item">
                                <?= renderMemberAvatar($w['photo'] ?? null, $w['member_name'] ?? '', 32) ?>
                                <div class="member-info">
                                    <div class="name"><?= clean($w['member_name'] ?? '') ?></div>
                                    <div class="sub">
                                        <code><?= clean($w['member_code'] ?? '') ?></code>
                                        <span><?= timeAgo($w['withdrawal_date'] ?? date('Y-m-d')) ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="amount-negative fw-bold">-৳<?= number_format($w['amount'] ?? 0, 0) ?></div>
                        </div>
                    </div>
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
                    <div class="list-group-item text-center text-success py-3 no-action">
                        <i data-feather="check-circle" style="width:24px;height:24px;display:block;margin:0 auto 6px;"></i>
                        সবাই জমা দিয়েছে! 🎉
                    </div>
                    <?php else: ?>
                    <?php foreach ($notDepositedMembers as $member): ?>
                    <div class="list-group-item no-action">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="member-item">
                                <?= renderMemberAvatar($member['photo'] ?? null, $member['name'] ?? '', 32) ?>
                                <div class="member-info">
                                    <div class="name"><?= clean($member['name'] ?? '') ?></div>
                                    <div class="sub">
                                        <code><?= clean($member['member_id'] ?? '') ?></code>
                                        <span>বকেয়া: ৳<?= number_format($member['monthly_deposit'] ?? 0, 0) ?></span>
                                    </div>
                                </div>
                            </div>
                            <span class="badge bg-danger">বকেয়া</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- ============================================
             INVESTMENT LIST - FULL WIDTH
             ============================================ -->
        <div class="col-12">
            <div class="card" style="border-left: 4px solid #F39C12;">
                <div class="card-header" style="background:#FEF9E7;">
                    <span class="card-title">
                        <i data-feather="briefcase" style="color:#F39C12;"></i> 
                        বিনিয়োগ তালিকা (<?= count($investmentList) ?>)
                    </span>
                    <span class="badge bg-secondary">শুধু দেখুন</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>শিরোনাম</th>
                                <th>ধরন</th>
                                <th>মূলধন</th>
                                <th>মুনাফা</th>
                                <th>মোট ফেরত</th>
                                <th>ফেরত পাওয়া</th>
                                <th>অবস্থা</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($investmentList)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-3">কোনো বিনিয়োগ নেই</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($investmentList as $inv): 
                                $profit = (float)$inv['expected_profit'];
                                $totalExpected = (float)$inv['amount'] + $profit;
                                $returned = (float)$inv['total_return'];
                                $remaining = $totalExpected - $returned;
                                $progress = $totalExpected > 0 ? ($returned / $totalExpected) * 100 : 0;
                                $profitPercent = $inv['profit_percent'] ?? 0;
                            ?>
                            <tr>
                                <td>
                                    <div style="font-weight:600;font-size:14px;"><?= clean($inv['title']) ?></div>
                                    <div class="investment-progress">
                                        <div class="progress-bar <?= $progress >= 100 ? 'bg-success' : 'bg-info' ?>" 
                                             style="width: <?= min(100, $progress) ?>%;height:6px;border-radius:10px;"></div>
                                    </div>
                                    <small class="text-muted" style="font-size:10px;"><?= number_format($progress, 1) ?>% সম্পন্ন</small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark"><?= clean($inv['investment_type']) ?></span>
                                </td>
                                <td class="amount-neutral" style="font-weight:600;">৳<?= number_format((float)$inv['amount'], 0) ?></td>
                                <td>
                                    <div class="amount-positive">৳<?= number_format($profit, 0) ?></div>
                                    <small class="text-muted">(<?= number_format($profitPercent, 1) ?>%)</small>
                                </td>
                                <td>
                                    <strong>৳<?= number_format($totalExpected, 0) ?></strong>
                                    <br><small class="text-muted">মূল + মুনাফা</small>
                                </td>
                                <td>
                                    <div><?= number_format($returned, 0) ?></div>
                                    <?php if ($remaining > 0): ?>
                                    <small class="text-warning">বাকি: ৳<?= number_format($remaining, 0) ?></small>
                                    <?php else: ?>
                                    <small class="text-success">✅ সম্পূর্ণ</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= getInvestmentStatusColor($inv['status']) ?>">
                                        <?= getInvestmentStatusLabel($inv['status']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ============================================
             RECENT EXPENSES - FULL WIDTH
             ============================================ -->
        <div class="col-12">
            <div class="card" style="border-left: 4px solid #E74C3C;">
                <div class="card-header" style="background:#FDEDEC;">
                    <span class="card-title">
                        <i data-feather="shopping-bag" style="color:#E74C3C;"></i> 
                        সর্বশেষ খরচ (<?= count($recentExpenses) ?>)
                    </span>
                    <span class="badge bg-secondary">শুধু দেখুন</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th>শিরোনাম</th>
                                <th>ক্যাটাগরি</th>
                                <th>পরিমাণ</th>
                                <th>তারিখ</th>
                                <th>বিবরণ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentExpenses)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">কোনো খরচ নেই</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($recentExpenses as $exp): ?>
                            <tr>
                                <td><?= clean($exp['title']) ?></td>
                                <td>
                                    <span class="badge bg-secondary"><?= clean($exp['category']) ?></span>
                                </td>
                                <td class="amount-negative fw-bold">-৳<?= number_format($exp['amount'], 0) ?></td>
                                <td><?= date('d M Y', strtotime($exp['expense_date'])) ?></td>
                                <td style="font-size:12px;"><?= clean($exp['description'] ?? '-') ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ============================================
             RECENT MEMBERS - Show 10 Members with Photo
             ============================================ -->
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <span class="card-title">
                        <i data-feather="users" style="width:16px;height:16px"></i> 
                        সর্বশেষ <?= count($recentMembers) ?> জন সদস্য
                    </span>
                    <span class="badge bg-secondary">শুধু দেখুন</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>সদস্য</th>
                                <th>যোগদানের তারিখ</th>
                                <th>অবস্থা</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentMembers)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">কোনো সদস্য নেই</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($recentMembers as $index => $member): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <div class="member-cell">
                                        <?php 
                                        $photo = $member['photo'] ?? null;
                                        $name = $member['name'] ?? '';
                                        if (!empty($photo) && file_exists($_SERVER['DOCUMENT_ROOT'] . $photo)): 
                                        ?>
                                            <img src="<?= htmlspecialchars($photo) ?>" alt="<?= clean($name) ?>" class="member-avatar-sm">
                                        <?php else: 
                                            $firstLetter = mb_substr($name, 0, 1, 'UTF-8');
                                            $color = getColor($name);
                                        ?>
                                            <div class="avatar-placeholder-sm" style="background:<?= $color ?>;"><?= htmlspecialchars($firstLetter) ?></div>
                                        <?php endif; ?>
                                        <span class="member-name"><?= clean($name) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark">
                                        <i data-feather="calendar" style="width:12px;height:12px"></i>
                                        <?= formatDate($member['join_date']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= $member['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= $member['status'] === 'active' ? '✅ সক্রিয়' : '⛔ নিষ্ক্রিয়' ?>
                                    </span>
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
</div><!-- /main-content -->

<!-- ============================================
     INCLUDE FOOTER (Bottom Nav) - ONLY ONCE
     ============================================ -->
<?php require_once __DIR__ . '/includes/footer.php'; ?>

<!-- ============================================
     SCRIPTS
     ============================================ -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
});
</script>

</body>
</html>