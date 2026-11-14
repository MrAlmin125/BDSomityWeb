<?php
// =====================================================
// admin/full_report.php - সম্পূর্ণ ড্যাশবোর্ড (সব ডেটা + ফাইনস + বিনিয়োগ)
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

$pageTitle = 'সম্পূর্ণ ড্যাশবোর্ড';
$conn = conn();

// ============================================
// GET FILTER PARAMETERS
// ============================================
$selectedMonth = isset($_GET['month']) ? intval($_GET['month']) : 0;
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : 0;

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
// BUILD DATE FILTER CONDITIONS
// ============================================
function buildDateFilter($table, $column, $selectedMonth, $selectedYear) {
    $where = "";
    if ($selectedMonth > 0 && $selectedYear > 0) {
        $where = " AND YEAR($table.$column) = $selectedYear AND MONTH($table.$column) = $selectedMonth ";
    } elseif ($selectedYear > 0) {
        $where = " AND YEAR($table.$column) = $selectedYear ";
    } elseif ($selectedMonth > 0) {
        $where = " AND MONTH($table.$column) = $selectedMonth ";
    }
    return $where;
}

function buildDateFilterSimple($column, $selectedMonth, $selectedYear) {
    $where = "";
    if ($selectedMonth > 0 && $selectedYear > 0) {
        $where = " WHERE YEAR($column) = $selectedYear AND MONTH($column) = $selectedMonth ";
    } elseif ($selectedYear > 0) {
        $where = " WHERE YEAR($column) = $selectedYear ";
    } elseif ($selectedMonth > 0) {
        $where = " WHERE MONTH($column) = $selectedMonth ";
    }
    return $where;
}

// ============================================
// 1. সদস্য সম্পর্কিত ডেটা
// ============================================
$totalMembers = 0;
$activeMembers = 0;
$inactiveMembers = 0;
$totalMembersEver = 0;

try {
    $result = $conn->query("SELECT COUNT(*) as count FROM members WHERE status = 'active'");
    if ($result) {
        $row = $result->fetch_assoc();
        $activeMembers = $row['count'] ?? 0;
    }
    
    $result = $conn->query("SELECT COUNT(*) as count FROM members WHERE status = 'inactive'");
    if ($result) {
        $row = $result->fetch_assoc();
        $inactiveMembers = $row['count'] ?? 0;
    }
    
    $result = $conn->query("SELECT COUNT(*) as count FROM members");
    if ($result) {
        $row = $result->fetch_assoc();
        $totalMembersEver = $row['count'] ?? 0;
    }
    
    $totalMembers = $activeMembers;
} catch (Exception $e) {}

// ============================================
// 2. বিনিয়োগ সম্পর্কিত ডেটা (WITH DATE FILTER)
// ============================================
$totalInvestments = 0;
$totalInvestmentReturns = 0;
$totalInvestmentsCount = 0;
$activeInvestments = 0;
$completedInvestments = 0;
$paidInvestments = 0;

try {
    $tableCheck = $conn->query("SHOW TABLES LIKE 'investments'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        
        // Build where clause for investments
        $investmentWhere = "";
        if ($selectedMonth > 0 && $selectedYear > 0) {
            $investmentWhere = " WHERE YEAR(created_at) = $selectedYear AND MONTH(created_at) = $selectedMonth ";
        } elseif ($selectedYear > 0) {
            $investmentWhere = " WHERE YEAR(created_at) = $selectedYear ";
        } elseif ($selectedMonth > 0) {
            $investmentWhere = " WHERE MONTH(created_at) = $selectedMonth ";
        }
        
        $result = $conn->query("SELECT COALESCE(SUM(amount),0) as total FROM investments $investmentWhere");
        if ($result) {
            $row = $result->fetch_assoc();
            $totalInvestments = $row['total'] ?? 0;
        }
        
        $result = $conn->query("SELECT COUNT(*) as count FROM investments $investmentWhere");
        if ($result) {
            $row = $result->fetch_assoc();
            $totalInvestmentsCount = $row['count'] ?? 0;
        }
        
        $result = $conn->query("SELECT status, COUNT(*) as count FROM investments $investmentWhere GROUP BY status");
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
    }
    
    $tableCheck = $conn->query("SHOW TABLES LIKE 'investment_returns'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        $irWhere = "";
        if ($selectedMonth > 0 && $selectedYear > 0) {
            $irWhere = " WHERE YEAR(return_date) = $selectedYear AND MONTH(return_date) = $selectedMonth ";
        } elseif ($selectedYear > 0) {
            $irWhere = " WHERE YEAR(return_date) = $selectedYear ";
        } elseif ($selectedMonth > 0) {
            $irWhere = " WHERE MONTH(return_date) = $selectedMonth ";
        }
        $result = $conn->query("SELECT COALESCE(SUM(return_amount),0) as total FROM investment_returns $irWhere");
        if ($result) {
            $row = $result->fetch_assoc();
            $totalInvestmentReturns = $row['total'] ?? 0;
        }
    }
} catch (Exception $e) {
    error_log("Investment statistics error: " . $e->getMessage());
}

// ============================================
// 3. সঞ্চয়, খরচ ও ফাইনস সম্পর্কিত ডেটা
// ============================================
$totalDeposits = 0;
$totalRegularDeposits = 0;
$totalFineDeposits = 0;
$totalWithdrawals = 0;
$totalExpenses = 0;
$netSavings = 0;
$totalDues = 0;
$totalDuesCollected = 0;

