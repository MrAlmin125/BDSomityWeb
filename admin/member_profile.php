<?php
// =====================================================
// admin/member_profile.php - Member Profile Page (FULLY FIXED)
// =====================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
requireAdmin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    setFlash('danger', 'সদস্য আইডি পাওয়া যায়নি।');
    header('Location: members.php');
    exit;
}

$conn = conn();

// Get member data
$member = queryOne("SELECT * FROM members WHERE id = ?", 'i', [$id]);
if (!$member) {
    setFlash('danger', 'সদস্য পাওয়া যায়নি।');
    header('Location: members.php');
    exit;
}

// ============================================
// AVATAR FUNCTIONS
// ============================================
function getAvatarColor($name) {
    $colors = ['#1B4F72', '#2E86C1', '#117A65', '#C0392B', '#8E44AD', '#D35400', '#27AE60', '#2980B9', '#E67E22', '#16A085'];
    $hash = 0;
    for ($i = 0; $i < strlen($name); $i++) {
        $hash = ($hash * 31 + ord($name[$i])) & 0xFFFFFFFF;
    }
    return $colors[abs($hash) % count($colors)];
}

function generateAvatar($name, $size = 100, $circle = true) {
    $firstLetter = mb_substr($name, 0, 1, 'UTF-8');
    $color = getAvatarColor($name);
    
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 ' . $size . ' ' . $size . '">';
    
    if ($circle) {
        $svg .= '<rect width="' . $size . '" height="' . $size . '" rx="' . ($size/2) . '" fill="' . $color . '"/>';
    } else {
        $svg .= '<rect width="' . $size . '" height="' . $size . '" rx="6" fill="' . $color . '"/>';
    }
    
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
    return generateAvatar($member['name'], 100, true);
}

// ============================================
// DATE TIME FORMATTING FUNCTION
// ============================================
if (!function_exists('formatDateTime')) {
    function formatDateTime($datetime) {
        if (empty($datetime)) {
            return '-';
        }
        return date('d M Y, h:i A', strtotime($datetime));
    }
}

// ============================================
// CALCULATE FINANCIAL DATA
// ============================================
$totalDeposit = queryOne("SELECT COALESCE(SUM(amount),0) as total FROM deposits WHERE member_id = ?", 'i', [$id])['total'] ?? 0;
$totalWithdrawal = queryOne("SELECT COALESCE(SUM(amount),0) as total FROM withdrawals WHERE member_id = ? AND status='completed'", 'i', [$id])['total'] ?? 0;
$currentBalance = $totalDeposit - $totalWithdrawal;

// Current month status
$currentMonth = date('m');
$currentYear = date('Y');
$currentMonthDeposit = queryOne(
    "SELECT amount, deposit_date FROM deposits WHERE member_id = ? AND MONTH(deposit_date) = ? AND YEAR(deposit_date) = ? ORDER BY deposit_date DESC LIMIT 1",
    'iii', [$id, $currentMonth, $currentYear]
);
$isCurrentMonthPaid = $currentMonthDeposit !== null;
$currentMonthAmount = $isCurrentMonthPaid ? $currentMonthDeposit['amount'] : 0;

// Get last deposit date overall
$lastDeposit = queryOne(
    "SELECT deposit_date FROM deposits WHERE member_id = ? ORDER BY deposit_date DESC LIMIT 1",
    'i', [$id]
);
$lastDepositDate = $lastDeposit ? $lastDeposit['deposit_date'] : null;

// ============================================
// GET TAB DATA
// ============================================
$activeTab = $_GET['tab'] ?? 'deposits';

// Deposit History
$deposits = queryAll(
    "SELECT * FROM deposits WHERE member_id = ? ORDER BY deposit_date DESC LIMIT 20",
    'i', [$id]
);

// Withdrawal History
$withdrawals = queryAll(
    "SELECT * FROM withdrawals WHERE member_id = ? ORDER BY withdrawal_date DESC LIMIT 20",
    'i', [$id]
);

