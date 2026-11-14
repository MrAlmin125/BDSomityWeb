<?php
// =====================================================
// member_search.php - Member Search System (FIXED - No duplicate function)
// =====================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/functions.php';
startSecureSession();

// Check if user is logged in (admin or member)
if (!isAdminLoggedIn() && !isMemberLoggedIn()) {
    header('Location: /login.php');
    exit;
}

$pageTitle = 'সদস্য অনুসন্ধান';
$searchResult = null;
$error = '';
$searchTerm = '';
$withdrawals = [];
$realBalance = 0;
$totalDeposit = 0;
$totalWithdrawn = 0;

// ============================================
// GET MEMBER BALANCE FUNCTION
// ============================================
function getMemberBalance($memberId) {
    $totalDeposit = queryOne("SELECT COALESCE(SUM(amount),0) AS t FROM deposits WHERE member_id=?", 'i', [$memberId])['t'] ?? 0;
    $totalWithdrawn = queryOne("SELECT COALESCE(SUM(amount),0) AS t FROM withdrawals WHERE member_id=? AND status='completed'", 'i', [$memberId])['t'] ?? 0;
    return (float)$totalDeposit - (float)$totalWithdrawn;
}

// ============================================
// NOTE: timeAgo() function is already defined in functions.php
// Do NOT redeclare it here
// ============================================