try {
    // Build date filter for deposits
    $depositWhere = "";
    if ($selectedMonth > 0 && $selectedYear > 0) {
        $depositWhere = " AND YEAR(deposit_date) = $selectedYear AND MONTH(deposit_date) = $selectedMonth ";
    } elseif ($selectedYear > 0) {
        $depositWhere = " AND YEAR(deposit_date) = $selectedYear ";
    } elseif ($selectedMonth > 0) {
        $depositWhere = " AND MONTH(deposit_date) = $selectedMonth ";
    }
    
    // ✅ মোট regular ডিপোজিট
    $result = $conn->query("SELECT COALESCE(SUM(amount),0) as total FROM deposits WHERE deposit_type = 'regular' $depositWhere");
    if ($result) {
        $row = $result->fetch_assoc();
        $totalRegularDeposits = $row['total'] ?? 0;
    }
    
    // ✅ মোট fine ডিপোজিট
    $fineWhere = "";
    if ($selectedMonth > 0 && $selectedYear > 0) {
        $fineWhere = " AND YEAR(paid_date) = $selectedYear AND MONTH(paid_date) = $selectedMonth ";
    } elseif ($selectedYear > 0) {
        $fineWhere = " AND YEAR(paid_date) = $selectedYear ";
    } elseif ($selectedMonth > 0) {
        $fineWhere = " AND MONTH(paid_date) = $selectedMonth ";
    }
    $result = $conn->query("SELECT COALESCE(SUM(paid_amount),0) as total FROM fines WHERE status = 'paid' $fineWhere");
    if ($result) {
        $row = $result->fetch_assoc();
        $totalFineDeposits = $row['total'] ?? 0;
    }
    
    $totalDeposits = $totalRegularDeposits + $totalFineDeposits;
    
    // ✅ মোট উত্তোলন
    $withdrawWhere = "";
    if ($selectedMonth > 0 && $selectedYear > 0) {
        $withdrawWhere = " AND YEAR(withdrawal_date) = $selectedYear AND MONTH(withdrawal_date) = $selectedMonth ";
    } elseif ($selectedYear > 0) {
        $withdrawWhere = " AND YEAR(withdrawal_date) = $selectedYear ";
    } elseif ($selectedMonth > 0) {
        $withdrawWhere = " AND MONTH(withdrawal_date) = $selectedMonth ";
    }
    $result = $conn->query("SELECT COALESCE(SUM(amount),0) as total FROM withdrawals WHERE status='completed' $withdrawWhere");
    if ($result) {
        $row = $result->fetch_assoc();
        $totalWithdrawals = $row['total'] ?? 0;
    }
    
    // ✅ মোট খরচ
    $expenseWhere = "";
    if ($selectedMonth > 0 && $selectedYear > 0) {
        $expenseWhere = " AND YEAR(expense_date) = $selectedYear AND MONTH(expense_date) = $selectedMonth ";
    } elseif ($selectedYear > 0) {
        $expenseWhere = " AND YEAR(expense_date) = $selectedYear ";
    } elseif ($selectedMonth > 0) {
        $expenseWhere = " AND MONTH(expense_date) = $selectedMonth ";
    }
    $result = $conn->query("SELECT COALESCE(SUM(amount),0) as total FROM expenses $expenseWhere");
    if ($result) {
        $row = $result->fetch_assoc();
        $totalExpenses = $row['total'] ?? 0;
    }
    
    // ✅ বকেয়া সঞ্চয়
    $result = $conn->query("SELECT COALESCE(SUM(amount - paid_amount),0) as total FROM monthly_dues WHERE status != 'paid'");
    if ($result) {
        $row = $result->fetch_assoc();
        $totalDues = $row['total'] ?? 0;
    }
    
    // ✅ মোট আদায়কৃত সঞ্চয়
    $duesWhere = "";
    if ($selectedMonth > 0 && $selectedYear > 0) {
        $duesWhere = " AND YEAR(paid_date) = $selectedYear AND MONTH(paid_date) = $selectedMonth ";
    } elseif ($selectedYear > 0) {
        $duesWhere = " AND YEAR(paid_date) = $selectedYear ";
    } elseif ($selectedMonth > 0) {
        $duesWhere = " AND MONTH(paid_date) = $selectedMonth ";
    }
    $result = $conn->query("SELECT COALESCE(SUM(paid_amount),0) as total FROM monthly_dues WHERE status IN ('paid','partial') $duesWhere");
    if ($result) {
        $row = $result->fetch_assoc();
        $totalDuesCollected = $row['total'] ?? 0;
    }
} catch (Exception $e) {
    error_log("Error in calculations: " . $e->getMessage());
}

// ============================================
// 4. নেট সঞ্চয় = (regular + fine + রিটার্ন) - (উত্তোলন + খরচ + বিনিয়োগ)
// ============================================
$netSavings = ($totalDeposits + $totalInvestmentReturns) - ($totalWithdrawals + $totalExpenses + $totalInvestments);

// ============================================
// 5. ঋণ সম্পর্কিত ডেটা
// ============================================
$totalLoansGiven = 0;
$totalLoansReceived = 0;
$totalLoanInterest = 0;
$activeLoans = 0;
$completedLoans = 0;

try {
    $result = $conn->query("SELECT COALESCE(SUM(principal_amount),0) as total FROM loans WHERE status != 'rejected'");
    if ($result) {
        $row = $result->fetch_assoc();
        $totalLoansGiven = $row['total'] ?? 0;
    }
    
    $loanWhere = "";
    if ($selectedMonth > 0 && $selectedYear > 0) {
        $loanWhere = " AND YEAR(paid_date) = $selectedYear AND MONTH(paid_date) = $selectedMonth ";
    } elseif ($selectedYear > 0) {
        $loanWhere = " AND YEAR(paid_date) = $selectedYear ";
    } elseif ($selectedMonth > 0) {
        $loanWhere = " AND MONTH(paid_date) = $selectedMonth ";
    }
    $result = $conn->query("SELECT COALESCE(SUM(paid_amount),0) as total FROM loan_payments WHERE status='paid' $loanWhere");
    if ($result) {
        $row = $result->fetch_assoc();
        $totalLoansReceived = $row['total'] ?? 0;
    }
    
    $result = $conn->query("SELECT COALESCE(SUM(interest_amount),0) as total FROM loan_payments WHERE status='paid' $loanWhere");
    if ($result) {
        $row = $result->fetch_assoc();
        $totalLoanInterest = $row['total'] ?? 0;
    }
    
    $result = $conn->query("SELECT COUNT(*) as count FROM loans WHERE status='active'");
    if ($result) {
        $row = $result->fetch_assoc();
        $activeLoans = $row['count'] ?? 0;
    }
    
    $result = $conn->query("SELECT COUNT(*) as count FROM loans WHERE status='completed'");
    if ($result) {
        $row = $result->fetch_assoc();
        $completedLoans = $row['count'] ?? 0;
    }
} catch (Exception $e) {}

// ============================================
// 6. ফাইনস সম্পর্কিত ডেটা
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
$fineTransactionsCount = 0;

try {
    $tableCheck = $conn->query("SHOW TABLES LIKE 'fines'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        
        $fineWhere = "";
        if ($selectedMonth > 0 && $selectedYear > 0) {
            $fineWhere = " WHERE YEAR(created_at) = $selectedYear AND MONTH(created_at) = $selectedMonth ";
        } elseif ($selectedYear > 0) {
            $fineWhere = " WHERE YEAR(created_at) = $selectedYear ";
        } elseif ($selectedMonth > 0) {
            $fineWhere = " WHERE MONTH(created_at) = $selectedMonth ";
        }
        
        $result = $conn->query("
            SELECT 
                COUNT(*) as total_count,
                COALESCE(SUM(fine_amount), 0) as total_fine,
                COALESCE(SUM(paid_amount), 0) as total_paid,
                COALESCE(SUM(waived_amount), 0) as total_waived,
                COALESCE(SUM(fine_amount - paid_amount - waived_amount), 0) as total_pending
            FROM fines $fineWhere
        ");
        if ($result) {
            $row = $result->fetch_assoc();
            $totalFineCount = $row['total_count'] ?? 0;
            $totalFineAmount = $row['total_fine'] ?? 0;
            $totalFinePaid = $row['total_paid'] ?? 0;
            $totalFineWaived = $row['total_waived'] ?? 0;
            $totalFinePending = $row['total_pending'] ?? 0;
        }

        $result = $conn->query("SELECT status, COUNT(*) as count FROM fines $fineWhere GROUP BY status");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                if ($row['status'] == 'pending') $pendingFineCount = $row['count'];
                elseif ($row['status'] == 'partially_paid') $partiallyPaidFineCount = $row['count'];
                elseif ($row['status'] == 'paid') $paidFineCount = $row['count'];
                elseif ($row['status'] == 'waived') $waivedFineCount = $row['count'];
            }
        }

        $result = $conn->query("
            SELECT COUNT(*) as count 
            FROM fines 
            WHERE due_date < CURDATE() 
            AND status IN ('pending', 'partially_paid')
        ");
        if ($result) {
            $row = $result->fetch_assoc();
            $overdueFines = $row['count'] ?? 0;
        }
        
        $txCheck = $conn->query("SHOW TABLES LIKE 'fine_payments'");
        if ($txCheck && $txCheck->num_rows > 0) {
            $fpWhere = "";
            if ($selectedMonth > 0 && $selectedYear > 0) {
                $fpWhere = " WHERE YEAR(payment_date) = $selectedYear AND MONTH(payment_date) = $selectedMonth ";
            } elseif ($selectedYear > 0) {
                $fpWhere = " WHERE YEAR(payment_date) = $selectedYear ";
            } elseif ($selectedMonth > 0) {
                $fpWhere = " WHERE MONTH(payment_date) = $selectedMonth ";
            }
            $result = $conn->query("SELECT COUNT(*) as count FROM fine_payments $fpWhere");
            if ($result) {
                $row = $result->fetch_assoc();
                $fineTransactionsCount = $row['count'] ?? 0;
            }
        }
    }
} catch (Exception $e) {
    error_log("Fine statistics error: " . $e->getMessage());
}

