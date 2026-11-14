<?php
// =====================================================
// admin/fine_payments_list.php - Fine Payment Transactions List
// =====================================================

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/functions.php';
startSecureSession();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_role'])) {
    header('Location: /admin/login.php');
    exit;
}

$pageTitle = 'জরিমানা পরিশোধের তালিকা';
$conn = conn();

// ============================================
// HELPER FUNCTION FOR AVATAR GENERATION
// ============================================
function getAvatarColor($name) {
    $colors = ['#1B4F72', '#2E86C1', '#117A65', '#C0392B', '#8E44AD', '#D35400', '#27AE60', '#2980B9', '#E67E22', '#16A085'];
    $hash = 0;
    for ($i = 0; $i < strlen($name); $i++) {
        $hash = ($hash * 31 + ord($name[$i])) & 0xFFFFFFFF;
    }
    return $colors[abs($hash) % count($colors)];
}

function generateAvatar($name, $size = 32) {
    $firstLetter = mb_substr($name, 0, 1, 'UTF-8');
    $color = getAvatarColor($name);
    
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 ' . $size . ' ' . $size . '">';
    $svg .= '<rect width="' . $size . '" height="' . $size . '" rx="' . ($size/2) . '" fill="' . $color . '"/>';
    $fontSize = $size * 0.5;
    $svg .= '<text x="' . ($size/2) . '" y="' . ($size/2 + $size * 0.04) . '" ';
    $svg .= 'font-family="Arial, sans-serif" font-size="' . $fontSize . '" font-weight="bold" fill="white" text-anchor="middle" dominant-baseline="central">';
    $svg .= htmlspecialchars($firstLetter);
    $svg .= '</text>';
    $svg .= '</svg>';
    
    return 'data:image/svg+xml;charset=utf-8,' . rawurlencode($svg);
}

function getMemberPhoto($member) {
    if (!empty($member['photo']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $member['photo'])) {
        return $member['photo'];
    }
    return generateAvatar($member['name'], 32);
}

