<?php
// =====================================================
// admin/loan_payments.php - Loan Installment Collection (FIXED)
// =====================================================

require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
requireAdmin();

$pageTitle = 'কিস্তি গ্রহণ';
$loanId    = (int)($_GET['loan_id'] ?? 0);
$error = '';
$success = '';

// ---- Handle POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('danger','নিরাপত্তা যাচাই ব্যর্থ।'); 
        header('Location: loan_payments.php'); 
        exit;
    }
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'collect') {
        $pmtId    = (int)$_POST['payment_id'];
        $paid     = (float)$_POST['paid_amount'];
        $fine     = (float)($_POST['late_fine'] ?? 0);
        $paidDate = $_POST['paid_date'] ?? date('Y-m-d');
        $note     = trim($_POST['note'] ?? '');
        $receipt  = generateReceiptNo('EMI');
        $by       = $_SESSION['admin_id'];

        if ($pmtId <= 0) {
            setFlash('danger','কিস্তি নির্বাচন করুন।');
            header('Location: loan_payments.php');
            exit;
        }

        if ($paid <= 0) {
            setFlash('danger','পরিমাণ দিন।');
            header('Location: loan_payments.php');
            exit;
        }

        $pmt = queryOne("SELECT lp.*, l.member_id, l.outstanding_balance, l.total_paid FROM loan_payments lp JOIN loans l ON l.id=lp.loan_id WHERE lp.id=?", 'i', [$pmtId]);
        if (!$pmt) { 
            setFlash('danger','কিস্তি পাওয়া যায়নি।'); 
            header('Location: loan_payments.php'); 
            exit; 
        }

        $totalPaid = (float)$pmt['paid_amount'] + $paid;
        $required  = (float)$pmt['total_due'] + $fine;
        $status    = $totalPaid >= $required ? 'paid' : 'partial';

        // Start transaction
        db()->beginTransaction();
        try {
            $conn = conn();
            $stmt = $conn->prepare(
                "UPDATE loan_payments SET paid_amount=?, paid_date=?, late_fine=?, status=?, receipt_no=?, collected_by=?, note=? WHERE id=?"
            );
            $stmt->bind_param('dsdssssi', $totalPaid, $paidDate, $fine, $status, $receipt, $by, $note, $pmtId);
            $stmt->execute();

            // Update loan outstanding
            $newPaid = (float)$pmt['total_paid'] + $paid;
            $newOutstanding = max(0, (float)$pmt['outstanding_balance'] - $paid);
            $lStatus = $newOutstanding <= 0 ? 'completed' : 'active';
            $conn->query("UPDATE loans SET total_paid=$newPaid, outstanding_balance=$newOutstanding, status='$lStatus' WHERE id={$pmt['loan_id']}");

            db()->commit();
            logActivity('loan_payment', "Collected ৳$paid for payment ID $pmtId, receipt $receipt");
            
            if ($lStatus === 'completed') {
                sendNotification('loan_installment','ঋণ পরিশোধ সম্পন্ন','আপনার ঋণ সম্পূর্ণ পরিশোধ হয়েছে।','member',$pmt['member_id']);
                setFlash('success', "✅ কিস্তি গ্রহণ সফল! রসিদ: $receipt — ঋণ সম্পূর্ণ পরিশোধ হয়েছে!");
            } else {
                setFlash('success', "✅ কিস্তি গ্রহণ সফল! রসিদ নং: $receipt");
            }
            
        } catch (Exception $e) {
            db()->rollback();
            setFlash('danger','কিস্তি গ্রহণে ত্রুটি: ' . $e->getMessage());
        }
        $redir = $loanId ? "loan_payments.php?loan_id=$loanId" : 'loan_payments.php';
        header("Location: $redir"); 
        exit;
    }
}

// ---- GET: Delete ----
if (isset($_GET['delete']) && $_GET['delete'] > 0) {
    $id = (int)$_GET['delete'];
    if (!verifyCSRF($_GET['csrf_token'] ?? '')) {
        setFlash('danger','নিরাপত্তা যাচাই ব্যর্থ।');
    } else {
        $conn = conn();
        $stmt = $conn->prepare("DELETE FROM loan_payments WHERE id=?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            setFlash('success','কিস্তি ডিলিট করা হয়েছে।');
            logActivity('loan_payment_delete', "কিস্তি ডিলিট ID: $id");
        } else {
            setFlash('danger','ডিলিট করতে ব্যর্থ হয়েছে।');
        }
    }
    header('Location: loan_payments.php');
    exit;
}

