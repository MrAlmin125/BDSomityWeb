<?php
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
requireAdmin();

$pageTitle  = 'রিপোর্ট';
$reportType = $_GET['type'] ?? 'summary';
$fromDate   = $_GET['from'] ?? date('Y-01-01');
$toDate     = $_GET['to']   ?? date('Y-12-31');
$year       = (int)($_GET['year'] ?? date('Y'));

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Report Type Selector -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-12 col-md-4">
        <label class="form-label">রিপোর্টের ধরন</label>
        <select name="type" class="form-select" onchange="this.form.submit()">
          <?php $types=['summary'=>'সারসংক্ষেপ','members'=>'সদস্য তালিকা','deposits'=>'সঞ্চয় বিবরণী','dues'=>'চাঁদা বিবরণী','loans'=>'ঋণ বিবরণী','expenses'=>'খরচ বিবরণী','profit'=>'লাভ-ক্ষতি রিপোর্ট']; ?>
          <?php foreach ($types as $k=>$v): ?>
          <option value="<?= $k ?>" <?= $reportType===$k?'selected':'' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if (!in_array($reportType,['summary','members','profit'])): ?>
      <div class="col-6 col-md-2">
        <label class="form-label">শুরু</label>
        <input type="date" name="from" class="form-control" value="<?= $fromDate ?>">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label">শেষ</label>
        <input type="date" name="to" class="form-control" value="<?= $toDate ?>">
      </div>
      <?php else: ?>
      <div class="col-6 col-md-2">
        <label class="form-label">বছর</label>
        <input type="number" name="year" class="form-control" value="<?= $year ?>" min="2020" max="2050">
      </div>
      <?php endif; ?>
      <div class="col-6 col-md-2">
        <button class="btn btn-primary w-100">দেখুন</button>
      </div>
      <div class="col-6 col-md-2">
        <button type="button" class="btn btn-outline-secondary w-100" onclick="window.print()">
          <i data-feather="printer" style="width:14px;height:14px" class="me-1"></i>প্রিন্ট
        </button>
      </div>
    </form>
  </div>
</div>

<div id="reportContent">

<?php if ($reportType === 'summary'): ?>
<!-- ===== SUMMARY REPORT ===== -->
<?php
$totalMembers   = queryCount('members',"status='active'");
$totalDeposits  = queryOne("SELECT COALESCE(SUM(amount),0) AS t FROM deposits WHERE YEAR(deposit_date)=?",'i',[$year])['t'];
$totalDues      = queryOne("SELECT COALESCE(SUM(paid_amount),0) AS t FROM monthly_dues WHERE due_year=?",'i',[$year])['t'];
$totalLoansGiven= queryOne("SELECT COALESCE(SUM(principal_amount),0) AS t FROM loans WHERE YEAR(loan_date)=? AND status!='rejected'",'i',[$year])['t'];
$totalLoanRecov = queryOne("SELECT COALESCE(SUM(paid_amount),0) AS t FROM loan_payments WHERE YEAR(paid_date)=? AND status='paid'",'i',[$year])['t'];
$totalExpenses  = queryOne("SELECT COALESCE(SUM(amount),0) AS t FROM expenses WHERE YEAR(expense_date)=?",'i',[$year])['t'];
$totalInvestment= queryOne("SELECT COALESCE(SUM(amount),0) AS t FROM investments WHERE YEAR(investment_date)=?",'i',[$year])['t'];
$totalOutstanding=queryOne("SELECT COALESCE(SUM(outstanding_balance),0) AS t FROM loans WHERE status='active'")['t'];
?>
<div class="card">
  <div class="card-header">
    <span class="card-title">
      <?= clean(getSetting('somity_name')) ?> — <?= $year ?> সালের সারসংক্ষেপ
    </span>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-12">
        <h6 class="text-primary border-bottom pb-2">আয় ও সঞ্চয়</h6>
        <table class="table table-bordered table-sm">
          <tbody>
            <tr><td>মোট সক্রিয় সদস্য</td><td class="text-end fw-bold"><?= toBengaliNumber($totalMembers) ?> জন</td></tr>
            <tr><td>মোট সঞ্চয় জমা (<?= $year ?>)</td><td class="text-end fw-bold amount-positive"><?= formatCurrency((float)$totalDeposits) ?></td></tr>
            <tr><td>মাসিক চাঁদা আদায় (<?= $year ?>)</td><td class="text-end fw-bold amount-positive"><?= formatCurrency((float)$totalDues) ?></td></tr>
          </tbody>
        </table>
      </div>
      <div class="col-12">
        <h6 class="text-warning border-bottom pb-2">ঋণ</h6>
        <table class="table table-bordered table-sm">
          <tbody>
            <tr><td>মোট ঋণ প্রদান (<?= $year ?>)</td><td class="text-end fw-bold"><?= formatCurrency((float)$totalLoansGiven) ?></td></tr>
            <tr><td>ঋণ আদায় (<?= $year ?>)</td><td class="text-end fw-bold amount-positive"><?= formatCurrency((float)$totalLoanRecov) ?></td></tr>
            <tr class="table-warning"><td><strong>মোট বকেয়া ঋণ (বর্তমান)</strong></td><td class="text-end fw-bold amount-negative"><?= formatCurrency((float)$totalOutstanding) ?></td></tr>
          </tbody>
        </table>
      </div>
      <div class="col-12">
        <h6 class="text-danger border-bottom pb-2">ব্যয় ও বিনিয়োগ</h6>
        <table class="table table-bordered table-sm">
          <tbody>
            <tr><td>মোট পরিচালন ব্যয় (<?= $year ?>)</td><td class="text-end fw-bold amount-negative"><?= formatCurrency((float)$totalExpenses) ?></td></tr>
            <tr><td>মোট বিনিয়োগ (<?= $year ?>)</td><td class="text-end fw-bold"><?= formatCurrency((float)$totalInvestment) ?></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php elseif ($reportType === 'members'): ?>