// Timeline
$timeline = [];
foreach ($deposits as $d) {
    $timeline[] = [
        'date' => $d['deposit_date'],
        'type' => 'deposit',
        'amount' => $d['amount'],
        'description' => $d['deposit_type'] ?? 'Regular',
        'reference' => $d['receipt_no'] ?? '',
        'id' => $d['id'],
        'member_id' => $d['member_id']
    ];
}
foreach ($withdrawals as $w) {
    if ($w['status'] === 'completed') {
        $timeline[] = [
            'date' => $w['withdrawal_date'],
            'type' => 'withdrawal',
            'amount' => $w['amount'],
            'description' => $w['withdrawal_type'] ?? 'partial',
            'reference' => $w['receipt_no'] ?? '',
            'id' => $w['id'],
            'member_id' => $w['member_id']
        ];
    }
}
usort($timeline, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// Monthly Status
$monthlyStatus = [];
$startDate = new DateTime($member['join_date']);
$endDate = new DateTime();
$interval = new DateInterval('P1M');
$period = new DatePeriod($startDate, $interval, $endDate->modify('+1 month'));

foreach ($period as $dt) {
    $year = $dt->format('Y');
    $month = $dt->format('m');
    $monthName = $dt->format('F Y');
    
    $deposit = queryOne(
        "SELECT amount FROM deposits WHERE member_id = ? AND MONTH(deposit_date) = ? AND YEAR(deposit_date) = ?",
        'iii', [$id, $month, $year]
    );
    
    $monthlyStatus[] = [
        'month' => $monthName,
        'year' => $year,
        'month_num' => $month,
        'paid' => $deposit !== null,
        'amount' => $deposit ? $deposit['amount'] : 0,
        'is_current' => ($year == date('Y') && $month == date('m'))
    ];
}
$monthlyStatus = array_reverse($monthlyStatus);

// ============================================
// PAGE HEADER
// ============================================
$pageTitle = $member['name'] . ' - প্রোফাইল';
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
     MAIN CONTENT
     ============================================ -->
<div class="container-fluid px-4 py-3">

    <!-- Back Button -->
    <div class="mb-3">
        <a href="members.php" class="btn btn-outline-secondary btn-sm">
            <i data-feather="arrow-left" style="width:14px;height:14px"></i> সদস্য তালিকায় ফিরে যান
        </a>
    </div>

    <!-- ============================================
         PROFILE HEADER CARD
         ============================================ -->
    <div class="profile-header-card mb-4">
        <div class="row align-items-center g-3">
            <!-- Photo -->
            <div class="col-12 col-md-3 text-center text-md-start">
                <img src="<?= getMemberPhoto($member) ?>" alt="Photo" class="profile-photo">
                <div class="mt-2">
                    <span class="badge status-<?= $member['status'] === 'active' ? 'active' : 'inactive' ?>">
                        <?= $member['status'] === 'active' ? '✅ সক্রিয়' : '⛔ নিষ্ক্রিয়' ?>
                    </span>
                </div>
            </div>
            
            <!-- Info -->
            <div class="col-12 col-md-5">
                <h3 class="member-name"><?= clean($member['name']) ?></h3>
                <div class="member-id"><code>#<?= clean($member['member_id']) ?></code></div>
                <div class="member-details">
                    <div class="detail-item"><i data-feather="phone"></i> <?= clean($member['phone']) ?></div>
                    <?php if (!empty($member['email'])): ?>
                    <div class="detail-item"><i data-feather="mail"></i> <?= clean($member['email']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($member['address'])): ?>
                    <div class="detail-item"><i data-feather="map-pin"></i> <?= clean($member['address']) ?></div>
                    <?php endif; ?>
                    <div class="detail-item"><i data-feather="calendar"></i> যোগদান: <?= formatDate($member['join_date']) ?></div>
                    <div class="detail-item"><i data-feather="dollar-sign"></i> মাসিক জমা: <?= formatCurrency($member['monthly_deposit'] ?? 500) ?></div>
                </div>
            </div>
            
            <!-- Actions - 4 Inline Buttons -->
            <div class="col-12 col-md-4">
                <div class="action-buttons">
                    <a href="deposits.php?member_id=<?= $id ?>" class="btn btn-success btn-sm action-btn">
                        <i data-feather="plus-circle"></i> <span class="btn-text">সঞ্চয় জমা</span>
                    </a>
                    <a href="withdrawals.php?member_id=<?= $id ?>" class="btn btn-warning btn-sm action-btn">
                        <i data-feather="minus-circle"></i> <span class="btn-text">উত্তোলন</span>
                    </a>
                    <a href="member_add.php?id=<?= $id ?>" class="btn btn-primary btn-sm action-btn">
                        <i data-feather="edit-2"></i> <span class="btn-text">সম্পাদনা</span>
                    </a>
                    <button class="btn btn-info btn-sm action-btn" onclick="window.print()">
                        <i data-feather="printer"></i> <span class="btn-text">প্রিন্ট</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================
         STATISTICS CARDS
         ============================================ -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card bg-success-grad">
                <div class="stat-label">মোট সঞ্চয়</div>
                <div class="stat-value"><?= formatCurrency($totalDeposit) ?></div>
                <div class="stat-sub">সর্বমোট জমা</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card bg-danger-grad">
                <div class="stat-label">মোট উত্তোলন</div>
                <div class="stat-value"><?= formatCurrency($totalWithdrawal) ?></div>
                <div class="stat-sub">সর্বমোট উত্তোলন</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card <?= $currentBalance >= 0 ? 'bg-info-grad' : 'bg-warning-grad' ?>">
                <div class="stat-label">বর্তমান ব্যালেন্স</div>
                <div class="stat-value <?= $currentBalance >= 0 ? 'text-success' : 'text-danger' ?>">
                    <?= formatCurrency($currentBalance) ?>
                </div>
                <div class="stat-sub">জমা - উত্তোলন</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card <?= $isCurrentMonthPaid ? 'bg-success-grad' : 'bg-danger-grad' ?>">
                <div class="stat-label">বর্তমান মাস</div>
                <div class="stat-value" style="font-size:20px;">
                    <?= $isCurrentMonthPaid ? '✅ পরিশোধিত' : '⛔ বকেয়া' ?>
                </div>
                <div class="stat-sub">
                    <?php if ($isCurrentMonthPaid): ?>
                        জমা: <?= formatCurrency($currentMonthAmount) ?>
                    <?php else: ?>
                        জমা দিননি
                    <?php endif; ?>
                </div>
                <?php if ($lastDepositDate): ?>
                <div class="stat-sub" style="font-size:11px;margin-top:4px;">
                    শেষ জমা: <?= formatDate($lastDepositDate) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ============================================
         TABS - USING URL PARAMETERS (WORKING)
         ============================================ -->
    <div class="tabs-container">
        <ul class="nav nav-tabs profile-tabs">
            <li class="nav-item">
                <a href="?id=<?= $id ?>&tab=deposits" class="nav-link <?= $activeTab === 'deposits' ? 'active' : '' ?>">
                    <i data-feather="book-open"></i> সঞ্চয়ের ইতিহাস
                    <span class="badge bg-primary ms-1"><?= count($deposits) ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a href="?id=<?= $id ?>&tab=withdrawals" class="nav-link <?= $activeTab === 'withdrawals' ? 'active' : '' ?>">
                    <i data-feather="arrow-down"></i> উত্তোলনের ইতিহাস
                    <span class="badge bg-danger ms-1"><?= count($withdrawals) ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a href="?id=<?= $id ?>&tab=timeline" class="nav-link <?= $activeTab === 'timeline' ? 'active' : '' ?>">
                    <i data-feather="clock"></i> টাইমলাইন
                </a>
            </li>
            <li class="nav-item">
                <a href="?id=<?= $id ?>&tab=monthly" class="nav-link <?= $activeTab === 'monthly' ? 'active' : '' ?>">
                    <i data-feather="calendar"></i> মাসিক অবস্থা
                </a>
            </li>
        </ul>

        <!-- ============================================
             TAB CONTENT
             ============================================ -->
        <div class="tab-content">

            <!-- TAB 1: DEPOSITS -->
            <?php if ($activeTab === 'deposits'): ?>
            <div class="tab-pane show active">
                <div class="card">
                    <div class="card-header">
                        <span class="card-title"><i data-feather="book-open"></i> সঞ্চয়ের ইতিহাস</span>
                        <a href="deposits.php?member_id=<?= $id ?>" class="btn btn-outline-primary btn-sm">
                            সব দেখুন <i data-feather="arrow-right"></i>
                        </a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>তারিখ</th>
                                    <th>ধরন</th>
                                    <th>পরিমাণ</th>
                                    <th>রসিদ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($deposits)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        <i data-feather="inbox" style="width:32px;height:32px;display:block;margin:0 auto 10px;"></i>
                                        কোনো সঞ্চয় নেই
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($deposits as $d): ?>
                                <tr class="clickable-row" data-href="deposits.php?member_id=<?= $id ?>">
                                    <td><?= formatDate($d['deposit_date']) ?></td>
                                    <td><span class="badge bg-primary"><?= clean($d['deposit_type']) ?></span></td>
                                    <td class="amount-positive"><?= formatCurrency($d['amount']) ?></td>
                                    <td><code><?= clean($d['receipt_no'] ?? '-') ?></code></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-active">
                                    <td colspan="2"><strong>সর্বমোট</strong></td>
                                    <td><strong><?= formatCurrency($totalDeposit) ?></strong></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- TAB 2: WITHDRAWALS -->
            <?php if ($activeTab === 'withdrawals'): ?>
            <div class="tab-pane show active">
                <div class="card">
                    <div class="card-header">
                        <span class="card-title"><i data-feather="arrow-down"></i> উত্তোলনের ইতিহাস</span>
                        <a href="withdrawals.php?member_id=<?= $id ?>" class="btn btn-outline-danger btn-sm">
                            সব দেখুন <i data-feather="arrow-right"></i>
                        </a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>তারিখ</th>
                                    <th>পরিমাণ</th>
                                    <th>কারণ</th>
                                    <th>রসিদ</th>
                                    <th>অবস্থা</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($withdrawals)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        <i data-feather="inbox" style="width:32px;height:32px;display:block;margin:0 auto 10px;"></i>
                                        কোনো উত্তোলন নেই
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($withdrawals as $w): ?>
                                <tr class="clickable-row" data-href="withdrawals.php?member_id=<?= $id ?>">
                                    <td><?= formatDate($w['withdrawal_date']) ?></td>
                                    <td class="amount-negative"><?= formatCurrency($w['amount']) ?></td>
                                    <td><?= clean($w['reason'] ?? '-') ?></td>
                                    <td><code><?= clean($w['receipt_no'] ?? '-') ?></code></td>
                                    <td>
                                        <span class="badge <?= $w['status'] === 'completed' ? 'bg-success' : 'bg-warning' ?>">
                                            <?= $w['status'] === 'completed' ? 'সম্পন্ন' : 'প্রক্রিয়াধীন' ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-active">
                                    <td><strong>সর্বমোট</strong></td>
                                    <td><strong><?= formatCurrency($totalWithdrawal) ?></strong></td>
                                    <td colspan="3"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- TAB 3: TIMELINE -->
            <?php if ($activeTab === 'timeline'): ?>
            <div class="tab-pane show active">
                <div class="card">
                    <div class="card-header">
                        <span class="card-title"><i data-feather="clock"></i> টাইমলাইন</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($timeline)): ?>
                        <div class="text-center text-muted py-4">
                            <i data-feather="clock" style="width:32px;height:32px;display:block;margin:0 auto 10px;"></i>
                            কোনো কার্যক্রম নেই
                        </div>
                        <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($timeline as $event): ?>
                            <div class="timeline-item clickable-row" 
                                 data-href="<?= $event['type'] === 'deposit' ? 'deposits.php?member_id=' . $id : 'withdrawals.php?member_id=' . $id ?>">
                                <div class="timeline-icon <?= $event['type'] === 'deposit' ? 'icon-success' : 'icon-danger' ?>">
                                    <i data-feather="<?= $event['type'] === 'deposit' ? 'arrow-up' : 'arrow-down' ?>"></i>
                                </div>
                                <div class="timeline-content">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap">
                                        <div>
                                            <h6 class="mb-0">
                                                <?= $event['type'] === 'deposit' ? '💰 সঞ্চয় জমা' : '🏦 উত্তোলন' ?>
                                                <small class="text-muted ms-2">
                                                    <?php echo formatDateTime($event['date']); ?>
                                                </small>
                                            </h6>
                                            <p class="text-muted mb-0 small">
                                                <?= clean($event['description']) ?>
                                                <?php if (!empty($event['reference'])): ?>
                                                <span class="ms-2"><code><?= clean($event['reference']) ?></code></span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <span class="<?= $event['type'] === 'deposit' ? 'amount-positive' : 'amount-negative' ?> fw-bold">
                                            <?= $event['type'] === 'deposit' ? '+' : '-' ?> <?= formatCurrency($event['amount']) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- TAB 4: MONTHLY STATUS -->
            <?php if ($activeTab === 'monthly'): ?>
            <div class="tab-pane show active">
                <div class="card">
                    <div class="card-header">
                        <span class="card-title"><i data-feather="calendar"></i> মাসিক অবস্থা</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>মাস</th>
                                    <th>অবস্থা</th>
                                    <th>জমার পরিমাণ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($monthlyStatus)): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">
                                        <i data-feather="calendar" style="width:32px;height:32px;display:block;margin:0 auto 10px;"></i>
                                        কোনো মাসিক ডেটা নেই
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($monthlyStatus as $ms): ?>
                                <tr class="<?= $ms['is_current'] ? 'table-primary' : '' ?>">
                                    <td>
                                        <?= clean($ms['month']) ?>
                                        <?php if ($ms['is_current']): ?>
                                        <span class="badge bg-primary ms-1">বর্তমান</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $ms['paid'] ? 'bg-success' : 'bg-danger' ?>">
                                            <?= $ms['paid'] ? '✅ পরিশোধিত' : '⛔ বকেয়া' ?>
                                        </span>
                                    </td>
                                    <td class="<?= $ms['paid'] ? 'amount-positive' : 'text-muted' ?>">
                                        <?= $ms['paid'] ? formatCurrency($ms['amount']) : '-' ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<style>