// ---- Load loan info if filtered ----
$loanInfo = null;
if ($loanId) {
    $loanInfo = queryOne("SELECT l.*, m.name AS mname, m.phone AS mphone, m.member_id AS mid
                          FROM loans l 
                          JOIN members m ON m.id = l.member_id 
                          WHERE l.id=?", 'i', [$loanId]);
}

// ---- Get all active loans for dropdown ----
$activeLoans = queryAll("
    SELECT l.id, l.loan_no, l.outstanding_balance, m.name AS mname, m.member_id AS mid
    FROM loans l
    JOIN members m ON m.id = l.member_id
    WHERE l.status = 'active'
    ORDER BY l.loan_no ASC
");

// ---- List: pending/overdue installments ----
$where = "lp.status IN ('pending','partial','overdue')";
$types = ''; 
$params = [];

if ($loanId) { 
    $where .= " AND lp.loan_id=?"; 
    $types .= 'i'; 
    $params[] = $loanId; 
}

$page = max(1, (int)($_GET['page'] ?? 1));

$sql = "SELECT lp.*, l.loan_no, l.member_id, l.interest_rate, m.name AS mname, m.phone AS mphone, m.member_id AS mid
        FROM loan_payments lp
        JOIN loans l ON l.id = lp.loan_id
        JOIN members m ON m.id = l.member_id
        WHERE $where 
        ORDER BY lp.due_date ASC";

// Include header
require_once __DIR__ . '/../includes/header.php';

// Show flash messages
$flash = getFlash();
if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show m-3" role="alert">
    <i data-feather="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-circle' ?>" 
       style="width:16px;height:16px;vertical-align:middle"></i>
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="container-fluid px-3">

<!-- ============================================
     LOAN FILTER
     ============================================ -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">ঋণ নির্বাচন করুন</label>
                <select name="loan_id" class="form-select" onchange="this.form.submit()">
                    <option value="">— সব ঋণ —</option>
                    <?php foreach ($activeLoans as $loan): ?>
                    <option value="<?= $loan['id'] ?>" <?= $loanId == $loan['id'] ? 'selected' : '' ?>>
                        <?= clean($loan['loan_no']) ?> — <?= clean($loan['mname']) ?> (<?= $loan['mid'] ?>) 
                        [বকেয়া: <?= formatCurrency($loan['outstanding_balance']) ?>]
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i data-feather="filter" style="width:14px;height:14px"></i> ফিল্টার
                </button>
            </div>
            <?php if ($loanId): ?>
            <div class="col-md-2">
                <a href="loan_payments.php" class="btn btn-outline-secondary w-100">
                    <i data-feather="x" style="width:14px;height:14px"></i> সাফ
                </a>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if ($loanInfo): ?>
<!-- Loan summary banner -->
<div class="alert alert-info d-flex flex-wrap align-items-center gap-3 mb-3 py-2">
    <i data-feather="briefcase" style="width:18px;height:18px"></i>
    <strong><?= $loanInfo['loan_no'] ?></strong>
    <span>— <?= clean($loanInfo['mname']) ?> (<?= $loanInfo['mid'] ?>)</span>
    <span class="ms-auto">বকেয়া: <strong class="text-danger"><?= formatCurrency((float)$loanInfo['outstanding_balance']) ?></strong></span>
    <a href="loans.php?action=view&id=<?= $loanId ?>" class="btn btn-sm btn-outline-primary">
        <i data-feather="eye" style="width:14px;height:14px"></i> বিস্তারিত
    </a>
</div>
<?php endif; ?>

<div class="row g-3">
    <!-- Quick Collect Panel -->
    <div class="col-12 col-lg-4">
        <div class="card">
            <div class="card-header">
                <span class="card-title">
                    <i data-feather="dollar-sign" style="width:15px;height:15px"></i> 
                    কিস্তি গ্রহণ
                </span>
            </div>
            <div class="card-body">
                <form method="POST" id="collectForm">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="collect">
                    <input type="hidden" name="payment_id" id="pmtId" value="0">

                    <!-- Selected Installment -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">
                            <i data-feather="check-circle" style="width:14px;height:14px"></i> 
                            নির্বাচিত কিস্তি
                        </label>
                        <div id="selectedInstallment" class="p-3 rounded" 
                             style="background: #f8f9fa; border: 2px dashed #dee2e6; min-height:80px; transition: all 0.3s;">
                            <div class="text-center text-muted" style="font-size:13px;">
                                <i data-feather="arrow-down" style="width:18px;height:18px;display:block;margin:0 auto 6px;"></i>
                                নিচের তালিকা থেকে কিস্তি নির্বাচন করুন ↓
                            </div>
                        </div>
                    </div>

                    <!-- Payment Details -->
                    <div class="mb-3">
                        <label class="form-label">
                            <i data-feather="credit-card" style="width:14px;height:14px"></i> 
                            প্রদত্ত পরিমাণ (৳) <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">৳</span>
                            <input type="number" name="paid_amount" id="paidAmt" 
                                   class="form-control" step="0.01" min="1" 
                                   placeholder="পরিমাণ দিন" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            <i data-feather="alert-triangle" style="width:14px;height:14px"></i> 
                            বিলম্ব জরিমানা (৳)
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">৳</span>
                            <input type="number" name="late_fine" id="lateFine" 
                                   class="form-control" step="0.01" value="0" min="0" 
                                   placeholder="০.০০" onchange="updateTotal()">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            <i data-feather="calendar" style="width:14px;height:14px"></i> 
                            প্রদানের তারিখ
                        </label>
                        <input type="date" name="paid_date" class="form-control" 
                               value="<?= date('Y-m-d') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            <i data-feather="message-circle" style="width:14px;height:14px"></i> 
                            মন্তব্য
                        </label>
                        <input type="text" name="note" class="form-control" 
                               placeholder="ঐচ্ছিক মন্তব্য লিখুন...">
                    </div>

                    <button type="submit" class="btn btn-success w-100" id="collectBtn" disabled>
                        <i data-feather="check-circle" style="width:14px;height:14px" class="me-1"></i>
                        কিস্তি গ্রহণ করুন
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Pending List -->
    <div class="col-12 col-lg-8">
        <div class="card">
            <div class="card-header">
                <span class="card-title">
                    <i data-feather="list" style="width:16px;height:16px"></i>
                    <?= $loanId ? 'এই ঋণের কিস্তি' : 'অপরিশোধিত কিস্তিসমূহ' ?>
                </span>
                <span class="badge bg-secondary">
                    <?php 
                    $paged = paginate($sql, $types, $params, $page); 
                    echo count($paged['data']) . ' টি';
                    ?>
                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ঋণ নং</th>
                            <th>সদস্য</th>
                            <th>কিস্তি</th>
                            <th>তারিখ</th>
                            <th>মোট</th>
                            <th>অবস্থা</th>
                            <th>নির্বাচন</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($paged['data'])): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <i data-feather="check-circle" style="width:32px;height:32px;display:block;margin:0 auto 10px;color:#27AE60;"></i>
                                ✅ কোনো বকেয়া কিস্তি নেই
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($paged['data'] as $p): ?>
                        <?php
                        $isOverdue = $p['due_date'] < date('Y-m-d') && $p['status'] !== 'paid';
                        $remaining = (float)$p['total_due'] - (float)$p['paid_amount'];
                        ?>
                        <tr class="<?= $isOverdue ? 'table-danger' : '' ?>">
                            <td><code style="font-size:11px"><?= $p['loan_no'] ?></code></td>
                            <td>
                                <div style="font-size:13px;font-weight:500"><?= clean($p['mname']) ?></div>
                                <div style="font-size:11px;color:#888"><?= $p['mphone'] ?></div>
                            </td>
                            <td style="font-size:13px">
                                #<?= $p['installment_no'] ?>
                                <?php if ((float)$p['paid_amount'] > 0): ?>
                                <span class="badge bg-warning text-dark" style="font-size:9px;">আংশিক</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:13px">
                                <?= formatDate($p['due_date'],'d M Y') ?>
                                <?php if ($isOverdue): ?>
                                <span class="badge status-unpaid" style="font-size:9px;">⏰ মেয়াদোত্তীর্ণ</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="amount-neutral fw-600"><?= formatCurrency((float)$p['total_due']) ?></div>
                                <?php if ((float)$p['paid_amount'] > 0): ?>
                                <div style="font-size:11px;color:#888">
                                    পরিশোধ: <?= formatCurrency((float)$p['paid_amount']) ?> 
                                    | বকেয়া: <?= formatCurrency($remaining) ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $sMap = [
                                    'pending' => ['⏳ অপেক্ষমাণ', 'status-pending'],
                                    'partial' => ['🔄 আংশিক', 'status-partial'],
                                    'overdue' => ['⛔ মেয়াদোত্তীর্ণ', 'status-unpaid']
                                ]; 
                                ?>
                                <span class="badge <?= $sMap[$p['status']][1] ?? 'status-pending' ?>">
                                    <?= $sMap[$p['status']][0] ?? $p['status'] ?>
                                </span>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary" 
                                    onclick="selectInstallment(<?= $p['id'] ?>, '<?= addslashes($p['loan_no']) ?>', '<?= addslashes($p['mname']) ?>', <?= $p['installment_no'] ?>, <?= $p['total_due'] ?>, <?= $p['paid_amount'] ?>, '<?= $p['due_date'] ?>')">
                                    <i data-feather="check" style="width:13px;height:13px"></i>
                                    <span class="d-none d-sm-inline">নির্বাচন</span>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-body pt-2">
                <?= renderPagination($paged, "loan_payments.php?loan_id=$loanId") ?>
            </div>
        </div>
    </div>
</div>
</div>

<style>
/* Selected installment styling */
#selectedInstallment.has-selection {
    background: #d5f5e3 !important;
    border: 2px solid #27ae60 !important;
}

