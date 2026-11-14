<?php
// =====================================================
// member/fines.php - Member Fines List (COMPLETE)
// =====================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/functions.php';
startSecureSession();

if (!isset($_SESSION['member_id'])) {
    header('Location: /member/login.php');
    exit;
}

$mId = $_SESSION['member_id'];
$conn = conn();

$member = queryOne("SELECT * FROM members WHERE id = ?", 'i', [$mId]);
if (!$member) {
    header('Location: /member/login.php');
    exit;
}

// Get all fines for this member
$fines = queryAll(
    "SELECT * FROM fines WHERE member_id = ? ORDER BY month_year DESC, due_date ASC",
    'i', [$mId]
);

// Calculate totals
$totalFine = 0;
$pendingFine = 0;
$paidFine = 0;
$waivedFine = 0;

foreach ($fines as $fine) {
    $fineAmount = isset($fine['fine_amount']) ? (float)$fine['fine_amount'] : 0;
    $paidAmount = isset($fine['paid_amount']) ? (float)$fine['paid_amount'] : 0;
    $waivedAmount = isset($fine['waived_amount']) ? (float)$fine['waived_amount'] : 0;
    $status = isset($fine['status']) ? $fine['status'] : 'pending';
    
    $totalFine += $fineAmount;
    if ($status == 'paid') {
        $paidFine += $fineAmount;
    } elseif ($status == 'waived') {
        $waivedFine += $fineAmount;
    } else {
        $pendingFine += ($fineAmount - $paidAmount - $waivedAmount);
    }
}

$pageTitle = 'জরিমানার তালিকা';
require_once __DIR__ . '/includes/header.php';
?>

