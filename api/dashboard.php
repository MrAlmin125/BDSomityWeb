<?php
// =====================================================
// api/dashboard.php - ড্যাশবোর্ড ডেটা
// GET (Header: Authorization: Bearer <token>)
// =====================================================
require_once __DIR__ . '/config.php';

$admin = requireToken();

try {
    $conn = conn();
    $currentMonth = date('m');
    $currentYear = date('Y');
    $prevMonth = $currentMonth - 1;
    $prevYear = $currentYear;
    if ($prevMonth == 0) { $prevMonth = 12; $prevYear = $currentYear - 1; }

    // ১. মোট সক্রিয় সদস্য
    $totalMembers = (int)(queryOne("SELECT COUNT(*) as c FROM members WHERE status='active'")['c'] ?? 0);

    // ২. মোট regular সঞ্চয় জমা
    $regularDeposits = (float)(queryOne(
        "SELECT COALESCE(SUM(amount),0) as t FROM deposits 
         WHERE deposit_type IN ('regular','special','share','fd')"
    )['t'] ?? 0);

    // ৩. মোট পরিশোধিত জরিমানা
    $totalFinesPaid = (float)(queryOne(
        "SELECT COALESCE(SUM(paid_amount),0) as t FROM fines WHERE status='paid'"
    )['t'] ?? 0);

    // ৪. মোট বিনিয়োগ রিটার্ন
    $totalInvestmentReturns = (float)(queryOne(
        "SELECT COALESCE(SUM(return_amount),0) as t FROM investment_returns"
    )['t'] ?? 0);

    // ৫. মোট বিনিয়োগ
    $totalInvestmentAmount = (float)(queryOne(
        "SELECT COALESCE(SUM(amount),0) as t FROM investments"
    )['t'] ?? 0);

    // ৬. মোট উত্তোলন
    $totalWithdrawals = (float)(queryOne(
        "SELECT COALESCE(SUM(amount),0) as t FROM withdrawals WHERE status='completed'"
    )['t'] ?? 0);

    // ৭. মোট খরচ
    $totalExpenses = (float)(queryOne(
        "SELECT COALESCE(SUM(amount),0) as t FROM expenses"
    )['t'] ?? 0);

    // ৮. নেট সঞ্চয় (ব্যালেন্স)
    $netSavings = ($regularDeposits + $totalFinesPaid + $totalInvestmentReturns)
                - ($totalWithdrawals + $totalExpenses + $totalInvestmentAmount);

    // ৯. বকেয়া ফাইন
    $pendingFines = (float)(queryOne(
        "SELECT COALESCE(SUM(fine_amount - paid_amount - waived_amount),0) as t 
         FROM fines WHERE status IN ('pending','partially_paid')"
    )['t'] ?? 0);

    $pendingFineCount = (int)(queryOne(
        "SELECT COUNT(*) as c FROM fines WHERE status IN ('pending','partially_paid')"
    )['c'] ?? 0);

    // ১০. গত মাসে কতজন জমা দিয়েছে
    $depositedThisMonth = (int)(queryOne(
        "SELECT COUNT(DISTINCT member_id) as c FROM deposits 
         WHERE MONTH(deposit_date)=? AND YEAR(deposit_date)=?",
        'ii', [$prevMonth, $prevYear]
    )['c'] ?? 0);

    $totalThisMonthDeposit = (float)(queryOne(
        "SELECT COALESCE(SUM(amount),0) as t FROM deposits 
         WHERE MONTH(deposit_date)=? AND YEAR(deposit_date)=?",
        'ii', [$prevMonth, $prevYear]
    )['t'] ?? 0);

    $notDepositedThisMonth = $totalMembers - $depositedThisMonth;

    // ১১. সক্রিয় ঋণ
    $activeLoans = (int)(queryOne(
        "SELECT COUNT(*) as c FROM loans WHERE status='active'"
    )['c'] ?? 0);

    $activeLoanAmount = (float)(queryOne(
        "SELECT COALESCE(SUM(outstanding_balance),0) as t FROM loans WHERE status='active'"
    )['t'] ?? 0);

    // ১২. সাম্প্রতিক ৫টি জমা
    $recentDeposits = queryAll(
        "SELECT d.id, d.amount, d.deposit_date, d.deposit_type,
                m.name as member_name, m.member_id as member_code
         FROM deposits d
         JOIN members m ON m.id = d.member_id
         ORDER BY d.deposit_date DESC, d.id DESC
         LIMIT 5"
    );

    // ১৩. সাম্প্রতিক ৫টি বকেয়া ফাইন
    $recentPendingFines = queryAll(
        "SELECT f.id, f.fine_amount, f.paid_amount, f.waived_amount,
                f.due_date, f.status,
                m.name as member_name, m.member_id as member_code,
                (f.fine_amount - f.paid_amount - f.waived_amount) as remaining
         FROM fines f
         JOIN members m ON m.id = f.member_id
         WHERE f.status IN ('pending','partially_paid')
         ORDER BY f.due_date ASC
         LIMIT 5"
    );

    // ১৪. সোমিটি তথ্য
    $somityName = getSetting('somity_name', 'সোমিটি');
    $currencySymbol = getSetting('currency_symbol', '৳');

    // রেসপন্স
    jsonResponse(true, [
        'admin' => formatAdmin($admin),
        'somity' => [
            'name' => $somityName,
            'currency' => $currencySymbol,
        ],
        'stats' => [
            'total_members' => $totalMembers,
            'deposited_this_month' => $depositedThisMonth,
            'not_deposited_this_month' => $notDepositedThisMonth,
            'total_this_month_deposit' => $totalThisMonthDeposit,
            'regular_deposits' => $regularDeposits,
            'total_fines_paid' => $totalFinesPaid,
            'total_withdrawals' => $totalWithdrawals,
            'total_expenses' => $totalExpenses,
            'total_investment_amount' => $totalInvestmentAmount,
            'total_investment_returns' => $totalInvestmentReturns,
            'net_savings' => $netSavings,
            'pending_fines' => $pendingFines,
            'pending_fine_count' => $pendingFineCount,
            'active_loans' => $activeLoans,
            'active_loan_amount' => $activeLoanAmount,
            'previous_month' => $prevMonth,
            'previous_year' => $prevYear,
        ],
        'recent_deposits' => $recentDeposits,
        'recent_pending_fines' => $recentPendingFines,
    ], '');
    
} catch (Exception $e) {
    error_log('API Dashboard Error: ' . $e->getMessage());
    jsonError('ডেটা লোড করতে ব্যর্থ হয়েছে।', 500);
}