/* ============================================
   PROFILE PAGE STYLES
   ============================================ */

/* Profile Header */
.profile-header-card {
    background: #ffffff;
    border: none;
    border-radius: 16px;
    padding: 24px 28px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
}

.profile-photo {
    width: 110px;
    height: 110px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #fff;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.member-name {
    font-weight: 700;
    color: #1B4F72;
    margin-bottom: 2px;
    font-size: 22px;
}

.member-id {
    font-size: 13px;
    color: #95a5a6;
    margin-bottom: 10px;
}

.member-details {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 4px 20px;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: #2C3E50;
    padding: 2px 0;
}

.detail-item i {
    width: 16px;
    height: 16px;
    color: #7f8c8d;
    flex-shrink: 0;
}

/* ============================================
   ACTION BUTTONS - 4 INLINE (PC) / 2 INLINE (MOBILE)
   ============================================ */
.action-buttons {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 6px;
    width: 100%;
}

.action-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    font-size: 12px;
    padding: 8px 4px;
    border-radius: 8px;
    white-space: nowrap;
    min-height: 38px;
}

.action-btn i {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
}

.action-btn .btn-text {
    font-size: 12px;
    font-weight: 500;
}

/* Mobile - 2 columns */
@media (max-width: 768px) {
    .action-buttons {
        grid-template-columns: repeat(2, 1fr);
        gap: 4px;
    }
    
    .action-btn {
        font-size: 11px;
        padding: 6px 4px;
        min-height: 34px;
    }
    
    .action-btn i {
        width: 14px;
        height: 14px;
    }
    
    .action-btn .btn-text {
        font-size: 10px;
    }
}

