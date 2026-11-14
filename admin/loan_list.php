<?php
// =====================================================
// admin/loan_list.php - Loan List (SIMPLE - NO PAGINATION)
// =====================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/functions.php';
startSecureSession();

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    header('Location: /admin/login.php');
    exit;
}

$pageTitle = 'ঋণের তালিকা';

// Get database connection
$conn = conn();

// ---- DELETE LOAN ----
if (isset($_GET['delete']) && $_GET['delete'] > 0) {
    $id = (int)$_GET['delete'];
    if (!verifyCSRF($_GET['csrf_token'] ?? '')) {
        setFlash('danger','নিরাপত্তা যাচাই ব্যর্থ।');
    } else {
        $conn->query("DELETE FROM loan_payments WHERE loan_id = $id");
        $conn->query("DELETE FROM loans WHERE id = $id");
        setFlash('success', "ঋণ ডিলিট করা হয়েছে।");
    }
    header('Location: loan_list.php');
    exit;
}

// ---- EDIT LOAN ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'edit') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('danger','নিরাপত্তা যাচাই ব্যর্থ।');
    } else {
        $loanId = (int)$_POST['loan_id'];
        $principal = (float)$_POST['principal_amount'];
        $interest_rate = (float)$_POST['interest_rate'];
        $tenure_months = (int)$_POST['tenure_months'];
        $status = $_POST['status'] ?? 'active';
        
        $stmt = $conn->prepare("
            UPDATE loans SET 
                principal_amount = ?,
                interest_rate = ?,
                tenure_months = ?,
                status = ?
            WHERE id = ?
        ");
        $stmt->bind_param('ddiss', $principal, $interest_rate, $tenure_months, $status, $loanId);
        $stmt->execute();
        setFlash('success', 'ঋণ আপডেট হয়েছে।');
    }
    header('Location: loan_list.php');
    exit;
}

// ---- GET LOAN FOR EDIT ----
$editLoan = null;
if (isset($_GET['edit']) && $_GET['edit'] > 0) {
    $id = (int)$_GET['edit'];
    $result = $conn->query("
        SELECT l.*, m.name AS member_name, m.member_id AS member_code 
        FROM loans l 
        LEFT JOIN members m ON m.id = l.member_id 
        WHERE l.id = $id
    ");
    $editLoan = $result->fetch_assoc();
}

// ---- GET ALL LOANS (SIMPLE) ----
$result = $conn->query("
    SELECT l.*, 
           m.name AS member_name, 
           m.member_id AS member_code,
           m.phone AS member_phone
    FROM loans l 
    LEFT JOIN members m ON m.id = l.member_id 
    ORDER BY l.created_at DESC
");

$loans = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $loans[] = $row;
    }
}

// Statistics
$total = count($loans);
$active = 0;
$pending = 0;
$completed = 0;
$defaulted = 0;
$total_amount = 0;
$total_paid = 0;

foreach ($loans as $l) {
    if ($l['status'] == 'active') $active++;
    elseif ($l['status'] == 'pending') $pending++;
    elseif ($l['status'] == 'completed') $completed++;
    elseif ($l['status'] == 'defaulted') $defaulted++;
    
    $total_amount += $l['principal_amount'];
    $total_paid += $l['total_paid'];
}
$total_outstanding = $total_amount - $total_paid;

// Include header
require_once __DIR__ . '/../includes/header.php';

// Flash messages
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

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4>
        <i data-feather="briefcase" style="width:20px;height:20px"></i> 
        ঋণের তালিকা
        <small class="text-muted">(<?= $total ?> টি ঋণ)</small>
    </h4>
    <div>
        <a href="loans.php?action=add" class="btn btn-primary btn-sm">
            <i data-feather="plus" style="width:14px;height:14px"></i> নতুন ঋণ
        </a>
        <a href="loan_list.php" class="btn btn-outline-secondary btn-sm">
            <i data-feather="refresh-cw" style="width:14px;height:14px"></i> রিফ্রেশ
        </a>
    </div>
</div>

<!-- Statistics -->
<div class="row g-2 mb-3">
    <div class="col-4 col-md-2">
        <div class="card p-2 text-center" style="background:#EBF5FB">
            <h4><?= $total ?></h4>
            <small>মোট ঋণ</small>
        </div>
    </div>
    <div class="col-4 col-md-2">
        <div class="card p-2 text-center" style="background:#EAFAF1">
            <h4><?= $active ?></h4>
            <small>✅ সক্রিয়</small>
        </div>
    </div>
    <div class="col-4 col-md-2">
        <div class="card p-2 text-center" style="background:#FEF9E7">
            <h4><?= $pending ?></h4>
            <small>⏳ অপেক্ষমাণ</small>
        </div>
    </div>
    <div class="col-4 col-md-2">
        <div class="card p-2 text-center" style="background:#E8F8F5">
            <h4><?= $completed ?></h4>
            <small>✅ সম্পন্ন</small>
        </div>
    </div>
    <div class="col-4 col-md-2">
        <div class="card p-2 text-center" style="background:#FDEDEC">
            <h4><?= $defaulted ?></h4>
            <small>⛔ খেলাপি</small>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<?php if ($editLoan): ?>
