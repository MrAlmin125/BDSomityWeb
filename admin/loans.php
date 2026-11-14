<?php
// =====================================================
// admin/loans.php - Loan Management (FULLY FIXED)
// =====================================================

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/functions.php';
startSecureSession();

// ✅ Direct session check
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_role'])) {
    header('Location: /admin/login.php');
    exit;
}

$action    = $_GET['action'] ?? 'list';
$id        = (int)($_GET['id'] ?? 0);
$pageTitle = 'ঋণ ব্যবস্থাপনা';

// ---- POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('danger','নিরাপত্তা যাচাই ব্যর্থ।'); 
        header('Location: loans.php'); 
        exit;
    }
    $postAction = $_POST['action'] ?? '';

    // CREATE LOAN
    if ($postAction === 'add') {
        $mId          = (int)$_POST['member_id'];
        $principal    = (float)$_POST['principal_amount'];
        $rate         = (float)$_POST['interest_rate'];
        $tenure       = (int)$_POST['tenure_months'];
        $loanDate     = $_POST['loan_date'] ?? date('Y-m-d');
        $firstDate    = $_POST['first_installment_date'] ?? date('Y-m-d', strtotime('+1 month'));
        $loanType     = $_POST['loan_type'] ?? 'personal';
        $purpose      = trim($_POST['purpose'] ?? '');
        $guarantor    = trim($_POST['guarantor_name'] ?? '');
        $guarantorPh  = trim($_POST['guarantor_phone'] ?? '');

        if (!$mId || $principal<=0 || $rate<=0 || $tenure<=0) {
            setFlash('danger','সকল প্রয়োজনীয় তথ্য সঠিকভাবে দিন।');
            header('Location: loans.php');
            exit;
        }

        $calc  = calculateLoan($principal, $rate, $tenure);
        $loanNo= generateLoanNo();
        $by    = $_SESSION['admin_id'];
        $emi   = $calc['monthly_installment'];
        $total = $calc['total_payable'];

        try {
            $conn = conn();
            $stmt = $conn->prepare(
                "INSERT INTO loans (loan_no,member_id,loan_type,principal_amount,interest_rate,tenure_months,
                 monthly_installment,total_payable,loan_date,first_installment_date,outstanding_balance,
                 purpose,guarantor_name,guarantor_phone,status,created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pending',?)"
            );
            $stmt->bind_param(
                'sisddiidddssssi',
                $loanNo,$mId,$loanType,$principal,$rate,$tenure,$emi,$total,
                $loanDate,$firstDate,$principal,$purpose,$guarantor,$guarantorPh,$by
            );
            $stmt->execute();
            $loanId = $conn->insert_id;

            // Insert payment schedule
            $schedDate = $firstDate;
            foreach ($calc['schedule'] as $row) {
                $stmt2 = $conn->prepare(
                    "INSERT INTO loan_payments (loan_id,installment_no,due_date,principal_amount,interest_amount,total_due,status)
                     VALUES (?,?,?,?,?,?,'pending')"
                );
                $stmt2->bind_param('iisddd',$loanId,$row['installment_no'],$schedDate,$row['principal_amount'],$row['interest_amount'],$row['total_due']);
                $stmt2->execute();
                $schedDate = date('Y-m-d', strtotime($schedDate . ' +1 month'));
            }

            // Mark as active
            $conn->query("UPDATE loans SET status='active' WHERE id=$loanId");

            logActivity('loan_add',"New loan $loanNo for member $mId, ৳$principal");
            sendNotification('loan_installment','ঋণ মঞ্জুর',"আপনার ৳".number_format($principal,2)." টাকার ঋণ মঞ্জুর হয়েছে।",'member',$mId);
            setFlash('success',"ঋণ মঞ্জুর সফল! ঋণ নং: $loanNo | মাসিক কিস্তি: ".formatCurrency($emi));
            header("Location: loans.php?action=view&id=$loanId"); 
            exit;

        } catch (Exception $e) {
            setFlash('danger','ঋণ তৈরিতে ত্রুটি হয়েছে: ' . $e->getMessage());
            header('Location: loans.php');
            exit;
        }
    }

    // UPDATE STATUS
    if ($postAction === 'status') {
        $loanId    = (int)$_POST['loan_id'];
        $newStatus = $_POST['status'];
        if (in_array($newStatus, ['active','completed','defaulted','rejected'])) {
            $conn = conn();
            $conn->query("UPDATE loans SET status='$newStatus' WHERE id=$loanId");
            setFlash('success','ঋণের অবস্থা আপডেট হয়েছে।');
        }
        header("Location: loans.php?action=view&id=$loanId"); 
        exit;
    }
}