// ============================================
// 7. আয় সম্পর্কিত ডেটা
// ============================================
$totalIncome = 0;
$totalProfit = 0;

try {
    $incomeWhere = "";
    if ($selectedMonth > 0 && $selectedYear > 0) {
        $incomeWhere = " AND YEAR(paid_date) = $selectedYear AND MONTH(paid_date) = $selectedMonth ";
    } elseif ($selectedYear > 0) {
        $incomeWhere = " AND YEAR(paid_date) = $selectedYear ";
    } elseif ($selectedMonth > 0) {
        $incomeWhere = " AND MONTH(paid_date) = $selectedMonth ";
    }
    
    $result = $conn->query("
        SELECT COALESCE(SUM(paid_amount),0) as total 
        FROM monthly_dues 
        WHERE status IN ('paid','partial') $incomeWhere
    ");
    if ($result) {
        $row = $result->fetch_assoc();
        $totalIncome = $row['total'] ?? 0;
    }
    
    $loanWhere = "";
    if ($selectedMonth > 0 && $selectedYear > 0) {
        $loanWhere = " AND YEAR(paid_date) = $selectedYear AND MONTH(paid_date) = $selectedMonth ";
    } elseif ($selectedYear > 0) {
        $loanWhere = " AND YEAR(paid_date) = $selectedYear ";
    } elseif ($selectedMonth > 0) {
        $loanWhere = " AND MONTH(paid_date) = $selectedMonth ";
    }
    $result = $conn->query("SELECT COALESCE(SUM(interest_amount),0) as total FROM loan_payments WHERE status='paid' $loanWhere");
    if ($result) {
        $row = $result->fetch_assoc();
        $totalIncome += $row['total'] ?? 0;
    }
    
    $irWhere = "";
    if ($selectedMonth > 0 && $selectedYear > 0) {
        $irWhere = " WHERE YEAR(return_date) = $selectedYear AND MONTH(return_date) = $selectedMonth ";
    } elseif ($selectedYear > 0) {
        $irWhere = " WHERE YEAR(return_date) = $selectedYear ";
    } elseif ($selectedMonth > 0) {
        $irWhere = " WHERE MONTH(return_date) = $selectedMonth ";
    }
    $result = $conn->query("SELECT COALESCE(SUM(return_amount),0) as total FROM investment_returns $irWhere");
    if ($result) {
        $row = $result->fetch_assoc();
        $totalIncome += $row['total'] ?? 0;
    }
    
    $txCheck = $conn->query("SHOW TABLES LIKE 'fine_payments'");
    if ($txCheck && $txCheck->num_rows > 0) {
        $fpWhere = "";
        if ($selectedMonth > 0 && $selectedYear > 0) {
            $fpWhere = " WHERE YEAR(payment_date) = $selectedYear AND MONTH(payment_date) = $selectedMonth ";
        } elseif ($selectedYear > 0) {
            $fpWhere = " WHERE YEAR(payment_date) = $selectedYear ";
        } elseif ($selectedMonth > 0) {
            $fpWhere = " WHERE MONTH(payment_date) = $selectedMonth ";
        }
        $result = $conn->query("SELECT COALESCE(SUM(amount),0) as total FROM fine_payments $fpWhere");
        if ($result) {
            $row = $result->fetch_assoc();
            $totalIncome += $row['total'] ?? 0;
        }
    }
    
    $totalProfit = $totalIncome - $totalExpenses;
} catch (Exception $e) {}

// ============================================
// 8. লেনদেনের সংখ্যা
// ============================================
$totalDepositCount = 0;
$totalWithdrawalCount = 0;
$totalLoanPaymentCount = 0;
$totalExpenseCount = 0;

try {
    $depositWhere = "";
    if ($selectedMonth > 0 && $selectedYear > 0) {
        $depositWhere = " AND YEAR(deposit_date) = $selectedYear AND MONTH(deposit_date) = $selectedMonth ";
    } elseif ($selectedYear > 0) {
        $depositWhere = " AND YEAR(deposit_date) = $selectedYear ";
    } elseif ($selectedMonth > 0) {
        $depositWhere = " AND MONTH(deposit_date) = $selectedMonth ";
    }
    $result = $conn->query("SELECT COUNT(*) as count FROM deposits WHERE deposit_type = 'regular' OR deposit_type = 'fine' $depositWhere");
    if ($result) {
        $row = $result->fetch_assoc();
        $totalDepositCount = $row['count'] ?? 0;
    }
    
    $withdrawWhere = "";
    if ($selectedMonth > 0 && $selectedYear > 0) {
        $withdrawWhere = " AND YEAR(withdrawal_date) = $selectedYear AND MONTH(withdrawal_date) = $selectedMonth ";
    } elseif ($selectedYear > 0) {
        $withdrawWhere = " AND YEAR(withdrawal_date) = $selectedYear ";
    } elseif ($selectedMonth > 0) {
        $withdrawWhere = " AND MONTH(withdrawal_date) = $selectedMonth ";
    }
    $result = $conn->query("SELECT COUNT(*) as count FROM withdrawals WHERE status='completed' $withdrawWhere");
    if ($result) {
        $row = $result->fetch_assoc();
        $totalWithdrawalCount = $row['count'] ?? 0;
    }
    
    $loanWhere = "";
    if ($selectedMonth > 0 && $selectedYear > 0) {
        $loanWhere = " AND YEAR(paid_date) = $selectedYear AND MONTH(paid_date) = $selectedMonth ";
    } elseif ($selectedYear > 0) {
        $loanWhere = " AND YEAR(paid_date) = $selectedYear ";
    } elseif ($selectedMonth > 0) {
        $loanWhere = " AND MONTH(paid_date) = $selectedMonth ";
    }
    $result = $conn->query("SELECT COUNT(*) as count FROM loan_payments WHERE status='paid' $loanWhere");
    if ($result) {
        $row = $result->fetch_assoc();
        $totalLoanPaymentCount = $row['count'] ?? 0;
    }
    
    $expenseWhere = "";
    if ($selectedMonth > 0 && $selectedYear > 0) {
        $expenseWhere = " AND YEAR(expense_date) = $selectedYear AND MONTH(expense_date) = $selectedMonth ";
    } elseif ($selectedYear > 0) {
        $expenseWhere = " AND YEAR(expense_date) = $selectedYear ";
    } elseif ($selectedMonth > 0) {
        $expenseWhere = " AND MONTH(expense_date) = $selectedMonth ";
    }
    $result = $conn->query("SELECT COUNT(*) as count FROM expenses $expenseWhere");
    if ($result) {
        $row = $result->fetch_assoc();
        $totalExpenseCount = $row['count'] ?? 0;
    }
} catch (Exception $e) {}

// ============================================
// 9. সর্বশেষ লেনদেন
// ============================================
$recentTransactions = [];

try {
    $limit = 30;
    $dateWhere = "";
    if ($selectedMonth > 0 && $selectedYear > 0) {
        $dateWhere = " AND DATE(date) >= '$selectedYear-$selectedMonth-01' AND DATE(date) <= LAST_DAY('$selectedYear-$selectedMonth-01') ";
    } elseif ($selectedYear > 0) {
        $dateWhere = " AND YEAR(date) = $selectedYear ";
    } elseif ($selectedMonth > 0) {
        $dateWhere = " AND MONTH(date) = $selectedMonth ";
    }
    
    $result = $conn->query("
        (SELECT 'জমা' as type, d.amount, d.deposit_date as date, m.name as member_name, d.member_id
         FROM deposits d
         JOIN members m ON m.id = d.member_id
         WHERE (d.deposit_type = 'regular' OR d.deposit_type = 'fine') 
         ORDER BY d.deposit_date DESC LIMIT 3)
        UNION ALL
        (SELECT 'উত্তোলন' as type, w.amount, w.withdrawal_date as date, m.name as member_name, w.member_id
         FROM withdrawals w
         JOIN members m ON m.id = w.member_id
         WHERE w.status='completed'
         ORDER BY w.withdrawal_date DESC LIMIT 3)
        UNION ALL
        (SELECT 'ঋণ আদায়' as type, lp.paid_amount as amount, lp.paid_date as date, m.name as member_name, lp.member_id
         FROM loan_payments lp
         JOIN members m ON m.id = lp.member_id
         WHERE lp.status='paid'
         ORDER BY lp.paid_date DESC LIMIT 3)
        UNION ALL
        (SELECT 'জরিমানা পরিশোধ' as type, fp.amount, fp.payment_date as date, m.name as member_name, f.member_id
         FROM fine_payments fp
         JOIN fines f ON fp.fine_id = f.id
         JOIN members m ON m.id = f.member_id
         ORDER BY fp.payment_date DESC LIMIT 3)
        UNION ALL
        (SELECT 'বিনিয়োগ রিটার্ন' as type, ir.return_amount as amount, ir.return_date as date, 'বিনিয়োগ' as member_name, 0 as member_id
         FROM investment_returns ir
         ORDER BY ir.return_date DESC LIMIT 3)
        UNION ALL
        (SELECT 'খরচ' as type, e.amount, e.expense_date as date, e.title as member_name, 0 as member_id
         FROM expenses e
         ORDER BY e.expense_date DESC LIMIT 5)
        ORDER BY date DESC LIMIT $limit
    ");
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recentTransactions[] = $row;
        }
    }
} catch (Exception $e) {}