<!-- ===== MEMBERS REPORT ===== -->
<?php $members = queryAll("SELECT m.*,COALESCE(d.total,0) AS total_deposit,COALESCE(due.unpaid,0) AS unpaid_dues
  FROM members m
  LEFT JOIN (SELECT member_id,SUM(amount) AS total FROM deposits GROUP BY member_id) d ON d.member_id=m.id
  LEFT JOIN (SELECT member_id,SUM(amount-paid_amount) AS unpaid FROM monthly_dues WHERE status!='paid' GROUP BY member_id) due ON due.member_id=m.id
  WHERE m.status='active' ORDER BY m.name"); ?>
<div class="card">
  <div class="card-header">
    <span class="card-title">সদস্য তালিকা — মোট: <?= count($members) ?> জন</span>
  </div>
  <div class="table-responsive">
    <table class="table table-bordered table-sm">
      <thead class="table-light">
        <tr><th>#</th><th>সদস্য ID</th><th>নাম</th><th>ফোন</th><th>যোগদান</th><th>মোট সঞ্চয়</th><th>বকেয়া চাঁদা</th></tr>
      </thead>
      <tbody>
        <?php foreach ($members as $i=>$m): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><code style="font-size:11px"><?= $m['member_id'] ?></code></td>
          <td><?= clean($m['name']) ?></td>
          <td><?= $m['phone'] ?></td>
          <td><?= formatDate($m['join_date']) ?></td>
          <td class="amount-positive text-end"><?= formatCurrency((float)$m['total_deposit']) ?></td>
          <td class="<?= (float)$m['unpaid_dues']>0?'amount-negative':'amount-positive' ?> text-end"><?= formatCurrency((float)$m['unpaid_dues']) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="table-secondary fw-bold">
          <td colspan="5" class="text-end">মোট:</td>
          <td class="text-end"><?= formatCurrency(array_sum(array_column($members,'total_deposit'))) ?></td>
          <td class="text-end"><?= formatCurrency(array_sum(array_column($members,'unpaid_dues'))) ?></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($reportType === 'deposits'): ?>
<!-- ===== DEPOSITS REPORT ===== -->
<?php
$deposits = queryAll(
  "SELECT d.*,m.name,m.member_id AS mid FROM deposits d JOIN members m ON m.id=d.member_id
   WHERE d.deposit_date BETWEEN ? AND ? ORDER BY d.deposit_date DESC",
  'ss',[$fromDate,$toDate]
);
$grandTotal = array_sum(array_column($deposits,'amount'));
?>
<div class="card">
  <div class="card-header">
    <span class="card-title">সঞ্চয় বিবরণী — <?= formatDate($fromDate) ?> থেকে <?= formatDate($toDate) ?></span>
    <span class="badge bg-success"><?= count($deposits) ?> টি | <?= formatCurrency($grandTotal) ?></span>
  </div>
  <div class="table-responsive">
    <table class="table table-bordered table-sm">
      <thead class="table-light">
        <tr><th>#</th><th>রসিদ নং</th><th>সদস্য</th><th>ধরন</th><th>তারিখ</th><th>পরিমাণ</th></tr>
      </thead>
      <tbody>
        <?php foreach ($deposits as $i=>$d): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><code style="font-size:11px"><?= $d['receipt_no'] ?></code></td>
          <td><?= clean($d['name']) ?> (<?= $d['mid'] ?>)</td>
          <td><?= $d['deposit_type'] ?></td>
          <td><?= formatDate($d['deposit_date']) ?></td>
          <td class="text-end amount-positive"><?= formatCurrency((float)$d['amount']) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="table-success fw-bold">
          <td colspan="5" class="text-end">মোট সঞ্চয়:</td>
          <td class="text-end"><?= formatCurrency($grandTotal) ?></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($reportType === 'dues'): ?>
<!-- ===== DUES REPORT ===== -->
<?php
$dues = queryAll(
  "SELECT md.*,m.name,m.member_id AS mid FROM monthly_dues md JOIN members m ON m.id=md.member_id
   WHERE md.due_year BETWEEN ? AND ? ORDER BY md.due_year DESC,md.due_month DESC,m.name",
  'ii', [(int)$fromDate, (int)$toDate]
);
// Better: use actual date range
$dues = queryAll(
  "SELECT md.*,m.name,m.phone,m.member_id AS mid FROM monthly_dues md JOIN members m ON m.id=md.member_id
   WHERE CONCAT(md.due_year,'-',LPAD(md.due_month,2,'0')) BETWEEN DATE_FORMAT(?,'%Y-%m') AND DATE_FORMAT(?,'%Y-%m')
   ORDER BY md.due_year DESC,md.due_month DESC,m.name ASC",
  'ss', [$fromDate,$toDate]
);
$totalCollected = array_sum(array_column($dues,'paid_amount'));
$totalPending   = array_sum(array_map(fn($d)=>(float)$d['amount']-(float)$d['paid_amount'], $dues));
?>
<div class="card">
  <div class="card-header">
    <span class="card-title">চাঁদা বিবরণী</span>
    <div>
      <span class="badge bg-success me-1">আদায়: <?= formatCurrency($totalCollected) ?></span>
      <span class="badge bg-danger">বকেয়া: <?= formatCurrency($totalPending) ?></span>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-bordered table-sm">
      <thead class="table-light">
        <tr><th>#</th><th>সদস্য</th><th>মাস</th><th>পরিমাণ</th><th>পরিশোধ</th><th>বকেয়া</th><th>অবস্থা</th></tr>
      </thead>
      <tbody>
        <?php foreach ($dues as $i=>$d): ?>
        <?php $pending=(float)$d['amount']-(float)$d['paid_amount']; ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><?= clean($d['name']) ?><br><small class="text-muted"><?= $d['mid'] ?></small></td>
          <td style="white-space:nowrap"><?= bengaliMonth((int)$d['due_month']) ?> <?= $d['due_year'] ?></td>
          <td class="text-end"><?= formatCurrency((float)$d['amount']) ?></td>
          <td class="text-end amount-positive"><?= formatCurrency((float)$d['paid_amount']) ?></td>
          <td class="text-end <?= $pending>0?'amount-negative':'' ?>"><?= formatCurrency($pending) ?></td>
          <td>
            <?php $sm=['paid'=>['পরিশোধিত','success'],'unpaid'=>['অপরিশোধিত','danger'],'partial'=>['আংশিক','warning']]; ?>
            <span class="badge bg-<?= $sm[$d['status']][1] ?>"><?= $sm[$d['status']][0] ?></span>
          </td>
        </tr>
        <?php endforeach; ?>
        <tr class="table-light fw-bold">
          <td colspan="3" class="text-end">মোট:</td>
          <td class="text-end"><?= formatCurrency(array_sum(array_column($dues,'amount'))) ?></td>
          <td class="text-end"><?= formatCurrency($totalCollected) ?></td>
          <td class="text-end"><?= formatCurrency($totalPending) ?></td>
          <td></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($reportType === 'loans'): ?>
<!-- ===== LOANS REPORT ===== -->
<?php
$loans = queryAll(
  "SELECT l.*,m.name,m.phone,m.member_id AS mid FROM loans l JOIN members m ON m.id=l.member_id
   WHERE l.loan_date BETWEEN ? AND ? ORDER BY l.loan_date DESC",
  'ss', [$fromDate,$toDate]
);
?>
<div class="card">
  <div class="card-header">
    <span class="card-title">ঋণ বিবরণী</span>
    <span class="badge bg-primary"><?= count($loans) ?> টি ঋণ</span>
  </div>
  <div class="table-responsive">
    <table class="table table-bordered table-sm">
      <thead class="table-light">
        <tr><th>#</th><th>ঋণ নং</th><th>সদস্য</th><th>মূল</th><th>সুদ</th><th>মেয়াদ</th><th>বকেয়া</th><th>অবস্থা</th></tr>
      </thead>
      <tbody>
        <?php $totalPrincipal=$totalOutstanding2=0; ?>
        <?php foreach ($loans as $i=>$l): ?>
        <?php $totalPrincipal+=(float)$l['principal_amount']; $totalOutstanding2+=(float)$l['outstanding_balance']; ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><code style="font-size:11px"><?= $l['loan_no'] ?></code></td>
          <td><?= clean($l['name']) ?><br><small class="text-muted"><?= $l['mid'] ?></small></td>
          <td class="text-end"><?= formatCurrency((float)$l['principal_amount']) ?></td>
          <td class="text-end"><?= $l['interest_rate'] ?>%</td>
          <td class="text-center"><?= $l['tenure_months'] ?> মাস</td>
          <td class="text-end <?= (float)$l['outstanding_balance']>0?'amount-negative':'' ?>"><?= formatCurrency((float)$l['outstanding_balance']) ?></td>
          <td>
            <?php $sm=['active'=>['সক্রিয়','success'],'completed'=>['সম্পন্ন','secondary'],'defaulted'=>['খেলাপি','danger'],'pending'=>['অপেক্ষমাণ','warning']]; ?>
            <span class="badge bg-<?= $sm[$l['status']][1] ?? 'secondary' ?>"><?= $sm[$l['status']][0] ?? $l['status'] ?></span>
          </td>
        </tr>
        <?php endforeach; ?>
        <tr class="table-light fw-bold">
          <td colspan="3" class="text-end">মোট:</td>
          <td class="text-end"><?= formatCurrency($totalPrincipal) ?></td>
          <td colspan="2"></td>
          <td class="text-end"><?= formatCurrency($totalOutstanding2) ?></td>
          <td></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($reportType === 'expenses'): ?>
<!-- ===== EXPENSES REPORT ===== -->
<?php
$expenses = queryAll(
  "SELECT e.*,u.name AS entered_by FROM expenses e LEFT JOIN users u ON u.id=e.created_by
   WHERE e.expense_date BETWEEN ? AND ? ORDER BY e.expense_date DESC",
  'ss', [$fromDate,$toDate]
);
$grandTotal = array_sum(array_column($expenses,'amount'));
// Group by category
$byCategory = [];
foreach ($expenses as $e) {
  $byCategory[$e['category']] = ($byCategory[$e['category']] ?? 0) + (float)$e['amount'];
}
?>
<div class="row g-3">
  <div class="col-12 col-md-4">
    <div class="card">
      <div class="card-header"><span class="card-title">বিভাগ অনুযায়ী</span></div>
      <div class="card-body">
        <?php $catLabels=['office'=>'অফিস','salary'=>'বেতন','maintenance'=>'রক্ষণাবেক্ষণ','event'=>'অনুষ্ঠান','other'=>'অন্যান্য']; ?>
        <?php foreach ($byCategory as $cat=>$amt): ?>
        <div class="d-flex justify-content-between py-1 border-bottom" style="font-size:13px">
          <span><?= $catLabels[$cat] ?? $cat ?></span>
          <strong class="amount-negative"><?= formatCurrency($amt) ?></strong>
        </div>
        <?php endforeach; ?>
        <div class="d-flex justify-content-between pt-2 fw-bold">
          <span>মোট</span><span class="amount-negative"><?= formatCurrency($grandTotal) ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-8">
    <div class="card">
      <div class="card-header">
        <span class="card-title">খরচ বিবরণী</span>
        <span class="badge bg-danger"><?= formatCurrency($grandTotal) ?></span>
      </div>
      <div class="table-responsive">
        <table class="table table-bordered table-sm">
          <thead class="table-light">
            <tr><th>#</th><th>বিবরণ</th><th>বিভাগ</th><th>তারিখ</th><th>পরিমাণ</th></tr>
          </thead>
          <tbody>
            <?php foreach ($expenses as $i=>$e): ?>
            <tr>
              <td><?= $i+1 ?></td>
              <td><?= clean($e['title']) ?></td>
              <td><?= $catLabels[$e['category']] ?? $e['category'] ?></td>
              <td><?= formatDate($e['expense_date']) ?></td>
              <td class="text-end amount-negative"><?= formatCurrency((float)$e['amount']) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="table-danger fw-bold">
              <td colspan="4" class="text-end">মোট:</td>
              <td class="text-end"><?= formatCurrency($grandTotal) ?></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php elseif ($reportType === 'profit'): ?>
<!-- ===== PROFIT REPORT ===== -->
<?php $plRecords = queryAll("SELECT * FROM profit_loss ORDER BY fiscal_year DESC"); ?>
<div class="card">
  <div class="card-header"><span class="card-title">লাভ-ক্ষতি বিবরণী (সকল বছর)</span></div>
  <div class="table-responsive">
    <table class="table table-bordered">
      <thead class="table-light">
        <tr><th>আর্থিক বছর</th><th>মোট আয়</th><th>মোট ব্যয়</th><th>নিট লাভ</th><th>রিজার্ভ</th><th>বিতরণযোগ্য</th><th>অবস্থা</th></tr>
      </thead>
      <tbody>
        <?php foreach ($plRecords as $p): ?>
        <tr>
          <td><strong><?= $p['fiscal_year'] ?></strong></td>
          <td class="text-end amount-positive"><?= formatCurrency((float)$p['total_income']) ?></td>
          <td class="text-end amount-negative"><?= formatCurrency((float)$p['total_expenses']) ?></td>
          <td class="text-end <?= (float)$p['net_profit']>=0?'amount-positive':'amount-negative' ?> fw-bold"><?= formatCurrency((float)$p['net_profit']) ?></td>
          <td class="text-end"><?= formatCurrency((float)$p['reserve_amount']) ?></td>
          <td class="text-end amount-positive"><?= formatCurrency((float)$p['distributable_profit']) ?></td>
          <td><span class="badge bg-<?= $p['status']==='final'?'success':'warning text-dark' ?>"><?= $p['status']==='final'?'চূড়ান্ত':'খসড়া' ?></span></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($plRecords)): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">কোনো হিসাব পাওয়া যায়নি</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

</div><!-- /reportContent -->

<?php
$extraCSS = '<style>
@media print {
  .sidebar,.topbar,.card-header form,nav { display:none!important; }
  .main-content { margin:0!important; padding:0!important; }
  .card { box-shadow:none!important; border:1px solid #ddd!important; }
  body { font-size:12px; }
}
</style>';

require_once __DIR__ . '/../includes/footer.php';
?>
