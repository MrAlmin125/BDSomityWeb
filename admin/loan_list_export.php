<?php
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();

if (!isAdminLoggedIn()) {
    header('Location: /admin/login.php');
    exit;
}

$conn = conn();
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';

$query = "SELECT l.*, m.name AS member_name, m.member_id AS member_code, m.phone AS member_phone 
          FROM loans l 
          LEFT JOIN members m ON m.id = l.member_id 
          WHERE 1=1";

if (!empty($search)) {
    $search = $conn->real_escape_string($search);
    $query .= " AND (m.name LIKE '%$search%' OR m.member_id LIKE '%$search%' OR l.loan_no LIKE '%$search%')";
}

if (!empty($statusFilter)) {
    $statusFilter = $conn->real_escape_string($statusFilter);
    $query .= " AND l.status = '$statusFilter'";
}

$query .= " ORDER BY l.created_at DESC";
$result = $conn->query($query);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="loan_list_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Loan No', 'Member', 'Member ID', 'Phone', 'Amount', 'Paid', 'Outstanding', 'Status', 'Date']);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['loan_no'],
        $row['member_name'],
        $row['member_code'],
        $row['member_phone'],
        $row['principal_amount'],
        $row['total_paid'],
        $row['principal_amount'] - $row['total_paid'],
        $row['status'],
        $row['created_at']
    ]);
}

fclose($output);
exit;
?>