.installment-info {
    font-size: 14px;
}
.installment-info .loan-detail {
    font-weight: 600;
    color: #1B4F72;
}
.installment-info .member-detail {
    color: #2C3E50;
}
.installment-info .amount-detail {
    font-weight: 600;
    color: #27AE60;
}
.installment-info .due-detail {
    color: #E74C3C;
    font-weight: 500;
}
</style>

<script>
// ============================================
// SELECT INSTALLMENT FUNCTION
// ============================================
function selectInstallment(id, loanNo, memberName, installmentNo, totalDue, paidSoFar, dueDate) {
    const remaining = totalDue - paidSoFar;
    
    // Set form values
    document.getElementById('pmtId').value = id;
    document.getElementById('paidAmt').value = remaining.toFixed(2);
    document.getElementById('collectBtn').disabled = false;
    
    // Update selected installment display
    const container = document.getElementById('selectedInstallment');
    container.className = 'p-3 rounded has-selection';
    
    const dueDateFormatted = new Date(dueDate).toLocaleDateString('bn-BD', {
        day: 'numeric',
        month: 'short',
        year: 'numeric'
    });
    
    container.innerHTML = `
        <div class="installment-info">
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-success">✅ নির্বাচিত</span>
                <span class="loan-detail">${loanNo}</span>
                <span class="text-muted">—</span>
                <span class="member-detail">${memberName}</span>
            </div>
            <div class="row g-1 mt-2">
                <div class="col-6">
                    <small class="text-muted">কিস্তি #${installmentNo}</small>
                    <div class="amount-detail">মোট: ৳${parseFloat(totalDue).toFixed(2)}</div>
                </div>
                <div class="col-6">
                    <small class="text-muted">নির্ধারিত তারিখ</small>
                    <div class="due-detail">${dueDateFormatted}</div>
                </div>
            </div>
            ${parseFloat(paidSoFar) > 0 ? `
            <div class="mt-2 p-2 bg-warning bg-opacity-10 rounded">
                <small class="text-muted">আগে পরিশোধ: ৳${parseFloat(paidSoFar).toFixed(2)}</small>
                <br>
                <small class="text-muted">বকেয়া: <strong class="text-danger">৳${parseFloat(remaining).toFixed(2)}</strong></small>
            </div>
            ` : `
            <div class="mt-2">
                <small class="text-muted">বকেয়া পরিমাণ: <strong class="text-danger">৳${parseFloat(remaining).toFixed(2)}</strong></small>
            </div>
            `}
            <div class="mt-2">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearSelection()">
                    <i data-feather="x" style="width:14px;height:14px"></i> বাতিল
                </button>
            </div>
        </div>
    `;
    
    // Scroll to form
    document.getElementById('collectForm').scrollIntoView({behavior:'smooth', block:'center'});
}

// ============================================
// CLEAR SELECTION FUNCTION
// ============================================
function clearSelection() {
    document.getElementById('pmtId').value = 0;
    document.getElementById('paidAmt').value = '';
    document.getElementById('collectBtn').disabled = true;
    
    const container = document.getElementById('selectedInstallment');
    container.className = 'p-3 rounded';
    container.innerHTML = `
        <div class="text-center text-muted" style="font-size:13px;">
            <i data-feather="arrow-down" style="width:18px;height:18px;display:block;margin:0 auto 6px;"></i>
            নিচের তালিকা থেকে কিস্তি নির্বাচন করুন ↓
        </div>
    `;
}
</script>

<?php
// Include footer
require_once __DIR__ . '/../includes/footer.php';
?>