// ---- LOAD DATA ----
$loan = null;
if (in_array($action,['view','edit']) && $id) {
    $loan = queryOne("SELECT l.*,m.name AS mname,m.phone AS mphone,m.member_id AS mid
                      FROM loans l JOIN members m ON m.id=l.member_id WHERE l.id=?",'i',[$id]);
    if (!$loan) { 
        setFlash('danger','ঋণ পাওয়া যায়নি।'); 
        header('Location: loans.php'); 
        exit; 
    }
    $pageTitle = 'ঋণ: '.$loan['loan_no'];
}

// List filters
$search   = trim($_GET['s'] ?? '');
$statusF  = $_GET['status'] ?? '';
$page     = max(1,(int)($_GET['page'] ?? 1));

$where='1=1'; 
$types=''; 
$params=[];
if ($search)  { 
    $where.=" AND (m.name LIKE ? OR m.phone LIKE ? OR l.loan_no LIKE ? OR m.member_id LIKE ?)"; 
    $like="%$search%"; 
    $types.='ssss'; 
    $params=array_merge($params,[$like,$like,$like,$like]); 
}
if ($statusF) { 
    $where.=" AND l.status=?"; 
    $types.='s'; 
    $params[]=$statusF; 
}

$activeMembers = queryAll("SELECT id,member_id,name,phone FROM members WHERE status='active' ORDER BY name");
$defaultRate   = getSetting('loan_interest_rate','10');

// Include header
require_once __DIR__ . '/../includes/header.php';

// Show flash messages
$flash = getFlash();
if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show m-3" role="alert">
    <i data-feather="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-circle' ?>" 
       style="width:16px;height:16px;vertical-align:middle"></i>
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($action === 'add'): ?>
<!-- ============================================
     ADD LOAN FORM (action=add)
     ============================================ -->
<div class="container-fluid px-3">
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5><i data-feather="plus-circle" style="width:18px;height:18px"></i> নতুন ঋণ মঞ্জুর</h5>
                <a href="loans.php" class="btn btn-outline-secondary btn-sm">
                    <i data-feather="arrow-left" style="width:14px;height:14px"></i> ফিরুন
                </a>
            </div>
            <div class="card-body">
                <form method="POST" id="loanForm">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="add">

                    <div class="mb-3">
                        <label class="form-label">সদস্য <span class="text-danger">*</span></label>
                        <div id="memberSelectContainer">
                            <select name="member_id" id="memberSelect" class="form-select" required style="width:100%;">
                                <option value="">— সদস্য বেছে নিন —</option>
                                <?php foreach ($activeMembers as $m): ?>
                                <option value="<?= $m['id'] ?>" data-phone="<?= clean($m['phone']) ?>">
                                    <?= clean($m['name']) ?> (<?= $m['member_id'] ?>) 
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-text mt-1">
                            <i data-feather="search" style="width:14px;height:14px"></i>
                            টাইপ করে সদস্য খুঁজুন (নাম, আইডি বা ফোন)
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">ঋণের ধরন</label>
                        <select name="loan_type" class="form-select">
                            <option value="personal">ব্যক্তিগত</option>
                            <option value="business">ব্যবসা</option>
                            <option value="emergency">জরুরি</option>
                            <option value="education">শিক্ষা</option>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-12">
                            <label class="form-label">মূল পরিমাণ (৳) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">৳</span>
                                <input type="number" name="principal_amount" id="principal" class="form-control" step="0.01" min="100" required onchange="calcEMI()" onkeyup="calcEMI()">
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label">সুদের হার (%/বছর)</label>
                            <input type="number" name="interest_rate" id="rate" class="form-control" step="0.1" value="<?= $defaultRate ?>" onchange="calcEMI()" onkeyup="calcEMI()">
                        </div>
                        <div class="col-6">
                            <label class="form-label">মেয়াদ (মাস)</label>
                            <input type="number" name="tenure_months" id="tenure" class="form-control" min="1" max="120" value="12" onchange="calcEMI()" onkeyup="calcEMI()">
                        </div>
                    </div>

                    <!-- EMI Preview -->
                    <div id="emiPreview" class="alert alert-info py-2 px-3 mb-3" style="display:none;font-size:13px">
                        <div class="d-flex justify-content-between"><span>মাসিক কিস্তি:</span><strong id="emiVal">—</strong></div>
                        <div class="d-flex justify-content-between"><span>মোট পরিশোধ:</span><strong id="totalVal">—</strong></div>
                        <div class="d-flex justify-content-between"><span>মোট সুদ:</span><strong id="interestVal">—</strong></div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">ঋণের তারিখ</label>
                            <input type="date" name="loan_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">প্রথম কিস্তির তারিখ</label>
                            <input type="date" name="first_installment_date" class="form-control" value="<?= date('Y-m-d', strtotime('+1 month')) ?>">
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">জামিনদারের নাম</label>
                        <input type="text" name="guarantor_name" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">জামিনদারের ফোন</label>
                        <input type="tel" name="guarantor_phone" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ঋণের উদ্দেশ্য</label>
                        <textarea name="purpose" class="form-control" rows="2"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i data-feather="check-circle" style="width:14px;height:14px" class="me-1"></i>ঋণ মঞ্জুর করুন
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
</div>

<?php elseif ($action === 'list'): ?>
<!-- LIST -->
<div class="container-fluid px-3">
<div class="row g-3">
  <!-- Add Form -->
  <div class="col-12 col-lg-4">
    <div class="card">
      <div class="card-header"><span class="card-title"><i data-feather="plus-circle" style="width:15px;height:15px"></i> নতুন ঋণ মঞ্জুর</span></div>
      <div class="card-body">
        <form method="POST" id="loanForm">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="add">

          <!-- ============================================
               SEARCHABLE MEMBER SELECT - DROPDOWN TOP FIXED
               ============================================ -->
          <div class="mb-3">
            <label class="form-label">সদস্য <span class="text-danger">*</span></label>
            <div id="memberSelectContainer">
                <select name="member_id" id="memberSelect" class="form-select" required style="width:100%;">
                  <option value="">— সদস্য বেছে নিন —</option>
                  <?php foreach ($activeMembers as $m): ?>
                  <option value="<?= $m['id'] ?>" data-phone="<?= clean($m['phone']) ?>">
                    <?= clean($m['name']) ?> (<?= $m['member_id'] ?>) 
                  </option>
                  <?php endforeach; ?>
                </select>
            </div>
            <div class="form-text mt-1">
              <i data-feather="search" style="width:14px;height:14px"></i>
              টাইপ করে সদস্য খুঁজুন (নাম, আইডি বা ফোন)
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">ঋণের ধরন</label>
            <select name="loan_type" class="form-select">
              <option value="personal">ব্যক্তিগত</option>
              <option value="business">ব্যবসা</option>
              <option value="emergency">জরুরি</option>
              <option value="education">শিক্ষা</option>
            </select>
          </div>

          <div class="row g-2 mb-3">
            <div class="col-12">
              <label class="form-label">মূল পরিমাণ (৳) <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text">৳</span>
                <input type="number" name="principal_amount" id="principal" class="form-control" step="0.01" min="100" required onchange="calcEMI()" onkeyup="calcEMI()">
              </div>
            </div>
            <div class="col-6">
              <label class="form-label">সুদের হার (%/বছর)</label>
              <input type="number" name="interest_rate" id="rate" class="form-control" step="0.1" value="<?= $defaultRate ?>" onchange="calcEMI()" onkeyup="calcEMI()">
            </div>
            <div class="col-6">
              <label class="form-label">মেয়াদ (মাস)</label>
              <input type="number" name="tenure_months" id="tenure" class="form-control" min="1" max="120" value="12" onchange="calcEMI()" onkeyup="calcEMI()">
            </div>
          </div>

          <!-- EMI Preview -->
          <div id="emiPreview" class="alert alert-info py-2 px-3 mb-3" style="display:none;font-size:13px">
            <div class="d-flex justify-content-between"><span>মাসিক কিস্তি:</span><strong id="emiVal">—</strong></div>
            <div class="d-flex justify-content-between"><span>মোট পরিশোধ:</span><strong id="totalVal">—</strong></div>
            <div class="d-flex justify-content-between"><span>মোট সুদ:</span><strong id="interestVal">—</strong></div>
          </div>

          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label">ঋণের তারিখ</label>
              <input type="date" name="loan_date" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-6">
              <label class="form-label">প্রথম কিস্তির তারিখ</label>
              <input type="date" name="first_installment_date" class="form-control" value="<?= date('Y-m-d', strtotime('+1 month')) ?>">
            </div>
          </div>

          <div class="mb-2">
            <label class="form-label">জামিনদারের নাম</label>
            <input type="text" name="guarantor_name" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label">জামিনদারের ফোন</label>
            <input type="tel" name="guarantor_phone" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label">ঋণের উদ্দেশ্য</label>
            <textarea name="purpose" class="form-control" rows="2"></textarea>
          </div>

          <button type="submit" class="btn btn-primary w-100">
            <i data-feather="check-circle" style="width:14px;height:14px" class="me-1"></i>ঋণ মঞ্জুর করুন
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- List -->
  <div class="col-12 col-lg-8">
    <div class="card">
      <div class="card-header">
        <span class="card-title"><i data-feather="briefcase" style="width:16px;height:16px"></i> ঋণের তালিকা</span>
        <span class="badge bg-secondary">
          <?php 
          $sql = "SELECT l.*,m.name AS mname,m.phone AS mphone,m.member_id AS mid
                  FROM loans l JOIN members m ON m.id=l.member_id WHERE $where ORDER BY l.created_at DESC";
          $paged = paginate($sql,$types,$params,$page); 
          echo count($paged['data'] ?? []) . ' টি';
          ?>
        </span>
      </div>
      <div class="card-body border-bottom pb-3">
        <form method="GET" class="row g-2">
          <div class="col-12 col-sm-5">
            <div class="search-box">
              <i data-feather="search" class="search-icon"></i>
              <input type="text" name="s" class="form-control" placeholder="নাম, ফোন, ঋণ নং বা আইডি..." value="<?= clean($search) ?>">
            </div>
          </div>
          <div class="col-6 col-sm-3">
            <select name="status" class="form-select" onchange="this.form.submit()">
              <option value="">সব</option>
              <option value="active"    <?= $statusF==='active'?'selected':'' ?>>সক্রিয়</option>
              <option value="completed" <?= $statusF==='completed'?'selected':'' ?>>সম্পন্ন</option>
              <option value="defaulted" <?= $statusF==='defaulted'?'selected':'' ?>>খেলাপি</option>
              <option value="pending"   <?= $statusF==='pending'?'selected':'' ?>>অপেক্ষমাণ</option>
              <option value="rejected"  <?= $statusF==='rejected'?'selected':'' ?>>বাতিল</option>
            </select>
          </div>
          <div class="col-6 col-sm-2">
            <button class="btn btn-primary w-100">
              <i data-feather="search" style="width:14px;height:14px"></i> খুঁজুন
            </button>
          </div>
          <?php if ($search||$statusF): ?>
          <div class="col-6 col-sm-2">
            <a href="loans.php" class="btn btn-outline-secondary w-100">
              <i data-feather="x" style="width:14px;height:14px"></i> রিসেট
            </a>
          </div>
          <?php endif; ?>
        </form>
      </div>

      <div class="table-responsive">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>ঋণ নং</th>
              <th>সদস্য</th>
              <th>মূল</th>
              <th>কিস্তি</th>
              <th>বকেয়া</th>
              <th>অবস্থা</th>
              <th class="text-center">কার্যক্রম</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($paged['data'])): ?>
            <tr>
              <td colspan="7" class="text-center text-muted py-4">
                <i data-feather="inbox" style="width:32px;height:32px;display:block;margin:0 auto 10px"></i>
                <?php if (!empty($search)): ?>
                "<strong><?= htmlspecialchars($search) ?></strong>" দিয়ে কোনো ঋণ পাওয়া যায়নি।
                <?php else: ?>
                কোনো ঋণ নেই।
                <?php endif; ?>
              </td>
            </tr>
            <?php else: ?>
            <?php foreach ($paged['data'] as $l): ?>
            <tr>
              <td><code style="font-size:11px"><?= $l['loan_no'] ?></code></td>
              <td>
                <div style="font-size:13px;font-weight:500"><?= clean($l['mname']) ?></div>
                <div style="font-size:11px;color:#888"><?= $l['mid'] ?></div>
              </td>
              <td class="amount-neutral"><?= formatCurrency((float)$l['principal_amount']) ?></td>
              <td style="font-size:13px"><?= formatCurrency((float)$l['monthly_installment']) ?>/মাস</td>
              <td class="<?= (float)$l['outstanding_balance']>0?'amount-negative':'amount-positive' ?>">
                <?= formatCurrency((float)$l['outstanding_balance']) ?>
              </td>
              <td>
                <?php 
                $sMap = [
                    'active'=>['সক্রিয়','status-active'],
                    'completed'=>['সম্পন্ন','status-paid'],
                    'defaulted'=>['খেলাপি','status-unpaid'],
                    'pending'=>['অপেক্ষমাণ','status-pending'],
                    'rejected'=>['বাতিল','status-inactive']
                ]; 
                ?>
                <span class="badge <?= $sMap[$l['status']][1] ?? 'status-inactive' ?>">
                  <?= $sMap[$l['status']][0] ?? $l['status'] ?>
                </span>
              </td>
              <td class="text-center">
                <a href="?action=view&id=<?= $l['id'] ?>" class="btn btn-sm btn-outline-primary" title="বিস্তারিত দেখুন">
                  <i data-feather="eye" style="width:15px;height:15px"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="card-body pt-2">
        <?= renderPagination($paged,"loans.php?s=$search&status=$statusF") ?>
      </div>
    </div>
  </div>
