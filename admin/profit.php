<?php
// Add error reporting at the very top for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
requireAdmin();

$pageTitle = 'লাভ-ক্ষতি হিসাব';
$action    = $_GET['action'] ?? 'list';
$id        = (int)($_GET['id'] ?? 0);

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'নিরাপত্তা যাচাই ব্যর্থ।');
        header('Location: profit.php');
        exit;
    }
    
    $postAction = $_POST['action'] ?? '';

    // Calculate & Save P&L
    if ($postAction === 'calculate') {
        $fiscalYear = trim($_POST['fiscal_year'] ?? '');
        $reservePct = (float)($_POST['reserve_percentage'] ?? 10);
        $note = trim($_POST['note'] ?? '');

        if (!preg_match('/^\d{4}-\d{4}$/', $fiscalYear)) {
            setFlash('danger', 'আর্থিক বছর সঠিক ফরম্যাটে দিন (যেমন: 2023-2024)');
            header('Location: profit.php');
            exit;
        }

        $fy = explode('-', $fiscalYear);
        $startYear = (int)$fy[0];
        $endYear = (int)$fy[1];

        if ($startYear < 2000 || $endYear > 2100 || $endYear <= $startYear) {
            setFlash('danger', 'আর্থিক বছর সঠিক নয়।');
            header('Location: profit.php');
            exit;
        }

        $conn = conn();

        // Get all calculations
        $deposits = 0;
        $duesCollected = 0;
        $loanInterest = 0;
        $investReturn = 0;
        $expenses = 0;
        $loansGiven = 0;

        // Total deposits
        $result = $conn->query("SELECT COALESCE(SUM(amount),0) AS t FROM deposits WHERE YEAR(deposit_date) BETWEEN $startYear AND $endYear");
        if ($result) {
            $row = $result->fetch_assoc();
            $deposits = (float)$row['t'];
        }

        // Dues collected
        $result = $conn->query("SELECT COALESCE(SUM(paid_amount),0) AS t FROM monthly_dues WHERE due_year BETWEEN $startYear AND $endYear AND status IN ('paid','partial')");
        if ($result) {
            $row = $result->fetch_assoc();
            $duesCollected = (float)$row['t'];
        }

        // Loan interest
        $result = $conn->query("SELECT COALESCE(SUM(interest_amount),0) AS t FROM loan_payments WHERE status='paid' AND YEAR(paid_date) BETWEEN $startYear AND $endYear");
        if ($result) {
            $row = $result->fetch_assoc();
            $loanInterest = (float)$row['t'];
        }

        // Investment returns
        $result = $conn->query("SELECT COALESCE(SUM(return_amount),0) AS t FROM investment_returns WHERE YEAR(return_date) BETWEEN $startYear AND $endYear");
        if ($result) {
            $row = $result->fetch_assoc();
            $investReturn = (float)$row['t'];
        }

        // Expenses
        $result = $conn->query("SELECT COALESCE(SUM(amount),0) AS t FROM expenses WHERE YEAR(expense_date) BETWEEN $startYear AND $endYear");
        if ($result) {
            $row = $result->fetch_assoc();
            $expenses = (float)$row['t'];
        }

        // Loans given
        $result = $conn->query("SELECT COALESCE(SUM(principal_amount),0) AS t FROM loans WHERE YEAR(loan_date) BETWEEN $startYear AND $endYear AND status != 'rejected'");
        if ($result) {
            $row = $result->fetch_assoc();
            $loansGiven = (float)$row['t'];
        }

        // Calculate
        $totalIncome = $duesCollected + $loanInterest + $investReturn;
        $netProfit = $totalIncome - $expenses;
        $reserve = $netProfit * ($reservePct / 100);
        $distributable = $netProfit - $reserve;

        // Check if record exists
        $checkStmt = $conn->prepare("SELECT id FROM profit_loss WHERE fiscal_year = ?");
        $checkStmt->bind_param('s', $fiscalYear);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $exists = $checkResult->num_rows > 0;
        $checkStmt->close();

        if ($exists) {
            // Update existing
            $stmt = $conn->prepare("UPDATE profit_loss SET 
                total_deposits = ?,
                total_dues_collected = ?,
                total_loan_interest = ?,
                total_investment_return = ?,
                total_income = ?,
                total_expenses = ?,
                total_loan_given = ?,
                net_profit = ?,
                distributable_profit = ?,
                reserve_amount = ?,
                reserve_percentage = ?,
                note = ?,
                status = 'draft'
                WHERE fiscal_year = ?");
            
            $stmt->bind_param(
                'dddddddddddss',
                $deposits,
                $duesCollected,
                $loanInterest,
                $investReturn,
                $totalIncome,
                $expenses,
                $loansGiven,
                $netProfit,
                $distributable,
                $reserve,
                $reservePct,
                $note,
                $fiscalYear
            );
        } else {
            // Insert new
            $stmt = $conn->prepare("INSERT INTO profit_loss (
                fiscal_year, total_deposits, total_dues_collected, total_loan_interest,
                total_investment_return, total_income, total_expenses, total_loan_given,
                net_profit, distributable_profit, reserve_amount, reserve_percentage, note, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')");

            $stmt->bind_param(
                'sddddddddddds',
                $fiscalYear,
                $deposits,
                $duesCollected,
                $loanInterest,
                $investReturn,
                $totalIncome,
                $expenses,
                $loansGiven,
                $netProfit,
                $distributable,
                $reserve,
                $reservePct,
                $note
            );
        }

        if ($stmt->execute()) {
            logActivity('profit_calc', "P&L calculated for $fiscalYear");
            setFlash('success', "{$fiscalYear} এর লাভ-ক্ষতি হিসাব তৈরি হয়েছে। নিট লাভ: " . formatCurrency($netProfit));
        } else {
            setFlash('danger', 'হিসাব সংরক্ষণ ব্যর্থ: ' . $stmt->error);
        }
        $stmt->close();
        header('Location: profit.php');
        exit;
    }

    // Finalize P&L
    if ($postAction === 'finalize') {
        $plId = (int)$_POST['pl_id'];
        if ($plId <= 0) {
            setFlash('danger', 'অবৈধ রেকর্ড আইডি।');
            header('Location: profit.php');
            exit;
        }

        $by = (int)$_SESSION['admin_id'];
        $conn = conn();

        $stmt = $conn->prepare("UPDATE profit_loss SET status='final', finalized_by=?, finalized_at=NOW() WHERE id=? AND status='draft'");
        $stmt->bind_param('ii', $by, $plId);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            logActivity('profit_finalize', "P&L finalized for ID: $plId");
            setFlash('success', 'লাভ-ক্ষতি হিসাব চূড়ান্ত করা হয়েছে।');
        } else {
            setFlash('danger', 'হিসাব চূড়ান্ত করতে ব্যর্থ বা ইতিমধ্যে চূড়ান্ত করা হয়েছে।');
        }
        $stmt->close();
        header("Location: profit.php?action=view&id={$plId}");
        exit;
    }

    // Delete P&L - FIXED VERSION
    if ($postAction === 'delete') {
        $plId = (int)$_POST['pl_id'];
        if ($plId <= 0) {
            setFlash('danger', 'অবৈধ রেকর্ড আইডি।');
            header('Location: profit.php');
            exit;
        }

        $conn = conn();
        
        try {
            // Get record info first
            $stmt = $conn->prepare("SELECT fiscal_year, status FROM profit_loss WHERE id = ?");
            $stmt->bind_param('i', $plId);
            $stmt->execute();
            $result = $stmt->get_result();
            $pl = $result->fetch_assoc();
            $stmt->close();

            if (!$pl) {
                setFlash('danger', 'রেকর্ড পাওয়া যায়নি।');
                header('Location: profit.php');
                exit;
            }
            
            if ($pl['status'] === 'final') {
                setFlash('warning', 'চূড়ান্ত করা রেকর্ড মুছে ফেলা যায় না।');
                header('Location: profit.php');
                exit;
            }
            
            // Check if profit_distributions table exists and has the right column
            $tableCheck = $conn->query("SHOW TABLES LIKE 'profit_distributions'");
            $hasDistributionsTable = $tableCheck->num_rows > 0;
            
            $hasDistributions = false;
            if ($hasDistributionsTable) {
                // First, check what columns exist in profit_distributions
                $columns = $conn->query("SHOW COLUMNS FROM profit_distributions");
                $columnNames = [];
                while ($col = $columns->fetch_assoc()) {
                    $columnNames[] = $col['Field'];
                }
                
                // Check if 'pl_id' or 'profit_loss_id' exists
                if (in_array('pl_id', $columnNames)) {
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM profit_distributions WHERE pl_id = ?");
                    $stmt->bind_param('i', $plId);
                } elseif (in_array('profit_loss_id', $columnNames)) {
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM profit_distributions WHERE profit_loss_id = ?");
                    $stmt->bind_param('i', $plId);
                } else {
                    // No matching column found, assume no distributions
                    $hasDistributions = false;
                    $stmt = null;
                }
                
                if ($stmt) {
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $count = $result->fetch_assoc()['count'];
                    $stmt->close();
                    $hasDistributions = $count > 0;
                }
            }

            if ($hasDistributions) {
                setFlash('warning', 'এই রেকর্ডের সাথে লাভ বণ্টন সংযুক্ত আছে। প্রথমে বণ্টন মুছুন।');
            } else {
                // Simple delete query
                $deleteSql = "DELETE FROM profit_loss WHERE id = ? AND status = 'draft'";
                $stmt = $conn->prepare($deleteSql);
                if (!$stmt) {
                    setFlash('danger', 'ডেটাবেস ত্রুটি: ' . $conn->error);
                    header('Location: profit.php');
                    exit;
                }
                
                $stmt->bind_param('i', $plId);
                
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        logActivity('profit_delete', "P&L deleted for ID: $plId, Fiscal Year: {$pl['fiscal_year']}");
                        setFlash('success', "{$pl['fiscal_year']} এর লাভ-ক্ষতি হিসাব মুছে ফেলা হয়েছে।");
                    } else {
                        setFlash('warning', 'রেকর্ডটি ইতিমধ্যে মুছে ফেলা হয়েছে বা মুছা যায়নি।');
                    }
                } else {
                    setFlash('danger', 'হিসাব মুছতে ব্যর্থ: ' . $stmt->error);
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            setFlash('danger', 'ত্রুটি: ' . $e->getMessage());
        }
        
        header('Location: profit.php');
        exit;
    }
}

