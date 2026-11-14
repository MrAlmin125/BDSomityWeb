<?php
// =====================================================
// admin/profit_distribution.php
// =====================================================
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
requireAdmin();

$pageTitle = 'লাভ বণ্টন';
$plId      = (int)($_GET['pl_id'] ?? 0);

// POST: Save distribution & pay
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('danger','নিরাপত্তা যাচাই ব্যর্থ।'); header('Location: profit_distribution.php'); exit;
    }
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'distribute' && $plId) {
        $pl = queryOne("SELECT * FROM profit_loss WHERE id=? AND status='final'",'i',[$plId]);
        if (!$pl) { setFlash('danger','লাভ চূড়ান্ত না হওয়া পর্যন্ত বণ্টন করা যাবে না।'); header('Location: profit.php'); exit; }

        $distributions = calculateProfitDistribution($plId);
        $count = 0;

        db()->beginTransaction();
        foreach ($distributions as $dist) {
            // Check duplicate
            $exists = queryOne("SELECT id FROM profit_distributions WHERE member_id=? AND fiscal_year=?",'is',[$dist['member_id'],$pl['fiscal_year']]);
            if (!$exists) {
                $stmt = conn()->prepare(
                    "INSERT INTO profit_distributions (profit_loss_id,member_id,fiscal_year,member_deposit,distribution_ratio,profit_amount)
                     VALUES (?,?,?,?,?,?)"
                );
                $stmt->bind_param('iisddd',$plId,$dist['member_id'],$pl['fiscal_year'],$dist['member_deposit'],$dist['distribution_ratio'],$dist['profit_amount']);
                $stmt->execute();
                $count++;
            }
        }
        db()->commit();

        // Notifications
        sendNotification('profit_distribution','লাভ বণ্টন',"{$pl['fiscal_year']} সালের লাভ বণ্টন হয়েছে।",'all');
        logActivity('profit_distribute',"Distributed profit for {$pl['fiscal_year']}, $count members");
        setFlash('success',"$count সদস্যের মধ্যে লাভ বণ্টন সম্পন্ন হয়েছে।");
        header("Location: profit_distribution.php?pl_id=$plId"); exit;
    }

    // Mark as paid
    if ($postAction === 'mark_paid') {
        $distId = (int)$_POST['dist_id'];
        $paidDate = $_POST['paid_date'] ?? date('Y-m-d');
        $by = $_SESSION['admin_id'];
        conn()->query("UPDATE profit_distributions SET status='paid',paid_date='$paidDate',paid_by=$by WHERE id=$distId");
        setFlash('success','লাভ প্রদান সম্পন্ন হিসেবে চিহ্নিত হয়েছে।');
        header("Location: profit_distribution.php?pl_id=$plId"); exit;
    }
}

// Load data
$pl = $plId ? queryOne("SELECT * FROM profit_loss WHERE id=?",'i',[$plId]) : null;
$distributions = $plId ? queryAll(
    "SELECT pd.*,m.name,m.member_id AS mid,m.phone FROM profit_distributions pd
     JOIN members m ON m.id=pd.member_id WHERE pd.profit_loss_id=? ORDER BY pd.profit_amount DESC",
    'i', [$plId]
) : [];

$allPL = queryAll("SELECT id,fiscal_year,distributable_profit,status FROM profit_loss ORDER BY fiscal_year DESC");

