<?php
// member/dues.php
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
requireMember();
$member = getCurrentMember();
$mId = $member['id'];

$year = (int)($_GET['year'] ?? date('Y'));
$dues = queryAll(
  "SELECT * FROM monthly_dues WHERE member_id=? AND due_year=? ORDER BY due_month ASC",
  'ii', [$mId, $year]
);
$dueSummary = getMemberDueSummary($mId);

// Header Show
require_once __DIR__ . '/includes/header.php';
?>
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>মাসিক চাঁদা</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body{font-family:'Hind Siliguri',sans-serif;background:#F4F6F9;padding-bottom:70px}
    .topbar{background:#117A65;color:white;padding:14px 16px;display:flex;align-items:center;gap:10px;position:sticky;top:0;z-index:100}
    .main{padding:14px;max-width:600px;margin:0 auto}
    .card{border:none;border-radius:14px;box-shadow:0 1px 4px rgba(0,0,0,.08);margin-bottom:14px}
    .list-item{padding:12px 16px;border-bottom:1px solid #F0F0F0;font-size:14px}
    .list-item:last-child{border-bottom:none}
    .badge-paid{background:#D5F5E3;color:#1E8449;padding:3px 8px;border-radius:6px;font-size:11px}
    .badge-unpaid{background:#FDEDEC;color:#C0392B;padding:3px 8px;border-radius:6px;font-size:11px}
    .badge-partial{background:#FEF9E7;color:#B7770D;padding:3px 8px;border-radius:6px;font-size:11px}
    .stat-row{display:flex;gap:8px;margin-bottom:14px}
    .stat-mini{flex:1;background:white;border-radius:12px;padding:12px;text-align:center;box-shadow:0 1px 4px rgba(0,0,0,.08)}
    .bottom-nav{position:fixed;bottom:0;left:0;right:0;background:white;border-top:1px solid #DEE2E6;display:flex;z-index:200}
    .bottom-nav a{flex:1;padding:10px 0;text-align:center;color:#888;text-decoration:none;font-size:10px;display:flex;flex-direction:column;align-items:center;gap:3px}
    .bottom-nav a.active{color:#117A65}
    .bottom-nav svg{width:20px;height:20px}
  </style>
</head>
<body>
<div class="topbar">
  <a href="/member/index.php" style="color:white"><i data-feather="arrow-left" style="width:20px;height:20px"></i></a>
  <span style="font-size:16px;font-weight:600">মাসিক চাঁদা</span>
</div>
<div class="main">
  <div class="stat-row">
    <div class="stat-mini">
      <div style="font-size:18px;font-weight:700;color:#117A65"><?= toBengaliNumber($dueSummary['paid_months'] ?? 0) ?></div>
      <div style="font-size:11px;color:#888">পরিশোধিত মাস</div>
    </div>
    <div class="stat-mini">
      <div style="font-size:18px;font-weight:700;color:#C0392B"><?= toBengaliNumber($dueSummary['unpaid_months'] ?? 0) ?></div>
      <div style="font-size:11px;color:#888">বকেয়া মাস</div>
    </div>
    <div class="stat-mini">
      <div style="font-size:15px;font-weight:700;color:#C0392B"><?= formatCurrency((float)$dueSummary['total_due']) ?></div>
      <div style="font-size:11px;color:#888">মোট বকেয়া</div>
    </div>
  </div>

  <!-- Year Filter -->
  <div class="d-flex gap-2 mb-3">
    <?php for($y=date('Y');$y>=date('Y')-3;$y--): ?>
    <a href="?year=<?= $y ?>" class="btn btn-sm <?= $year===$y?'btn-primary':'btn-outline-secondary' ?>"><?= $y ?></a>
    <?php endfor; ?>
  </div>

  <div class="card">
    <?php if (empty($dues)): ?>
    <div class="list-item text-muted text-center py-4"><?= $year ?> সালের কোনো চাঁদার তথ্য নেই</div>
    <?php else: ?>
    <?php foreach ($dues as $d): ?>
    <div class="list-item d-flex justify-content-between align-items-center">
      <div>
        <div style="font-weight:500"><?= bengaliMonth((int)$d['due_month']) ?> <?= $d['due_year'] ?></div>
        <?php if ($d['paid_date']): ?><div style="font-size:11px;color:#888"><?= formatDate($d['paid_date']) ?> | <?= clean($d['receipt_no'] ?? '') ?></div><?php endif; ?>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span><?= formatCurrency((float)$d['amount']) ?></span>
        <?php $bm=['paid'=>['পরিশোধিত','badge-paid'],'unpaid'=>['বকেয়া','badge-unpaid'],'partial'=>['আংশিক','badge-partial']]; ?>
        <span class="<?= $bm[$d['status']][1] ?>"><?= $bm[$d['status']][0] ?></span>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<!-- <nav class="bottom-nav">
  <a href="/member/index.php"><i data-feather="home"></i><span>হোম</span></a>
  <a href="/member/deposits.php"><i data-feather="credit-card"></i><span>সঞ্চয়</span></a>
  <a href="/member/dues.php" class="active"><i data-feather="calendar"></i><span>চাঁদা</span></a>
  <a href="/member/loans.php"><i data-feather="briefcase"></i><span>ঋণ</span></a>
  <a href="/member/profile.php"><i data-feather="user"></i><span>প্রোফাইল</span></a>
</nav>  -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>feather.replace();</script>

<!-- ============================================
     INCLUDE FOOTER (Bottom Nav)
     ============================================ -->
<?php require_once __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