// Handle search
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $searchTerm = trim($_GET['search']);
    
    try {
        $conn = conn();
        
        // Search by phone or member_id
        $sql = "SELECT m.*, 
                       COALESCE(SUM(d.amount), 0) as total_deposit,
                       (SELECT COUNT(*) FROM loans WHERE member_id = m.id AND status = 'active') as active_loans,
                       (SELECT COUNT(*) FROM loans WHERE member_id = m.id) as total_loans
                FROM members m
                LEFT JOIN deposits d ON d.member_id = m.id
                WHERE m.phone LIKE ? OR m.member_id LIKE ?
                GROUP BY m.id
                LIMIT 1";
        
        $like = "%$searchTerm%";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $like, $like);
        $stmt->execute();
        $result = $stmt->get_result();
        $searchResult = $result->fetch_assoc();
        
        if ($searchResult) {
            // Get withdrawal history
            $stmt = $conn->prepare("
                SELECT id, amount, withdrawal_date, withdrawal_type, reason, receipt_no, status, approved_at, created_at
                FROM withdrawals 
                WHERE member_id = ? AND status = 'completed'
                ORDER BY withdrawal_date DESC, id DESC
                LIMIT 20
            ");
            $stmt->bind_param('i', $searchResult['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $withdrawals = $result->fetch_all(MYSQLI_ASSOC);
            
            // Calculate real balance
            $totalDeposit = $searchResult['total_deposit'] ?? 0;
            $totalWithdrawn = array_sum(array_column($withdrawals, 'amount'));
            $realBalance = $totalDeposit - $totalWithdrawn;
        } else {
            $error = 'কোনো সদস্য খুঁজে পাওয়া যায়নি।';
        }
        
    } catch (Exception $e) {
        $error = 'অনুসন্ধান করতে ব্যর্থ হয়েছে: ' . $e->getMessage();
    }
}

// Include header
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-4 py-3">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">
            <i data-feather="search" style="width:20px;height:20px"></i> 
            সদস্য অনুসন্ধান
            <small class="text-muted">(ফোন বা আইডি দিয়ে খুঁজুন)</small>
        </h4>
        <div>
            <?php if (isAdminLoggedIn()): ?>
            <a href="/admin/dashboard.php" class="btn btn-outline-secondary btn-sm">
                <i data-feather="arrow-left" style="width:14px;height:14px"></i> ড্যাশবোর্ড
            </a>
            <?php else: ?>
            <a href="/member/dashboard.php" class="btn btn-outline-secondary btn-sm">
                <i data-feather="arrow-left" style="width:14px;height:14px"></i> ড্যাশবোর্ড
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Search Box -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-8">
                    <div class="search-box">
                        <i data-feather="search" class="search-icon"></i>
                        <input type="text" name="search" class="form-control form-control-lg" 
                               placeholder="ফোন নম্বর বা সদস্য আইডি দিন (যেমন: 017XXXXXXXX বা SM20240001)" 
                               value="<?= htmlspecialchars($searchTerm) ?>"
                               required>
                    </div>
                    <div class="form-text mt-1">
                        <i data-feather="info" style="width:14px;height:14px"></i>
                        ফোন নম্বর বা সদস্য আইডি দিয়ে অনুসন্ধান করুন
                    </div>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        <i data-feather="search" style="width:18px;height:18px"></i> খুঁজুন
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Error Message -->
    <?php if ($error): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2">
        <i data-feather="alert-circle" style="width:18px;height:18px"></i>
        <span><?= htmlspecialchars($error) ?></span>
    </div>
    <?php endif; ?>

    <!-- Search Result -->
    <?php if ($searchResult): ?>
    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <i data-feather="user" style="width:16px;height:16px"></i> 
                সদস্য বিস্তারিত
            </span>
            <span class="badge bg-success">✅ পাওয়া গেছে</span>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <!-- Profile Section -->
                <div class="col-md-4 text-center">
                    <div class="member-profile-avatar" style="background:<?= getColor($searchResult['name']) ?>">
                        <?= mb_substr($searchResult['name'], 0, 2) ?>
                    </div>
                    <h5 class="mt-3"><?= clean($searchResult['name']) ?></h5>
                    <p class="text-muted small">
                        <span class="badge <?= $searchResult['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>">
                            <?= $searchResult['status'] === 'active' ? '✅ সক্রিয়' : '⛔ নিষ্ক্রিয়' ?>
                        </span>
                    </p>
                    
                    <!-- ✅ BIG BALANCE DISPLAY -->
                    <div class="balance-display mt-3 p-3 rounded" style="background: <?= $realBalance >= 0 ? '#d5f5e3' : '#fadbd8' ?>; border: 2px solid <?= $realBalance >= 0 ? '#27ae60' : '#e74c3c' ?>;">
                        <div style="font-size:12px; color:#666; text-transform:uppercase; letter-spacing:1px;">
                            <?= $realBalance >= 0 ? '💰 উপলব্ধ ব্যালেন্স' : '⚠️ নেগেটিভ ব্যালেন্স' ?>
                        </div>
                        <div style="font-size:28px; font-weight:700; color: <?= $realBalance >= 0 ? '#1e8449' : '#922b21' ?>;">
                            <?= formatCurrency($realBalance) ?>
                        </div>
                        <div style="font-size:12px; color:#666; margin-top:4px;">
                            মোট জমা: <?= formatCurrency($totalDeposit) ?> | উত্তোলন: <?= formatCurrency($totalWithdrawn) ?>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 mt-3">
                        <?php if (isAdminLoggedIn()): ?>
                        <a href="/admin/members.php?action=view&id=<?= $searchResult['id'] ?>" class="btn btn-primary btn-sm">
                            <i data-feather="eye" style="width:14px;height:14px"></i> সম্পূর্ণ প্রোফাইল দেখুন
                        </a>
                        <?php else: ?>
                        <button class="btn btn-primary btn-sm" disabled>
                            <i data-feather="eye" style="width:14px;height:14px"></i> বিস্তারিত দেখুন (অ্যাডমিন)
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Details Section -->
                <div class="col-md-8">
                    <div class="row g-3">
                        <!-- Personal Info -->
                        <div class="col-md-6">
                            <div class="info-card">
                                <h6 class="info-title">
                                    <i data-feather="user" style="width:14px;height:14px"></i> ব্যক্তিগত তথ্য
                                </h6>
                                <div class="info-item">
                                    <span class="info-label">সদস্য আইডি</span>
                                    <span class="info-value"><code><?= clean($searchResult['member_id']) ?></code></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">নাম</span>
                                    <span class="info-value"><?= clean($searchResult['name']) ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">ফোন</span>
                                    <span class="info-value"><?= clean($searchResult['phone']) ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">ইমেইল</span>
                                    <span class="info-value"><?= clean($searchResult['email'] ?? 'N/A') ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Account Info -->
                        <div class="col-md-6">
                            <div class="info-card">
                                <h6 class="info-title">
                                    <i data-feather="briefcase" style="width:14px;height:14px"></i> অ্যাকাউন্ট তথ্য
                                </h6>
                                <div class="info-item">
                                    <span class="info-label">যোগদান</span>
                                    <span class="info-value"><?= formatDate($searchResult['join_date']) ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">অবস্থা</span>
                                    <span class="info-value">
                                        <span class="badge <?= $searchResult['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>">
                                            <?= $searchResult['status'] === 'active' ? 'সক্রিয়' : 'নিষ্ক্রিয়' ?>
                                        </span>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">মোট জমা</span>
                                    <span class="info-value amount-positive"><?= formatCurrency($totalDeposit) ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">মোট উত্তোলন</span>
                                    <span class="info-value amount-negative"><?= formatCurrency($totalWithdrawn) ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Loan Summary -->
                        <div class="col-md-6">
                            <div class="info-card">
                                <h6 class="info-title">
                                    <i data-feather="credit-card" style="width:14px;height:14px"></i> ঋণ তথ্য
                                </h6>
                                <div class="info-item">
                                    <span class="info-label">মোট ঋণ</span>
                                    <span class="info-value"><?= $searchResult['total_loans'] ?? 0 ?> টি</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">সক্রিয় ঋণ</span>
                                    <span class="info-value"><?= $searchResult['active_loans'] ?? 0 ?> টি</span>
                                </div>
                                <?php if (($searchResult['active_loans'] ?? 0) > 0): ?>
                                <div class="info-item">
                                    <span class="info-label">স্ট্যাটাস</span>
                                    <span class="info-value text-warning">⏳ চলমান</span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="col-md-6">
                            <div class="info-card">
                                <h6 class="info-title">
                                    <i data-feather="zap" style="width:14px;height:14px"></i> দ্রুত কর্ম
                                </h6>
                                <?php if (isAdminLoggedIn()): ?>
                                <div class="d-grid gap-2">
                                    <a href="/admin/deposits.php?member_id=<?= $searchResult['id'] ?>" class="btn btn-success btn-sm">
                                        <i data-feather="plus-circle" style="width:14px;height:14px"></i> জমা দিন
                                    </a>
                                    <a href="/admin/loans.php?member_id=<?= $searchResult['id'] ?>" class="btn btn-info btn-sm text-white">
                                        <i data-feather="briefcase" style="width:14px;height:14px"></i> ঋণ দেখুন
                                    </a>
                                    <a href="/admin/monthly_dues.php?member_id=<?= $searchResult['id'] ?>" class="btn btn-warning btn-sm">
                                        <i data-feather="calendar" style="width:14px;height:14px"></i> চাঁদা দেখুন
                                    </a>
                                    <a href="/admin/withdrawals.php?member_id=<?= $searchResult['id'] ?>" class="btn btn-danger btn-sm">
                                        <i data-feather="arrow-up-circle" style="width:14px;height:14px"></i> উত্তোলন ইতিহাস
                                    </a>
                                </div>
                                <?php else: ?>
                                <p class="text-muted small mb-0">
                                    <i data-feather="lock" style="width:14px;height:14px"></i>
                                    এই কর্ম সম্পাদনের জন্য অ্যাডমিন লগইন প্রয়োজন
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================
         WITHDRAWAL HISTORY TABLE
         ============================================ -->
    <div class="card mt-4">
        <div class="card-header">
            <span class="card-title">
                <i data-feather="arrow-up-circle" style="width:16px;height:16px"></i> 
                উত্তোলনের ইতিহাস
            </span>
            <span class="badge bg-secondary"><?= count($withdrawals) ?> টি</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>তারিখ</th>
                        <th>পরিমাণ</th>
                        <th>ধরন</th>
                        <th>রসিদ নং</th>
                        <th>কারণ</th>
                        <th>অবস্থা</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($withdrawals)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i data-feather="inbox" style="width:32px;height:32px;display:block;margin:0 auto 10px"></i>
                            এই সদস্যের কোনো উত্তোলন নেই।
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($withdrawals as $w): ?>
                    <tr>
                        <td>
                            <?= formatDate($w['withdrawal_date']) ?>
                            <br><small class="text-muted">
                                <?php 
                                // Use timeAgo from functions.php (if exists)
                                if (function_exists('timeAgo')) {
                                    echo timeAgo($w['withdrawal_date']);
                                }
                                ?>
                            </small>
                        </td>
                        <td class="amount-negative"><?= formatCurrency($w['amount']) ?></td>
                        <td>
                            <?php 
                            $wTypeMap = [
                                'partial' => 'আংশিক',
                                'full' => 'সম্পূর্ণ',
                                'profit' => 'লাভ',
                                'emergency' => 'জরুরি'
                            ];
                            ?>
                            <span class="badge bg-light text-dark"><?= $wTypeMap[$w['withdrawal_type']] ?? $w['withdrawal_type'] ?></span>
                        </td>
                        <td><code style="font-size:11px"><?= clean($w['receipt_no']) ?></code></td>
                        <td style="font-size:12px;max-width:150px;word-wrap:break-word;">
                            <?= clean($w['reason'] ?? '-') ?>
                        </td>
                        <td>
                            <span class="badge bg-success">✅ সম্পন্ন</span>
                            <?php if ($w['approved_at']): ?>
                            <br><small class="text-muted" style="font-size:9px"><?= formatDate($w['approved_at'], 'd M Y') ?></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!empty($withdrawals)): ?>
                    <tfoot>
                        <tr style="font-weight:bold;background:#f8f9fa">
                            <td colspan="1" class="text-end">সর্বমোট:</td>
                            <td class="amount-negative">
                                <?= formatCurrency(array_sum(array_column($withdrawals, 'amount'))) ?>
                            </td>
                            <td colspan="4"></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tips Section -->
    <div class="card mt-4">
        <div class="card-body">
            <div class="d-flex align-items-start gap-3">
                <i data-feather="lightbulb" style="width:24px;height:24px;color:#F39C12"></i>
                <div>
                    <h6 class="mb-1">অনুসন্ধান টিপস</h6>
                    <ul class="small text-muted mb-0" style="padding-left:20px">
                        <li><strong>ফোন নম্বর:</strong> 017XXXXXXXX বা 019XXXXXXXX ফরম্যাটে লিখুন</li>
                        <li><strong>সদস্য আইডি:</strong> SM20240001 ফরম্যাটে লিখুন</li>
                        <li>আংশিক নম্বর দিয়েও খুঁজতে পারেন (যেমন: 017 বা SM2024)</li>
                        <li>সঠিক ফলাফল পেতে সম্পূর্ণ নম্বর ব্যবহার করুন</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Search Box */
