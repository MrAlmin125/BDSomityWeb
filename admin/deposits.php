<?php
// =====================================================
// admin/deposits.php - Deposit Management 
// WITH FINE INTEGRATION - AUTO PAY FINES WITH DEPOSIT
// =====================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
requireAdmin();

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$member_id = (int)($_GET['member_id'] ?? 0);
$pageTitle = 'সঞ্চয় ব্যবস্থাপনা';

$conn = conn();

// ============================================
// HELPER FUNCTIONS
// ============================================
function getAvatarColor($name) {
    $colors = ['#1B4F72', '#2E86C1', '#117A65', '#C0392B', '#8E44AD', '#D35400', '#27AE60', '#2980B9', '#E67E22', '#16A085'];
    $hash = 0;
    for ($i = 0; $i < strlen($name); $i++) {
        $hash = ($hash * 31 + ord($name[$i])) & 0xFFFFFFFF;
    }
    return $colors[abs($hash) % count($colors)];
}

function generateAvatar($name, $size = 40) {
    $firstLetter = mb_substr($name, 0, 1, 'UTF-8');
    $color = getAvatarColor($name);
    
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 ' . $size . ' ' . $size . '">';
    $svg .= '<rect width="' . $size . '" height="' . $size . '" rx="' . ($size/2) . '" fill="' . $color . '"/>';
    $fontSize = $size * 0.45;
    $yPos = $size * 0.66;
    $svg .= '<text x="' . ($size/2) . '" y="' . $yPos . '" font-family="Arial, sans-serif" font-size="' . $fontSize . '" font-weight="bold" fill="white" text-anchor="middle" dominant-baseline="central">' . htmlspecialchars($firstLetter) . '</text>';
    $svg .= '</svg>';
    
    return 'data:image/svg+xml;charset=utf-8,' . rawurlencode($svg);
}

function getMemberPhoto($member) {
    if (!empty($member['photo']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $member['photo'])) {
        return $member['photo'];
    }
    return generateAvatar($member['name'], 40);
}

// ============================================
// FUNCTION: GET TOTAL BALANCE
// ============================================
function getTotalBalance() {
    global $conn;
    
    $totalDeposits = queryOne("SELECT COALESCE(SUM(amount),0) as total FROM deposits WHERE deposit_type IN ('regular', 'special', 'share', 'fd')")['total'] ?? 0;
    $totalFines = queryOne("SELECT COALESCE(SUM(amount),0) as total FROM deposits WHERE deposit_type = 'fine'")['total'] ?? 0;
    
    return $totalDeposits + $totalFines;
}

// ============================================
// FUNCTION: GET MEMBER PENDING FINES
// ============================================
function getMemberPendingFines($memberId) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT 
            id,
            fine_amount,
            paid_amount,
            waived_amount,
            (fine_amount - paid_amount - waived_amount) as remaining,
            month_year,
            due_date,
            status
        FROM fines 
        WHERE member_id = ? 
        AND status IN ('pending', 'partially_paid')
        ORDER BY due_date ASC
    ");
    $stmt->bind_param('i', $memberId);
    $stmt->execute();
    $result = $stmt->get_result();
    $fines = $result->fetch_all(MYSQLI_ASSOC);
    
    $totalPending = 0;
    $fineIds = [];
    $totalFineAmount = 0;
    $totalPaidAmount = 0;
    
    foreach ($fines as $fine) {
        $remaining = $fine['remaining'];
        if ($remaining > 0) {
            $totalPending += $remaining;
            $fineIds[] = $fine['id'];
            $totalFineAmount += $fine['fine_amount'];
            $totalPaidAmount += $fine['paid_amount'];
        }
    }
    
    return [
        'fines' => $fines,
        'total_pending' => $totalPending,
        'fine_ids' => $fineIds,
        'total_fine_amount' => $totalFineAmount,
        'total_paid_amount' => $totalPaidAmount,
        'count' => count($fineIds)
    ];
}