require_once __DIR__ . '/../includes/header.php';
?>
<div class="row g-3">
  <div class="col-12 col-lg-4">
    <div class="card">
      <div class="card-header"><span class="card-title">আর্থিক বছর নির্বাচন</span></div>
      <div class="list-group list-group-flush">
        <?php foreach ($allPL as $p): ?>
        <a href="?pl_id=<?= $p['id'] ?>" class="list-group-item list-group-item-action <?= $plId==$p['id']?'active':'' ?>">
          <div class="d-flex justify-content-between">
            <strong><?= $p['fiscal_year'] ?></strong>
            <span><?= formatCurrency((float)$p['distributable_profit']) ?></span>
          </div>
          <div style="font-size:11px;opacity:.75"><?= $p['status']==='final'?'✅ চূড়ান্ত':'📝 খসড়া' ?></div>
        </a>
        <?php endforeach; ?>
        <?php if (empty($allPL)): ?>
        <div class="list-group-item text-muted text-center py-3">কোনো হিসাব নেই</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-8">
    <?php if ($pl): ?>
    <div class="card mb-3">
      <div class="card-body py-3">
        <div class="row text-center g-2">
          <div class="col-4"><div class="text-muted small">বিতরণযোগ্য</div><div class="fw-bold amount-positive"><?= formatCurrency((float)$pl['distributable_profit']) ?></div></div>
          <div class="col-4"><div class="text-muted small">মোট সদস্য</div><div class="fw-bold"><?= count($distributions) ?></div></div>
          <div class="col-4"><div class="text-muted small">অবস্থা</div>
            <span class="badge <?= $pl['status']==='final'?'bg-success':'bg-warning text-dark' ?>"><?= $pl['status']==='final'?'চূড়ান্ত':'খসড়া' ?></span>
          </div>
        </div>
      </div>
    </div>

    <?php if (empty($distributions) && $pl['status']==='final'): ?>
    <div class="card">
      <div class="card-body text-center py-5">
        <p class="text-muted mb-3"><?= $pl['fiscal_year'] ?> এর লাভ এখনো বণ্টন করা হয়নি।</p>
        <form method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="distribute">
          <button class="btn btn-primary">
            <i data-feather="share-2" style="width:14px;height:14px" class="me-1"></i>
            লাভ বণ্টন শুরু করুন
          </button>
        </form>
      </div>
    </div>
    <?php elseif (!empty($distributions)): ?>
    <div class="card">
      <div class="card-header">
        <span class="card-title"><?= $pl['fiscal_year'] ?> — লাভ বণ্টন তালিকা</span>
        <?php $paidCount = count(array_filter($distributions,fn($d)=>$d['status']==='paid')); ?>
        <span class="badge bg-success"><?= $paidCount ?>/<?= count($distributions) ?> প্রদান</span>
      </div>
      <div class="table-responsive">
        <table class="table table-hover">
          <thead><tr><th>সদস্য</th><th>সঞ্চয়</th><th>অনুপাত</th><th>লাভ</th><th>অবস্থা</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($distributions as $d): ?>
            <tr>
              <td>
                <div style="font-size:13px;font-weight:500"><?= clean($d['name']) ?></div>
                <div style="font-size:11px;color:#888"><?= $d['mid'] ?> | <?= $d['phone'] ?></div>
              </td>
              <td class="amount-neutral"><?= formatCurrency((float)$d['member_deposit']) ?></td>
              <td style="font-size:12px"><?= number_format((float)$d['distribution_ratio']*100, 2) ?>%</td>
              <td class="amount-positive fw-bold"><?= formatCurrency((float)$d['profit_amount']) ?></td>
              <td>
                <span class="badge <?= $d['status']==='paid'?'status-paid':'status-pending' ?>">
                  <?= $d['status']==='paid'?'প্রদান হয়েছে':'অপেক্ষমাণ' ?>
                </span>
                <?php if ($d['paid_date']): ?>
                <div style="font-size:10px;color:#888"><?= formatDate($d['paid_date'],'d M Y') ?></div>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($d['status']==='pending'): ?>
                <form method="POST" style="display:inline">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="mark_paid">
                  <input type="hidden" name="dist_id" value="<?= $d['id'] ?>">
                  <input type="hidden" name="paid_date" value="<?= date('Y-m-d') ?>">
                  <button class="btn btn-sm btn-success" title="প্রদান চিহ্নিত করুন">✓</button>
                </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
    <?php else: ?>
    <div class="card">
      <div class="card-body text-center py-5 text-muted">
        বাম থেকে আর্থিক বছর নির্বাচন করুন
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