</div>
</div>

<?php elseif ($action === 'view' && $loan): ?>
<!-- VIEW LOAN -->
<div class="container-fluid px-3">
<?php
$schedule = queryAll(
    "SELECT * FROM loan_payments WHERE loan_id=? ORDER BY installment_no ASC",'i',[$id]
);
$paidCount    = count(array_filter($schedule, fn($r) => $r['status']==='paid'));
$overdueCount = count(array_filter($schedule, fn($r) => $r['status']==='overdue' || ($r['status']==='pending' && $r['due_date'] < date('Y-m-d'))));
?>
<div class="d-flex gap-2 mb-3">
  <a href="loans.php" class="btn btn-outline-secondary btn-sm">
    <i data-feather="arrow-left" style="width:14px;height:14px"></i> ফিরে যান
  </a>
  <a href="/admin/loan_payments.php?loan_id=<?= $id ?>" class="btn btn-success btn-sm">
    <i data-feather="dollar-sign" style="width:14px;height:14px"></i> কিস্তি গ্রহণ
  </a>
</div>

<div class="row g-3">
  <div class="col-12 col-md-4">
    <div class="card">
      <div class="card-header"><span class="card-title">ঋণ তথ্য</span></div>
      <div class="card-body">
        <?php $lInfo = [
          ['ঋণ নং',       $loan['loan_no'],                          'hash'],
          ['সদস্য',        $loan['mname'].' ('.$loan['mid'].')',       'user'],
          ['ফোন',          $loan['mphone'],                           'phone'],
          ['ঋণের ধরন',    $loan['loan_type'],                        'tag'],
          ['মূল পরিমাণ',  formatCurrency((float)$loan['principal_amount']), 'dollar-sign'],
          ['সুদের হার',    $loan['interest_rate'].'% (বার্ষিক)',     'percent'],
          ['মেয়াদ',       $loan['tenure_months'].' মাস',             'clock'],
          ['মাসিক কিস্তি', formatCurrency((float)$loan['monthly_installment']),'repeat'],
          ['মোট পরিশোধ',  formatCurrency((float)$loan['total_payable']), 'trending-up'],
          ['বকেয়া',       formatCurrency((float)$loan['outstanding_balance']), 'alert-circle'],
          ['ঋণের তারিখ',  formatDate($loan['loan_date']),            'calendar'],
          ['প্রথম কিস্তি', formatDate($loan['first_installment_date']),'calendar'],
          ['জামিনদার',    $loan['guarantor_name'] ?? '-',            'shield'],
        ]; ?>
        <?php foreach ($lInfo as [$lbl,$val,$ico]): ?>
        <div class="d-flex justify-content-between py-1 border-bottom" style="font-size:13px">
          <span class="text-muted d-flex align-items-center gap-1">
            <i data-feather="<?= $ico ?>" style="width:12px;height:12px"></i><?= $lbl ?>
          </span>
          <strong><?= clean((string)$val) ?></strong>
        </div>
        <?php endforeach; ?>

        <!-- Progress -->
        <?php $progress = $loan['total_payable']>0 ? round(((float)$loan['total_paid']/(float)$loan['total_payable'])*100) : 0; ?>
        <div class="mt-3">
          <div class="d-flex justify-content-between mb-1" style="font-size:12px">
            <span>পরিশোধের অগ্রগতি</span><span><?= $progress ?>%</span>
          </div>
          <div class="progress" style="height:8px">
            <div class="progress-bar bg-success" style="width:<?= $progress ?>%"></div>
          </div>
          <div class="d-flex justify-content-between mt-1" style="font-size:11px;color:#888">
            <span>পরিশোধ: <?= $paidCount ?></span>
            <span>বকেয়া: <?= count($schedule)-$paidCount ?></span>
            <?php if ($overdueCount): ?><span class="text-danger">অতিরিক্ত: <?= $overdueCount ?></span><?php endif; ?>
          </div>
        </div>

        <!-- Status Change -->
        <form method="POST" class="mt-3">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="status">
          <input type="hidden" name="loan_id" value="<?= $id ?>">
          <select name="status" class="form-select form-select-sm mb-2">
            <?php foreach (['active'=>'সক্রিয়','completed'=>'সম্পন্ন','defaulted'=>'খেলাপি'] as $k=>$v): ?>
            <option value="<?= $k ?>" <?= $loan['status']===$k?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-outline-secondary btn-sm w-100">
            <i data-feather="refresh-cw" style="width:14px;height:14px"></i> অবস্থা পরিবর্তন
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Schedule -->
  <div class="col-12 col-md-8">
    <div class="card">
      <div class="card-header">
        <span class="card-title">কিস্তির সূচি (<?= count($schedule) ?> টি)</span>
        <a href="/admin/loan_payments.php?loan_id=<?= $id ?>" class="btn btn-success btn-sm">
          <i data-feather="dollar-sign" style="width:14px;height:14px"></i> কিস্তি গ্রহণ
        </a>
      </div>
      <div class="table-responsive">
        <table class="table table-sm">
          <thead>
            <tr><th>#</th><th>তারিখ</th><th>মূল</th><th>সুদ</th><th>মোট</th><th>অবস্থা</th></tr>
          </thead>
          <tbody>
            <?php foreach ($schedule as $s): ?>
            <?php
            // auto-mark overdue
            $rowStatus = $s['status'];
            if ($rowStatus === 'pending' && $s['due_date'] < date('Y-m-d')) $rowStatus = 'overdue';
            $sMap=['paid'=>['পরিশোধিত','status-paid'],'pending'=>['অপেক্ষমাণ','status-pending'],'overdue'=>['মেয়াদোত্তীর্ণ','status-unpaid'],'partial'=>['আংশিক','status-partial']];
            ?>
            <tr class="<?= $rowStatus==='overdue'?'table-danger':($rowStatus==='paid'?'table-success':'') ?>">
              <td style="font-size:12px"><?= $s['installment_no'] ?></td>
              <td style="font-size:12px"><?= formatDate($s['due_date'],'d M Y') ?></td>
              <td style="font-size:12px"><?= formatCurrency((float)$s['principal_amount']) ?></td>
              <td style="font-size:12px"><?= formatCurrency((float)$s['interest_amount']) ?></td>
              <td style="font-size:12px;font-weight:600"><?= formatCurrency((float)$s['total_due']) ?></td>
              <td>
                <span class="badge <?= $sMap[$rowStatus][1] ?? 'status-pending' ?>" style="font-size:10px">
                  <?= $sMap[$rowStatus][0] ?? $rowStatus ?>
                </span>
                <?php if ($s['paid_date']): ?>
                <div style="font-size:10px;color:#888"><?= formatDate($s['paid_date'],'d M') ?></div>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</div>