// ============================================
// 10. সর্বশেষ জরিমানা লেনদেন
// ============================================
$recentFinePayments = [];
try {
    $txCheck = $conn->query("SHOW TABLES LIKE 'fine_payments'");
    if ($txCheck && $txCheck->num_rows > 0) {
        $where = "";
        if ($selectedMonth > 0 && $selectedYear > 0) {
            $where = " WHERE YEAR(fp.payment_date) = $selectedYear AND MONTH(fp.payment_date) = $selectedMonth ";
        } elseif ($selectedYear > 0) {
            $where = " WHERE YEAR(fp.payment_date) = $selectedYear ";
        } elseif ($selectedMonth > 0) {
            $where = " WHERE MONTH(fp.payment_date) = $selectedMonth ";
        }
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
            $where
            ORDER BY fp.payment_date DESC
            LIMIT 10
        ");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $recentFinePayments[] = $row;
            }
        }
    }
} catch (Exception $e) {}

// ============================================
// 11. বকেয়া জরিমানা তালিকা
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
} catch (Exception $e) {}

// ============================================
// 12. সর্বশেষ বিনিয়োগ রিটার্ন
// ============================================
$recentInvestmentReturns = [];
try {
    $tableCheck = $conn->query("SHOW TABLES LIKE 'investment_returns'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        $where = "";
        if ($selectedMonth > 0 && $selectedYear > 0) {
            $where = " WHERE YEAR(ir.return_date) = $selectedYear AND MONTH(ir.return_date) = $selectedMonth ";
        } elseif ($selectedYear > 0) {
            $where = " WHERE YEAR(ir.return_date) = $selectedYear ";
        } elseif ($selectedMonth > 0) {
            $where = " WHERE MONTH(ir.return_date) = $selectedMonth ";
        }
        $result = $conn->query("
            SELECT 
                ir.*,
                i.title as investment_title,
                i.amount as investment_amount
            FROM investment_returns ir
            JOIN investments i ON ir.investment_id = i.id
            $where
            ORDER BY ir.return_date DESC
            LIMIT 5
        ");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $recentInvestmentReturns[] = $row;
            }
        }
    }
} catch (Exception $e) {}

// ============================================
// 13. সর্বশেষ খরচ
// ============================================
$recentExpenses = [];
try {
    $where = "";
    if ($selectedMonth > 0 && $selectedYear > 0) {
        $where = " WHERE YEAR(expense_date) = $selectedYear AND MONTH(expense_date) = $selectedMonth ";
    } elseif ($selectedYear > 0) {
        $where = " WHERE YEAR(expense_date) = $selectedYear ";
    } elseif ($selectedMonth > 0) {
        $where = " WHERE MONTH(expense_date) = $selectedMonth ";
    }
    $result = $conn->query("
        SELECT * FROM expenses 
        $where
        ORDER BY expense_date DESC, created_at DESC 
        LIMIT 10
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recentExpenses[] = $row;
        }
    }
} catch (Exception $e) {}

