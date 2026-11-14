<?php
// admin/api_dues.php — AJAX endpoint for unpaid dues by member
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
if (!isAdminLoggedIn()) { http_response_code(403); echo '[]'; exit; }

header('Content-Type: application/json');

$memberId = (int)($_GET['member_id'] ?? 0);
if (!$memberId) { echo '[]'; exit; }

$dues = queryAll(
    "SELECT id, due_year, due_month, amount, paid_amount,
            CONCAT(due_year,'-',LPAD(due_month,2,'0')) AS ym
     FROM monthly_dues
     WHERE member_id=? AND status != 'paid'
     ORDER BY due_year ASC, due_month ASC
     LIMIT 24",
    'i', [$memberId]
);

$months = ['জানু','ফেব্রু','মার্চ','এপ্রিল','মে','জুন','জুলাই','আগস্ট','সেপ্টে','অক্টো','নভে','ডিসে'];

$result = array_map(function($d) use ($months) {
    return [
        'id'          => $d['id'],
        'amount'      => $d['amount'],
        'paid_amount' => $d['paid_amount'],
        'month_label' => $months[(int)$d['due_month']-1] . ' ' . $d['due_year'],
    ];
}, $dues);

echo json_encode($result);
?>