<?php endif; ?>

<!-- ============================================
     SELECT2 CSS & JS - DROPDOWN TOP FIXED
     ============================================ -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<style>
/* ============================================
     SELECT2 CUSTOM STYLES - DROPDOWN POSITION FIX
     ============================================ */

/* Fix container */
.select2-container {
    width: 100% !important;
    max-width: 100% !important;
    display: block !important;
    position: relative !important;
}

/* Fix selection box */
.select2-container--default .select2-selection--single {
    border: 1px solid #ced4da !important;
    border-radius: 6px !important;
    min-height: 38px !important;
    background: #fff !important;
    width: 100% !important;
    padding: 0 !important;
}

.select2-container--default .select2-selection--single .select2-selection__rendered {
    padding: 6px 12px !important;
    font-family: 'Hind Siliguri', sans-serif !important;
    color: #212529 !important;
    line-height: 1.5 !important;
    font-size: 14px !important;
}

.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 36px !important;
    width: 32px !important;
}

.select2-container--default .select2-selection--single .select2-selection__arrow b {
    border-color: #6c757d transparent transparent transparent !important;
    border-width: 6px 5px 0 5px !important;
}

/* ============================================
     DROPDOWN - FIXED POSITION (JUST BELOW SELECT)
     ============================================ */
.select2-dropdown {
    border: 1px solid #ced4da !important;
    border-radius: 6px !important;
    font-family: 'Hind Siliguri', sans-serif !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important;
    max-height: 250px !important;
    overflow-y: auto !important;
    z-index: 9999 !important;
    background: #fff !important;
    width: 100% !important;
    min-width: 250px !important;
    max-width: 100% !important;
    position: absolute !important;
    top: 100% !important;
    left: 0 !important;
    right: auto !important;
    margin-top: 2px !important;
    transform: none !important;
}