// ============================================
// Get available years for filter dropdown
// ============================================
$availableYears = [];
try {
    $result = $conn->query("
        SELECT DISTINCT YEAR(deposit_date) as year FROM deposits 
        UNION 
        SELECT DISTINCT YEAR(withdrawal_date) as year FROM withdrawals 
        UNION 
        SELECT DISTINCT YEAR(expense_date) as year FROM expenses 
        UNION 
        SELECT DISTINCT YEAR(return_date) as year FROM investment_returns 
        UNION 
        SELECT DISTINCT YEAR(created_at) as year FROM investments 
        ORDER BY year DESC
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if ($row['year'] > 0) {
                $availableYears[] = $row['year'];
            }
        }
    }
    // If no years found, add current year
    if (empty($availableYears)) {
        $availableYears[] = date('Y');
    }
} catch (Exception $e) {
    $availableYears[] = date('Y');
}

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
        <i data-feather="bar-chart-2" style="width:20px;height:20px"></i> 
        সম্পূর্ণ ড্যাশবোর্ড
        <small class="text-muted">
            <?php 
            if ($selectedMonth > 0 && $selectedYear > 0) {
                echo date('F Y', mktime(0,0,0,$selectedMonth,1,$selectedYear));
            } elseif ($selectedYear > 0) {
                echo $selectedYear;
            } else {
                echo 'সকল সময়';
            }
            ?>
        </small>
    </h4>
    <div>
        <span class="badge bg-primary"><?= date('d M Y') ?></span>
        <span class="badge bg-success ms-1"><?= date('h:i A') ?></span>
        <a href="/admin/investments.php" class="btn btn-warning btn-sm ms-2">
            <i data-feather="briefcase" style="width:14px;height:14px"></i> বিনিয়োগ ব্যবস্থাপনা
        </a>
    </div>
</div>

<!-- ============================================
     FILTER DROPDOWNS - Month & Year
     ============================================ -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card" style="background: #f8f9fa; border: 1px solid #dee2e6;">
            <div class="card-body py-2">
                <form method="GET" action="" class="d-flex flex-wrap align-items-center gap-3">
                    <div>
                        <label class="fw-bold me-2" style="font-size:14px;">📅 ফিল্টার:</label>
                    </div>
                    <div>
                        <select name="month" class="form-select form-select-sm" style="width:150px; display:inline-block;">
                            <option value="0">সব মাস</option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= ($selectedMonth == $m) ? 'selected' : '' ?>>
                                    <?= date('F', mktime(0,0,0,$m,1)) ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div>
                        <select name="year" class="form-select form-select-sm" style="width:130px; display:inline-block;">
                            <option value="0">সব বছর</option>
                            <?php foreach ($availableYears as $year): ?>
                                <option value="<?= $year ?>" <?= ($selectedYear == $year) ? 'selected' : '' ?>>
                                    <?= $year ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i data-feather="filter" style="width:14px;height:14px"></i> ফিল্টার
                        </button>
                        <a href="?month=0&year=0" class="btn btn-secondary btn-sm">
                            <i data-feather="x" style="width:14px;height:14px"></i> রিসেট
                        </a>
                    </div>
                    <?php if ($selectedMonth > 0 && $selectedYear > 0): ?>
                        <div>
                            <span class="badge bg-info text-white">
                                <i data-feather="calendar" style="width:14px;height:14px"></i>
                                <?= date('F Y', mktime(0,0,0,$selectedMonth,1,$selectedYear)) ?>
                            </span>
                        </div>
                    <?php elseif ($selectedYear > 0): ?>
                        <div>
                            <span class="badge bg-info text-white">
                                <i data-feather="calendar" style="width:14px;height:14px"></i>
                                <?= $selectedYear ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Last Updated -->
<div class="alert alert-info mb-3">
    <i data-feather="clock" style="width:14px;height:14px"></i>
    সর্বশেষ আপডেট: <?= date('d M Y, h:i A') ?> | 
    অ্যাডমিন: <?= clean($admin['name'] ?? '') ?>
</div>

<!-- ============================================
     সারাংশ কার্ড - সদস্য
     ============================================ -->
<div class="row g-2 g-md-3 mb-3">
    <div class="col-12">
        <h5 class="text-primary mb-2"><i data-feather="users" style="width:18px;height:18px"></i> সদস্য পরিসংখ্যান</h5>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card bg-primary-grad text-center">
            <div class="stat-icon"><i data-feather="users"></i></div>
            <div class="stat-value"><?= number_format($totalMembers) ?> জন</div>
            <div class="stat-label"><i class="fa-regular fa-circle-check fa-beat" style="color:#54A868; font-size:15px; padding:4px; margin-right:3px; vertical-align:middle;"></i> সক্রিয় সদস্য</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card bg-danger-grad text-center">
            <div class="stat-icon"><i data-feather="user-x"></i></div>
            <div class="stat-value"><?= number_format($inactiveMembers) ?> জন</div>
            <div class="stat-label"><i class="fa-regular fa-circle-xmark fa-beat" style="color:#DC3545; font-size:15px; padding:4px; margin-right:3px; vertical-align:middle;"></i> নিষ্ক্রিয় সদস্য</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card bg-teal-grad text-center">
            <div class="stat-icon"><i data-feather="user-plus"></i></div>
            <div class="stat-value"><?= number_format($totalMembersEver) ?> জন</div>
            <div class="stat-label">📊 মোট সদস্য (সব)</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card bg-warning-grad text-center">
            <div class="stat-icon"><i data-feather="percent"></i></div>
            <div class="stat-value"><?= $totalMembersEver > 0 ? round(($activeMembers / $totalMembersEver) * 100, 1) : 0 ?>%</div>
            <div class="stat-label">📈 সক্রিয়তার হার</div>
        </div>
    </div>
</div>

<!-- ============================================
     সারাংশ কার্ড - সঞ্চয়, খরচ ও বিনিয়োগ
     ============================================ -->
