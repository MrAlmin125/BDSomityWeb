<?php
// member/deposits.php
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
requireMember();
$member = getCurrentMember();
$mId = $member['id'];

$page = max(1,(int)($_GET['page'] ?? 1));
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build filter conditions
$conditions = "member_id = ?";
$params = [$mId];
$types = 'i';

if ($filter == 'monthly') {
    $conditions .= " AND deposit_type = 'monthly'";
} elseif ($filter == 'special') {
    $conditions .= " AND deposit_type = 'special'";
} elseif ($filter == 'this_month') {
    $conditions .= " AND MONTH(deposit_date) = MONTH(CURRENT_DATE()) AND YEAR(deposit_date) = YEAR(CURRENT_DATE())";
} elseif ($filter == 'this_year') {
    $conditions .= " AND YEAR(deposit_date) = YEAR(CURRENT_DATE())";
}

if (!empty($search)) {
    $conditions .= " AND (receipt_no LIKE ? OR note LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'ss';
}

$sql = "SELECT * FROM deposits WHERE $conditions ORDER BY deposit_date DESC";
$paged = paginate($sql, $types, $params, $page);

// Get statistics
$totalDeposit = queryOne("SELECT COALESCE(SUM(amount),0) AS t FROM deposits WHERE member_id=?",'i',[$mId])['t'];
$monthlyTotal = queryOne("SELECT COALESCE(SUM(amount),0) AS t FROM deposits WHERE member_id=? AND deposit_type='monthly' AND MONTH(deposit_date)=MONTH(CURRENT_DATE()) AND YEAR(deposit_date)=YEAR(CURRENT_DATE())",'i',[$mId])['t'];
$specialTotal = queryOne("SELECT COALESCE(SUM(amount),0) AS t FROM deposits WHERE member_id=? AND deposit_type='special'",'i',[$mId])['t'];
$depositCount = $paged['total'];
$currentPage = $paged['page'];
$totalPages = $paged['total_pages'];

// Get member's monthly due amount from settings
$monthlyDue = queryOne("SELECT key_value FROM settings WHERE key_name='monthly_due_amount'");
$monthlyDue = $monthlyDue ? (float)$monthlyDue['key_value'] : 2000;

// Calculate current month status
$currentMonthDeposits = queryOne("SELECT COALESCE(SUM(amount),0) AS t FROM deposits WHERE member_id=? AND MONTH(deposit_date)=MONTH(CURRENT_DATE()) AND YEAR(deposit_date)=YEAR(CURRENT_DATE())",'i',[$mId])['t'];
$isCurrentMonthPaid = $currentMonthDeposits >= $monthlyDue;

// Calculate year summary
$yearTotal = queryOne("SELECT COALESCE(SUM(amount),0) AS t FROM deposits WHERE member_id=? AND YEAR(deposit_date)=YEAR(CURRENT_DATE())",'i',[$mId])['t'];
$monthsInYear = date('n');
$expectedYearTotal = $monthlyDue * $monthsInYear;
$yearPercentage = $expectedYearTotal > 0 ? min(100, round(($yearTotal / $expectedYearTotal) * 100)) : 0;

// ============================================
// CURRENCY FORMAT FUNCTION - No .00 unless poisha exists
// ============================================
function formatCurrencyClean($amount) {
    // Check if amount has decimal part
    $hasDecimal = ($amount - floor($amount)) > 0.0001;
    
    if ($hasDecimal) {
        // Show with 2 decimal places if poisha exists
        return '৳' . number_format($amount, 2);
    } else {
        // Show without decimal if no poisha
        return '৳' . number_format($amount, 0);
    }
}

// Also override the global formatCurrency function using a different name
// We'll use formatCurrencyClean() throughout this page

