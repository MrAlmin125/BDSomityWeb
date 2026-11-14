<?php
// =====================================================
// admin/investments.php (ক্লোজ/পেমেন্ট/ফেরত সিস্টেম)
// =====================================================
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
requireAdmin();
$pageTitle = 'বিনিয়োগ ব্যবস্থাপনা';
$action    = $_GET['action'] ?? 'list';
$id        = (int)($_GET['id'] ?? 0);
$filter    = $_GET['filter'] ?? 'all';

// =============================================
// DELETE
// =============================================
if ($action === 'delete' && $id > 0) {
    if (!verifyCSRF($_GET['csrf_token'] ?? '')) { setFlash('danger','নিরাপত্তা যাচাই ব্যর্থ।'); header('Location: investments.php'); exit; }
    
    conn()->query("DELETE FROM investment_returns WHERE investment_id = $id");
    $inv = queryOne("SELECT title FROM investments WHERE id = ?", 'i', [$id]);
    if (conn()->query("DELETE FROM investments WHERE id = $id")) {
        logActivity('investment_delete', "Deleted investment: {$inv['title']}");
        setFlash('success', "বিনিয়োগ ডিলিট করা হয়েছে: {$inv['title']}");
    } else {
        setFlash('danger', 'বিনিয়োগ ডিলিট ব্যর্থ।');
    }
    header('Location: investments.php'); exit;
}

// =============================================
// EDIT - ডেটা লোড
// =============================================
$editData = null;
if ($action === 'edit' && $id > 0) {
    $editData = queryOne("SELECT * FROM investments WHERE id = ?", 'i', [$id]);
    if (!$editData) {
        setFlash('danger', 'বিনিয়োগ পাওয়া যায়নি।');
        header('Location: investments.php'); exit;
    }
}

// =============================================
// CLOSE INVESTMENT (ম্যানুয়ালি ক্লোজ)
// =============================================
if ($action === 'close' && $id > 0) {
    if (!verifyCSRF($_GET['csrf_token'] ?? '')) { setFlash('danger','নিরাপত্তা যাচাই ব্যর্থ।'); header('Location: investments.php'); exit; }
    
    $inv = queryOne("SELECT * FROM investments WHERE id = ?", 'i', [$id]);
    if ($inv) {
        $totalReturn = queryOne("SELECT COALESCE(SUM(return_amount),0) AS t FROM investment_returns WHERE investment_id=?",'i',[$id])['t'];
        
        $expectedTotal = $inv['amount'] + $inv['expected_profit'];
        if ($totalReturn < $expectedTotal) {
            $remaining = $expectedTotal - $totalReturn;
            $stmt = conn()->prepare("INSERT INTO investment_returns (investment_id,return_amount,return_date,note,created_by) VALUES (?,?,?,?,?)");
            $note = "অটো ক্লোজ - বাকি টাকা: ".formatCurrency($remaining);
            $stmt->bind_param('idssi', $id, $remaining, date('Y-m-d'), $note, $_SESSION['admin_id']);
            $stmt->execute();
        }
        
        conn()->query("UPDATE investments SET status='completed', actual_return_date='".date('Y-m-d')."' WHERE id=$id");
        logActivity('investment_close', "Closed investment: {$inv['title']}");
        setFlash('success', "বিনিয়োগ ক্লোজ করা হয়েছে!<br>মোট ফেরত: ".formatCurrency($totalReturn + ($remaining ?? 0)));
    }
    header('Location: investments.php?filter=closed'); exit;
}

// =============================================
// MARK AS PAID
// =============================================
if ($action === 'paid' && $id > 0) {
    if (!verifyCSRF($_GET['csrf_token'] ?? '')) { setFlash('danger','নিরাপত্তা যাচাই ব্যর্থ।'); header('Location: investments.php'); exit; }
    
    $inv = queryOne("SELECT * FROM investments WHERE id = ?", 'i', [$id]);
    if ($inv) {
        conn()->query("UPDATE investments SET status='paid' WHERE id=$id");
        logActivity('investment_paid', "Marked as paid: {$inv['title']}");
        setFlash('success', "বিনিয়োগ পেইড মার্ক করা হয়েছে: {$inv['title']}");
    }
    header('Location: investments.php'); exit;
}