/* Dropdown above (if space not enough) */
.select2-dropdown--above {
    top: auto !important;
    bottom: 100% !important;
    margin-top: 0 !important;
    margin-bottom: 2px !important;
    transform: none !important;
}

/* Dropdown below (default) */
.select2-dropdown--below {
    top: 100% !important;
    bottom: auto !important;
    margin-top: 2px !important;
    transform: none !important;
}

/* Fix search field */
.select2-container--default .select2-search--dropdown {
    padding: 8px !important;
}

.select2-container--default .select2-search--dropdown .select2-search__field {
    border-radius: 6px !important;
    padding: 8px 12px !important;
    font-family: 'Hind Siliguri', sans-serif !important;
    border: 1px solid #ced4da !important;
    width: 100% !important;
    box-sizing: border-box !important;
    font-size: 14px !important;
}

.select2-container--default .select2-search--dropdown .select2-search__field:focus {
    border-color: #86b7fe !important;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25) !important;
    outline: none !important;
}

/* Fix results */
.select2-container--default .select2-results__options {
    max-height: 200px !important;
    overflow-y: auto !important;
}

.select2-container--default .select2-results__option {
    padding: 8px 12px !important;
    font-family: 'Hind Siliguri', sans-serif !important;
    font-size: 14px !important;
}