<style>
.container { max-width: 768px; margin: 0 auto; padding: 16px; }
.card { border: none; border-radius: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 14px; overflow: hidden; background: white; }
.card-header { background: white; border-bottom: 1px solid #eee; padding: 12px 16px; font-weight: 600; display: flex; justify-content: space-between; align-items: center; font-size: 14px; }
.card-header a { font-size: 12px; color: #117A65; text-decoration: none; }
.card-header a:hover { text-decoration: underline; }
.list-item { padding: 12px 16px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; }
.list-item:last-child { border-bottom: none; }
.list-item .item-title { font-size: 13px; font-weight: 500; }
.list-item .item-sub { font-size: 11px; color: #888; }
.amount-due { color: #E74C3C; font-weight: 600; font-size: 15px; }
.amount-positive { color: #27AE60; font-weight: 600; }
.empty-state { padding: 40px 16px; text-align: center; color: #999; }
.badge-status-paid { background: #D5F5E3; color: #1E8449; padding: 2px 10px; border-radius: 12px; font-size: 11px; }
.badge-status-unpaid { background: #FDEDEC; color: #C0392B; padding: 2px 10px; border-radius: 12px; font-size: 11px; }
.badge-status-partial { background: #FEF9E7; color: #B7950B; padding: 2px 10px; border-radius: 12px; font-size: 11px; }
.badge-status-waived { background: #F5EEF8; color: #7D3C98; padding: 2px 10px; border-radius: 12px; font-size: 11px; }

.summary-bar { background: #F8F9FA; padding: 12px 16px; border-radius: 12px; margin-bottom: 14px; display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
.summary-item { text-align: center; }
.summary-item .label { font-size: 11px; color: #888; }
.summary-item .value { font-size: 16px; font-weight: 700; }
.summary-item .value.red { color: #E74C3C; }
.summary-item .value.green { color: #27AE60; }
.summary-item .value.purple { color: #8E44AD; }

.btn-back { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; background: #F8F9FA; border: 1px solid #ddd; border-radius: 8px; color: #333; text-decoration: none; font-size: 13px; transition: all 0.3s; }
.btn-back:hover { background: #e9ecef; color: #117A65; }

@media (max-width: 576px) {
    .summary-bar { grid-template-columns: repeat(2, 1fr); gap: 6px; padding: 10px; }
    .summary-item .value { font-size: 14px; }
    .list-item { flex-wrap: wrap; gap: 6px; }
}
</style>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold m-0">
            <i data-feather="alert-triangle" style="width:18px;height:18px;"></i> 
            আমার জরিমানা
            <span style="font-size:13px;font-weight:normal;color:#888;margin-left:8px;">(<?= count($fines) ?> টি)</span>
        </h5>
        <a href="/member/index.php" class="btn-back">
            <i data-feather="arrow-left" style="width:14px;height:14px;"></i> ড্যাশবোর্ড
        </a>
    </div>

    <!-- Summary -->
    <div class="summary-bar">
        <div class="summary-item">
            <div class="label">মোট জরিমানা</div>
            <div class="value" style="color:#2C3E50;"><?= formatCurrency($totalFine) ?></div>
        </div>
        <div class="summary-item">
            <div class="label">pending</div>
            <div class="value red"><?= formatCurrency($pendingFine) ?></div>
        </div>
        <div class="summary-item">
            <div class="label">পরিশোধিত</div>
            <div class="value green"><?= formatCurrency($paidFine) ?></div>
        </div>
        <div class="summary-item">
            <div class="label">মওকুফকৃত</div>
            <div class="value purple"><?= formatCurrency($waivedFine) ?></div>
        </div>
    </div>

    <!-- Fines List -->
    <div class="card">
        <div class="card-header">
            <span><i data-feather="list" style="width:14px;height:14px;"></i> জরিমানার বিবরণ</span>
            <span style="font-size:12px;color:#888;">সর্বমোট: <?= count($fines) ?> টি</span>
        </div>
        <?php if (empty($fines)): ?>
        <div class="empty-state">
            <i data-feather="check-circle" style="width:40px;height:40px;color:#27AE60;display:block;margin:0 auto 8px;"></i>
            <p style="font-weight:500;margin-bottom:4px;">আপনার কোনো জরিমানা নেই</p>
            <small style="color:#aaa;">আপনি সময়মতো সব চাঁদা পরিশোধ করেছেন</small>
        </div>
        <?php else: ?>
        <?php foreach ($fines as $fine): 
            $fineAmount = isset($fine['fine_amount']) ? (float)$fine['fine_amount'] : 0;
            $paidAmount = isset($fine['paid_amount']) ? (float)$fine['paid_amount'] : 0;
            $waivedAmount = isset($fine['waived_amount']) ? (float)$fine['waived_amount'] : 0;
            $remaining = $fineAmount - $paidAmount - $waivedAmount;
            $status = isset($fine['status']) ? $fine['status'] : 'pending';
            
            $statusClass = '';
            $statusText = '';
            if ($status == 'paid') {
                $statusClass = 'badge-status-paid';
                $statusText = '✅ পরিশোধিত';
            } elseif ($status == 'waived') {
                $statusClass = 'badge-status-waived';
                $statusText = '⏳ মওকুফকৃত';
            } elseif ($status == 'partially_paid') {
                $statusClass = 'badge-status-partial';
                $statusText = '🔄 আংশিক';
            } else {
                $statusClass = 'badge-status-unpaid';
                $statusText = '⏳ pending';
            }
            
            $monthYear = isset($fine['month_year']) ? date('F Y', strtotime($fine['month_year'])) : '';
            $dueDate = isset($fine['due_date']) ? formatDate($fine['due_date']) : '';
            $paidDate = isset($fine['paid_date']) ? formatDate($fine['paid_date']) : '';
        ?>
        <div class="list-item" style="<?= ($status == 'pending' && $remaining > 0) ? 'border-left: 4px solid #E74C3C;' : ($status == 'partially_paid' ? 'border-left: 4px solid #F39C12;' : 'border-left: 4px solid transparent;') ?>">
            <div style="flex:1;">
                <div class="item-title">
                    <span style="font-weight:600;"><?= $monthYear ?></span>
                    <?php if (!empty($fine['notes'])): ?>
                    <small style="font-weight:normal;color:#666;font-size:11px;display:block;margin-top:2px;">
                        📝 <?= clean($fine['notes']) ?>
                    </small>
                    <?php endif; ?>
                </div>
                <div class="item-sub" style="margin-top:4px;display:flex;flex-wrap:wrap;gap:4px;">
                    <span class="<?= $statusClass ?>"><?= $statusText ?></span>
                    <?php if ($status == 'partially_paid'): ?>
                    <span style="font-size:11px;color:#888;">
                        পরিশোধ: <?= formatCurrency($paidAmount) ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($status == 'waived'): ?>
                    <span style="font-size:11px;color:#888;">
                        মওকুফ: <?= formatCurrency($waivedAmount) ?>
                    </span>
                    <?php endif; ?>
                    <?php if (!empty($dueDate) && $status != 'paid' && $status != 'waived'): ?>
                    <span style="font-size:11px;color:#E74C3C;">
                        📅 শেষ তারিখ: <?= $dueDate ?>
                    </span>
                    <?php endif; ?>
                    <?php if (!empty($paidDate) && $status == 'paid'): ?>
                    <span style="font-size:11px;color:#27AE60;">
                        ✅ পরিশোধ: <?= $paidDate ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <div style="text-align:right;flex-shrink:0;margin-left:12px;">
                <?php if ($status == 'paid'): ?>
                <div class="amount-positive"><?= formatCurrency($fineAmount) ?></div>
                <div style="font-size:10px;color:#27AE60;">পরিশোধিত</div>
                <?php elseif ($status == 'waived'): ?>
                <div style="color:#8E44AD;font-weight:600;"><?= formatCurrency($fineAmount) ?></div>
                <div style="font-size:10px;color:#8E44AD;">মওকুফকৃত</div>
                <?php else: ?>
                <div class="amount-due"><?= formatCurrency($remaining) ?></div>
                <div style="font-size:10px;color:#888;">জরিমানা: <?= formatCurrency($fineAmount) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Info Box -->
    <div style="background:#E8F8F5;border-radius:12px;padding:12px 16px;margin-top:14px;border:1px solid #A3E4D7;">
        <div style="display:flex;align-items:flex-start;gap:10px;font-size:13px;color:#0E6655;">
            <i data-feather="info" style="width:18px;height:18px;flex-shrink:0;margin-top:1px;"></i>
            <div>
                <strong>জরিমানা সংক্রান্ত তথ্য:</strong><br>
                • মাসিক চাঁদা ১০ তারিখের মধ্যে জমা না দিলে ২০০ টাকা জরিমানা প্রযোজ্য<br>
                • জরিমানা পরিশোধ করতে প্রশাসকের সাথে যোগাযোগ করুন
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof feather !== 'undefined') { feather.replace(); }
});
</script>

<!-- ============================================
     INCLUDE FOOTER (Bottom Nav)
     ============================================ -->
<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>