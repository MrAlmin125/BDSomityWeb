<?php
// member/loans.php
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
requireMember();
$member = getCurrentMember();
$mId = $member['id'];

$loans = queryAll("SELECT * FROM loans WHERE member_id=? ORDER BY created_at DESC",'i',[$mId]);

// Header Show
require_once __DIR__ . '/includes/header.php';
?>
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>ঋণ তথ্য</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body{font-family:'Hind Siliguri',sans-serif;background:#F4F6F9;padding-bottom:70px}
    .topbar{background:#117A65;color:white;padding:14px 16px;display:flex;align-items:center;gap:10px;position:sticky;top:0;z-index:100}
    .main{padding:14px;max-width:600px;margin:0 auto}
    .card{border:none;border-radius:14px;box-shadow:0 1px 4px rgba(0,0,0,.08);margin-bottom:14px}
    .loan-card{padding:16px}
    .badge-active{background:#D5F5E3;color:#1E8449;padding:4px 10px;border-radius:8px;font-size:12px}
    .badge-completed{background:#EBF5FB;color:#2471A3;padding:4px 10px;border-radius:8px;font-size:12px}
    .badge-defaulted{background:#FDEDEC;color:#C0392B;padding:4px 10px;border-radius:8px;font-size:12px}
    .amount-negative{color:#C0392B;font-weight:600}
    .amount-positive{color:#117A65;font-weight:600}
    .bottom-nav{position:fixed;bottom:0;left:0;right:0;background:white;border-top:1px solid #DEE2E6;display:flex;z-index:200}
    .bottom-nav a{flex:1;padding:10px 0;text-align:center;color:#888;text-decoration:none;font-size:10px;display:flex;flex-direction:column;align-items:center;gap:3px}
    .bottom-nav a.active{color:#117A65}
    .bottom-nav svg{width:20px;height:20px}
  </style>
</head>
<body>
<div class="topbar">
  <a href="/member/index.php" style="color:white"><i data-feather="arrow-left" style="width:20px;height:20px"></i></a>
  <span style="font-size:16px;font-weight:600">ঋণ তথ্য</span>
</div>
<div class="main">
  <?php if (empty($loans)): ?>
  <div class="card text-center py-5 text-muted">
    <i data-feather="briefcase" style="width:40px;height:40px;opacity:.3;margin-bottom:12px"></i>
    <div>কোনো ঋণ নেই</div>
  </div>
  <?php else: ?>
  <?php foreach ($loans as $l): ?>
  <div class="card">
    <div class="loan-card">
      <div class="d-flex justify-content-between align-items-start mb-2">
        <div>
          <code style="font-size:13px"><?= $l['loan_no'] ?></code>
          <div style="font-size:12px;color:#888"><?= $l['loan_type'] ?> | <?= formatDate($l['loan_date']) ?></div>
        </div>
        <?php $bm=['active'=>'badge-active','completed'=>'badge-completed','defaulted'=>'badge-defaulted','pending'=>'badge-active']; ?>
        <?php $bl=['active'=>'সক্রিয়','completed'=>'সম্পন্ন','defaulted'=>'খেলাপি','pending'=>'অপেক্ষমাণ']; ?>
        <span class="<?= $bm[$l['status']] ?? 'badge-active' ?>"><?= $bl[$l['status']] ?? $l['status'] ?></span>
      </div>

      <div class="row g-2">
        <div class="col-6"><div style="font-size:12px;color:#888">মূল পরিমাণ</div><div style="font-weight:600"><?= formatCurrency((float)$l['principal_amount']) ?></div></div>
        <div class="col-6"><div style="font-size:12px;color:#888">মাসিক কিস্তি</div><div style="font-weight:600"><?= formatCurrency((float)$l['monthly_installment']) ?></div></div>
        <div class="col-6"><div style="font-size:12px;color:#888">পরিশোধিত</div><div class="amount-positive"><?= formatCurrency((float)$l['total_paid']) ?></div></div>
        <div class="col-6"><div style="font-size:12px;color:#888">বকেয়া</div><div class="amount-negative"><?= formatCurrency((float)$l['outstanding_balance']) ?></div></div>
        <div class="col-12">
          <?php $pct = (float)$l['total_payable']>0 ? round(((float)$l['total_paid']/(float)$l['total_payable'])*100) : 0; ?>
          <div style="font-size:11px;color:#888;margin-bottom:4px">পরিশোধের অগ্রগতি <?= $pct ?>%</div>
          <div style="background:#EEE;border-radius:4px;height:6px">
            <div style="background:#117A65;width:<?= $pct ?>%;height:6px;border-radius:4px;transition:width .3s"></div>
          </div>
        </div>
      </div>

      <!-- Upcoming installments for this loan -->
      <?php
      $upcoming = queryAll("SELECT * FROM loan_payments WHERE loan_id=? AND status IN ('pending','partial','overdue') ORDER BY due_date ASC LIMIT 2",'i',[$l['id']]);
      ?>
      <?php if (!empty($upcoming)): ?>
      <div style="margin-top:12px;padding-top:12px;border-top:1px solid #EEE">
        <div style="font-size:12px;font-weight:600;color:#888;margin-bottom:6px">আসন্ন কিস্তি</div>
        <?php foreach ($upcoming as $p): ?>
        <?php $isOverdue = $p['due_date'] < date('Y-m-d'); ?>
        <div class="d-flex justify-content-between" style="font-size:13px;margin-bottom:4px">
          <span style="color:<?= $isOverdue?'#C0392B':'#333' ?>">#<?= $p['installment_no'] ?> — <?= formatDate($p['due_date'],'d M Y') ?><?= $isOverdue?' ⚠️':'' ?></span>
          <strong><?= formatCurrency((float)$p['total_due']) ?></strong>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>
<!-- <nav class="bottom-nav">
  <a href="/member/index.php"><i data-feather="home"></i><span>হোম</span></a>
  <a href="/member/deposits.php"><i data-feather="credit-card"></i><span>সঞ্চয়</span></a>
  <a href="/member/dues.php"><i data-feather="calendar"></i><span>চাঁদা</span></a>
  <a href="/member/loans.php" class="active"><i data-feather="briefcase"></i><span>ঋণ</span></a>
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