/* Very small screens - hide text, show only icons */
@media (max-width: 480px) {
    .action-btn .btn-text {
        display: none;
    }
    
    .action-btn {
        min-height: 32px;
        padding: 4px;
    }
    
    .action-btn i {
        width: 18px;
        height: 18px;
    }
}

/* Stat Cards */
.stat-card {
    border-radius: 12px;
    padding: 16px 18px;
    height: 100%;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    transition: transform 0.2s;
}
.stat-card:hover {
    transform: translateY(-2px);
}
.stat-card .stat-label {
    font-size: 11px;
    color: rgba(0,0,0,0.5);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}
.stat-card .stat-value {
    font-size: 22px;
    font-weight: 700;
    color: #1B4F72;
    margin: 6px 0 2px;
}
.stat-card .stat-sub {
    font-size: 12px;
    color: rgba(0,0,0,0.5);
}

.bg-success-grad { background: linear-gradient(135deg, #EAFAF1, #D5F5E3); }
.bg-danger-grad { background: linear-gradient(135deg, #FDEDEC, #FADBD8); }
.bg-info-grad { background: linear-gradient(135deg, #EAF2F8, #D4E6F1); }
.bg-warning-grad { background: linear-gradient(135deg, #FEF9E7, #FDEBD0); }

/* Status Badges */
.status-active { background: #D5F5E3; color: #1E8449; padding: 4px 14px; border-radius: 20px; }
.status-inactive { background: #F2F3F4; color: #5D6D7E; padding: 4px 14px; border-radius: 20px; }

/* Amount Colors */
.amount-positive { color: #27AE60; font-weight: 600; }
.amount-negative { color: #E74C3C; font-weight: 600; }

/* Tabs - Simple Anchor Tabs */
.tabs-container {
    margin-bottom: 20px;
}

.profile-tabs {
    border-bottom: 2px solid #e9ecef;
    margin-bottom: 20px;
    gap: 4px;
    display: flex;
    flex-wrap: wrap;
    list-style: none;
    padding-left: 0;
}
.profile-tabs .nav-item {
    margin-bottom: -2px;
}
.profile-tabs .nav-link {
    border: none;
    padding: 10px 20px;
    color: #6c757d;
    font-weight: 500;
    border-radius: 8px 8px 0 0;
    transition: all 0.2s;
    font-size: 14px;
    background: transparent;
    text-decoration: none;
    display: inline-block;
}
.profile-tabs .nav-link:hover {
    color: #1B4F72;
    background: #f8f9fa;
}
.profile-tabs .nav-link.active {
    color: #1B4F72;
    background: #fff;
    border-bottom: 3px solid #1B4F72;
    font-weight: 600;
}
.profile-tabs .nav-link i {
    width: 14px;
    height: 14px;
    margin-right: 6px;
}

/* Tab Content */
.tab-pane {
    display: none;
}
.tab-pane.show.active {
    display: block;
}

/* Cards */
.card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    margin-bottom: 20px;
}
.card-header {
    background: #fff;
    border-bottom: 1px solid #f0f0f0;
    padding: 12px 18px;
    border-radius: 12px 12px 0 0 !important;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}
.card-title {
    font-size: 14px;
    font-weight: 600;
    color: #2C3E50;
    display: flex;
    align-items: center;
    gap: 6px;
}
.card-title i {
    width: 16px;
    height: 16px;
}
.card-body {
    padding: 16px 18px;
}

/* Table */
.table {
    font-size: 13px;
    margin-bottom: 0;
}
.table th {
    font-weight: 600;
    color: #566573;
    border-bottom: 2px solid #f0f0f0;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.table td {
    vertical-align: middle;
    padding: 10px 12px;
}
.table-hover tbody tr:hover {
    background: #f8f9fa;
}

/* Timeline */
.timeline {
    padding: 4px 0;
}
.timeline-item {
    display: flex;
    gap: 16px;
    padding: 12px 0;
    border-bottom: 1px solid #f0f0f0;
    cursor: pointer;
    transition: background 0.2s;
    border-radius: 8px;
    padding-left: 8px;
}
.timeline-item:hover {
    background: #f8f9fa;
}
.timeline-item:last-child {
    border-bottom: none;
}
.timeline-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: white;
}
.timeline-icon i {
    width: 16px;
    height: 16px;
}
.icon-success { background: #27AE60; }
.icon-danger { background: #E74C3C; }
.timeline-content {
    flex: 1;
    min-width: 0;
}

/* Clickable Row */
.clickable-row {
    cursor: pointer;
}
.clickable-row:hover {
    background-color: #f8f9fa !important;
}

/* Responsive */
@media (max-width: 768px) {
    .profile-header-card {
        padding: 16px;
    }
    .member-details {
        grid-template-columns: 1fr;
    }
    .profile-photo {
        width: 90px;
        height: 90px;
    }
    .member-name {
        font-size: 18px;
    }
    .stat-card .stat-value {
        font-size: 18px;
    }
    .profile-tabs .nav-link {
        padding: 8px 14px;
        font-size: 13px;
    }
    .profile-tabs .nav-link i {
        width: 12px;
        height: 12px;
    }
    .timeline-item {
        padding: 10px 0;
        gap: 12px;
    }
    .timeline-icon {
        width: 30px;
        height: 30px;
    }
    .timeline-icon i {
        width: 14px;
        height: 14px;
    }
}

/* Print */
@media print {
    .btn, .action-buttons, .no-print {
        display: none !important;
    }
    .profile-header-card, .stat-card, .card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
    .table {
        font-size: 12px !important;
    }
    .card {
        break-inside: avoid;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Feather icons
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
    
    // Clickable rows
    document.querySelectorAll('.clickable-row').forEach(function(row) {
        row.addEventListener('click', function(e) {
            if (e.target.closest('a') || e.target.closest('button')) {
                return;
            }
            const href = this.dataset.href;
            if (href) {
                window.location.href = href;
            }
        });
    });
});

function printStatement() {
    window.print();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>