// ============================================
// GET FILTERS
// ============================================
$member_filter = isset($_GET['member_id']) ? intval($_GET['member_id']) : 0;
$method_filter = isset($_GET['method']) ? trim($_GET['method']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// ============================================
// BUILD WHERE CLAUSE
// ============================================
$where_clauses = [];
$params = [];
$types = '';

if ($member_filter > 0) {
    $where_clauses[] = "f.member_id = ?";
    $params[] = $member_filter;
    $types .= 'i';
}

if (!empty($method_filter)) {
    $where_clauses[] = "fp.payment_method = ?";
    $params[] = $method_filter;
    $types .= 's';
}

if (!empty($date_from)) {
    $where_clauses[] = "fp.payment_date >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $where_clauses[] = "fp.payment_date <= ?";
    $params[] = $date_to;
    $types .= 's';
}

if (!empty($search)) {
    $where_clauses[] = "(m.name LIKE ? OR m.member_id LIKE ? OR m.phone LIKE ? OR fp.transaction_no LIKE ? OR fp.receipt_no LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sssss';
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// ============================================
// GET TOTAL COUNT FOR PAGINATION
// ============================================
$count_sql = "
    SELECT COUNT(*) as total 
    FROM fine_payments fp
    JOIN fines f ON fp.fine_id = f.id
    JOIN members m ON f.member_id = m.id
    $where_sql
";

$stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$total_records = $result->fetch_assoc()['total'] ?? 0;
$total_pages = ceil($total_records / $limit);
$stmt->close();

// ============================================
// GET FINE PAYMENTS LIST
// ============================================
$sql = "
    SELECT 
        fp.*,
        f.id as fine_id,
        f.member_id,
        f.month_year,
        f.fine_amount,
        f.status as fine_status,
        f.due_date as fine_due_date,
        m.name as member_name,
        m.member_id as member_code,
        m.phone,
        m.photo as member_photo,
        (f.fine_amount - f.paid_amount - f.waived_amount) as fine_remaining
    FROM fine_payments fp
    JOIN fines f ON fp.fine_id = f.id
    JOIN members m ON f.member_id = m.id
    $where_sql
    ORDER BY fp.payment_date DESC, fp.created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ============================================
// GET MEMBERS FOR DROPDOWN
// ============================================
$members = [];
$result = $conn->query("SELECT id, member_id, name FROM members WHERE status = 'active' ORDER BY name ASC");
if ($result) {
    $members = $result->fetch_all(MYSQLI_ASSOC);
}

// ============================================
// GET SUMMARY STATISTICS
// ============================================
$total_amount = 0;
$total_cash = 0;
$total_bank = 0;
$total_mobile = 0;
$total_other = 0;

try {
    $summary_sql = "
        SELECT 
            COALESCE(SUM(fp.amount), 0) as total,
            COALESCE(SUM(CASE WHEN fp.payment_method = 'cash' THEN fp.amount ELSE 0 END), 0) as cash,
            COALESCE(SUM(CASE WHEN fp.payment_method = 'bank' THEN fp.amount ELSE 0 END), 0) as bank,
            COALESCE(SUM(CASE WHEN fp.payment_method = 'mobile' THEN fp.amount ELSE 0 END), 0) as mobile,
            COALESCE(SUM(CASE WHEN fp.payment_method = 'other' THEN fp.amount ELSE 0 END), 0) as other
        FROM fine_payments fp
        JOIN fines f ON fp.fine_id = f.id
        JOIN members m ON f.member_id = m.id
        $where_sql
    ";
    
    $stmt = $conn->prepare($summary_sql);
    if (!empty($params)) {
        // Remove limit and offset params
        $summary_params = array_slice($params, 0, -2);
        $summary_types = substr($types, 0, -2);
        if (!empty($summary_params)) {
            $stmt->bind_param($summary_types, ...$summary_params);
        }
    }
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $total_amount = $row['total'] ?? 0;
        $total_cash = $row['cash'] ?? 0;
        $total_bank = $row['bank'] ?? 0;
        $total_mobile = $row['mobile'] ?? 0;
        $total_other = $row['other'] ?? 0;
    }
    $stmt->close();
} catch (Exception $e) {}

// Get admin info
$admin = getCurrentAdmin();
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-3">

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h4>
        <i data-feather="credit-card" style="width:20px;height:20px"></i> 
        জরিমানা পরিশোধের তালিকা
        <small class="text-muted">মোট <?= number_format($total_records) ?> টি লেনদেন</small>
    </h4>
    <div>
        <a href="/admin/fines.php" class="btn btn-primary btn-sm">
            <i data-feather="alert-triangle" style="width:14px;height:14px"></i> জরিমানা ব্যবস্থাপনা
        </a>
        <a href="/admin/fines.php?action=add" class="btn btn-success btn-sm">
            <i data-feather="plus-circle" style="width:14px;height:14px"></i> নতুন জরিমানা
        </a>
    </div>
</div>

<!-- ============================================
     SUMMARY CARDS
     ============================================ -->
<div class="row g-2 mb-4">
    <div class="col-6 col-md-2">
        <div class="stat-card text-center" style="background: linear-gradient(135deg, #E8F8F5, #D1F2EB);">
            <div class="stat-value" style="font-size:22px;">৳<?= number_format($total_amount, 0) ?></div>
            <div class="stat-label">মোট পরিশোধ</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card text-center" style="background: linear-gradient(135deg, #EAF2F8, #D6EAF8);">
            <div class="stat-value" style="font-size:22px;">৳<?= number_format($total_cash, 0) ?></div>
            <div class="stat-label">💵 নগদ</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card text-center" style="background: linear-gradient(135deg, #FEF9E7, #FDEBD0);">
            <div class="stat-value" style="font-size:22px;">৳<?= number_format($total_bank, 0) ?></div>
            <div class="stat-label">🏦 ব্যাংক</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card text-center" style="background: linear-gradient(135deg, #F4ECF7, #EBDEF0);">
            <div class="stat-value" style="font-size:22px;">৳<?= number_format($total_mobile, 0) ?></div>
            <div class="stat-label">📱 মোবাইল ব্যাংকিং</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card text-center" style="background: linear-gradient(135deg, #FDEDEC, #FADBD8);">
            <div class="stat-value" style="font-size:22px;">৳<?= number_format($total_other, 0) ?></div>
            <div class="stat-label">🔗 অন্যান্য</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card text-center" style="background: linear-gradient(135deg, #F5EEF8, #EBDEF0);">
            <div class="stat-value" style="font-size:22px;"><?= number_format($total_records) ?></div>
            <div class="stat-label">📋 মোট লেনদেন</div>
        </div>
    </div>
</div>

<!-- ============================================
     FILTERS
     ============================================ -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="/admin/fine_payments_list.php" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">সদস্য</label>
                <select name="member_id" class="form-control">
                    <option value="">সব সদস্য</option>
                    <?php foreach ($members as $m): ?>
                    <option value="<?= $m['id'] ?>" <?= $member_filter == $m['id'] ? 'selected' : '' ?>>
                        <?= clean($m['name']) ?> (<?= clean($m['member_id']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">পদ্ধতি</label>
                <select name="method" class="form-control">
                    <option value="">সব</option>
                    <option value="cash" <?= $method_filter == 'cash' ? 'selected' : '' ?>>💵 নগদ</option>
                    <option value="bank" <?= $method_filter == 'bank' ? 'selected' : '' ?>>🏦 ব্যাংক</option>
                    <option value="mobile" <?= $method_filter == 'mobile' ? 'selected' : '' ?>>📱 মোবাইল</option>
                    <option value="other" <?= $method_filter == 'other' ? 'selected' : '' ?>>🔗 অন্যান্য</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">তারিখ থেকে</label>
                <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">তারিখ পর্যন্ত</label>
                <input type="date" name="date_to" class="form-control" value="<?= $date_to ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">অনুসন্ধান</label>
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="নাম, আইডি, ট্রানজেকশন..." value="<?= clean($search) ?>">
                    <button type="submit" class="btn btn-primary">
                        <i data-feather="search" style="width:14px;height:14px"></i>
                    </button>
                </div>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i data-feather="filter" style="width:14px;height:14px"></i> ফিল্টার করুন
                </button>
                <a href="/admin/fine_payments_list.php" class="btn btn-secondary">
                    <i data-feather="refresh-cw" style="width:14px;height:14px"></i> রিসেট
                </a>
                <button type="button" class="btn btn-success float-end" onclick="window.print()">
                    <i data-feather="printer" style="width:14px;height:14px"></i> প্রিন্ট
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================
     PAYMENTS TABLE
     ============================================ -->
<div class="card">
    <div class="card-header">
        <span class="card-title">
            <i data-feather="list" style="width:16px;height:16px"></i> 
            জরিমানা পরিশোধের তালিকা
        </span>
        <span class="badge bg-secondary"><?= number_format($total_records) ?> টি</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>সদস্য</th>
                    <th>জরিমানা</th>
                    <th>পরিশোধ</th>
                    <th>পদ্ধতি</th>
                    <th>তারিখ</th>
                    <th>লেনদেন নং</th>
                    <th>রসিদ নং</th>
                    <th>অবস্থা</th>
                    <th>কার্যক্রম</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                <tr>
                    <td colspan="10" class="text-center text-muted py-4">
                        <i data-feather="inbox" style="width:40px;height:40px;display:block;margin:0 auto 10px;opacity:0.3;"></i>
                        কোনো জরিমানা পরিশোধ পাওয়া যায়নি
                    </td>
                </tr>
                <?php else: ?>
                <?php $counter = $offset + 1; ?>
                <?php foreach ($payments as $p): 
                    $method_icons = [
                        'cash' => '💵',
                        'bank' => '🏦',
                        'mobile' => '📱',
                        'other' => '🔗'
                    ];
                    $method_icon = $method_icons[$p['payment_method']] ?? '💰';
                    
                    $status_colors = [
                        'pending' => 'warning',
                        'partially_paid' => 'info',
                        'paid' => 'success',
                        'waived' => 'secondary'
                    ];
                    $status_labels = [
                        'pending' => '⏳ বাকি',
                        'partially_paid' => '🔄 আংশিক',
                        'paid' => '✅ পরিশোধিত',
                        'waived' => '⛔ মওকুফ'
                    ];
                    $status_color = $status_colors[$p['fine_status']] ?? 'secondary';
                    $status_label = $status_labels[$p['fine_status']] ?? $p['fine_status'];
                ?>
                <tr class="clickable-row" data-href="/admin/fines.php?action=view&id=<?= $p['fine_id'] ?>">
                    <td><?= $counter++ ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <img src="<?= getMemberPhoto(['name' => $p['member_name'], 'photo' => $p['member_photo']]) ?>" 
                                 alt="Photo" style="width:32px;height:32px;border-radius:50%;object-fit:cover;border:1px solid #ddd;">
                            <div>
                                <div class="fw-semibold" style="font-size:13px;"><?= clean($p['member_name']) ?></div>
                                <code style="font-size:10px;"><?= clean($p['member_code']) ?></code>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div>৳<?= number_format($p['fine_amount'], 0) ?></div>
                        <div style="font-size:10px;color:#888;">
                            <?= date('M Y', strtotime($p['month_year'])) ?>
                        </div>
                    </td>
                    <td>
                        <span class="amount-negative fw-bold">-৳<?= number_format($p['amount'], 0) ?></span>
                        <?php if ($p['fine_remaining'] > 0): ?>
                        <div style="font-size:10px;color:#888;">বাকি: ৳<?= number_format($p['fine_remaining'], 0) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge bg-secondary">
                            <?= $method_icon ?> <?= ucfirst($p['payment_method']) ?>
                        </span>
                    </td>
                    <td>
                        <div><?= date('d M Y', strtotime($p['payment_date'])) ?></div>
                        <div style="font-size:10px;color:#888;"><?= date('h:i A', strtotime($p['created_at'])) ?></div>
                    </td>
                    <td>
                        <code><?= clean($p['transaction_no']) ?: '-' ?></code>
                    </td>
                    <td>
                        <code><?= clean($p['receipt_no']) ?: '-' ?></code>
                    </td>
                    <td>
                        <span class="badge bg-<?= $status_color ?>">
                            <?= $status_label ?>
                        </span>
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <a href="/admin/fines.php?action=view&id=<?= $p['fine_id'] ?>" class="btn btn-outline-primary">
                                <i data-feather="eye" style="width:14px;height:14px"></i>
                            </a>
                            <a href="/admin/fine_payments.php?action=delete&id=<?= $p['id'] ?>" 
                               class="btn btn-outline-danger" 
                               onclick="event.stopPropagation();return confirm('আপনি কি এই পরিশোধ মুছে ফেলতে চান?')">
                                <i data-feather="trash-2" style="width:14px;height:14px"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- ============================================
         PAGINATION
         ============================================ -->
    <?php if ($total_pages > 1): ?>
    <div class="card-footer">
        <nav>
            <ul class="pagination pagination-sm justify-content-center mb-0">
                <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?= $page-1 ?>&member_id=<?= $member_filter ?>&method=<?= urlencode($method_filter) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&search=<?= urlencode($search) ?>">
                        পূর্ববর্তী
                    </a>
                </li>
                <?php endif; ?>
                
                <?php 
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                ?>
                
                <?php if ($start_page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=1&member_id=<?= $member_filter ?>&method=<?= urlencode($method_filter) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&search=<?= urlencode($search) ?>">1</a>
                </li>
                <?php if ($start_page > 2): ?>
                <li class="page-item disabled"><span class="page-link">...</span></li>
                <?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&member_id=<?= $member_filter ?>&method=<?= urlencode($method_filter) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&search=<?= urlencode($search) ?>">
                        <?= $i ?>
                    </a>
                </li>
                <?php endfor; ?>
                
                <?php if ($end_page < $total_pages): ?>
                <?php if ($end_page < $total_pages - 1): ?>
                <li class="page-item disabled"><span class="page-link">...</span></li>
                <?php endif; ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?= $total_pages ?>&member_id=<?= $member_filter ?>&method=<?= urlencode($method_filter) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&search=<?= urlencode($search) ?>">
                        <?= $total_pages ?>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if ($page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?= $page+1 ?>&member_id=<?= $member_filter ?>&method=<?= urlencode($method_filter) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&search=<?= urlencode($search) ?>">
                        পরবর্তী
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        
        <div class="text-center text-muted mt-2" style="font-size:12px;">
            দেখানো হচ্ছে <?= $offset + 1 ?> - <?= min($offset + $limit, $total_records) ?> of <?= number_format($total_records) ?> টি
        </div>
    </div>
    <?php endif; ?>
</div>

</div><!-- /container-fluid -->

<style>
/* ============================================
   STAT CARDS
   ============================================ */
.stat-card {
    border-radius: 12px;
    padding: 16px 18px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    transition: all 0.3s;
    height: 100%;
    position: relative;
    overflow: hidden;
    text-align: center;
}
.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.1);
}
.stat-card .stat-value {
    font-size: 26px;
    font-weight: 700;
    color: #1B4F72;
    margin-bottom: 2px;
}
.stat-card .stat-label {
    font-size: 12px;
    color: #566573;
    font-weight: 500;
}

/* Cards */
.card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    margin-bottom: 0;
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
    font-size: 14px;
    font-weight: 600;
    color: #2C3E50;
    display: flex;
    align-items: center;
    gap: 6px;
}
.card-body {
    padding: 14px 16px;
}
.card-footer {
    background: white;
    border-top: 1px solid #f0f0f0;
    padding: 12px 18px;
}

.amount-positive { color: #27AE60; font-weight: 600; }
.amount-negative { color: #E74C3C; font-weight: 600; }

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
    background: #f8f9fa;
}
.table td {
    vertical-align: middle;
    padding: 10px 12px;
}
.table-hover tbody tr:hover {
    background: #f8f9fa;
}

/* Clickable Row */
.clickable-row {
    cursor: pointer;
}
.clickable-row:hover {
    background-color: #f0f8ff !important;
}

/* Responsive */
@media (max-width: 768px) {
    .stat-card .stat-value {
        font-size: 20px;
    }
    .stat-card {
        padding: 12px 14px;
    }
    .card-header {
        padding: 10px 14px;
    }
    .card-body {
        padding: 12px 14px;
    }
    .table td, .table th {
        padding: 6px 8px;
        font-size: 11px;
    }
}

/* Print Styles */
@media print {
    .btn, .btn-group, .no-print {
        display: none !important;
    }
    .card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
    .table th {
        background: #f0f0f0 !important;
    }
    .stat-card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Clickable rows for table
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>