// =============================================
// POST হ্যান্ডলিং
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { setFlash('danger','নিরাপত্তা যাচাই ব্যর্থ।'); header('Location: investments.php'); exit; }
    $pa = $_POST['action'] ?? '';

    if ($pa === 'add') {
        $title     = trim($_POST['title'] ?? '');
        $type      = $_POST['investment_type'] ?? 'business';
        $amount    = (float)$_POST['amount'];
        $date      = $_POST['investment_date'] ?? date('Y-m-d');
        $retDate   = $_POST['expected_return_date'] ?? null;
        $desc      = trim($_POST['description'] ?? '');
        $by        = $_SESSION['admin_id'];
        $status    = $_POST['status'] ?? 'active';
        
        $profitType = $_POST['profit_type'] ?? 'percentage';
        $expProfit = 0;
        $profitPercent = 0;
        
        if ($profitType === 'percentage') {
            $profitPercent = (float)($_POST['profit_percent'] ?? 0);
            $expProfit = ($amount * $profitPercent) / 100;
        } else {
            $expProfit = (float)($_POST['profit_fixed'] ?? 0);
            $profitPercent = $amount > 0 ? ($expProfit / $amount) * 100 : 0;
        }

        if (!$title || $amount <= 0) { 
            setFlash('danger','শিরোনাম ও পরিমাণ আবশ্যক।'); 
        } else {
            $stmt = conn()->prepare("INSERT INTO investments (title,description,investment_type,amount,investment_date,expected_return_date,expected_profit,status,created_by) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param('sssdssdsi',$title,$desc,$type,$amount,$date,$retDate,$expProfit,$status,$by);
            if ($stmt->execute()) { 
                logActivity('investment_add',"Added investment: $title"); 
                setFlash('success',"<strong>বিনিয়োগ যোগ হয়েছে!</strong><br>📌 মূলধন: ".formatCurrency($amount)."<br>📈 মুনাফা: ".formatCurrency($expProfit)." ({$profitPercent}%)<br>💰 মোট ফেরত: ".formatCurrency($amount + $expProfit));
                header('Location: investments.php'); exit; 
            } else {
                setFlash('danger','বিনিয়োগ যোগ ব্যর্থ।');
            }
        }
    }

    if ($pa === 'edit') {
        $editId    = (int)$_POST['id'];
        $title     = trim($_POST['title'] ?? '');
        $type      = $_POST['investment_type'] ?? 'business';
        $amount    = (float)$_POST['amount'];
        $date      = $_POST['investment_date'] ?? date('Y-m-d');
        $retDate   = $_POST['expected_return_date'] ?? null;
        $desc      = trim($_POST['description'] ?? '');
        $status    = $_POST['status'] ?? 'active';
        
        $profitType = $_POST['profit_type'] ?? 'percentage';
        $expProfit = 0;
        $profitPercent = 0;
        
        if ($profitType === 'percentage') {
            $profitPercent = (float)($_POST['profit_percent'] ?? 0);
            $expProfit = ($amount * $profitPercent) / 100;
        } else {
            $expProfit = (float)($_POST['profit_fixed'] ?? 0);
            $profitPercent = $amount > 0 ? ($expProfit / $amount) * 100 : 0;
        }

        if (!$title || $amount <= 0) { 
            setFlash('danger','শিরোনাম ও পরিমাণ আবশ্যক।'); 
        } else {
            $stmt = conn()->prepare("UPDATE investments SET title=?, description=?, investment_type=?, amount=?, investment_date=?, expected_return_date=?, expected_profit=?, status=? WHERE id=?");
            $stmt->bind_param('sssdssdsi',$title,$desc,$type,$amount,$date,$retDate,$expProfit,$status,$editId);
            if ($stmt->execute()) { 
                logActivity('investment_edit',"Edited investment: $title"); 
                setFlash('success',"<strong>বিনিয়োগ আপডেট হয়েছে!</strong><br>📌 মূলধন: ".formatCurrency($amount)."<br>📈 মুনাফা: ".formatCurrency($expProfit)." ({$profitPercent}%)<br>💰 মোট ফেরত: ".formatCurrency($amount + $expProfit));
                header('Location: investments.php'); exit; 
            } else {
                setFlash('danger','বিনিয়োগ আপডেট ব্যর্থ।');
            }
        }
    }

    if ($pa === 'add_return') {
        $invId  = (int)$_POST['investment_id'];
        $retAmt = (float)$_POST['return_amount'];
        $retDt  = $_POST['return_date'] ?? date('Y-m-d');
        $note   = trim($_POST['note'] ?? '');
        $by     = $_SESSION['admin_id'];

        $invCheck = queryOne("SELECT status FROM investments WHERE id=?", 'i', [$invId]);
        if ($invCheck && ($invCheck['status'] == 'completed' || $invCheck['status'] == 'paid')) {
            setFlash('warning', 'এই বিনিয়োগ ইতিমধ্যে ক্লোজ বা পেইড করা হয়েছে!');
            header("Location: investments.php"); exit;
        }

        $stmt = conn()->prepare("INSERT INTO investment_returns (investment_id,return_amount,return_date,note,created_by) VALUES (?,?,?,?,?)");
        $stmt->bind_param('idssi',$invId,$retAmt,$retDt,$note,$by);
        if ($stmt->execute()) {
            $totalReturn = queryOne("SELECT COALESCE(SUM(return_amount),0) AS t FROM investment_returns WHERE investment_id=?",'i',[$invId])['t'];
            $inv = queryOne("SELECT amount, expected_profit FROM investments WHERE id=?",'i',[$invId]);
            if ($inv) {
                $expectedTotal = $inv['amount'] + $inv['expected_profit'];
                
                if ((float)$totalReturn >= (float)$expectedTotal) {
                    conn()->query("UPDATE investments SET status='completed', actual_return_date='$retDt' WHERE id=$invId");
                    setFlash('success', "🎉 বিনিয়োগ সম্পূর্ণ ফেরত হয়েছে!<br>মোট ফেরত: ".formatCurrency($totalReturn));
                } elseif ((float)$totalReturn >= (float)$inv['amount']) {
                    conn()->query("UPDATE investments SET status='partial' WHERE id=$invId");
                    setFlash('info', "আংশিক ফেরত!<br>মোট ফেরত: ".formatCurrency($totalReturn));
                } else {
                    setFlash('success', "আয় জমা হয়েছে!<br>মোট ফেরত: ".formatCurrency($totalReturn));
                }
            }
        } else {
            setFlash('danger','আয় জমা ব্যর্থ।');
        }
        header("Location: investments.php"); exit;
    }
}