<div class="row g-2 g-md-3 mb-3">
    <div class="col-12">
        <h5 class="text-success mb-2"><i data-feather="credit-card" style="width:18px;height:18px"></i> সঞ্চয়, খরচ ও বিনিয়োগ</h5>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card bg-success-grad text-center">
            <div class="stat-icon"><i data-feather="arrow-down-circle"></i></div>
            <div class="stat-value">৳<?= number_format($totalDeposits + $totalInvestmentReturns, 0) ?></div>
            <div class="stat-label">📥 মোট সদস্য জমা</div>
            <small class="text-muted">সঞ্চয় + জরিমানা + ইনভেস্টমেন্ট রিটার্ন</small>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card" style="background: linear-gradient(135deg, #E8F5E9, #C8E6C9);">
            <div class="stat-icon"><i data-feather="check-circle" style="color:#27AE60;"></i></div>
            <div class="stat-value">৳<?= number_format($totalRegularDeposits, 0) ?></div>
            <div class="stat-label">📥 সঞ্চয় জমা</div>
            <small class="text-muted">শুধু সঞ্চয় জমা</small>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card" style="background: linear-gradient(135deg, #FFF3E0, #FFE0B2);">
            <div class="stat-icon"><i data-feather="alert-triangle" style="color:#E67E22;"></i></div>
            <div class="stat-value">৳<?= number_format($totalFineDeposits, 0) ?></div>
            <div class="stat-label">⚖️ জরিমানা</div>
            <small class="text-muted">পরিশোধিত জরিমানা</small>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card bg-danger-grad text-center">
            <div class="stat-icon"><i data-feather="arrow-up-circle"></i></div>
            <div class="stat-value">৳<?= number_format($totalWithdrawals, 0) ?></div>
            <div class="stat-label">📤 মোট উত্তোলন</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card" style="background: linear-gradient(135deg, #FDEDEC, #FADBD8);">
            <div class="stat-icon"><i data-feather="shopping-bag" style="color:#E74C3C;"></i></div>
            <div class="stat-value">৳<?= number_format($totalExpenses, 0) ?></div>
            <div class="stat-label">📊 মোট খরচ</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card bg-primary-grad text-center">
            <div class="stat-icon"><i data-feather="dollar-sign"></i></div>
            <div class="stat-value">৳<?= number_format($netSavings, 0) ?></div>
            <div class="stat-label">💰 নেট সঞ্চয়</div>
            <small class="text-muted">বর্তমান ব্যালেন্স</small>
        </div>
    </div>
</div>

<!-- ============================================
     সারাংশ কার্ড - ফাইনস (জরিমানা)
     ============================================ -->
<div class="row g-2 g-md-3 mb-3">
    <div class="col-12">
        <h5 class="text-purple mb-2"><i data-feather="alert-triangle" style="width:18px;height:18px"></i> জরিমানা পরিসংখ্যান</h5>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card" style="background: linear-gradient(135deg, #F5EEF8, #EBDEF0);">
            <div class="stat-icon"><i data-feather="dollar-sign" style="color:#6C3483;"></i></div>
            <div class="stat-value">৳<?= number_format($totalFineAmount, 0) ?></div>
            <div class="stat-label">💰 মোট জরিমানা</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card" style="background: linear-gradient(135deg, #E8F5E9, #C8E6C9);">
            <div class="stat-icon"><i data-feather="check-circle" style="color:#27AE60;"></i></div>
            <div class="stat-value">৳<?= number_format($totalFinePaid, 0) ?></div>
            <div class="stat-label"><i class="fa-regular fa-circle-check fa-beat" style="color:#54A868; font-size:15px; padding:4px; margin-right:3px; vertical-align:middle;"></i> পরিশোধিত</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card" style="background: linear-gradient(135deg, #FFF3E0, #FFE0B2);">
            <div class="stat-icon"><i data-feather="clock" style="color:#E67E22;"></i></div>
            <div class="stat-value">৳<?= number_format($totalFinePending, 0) ?></div>
            <div class="stat-label">⏳ বকেয়া</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card" style="background: linear-gradient(135deg, #E3F2FD, #BBDEFB);">
            <div class="stat-icon"><i data-feather="slash" style="color:#2980B9;"></i></div>
            <div class="stat-value">৳<?= number_format($totalFineWaived, 0) ?></div>
            <div class="stat-label">⛔ মওকুফ</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card" style="background: linear-gradient(135deg, #FCE4EC, #F8BBD0);">
            <div class="stat-icon"><i data-feather="alert-circle" style="color:#E74C3C;"></i></div>
            <div class="stat-value"><?= number_format($overdueFines) ?></div>
            <div class="stat-label">🔴 মেয়াদোত্তীর্ণ</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card" style="background: linear-gradient(135deg, #E8EAF6, #C5CAE9);">
            <div class="stat-icon"><i data-feather="list" style="color:#3F51B5;"></i></div>
            <div class="stat-value"><?= number_format($totalFineCount) ?></div>
            <div class="stat-label">📋 মোট জরিমানা</div>
        </div>
    </div>
</div>

<!-- ফাইনস স্ট্যাটাস ব্রেকডাউন -->
<div class="row g-2 g-md-3 mb-3">
    <div class="col-12">
        <div class="card fine-status-card">
            <div class="card-body">
                <h6 class="mb-3" style="color: #6C3483; font-weight: 600;">
                    <i data-feather="bar-chart-2" style="width:18px;height:18px"></i> 
                    জরিমানা অবস্থা
                </h6>
                <div class="row g-2">
                    <div class="col-6 col-md-3">
                        <span class="fine-status-item pending">
                            ⏳ পেন্ডিং: <span class="fine-status-count"><?= $pendingFineCount ?></span>
                        </span>
                    </div>
                    <div class="col-6 col-md-3">
                        <span class="fine-status-item partial">
                            🔄 আংশিক: <span class="fine-status-count"><?= $partiallyPaidFineCount ?></span>
                        </span>
                    </div>
                    <div class="col-6 col-md-3">
                        <span class="fine-status-item paid">
                            ✅ পরিশোধিত: <span class="fine-status-count"><?= $paidFineCount ?></span>
                        </span>
                    </div>
                    <div class="col-6 col-md-3">
                        <span class="fine-status-item waived">
                            ⛔ মওকুফ: <span class="fine-status-count"><?= $waivedFineCount ?></span>
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
     সারাংশ কার্ড - ঋণ
     ============================================ -->
<div class="row g-2 g-md-3 mb-3">
    <div class="col-12">
        <h5 class="text-warning mb-2"><i data-feather="briefcase" style="width:18px;height:18px"></i> ঋণ পরিসংখ্যান</h5>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="background: linear-gradient(135deg, #FEF9E7, #FDEBD0);">
            <div class="stat-icon"><i data-feather="plus-circle" style="color:#D35400;"></i></div>
            <div class="stat-value">৳<?= number_format($totalLoansGiven, 0) ?></div>
            <div class="stat-label">📤 মোট ঋণ বিতরণ</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="background: linear-gradient(135deg, #E8F8F5, #D1F2EB);">
            <div class="stat-icon"><i data-feather="check-circle" style="color:#1ABC9C;"></i></div>
            <div class="stat-value">৳<?= number_format($totalLoansReceived, 0) ?></div>
            <div class="stat-label">📥 মোট ঋণ আদায়</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="background: linear-gradient(135deg, #EAF2F8, #D6EAF8);">
            <div class="stat-icon"><i data-feather="dollar-sign" style="color:#2E86C1;"></i></div>
            <div class="stat-value">৳<?= number_format($totalLoanInterest, 0) ?></div>
            <div class="stat-label">💰 মোট সুদ আদায়</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="background: linear-gradient(135deg, #FDEDEC, #FADBD8);">
            <div class="stat-icon"><i data-feather="activity" style="color:#E74C3C;"></i></div>
            <div class="stat-value"><?= number_format($activeLoans) ?> টি</div>
            <div class="stat-label">🟢 সক্রিয় ঋণ</div>
        </div>
    </div>
</div>

<!-- ============================================
     সারাংশ কার্ড - বিনিয়োগ
     ============================================ -->
<div class="row g-2 g-md-3 mb-3">
    <div class="col-12">
        <h5 class="text-purple mb-2"><i data-feather="trending-up" style="width:18px;height:18px"></i> বিনিয়োগ পরিসংখ্যান</h5>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card bg-purple-grad text-center">
            <div class="stat-icon"><i data-feather="briefcase"></i></div>
            <div class="stat-value">৳<?= number_format($totalInvestments, 0) ?></div>
            <div class="stat-label">💼 মোট বিনিয়োগ</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card bg-success-grad text-center">
            <div class="stat-icon"><i data-feather="dollar-sign"></i></div>
            <div class="stat-value">৳<?= number_format($totalInvestmentReturns, 0) ?></div>
            <div class="stat-label">💰 মোট রিটার্ন</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card bg-primary-grad text-center">
            <div class="stat-icon"><i data-feather="percent"></i></div>
            <div class="stat-value"><?= $totalInvestments > 0 ? round(($totalInvestmentReturns / $totalInvestments) * 100, 1) : 0 ?>%</div>
            <div class="stat-label">📈 রিটার্ন হার</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card" style="background: linear-gradient(135deg, #FFF3E0, #FFE0B2);">
            <div class="stat-icon"><i data-feather="play-circle" style="color:#E67E22;"></i></div>
            <div class="stat-value"><?= number_format($activeInvestments) ?></div>
            <div class="stat-label">🟢 চলমান</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card" style="background: linear-gradient(135deg, #E8F5E9, #C8E6C9);">
            <div class="stat-icon"><i data-feather="check-circle" style="color:#27AE60;"></i></div>
            <div class="stat-value"><?= number_format($completedInvestments) ?></div>
            <div class="stat-label">✅ সম্পন্ন</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card" style="background: linear-gradient(135deg, #E3F2FD, #BBDEFB);">
            <div class="stat-icon"><i data-feather="credit-card" style="color:#1976D2;"></i></div>
            <div class="stat-value"><?= number_format($paidInvestments) ?></div>
            <div class="stat-label">💳 পেইড</div>
        </div>
    </div>
</div>

<!-- ============================================
     সারাংশ কার্ড - আয় ও খরচ
     ============================================ -->
<div class="row g-2 g-md-3 mb-3">
    <div class="col-12">
        <h5 class="text-info mb-2"><i data-feather="pie-chart" style="width:18px;height:18px"></i> আয়-ব্যয় পরিসংখ্যান</h5>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card bg-success-grad text-center">
            <div class="stat-icon"><i data-feather="trending-up"></i></div>
            <div class="stat-value">৳<?= number_format($totalIncome, 0) ?></div>
            <div class="stat-label">📈 মোট আয়</div>
            <small class="text-muted">চাঁদা + সুদ + রিটার্ন + ফাইনস</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card bg-danger-grad text-center">
            <div class="stat-icon"><i data-feather="shopping-bag"></i></div>
            <div class="stat-value">৳<?= number_format($totalExpenses, 0) ?></div>
            <div class="stat-label">📊 মোট খরচ</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card bg-warning-grad text-center">
            <div class="stat-icon"><i data-feather="percent"></i></div>
            <div class="stat-value"><?= $totalIncome > 0 ? round(($totalProfit / $totalIncome) * 100, 1) : 0 ?>%</div>
            <div class="stat-label">📈 লাভের হার</div>
        </div>
    </div>
</div>

<!-- ============================================
     সারাংশ কার্ড - লেনদেনের সংখ্যা
     ============================================ -->
<div class="row g-2 g-md-3 mb-3">
    <div class="col-12">
        <h5 class="text-secondary mb-2"><i data-feather="list" style="width:18px;height:18px"></i> লেনদেন পরিসংখ্যান</h5>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card" style="background: linear-gradient(135deg, #EBF5FB, #D6EAF8);">
            <div class="stat-icon"><i data-feather="file-text" style="color:#2E86C1;"></i></div>
            <div class="stat-value"><?= number_format($totalDepositCount) ?></div>
            <div class="stat-label">📥 জমা</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card" style="background: linear-gradient(135deg, #FDEDEC, #FADBD8);">
            <div class="stat-icon"><i data-feather="file-text" style="color:#E74C3C;"></i></div>
            <div class="stat-value"><?= number_format($totalWithdrawalCount) ?></div>
            <div class="stat-label">📤 উত্তোলন</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card" style="background: linear-gradient(135deg, #FEF9E7, #FDEBD0);">
            <div class="stat-icon"><i data-feather="file-text" style="color:#D35400;"></i></div>
            <div class="stat-value"><?= number_format($totalLoanPaymentCount) ?></div>
            <div class="stat-label">💰 ঋণ আদায়</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card" style="background: linear-gradient(135deg, #E8F8F5, #D1F2EB);">
            <div class="stat-icon"><i data-feather="file-text" style="color:#1ABC9C;"></i></div>
            <div class="stat-value"><?= number_format($totalExpenseCount) ?></div>
            <div class="stat-label">📊 খরচ</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card" style="background: linear-gradient(135deg, #F5EEF8, #EBDEF0);">
            <div class="stat-icon"><i data-feather="file-text" style="color:#6C3483;"></i></div>
            <div class="stat-value"><?= number_format($fineTransactionsCount) ?></div>
            <div class="stat-label">⚖️ জরিমানা পরিশোধ</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card" style="background: linear-gradient(135deg, #E8F5E9, #C8E6C9);">
            <div class="stat-icon"><i data-feather="file-text" style="color:#27AE60;"></i></div>
            <div class="stat-value"><?= number_format($totalDepositCount + $totalWithdrawalCount + $totalLoanPaymentCount + $totalExpenseCount + $fineTransactionsCount) ?></div>
            <div class="stat-label">📋 মোট লেনদেন</div>
        </div>
    </div>
</div>

<!-- ============================================
     সর্বশেষ খরচ তালিকা
     ============================================ -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card" style="border-left: 4px solid #E74C3C;">
            <div class="card-header" style="background:#FDEDEC;">
                <span class="card-title">
                    <i data-feather="shopping-bag" style="color:#E74C3C;"></i> 
                    সর্বশেষ খরচ (<?= count($recentExpenses) ?>)
                </span>
                <a href="/admin/expenses.php" class="btn btn-outline-danger btn-sm">সব দেখুন</a>
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
</div>

<!-- ============================================
     বকেয়া জরিমানা তালিকা
     ============================================ -->
<?php if (!empty($pendingFineList)): ?>
<div class="row mb-3">
    <div class="col-12">
        <div class="card" style="border-left: 4px solid #E67E22;">
            <div class="card-header" style="background:#FEF9E7;">
                <span class="card-title">
                    <i data-feather="alert-triangle" style="color:#E67E22;"></i> 
                    বকেয়া জরিমানা (<?= count($pendingFineList) ?>)
                </span>
                <a href="/admin/fines.php?status=pending" class="btn btn-outline-warning btn-sm">সব দেখুন</a>
            </div>
            <div class="list-group list-group-flush">
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
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ============================================
     সর্বশেষ ফাইনস লেনদেন
     ============================================ -->
<?php if (!empty($recentFinePayments)): ?>
<div class="row mb-3">
    <div class="col-12">
        <div class="card" style="border-left: 4px solid #8E44AD;">
            <div class="card-header" style="background:#F4ECF7;">
                <span class="card-title">
                    <i data-feather="credit-card" style="color:#8E44AD;"></i> 
                    সর্বশেষ জরিমানা পরিশোধ (<?= count($recentFinePayments) ?>)
                </span>
                <a href="/admin/fine_payments_list.php" class="btn btn-outline-purple btn-sm">সব দেখুন</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>সদস্য</th>
                            <th>পরিমাণ</th>
                            <th>পদ্ধতি</th>
                            <th>তারিখ</th>
                            <th>অবস্থা</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentFinePayments as $fp): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <img src="<?= getMemberPhoto(['name' => $fp['member_name'], 'photo' => $fp['member_photo']]) ?>" 
                                         alt="Photo" style="width:28px;height:28px;border-radius:50%;object-fit:cover;border:1px solid #ddd;">
                                    <?= clean($fp['member_name']) ?>
                                </div>
                            </td>
                            <td class="amount-negative">-৳<?= number_format($fp['amount'], 0) ?></td>
                            <td>
                                <span class="badge bg-secondary">
                                    <?= ucfirst($fp['payment_method']) ?>
                                </span>
                            </td>
                            <td><?= date('d M Y', strtotime($fp['payment_date'])) ?></td>
                            <td>
                                <span class="badge <?= $fp['fine_status'] == 'paid' ? 'bg-success' : ($fp['fine_status'] == 'partially_paid' ? 'bg-warning' : 'bg-secondary') ?>">
                                    <?= ucfirst(str_replace('_', ' ', $fp['fine_status'])) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ============================================
     সর্বশেষ বিনিয়োগ রিটার্ন
     ============================================ -->
<?php if (!empty($recentInvestmentReturns)): ?>
<div class="row mb-3">
    <div class="col-12">
        <div class="card" style="border-left: 4px solid #F39C12;">
            <div class="card-header" style="background:#FEF9E7;">
                <span class="card-title">
                    <i data-feather="trending-up" style="color:#F39C12;"></i> 
                    সর্বশেষ বিনিয়োগ রিটার্ন (<?= count($recentInvestmentReturns) ?>)
                </span>
                <a href="/admin/investments.php" class="btn btn-outline-warning btn-sm">সব দেখুন</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>বিনিয়োগ</th>
                            <th>রিটার্ন পরিমাণ</th>
                            <th>তারিখ</th>
                            <th>মন্তব্য</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentInvestmentReturns as $ir): ?>
                        <tr>
                            <td>
                                <a href="/admin/investments.php?action=edit&id=<?= $ir['investment_id'] ?>">
                                    <?= clean($ir['investment_title']) ?>
                                </a>
                                <br><small style="font-size:10px;color:#888;">
                                    মূল বিনিয়োগ: ৳<?= number_format($ir['investment_amount'], 0) ?>
                                </small>
                            </td>
                            <td class="amount-positive fw-bold">+৳<?= number_format($ir['return_amount'], 0) ?></td>
                            <td><?= date('d M Y', strtotime($ir['return_date'])) ?></td>
                            <td style="font-size:12px;"><?= clean($ir['note'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ============================================
     সর্বশেষ লেনদেন (সব)
     ============================================ -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <span class="card-title">
                    <i data-feather="clock" style="width:16px;height:16px"></i> 
                    সর্বশেষ লেনদেন (<?= count($recentTransactions) ?>)
                </span>
                <span class="text-muted" style="font-size:12px;">
                    <?= ($selectedMonth > 0 && $selectedYear > 0) ? date('F Y', mktime(0,0,0,$selectedMonth,1,$selectedYear)) : (($selectedYear > 0) ? $selectedYear : 'সব') ?>
                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>লেনদেনের ধরন</th>
                            <th>সদস্য/বিবরণ</th>
                            <th>পরিমাণ</th>
                            <th>তারিখ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentTransactions)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-3">কোনো লেনদেন নেই</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($recentTransactions as $index => $t): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td>
                                <span class="badge <?= 
                                    $t['type'] == 'জমা' ? 'bg-success' : 
                                    ($t['type'] == 'উত্তোলন' ? 'bg-danger' : 
                                    ($t['type'] == 'জরিমানা পরিশোধ' ? 'bg-purple' : 
                                    ($t['type'] == 'বিনিয়োগ রিটার্ন' ? 'bg-warning text-dark' : 
                                    ($t['type'] == 'খরচ' ? 'bg-secondary' : 'bg-info')))) 
                                ?>">
                                    <?= $t['type'] ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($t['member_id'] > 0 && $t['type'] != 'খরচ'): ?>
                                <a href="/admin/member_profile.php?id=<?= $t['member_id'] ?>">
                                    <?= clean($t['member_name'] ?? '') ?>
                                </a>
                                <?php else: ?>
                                <span class="text-muted"><?= clean($t['member_name'] ?? '') ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-bold <?= 
                                $t['type'] == 'জমা' ? 'amount-positive' : 
                                ($t['type'] == 'উত্তোলন' ? 'amount-negative' : 
                                ($t['type'] == 'জরিমানা পরিশোধ' ? 'amount-negative' : 
                                ($t['type'] == 'বিনিয়োগ রিটার্ন' ? 'amount-positive' : 
                                ($t['type'] == 'খরচ' ? 'amount-negative' : 'text-warning')))) 
                            ?>">
                                <?php if ($t['type'] == 'উত্তোলন' || $t['type'] == 'জরিমানা পরিশোধ' || $t['type'] == 'খরচ'): ?>
                                -৳<?= number_format($t['amount'] ?? 0, 0) ?>
                                <?php else: ?>
                                +৳<?= number_format($t['amount'] ?? 0, 0) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= formatDate($t['date'] ?? date('Y-m-d')) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
     Quick Actions
     ============================================ -->
<div class="row mt-3">
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
                    <a href="/admin/fine_payments_list.php" class="btn btn-purple btn-sm">
                        <i data-feather="credit-card" style="width:14px;height:14px"></i> জরিমানা পরিশোধের তালিকা
                    </a>
                    <a href="/admin/investments.php" class="btn btn-warning btn-sm text-dark">
                        <i data-feather="briefcase" style="width:14px;height:14px"></i> বিনিয়োগ ব্যবস্থাপনা
                    </a>
                    <a href="/admin/expenses.php" class="btn btn-danger btn-sm">
                        <i data-feather="shopping-bag" style="width:14px;height:14px"></i> খরচ ব্যবস্থাপনা
                    </a>
                    <a href="/admin/monthly_dues.php" class="btn btn-info btn-sm text-white">
                        <i data-feather="calendar" style="width:14px;height:14px"></i> সঞ্চয় সংগ্রহ
                    </a>
                    <a href="/admin/reports.php" class="btn btn-dark btn-sm">
                        <i data-feather="file-text" style="width:14px;height:14px"></i> রিপোর্ট
                    </a>
                    <a href="/admin/profit.php" class="btn btn-purple btn-sm">
                        <i data-feather="pie-chart" style="width:14px;height:14px"></i> লাভ-ক্ষতি
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

</div><!-- /container-fluid -->

<style>
/* ============================================
   STAT CARDS
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

.bg-purple {
    background-color: #6C3483 !important;
    color: white !important;
}

.text-purple { color: #6C3483; }

/* ============================================
   FINE STATUS STYLES
   ============================================ */
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

/* Progress Bar */
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

/* Badge Styles */
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

.list-group-item {
    border-left: none;
    border-right: none;
    border-color: #f0f0f0;
}
.list-group-item:first-child {
    border-top: none;
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
    .fine-status-item {
        padding: 6px 12px;
        font-size: 12px;
        margin: 2px 4px 2px 0;
    }
}

/* Filter form styles */
.form-select-sm {
    font-size: 13px;
    padding: 4px 28px 4px 10px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
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