// Header Show
require_once __DIR__ . '/includes/header.php';
?>
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>সঞ্চয় তালিকা - <?= htmlspecialchars($member['full_name'] ?? '') ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    * {
      font-family: 'Hind Siliguri', sans-serif;
    }
    
    body {
      background: #f0f2f5;
      padding-bottom: 80px;
    }
    
    /* ============================================
       TOP BAR STYLES
    ============================================ */
    .topbar {
      background: linear-gradient(135deg, #0d5e4e, #117A65);
      color: white;
      padding: 16px 20px;
      display: flex;
      align-items: center;
      gap: 14px;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 4px 20px rgba(17, 122, 101, 0.25);
      border-bottom: 3px solid #1ABC9C;
    }

    .topbar a {
      color: white;
      text-decoration: none;
      transition: transform 0.3s ease;
    }

    .topbar a:hover {
      transform: scale(1.1);
    }

    .topbar .topbar-title {
      font-size: 18px;
      font-weight: 700;
      flex: 1;
    }

    .topbar .topbar-badge {
      background: rgba(255,255,255,0.2);
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 500;
    }

    /* ============================================
       MAIN CONTENT
    ============================================ */
    .main {
      padding: 16px;
      max-width: 650px;
      margin: 0 auto;
    }

    /* ============================================
       STAT CARDS
    ============================================ */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
      gap: 12px;
      margin-bottom: 20px;
    }

    .stat-card {
      background: white;
      border-radius: 14px;
      padding: 16px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06);
      transition: all 0.3s ease;
      border: 1px solid rgba(0,0,0,0.04);
      position: relative;
      overflow: hidden;
    }

    .stat-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(17, 122, 101, 0.12);
    }

    .stat-card .stat-label {
      font-size: 12px;
      color: #6b7280;
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .stat-card .stat-value {
      font-size: 22px;
      font-weight: 800;
      color: #1a202c;
      margin-top: 4px;
    }

    .stat-card .stat-icon {
      position: absolute;
      top: 12px;
      right: 12px;
      opacity: 0.15;
    }

    .stat-card .stat-icon svg {
      width: 32px;
      height: 32px;
    }

    .stat-card.total .stat-value { color: #117A65; }
    .stat-card.monthly .stat-value { color: #2563EB; }
    .stat-card.special .stat-value { color: #7C3AED; }
    .stat-card.count .stat-value { color: #DC2626; }

    .stat-card.primary {
      background: linear-gradient(135deg, #117A65, #0d5e4e);
      color: white;
    }

    .stat-card.primary .stat-label { color: rgba(255,255,255,0.8); }
    .stat-card.primary .stat-value { color: white; }
    .stat-card.primary .stat-icon { opacity: 0.2; }

    /* ============================================
       MONTHLY STATUS CARD
    ============================================ */
    .monthly-status {
      background: white;
      border-radius: 14px;
      padding: 16px 20px;
      margin-bottom: 20px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06);
      border-left: 4px solid #117A65;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .monthly-status .status-text {
      font-weight: 600;
      font-size: 15px;
    }

    .monthly-status .status-badge {
      padding: 4px 16px;
      border-radius: 20px;
      font-size: 13px;
      font-weight: 600;
    }

    .monthly-status .status-badge.paid {
      background: #D1FAE5;
      color: #065F46;
    }

    .monthly-status .status-badge.unpaid {
      background: #FEE2E2;
      color: #991B1B;
    }

    /* ============================================
       FILTER & SEARCH
    ============================================ */
    .filter-section {
      background: white;
      border-radius: 14px;
      padding: 16px;
      margin-bottom: 16px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }

    .filter-buttons {
      display: flex;
      gap: 6px;
      flex-wrap: wrap;
      margin-bottom: 12px;
    }

    .filter-btn {
      padding: 5px 14px;
      border-radius: 20px;
      border: 1.5px solid #e5e7eb;
      background: white;
      font-size: 12px;
      font-weight: 500;
      color: #4b5563;
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
      font-family: 'Hind Siliguri', sans-serif;
    }

    .filter-btn:hover {
      border-color: #117A65;
      color: #117A65;
      transform: translateY(-1px);
    }

    .filter-btn.active {
      background: #117A65;
      color: white;
      border-color: #117A65;
      box-shadow: 0 4px 12px rgba(17, 122, 101, 0.3);
    }

    .search-box {
      display: flex;
      gap: 8px;
      align-items: center;
    }

    .search-box input {
      flex: 1;
      padding: 8px 14px;
      border: 1.5px solid #e5e7eb;
      border-radius: 10px;
      font-size: 14px;
      font-family: 'Hind Siliguri', sans-serif;
      outline: none;
      transition: border-color 0.3s ease;
    }

    .search-box input:focus {
      border-color: #117A65;
      box-shadow: 0 0 0 3px rgba(17, 122, 101, 0.1);
    }

    .search-box button {
      padding: 8px 16px;
      background: #117A65;
      color: white;
      border: none;
      border-radius: 10px;
      font-weight: 600;
      font-family: 'Hind Siliguri', sans-serif;
      transition: all 0.3s ease;
      cursor: pointer;
    }

    .search-box button:hover {
      background: #0d5e4e;
      transform: translateY(-1px);
    }

    /* ============================================
       TRANSACTION LIST
    ============================================ */
    .transaction-list {
      background: white;
      border-radius: 14px;
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }

    .list-item {
      padding: 14px 18px;
      border-bottom: 1px solid #f3f4f6;
      display: flex;
      justify-content: space-between;
      align-items: center;
      transition: all 0.3s ease;
    }

    .list-item:hover {
      background: #f9fafb;
    }

    .list-item:last-child {
      border-bottom: none;
    }

    .list-item .item-left {
      display: flex;
      flex-direction: column;
      gap: 2px;
    }

    .list-item .item-date {
      font-weight: 600;
      font-size: 14px;
      color: #1a202c;
    }

    .list-item .item-meta {
      font-size: 12px;
      color: #6b7280;
    }

    .list-item .item-meta .badge-type {
      display: inline-block;
      padding: 1px 10px;
      border-radius: 12px;
      font-size: 10px;
      font-weight: 600;
    }

    .badge-type.monthly {
      background: #DBEAFE;
      color: #1E40AF;
    }

    .badge-type.special {
      background: #EDE9FE;
      color: #5B21B6;
    }

    .badge-type.other {
      background: #F3F4F6;
      color: #4B5563;
    }

    .list-item .item-amount {
      font-weight: 700;
      font-size: 16px;
      color: #117A65;
    }

    .list-item .item-amount.negative {
      color: #DC2626;
    }

    .list-item .item-receipt {
      font-size: 11px;
      color: #9ca3af;
    }

    /* ============================================
       PAGINATION
    ============================================ */
    .pagination-wrap {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 16px 4px 4px;
      font-size: 13px;
      color: #6b7280;
    }

    .pagination-wrap .page-links {
      display: flex;
      gap: 4px;
    }

    .pagination-wrap .page-links a {
      padding: 4px 12px;
      border-radius: 8px;
      border: 1px solid #e5e7eb;
      color: #4b5563;
      text-decoration: none;
      font-size: 13px;
      transition: all 0.3s ease;
      font-family: 'Hind Siliguri', sans-serif;
    }

    .pagination-wrap .page-links a:hover {
      background: #117A65;
      color: white;
      border-color: #117A65;
    }

    .pagination-wrap .page-links .current {
      background: #117A65;
      color: white;
      border-color: #117A65;
      padding: 4px 12px;
      border-radius: 8px;
    }

    /* ============================================
       EMPTY STATE
    ============================================ */
    .empty-state {
      text-align: center;
      padding: 40px 20px;
      color: #6b7280;
    }

    .empty-state svg {
      width: 64px;
      height: 64px;
      color: #d1d5db;
      margin-bottom: 12px;
    }

    .empty-state h5 {
      font-weight: 600;
      color: #374151;
    }

    /* ============================================
       ANIMATIONS
    ============================================ */
    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .animate-in {
      animation: fadeInUp 0.5s ease forwards;
    }

    .animate-in.delay-1 { animation-delay: 0.1s; }
    .animate-in.delay-2 { animation-delay: 0.2s; }
    .animate-in.delay-3 { animation-delay: 0.3s; }
    .animate-in.delay-4 { animation-delay: 0.4s; }

    /* ============================================
       RESPONSIVE
    ============================================ */
    @media (max-width: 768px) {
      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
      }

      .stat-card .stat-value {
        font-size: 18px;
      }

      .stat-card .stat-label {
        font-size: 10px;
      }

      .topbar-title {
        font-size: 16px;
      }

      .filter-btn {
        font-size: 10px;
        padding: 4px 10px;
      }

      .list-item {
        padding: 12px 14px;
      }

      .list-item .item-amount {
        font-size: 14px;
      }
    }

    @media (max-width: 480px) {
      .stats-grid {
        grid-template-columns: 1fr 1fr;
        gap: 8px;
      }

      .stat-card {
        padding: 12px;
      }

      .stat-card .stat-value {
        font-size: 16px;
      }

      .filter-buttons {
        gap: 4px;
      }

      .filter-btn {
        font-size: 9px;
        padding: 3px 8px;
      }

      .monthly-status {
        flex-direction: column;
        gap: 8px;
        text-align: center;
      }
    }

    /* Scrollbar styling */
    ::-webkit-scrollbar {
      width: 6px;
    }

    ::-webkit-scrollbar-track {
      background: #f1f1f1;
    }

    ::-webkit-scrollbar-thumb {
      background: #117A65;
      border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb:hover {
      background: #0d5e4e;
    }

    /* Quick action button */
    .quick-action {
      position: fixed;
      bottom: 90px;
      right: 20px;
      z-index: 50;
    }

    .quick-action a {
      display: flex;
      align-items: center;
      gap: 8px;
      background: #117A65;
      color: white;
      padding: 12px 20px;
      border-radius: 50px;
      text-decoration: none;
      font-weight: 600;
      font-size: 14px;
      box-shadow: 0 8px 30px rgba(17, 122, 101, 0.35);
      transition: all 0.3s ease;
      font-family: 'Hind Siliguri', sans-serif;
    }

    .quick-action a:hover {
      transform: translateY(-2px) scale(1.02);
      box-shadow: 0 12px 40px rgba(17, 122, 101, 0.45);
      color: white;
    }

    @media (max-width: 480px) {
      .quick-action a {
        padding: 10px 16px;
        font-size: 12px;
      }
    }
  </style>
</head>
<body>

<!-- ============================================
     TOP BAR
     ============================================ -->
<div class="topbar">
  <a href="/member/index.php">
    <i data-feather="arrow-left" style="width:22px;height:22px"></i>
  </a>
  <span class="topbar-title">সঞ্চয় বিবরণী</span>
  <span class="topbar-badge"><?= date('F Y') ?></span>
</div>

<!-- ============================================
     MAIN CONTENT
     ============================================ -->
<div class="main">

  <!-- ============================================
       STATISTICS CARDS
       ============================================ -->
  <div class="stats-grid">
    <div class="stat-card total animate-in">
      <div class="stat-icon"><i data-feather="database"></i></div>
      <div class="stat-label">মোট সঞ্চয়</div>
      <div class="stat-value"><?= formatCurrencyClean((float)$totalDeposit) ?></div>
    </div>

    <div class="stat-card monthly animate-in delay-1">
      <div class="stat-icon"><i data-feather="calendar"></i></div>
      <div class="stat-label">এই মাসের সঞ্চয়</div>
      <div class="stat-value"><?= formatCurrencyClean((float)$monthlyTotal) ?></div>
    </div>

    <div class="stat-card special animate-in delay-2">
      <div class="stat-icon"><i data-feather="star"></i></div>
      <div class="stat-label">বিশেষ সঞ্চয়</div>
      <div class="stat-value"><?= formatCurrencyClean((float)$specialTotal) ?></div>
    </div>

    <div class="stat-card count animate-in delay-3">
      <div class="stat-icon"><i data-feather="list"></i></div>
      <div class="stat-label">মোট লেনদেন</div>
      <div class="stat-value"><?= number_format($depositCount) ?></div>
    </div>
  </div>

  <!-- ============================================
       MONTHLY STATUS
       ============================================ -->
  <div class="monthly-status animate-in delay-4">
    <div>
      <div class="status-text"><?= date('F Y') ?> মাসের সঞ্চয়</div>
      <div style="font-size:13px;color:#6b7280;">
        লক্ষ্য: <?= formatCurrencyClean($monthlyDue) ?> | জমা: <?= formatCurrencyClean((float)$currentMonthDeposits) ?>
      </div>
    </div>
    <span class="status-badge <?= $isCurrentMonthPaid ? 'paid' : 'unpaid' ?>">
      <?= $isCurrentMonthPaid ? '✅ পরিশোধিত' : '⏳ বাকি' ?>
    </span>
  </div>

  <!-- ============================================
       FILTER & SEARCH
       ============================================ -->
  <div class="filter-section">
    <div class="filter-buttons">
      <a href="?filter=all" class="filter-btn <?= $filter == 'all' ? 'active' : '' ?>">সব</a>
      <a href="?filter=monthly" class="filter-btn <?= $filter == 'monthly' ? 'active' : '' ?>">মাসিক</a>
      <a href="?filter=special" class="filter-btn <?= $filter == 'special' ? 'active' : '' ?>">বিশেষ</a>
      <a href="?filter=this_month" class="filter-btn <?= $filter == 'this_month' ? 'active' : '' ?>">এই মাস</a>
      <a href="?filter=this_year" class="filter-btn <?= $filter == 'this_year' ? 'active' : '' ?>">এই বছর</a>
    </div>

    <form method="GET" class="search-box">
      <?php if ($filter != 'all'): ?>
        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
      <?php endif; ?>
      <input type="text" name="search" placeholder="🔍 রসিদ নং বা নোট অনুসন্ধান..." value="<?= htmlspecialchars($search) ?>">
      <button type="submit">খুঁজুন</button>
      <?php if (!empty($search)): ?>
        <a href="?filter=<?= $filter ?>" style="color:#DC2626;text-decoration:none;font-size:20px;font-weight:700;">×</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- ============================================
       TRANSACTION LIST
       ============================================ -->
  <div class="transaction-list">
    <?php if (!empty($paged['data'])): ?>
      <?php foreach ($paged['data'] as $index => $d): ?>
        <div class="list-item" style="animation: fadeInUp 0.4s ease forwards; animation-delay: <?= $index * 0.05 + 0.1 ?>s; opacity:0;">
          <div class="item-left">
            <div class="item-date">
              <?= formatDate($d['deposit_date']) ?>
              <span class="badge-type <?= strtolower($d['deposit_type']) ?>">
                <?= $d['deposit_type'] == 'monthly' ? 'মাসিক' : ($d['deposit_type'] == 'special' ? 'বিশেষ' : $d['deposit_type']) ?>
              </span>
            </div>
            <div class="item-meta">
              <?php if (!empty($d['receipt_no'])): ?>
                রসিদ: <?= clean($d['receipt_no']) ?>
              <?php endif; ?>
              <?php if (!empty($d['note'])): ?>
                <span style="color:#9ca3af;">| <?= clean($d['note']) ?></span>
              <?php endif; ?>
            </div>
          </div>
          <div class="item-amount"><?= formatCurrencyClean((float)$d['amount']) ?></div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="empty-state">
        <i data-feather="inbox"></i>
        <h5>কোনো সঞ্চয় নেই</h5>
        <p style="font-size:14px;">আপনার এখনো কোনো সঞ্চয় লেনদেন নেই।</p>
      </div>
    <?php endif; ?>
  </div>

  <!-- ============================================
       PAGINATION
       ============================================ -->
  <?php if ($totalPages > 1): ?>
    <div class="pagination-wrap">
      <span>
        <?= ($currentPage - 1) * 25 + 1 ?> - <?= min($currentPage * 25, $depositCount) ?> 
        (<?= number_format($depositCount) ?> টি)
      </span>
      <div class="page-links">
        <?php if ($currentPage > 1): ?>
          <a href="?page=<?= $currentPage - 1 ?><?= $filter != 'all' ? '&filter='.$filter : '' ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?>">←</a>
        <?php endif; ?>
        
        <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
          <?php if ($i == $currentPage): ?>
            <span class="current"><?= $i ?></span>
          <?php else: ?>
            <a href="?page=<?= $i ?><?= $filter != 'all' ? '&filter='.$filter : '' ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?>"><?= $i ?></a>
          <?php endif; ?>
        <?php endfor; ?>
        
        <?php if ($currentPage < $totalPages): ?>
          <a href="?page=<?= $currentPage + 1 ?><?= $filter != 'all' ? '&filter='.$filter : '' ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?>">→</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

</div>

<!-- ============================================
     QUICK ACTION BUTTON - Add Deposit
     ============================================ -->
<!-- <div class="quick-action">
  <a href="/member/index.php">
    <i data-feather="plus-circle" style="width:20px;height:20px"></i>
    নতুন সঞ্চয়
  </a>
</div>

<!-- ============================================
     SCRIPTS
     ============================================ -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  if (typeof feather !== 'undefined') {
    feather.replace();
  }
});
</script>

<!-- ============================================
     INCLUDE FOOTER (Bottom Nav)
     ============================================ -->
<?php require_once __DIR__ . '/includes/footer.php'; ?>

</body>
</html>