// =============================================
// ডেটা কোয়েরি
// =============================================
$filterCondition = "";
if ($filter === 'running') {
    $filterCondition = "WHERE i.status IN ('active', 'partial')";
} elseif ($filter === 'closed') {
    $filterCondition = "WHERE i.status = 'completed'";
} elseif ($filter === 'paid') {
    $filterCondition = "WHERE i.status = 'paid'";
}

$investments = queryAll("
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
    $filterCondition
    ORDER BY i.investment_date DESC
");

$summaryData = queryOne("
    SELECT 
        COUNT(*) AS total_investments,
        COALESCE(SUM(amount), 0) AS total_amount,
        COALESCE(SUM(expected_profit), 0) AS total_expected_profit,
        COALESCE(SUM(r.total_return), 0) AS total_return
    FROM investments i
    LEFT JOIN (
        SELECT investment_id, SUM(return_amount) AS total_return 
        FROM investment_returns 
        GROUP BY investment_id
    ) r ON r.investment_id = i.id
", '');

$currentProfit = $summaryData['total_return'] - $summaryData['total_amount'];
$totalExpectedReturn = $summaryData['total_amount'] + $summaryData['total_expected_profit'];
$returnPercentage = $summaryData['total_amount'] > 0 
    ? ($summaryData['total_return'] / $summaryData['total_amount']) * 100 
    : 0;

$runningCount = queryOne("SELECT COUNT(*) AS count FROM investments WHERE status IN ('active', 'partial')", '')['count'];
$closedCount = queryOne("SELECT COUNT(*) AS count FROM investments WHERE status = 'completed'", '')['count'];
$paidCount = queryOne("SELECT COUNT(*) AS count FROM investments WHERE status = 'paid'", '')['count'];

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.bg-gradient-green { background: linear-gradient(135deg, #28a745, #20c997); }
.bg-gradient-red { background: linear-gradient(135deg, #dc3545, #e74c3c); }
.bg-gradient-blue { background: linear-gradient(135deg, #0d6efd, #0dcaf0); }
.bg-gradient-purple { background: linear-gradient(135deg, #6f42c1, #a855f7); }
.bg-gradient-orange { background: linear-gradient(135deg, #fd7e14, #ffc107); }
.card-hover { transition: transform 0.2s ease, box-shadow 0.2s ease; }
.card-hover:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
.stat-icon { opacity: 0.3; position: absolute; right: 15px; bottom: 15px; font-size: 48px; }
.progress { height: 8px; border-radius: 10px; }
.amount-positive { color: #28a745; font-weight: 600; }
.amount-negative { color: #dc3545; font-weight: 600; }
.amount-neutral { color: #6c757d; font-weight: 500; }
.calc-box { background: #f8f9fa; border-radius: 8px; padding: 15px; border-left: 4px solid #0d6efd; transition: all 0.3s ease; }
.calc-box .calc-label { font-size: 12px; color: #6c757d; margin-bottom: 2px; }
.calc-box .calc-value { font-size: 18px; font-weight: 600; }
.filter-tabs .nav-link { color: #6c757d; font-weight: 500; }
.filter-tabs .nav-link.active { color: #0d6efd; background: none; border-bottom: 3px solid #0d6efd; }
.filter-tabs .nav-link:hover { color: #0d6efd; }
</style>

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-2">
        <div class="card bg-primary text-white card-hover position-relative">
            <div class="card-body">
                <h6 class="card-title text-white-50 mb-1">মোট বিনিয়োগ</h6>
                <h2 class="mb-0 fw-bold"><?= $summaryData['total_investments'] ?></h2>
                <small>টি প্রকল্প</small>
                <div class="stat-icon"><i data-feather="briefcase"></i></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-2">
        <div class="card bg-success text-white card-hover position-relative">
            <div class="card-body">
                <h6 class="card-title text-white-50 mb-1">মোট বিনিয়োগের টাকা</h6>
                <h5 class="mb-0 fw-bold"><?= formatCurrency($summaryData['total_amount']) ?></h5>
                <small>মূলধন</small>
                <div class="stat-icon"><i data-feather="dollar-sign"></i></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-2">
        <div class="card bg-gradient-blue text-white card-hover position-relative">
            <div class="card-body">
                <h6 class="card-title text-white-50 mb-1">প্রত্যাশিত মোট ফেরত</h6>
                <h5 class="mb-0 fw-bold"><?= formatCurrency($totalExpectedReturn) ?></h5>
                <small><?= number_format($returnPercentage, 1) ?>% রিটার্ন</small>
                <div class="stat-icon"><i data-feather="trending-up"></i></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-2">
        <div class="card bg-warning text-dark card-hover position-relative">
            <div class="card-body">
                <h6 class="card-title text-dark-50 mb-1">ফেরত পাওয়া</h6>
                <h5 class="mb-0 fw-bold"><?= formatCurrency($summaryData['total_return']) ?></h5>
                <small><?= number_format($returnPercentage, 1) ?>% ফেরত</small>
                <div class="stat-icon"><i data-feather="corner-down-left"></i></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-2">
        <div class="card <?= $currentProfit >= 0 ? 'bg-gradient-green' : 'bg-gradient-red' ?> text-white card-hover position-relative">
            <div class="card-body">
                <h6 class="card-title text-white-50 mb-1">বর্তমান মুনাফা</h6>
                <h5 class="mb-0 fw-bold"><?= formatCurrency($currentProfit) ?></h5>
                <small><?= $currentProfit >= 0 ? '📈 লাভ' : '📉 লোকসান' ?></small>
                <div class="stat-icon"><i data-feather="<?= $currentProfit >= 0 ? 'smile' : 'frown' ?>"></i></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-2">
        <div class="card bg-gradient-purple text-white card-hover position-relative">
            <div class="card-body">
                <h6 class="card-title text-white-50 mb-1">ROI</h6>
                <h2 class="mb-0 fw-bold <?= ($returnPercentage - 100) >= 0 ? '' : 'text-danger' ?>">
                    <?= number_format($returnPercentage - 100, 1) ?>%
                </h2>
                <small>বিনিয়োগের উপর রিটার্ন</small>
                <div class="stat-icon"><i data-feather="percent"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Tabs -->
<ul class="nav filter-tabs mb-3" style="border-bottom: 2px solid #e9ecef;">
    <li class="nav-item">
        <a class="nav-link <?= $filter == 'all' ? 'active' : '' ?>" href="?filter=all">
            <i data-feather="list" style="width:14px;height:14px"></i> সব (<?= $summaryData['total_investments'] ?>)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $filter == 'running' ? 'active' : '' ?>" href="?filter=running">
            <i data-feather="play-circle" style="width:14px;height:14px"></i> চলমান (<?= $runningCount ?>)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $filter == 'closed' ? 'active' : '' ?>" href="?filter=closed">
            <i data-feather="check-circle" style="width:14px;height:14px"></i> ক্লোজ (<?= $closedCount ?>)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $filter == 'paid' ? 'active' : '' ?>" href="?filter=paid">
            <i data-feather="credit-card" style="width:14px;height:14px"></i> পেইড (<?= $paidCount ?>)
        </a>
    </li>
</ul>

<!-- Main Content -->
<div class="row g-3">
    <div class="col-12 col-lg-4">
        <div class="card">
            <div class="card-header">
                <span class="card-title">
                    <i data-feather="<?= $editData ? 'edit-2' : 'plus-circle' ?>" style="width:15px;height:15px"></i> 
                    <?= $editData ? 'বিনিয়োগ সম্পাদনা' : 'নতুন বিনিয়োগ' ?>
                </span>
                <?php if ($editData): ?>
                <a href="?filter=<?= $filter ?>" class="btn btn-sm btn-outline-secondary float-end">নতুন</a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form method="POST" id="investmentForm">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="<?= $editData ? 'edit' : 'add' ?>">
                    <?php if ($editData): ?>
                    <input type="hidden" name="id" value="<?= $editData['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label class="form-label">শিরোনাম <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required placeholder="বিনিয়োগের নাম" value="<?= $editData ? clean($editData['title']) : '' ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">ধরন</label>
                        <select name="investment_type" class="form-select">
                            <option value="business" <?= $editData && $editData['investment_type'] == 'business' ? 'selected' : '' ?>>ব্যবসা</option>
                            <option value="land" <?= $editData && $editData['investment_type'] == 'land' ? 'selected' : '' ?>>জমি/সম্পদ</option>
                            <option value="fd" <?= $editData && $editData['investment_type'] == 'fd' ? 'selected' : '' ?>>মেয়াদি আমানত</option>
                            <option value="other" <?= $editData && $editData['investment_type'] == 'other' ? 'selected' : '' ?>>অন্যান্য</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">বিনিয়োগের পরিমাণ (৳) <span class="text-danger">*</span></label>
                        <input type="number" name="amount" class="form-control" step="0.01" min="1" required id="investmentAmount" oninput="calculateProfit()" placeholder="যেমন: 5000" value="<?= $editData ? $editData['amount'] : '' ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">মুনাফার ধরন</label>
                        <select name="profit_type" class="form-select" id="profitType" onchange="toggleProfitInput(); calculateProfit();">
                            <option value="percentage" <?= $editData && $editData['expected_profit'] > 0 ? '' : 'selected' ?>>পারসেন্টেজ (%)</option>
                            <option value="fixed" <?= $editData && $editData['expected_profit'] > 0 ? 'selected' : '' ?>>ফিক্সড (টাকায়)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3 <?= $editData && $editData['expected_profit'] > 0 ? 'd-none' : '' ?>" id="percentageDiv">
                        <label class="form-label">মুনাফার হার (%) <span class="text-danger">*</span></label>
                        <input type="number" name="profit_percent" class="form-control" step="0.01" min="0" id="profitPercent" oninput="calculateProfit()" placeholder="যেমন: 10" value="<?= $editData && $editData['amount'] > 0 ? number_format(($editData['expected_profit'] / $editData['amount']) * 100, 2) : '' ?>">
                        <small class="text-muted">বিনিয়োগের উপর শতকরা হার</small>
                    </div>
                    
                    <div class="mb-3 <?= $editData && $editData['expected_profit'] > 0 ? '' : 'd-none' ?>" id="fixedDiv">
                        <label class="form-label">মুনাফা (৳) <span class="text-danger">*</span></label>
                        <input type="number" name="profit_fixed" class="form-control" step="0.01" min="0" id="profitFixed" oninput="calculateProfit()" placeholder="যেমন: 500" value="<?= $editData ? $editData['expected_profit'] : '' ?>">
                        <small class="text-muted">নির্দিষ্ট টাকার পরিমাণ</small>
                    </div>
                    
                    <div class="calc-box mb-3" id="profitPreview">
                        <div class="row">
                            <div class="col-6">
                                <div class="calc-label">📈 মুনাফা</div>
                                <div class="calc-value" id="profitAmount">৳<?= $editData ? number_format($editData['expected_profit'], 2) : '0.00' ?></div>
                            </div>
                            <div class="col-6">
                                <div class="calc-label">💰 মোট ফেরত</div>
                                <div class="calc-value" id="totalReturnAmount">৳<?= $editData ? number_format($editData['amount'] + $editData['expected_profit'], 2) : '0.00' ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">অবস্থা</label>
                        <select name="status" class="form-select">
                            <option value="active" <?= $editData && $editData['status'] == 'active' ? 'selected' : '' ?>>সক্রিয়</option>
                            <option value="partial" <?= $editData && $editData['status'] == 'partial' ? 'selected' : '' ?>>আংশিক</option>
                            <option value="completed" <?= $editData && $editData['status'] == 'completed' ? 'selected' : '' ?>>সম্পন্ন</option>
                            <option value="paid" <?= $editData && $editData['status'] == 'paid' ? 'selected' : '' ?>>পেইড</option>
                            <option value="failed" <?= $editData && $editData['status'] == 'failed' ? 'selected' : '' ?>>ব্যর্থ</option>
                        </select>
                    </div>
                    
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">তারিখ</label>
                            <input type="date" name="investment_date" class="form-control" value="<?= $editData ? $editData['investment_date'] : date('Y-m-d') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">প্রত্যাশিত ফেরতের তারিখ</label>
                            <input type="date" name="expected_return_date" class="form-control" value="<?= $editData ? $editData['expected_return_date'] : '' ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">বিবরণ</label>
                        <textarea name="description" class="form-control" rows="2"><?= $editData ? clean($editData['description']) : '' ?></textarea>
                    </div>
                    
                    <button class="btn btn-<?= $editData ? 'warning' : 'primary' ?> w-100">
                        <i data-feather="<?= $editData ? 'save' : 'plus-circle' ?>" style="width:16px;height:16px"></i> 
                        <?= $editData ? 'আপডেট করুন' : 'বিনিয়োগ যোগ করুন' ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-8">
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i data-feather="trending-up" style="width:16px;height:16px"></i> বিনিয়োগ তালিকা</span>
                <span class="badge bg-primary float-end"><?= count($investments) ?> টি</span>
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
                            <th width="160">অ্যাকশন</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($investments as $inv): 
                            $profit = (float)$inv['expected_profit'];
                            $totalExpected = (float)$inv['amount'] + $profit;
                            $returned = (float)$inv['total_return'];
                            $remaining = $totalExpected - $returned;
                            $progress = $totalExpected > 0 ? ($returned / $totalExpected) * 100 : 0;
                            $profitPercent = $inv['profit_percent'] ?? 0;
                            
                            $statusColors = [
                                'active' => 'primary',
                                'partial' => 'warning',
                                'completed' => 'success',
                                'paid' => 'info',
                                'failed' => 'danger'
                            ];
                            $statusLabels = [
                                'active' => 'সক্রিয়',
                                'partial' => 'আংশিক',
                                'completed' => 'সম্পন্ন',
                                'paid' => 'পেইড',
                                'failed' => 'ব্যর্থ'
                            ];
                        ?>
                        <tr>
                            <td>
                                <div style="font-size:14px;font-weight:500"><?= clean($inv['title']) ?></div>
                                <div class="progress">
                                    <div class="progress-bar <?= $progress >= 100 ? 'bg-success' : 'bg-info' ?>" 
                                         style="width: <?= min(100, $progress) ?>%"></div>
                                </div>
                                <small class="text-muted"><?= number_format($progress, 1) ?>% সম্পন্ন</small>
                            </td>
                            <td><span class="badge bg-light text-dark"><?= $inv['investment_type'] ?></span></td>
                            <td class="amount-neutral"><?= formatCurrency((float)$inv['amount']) ?></td>
                            <td>
                                <div class="amount-positive"><?= formatCurrency($profit) ?></div>
                                <small class="text-muted">(<?= number_format($profitPercent, 1) ?>%)</small>
                            </td>
                            <td>
                                <strong class="text-warning"><?= formatCurrency($totalExpected) ?></strong>
                                <br><small class="text-muted">মূল + মুনাফা</small>
                            </td>
                            <td>
                                <div> <strong class="text-success"><?= formatCurrency($returned) ?> </strong> </div>
                                <?php if ($remaining > 0): ?>
                                <small class="text-danger fw-bold">বাকি:  <span style="font-size: 15px;"><?= formatCurrency($remaining) ?></span> </small>
                                <?php else: ?>
                                <small class="text-success">✅ সম্পূর্ণ</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= $statusColors[$inv['status']] ?? 'secondary' ?>">
                                    <?= $statusLabels[$inv['status']] ?? $inv['status'] ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="?action=edit&id=<?= $inv['id'] ?>&filter=<?= $filter ?>" class="btn btn-outline-primary" title="এডিট">
                                        <i data-feather="edit-2" style="width:13px;height:13px"></i>
                                    </a>
                                    
                                    <?php if ($inv['status'] != 'completed' && $inv['status'] != 'paid'): ?>
                                    <button type="button" class="btn btn-outline-success" onclick="showReturnForm(<?= $inv['id'] ?>, '<?= addslashes(clean($inv['title'])) ?>', <?= $totalExpected ?>)" title="আয় জমা">
                                        <i data-feather="plus" style="width:13px;height:13px"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($inv['status'] != 'completed' && $inv['status'] != 'paid'): ?>
                                    <a href="?action=close&id=<?= $inv['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" 
                                       class="btn btn-outline-warning" 
                                       title="ক্লোজ করুন"
                                       onclick="return confirm('আপনি কি এই বিনিয়োগ ক্লোজ করতে চান? বাকি টাকা অটো অ্যাড হবে।');">
                                        <i data-feather="lock" style="width:13px;height:13px"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($inv['status'] == 'active' || $inv['status'] == 'partial'): ?>
                                    <a href="?action=paid&id=<?= $inv['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" 
                                       class="btn btn-outline-info" 
                                       title="পেইড মার্ক"
                                       onclick="return confirm('আপনি কি এই বিনিয়োগ পেইড মার্ক করতে চান?');">
                                        <i data-feather="credit-card" style="width:13px;height:13px"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <a href="?action=delete&id=<?= $inv['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" 
                                       class="btn btn-outline-danger" 
                                       title="ডিলিট"
                                       onclick="return confirm('আপনি কি এই বিনিয়োগ ডিলিট করতে চান?');">
                                        <i data-feather="trash-2" style="width:13px;height:13px"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($investments)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">
                            <?php if ($filter == 'running'): ?>
                            কোনো চলমান বিনিয়োগ নেই
                            <?php elseif ($filter == 'closed'): ?>
                            কোনো ক্লোজ বিনিয়োগ নেই
                            <?php elseif ($filter == 'paid'): ?>
                            কোনো পেইড বিনিয়োগ নেই
                            <?php else: ?>
                            কোনো বিনিয়োগ নেই
                            <?php endif; ?>
                        </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ============================================= -->
<!-- Return Modal -->
<!-- ============================================= -->
<div class="modal fade" id="returnModal" tabindex="-1" aria-labelledby="returnModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title" id="returnModalLabel">
                    <i data-feather="plus-circle" style="width:16px;height:16px"></i> বিনিয়োগ আয় জমা
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="returnForm">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="add_return">
                <input type="hidden" name="investment_id" id="returnInvId">
                <div class="modal-body">
                    <div class="text-center mb-2">
                        <span class="badge bg-primary" id="returnInvTitle" style="font-size:14px;">প্রকল্পের নাম</span>
                    </div>
                    
                    <div class="alert alert-info py-2 mb-3">
                        <small>প্রত্যাশিত মোট ফেরত: <strong id="expectedTotalDisplay">৳0.00</strong></small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">আয়ের পরিমাণ (৳)</label>
                        <div class="input-group">
                            <span class="input-group-text">৳</span>
                            <input type="number" name="return_amount" class="form-control" step="0.01" min="1" required placeholder="যেমন: 500" id="returnAmountInput">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">তারিখ</label>
                        <input type="date" name="return_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    
                    <div class="mb-2">
                        <label class="form-label">মন্তব্য (ঐচ্ছিক)</label>
                        <input type="text" name="note" class="form-control" placeholder="যেমন: মাসিক লভ্যাংশ">
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">বাতিল</button>
                    <button type="submit" class="btn btn-success btn-sm">
                        <i data-feather="check" style="width:14px;height:14px"></i> জমা করুন
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================= -->
<!-- JavaScript  -->
<!-- ============================================= -->
<script>
// =============================================
// MODAL FUNCTIONS -
// =============================================
function showReturnForm(id, title, expectedTotal) {
    try {
        // Check if Bootstrap is loaded
        if (typeof bootstrap === 'undefined') {
            alert('Bootstrap লোড হয়নি! অনুগ্রহ করে পৃষ্ঠা রিফ্রেশ করুন।');
            return;
        }
        
        // Get modal element
        var modalElement = document.getElementById('returnModal');
        if (!modalElement) {
            alert('মডাল এলিমেন্ট পাওয়া যায়নি!');
            return;
        }
        
        // Set values
        var invId = document.getElementById('returnInvId');
        var invTitle = document.getElementById('returnInvTitle');
        var expTotal = document.getElementById('expectedTotalDisplay');
        var amountInput = document.getElementById('returnAmountInput');
        var noteInput = document.querySelector('input[name="note"]');
        
        if (invId) invId.value = id;
        if (invTitle) invTitle.textContent = title;
        if (expTotal) expTotal.textContent = '৳' + parseFloat(expectedTotal).toFixed(2);
        if (amountInput) amountInput.value = '';
        if (noteInput) noteInput.value = '';
        
        // Create and show modal
        var modal = new bootstrap.Modal(modalElement, {
            backdrop: true,
            keyboard: true,
            focus: true
        });
        
        modal.show();
        
        // Focus on amount input after modal is shown
        setTimeout(function() {
            var input = document.getElementById('returnAmountInput');
            if (input) {
                input.focus();
            }
        }, 300);
        
    } catch (error) {
        console.error('Modal Error:', error);
        alert('মডাল খুলতে সমস্যা হচ্ছে। অনুগ্রহ করে পৃষ্ঠা রিফ্রেশ করে আবার চেষ্টা করুন।\nError: ' + error.message);
    }
}

// =============================================
// MODAL CLEANUP
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    var modalElement = document.getElementById('returnModal');
    if (modalElement) {
        modalElement.addEventListener('hidden.bs.modal', function() {
            // Reset form when modal is closed
            var invId = document.getElementById('returnInvId');
            var invTitle = document.getElementById('returnInvTitle');
            var expTotal = document.getElementById('expectedTotalDisplay');
            var amountInput = document.getElementById('returnAmountInput');
            var noteInput = document.querySelector('input[name="note"]');
            
            if (invId) invId.value = '';
            if (invTitle) invTitle.textContent = 'প্রকল্পের নাম';
            if (expTotal) expTotal.textContent = '৳0.00';
            if (amountInput) amountInput.value = '';
            if (noteInput) noteInput.value = '';
            
            // Remove any leftover backdrops
            var backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach(function(el) {
                el.remove();
            });
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        });
    }
});

// =============================================
// PROFIT CALCULATION
// =============================================
function toggleProfitInput() {
    var type = document.getElementById('profitType').value;
    var percentDiv = document.getElementById('percentageDiv');
    var fixedDiv = document.getElementById('fixedDiv');
    
    if (type === 'percentage') {
        if (percentDiv) percentDiv.classList.remove('d-none');
        if (fixedDiv) fixedDiv.classList.add('d-none');
    } else {
        if (percentDiv) percentDiv.classList.add('d-none');
        if (fixedDiv) fixedDiv.classList.remove('d-none');
    }
    calculateProfit();
}

function calculateProfit() {
    var amount = parseFloat(document.getElementById('investmentAmount').value) || 0;
    var type = document.getElementById('profitType').value;
    
    var profit = 0;
    var percent = 0;
    
    if (type === 'percentage') {
        percent = parseFloat(document.getElementById('profitPercent').value) || 0;
        profit = (amount * percent) / 100;
    } else {
        profit = parseFloat(document.getElementById('profitFixed').value) || 0;
        percent = amount > 0 ? (profit / amount) * 100 : 0;
    }
    
    var total = amount + profit;
    
    var profitAmount = document.getElementById('profitAmount');
    var totalReturnAmount = document.getElementById('totalReturnAmount');
    var previewBox = document.getElementById('profitPreview');
    
    if (profitAmount) profitAmount.innerHTML = '৳' + profit.toFixed(2);
    if (totalReturnAmount) totalReturnAmount.innerHTML = '৳' + total.toFixed(2);
    
    if (previewBox) {
        if (profit > 0) {
            previewBox.style.borderLeftColor = '#28a745';
        } else if (profit < 0) {
            previewBox.style.borderLeftColor = '#dc3545';
        } else {
            previewBox.style.borderLeftColor = '#0d6efd';
        }
    }
}

// =============================================
// FORM VALIDATIONS
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    // Investment Form
    var investmentForm = document.getElementById('investmentForm');
    if (investmentForm) {
        investmentForm.addEventListener('submit', function(e) {
            var amount = parseFloat(document.getElementById('investmentAmount').value) || 0;
            if (amount <= 0) {
                e.preventDefault();
                alert('দয়া করে বিনিয়োগের পরিমাণ দিন।');
                document.getElementById('investmentAmount').focus();
                return false;
            }
        });
    }
    
    // Return Form
    var returnForm = document.getElementById('returnForm');
    if (returnForm) {
        returnForm.addEventListener('submit', function(e) {
            var amount = parseFloat(document.getElementById('returnAmountInput').value) || 0;
            if (amount <= 0) {
                e.preventDefault();
                alert('দয়া করে আয়ের পরিমাণ দিন।');
                document.getElementById('returnAmountInput').focus();
                return false;
            }
        });
    }
    
    // Enter key support for return amount
    var returnInput = document.getElementById('returnAmountInput');
    if (returnInput) {
        returnInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                var submitBtn = document.querySelector('#returnForm button[type="submit"]');
                if (submitBtn) submitBtn.click();
            }
        });
    }
    
    // Initialize
    <?php if (!$editData): ?>
    var profitType = document.getElementById('profitType');
    if (profitType) profitType.value = 'percentage';
    <?php endif; ?>
    
    toggleProfitInput();
    calculateProfit();
    
    // Auto-hide alerts
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert:not(.alert-info)');
        alerts.forEach(function(el) {
            el.style.transition = 'opacity 0.5s';
            el.style.opacity = '0';
            setTimeout(function() {
                if (el && el.parentNode) el.remove();
            }, 500);
        });
    }, 5000);
    
    // Feather Icons
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
    
    // Clean any leftover modal backdrops
    var backdrops = document.querySelectorAll('.modal-backdrop');
    backdrops.forEach(function(el) {
        el.remove();
    });
    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>