.select2-container--default .select2-results__option--highlighted {
    background: #2E86C1 !important;
    color: white !important;
}

.select2-container--default .select2-results__option[aria-selected="true"] {
    background: #e9ecef !important;
    color: #1B4F72 !important;
}

.select2-container--default .select2-results__option--highlighted[aria-selected="true"] {
    background: #2E86C1 !important;
    color: white !important;
}

/* Mobile responsive */
@media (max-width: 768px) {
    .select2-dropdown {
        max-height: 200px !important;
        min-width: 200px !important;
        width: 100% !important;
        left: 0 !important;
        right: auto !important;
        transform: none !important;
        top: 100% !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        padding: 4px 10px !important;
        font-size: 13px !important;
    }
}

/* Fix z-index */
.select2-container--open {
    z-index: 9999 !important;
}

/* Hide clear button if not needed */
.select2-container--default .select2-selection--single .select2-selection__clear {
    margin-right: 28px !important;
}

/* Force dropdown container to be relative */
#memberSelectContainer {
    position: relative;
}

/* Fix dropdown wrapper */
.select2 {
    width: 100% !important;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
// ============================================
// SELECT2 - DROPDOWN POSITION FIXED (TOP ADJUSTED)
// ============================================
$(document).ready(function() {
    // Destroy existing select2 if any
    if ($('#memberSelect').data('select2')) {
        $('#memberSelect').select2('destroy');
    }
    
    // Initialize select2 with dropdown parent
    $('#memberSelect').select2({
        placeholder: 'সদস্য খুঁজুন...',
        allowClear: true,
        width: '100%',
        dropdownParent: $('#memberSelectContainer'),
        language: {
            searching: function() {
                return 'খুঁজছেন...';
            },
            noResults: function() {
                return 'কোনো সদস্য পাওয়া যায়নি';
            }
        },
        matcher: function(params, data) {
            if ($.trim(params.term) === '') {
                return data;
            }

            var term = params.term.toLowerCase();
            var text = data.text.toLowerCase();
            var id = data.id.toString().toLowerCase();
            
            if (text.indexOf(term) > -1 || id.indexOf(term) > -1) {
                return data;
            }

            var phone = $(data.element).data('phone') || '';
            if (phone.toLowerCase().indexOf(term) > -1) {
                return data;
            }

            return null;
        }
    });

    // Fix dropdown position - ensure it stays just below the select
    $('#memberSelect').on('select2:open', function() {
        var dropdown = $('.select2-dropdown');
        if (dropdown.length) {
            var selectElement = $(this);
            var selectOffset = selectElement.offset();
            var selectHeight = selectElement.outerHeight();
            
            // Calculate position
            var windowHeight = $(window).height();
            var spaceBelow = windowHeight - (selectOffset.top + selectHeight + 50);
            var spaceAbove = selectOffset.top - 50;
            
            // Force dropdown to be just below or above based on space
            if (spaceBelow < 250 && spaceAbove > 250) {
                // Open above
                dropdown.addClass('select2-dropdown--above').removeClass('select2-dropdown--below');
                dropdown.css({
                    'top': 'auto',
                    'bottom': '100%',
                    'margin-top': '0',
                    'margin-bottom': '2px',
                    'left': '0',
                    'right': 'auto',
                    'transform': 'none',
                    'width': '100%',
                    'min-width': '250px',
                    'max-width': '100%',
                    'position': 'absolute'
                });
            } else {
                // Open below
                dropdown.addClass('select2-dropdown--below').removeClass('select2-dropdown--above');
                dropdown.css({
                    'top': '100%',
                    'bottom': 'auto',
                    'margin-top': '2px',
                    'margin-bottom': '0',
                    'left': '0',
                    'right': 'auto',
                    'transform': 'none',
                    'width': '100%',
                    'min-width': '250px',
                    'max-width': '100%',
                    'position': 'absolute'
                });
            }
        }
        
        // Focus search field
        setTimeout(function() {
            var searchField = document.querySelector('.select2-search__field');
            if (searchField) {
                searchField.focus();
                searchField.placeholder = 'সদস্য খুঁজুন...';
                searchField.style.width = '100%';
            }
        }, 150);
    });

    // Fix dropdown on scroll/resize
    $(window).on('scroll resize', function() {
        var dropdown = $('.select2-dropdown');
        if (dropdown.length && dropdown.is(':visible')) {
            var selectElement = $('#memberSelect');
            var selectOffset = selectElement.offset();
            var selectHeight = selectElement.outerHeight();
            var windowHeight = $(window).height();
            var spaceBelow = windowHeight - (selectOffset.top + selectHeight + 50);
            var spaceAbove = selectOffset.top - 50;
            
            if (spaceBelow < 250 && spaceAbove > 250) {
                dropdown.addClass('select2-dropdown--above').removeClass('select2-dropdown--below');
                dropdown.css({
                    'top': 'auto',
                    'bottom': '100%',
                    'margin-top': '0',
                    'margin-bottom': '2px',
                    'left': '0',
                    'right': 'auto',
                    'transform': 'none',
                    'width': '100%',
                    'min-width': '250px',
                    'max-width': '100%',
                    'position': 'absolute'
                });
            } else {
                dropdown.addClass('select2-dropdown--below').removeClass('select2-dropdown--above');
                dropdown.css({
                    'top': '100%',
                    'bottom': 'auto',
                    'margin-top': '2px',
                    'margin-bottom': '0',
                    'left': '0',
                    'right': 'auto',
                    'transform': 'none',
                    'width': '100%',
                    'min-width': '250px',
                    'max-width': '100%',
                    'position': 'absolute'
                });
            }
        }
    });
});

// ============================================
// EMI CALCULATION
// ============================================
function calcEMI() {
  const p = parseFloat(document.getElementById('principal').value) || 0;
  const r = parseFloat(document.getElementById('rate').value) || 0;
  const n = parseInt(document.getElementById('tenure').value) || 0;
  const preview = document.getElementById('emiPreview');
  
  if (!p || !n || p <= 0 || n <= 0) {
    preview.style.display = 'none';
    return;
  }
  
  const mr = (r/100)/12;
  let emi, total, interest;
  if (mr === 0) {
    emi = p/n; total = p; interest = 0;
  } else {
    emi   = p * mr * Math.pow(1+mr,n) / (Math.pow(1+mr,n)-1);
    total = emi * n;
    interest = total - p;
  }
  
  preview.style.display = '';
  document.getElementById('emiVal').textContent      = '৳' + emi.toFixed(2);
  document.getElementById('totalVal').textContent    = '৳' + total.toFixed(2);
  document.getElementById('interestVal').textContent = '৳' + interest.toFixed(2);
}

// Auto calculate on page load
document.addEventListener('DOMContentLoaded', function() {
  calcEMI();
});

// Auto submit on search enter
document.addEventListener('DOMContentLoaded', function() {
  const searchInput = document.querySelector('input[name="s"]');
  if (searchInput) {
    searchInput.addEventListener('keyup', function(e) {
      if (e.key === 'Enter') {
        this.closest('form').submit();
      }
    });
  }
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>