<div class="modal fade show" style="display:block;background:rgba(0,0,0,0.5);">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5>ঋণ সম্পাদনা — <?= $editLoan['loan_no'] ?></h5>
                <a href="loan_list.php" class="btn-close"></a>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="loan_id" value="<?= $editLoan['id'] ?>">
                    <?= csrfField() ?>
                    
                    <div class="mb-3">
                        <label>সদস্য</label>
                        <input type="text" class="form-control" value="<?= $editLoan['member_name'] ?>" disabled>
                    </div>
                    <div class="mb-3">
                        <label>মূল পরিমাণ (৳)</label>
                        <input type="number" name="principal_amount" class="form-control" value="<?= $editLoan['principal_amount'] ?>" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label>সুদের হার (%)</label>
                        <input type="number" name="interest_rate" class="form-control" value="<?= $editLoan['interest_rate'] ?>" step="0.1">
                    </div>
                    <div class="mb-3">
                        <label>মেয়াদ (মাস)</label>
                        <input type="number" name="tenure_months" class="form-control" value="<?= $editLoan['tenure_months'] ?>" required>
                    </div>
                    <div class="mb-3">
                        <label>অবস্থা</label>
                        <select name="status" class="form-select">
                            <option value="active" <?= $editLoan['status'] == 'active' ? 'selected' : '' ?>>সক্রিয়</option>
                            <option value="pending" <?= $editLoan['status'] == 'pending' ? 'selected' : '' ?>>অপেক্ষমাণ</option>
                            <option value="completed" <?= $editLoan['status'] == 'completed' ? 'selected' : '' ?>>সম্পন্ন</option>
                            <option value="defaulted" <?= $editLoan['status'] == 'defaulted' ? 'selected' : '' ?>>খেলাপি</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="loan_list.php" class="btn btn-secondary">বাতিল</a>
                    <button type="submit" class="btn btn-primary">আপডেট</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Loan Table -->
<div class="card">
    <div class="card-header">
        <span>ঋণের তালিকা</span>
        <span class="badge bg-secondary"><?= count($loans) ?> টি</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>#</th>
                    <th>সদস্য</th>
                    <th>ঋণ নং</th>
                    <th>পরিমাণ</th>
                    <th>পরিশোধ</th>
                    <th>বকেয়া</th>
                    <th>অবস্থা</th>
                    <th>কার্যক্রম</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($loans)): ?>
                <tr>
                    <td colspan="8" class="text-center py-4">
                        <i data-feather="inbox" style="width:32px;height:32px;display:block;margin:0 auto 10px"></i>
                        কোনো ঋণ নেই। <a href="loans.php?action=add" class="btn btn-sm btn-primary mt-2">প্রথম ঋণ তৈরি করুন</a>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($loans as $i => $loan): 
                    $outstanding = $loan['principal_amount'] - $loan['total_paid'];
                ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td>
                        <div>
                            <strong><?= $loan['member_name'] ?></strong>
                            <br><small class="text-muted"><?= $loan['member_code'] ?></small>
                        </div>
                    </td>
                    <td><code><?= $loan['loan_no'] ?></code></td>
                    <td>৳<?= number_format($loan['principal_amount'], 2) ?></td>
                    <td class="text-success">৳<?= number_format($loan['total_paid'], 2) ?></td>
                    <td class="<?= $outstanding > 0 ? 'text-danger' : 'text-success' ?>">
                        ৳<?= number_format($outstanding, 2) ?>
                    </td>
                    <td>
                        <?php
                        $statusBadge = [
                            'active' => 'bg-success',
                            'pending' => 'bg-warning',
                            'completed' => 'bg-info',
                            'defaulted' => 'bg-danger'
                        ];
                        ?>
                        <span class="badge <?= $statusBadge[$loan['status']] ?? 'bg-secondary' ?>">
                            <?= ucfirst($loan['status']) ?>
                        </span>
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <a href="?edit=<?= $loan['id'] ?>" class="btn btn-outline-primary">
                                <i data-feather="edit-2" style="width:14px;height:14px"></i>
                            </a>
                            <a href="?delete=<?= $loan['id'] ?>&csrf_token=<?= generateCSRF() ?>" 
                               class="btn btn-outline-danger" 
                               onclick="return confirm('ডিলিট করবেন?')">
                                <i data-feather="trash-2" style="width:14px;height:14px"></i>
                            </a>
                            <a href="loans.php?action=view&id=<?= $loan['id'] ?>" class="btn btn-outline-secondary">
                                <i data-feather="eye" style="width:14px;height:14px"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($loans)): ?>
            <tfoot>
                <tr style="font-weight:bold;background:#f8f9fa">
                    <td colspan="3" class="text-end">সর্বমোট:</td>
                    <td>৳<?= number_format($total_amount, 2) ?></td>
                    <td class="text-success">৳<?= number_format($total_paid, 2) ?></td>
                    <td class="text-danger">৳<?= number_format($total_outstanding, 2) ?></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>