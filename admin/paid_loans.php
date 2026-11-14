<?php
// =====================================================
// admin/paid_loans.php - Loan List by Member (Paid + Unpaid)
// =====================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
requireAdmin();

$pageTitle = 'ঋণের তালিকা';
$error = '';
$memberId = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$loanStatus = isset($_GET['loan_status']) ? $_GET['loan_status'] : 'all';

// Initialize variables
$memberList = [];
$memberLoans = [];
$selectedMember = null;

try {
    $conn = conn();
    
    // ============================================
    // 1. GET ALL MEMBERS WITH LOAN SUMMARY
    // ============================================
    $sql = "SELECT m.id, m.name, m.member_id, m.phone,
                   COUNT(l.id) as total_loans,
                   COALESCE(SUM(l.principal_amount), 0) as total_amount,
                   COALESCE(SUM(l.total_paid), 0) as total_paid,
                   MAX(l.updated_at) as last_updated,
                   SUM(CASE WHEN l.status = 'completed' THEN 1 ELSE 0 END) as paid_loans,
                   SUM(CASE WHEN l.status = 'active' THEN 1 ELSE 0 END) as active_loans,
                   SUM(CASE WHEN l.status = 'pending' THEN 1 ELSE 0 END) as pending_loans
            FROM members m
            INNER JOIN loans l ON l.member_id = m.id";
    
    $params = [];
    $types = "";
    
    // Filter by loan status
    if ($loanStatus === 'paid') {
        $sql .= " WHERE l.status = 'completed'";
    } elseif ($loanStatus === 'unpaid') {
        $sql .= " WHERE l.status IN ('active', 'pending', 'overdue')";
    } else {
        $sql .= " WHERE l.status IS NOT NULL";
    }
    
    if (!empty($searchTerm)) {
        if ($loanStatus === 'all') {
            $sql .= " AND (m.name LIKE ? OR m.member_id LIKE ? OR m.phone LIKE ?)";
        } else {
            $sql .= " AND (m.name LIKE ? OR m.member_id LIKE ? OR m.phone LIKE ?)";
        }
        $types .= 'sss';
        $like = "%$searchTerm%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    
    $sql .= " GROUP BY m.id ORDER BY last_updated DESC, m.name ASC";
    
    // Execute
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }
    
    while ($row = $result->fetch_assoc()) {
        $memberList[] = $row;
    }
    
    // ============================================
    // 2. GET ALL LOANS FOR SELECTED MEMBER
    // ============================================
    if ($memberId > 0) {
        // Get member info
        $stmt = $conn->prepare("SELECT id, name, member_id, phone, email, join_date, status FROM members WHERE id = ?");
        $stmt->bind_param('i', $memberId);
        $stmt->execute();
        $result = $stmt->get_result();
        $selectedMember = $result->fetch_assoc();
        
        if ($selectedMember) {
            // Get all loans for this member
            $stmt = $conn->prepare("
                SELECT l.*, 
                       (SELECT COUNT(*) FROM loan_payments WHERE loan_id = l.id) as total_installments,
                       (SELECT COUNT(*) FROM loan_payments WHERE loan_id = l.id AND status = 'paid') as paid_installments
                FROM loans l
                WHERE l.member_id = ?
                ORDER BY l.created_at DESC, l.id DESC
            ");
            $stmt->bind_param('i', $memberId);
            $stmt->execute();
            $result = $stmt->get_result();
            $memberLoans = $result->fetch_all(MYSQLI_ASSOC);
        }
    }
    
    // Get statistics
    $totalMembers = count($memberList);
    $totalLoans = 0;
    $paidCount = 0;
    $unpaidCount = 0;
    $totalAmount = 0;
    $totalPaid = 0;
    
    foreach ($memberList as $m) {
        $totalLoans += $m['total_loans'];
        $paidCount += $m['paid_loans'];
        $unpaidCount += ($m['active_loans'] + $m['pending_loans']);
        $totalAmount += $m['total_amount'];
        $totalPaid += $m['total_paid'];
    }
    
} catch (Exception $e) {
    $error = 'ডেটা লোড করতে ব্যর্থ হয়েছে: ' . $e->getMessage();
}

// Include header
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">
            <i data-feather="briefcase" style="width:20px;height:20px"></i> 
            ঋণের তালিকা
            <?php if ($selectedMember): ?>
            — <span style="color:#2E86C1"><?= clean($selectedMember['name']) ?></span>
            <?php endif; ?>
        </h4>
        <div>
            <?php if ($selectedMember): ?>
            <a href="paid_loans.php" class="btn btn-outline-secondary btn-sm">
                <i data-feather="arrow-left" style="width:14px;height:14px"></i> সব সদস্য
            </a>
            <?php endif; ?>
            <a href="loans.php" class="btn btn-info btn-sm text-white">
                <i data-feather="plus-circle" style="width:14px;height:14px"></i> নতুন ঋণ
            </a>
        </div>
    </div>

    <!-- Error Message -->
    <?php if ($error): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2">
        <i data-feather="alert-circle" style="width:16px;height:16px"></i>
        <span><?= htmlspecialchars($error) ?></span>
    </div>
    <?php endif; ?>

    <!-- ============================================
         STATISTICS CARDS
         ============================================ -->
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="stat-card bg-primary-grad" style="padding:14px;">
                <div class="stat-value" style="font-size:18px;"><?= toBengaliNumber($totalMembers) ?></div>
                <div class="stat-label" style="font-size:12px;">সদস্য</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card bg-success-grad" style="padding:14px;">
                <div class="stat-value" style="font-size:18px;"><?= toBengaliNumber($paidCount) ?></div>
                <div class="stat-label" style="font-size:12px;">✅ পরিশোধিত ঋণ</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card bg-danger-grad" style="padding:14px;">
                <div class="stat-value" style="font-size:18px;"><?= toBengaliNumber($unpaidCount) ?></div>
                <div class="stat-label" style="font-size:12px;">⛔ বকেয়া ঋণ</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card bg-warning-grad" style="padding:14px;">
                <div class="stat-value" style="font-size:18px;"><?= formatCurrency($totalAmount - $totalPaid) ?></div>
                <div class="stat-label" style="font-size:12px;">মোট বকেয়া</div>
            </div>
        </div>
    </div>

    <!-- ============================================
         MEMBER LIST (DEFAULT VIEW)
         ============================================ -->
    <?php if (!$selectedMember): ?>
    
    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <i data-feather="users" style="width:16px;height:16px"></i> 
                সদস্যদের ঋণের সারাংশ
            </span>
            <div class="d-flex align-items-center gap-2">
                <!-- Status Filter -->
                <form method="GET" class="d-flex gap-1">
                    <select name="loan_status" class="form-select form-select-sm" style="width:150px;" onchange="this.form.submit()">
                        <option value="all" <?= $loanStatus === 'all' ? 'selected' : '' ?>>সব ঋণ</option>
                        <option value="paid" <?= $loanStatus === 'paid' ? 'selected' : '' ?>>✅ পরিশোধিত</option>
                        <option value="unpaid" <?= $loanStatus === 'unpaid' ? 'selected' : '' ?>>⛔ বকেয়া</option>
                    </select>
                    
                    <div class="input-group" style="width:200px;">
                        <input type="text" name="search" class="form-control form-control-sm" 
                               placeholder="নাম, আইডি বা ফোন..." 
                               value="<?= htmlspecialchars($searchTerm) ?>"
                               style="border-radius: 6px 0 0 6px;">
                        <button type="submit" class="btn btn-primary btn-sm" 
                                style="border-radius: 0 6px 6px 0;">
                            <i data-feather="search" style="width:14px;height:14px;"></i>
                        </button>
                    </div>
                    <?php if (!empty($searchTerm) || $loanStatus !== 'all'): ?>
                    <a href="paid_loans.php" class="btn btn-outline-secondary btn-sm">
                        <i data-feather="x" style="width:14px;height:14px"></i>
                    </a>
                    <?php endif; ?>
                </form>
                <span class="badge bg-secondary"><?= count($memberList) ?> জন</span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover" id="memberTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>সদস্য</th>
                        <th>আইডি</th>
                        <th>ফোন</th>
                        <th>মোট ঋণ</th>
                        <th>পরিশোধিত</th>
                        <th>বকেয়া</th>
                        <th>অবস্থা</th>
                        <th>কার্যক্রম</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($memberList)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            <i data-feather="inbox" style="width:32px;height:32px;display:block;margin:0 auto 10px"></i>
                            <?php if (!empty($searchTerm)): ?>
                            "<strong><?= htmlspecialchars($searchTerm) ?></strong>" দিয়ে কোনো সদস্য পাওয়া যায়নি।
                            <br>
                            <a href="paid_loans.php" class="btn btn-sm btn-outline-primary mt-2">সব দেখুন</a>
                            <?php elseif ($loanStatus === 'paid'): ?>
                            কোনো পরিশোধিত ঋণ নেই।
                            <?php elseif ($loanStatus === 'unpaid'): ?>
                            কোনো বকেয়া ঋণ নেই। সব ঋণ পরিশোধিত হয়েছে! 🎉
                            <?php else: ?>
                            কোনো ঋণ নেই।
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($memberList as $index => $member): 
                        $outstanding = $member['total_amount'] - $member['total_paid'];
                        $status = $outstanding <= 0 ? 'completed' : 'active';
                    ?>
                    <tr class="clickable-row" data-href="paid_loans.php?member_id=<?= $member['id'] ?>" 
                        style="cursor:pointer;">
                        <td><?= $index + 1 ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="member-avatar" style="background:<?= getColor($member['name']) ?>">
                                    <?= mb_substr($member['name'], 0, 1) ?>
                                </div>
                                <a href="paid_loans.php?member_id=<?= $member['id'] ?>" 
                                   class="text-decoration-none fw-bold" style="color:#1B4F72">
                                    <?= clean($member['name']) ?>
                                </a>
                            </div>
                        </td>
                        <td><code><?= clean($member['member_id']) ?></code></td>
                        <td><?= clean($member['phone']) ?></td>
                        <td class="amount-neutral"><?= formatCurrency($member['total_amount']) ?></td>
                        <td class="amount-positive"><?= formatCurrency($member['total_paid']) ?></td>
                        <td class="<?= $outstanding > 0 ? 'amount-negative' : 'amount-positive' ?>">
                            <?= formatCurrency($outstanding) ?>
                        </td>
                        <td>
                            <?php if ($outstanding <= 0): ?>
                                <span class="badge bg-success">✅ পরিশোধিত</span>
                            <?php elseif ($member['active_loans'] > 0): ?>
                                <span class="badge bg-warning text-dark">⏳ চলমান</span>
                            <?php else: ?>
                                <span class="badge bg-danger">⛔ বকেয়া</span>
                            <?php endif; ?>
                            <br><small class="text-muted"><?= $member['total_loans'] ?> টি</small>
                        </td>
                        <td>
                            <a href="paid_loans.php?member_id=<?= $member['id'] ?>" 
                               class="btn btn-sm btn-outline-primary">
                                <i data-feather="eye" style="width:14px;height:14px"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================
         MEMBER LOANS LIST (MEMBER VIEW)
         ============================================ -->
    <?php if ($selectedMember): ?>
    
    <div class="row g-4">
        <!-- Member Info Card -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <div class="member-profile-avatar" style="background:<?= getColor($selectedMember['name']) ?>">
                        <?= mb_substr($selectedMember['name'], 0, 2) ?>
                    </div>
                    <h5 class="mt-3"><?= clean($selectedMember['name']) ?></h5>
                    <p class="text-muted small">
                        <code><?= clean($selectedMember['member_id']) ?></code>
                        <span class="badge <?= $selectedMember['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>">
                            <?= $selectedMember['status'] === 'active' ? '✅ সক্রিয়' : '⛔ নিষ্ক্রিয়' ?>
                        </span>
                    </p>
                    <div class="info-item">
                        <span class="info-label">ফোন</span>
                        <span class="info-value"><?= clean($selectedMember['phone']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">ইমেইল</span>
                        <span class="info-value"><?= clean($selectedMember['email'] ?? 'N/A') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">যোগদান</span>
                        <span class="info-value"><?= formatDate($selectedMember['join_date']) ?></span>
                    </div>
                    <hr>
                    <?php 
                    $totalLoan = array_sum(array_column($memberLoans, 'principal_amount'));
                    $totalPaid = array_sum(array_column($memberLoans, 'total_paid'));
                    $outstanding = $totalLoan - $totalPaid;
                    ?>
                    <div class="info-item">
                        <span class="info-label">মোট ঋণ</span>
                        <span class="info-value amount-neutral"><?= formatCurrency($totalLoan) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">পরিশোধিত</span>
                        <span class="info-value amount-positive"><?= formatCurrency($totalPaid) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">বকেয়া</span>
                        <span class="info-value <?= $outstanding > 0 ? 'amount-negative' : 'amount-positive' ?>">
                            <?= formatCurrency($outstanding) ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">মোট ঋণ</span>
                        <span class="info-value"><?= count($memberLoans) ?> টি</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loans List -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <span class="card-title">
                        <i data-feather="list" style="width:16px;height:16px"></i> 
                        ঋণের তালিকা
                    </span>
                    <span class="badge bg-secondary"><?= count($memberLoans) ?> টি</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ঋণ নং</th>
                                <th>পরিমাণ</th>
                                <th>পরিশোধ</th>
                                <th>বকেয়া</th>
                                <th>সুদের হার</th>
                                <th>অবস্থা</th>
                                <th>কার্যক্রম</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($memberLoans)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i data-feather="inbox" style="width:32px;height:32px;display:block;margin:0 auto 10px"></i>
                                    এই সদস্যের কোনো ঋণ নেই।
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($memberLoans as $loan): 
                                $loanOutstanding = $loan['principal_amount'] - $loan['total_paid'];
                            ?>
                            <tr>
                                <td><code style="font-size:12px"><?= clean($loan['loan_no']) ?></code></td>
                                <td class="amount-neutral"><?= formatCurrency($loan['principal_amount']) ?></td>
                                <td class="amount-positive"><?= formatCurrency($loan['total_paid']) ?></td>
                                <td class="<?= $loanOutstanding > 0 ? 'amount-negative' : 'amount-positive' ?>">
                                    <?= formatCurrency($loanOutstanding) ?>
                                </td>
                                <td><?= $loan['interest_rate'] ?>%</td>
                                <td>
                                    <?php if ($loan['status'] === 'completed'): ?>
                                        <span class="badge bg-success">✅ পরিশোধিত</span>
                                    <?php elseif ($loan['status'] === 'active'): ?>
                                        <span class="badge bg-warning text-dark">⏳ চলমান</span>
                                    <?php elseif ($loan['status'] === 'pending'): ?>
                                        <span class="badge bg-info">⏳ অপেক্ষমাণ</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">⛔ বকেয়া</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="loans.php?action=view&id=<?= $loan['id'] ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i data-feather="eye" style="width:14px;height:14px"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <?php if (!empty($memberLoans)): ?>
                        <tfoot>
                            <tr style="font-weight:bold;background:#f8f9fa">
                                <td class="text-end">সর্বমোট:</td>
                                <td><?= formatCurrency(array_sum(array_column($memberLoans, 'principal_amount'))) ?></td>
                                <td class="amount-positive"><?= formatCurrency(array_sum(array_column($memberLoans, 'total_paid'))) ?></td>
                                <td class="<?= $outstanding > 0 ? 'amount-negative' : 'amount-positive' ?>">
                                    <?= formatCurrency($outstanding) ?>
                                </td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<style>
.member-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 600;
    flex-shrink: 0;
}

.member-profile-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    font-weight: 600;
    margin: 0 auto;
}