// ============================================
// FUNCTION: PROCESS FINE PAYMENT WITH DEPOSIT
// ============================================
function processFinePaymentWithDeposit($memberId, $depositAmount, $depositId, $paymentDate, $paymentMethod, $transactionNo) {
    global $conn;
    
    $pendingFines = getMemberPendingFines($memberId);
    
    if ($pendingFines['total_pending'] <= 0) {
        return ['success' => true, 'message' => 'কোনো বকেয়া ফাইন নেই', 'paid' => 0];
    }
    
    $remainingFine = $pendingFines['total_pending'];
    $amountToPay = min($depositAmount, $remainingFine);
    
    if ($amountToPay <= 0) {
        return ['success' => true, 'message' => 'ফাইন পরিশোধের জন্য পর্যাপ্ত পরিমাণ নেই', 'paid' => 0];
    }
    
    $paidCount = 0;
    $finePaymentIds = [];
    $totalPaid = 0;
    
    foreach ($pendingFines['fines'] as $fine) {
        if ($amountToPay <= 0) break;
        
        $remaining = $fine['remaining'];
        if ($remaining <= 0) continue;
        
        $payAmount = min($amountToPay, $remaining);
        
        $newPaidAmount = $fine['paid_amount'] + $payAmount;
        $newRemaining = $fine['fine_amount'] - $newPaidAmount - $fine['waived_amount'];
        
        if ($newRemaining <= 0) {
            $newStatus = 'paid';
            $paidDate = $paymentDate;
        } else {
            $newStatus = 'partially_paid';
            $paidDate = null;
        }
        
        $stmt = $conn->prepare("
            UPDATE fines 
            SET paid_amount = ?, 
                status = ?, 
                paid_date = ?
            WHERE id = ?
        ");
        $stmt->bind_param('dssi', $newPaidAmount, $newStatus, $paidDate, $fine['id']);
        $stmt->execute();
        $stmt->close();
        
        $stmt = $conn->prepare("
            INSERT INTO fine_payments (
                fine_id, 
                amount, 
                payment_date, 
                payment_method, 
                transaction_no, 
                receipt_no, 
                notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $receiptNo = 'FP-' . date('Ymd') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
        $notes = "সঞ্চয় জমার সাথে ফাইন পরিশোধ";
        $stmt->bind_param('idsssss', $fine['id'], $payAmount, $paymentDate, $paymentMethod, $transactionNo, $receiptNo, $notes);
        $stmt->execute();
        $finePaymentIds[] = $conn->insert_id;
        $stmt->close();
        
        $amountToPay -= $payAmount;
        $totalPaid += $payAmount;
        $paidCount++;
    }
    
    return [
        'success' => true,
        'message' => "{$totalPaid} টাকা ফাইন পরিশোধ করা হয়েছে",
        'paid' => $totalPaid,
        'fine_payment_count' => $paidCount,
        'fine_payment_ids' => $finePaymentIds
    ];
}

// ============================================
// LOAD DATA FOR VIEW/EDIT
// ============================================
$deposit = null;
if (($action === 'view' || $action === 'edit') && $id > 0) {
    $deposit = queryOne("SELECT d.*, m.name, m.member_id, m.phone, m.monthly_deposit, m.photo 
                         FROM deposits d 
                         LEFT JOIN members m ON d.member_id = m.id 
                         WHERE d.id = ?", 'i', [$id]);
    if (!$deposit) {
        setFlash('danger', 'সঞ্চয় পাওয়া যায়নি।');
        header('Location: deposits.php');
        exit;
    }
    $pageTitle = $action === 'view' ? 'সঞ্চয় বিবরণ' : 'সঞ্চয় সম্পাদনা';
}

// ============================================
// HANDLE DELETE
// ============================================
if (isset($_GET['delete']) && $_GET['delete'] > 0) {
    $delId = (int)$_GET['delete'];
    if (!verifyCSRF($_GET['csrf_token'] ?? '')) {
        setFlash('danger', 'নিরাপত্তা যাচাই ব্যর্থ।');
    } else {
        $depositData = queryOne("SELECT * FROM deposits WHERE id = ?", 'i', [$delId]);
        if ($depositData) {
            $conn = conn();
            $stmt = $conn->prepare("DELETE FROM deposits WHERE id = ?");
            $stmt->bind_param('i', $delId);
            $stmt->execute();
            
            setFlash('success', "সঞ্চয় ডিলিট করা হয়েছে। রসিদ: {$depositData['receipt_no']}");
            logActivity('deposit_delete', "সঞ্চয় ডিলিট: {$depositData['receipt_no']}");
        }
    }
    header('Location: deposits.php');
    exit;
}

// ============================================
// HANDLE POST (Add/Edit) - WITH FINE INTEGRATION
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'নিরাপত্তা যাচাই ব্যর্থ।');
        header('Location: deposits.php');
        exit;
    }

    $postAction = $_POST['action'] ?? '';

    if (in_array($postAction, ['add', 'edit'])) {
        $member_id = isset($_POST['member_id']) ? (int)$_POST['member_id'] : 0;
        $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
        $deposit_date = $_POST['deposit_date'] ?? date('Y-m-d');
        $deposit_type = trim($_POST['deposit_type'] ?? 'regular');
        $payment_method = trim($_POST['payment_method'] ?? 'cash');
        $transaction_no = trim($_POST['transaction_no'] ?? '');
        $receipt_no = trim($_POST['receipt_no'] ?? '');
        $note = trim($_POST['note'] ?? '');
        $collected_by = (int)($_POST['collected_by'] ?? $_SESSION['admin_id']);
        $editId = isset($_POST['deposit_id']) ? (int)$_POST['deposit_id'] : 0;

        if ($member_id <= 0) {
            setFlash('danger', 'দয়া করে একজন সদস্য নির্বাচন করুন।');
            header('Location: deposits.php');
            exit;
        }
        
        if ($amount <= 0) {
            setFlash('danger', 'সঠিক পরিমাণ দিন।');
            header('Location: deposits.php');
            exit;
        }

        if ($postAction === 'add') {
            if (empty($receipt_no)) {
                $receipt_no = 'DEP-' . date('Ymd') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
            }
            
            $conn->begin_transaction();
            
            try {
                $pendingFines = getMemberPendingFines($member_id);
                $finePaidAmount = 0;
                $fineMessage = '';
                
                $stmt = $conn->prepare(
                    "INSERT INTO deposits (member_id, amount, deposit_date, deposit_type, 
                     payment_method, transaction_no, receipt_no, note, collected_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param('idssssssi', 
                    $member_id, $amount, $deposit_date, $deposit_type,
                    $payment_method, $transaction_no, 
                    $receipt_no, $note, $collected_by
                );
                
                if (!$stmt->execute()) {
                    throw new Exception('সঞ্চয় জমা ব্যর্থ হয়েছে: ' . $conn->error);
                }
                
                $depositId = $conn->insert_id;
                $stmt->close();
                
                if ($pendingFines['total_pending'] > 0) {
                    $fineResult = processFinePaymentWithDeposit(
                        $member_id, 
                        $amount, 
                        $depositId, 
                        $deposit_date, 
                        $payment_method, 
                        $transaction_no
                    );
                    
                    if ($fineResult['success'] && $fineResult['paid'] > 0) {
                        $finePaidAmount = $fineResult['paid'];
                        $fineMessage = " এবং ৳{$finePaidAmount} ফাইন পরিশোধ করা হয়েছে";
                        
                        $updatedNote = $note ? $note . "\n" : '';
                        $updatedNote .= "ফাইন পরিশোধ: ৳{$finePaidAmount} ({$fineResult['fine_payment_count']} টি ফাইন)";
                        
                        $stmt = $conn->prepare("UPDATE deposits SET note = ? WHERE id = ?");
                        $stmt->bind_param('si', $updatedNote, $depositId);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
                
                $conn->commit();
                
                logActivity('deposit_add', "নতুন সঞ্চয়: $receipt_no (৳$amount)" . ($finePaidAmount > 0 ? " + ফাইন ৳$finePaidAmount" : ""));
                setFlash('success', "সঞ্চয় জমা সফল! রসিদ নং: $receipt_no" . $fineMessage);
                header("Location: deposits.php");
                exit;
                
            } catch (Exception $e) {
                $conn->rollback();
                setFlash('danger', $e->getMessage());
                header('Location: deposits.php');
                exit;
            }
        } 
        elseif ($postAction === 'edit') {
            if ($editId <= 0) {
                setFlash('danger', 'সম্পাদনার জন্য সঠিক আইডি পাওয়া যায়নি।');
                header('Location: deposits.php');
                exit;
            }
            
            $stmt = $conn->prepare(
                "UPDATE deposits SET 
                    member_id = ?, 
                    amount = ?, 
                    deposit_date = ?, 
                    deposit_type = ?, 
                    payment_method = ?, 
                    transaction_no = ?, 
                    note = ?, 
                    collected_by = ? 
                WHERE id = ?"
            );
            $stmt->bind_param('idsssssii', 
                $member_id, 
                $amount, 
                $deposit_date, 
                $deposit_type,
                $payment_method, 
                $transaction_no,
                $note, 
                $collected_by, 
                $editId
            );
            
            if ($stmt->execute()) {
                logActivity('deposit_edit', "সঞ্চয় আপডেট: ID #$editId");
                setFlash('success', 'সঞ্চয় আপডেট হয়েছে।');
                header("Location: deposits.php");
                exit;
            } else {
                setFlash('danger', 'আপডেট ব্যর্থ হয়েছে: ' . $conn->error);
                header('Location: deposits.php');
                exit;
            }
        }
    }
    
    header('Location: deposits.php');
    exit;
}

// ============================================
// GET MEMBER DATA FOR AJAX (WITH FINE INFO)
// ============================================
if (isset($_GET['ajax']) && $_GET['ajax'] === 'member' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $memId = (int)$_GET['id'];
    $member = queryOne("SELECT * FROM members WHERE id = ?", 'i', [$memId]);
    
    if ($member) {
        $totalDeposit = queryOne("SELECT COALESCE(SUM(amount),0) as total FROM deposits WHERE member_id = ? AND deposit_type IN ('regular', 'special', 'share', 'fd')", 'i', [$memId])['total'] ?? 0;
        $totalWithdrawal = queryOne("SELECT COALESCE(SUM(amount),0) as total FROM deposits WHERE member_id = ? AND deposit_type='withdrawal'", 'i', [$memId])['total'] ?? 0;
        $balance = $totalDeposit - $totalWithdrawal;
        $photo = getMemberPhoto($member);
        
        $pendingFines = getMemberPendingFines($memId);
        
        echo json_encode([
            'success' => true,
            'member' => $member,
            'photo' => $photo,
            'total_deposit' => $totalDeposit,
            'total_withdrawal' => $totalWithdrawal,
            'balance' => $balance,
            'monthly_deposit' => $member['monthly_deposit'] ?? 500,
            'pending_fines' => $pendingFines,
            'has_pending_fine' => $pendingFines['total_pending'] > 0,
            'pending_fine_amount' => $pendingFines['total_pending'],
            'pending_fine_count' => $pendingFines['count']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'সদস্য পাওয়া যায়নি']);
    }
    exit;
}

// ============================================
// BUILD QUERY FOR HISTORY
// ============================================
$search = trim($_GET['s'] ?? '');
$monthFilter = $_GET['month'] ?? '';
$yearFilter = $_GET['year'] ?? date('Y');
$typeFilter = $_GET['type'] ?? '';
$member_id_filter = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;
$page = max(1, (int)($_GET['page'] ?? 1));

$where = '1=1';
$types = '';
$params = [];

if ($member_id_filter > 0) {
    $where .= " AND d.member_id = ?";
    $types .= 'i';
    $params[] = $member_id_filter;
}

if (!empty($search)) {
    $where .= " AND (m.name LIKE ? OR m.member_id LIKE ? OR d.receipt_no LIKE ? OR m.phone LIKE ?)";
    $like = "%$search%";
    $types .= 'ssss';
    $params = array_merge($params, [$like, $like, $like, $like]);
}

if (!empty($monthFilter)) {
    $where .= " AND MONTH(d.deposit_date) = ?";
    $types .= 'i';
    $params[] = $monthFilter;
}

if (!empty($yearFilter)) {
    $where .= " AND YEAR(d.deposit_date) = ?";
    $types .= 'i';
    $params[] = $yearFilter;
}

if (!empty($typeFilter)) {
    $where .= " AND d.deposit_type = ?";
    $types .= 's';
    $params[] = $typeFilter;
}

$sql = "SELECT d.id, d.member_id, d.amount, d.deposit_date, d.payment_method, 
               d.transaction_no, d.note, d.deposit_type, d.receipt_no, 
               d.collected_by, d.created_at,
               m.name, m.member_id AS member_member_id, m.phone, m.photo 
        FROM deposits d 
        LEFT JOIN members m ON d.member_id = m.id 
        WHERE $where 
        ORDER BY d.deposit_date DESC, d.created_at DESC";

$countSql = "SELECT COUNT(*) as total FROM deposits d LEFT JOIN members m ON d.member_id = m.id WHERE $where";
$countResult = queryOne($countSql, $types, $params);
$totalRecords = $countResult['total'] ?? 0;

$activeMembers = queryAll("SELECT id, name, member_id, phone, monthly_deposit FROM members WHERE status='active' ORDER BY name");

$perPage = 20;
$totalPages = ceil($totalRecords / $perPage);
$offset = ($page - 1) * $perPage;
$sql .= " LIMIT ? OFFSET ?";
$types .= 'ii';
$params[] = $perPage;
$params[] = $offset;

$deposits = queryAll($sql, $types, $params);

$totalAmount = 0;
if (!empty($deposits)) {
    $totalAmount = array_sum(array_column($deposits, 'amount'));
}

$netBalance = getTotalBalance();

require_once __DIR__ . '/../includes/header.php';

$flash = getFlash();
if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show m-3" role="alert">
    <i data-feather="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-circle' ?>" 
       style="width:16px;height:16px;vertical-align:middle"></i>
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- ============================================
     MAIN CONTENT - MOBILE RESPONSIVE
     ============================================ -->
<div class="container-fluid px-2 px-md-4">
    
    <!-- MOBILE BALANCE BADGE - Visible on mobile -->
    <div class="d-block d-lg-none mb-3">
        <div class="card bg-success text-white">
            <div class="card-body py-2 text-center">
                <small class="text-white-50">মোট সঞ্চয় জমা</small>
                <div class="h5 mb-0"><?= formatCurrency($netBalance) ?></div>
            </div>
        </div>
    </div>

    <div class="row g-2 g-md-3">

        <!-- LEFT COLUMN: ADD DEPOSIT FORM -->
        <div class="col-12 col-lg-5">
            <div class="card sticky-top-mobile">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <span class="card-title">
                        <i data-feather="plus-circle" style="width:16px;height:16px"></i>
                        <span class="d-none d-sm-inline">নতুন সঞ্চয় জমা</span>
                        <span class="d-inline d-sm-none">জমা</span>
                    </span>
                    <!-- NET BALANCE - Desktop only -->
                    <span class="badge bg-success d-none d-lg-inline-block" style="font-size: 14px; padding: 6px 14px;">
                        <i data-feather="dollar-sign" style="width:14px;height:14px;"></i>
                        মোট জমা: <?= formatCurrency($netBalance) ?>
                    </span>
                    <!-- Toggle button for mobile -->
                    <button class="btn btn-sm btn-outline-secondary d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#depositFormCollapse" aria-expanded="true">
                        <i data-feather="chevron-down" style="width:16px;height:16px"></i>
                    </button>
                </div>
                
                <div class="collapse d-lg-block" id="depositFormCollapse">
                    <div class="card-body" style="max-height: calc(100vh - 200px); overflow-y: auto;">
                        <form method="POST" id="depositForm">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="add">

                            <div class="row g-2">
                                <!-- Member Selection -->
                                <div class="col-12">
                                    <label class="form-label">সদস্য নির্বাচন <span class="text-danger">*</span></label>
                                    <select name="member_id" id="memberSelect" class="form-control select2-searchable" required>
                                        <option value="">-- সদস্য নির্বাচন করুন --</option>
                                        <?php foreach ($activeMembers as $m): ?>
                                        <option value="<?= $m['id'] ?>" 
                                                <?= ($member_id_filter == $m['id']) ? 'selected' : '' ?>
                                                data-name="<?= htmlspecialchars($m['name']) ?>"
                                                data-member-id="<?= $m['member_id'] ?>"
                                                data-phone="<?= $m['phone'] ?>"
                                                data-monthly-deposit="<?= $m['monthly_deposit'] ?? 500 ?>">
                                            <?= htmlspecialchars($m['name']) ?> (<?= $m['member_id'] ?>) - <?= $m['phone'] ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">
                                        <i data-feather="search" style="width:14px;height:14px"></i>
                                        নাম, সদস্য আইডি বা ফোন দিয়ে খুঁজুন
                                    </div>
                                </div>

                                <!-- Member Info -->
                                <div class="col-12" id="memberInfo" style="display: none;">
                                    <div class="card bg-light">
                                        <div class="card-body py-2">
                                            <div class="row align-items-center g-2">
                                                <div class="col-auto">
                                                    <img id="memberPhoto" src="" alt="Photo" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                                                </div>
                                                <div class="col">
                                                    <div class="row g-1">
                                                        <div class="col-12">
                                                            <small class="text-muted">নাম</small>
                                                            <div><strong id="displayName" style="font-size:14px;">-</strong></div>
                                                        </div>
                                                        <div class="col-6">
                                                            <small class="text-muted">সদস্য ID</small>
                                                            <div><strong id="displayMemberId" style="font-size:13px;">-</strong></div>
                                                        </div>
                                                        <div class="col-6">
                                                            <small class="text-muted">মাসিক জমা</small>
                                                            <div><strong id="displayMonthlyDeposit" style="font-size:13px;">-</strong></div>
                                                        </div>
                                                        <div class="col-4">
                                                            <small class="text-muted">মোট জমা</small>
                                                            <div><strong id="displayTotalDeposit" class="text-success" style="font-size:13px;">-</strong></div>
                                                        </div>
                                                        <div class="col-4">
                                                            <small class="text-muted">মোট উত্তোলন</small>
                                                            <div><strong id="displayTotalWithdrawal" class="text-danger" style="font-size:13px;">-</strong></div>
                                                        </div>
                                                        <div class="col-4">
                                                            <small class="text-muted">ব্যালেন্স</small>
                                                            <div><strong id="displayBalance" class="text-primary" style="font-size:13px;">-</strong></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Pending Fine Display -->
                                <div class="col-12" id="pendingFineInfo" style="display: none;">
                                    <div class="card" style="border-left: 4px solid #E74C3C; background: #FDEDEC;">
                                        <div class="card-body py-2">
                                            <div class="d-flex align-items-start gap-2">
                                                <i data-feather="alert-triangle" style="color:#E74C3C; margin-top:2px;"></i>
                                                <div>
                                                    <div class="text-muted small">⚠️ বকেয়া ফাইন</div>
                                                    <div style="font-size:16px; font-weight:700; color:#E74C3C;" id="pendingFineAmount">৳0.00</div>
                                                    <div class="text-muted small" id="pendingFineCount">0 টি ফাইন</div>
                                                    <small class="text-muted">এই ফাইন জমার সাথে স্বয়ংক্রিয়ভাবে পরিশোধ হবে</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- DEPOSIT AMOUNT - AUTO FILL -->
                                <div class="col-12">
                                    <label class="form-label">মাসিক জমার পরিমাণ (৳) <span class="text-danger">*</span></label>
                                    <input type="number" name="amount" id="depositAmount" class="form-control" 
                                           step="0.01" required placeholder="সদস্য নির্বাচন করলে স্বয়ংক্রিয়ভাবে বসবে">
                                    <small class="text-muted" id="amountHelper">
                                        <i data-feather="info" style="width:12px;height:12px"></i>
                                        সদস্য নির্বাচন করলে মাসিক জমার পরিমাণ স্বয়ংক্রিয়ভাবে বসবে
                                    </small>
                                </div>

                                <!-- TOTAL AMOUNT DISPLAY -->
                                <div class="col-12" id="totalAmountDisplay" style="display: none;">
                                    <div class="card bg-primary text-white">
                                        <div class="card-body py-2">
                                            <div class="row text-center g-0">
                                                <div class="col-4 border-end border-light">
                                                    <div class="small">সঞ্চয়</div>
                                                    <strong id="totalDepositOnly" style="font-size:16px;">৳0.00</strong>
                                                </div>
                                                <div class="col-4 border-end border-light">
                                                    <div class="small">ফাইন</div>
                                                    <strong id="totalFineOnly" style="font-size:16px; color:#FFD700;">৳0.00</strong>
                                                </div>
                                                <div class="col-4">
                                                    <div class="small">মোট</div>
                                                    <strong id="totalAmountText" style="font-size:18px;">৳0.00</strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-6">
                                    <label class="form-label">তারিখ <span class="text-danger">*</span></label>
                                    <input type="date" name="deposit_date" class="form-control" required
                                           value="<?= date('Y-m-d') ?>">
                                </div>

                                <div class="col-6">
                                    <label class="form-label">ধরন</label>
                                    <select name="deposit_type" class="form-select">
                                        <option value="regular">নিয়মিত</option>
                                        <option value="special">বিশেষ</option>
                                        <option value="share">শেয়ার</option>
                                        <option value="fd">এফডি</option>
                                        <option value="fine">ফাইন</option>
                                    </select>
                                </div>

                                <div class="col-6">
                                    <label class="form-label">পদ্ধতি</label>
                                    <select name="payment_method" class="form-select">
                                        <option value="cash">নগদ</option>
                                        <option value="bank">ব্যাংক</option>
                                        <option value="mobile">মোবাইল</option>
                                        <option value="check">চেক</option>
                                        <option value="online">অনলাইন</option>
                                    </select>
                                </div>

                                <div class="col-6">
                                    <label class="form-label">লেনদেন নম্বর</label>
                                    <input type="text" name="transaction_no" class="form-control" 
                                           placeholder="লেনদেন নম্বর">
                                </div>

                                <div class="col-12">
                                    <label class="form-label">মন্তব্য</label>
                                    <textarea name="note" class="form-control" rows="2" 
                                              placeholder="বিবরণ (ঐচ্ছিক)"></textarea>
                                </div>

                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary w-100" id="submitBtn">
                                        <i data-feather="save" style="width:15px;height:15px" class="me-1"></i>
                                        সঞ্চয় জমা করুন
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: HISTORY -->
        <div class="col-12 col-lg-7">
            <div class="card">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <span class="card-title">
                        <i data-feather="book-open" style="width:16px;height:16px"></i>
                        <span class="d-none d-sm-inline">সঞ্চয় তালিকা</span>
                        <span class="d-inline d-sm-none">তালিকা</span>
                    </span>
                    <div class="d-flex gap-1">
                        <span class="badge bg-secondary d-none d-md-inline-block"><?= $totalRecords ?> টি</span>
                        <a href="deposits.php" class="btn btn-outline-secondary btn-sm">
                            <i data-feather="refresh-cw" style="width:14px;height:14px"></i>
                            <span class="d-none d-sm-inline">রিসেট</span>
                        </a>
                    </div>
                </div>

                <!-- FILTERS - Mobile optimized -->
                <div class="card-body border-bottom pb-2">
                    <form method="GET" class="row g-2" id="filterForm">
                        <div class="col-12 col-sm-6 col-md-3">
                            <div class="search-box">
                                <i data-feather="search" class="search-icon"></i>
                                <input type="text" name="s" class="form-control form-control-sm" 
                                       placeholder="খুঁজুন..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                        </div>
                        <div class="col-6 col-sm-3 col-md-2">
                            <select name="month" class="form-select form-select-sm">
                                <option value="">মাস</option>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= $monthFilter == $m ? 'selected' : '' ?>>
                                    <?= date('M', mktime(0,0,0,$m,1)) ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-6 col-sm-3 col-md-2">
                            <select name="year" class="form-select form-select-sm">
                                <option value="">সাল</option>
                                <?php for ($y = date('Y'); $y >= date('Y')-5; $y--): ?>
                                <option value="<?= $y ?>" <?= $yearFilter == $y ? 'selected' : '' ?>>
                                    <?= $y ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-6 col-sm-6 col-md-2">
                            <select name="type" class="form-select form-select-sm">
                                <option value="">ধরন</option>
                                <option value="regular" <?= $typeFilter === 'regular' ? 'selected' : '' ?>>নিয়মিত</option>
                                <option value="special" <?= $typeFilter === 'special' ? 'selected' : '' ?>>বিশেষ</option>
                                <option value="share" <?= $typeFilter === 'share' ? 'selected' : '' ?>>শেয়ার</option>
                                <option value="fd" <?= $typeFilter === 'fd' ? 'selected' : '' ?>>এফডি</option>
                                <option value="fine" <?= $typeFilter === 'fine' ? 'selected' : '' ?>>ফাইন</option>
                            </select>
                        </div>
                        <div class="col-6 col-sm-6 col-md-3 d-flex gap-1">
                            <button class="btn btn-primary btn-sm flex-grow-1" type="submit">
                                <i data-feather="search" style="width:14px;height:14px"></i>
                                <span class="d-none d-sm-inline">খুঁজুন</span>
                            </button>
                            <?php if ($search || $monthFilter || $yearFilter || $typeFilter || $member_id_filter > 0): ?>
                            <a href="deposits.php" class="btn btn-outline-secondary btn-sm flex-grow-1">
                                <i data-feather="x" style="width:14px;height:14px"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php if ($member_id_filter > 0): ?>
                        <input type="hidden" name="member_id" value="<?= $member_id_filter ?>">
                        <?php endif; ?>
                    </form>
                </div>

                <!-- TABLE - Mobile responsive with horizontal scroll -->
                <div class="table-responsive" style="max-height: 600px; overflow-y: auto; overflow-x: auto;">
                    <table class="table table-hover table-striped table-sm" id="depositTable">
                        <thead style="position: sticky; top: 0; background: #fff; z-index: 10; border-bottom: 2px solid #dee2e6;">
                            <tr>
                                <th style="min-width:70px;">রসিদ</th>
                                <th style="min-width:120px;">সদস্য</th>
                                <th style="min-width:60px;">পরিমাণ</th>
                                <th style="min-width:80px;">ধরন</th>
                                <th style="min-width:60px;">তারিখ</th>
                                <th style="min-width:100px;" class="text-center">কার্যক্রম</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($deposits)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i data-feather="inbox" style="width:32px;height:32px;display:block;margin:0 auto 10px"></i>
                                    কোনো সঞ্চয় পাওয়া যায়নি
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($deposits as $d): 
                                $depositId = isset($d['id']) ? (int)$d['id'] : 0;
                                $receiptNo = isset($d['receipt_no']) ? (string)$d['receipt_no'] : '';
                                $memberName = isset($d['name']) ? (string)$d['name'] : '';
                                $memberCode = isset($d['member_member_id']) ? (string)$d['member_member_id'] : (isset($d['member_id']) ? (string)$d['member_id'] : '');
                                $depositDate = isset($d['deposit_date']) ? $d['deposit_date'] : date('Y-m-d');
                                $amount = isset($d['amount']) ? (float)$d['amount'] : 0;
                                $paymentMethod = isset($d['payment_method']) ? (string)$d['payment_method'] : '';
                                $transactionNo = isset($d['transaction_no']) ? (string)$d['transaction_no'] : '-';
                                $noteValue = isset($d['note']) ? (string)$d['note'] : '';
                                $depositType = isset($d['deposit_type']) ? (string)$d['deposit_type'] : 'regular';
                                
                                $photoFile = isset($d['photo']) ? (string)$d['photo'] : '';
                                if (!empty($photoFile)) {
                                    if (strpos($photoFile, '/') !== false) {
                                        $photoFile = basename($photoFile);
                                    }
                                    $photoUrl = '/uploads/members/' . $photoFile;
                                    $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/uploads/members/' . $photoFile;
                                    $hasPhoto = file_exists($fullPath);
                                } else {
                                    $hasPhoto = false;
                                    $photoUrl = '';
                                }
                                
                                $rowClass = '';
                                $amountClass = 'amount-positive';
                                $badgeClass = '';
                                $badgeText = '';
                                
                                if ($depositType == 'withdrawal') {
                                    $rowClass = 'row-withdrawal';
                                    $amountClass = 'amount-negative';
                                    $badgeClass = 'bg-danger';
                                    $badgeText = 'উত্তোলন';
                                } elseif ($depositType == 'expense') {
                                    $rowClass = 'row-expense';
                                    $amountClass = 'amount-negative';
                                    $badgeClass = 'bg-success text-white';
                                    $badgeText = 'খরচ';
                                } else {
                                    $typeLabels = [
                                        'regular' => 'নিয়মিত',
                                        'special' => 'বিশেষ',
                                        'share' => 'শেয়ার',
                                        'fd' => 'এফডি',
                                        'fine' => 'ফাইন'
                                    ];
                                    $badgeClass = 'type-' . $depositType;
                                    $badgeText = isset($typeLabels[$depositType]) ? $typeLabels[$depositType] : ucfirst($depositType);
                                }
                            ?>
                            <tr class="clickable-row <?= $rowClass ?>" data-id="<?= $depositId ?>" style="cursor:pointer;">
                                <td><code style="font-size:11px;"><?= htmlspecialchars($receiptNo) ?></code></td>
                                <td>
                                    <div class="d-flex align-items-center gap-1">
                                        <?php if ($hasPhoto): ?>
                                            <img src="<?= htmlspecialchars($photoUrl) ?>" 
                                                 alt="<?= htmlspecialchars($memberName) ?>" 
                                                 class="member-avatar-xs"
                                                 onerror="this.style.display='none'; this.parentElement.querySelector('.avatar-placeholder-xs').style.display='flex';">
                                            <div class="avatar-placeholder-xs" style="display:none;">
                                                <?= strtoupper(substr($memberName, 0, 1)) ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="avatar-placeholder-xs">
                                                <?= strtoupper(substr($memberName, 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div style="font-weight:500;font-size:12px;white-space:nowrap;"><?= htmlspecialchars($memberName) ?></div>
                                            <small class="text-muted" style="font-size:9px;"><?= htmlspecialchars($memberCode) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td class="<?= $amountClass ?>" style="font-size:13px;font-weight:600;"><?= formatCurrency($amount) ?></td>
                                <td>
                                    <span class="badge <?= $badgeClass ?>" style="font-size:9px;"><?= $badgeText ?></span>
                                </td>
                                <td style="font-size:11px;white-space:nowrap;"><?= date('d M y', strtotime($depositDate)) ?></td>
                                <td class="text-center no-print" style="white-space:nowrap;">
                                    <div class="btn-group btn-group-sm" onclick="event.stopPropagation();">
                                        <a href="deposits.php?action=view&id=<?= $depositId ?>" class="btn btn-outline-primary" title="দেখুন">
                                            <i data-feather="eye" style="width:12px;height:12px"></i>
                                        </a>
                                        <button class="btn btn-outline-info" onclick="printReceipt('<?= htmlspecialchars($receiptNo) ?>', '<?= htmlspecialchars(addslashes($memberName)) ?>', '<?= $amount ?>', '<?= $depositDate ?>', '<?= $depositType ?>')" title="প্রিন্ট">
                                            <i data-feather="printer" style="width:12px;height:12px"></i>
                                        </button>
                                        <a href="deposits.php?action=edit&id=<?= $depositId ?>" class="btn btn-outline-warning" title="সম্পাদনা">
                                            <i data-feather="edit-2" style="width:12px;height:12px"></i>
                                        </a>
                                        <a href="deposits.php?delete=<?= $depositId ?>&csrf_token=<?= generateCSRF() ?>" 
                                           class="btn btn-outline-danger" 
                                           onclick="return confirm('আপনি কি নিশ্চিত?')" 
                                           title="ডিলিট">
                                            <i data-feather="trash-2" style="width:12px;height:12px"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-active">
                                <td colspan="2"><strong>সর্বমোট</strong></td>
                                <td><strong><?= formatCurrency($totalAmount) ?></strong></td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <div class="card-body pt-2">
                    <?php if ($totalPages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center mb-0 flex-wrap gap-1">
                            <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page-1 ?>&s=<?= urlencode($search) ?>&month=<?= $monthFilter ?>&year=<?= $yearFilter ?>&type=<?= $typeFilter ?>&member_id=<?= $member_id_filter ?>">
                                    <i data-feather="chevron-left" style="width:14px;height:14px"></i>
                                </a>
                            </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&s=<?= urlencode($search) ?>&month=<?= $monthFilter ?>&year=<?= $yearFilter ?>&type=<?= $typeFilter ?>&member_id=<?= $member_id_filter ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page+1 ?>&s=<?= urlencode($search) ?>&month=<?= $monthFilter ?>&year=<?= $yearFilter ?>&type=<?= $typeFilter ?>&member_id=<?= $member_id_filter ?>">
                                    <i data-feather="chevron-right" style="width:14px;height:14px"></i>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                    <div class="text-center text-muted small mt-2">
                        মোট <?= $totalRecords ?> টি রেকর্ড 
                        <?php if ($totalPages > 1): ?>
                        (পৃষ্ঠা <?= $page ?> / <?= $totalPages ?>)
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
     PRINT RECEIPT POPUP MODAL
     ============================================ -->
<div class="modal fade" id="receiptModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">জমার রসিদ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="receiptContent"></div>
            <div class="modal-footer">
                <button class="btn btn-primary btn-sm" onclick="printReceiptContent()">
                    <i data-feather="printer" style="width:14px;height:14px"></i> প্রিন্ট
                </button>
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">বন্ধ</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
     VIEW DEPOSIT DETAILS MODAL
     ============================================ -->
<?php if ($action === 'view' && $deposit): ?>
<div class="modal fade show" id="viewModal" tabindex="-1" style="display:block;background:rgba(0,0,0,0.5);">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i data-feather="eye" style="width:18px;height:18px"></i>
                    সঞ্চয় বিবরণ
                </h5>
                <a href="deposits.php" class="btn-close"></a>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-12">
                        <table class="table table-borderless table-sm">
                            <tr><td><strong>রসিদ নম্বর</strong></td><td><code><?= htmlspecialchars($deposit['receipt_no']) ?></code></td></tr>
                            <tr><td><strong>সদস্য</strong></td><td><?= htmlspecialchars($deposit['name']) ?> (<?= $deposit['member_id'] ?>)</td></tr>
                            <tr><td><strong>পরিমাণ</strong></td><td><?= formatCurrency($deposit['amount']) ?></td></tr>
                            <tr><td><strong>জমার তারিখ</strong></td><td><?= formatDate($deposit['deposit_date']) ?></td></tr>
                            <tr><td><strong>মাস</strong></td><td><?= date('F Y', strtotime($deposit['deposit_date'])) ?></td></tr>
                            <tr><td><strong>সঞ্চয়ের ধরন</strong></td><td><?= htmlspecialchars($deposit['deposit_type']) ?></td></tr>
                            <tr><td><strong>পেমেন্ট পদ্ধতি</strong></td><td><?= htmlspecialchars($deposit['payment_method']) ?></td></tr>
                            <?php if (isset($deposit['transaction_no']) && !empty($deposit['transaction_no'])): ?>
                            <tr><td><strong>লেনদেন নম্বর</strong></td><td><?= htmlspecialchars($deposit['transaction_no']) ?></td></tr>
                            <?php endif; ?>
                            <tr><td><strong>মন্তব্য</strong></td><td><?= nl2br(htmlspecialchars($deposit['note'] ?? '-')) ?></td></tr>
                            <tr><td><strong>সংগ্রহকারী</strong></td><td>Admin #<?= $deposit['collected_by'] ?? 'N/A' ?></td></tr>
                            <tr><td><strong>তৈরি</strong></td><td><?= formatDateTime($deposit['created_at']) ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="deposits.php" class="btn btn-secondary">বন্ধ</a>
                <a href="deposits.php?action=edit&id=<?= $id ?>" class="btn btn-warning">
                    <i data-feather="edit-2" style="width:14px;height:14px"></i> সম্পাদনা
                </a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ============================================
     EDIT DEPOSIT MODAL
     ============================================ -->
<?php if ($action === 'edit' && $deposit): ?>
<div class="modal fade show" id="editModal" tabindex="-1" style="display:block;background:rgba(0,0,0,0.5);">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i data-feather="edit-2" style="width:18px;height:18px"></i>
                    সঞ্চয় সম্পাদনা
                </h5>
                <a href="deposits.php" class="btn-close"></a>
            </div>
            <form method="POST" action="deposits.php">
                <div class="modal-body">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="deposit_id" value="<?= $deposit['id'] ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">সদস্য <span class="text-danger">*</span></label>
                        <select name="member_id" id="editMemberSelect" class="form-control select2-searchable" required>
                            <option value="">-- সদস্য নির্বাচন করুন --</option>
                            <?php foreach ($activeMembers as $m): ?>
                            <option value="<?= $m['id'] ?>" 
                                    data-name="<?= htmlspecialchars($m['name']) ?>"
                                    data-member-id="<?= $m['member_id'] ?>"
                                    data-phone="<?= $m['phone'] ?>">
                                <?= htmlspecialchars($m['name']) ?> (<?= $m['member_id'] ?>) - <?= $m['phone'] ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">নাম, আইডি বা ফোন দিয়ে খুঁজুন</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">রসিদ নং</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($deposit['receipt_no']) ?>" disabled>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">পরিমাণ (৳) <span class="text-danger">*</span></label>
                        <input type="number" name="amount" class="form-control" 
                               value="<?= $deposit['amount'] ?>" step="0.01" min="1" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">তারিখ <span class="text-danger">*</span></label>
                        <input type="date" name="deposit_date" class="form-control" 
                               value="<?= $deposit['deposit_date'] ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">জমার ধরন</label>
                        <select name="deposit_type" class="form-select">
                            <option value="regular" <?= $deposit['deposit_type'] == 'regular' ? 'selected' : '' ?>>নিয়মিত</option>
                            <option value="special" <?= $deposit['deposit_type'] == 'special' ? 'selected' : '' ?>>বিশেষ</option>
                            <option value="share" <?= $deposit['deposit_type'] == 'share' ? 'selected' : '' ?>>শেয়ার</option>
                            <option value="fd" <?= $deposit['deposit_type'] == 'fd' ? 'selected' : '' ?>>এফডি</option>
                            <option value="fine" <?= $deposit['deposit_type'] == 'fine' ? 'selected' : '' ?>>ফাইন</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">পেমেন্ট পদ্ধতি</label>
                        <select name="payment_method" class="form-select">
                            <option value="cash" <?= $deposit['payment_method'] == 'cash' ? 'selected' : '' ?>>নগদ</option>
                            <option value="bank" <?= $deposit['payment_method'] == 'bank' ? 'selected' : '' ?>>ব্যাংক</option>
                            <option value="mobile" <?= $deposit['payment_method'] == 'mobile' ? 'selected' : '' ?>>মোবাইল</option>
                            <option value="check" <?= $deposit['payment_method'] == 'check' ? 'selected' : '' ?>>চেক</option>
                            <option value="online" <?= $deposit['payment_method'] == 'online' ? 'selected' : '' ?>>অনলাইন</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">লেনদেন নম্বর</label>
                        <input type="text" name="transaction_no" class="form-control" 
                               value="<?= htmlspecialchars($deposit['transaction_no'] ?? '') ?>" placeholder="লেনদেন নম্বর">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">মন্তব্য</label>
                        <textarea name="note" class="form-control" rows="2" placeholder="ঐচ্ছিক"><?= htmlspecialchars($deposit['note'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="deposits.php" class="btn btn-secondary">বাতিল</a>
                    <button type="submit" class="btn btn-primary">
                        <i data-feather="save" style="width:14px;height:14px"></i> আপডেট করুন
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ============================================
     JQUERY & SELECT2 CDN
     ============================================ -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
/* ============================================
   MOBILE RESPONSIVE STYLES
   ============================================ */

/* Mobile sticky top adjustment */
.sticky-top-mobile {
    position: sticky;
    top: 60px;
    z-index: 100;
}

/* Extra small avatar for mobile */
.member-avatar-xs {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #e9ecef;
    background-color: #f8f9fa;
    flex-shrink: 0;
}
.avatar-placeholder-xs {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #1B4F72, #2E86C1);
    color: white;
    font-weight: bold;
    font-size: 12px;
    border: 2px solid #e9ecef;
    flex-shrink: 0;
}

.type-regular { background: #D5F5E3; color: #1E8449; }
.type-special { background: #D6EAF8; color: #1A5276; }
.type-share { background: #FEF9E7; color: #B7950B; }
.type-fd { background: #F5EEF8; color: #7D3C98; }
.type-fine { background: #FDEDEC; color: #C0392B; }

.amount-positive { color: #27AE60; font-weight: 600; }
.amount-negative { color: #E74C3C; font-weight: 600; }

.row-withdrawal {
    background: #fff5f5 !important;
    border-left: 4px solid #dc3545 !important;
}
.row-withdrawal:hover {
    background: #ffe0e0 !important;
}
.row-withdrawal .amount-negative {
    color: #C0392B !important;
    font-weight: 700 !important;
}

.row-expense {
    background: #f5fff5 !important;
    border-left: 4px solid #28a745 !important;
}
.row-expense:hover {
    background: #e0ffe0 !important;
}
.row-expense .amount-negative {
    color: #1E7E34 !important;
    font-weight: 700 !important;
}

.search-box {
    position: relative;
}
.search-box .search-icon {
    position: absolute;
    left: 8px;
    top: 50%;
    transform: translateY(-50%);
    width: 14px;
    height: 14px;
    color: #999;
}
.search-box input {
    padding-left: 28px;
}

.card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}
.card-header {
    background: white;
    border-bottom: 1px solid #f0f0f0;
    padding: 10px 14px;
    border-radius: 12px 12px 0 0 !important;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
}
.card-title {
    font-size: 13px;
    font-weight: 600;
    color: #2C3E50;
    display: flex;
    align-items: center;
    gap: 6px;
}
.card-body {
    padding: 12px 14px;
}

.table {
    font-size: 12px;
    margin-bottom: 0;
}
.table th {
    font-weight: 600;
    color: #566573;
    border-bottom: 2px solid #f0f0f0;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    background: #fff;
    position: sticky;
    top: 0;
    z-index: 10;
    padding: 6px 8px;
}
.table td {
    vertical-align: middle;
    padding: 6px 8px;
    font-size: 11px;
}
.table-hover tbody tr:hover {
    background: #f0f7ff !important;
}

.table-responsive {
    max-height: 600px;
    overflow-y: auto;
    overflow-x: auto;
}

.select2-container {
    width: 100% !important;
}
.select2-container--default .select2-selection--single {
    height: 38px !important;
    border: 1px solid #ced4da !important;
    border-radius: 8px !important;
    background: #fff !important;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 36px !important;
    padding-left: 12px !important;
    color: #212529 !important;
    font-size: 14px !important;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 36px !important;
}

.modal.show {
    display: block !important;
}
.modal-backdrop {
    display: none;
}

.receipt-container {
    font-family: 'Courier New', monospace;
    font-size: 12px;
    padding: 10px;
}
.receipt-container .receipt-header {
    text-align: center;
    border-bottom: 2px dashed #333;
    padding-bottom: 8px;
    margin-bottom: 8px;
}
.receipt-container .receipt-header h5 {
    margin: 0;
    font-weight: bold;
}
.receipt-container .receipt-body {
    padding: 5px 0;
}
.receipt-container .receipt-row {
    display: flex;
    justify-content: space-between;
    padding: 3px 0;
    border-bottom: 1px dotted #ddd;
}
.receipt-container .receipt-footer {
    text-align: center;
    border-top: 2px dashed #333;
    padding-top: 8px;
    margin-top: 8px;
}

#totalAmountDisplay .border-end {
    border-color: rgba(255,255,255,0.3) !important;
}

/* ============================================
   RESPONSIVE BREAKPOINTS
   ============================================ */

/* Large screens */
@media (min-width: 992px) {
    .sticky-top-mobile {
        position: sticky;
        top: 80px;
        z-index: 100;
    }
    .card-body {
        max-height: calc(100vh - 200px);
        overflow-y: auto;
    }
}

/* Tablets and small laptops */
@media (max-width: 991px) {
    .sticky-top-mobile {
        position: relative;
        top: 0;
    }
    .card-body {
        max-height: none !important;
        overflow-y: visible !important;
    }
}

/* Mobile phones */
@media (max-width: 768px) {
    .table td, .table th {
        padding: 4px 6px;
        font-size: 10px;
    }
    .card-header {
        padding: 8px 12px;
    }
    .card-body {
        padding: 10px 12px;
    }
    .btn-group .btn {
        padding: 1px 3px;
    }
    .btn-group .btn i {
        width: 10px;
        height: 10px;
    }
    .pagination .page-item .page-link {
        padding: 3px 8px;
        font-size: 11px;
    }
    .member-avatar-sm {
        width: 24px;
        height: 24px;
    }
    .avatar-placeholder {
        width: 24px;
        height: 24px;
        font-size: 10px;
    }
    .container-fluid {
        padding-left: 8px !important;
        padding-right: 8px !important;
    }
}

/* Very small phones */
@media (max-width: 400px) {
    .table td, .table th {
        padding: 3px 4px;
        font-size: 9px;
    }
    .card-header {
        padding: 6px 8px;
    }
    .card-title {
        font-size: 11px;
    }
    .badge {
        font-size: 9px !important;
        padding: 3px 6px !important;
    }
    .btn-sm {
        font-size: 10px !important;
        padding: 2px 6px !important;
    }
}

@media print {
    .btn, .btn-group, .no-print {
        display: none !important;
    }
    .card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
}
</style>

<script>
let pendingFineAmount = 0;
let pendingFineCount = 0;
let monthlyDeposit = 0;

function loadMemberData(memberId) {
    if (!memberId) {
        document.getElementById('memberInfo').style.display = 'none';
        document.getElementById('pendingFineInfo').style.display = 'none';
        document.getElementById('totalAmountDisplay').style.display = 'none';
        document.getElementById('depositAmount').value = '';
        document.getElementById('amountHelper').innerHTML = 
            '<i data-feather="info" style="width:12px;height:12px"></i> সদস্য নির্বাচন করলে মাসিক জমার পরিমাণ স্বয়ংক্রিয়ভাবে বসবে';
        return;
    }
    
    fetch(`deposits.php?ajax=member&id=${memberId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('memberInfo').style.display = 'block';
                document.getElementById('memberPhoto').src = data.photo;
                document.getElementById('displayName').textContent = data.member.name;
                document.getElementById('displayMemberId').textContent = data.member.member_id;
                document.getElementById('displayMonthlyDeposit').textContent = formatCurrency(data.monthly_deposit);
                document.getElementById('displayTotalDeposit').textContent = formatCurrency(data.total_deposit);
                document.getElementById('displayTotalWithdrawal').textContent = formatCurrency(data.total_withdrawal);
                document.getElementById('displayBalance').textContent = formatCurrency(data.balance);
              
                monthlyDeposit = data.monthly_deposit || 500;
                const amountField = document.getElementById('depositAmount');
                amountField.value = monthlyDeposit;
                
                pendingFineAmount = data.pending_fine_amount || 0;
                pendingFineCount = data.pending_fine_count || 0;
                
                if (pendingFineAmount > 0) {
                    document.getElementById('pendingFineInfo').style.display = 'block';
                    document.getElementById('pendingFineAmount').textContent = formatCurrency(pendingFineAmount);
                    document.getElementById('pendingFineCount').textContent = pendingFineCount + ' টি ফাইন';
                    document.getElementById('totalAmountDisplay').style.display = 'block';
                    updateTotalAmount();
                    document.getElementById('amountHelper').innerHTML = 
                        '<i data-feather="info" style="width:12px;height:12px;color:#E67E22;"></i> ' +
                        'মাসিক জমা: <strong>' + formatCurrency(monthlyDeposit) + '</strong> + ' +
                        'ফাইন: <strong style="color:#E74C3C;">' + formatCurrency(pendingFineAmount) + '</strong> ' +
                        '= মোট: <strong style="color:#2E86C1;">' + formatCurrency(monthlyDeposit + pendingFineAmount) + '</strong>';
                } else {
                    document.getElementById('pendingFineInfo').style.display = 'none';
                    document.getElementById('totalAmountDisplay').style.display = 'none';
                    document.getElementById('amountHelper').innerHTML = 
                        '<i data-feather="check-circle" style="width:12px;height:12px;color:#27AE60;"></i> ' +
                        'মাসিক জমা: <strong>' + formatCurrency(monthlyDeposit) + '</strong> (কোনো ফাইন নেই)';
                }
            } else {
                alert(data.message || 'সদস্য ডেটা লোড করতে ব্যর্থ হয়েছে');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('সদস্য ডেটা লোড করতে ব্যর্থ হয়েছে');
        });
}

function updateTotalAmount() {
    const depositAmount = parseFloat(document.getElementById('depositAmount').value) || 0;
    const totalAmount = depositAmount + pendingFineAmount;
    
    if (pendingFineAmount > 0) {
        document.getElementById('totalAmountDisplay').style.display = 'block';
        document.getElementById('totalDepositOnly').textContent = formatCurrency(depositAmount);
        document.getElementById('totalFineOnly').textContent = formatCurrency(pendingFineAmount);
        document.getElementById('totalAmountText').textContent = formatCurrency(totalAmount);
        document.getElementById('amountHelper').innerHTML = 
            '<i data-feather="info" style="width:12px;height:12px;color:#E67E22;"></i> ' +
            'মাসিক জমা: <strong>' + formatCurrency(depositAmount) + '</strong> + ' +
            'ফাইন: <strong style="color:#E74C3C;">' + formatCurrency(pendingFineAmount) + '</strong> ' +
            '= মোট: <strong style="color:#2E86C1;">' + formatCurrency(totalAmount) + '</strong>';
    } else {
        document.getElementById('totalAmountDisplay').style.display = 'none';
        document.getElementById('amountHelper').innerHTML = 
            '<i data-feather="check-circle" style="width:12px;height:12px;color:#27AE60;"></i> ' +
            'মাসিক জমা: <strong>' + formatCurrency(depositAmount) + '</strong> (কোনো ফাইন নেই)';
    }
}

function formatCurrency(amount) {
    return '৳ ' + parseFloat(amount).toFixed(2);
}

function printReceipt(receiptNo, memberName, amount, date, type) {
    let typeLabel = '';
    let isNegative = false;
    
    if (type === 'withdrawal') {
        typeLabel = 'উত্তোলন';
        isNegative = true;
    } else if (type === 'expense') {
        typeLabel = 'খরচ';
        isNegative = true;
    } else {
        const typeLabels = {
            'regular': 'নিয়মিত',
            'special': 'বিশেষ',
            'share': 'শেয়ার',
            'fd': 'এফডি',
            'fine': 'ফাইন'
        };
        typeLabel = typeLabels[type] || type.charAt(0).toUpperCase() + type.slice(1);
        isNegative = false;
    }
    
    const displayAmount = Math.abs(parseFloat(amount));
    
    const receiptHTML = `
        <div class="receipt-container">
            <div class="receipt-header">
                <h5>${isNegative ? 'উত্তোলন/খরচ রসিদ' : 'জমার রসিদ'}</h5>
                <small>রসিদ নং: ${receiptNo}</small>
            </div>
            <div class="receipt-body">
                <div class="receipt-row">
                    <span><strong>সদস্য:</strong></span>
                    <span>${memberName}</span>
                </div>
                <div class="receipt-row">
                    <span><strong>পরিমাণ:</strong></span>
                    <span style="color: ${isNegative ? '#E74C3C' : '#27AE60'}">
                        ${isNegative ? '-' : ''} ${formatCurrency(displayAmount)}
                    </span>
                </div>
                <div class="receipt-row">
                    <span><strong>ধরন:</strong></span>
                    <span>${typeLabel}</span>
                </div>
                <div class="receipt-row">
                    <span><strong>তারিখ:</strong></span>
                    <span>${date}</span>
                </div>
            </div>
            <div class="receipt-footer">
                <small>ধন্যবাদ</small>
            </div>
        </div>
    `;
    
    document.getElementById('receiptContent').innerHTML = receiptHTML;
    var myModal = new bootstrap.Modal(document.getElementById('receiptModal'));
    myModal.show();
}

function printReceiptContent() {
    var content = document.getElementById('receiptContent').innerHTML;
    var win = window.open('', '', 'width=400,height=500');
    win.document.write(`
        <html>
            <head>
                <title>জমার রসিদ</title>
                <style>
                    body { font-family: 'Courier New', monospace; font-size: 12px; padding: 20px; }
                    .receipt-container { max-width: 350px; margin: 0 auto; }
                    .receipt-header { text-align: center; border-bottom: 2px dashed #333; padding-bottom: 8px; margin-bottom: 8px; }
                    .receipt-header h5 { margin: 0; font-weight: bold; }
                    .receipt-body { padding: 5px 0; }
                    .receipt-row { display: flex; justify-content: space-between; padding: 3px 0; border-bottom: 1px dotted #ddd; }
                    .receipt-footer { text-align: center; border-top: 2px dashed #333; padding-top: 8px; margin-top: 8px; }
                    @media print { body { margin: 0; padding: 10px; } }
                </style>
            </head>
            <body>
                ${content}
                <script>
                    window.onload = function() { window.print(); window.close(); }
                <\/script>
            </body>
        </html>
    `);
    win.document.close();
}

$(document).ready(function() {
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
    
    $('#memberSelect').select2({
        placeholder: '-- সদস্য নির্বাচন করুন --',
        allowClear: true,
        width: '100%',
        dropdownCssClass: 'select2-dropdown-custom',
        language: {
            searching: function() { return 'খুঁজছেন...'; },
            noResults: function() { return 'কোনো সদস্য পাওয়া যায়নি'; },
            inputTooShort: function() { return 'অনুগ্রহ করে আরও অক্ষর টাইপ করুন'; }
        },
        templateResult: function(data) {
            if (!data.id) return data.text;
            var $result = $(
                '<div><strong>' + $(data.element).data('name') + '</strong><br>' +
                '<small>ID: ' + $(data.element).data('member-id') + ' | ফোন: ' + $(data.element).data('phone') + '</small></div>'
            );
            return $result;
        },
        templateSelection: function(data) {
            if (!data.id) return data.text;
            return $(data.element).data('name') + ' (' + $(data.element).data('member-id') + ')';
        }
    });
    
    $('#editMemberSelect').select2({
        placeholder: '-- সদস্য নির্বাচন করুন --',
        allowClear: true,
        width: '100%',
        dropdownCssClass: 'select2-dropdown-custom',
        language: {
            searching: function() { return 'খুঁজছেন...'; },
            noResults: function() { return 'কোনো সদস্য পাওয়া যায়নি'; },
            inputTooShort: function() { return 'অনুগ্রহ করে আরও অক্ষর টাইপ করুন'; }
        },
        templateResult: function(data) {
            if (!data.id) return data.text;
            var $result = $(
                '<div><strong>' + $(data.element).data('name') + '</strong><br>' +
                '<small>ID: ' + $(data.element).data('member-id') + ' | ফোন: ' + $(data.element).data('phone') + '</small></div>'
            );
            return $result;
        },
        templateSelection: function(data) {
            if (!data.id) return data.text;
            return $(data.element).data('name') + ' (' + $(data.element).data('member-id') + ')';
        }
    });
    
    <?php if ($action === 'edit' && $deposit): ?>
    $('#editMemberSelect').val(<?= $deposit['member_id'] ?>).trigger('change');
    <?php endif; ?>
    
    $('#memberSelect').on('change', function() {
        loadMemberData(this.value);
    });
    
    $('#depositAmount').on('input', function() {
        updateTotalAmount();
    });
    
    $('.form-select-sm').on('change', function() {
        this.closest('form').submit();
    });
    
    // Row click handler
    $('.clickable-row').on('click', function(e) {
        if ($(e.target).closest('.btn, .btn-group, a').length) {
            return;
        }
        var id = $(this).data('id');
        if (id) {
            window.location.href = 'deposits.php?action=view&id=' + id;
        }
    });
    
    <?php if ($member_id_filter > 0): ?>
    loadMemberData(<?= $member_id_filter ?>);
    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>