.search-box {
    position: relative;
}
.search-box .search-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    width: 20px;
    height: 20px;
    color: #999;
}
.search-box input {
    padding-left: 44px;
}

/* Member Profile Avatar */
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

/* Info Cards */
.info-card {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 14px 16px;
    height: 100%;
}
.info-title {
    font-size: 13px;
    font-weight: 600;
    color: #1B4F72;
    margin-bottom: 12px;
    border-bottom: 2px solid #e9ecef;
    padding-bottom: 8px;
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
.amount-positive {
    color: #27AE60;
    font-weight: 600;
}
.amount-negative {
    color: #E74C3C;
    font-weight: 600;
}

/* Balance Display */
.balance-display {
    transition: all 0.3s ease;
}
.balance-display:hover {
    transform: scale(1.02);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

/* Card */
.card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}
.card-header {
    background: white;
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
    font-size: 15px;
    font-weight: 600;
    color: #2C3E50;
    display: flex;
    align-items: center;
    gap: 6px;
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
    font-size: 12px;
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

/* Responsive */
@media (max-width: 768px) {
    .member-profile-avatar {
        width: 60px;
        height: 60px;
        font-size: 20px;
    }
    .balance-display {
        padding: 12px !important;
    }
    .balance-display div:last-child {
        font-size: 11px !important;
    }
    .info-item {
        font-size: 12px;
    }
    .table td, .table th {
        padding: 6px 8px;
        font-size: 12px;
    }
}
</style>

<?php
// Helper function for avatar color
function getColor($name) {
    $colors = ['#1B4F72', '#2E86C1', '#117A65', '#C0392B', '#8E44AD', '#D35400', '#27AE60', '#2980B9'];
    return $colors[abs(crc32($name)) % count($colors)];
}

// Include footer
require_once __DIR__ . '/../includes/footer.php';
?>