// View single record
$pl = null;
if ($action === 'view' && $id > 0) {
    $conn = conn();
    $stmt = $conn->prepare("SELECT * FROM profit_loss WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $pl = $result->fetch_assoc();
    $stmt->close();

    if (!$pl) {
        setFlash('danger', 'রেকর্ড পাওয়া যায়নি।');
        header('Location: profit.php');
        exit;
    }
    $pageTitle = $pl['fiscal_year'] . ' — লাভ-ক্ষতি';
}

// Get all records
$records = [];
$conn = conn();
$result = $conn->query("SELECT * FROM profit_loss ORDER BY fiscal_year DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row g-3">
    <!-- Calculate Form -->
    <div class="col-12 col-lg-4">
        <div class="card">
            <div class="card-header">
                <span class="card-title">
                    <i data-feather="calculator" style="width:15px;height:15px"></i> নতুন হিসাব
                </span>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="calculate">

                    <div class="mb-3">
                        <label class="form-label">আর্থিক বছর <span class="text-danger">*</span></label>
                        <input type="text" name="fiscal_year" class="form-control"
                               placeholder="2023-2024" pattern="\d{4}-\d{4}" required
                               value="<?= htmlspecialchars(date('Y') . '-' . (date('Y') + 1)) ?>">
                        <div class="form-text">YYYY-YYYY ফরম্যাটে দিন</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">রিজার্ভ (%)</label>
                        <div class="input-group">
                            <input type="number" name="reserve_percentage" class="form-control" value="10" min="0" max="50" step="0.5">
                            <span class="input-group-text">%</span>
                        </div>
                        <div class="form-text">মোট লাভের কত % রিজার্ভে রাখা হবে</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">মন্তব্য</label>
                        <textarea name="note" class="form-control" rows="2" maxlength="500"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i data-feather="refresh-cw" style="width:14px;height:14px" class="me-1"></i>
                        হিসাব তৈরি করুন
                    </button>
                </form>
            </div>
        </div>

        <!-- Records List -->
        <?php if (!empty($records)): ?>
        <div class="card mt-3">
            <div class="card-header"><span class="card-title">সংরক্ষিত হিসাব</span></div>
            <div class="list-group list-group-flush">
                <?php foreach ($records as $r): ?>
                <a href="?action=view&id=<?= (int)$r['id'] ?>"
                   class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2">
                    <div>
                        <div style="font-size:13px;font-weight:600"><?= htmlspecialchars($r['fiscal_year']) ?></div>
                        <div style="font-size:11px;color:#888"><?= $r['status'] === 'final' ? '✅ চূড়ান্ত' : '📝 খসড়া' ?></div>
                    </div>
                    <div class="text-end">
                        <div class="<?= (float)$r['net_profit'] >= 0 ? 'amount-positive' : 'amount-negative' ?>" style="font-size:13px">
                            <?= formatCurrency((float)$r['net_profit']) ?>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- View / Summary -->
    <div class="col-12 col-lg-8">
        <?php if ($pl): ?>
        <!-- Detailed View -->
        <div class="d-flex gap-2 mb-3 flex-wrap">
            <a href="profit.php" class="btn btn-outline-secondary btn-sm">← ফিরে যান</a>
            <a href="profit_distribution.php?pl_id=<?= (int)$pl['id'] ?>" class="btn btn-primary btn-sm">
                <i data-feather="share-2" style="width:13px;height:13px" class="me-1"></i>লাভ বণ্টন করুন
            </a>
            <?php if ($pl['status'] === 'draft'): ?>
            <form method="POST" style="display:inline" onsubmit="return confirm('লাভ-ক্ষতি চূড়ান্ত করলে আর পরিবর্তন করা যাবে না। নিশ্চিত?')">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="finalize">
                <input type="hidden" name="pl_id" value="<?= (int)$pl['id'] ?>">
                <button class="btn btn-success btn-sm">✅ চূড়ান্ত করুন</button>
            </form>

            <form method="POST" style="display:inline" onsubmit="return confirm('আপনি কি নিশ্চিত যে এই লাভ-ক্ষতি হিসাব মুছে ফেলতে চান?')">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="pl_id" value="<?= (int)$pl['id'] ?>">
                <button class="btn btn-danger btn-sm">
                    <i data-feather="trash-2" style="width:13px;height:13px" class="me-1"></i>মুছুন
                </button>
            </form>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header">
                <span class="card-title">
                    <?= htmlspecialchars($pl['fiscal_year']) ?> — লাভ-ক্ষতি বিবরণী
                    <span class="badge <?= $pl['status'] === 'final' ? 'bg-success' : 'bg-warning text-dark' ?> ms-2">
                        <?= $pl['status'] === 'final' ? 'চূড়ান্ত' : 'খসড়া' ?>
                    </span>
                </span>
            </div>
            <div class="card-body">
                <h6 class="text-success mb-2">▶ আয়</h6>
                <div class="table-responsive mb-3">
                    <table class="table table-sm">
                        <tbody>
                            <tr><td>মাসিক চাঁদা আদায়</td><td class="text-end amount-positive"><?= formatCurrency((float)$pl['total_dues_collected']) ?></td></tr>
                            <tr><td>ঋণের সুদ আদায়</td><td class="text-end amount-positive"><?= formatCurrency((float)$pl['total_loan_interest']) ?></td></tr>
                            <tr><td>বিনিয়োগ থেকে আয়</td><td class="text-end amount-positive"><?= formatCurrency((float)$pl['total_investment_return']) ?></td></tr>
                            <tr class="table-success fw-bold"><td>মোট আয়</td><td class="text-end"><?= formatCurrency((float)$pl['total_income']) ?></td></tr>
                        </tbody>
                    </table>
                </div>

                <h6 class="text-danger mb-2">▶ ব্যয়</h6>
                <div class="table-responsive mb-3">
                    <table class="table table-sm">
                        <tbody>
                            <tr><td>মোট পরিচালন ব্যয়</td><td class="text-end amount-negative"><?= formatCurrency((float)$pl['total_expenses']) ?></td></tr>
                            <tr class="table-danger fw-bold"><td>মোট ব্যয়</td><td class="text-end"><?= formatCurrency((float)$pl['total_expenses']) ?></td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="row g-2">
                    <div class="col-6">
                        <div class="stat-card <?= (float)$pl['net_profit'] >= 0 ? 'bg-success-grad' : 'bg-danger-grad' ?>" style="padding:16px">
                            <div class="stat-value" style="font-size:16px"><?= formatCurrency((float)$pl['net_profit']) ?></div>
                            <div class="stat-label" style="font-size:12px">নিট লাভ</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-card bg-warning-grad" style="padding:16px">
                            <div class="stat-value" style="font-size:16px"><?= formatCurrency((float)$pl['reserve_amount']) ?></div>
                            <div class="stat-label" style="font-size:12px">রিজার্ভ (<?= (float)$pl['reserve_percentage'] ?>%)</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-card bg-primary-grad" style="padding:16px">
                            <div class="stat-value" style="font-size:16px"><?= formatCurrency((float)$pl['distributable_profit']) ?></div>
                            <div class="stat-label" style="font-size:12px">বিতরণযোগ্য লাভ</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-card bg-teal-grad" style="padding:16px">
                            <div class="stat-value" style="font-size:16px"><?= formatCurrency((float)$pl['total_deposits']) ?></div>
                            <div class="stat-label" style="font-size:12px">মোট সঞ্চয়</div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($pl['note'])): ?>
                <div class="alert alert-light mt-3 mb-0" style="font-size:13px">
                    <strong>মন্তব্য:</strong> <?= htmlspecialchars($pl['note']) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php else: ?>
        <!-- Overview -->
        <div class="card">
            <div class="card-header"><span class="card-title"><i data-feather="pie-chart" style="width:16px;height:16px"></i> সারসংক্ষেপ</span></div>
            <div class="card-body">
                <?php if (empty($records)): ?>
                <div class="text-center py-5 text-muted">
                    <i data-feather="pie-chart" style="width:48px;height:48px;opacity:.3"></i>
                    <p class="mt-3">এখনো কোনো লাভ-ক্ষতি হিসাব তৈরি হয়নি।<br>বাম ফর্ম থেকে নতুন হিসাব তৈরি করুন।</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>আর্থিক বছর</th>
                                <th>মোট আয়</th>
                                <th>মোট ব্যয়</th>
                                <th>নিট লাভ</th>
                                <th>বিতরণযোগ্য</th>
                                <th>অবস্থা</th>
                                <th>অ্যাকশন</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $r): ?>
                            <tr>
                                <td style="font-weight:600"><?= htmlspecialchars($r['fiscal_year']) ?></td>
                                <td class="amount-positive"><?= formatCurrency((float)$r['total_income']) ?></td>
                                <td class="amount-negative"><?= formatCurrency((float)$r['total_expenses']) ?></td>
                                <td class="<?= (float)$r['net_profit'] >= 0 ? 'amount-positive' : 'amount-negative' ?> fw-bold"><?= formatCurrency((float)$r['net_profit']) ?></td>
                                <td class="amount-positive"><?= formatCurrency((float)$r['distributable_profit']) ?></td>
                                <td>
                                    <span class="badge <?= $r['status'] === 'final' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                        <?= $r['status'] === 'final' ? 'চূড়ান্ত' : 'খসড়া' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="?action=view&id=<?= (int)$r['id'] ?>" class="btn btn-outline-primary">
                                            <i data-feather="eye" style="width:13px;height:13px"></i>
                                        </a>
                                        <?php if ($r['status'] === 'draft'): ?>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('আপনি কি নিশ্চিত যে এই লাভ-ক্ষতি হিসাব মুছে ফেলতে চান?')">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="pl_id" value="<?= (int)$r['id'] ?>">
                                            <button class="btn btn-outline-danger" type="submit">
                                                <i data-feather="trash-2" style="width:13px;height:13px"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>