.amount-positive {
    color: #27AE60;
    font-weight: 600;
}
.amount-negative {
    color: #E74C3C;
    font-weight: 600;
}
.amount-neutral {
    color: #1B4F72;
    font-weight: 600;
}

.info-item {
    display: flex;
    justify-content: space-between;
    padding: 4px 0;
    font-size: 13px;
}
.info-label {
    color: #566573;
}
.info-value {
    font-weight: 500;
    color: #2C3E50;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

.clickable-row:hover {
    background-color: #f0f8ff !important;
    cursor: pointer;
}

.stat-card {
    border-radius: 12px;
    padding: 14px 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.07);
    height: 100%;
}
.stat-card .stat-value {
    font-size: 22px;
    font-weight: 700;
}
.stat-card .stat-label {
    font-size: 12px;
    color: #566573;
    font-weight: 500;
}

.bg-primary-grad { background: linear-gradient(135deg, #EBF5FB, #D6EAF8); }
.bg-success-grad { background: linear-gradient(135deg, #EAFAF1, #D5F5E3); }
.bg-danger-grad { background: linear-gradient(135deg, #FDEDEC, #FADBD8); }
.bg-warning-grad { background: linear-gradient(135deg, #FEF9E7, #FDEBD0); }
</style>

<script>
// Clickable row
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

<?php
// Helper function for color
function getColor($name) {
    $colors = ['#1B4F72', '#2E86C1', '#117A65', '#C0392B', '#8E44AD', '#D35400', '#27AE60', '#2980B9'];
    return $colors[abs(crc32($name)) % count($colors)];
}

// Include footer
require_once __DIR__ . '/../includes/footer.php';
?>