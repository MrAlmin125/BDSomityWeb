<?php
// =====================================================
// admin/withdrawals.php - Withdrawal Management 
// =====================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
requireAdmin();

$pageTitle = 'উত্তোলন ব্যবস্থাপনা';
$conn = conn();

// ============================================
// CREATE WITHDRAWAL NOTIFICATION
// ============================================
function createWithdrawalNotification($memberId, $amount, $withdrawalDate, $receiptNo, $type) {
    $member = queryOne("SELECT name FROM members WHERE id = ?", 'i', [$memberId]);
    $memberName = $member['name'] ?? 'সদস্য #' . $memberId;
    
    $formattedAmount = '৳' . number_format($amount, 2);
    $formattedDate = date('d M Y', strtotime($withdrawalDate));
    
    $typeLabels = [
        'partial' => 'আংশিক',
        'full' => 'সম্পূর্ণ',
        'profit' => 'লাভ',
        'emergency' => 'জরুরি'
    ];
    $typeLabel = $typeLabels[$type] ?? $type;
    
    $title = 'উত্তোলন';
    $message = "{$memberName} এর উত্তোলন হয়েছে: {$formattedAmount} (ধরন: {$typeLabel}, তারিখ: {$formattedDate}, রসিদ: {$receiptNo})";
    
    $conn = conn();
    
    $stmt = $conn->prepare(
        "INSERT INTO notifications (type, title, message, target, member_id, member_name, created_at) 
         VALUES ('withdrawal', ?, ?, 'admin', ?, ?, NOW())"
    );
    $stmt->bind_param('ssis', $title, $message, $memberId, $memberName);
    $stmt->execute();
    $stmt->close();
    
    logActivity('withdrawal_notification', "উত্তোলন নোটিফিকেশন তৈরি: $receiptNo - $memberName");
}

// ============================================
// GET MEMBER BALANCE
// ============================================
function getMemberBalance($memberId) {
    $totalDeposit = queryOne("SELECT COALESCE(SUM(amount),0) AS t FROM deposits WHERE member_id=?", 'i', [$memberId])['t'] ?? 0;
    $totalWithdrawn = queryOne("SELECT COALESCE(SUM(amount),0) AS t FROM withdrawals WHERE member_id=? AND status='completed'", 'i', [$memberId])['t'] ?? 0;
    return (float)$totalDeposit - (float)$totalWithdrawn;
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

function generateAvatar($name, $size = 40) {
    $firstLetter = mb_substr($name, 0, 1, 'UTF-8');
    $color = getAvatarColor($name);
    
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 ' . $size . ' ' . $size . '">';
    $svg .= '<rect width="' . $size . '" height="' . $size . '" rx="' . ($size/2) . '" fill="' . $color . '"/>';
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
    return generateAvatar($member['name'], 40);
}

// ============================================
// GET MEMBER DATA FOR AJAX
// ============================================
if (isset($_GET['ajax']) && $_GET['ajax'] === 'member' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $memId = (int)$_GET['id'];
    $member = queryOne("SELECT * FROM members WHERE id = ?", 'i', [$memId]);
    
    if ($member) {
        $totalDeposit = queryOne("SELECT COALESCE(SUM(amount),0) as total FROM deposits WHERE member_id = ?", 'i', [$memId])['total'] ?? 0;
        $totalWithdrawal = queryOne("SELECT COALESCE(SUM(amount),0) as total FROM withdrawals WHERE member_id = ? AND status='completed'", 'i', [$memId])['total'] ?? 0;
        $balance = $totalDeposit - $totalWithdrawal;
        
        $photo = getMemberPhoto($member);
        
        echo json_encode([
            'success' => true,
            'member' => $member,
            'photo' => $photo,
            'total_deposit' => $totalDeposit,
            'total_withdrawal' => $totalWithdrawal,
            'balance' => $balance,
            'monthly_deposit' => $member['monthly_deposit'] ?? 500
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'সদস্য পাওয়া যায়নি']);
    }
    exit;
}

// ============================================
// GET WITHDRAWAL FOR EDIT
// ============================================
$editWithdrawal = null;
if (isset($_GET['edit']) && $_GET['edit'] > 0) {
    $editId = (int)$_GET['edit'];
    $editWithdrawal = queryOne("SELECT * FROM withdrawals WHERE id = ?", 'i', [$editId]);
    if ($editWithdrawal) {
        $memberInfo = queryOne("SELECT name, member_id FROM members WHERE id = ?", 'i', [$editWithdrawal['member_id']]);
    }
}

// ============================================
// HANDLE DELETE WITHDRAWAL
// ============================================
if (isset($_GET['delete']) && $_GET['delete'] > 0) {
    $withdrawalId = (int)$_GET['delete'];
    if (!verifyCSRF($_GET['csrf_token'] ?? '')) {
        setFlash('danger', 'নিরাপত্তা যাচাই ব্যর্থ।');
    } else {
        try {
            $withdrawal = queryOne("SELECT * FROM withdrawals WHERE id = ?", 'i', [$withdrawalId]);
            if ($withdrawal) {
                $member = queryOne("SELECT name FROM members WHERE id = ?", 'i', [$withdrawal['member_id']]);
                $memberName = $member['name'] ?? 'সদস্য #' . $withdrawal['member_id'];
                
                // Delete from deposits
                $conn->query("DELETE FROM deposits WHERE transaction_no = 'WD-" . $withdrawalId . "'");
                $conn->query("DELETE FROM deposits WHERE receipt_no = '" . $withdrawal['receipt_no'] . "'");
                
                // Delete the withdrawal
                $conn->query("DELETE FROM withdrawals WHERE id = $withdrawalId");
                
                $title = 'উত্তোলন ডিলিট';
                $message = "{$memberName} এর উত্তোলন ডিলিট করা হয়েছে (রসিদ: {$withdrawal['receipt_no']})";
                $notifStmt = $conn->prepare(
                    "INSERT INTO notifications (type, title, message, target, member_id, member_name, created_at) 
                     VALUES ('withdrawal', ?, ?, 'admin', ?, ?, NOW())"
                );
                $notifStmt->bind_param('ssis', $title, $message, $withdrawal['member_id'], $memberName);
                $notifStmt->execute();
                $notifStmt->close();
                
                setFlash('success', "উত্তোলন ডিলিট করা হয়েছে। (রসিদ: {$withdrawal['receipt_no']})");
                logActivity('withdrawal_delete', "উত্তোলন ডিলিট: {$withdrawal['receipt_no']}");
            } else {
                setFlash('danger', 'উত্তোলন পাওয়া যায়নি।');
            }
        } catch (Exception $e) {
            setFlash('danger', 'ডিলিট করতে ব্যর্থ হয়েছে: ' . $e->getMessage());
        }
    }
    header('Location: withdrawals.php');
    exit;
}

// ============================================
// HANDLE EDIT WITHDRAWAL
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_withdrawal') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'নিরাপত্তা যাচাই ব্যর্থ।');
    } else {
        $withdrawalId = (int)$_POST['withdrawal_id'];
        $amount = (float)$_POST['amount'];
        $withdrawal_date = $_POST['withdrawal_date'];
        $withdrawal_type = $_POST['withdrawal_type'];
        $reason = trim($_POST['reason'] ?? '');
        
        if ($amount <= 0) {
            setFlash('danger', 'সঠিক পরিমাণ দিন।');
        } else {
            try {
                $oldData = queryOne("SELECT * FROM withdrawals WHERE id = ?", 'i', [$withdrawalId]);
                
                $w = queryOne("SELECT member_id, amount FROM withdrawals WHERE id = ?", 'i', [$withdrawalId]);
                if ($w) {
                    $currentBalance = getMemberBalance($w['member_id']);
                    $newBalance = $currentBalance + $w['amount'] - $amount;
                    if ($newBalance < 0) {
                        setFlash('danger', 'এই পরিমাণ উত্তোলন করা সম্ভব নয়। উপলব্ধ ব্যালেন্স: ' . formatCurrency($currentBalance));
                        header('Location: withdrawals.php?edit=' . $withdrawalId);
                        exit;
                    }
                }
                
                // Update withdrawal
                $stmt = $conn->prepare("
                    UPDATE withdrawals SET 
                        amount = ?,
                        withdrawal_date = ?,
                        withdrawal_type = ?,
                        reason = ?
                    WHERE id = ?
                ");
                $stmt->bind_param('dsssi', $amount, $withdrawal_date, $withdrawal_type, $reason, $withdrawalId);
                $stmt->execute();
                
                // Delete old deposit entry
                $conn->query("DELETE FROM deposits WHERE transaction_no = 'WD-" . $withdrawalId . "'");
                
                // Insert new deposit entry
                $negativeAmount = -$amount;
                $transactionNo = 'WD-' . $withdrawalId;
                $note = "উত্তোলন: " . ($reason ?: "মেম্বার #{$oldData['member_id']} এর উত্তোলন");
                $collectedBy = $_SESSION['admin_id'];
                $receiptNo = $oldData['receipt_no'];
                $memberId = $oldData['member_id'];
                
                $depositStmt = $conn->prepare("
                    INSERT INTO deposits (member_id, amount, deposit_date, deposit_type, payment_method, transaction_no, receipt_no, note, collected_by) 
                    VALUES (?, ?, ?, 'withdrawal', 'cash', ?, ?, ?, ?)
                ");
                $depositStmt->bind_param('idssssi', $memberId, $negativeAmount, $withdrawal_date, $transactionNo, $receiptNo, $note, $collectedBy);
                $depositStmt->execute();
                $depositStmt->close();
                
                if ($oldData) {
                    $member = queryOne("SELECT name FROM members WHERE id = ?", 'i', [$oldData['member_id']]);
                    $memberName = $member['name'] ?? 'সদস্য #' . $oldData['member_id'];
                    
                    $title = 'উত্তোলন আপডেট';
                    $message = "{$memberName} এর উত্তোলন আপডেট হয়েছে: ৳" . number_format($amount, 2) . " (রসিদ: {$oldData['receipt_no']})";
                    
                    $notifStmt = $conn->prepare(
                        "INSERT INTO notifications (type, title, message, target, member_id, member_name, created_at) 
                         VALUES ('withdrawal', ?, ?, 'admin', ?, ?, NOW())"
                    );
                    $notifStmt->bind_param('ssis', $title, $message, $oldData['member_id'], $memberName);
                    $notifStmt->execute();
                    $notifStmt->close();
                }
                
                setFlash('success', 'উত্তোলন আপডেট করা হয়েছে।');
                logActivity('withdrawal_edit', "উত্তোলন আপডেট: ID $withdrawalId");
                
            } catch (Exception $e) {
                setFlash('danger', 'আপডেট করতে ব্যর্থ হয়েছে: ' . $e->getMessage());
            }
        }
    }
    header('Location: withdrawals.php');
    exit;
}

// ============================================
// HANDLE POST: ADD WITHDRAWAL
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('danger','নিরাপত্তা যাচাই ব্যর্থ।'); 
        header('Location: withdrawals.php'); 
        exit;
    }
    
    $mId    = (int)$_POST['member_id'];
    $amount = (float)$_POST['amount'];
    $date   = $_POST['withdrawal_date'] ?? date('Y-m-d');
    $type   = $_POST['withdrawal_type'] ?? 'partial';
    $reason = trim($_POST['reason'] ?? '');
    $by     = $_SESSION['admin_id'];

    $available = getMemberBalance($mId);

    if (!$mId || $amount <= 0) {
        setFlash('danger','সদস্য ও পরিমাণ দিন।');
    } elseif ($amount > $available) {
        setFlash('danger',"উপলব্ধ ব্যালেন্স: ".formatCurrency($available).". এর বেশি উত্তোলন করা যাবে না।");
    } else {
        $receipt = generateReceiptNo('WD');
        
        $conn->begin_transaction();
        try {
            // 1. Insert into withdrawals
            $stmt = $conn->prepare("
                INSERT INTO withdrawals (member_id, amount, withdrawal_date, withdrawal_type, reason, receipt_no, status, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, 'completed', ?)
            ");
            $stmt->bind_param('idssssi', $mId, $amount, $date, $type, $reason, $receipt, $by);
            $stmt->execute();
            $withdrawalId = $conn->insert_id;
            
            $conn->query("UPDATE withdrawals SET approved_by=$by, approved_at=NOW() WHERE id=$withdrawalId");
            
            // ============================================
            // 2. Insert into deposits as deduction
            // ============================================
            $negativeAmount = -$amount;
            $transactionNo = 'WD-' . $withdrawalId;
            $note = "উত্তোলন: " . ($reason ?: "মেম্বার #$mId এর উত্তোলন");
            $collectedBy = $by;
            
            $depositStmt = $conn->prepare("
                INSERT INTO deposits (member_id, amount, deposit_date, deposit_type, payment_method, transaction_no, receipt_no, note, collected_by) 
                VALUES (?, ?, ?, 'withdrawal', 'cash', ?, ?, ?, ?)
            ");
            // 7 parameters: member_id, amount, deposit_date, deposit_type, payment_method, transaction_no, receipt_no, note, collected_by
            // Actually 9 fields but payment_method is static 'cash'
            // So: member_id, amount, deposit_date, transaction_no, receipt_no, note, collected_by = 7 parameters
            $depositStmt->bind_param('idssssi', $mId, $negativeAmount, $date, $transactionNo, $receipt, $note, $collectedBy);
            $depositStmt->execute();
            $depositStmt->close();
            
            $conn->commit();
            
            createWithdrawalNotification($mId, $amount, $date, $receipt, $type);
            
            logActivity('withdrawal_add', "Withdrawal ৳$amount for member $mId, receipt $receipt");
            setFlash('success', "উত্তোলন সফল! রসিদ: $receipt | পরিমাণ: ".formatCurrency($amount));
            header('Location: withdrawals.php'); 
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            setFlash('danger', 'উত্তোলন ব্যর্থ: ' . $e->getMessage());
        }
    }
}

// ============================================
// GET DATA FOR LIST
// ============================================
$search = trim($_GET['s'] ?? '');
$typeF  = $_GET['type'] ?? '';
$fromDate = $_GET['from'] ?? '';
$toDate   = $_GET['to'] ?? '';
$member_id_filter = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;
$page     = max(1, (int)($_GET['page'] ?? 1));

$where = '1=1'; 
$types = ''; 
$params = [];

if ($member_id_filter > 0) {
    $where .= " AND w.member_id = ?";
    $types .= 'i';
    $params[] = $member_id_filter;
}

if ($search) { 
    $where .= " AND (m.name LIKE ? OR m.phone LIKE ? OR m.member_id LIKE ? OR w.receipt_no LIKE ? OR w.reason LIKE ?)"; 
    $like = "%$search%"; 
    $types .= 'sssss'; 
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($typeF) { 
    $where .= " AND w.withdrawal_type=?"; 
    $types .= 's'; 
    $params[] = $typeF; 
}
if ($fromDate) { 
    $where .= " AND w.withdrawal_date>=?"; 
    $types .= 's'; 
    $params[] = $fromDate; 
}
if ($toDate) { 
    $where .= " AND w.withdrawal_date<=?"; 
    $types .= 's'; 
    $params[] = $toDate; 
}

$sql = "SELECT w.*, m.name AS member_name, m.phone AS member_phone, m.member_id AS mid, m.photo,
               (SELECT COALESCE(SUM(amount),0) FROM deposits WHERE member_id = m.id) - 
               (SELECT COALESCE(SUM(amount),0) FROM withdrawals WHERE member_id = m.id AND status='completed') AS current_balance
        FROM withdrawals w 
        JOIN members m ON m.id = w.member_id 
        WHERE $where 
        ORDER BY w.withdrawal_date DESC, w.id DESC";

$activeMembers = queryAll("SELECT id, member_id, name, phone FROM members WHERE status='active' ORDER BY name");

$preSelected = isset($_GET['member_id']) ? queryOne("SELECT id, member_id, name FROM members WHERE id=?", 'i', [(int)$_GET['member_id']]) : null;

$memberBalances = [];
foreach ($activeMembers as $m) {
    $memberBalances[$m['id']] = getMemberBalance($m['id']);
}

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

<div class="container-fluid px-3">
<div class="row g-3">

  <!-- LEFT: ADD WITHDRAWAL FORM -->
  <div class="col-12 col-lg-5">
    <div class="card sticky-top" style="top: 80px; z-index: 100;">
      <div class="card-header">
        <span class="card-title"><i data-feather="arrow-up-circle" style="width:16px;height:16px"></i> উত্তোলন</span>
      </div>
      <div class="card-body" style="max-height: calc(100vh - 200px); overflow-y: auto;">
        <form method="POST" id="withdrawalForm">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="add">

          <div class="mb-3">
            <label class="form-label">সদস্য নির্বাচন <span class="text-danger">*</span></label>
            <select name="member_id" id="memberSelect" class="form-control select2" required>
              <option value="">— সদস্য নির্বাচন করুন —</option>
              <?php foreach ($activeMembers as $m): ?>
              <option value="<?= $m['id'] ?>" 
                      <?= $preSelected && $preSelected['id']==$m['id']?'selected':'' ?>
                      data-balance="<?= $memberBalances[$m['id']] ?? 0 ?>"
                      data-name="<?= clean($m['name']) ?>"
                      data-member-id="<?= $m['member_id'] ?>"
                      data-phone="<?= $m['phone'] ?>">
                <?= clean($m['name']) ?> (<?= $m['member_id'] ?>) - <?= $m['phone'] ?> | ব্যালেন্স: <?= formatCurrency($memberBalances[$m['id']] ?? 0) ?>
              </option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">
              <i data-feather="search" style="width:14px;height:14px"></i>
              নাম, সদস্য আইডি বা ফোন দিয়ে খুঁজুন
            </div>
          </div>

          <div class="col-12" id="memberInfo" style="display: none;">
            <div class="card bg-light">
              <div class="card-body py-2">
                <div class="row align-items-center">
                  <div class="col-auto">
                    <img id="memberPhoto" src="" alt="Photo" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                  </div>
                  <div class="col">
                    <div class="row g-1">
                      <div class="col-12">
                        <small class="text-muted">নাম</small>
                        <div><strong id="displayName" style="font-size:14px;">-</strong></div>
                      </div>
                      <div class="col-6">
                        <small class="text-muted">সদস্য ID</small>
                        <div><strong id="displayMemberId" style="font-size:13px;">-</strong></div>
                      </div>
                      <div class="col-6">
                        <small class="text-muted">মাসিক জমা</small>
                        <div><strong id="displayMonthlyDeposit" style="font-size:13px;">-</strong></div>
                      </div>
                      <div class="col-4">
                        <small class="text-muted">মোট জমা</small>
                        <div><strong id="displayTotalDeposit" class="text-success" style="font-size:13px;">-</strong></div>
                      </div>
                      <div class="col-4">
                        <small class="text-muted">মোট উত্তোলন</small>
                        <div><strong id="displayTotalWithdrawal" class="text-danger" style="font-size:13px;">-</strong></div>
                      </div>
                      <div class="col-4">
                        <small class="text-muted">বর্তমান ব্যালেন্স</small>
                        <div><strong id="displayBalance" class="text-primary" style="font-size:13px;">-</strong></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">ধরন</label>
            <select name="withdrawal_type" class="form-select">
              <option value="partial">আংশিক উত্তোলন</option>
              <option value="full">সম্পূর্ণ উত্তোলন</option>
              <option value="profit">লাভ উত্তোলন</option>
              <option value="emergency">জরুরি উত্তোলন</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">উত্তোলনের পরিমাণ (৳) <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text">৳</span>
              <input type="number" name="amount" id="withdrawAmount" class="form-control" 
                     step="0.01" min="1" placeholder="০.০০" required onkeyup="checkBalance()">
            </div>
            <div id="balanceWarning" class="text-danger small mt-1" style="display:none;"></div>
            <small class="text-muted">সর্বোচ্চ উত্তোলন: <span id="maxWithdraw">0.00</span> ৳</small>
          </div>

          <div class="mb-3">
            <label class="form-label">তারিখ</label>
            <input type="date" name="withdrawal_date" class="form-control" value="<?= date('Y-m-d') ?>">
          </div>

          <div class="mb-3">
            <label class="form-label">কারণ</label>
            <textarea name="reason" class="form-control" rows="2" placeholder="ঐচ্ছিক"></textarea>
          </div>

          <div class="alert alert-warning small">
            <i data-feather="info" style="width:14px;height:14px"></i>
            উত্তোলন করলে <strong>মোট জমা</strong> থেকে স্বয়ংক্রিয়ভাবে কেটে নেওয়া হবে।
          </div>

          <button type="submit" class="btn btn-warning w-100 text-dark" id="submitBtn">
            <i data-feather="arrow-up-circle" style="width:14px;height:14px" class="me-1"></i>
            উত্তোলন করুন
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- RIGHT: WITHDRAWAL HISTORY -->
  <div class="col-12 col-lg-7">
    <div class="card">
      <div class="card-header">
        <span class="card-title">
          <i data-feather="list" style="width:16px;height:16px"></i>
          উত্তোলনের ইতিহাস
        </span>
        <button class="btn btn-outline-info btn-sm" onclick="window.print()">
          <i data-feather="printer" style="width:14px;height:14px"></i> প্রিন্ট
        </button>
      </div>

      <div class="card-body border-bottom pb-3">
        <form method="GET" class="row g-2">
          <div class="col-12 col-md-4">
            <div class="search-box">
              <i data-feather="search" class="search-icon"></i>
              <input type="text" name="s" class="form-control form-control-sm" 
                     placeholder="নাম, ফোন, আইডি, রসিদ..." value="<?= clean($search) ?>">
            </div>
          </div>
          <div class="col-4 col-md-2">
            <select name="type" class="form-select form-select-sm">
              <option value="">ধরন</option>
              <option value="partial"  <?= $typeF==='partial'?'selected':'' ?>>আংশিক</option>
              <option value="full"     <?= $typeF==='full'?'selected':'' ?>>সম্পূর্ণ</option>
              <option value="profit"   <?= $typeF==='profit'?'selected':'' ?>>লাভ</option>
              <option value="emergency"<?= $typeF==='emergency'?'selected':'' ?>>জরুরি</option>
            </select>
          </div>
          <div class="col-4 col-md-2">
            <input type="date" name="from" class="form-control form-control-sm" value="<?= $fromDate ?>" placeholder="শুরু">
          </div>
          <div class="col-4 col-md-2">
            <input type="date" name="to" class="form-control form-control-sm" value="<?= $toDate ?>" placeholder="শেষ">
          </div>
          <div class="col-6 col-md-2">
            <button class="btn btn-primary btn-sm w-100">
              <i data-feather="search" style="width:14px;height:14px"></i> খুঁজুন
            </button>
          </div>
          <?php if ($search || $typeF || $fromDate || $toDate || $member_id_filter > 0): ?>
          <div class="col-6 col-md-2">
            <a href="withdrawals.php" class="btn btn-outline-secondary btn-sm w-100">
              <i data-feather="x" style="width:14px;height:14px"></i> রিসেট
            </a>
          </div>
          <?php endif; ?>
          <?php if ($member_id_filter > 0): ?>
          <input type="hidden" name="member_id" value="<?= $member_id_filter ?>">
          <?php endif; ?>
        </form>
      </div>

      <?php
      $paged = paginate($sql, $types, $params, $page);
      $grandTotal = 0;
      
      if (!empty($paged['data'])) {
          $sumSql = "SELECT COALESCE(SUM(w.amount),0) AS s FROM withdrawals w JOIN members m ON m.id=w.member_id WHERE $where";
          $sumRow = queryOne($sumSql, $types, $params);
          $grandTotal = (float)($sumRow['s'] ?? 0);
      }
      ?>

      <?php if ($grandTotal > 0): ?>
      <div class="px-3 py-2 bg-light border-bottom" style="font-size:13px">
        <strong>মোট উত্তোলন:</strong> <span class="amount-negative"><?= formatCurrency($grandTotal) ?></span>
        &nbsp;|&nbsp; <strong>রেকর্ড:</strong> <?= $paged['total'] ?>
      </div>
      <?php endif; ?>

      <div class="table-responsive">
        <table class="table table-hover table-sm" id="withdrawalTable">
          <thead>
            <tr>
              <th>রসিদ</th>
              <th>সদস্য</th>
              <th>ধরন</th>
              <th>উত্তোলন পরিমাণ</th>
              <th>বর্তমান ব্যালেন্স</th>
              <th>কারণ</th>
              <th>তারিখ</th>
              <th class="text-center" style="min-width:120px;">কার্যক্রম</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($paged['data'])): ?>
            <tr>
              <td colspan="8" class="text-center py-4">
                <div class="empty-state">
                  <i data-feather="inbox" style="width:48px;height:48px;color:#ccc;display:block;margin:0 auto 10px;"></i>
                  <h6 class="text-muted">কোনো উত্তোলন পাওয়া যায়নি</h6>
                  <p class="text-muted small">উপরে সার্চ করে দেখুন অথবা নতুন উত্তোলন করুন</p>
                </div>
              </td>
            </tr>
            <?php else: ?>
            <?php foreach ($paged['data'] as $w): 
                $photoFile = !empty($w['photo']) ? $w['photo'] : '';
                if (!empty($photoFile)) {
                    if (strpos($photoFile, '/') !== false) {
                        $photoFile = basename($photoFile);
                    }
                    $photoUrl = '/uploads/members/' . $photoFile;
                    $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/uploads/members/' . $photoFile;
                    $hasPhoto = file_exists($fullPath);
                } else {
                    $hasPhoto = false;
                    $photoUrl = '';
                }
            ?>
            <tr class="clickable-row" data-href="member_profile.php?id=<?= $w['member_id'] ?>" style="cursor:pointer;">
              <td>
                <code style="font-size:11px;"><?= clean($w['receipt_no'] ?? '-') ?></code>
              </td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <?php if ($hasPhoto): ?>
                    <img src="<?= htmlspecialchars($photoUrl) ?>" 
                         alt="<?= clean($w['member_name']) ?>" 
                         class="member-avatar-sm"
                         onerror="this.style.display='none'; this.parentElement.querySelector('.avatar-placeholder').style.display='flex';">
                    <div class="avatar-placeholder" style="display:none;">
                      <?= strtoupper(substr($w['member_name'], 0, 1)) ?>
                    </div>
                  <?php else: ?>
                    <div class="avatar-placeholder">
                      <?= strtoupper(substr($w['member_name'], 0, 1)) ?>
                    </div>
                  <?php endif; ?>
                  <div>
                    <div style="font-weight:500;font-size:13px;">
                      <?= clean($w['member_name']) ?>
                    </div>
                    <small class="text-muted" style="font-size:11px;"><?= clean($w['mid']) ?></small>
                  </div>
                </div>
              </td>
              <td>
                <?php 
                $typeLabels = [
                    'partial'=>'আংশিক',
                    'full'=>'সম্পূর্ণ',
                    'profit'=>'লাভ',
                    'emergency'=>'জরুরি'
                ]; 
                ?>
                <span class="badge bg-light text-dark"><?= $typeLabels[$w['withdrawal_type']] ?? $w['withdrawal_type'] ?></span>
              </td>
              <td class="amount-negative" style="font-weight:600;"><?= formatCurrency((float)$w['amount']) ?></td>
              <td>
                <?php 
                $balance = isset($w['current_balance']) ? (float)$w['current_balance'] : 0;
                $balanceClass = $balance >= 0 ? 'text-success' : 'text-danger';
                ?>
                <span class="<?= $balanceClass ?>" style="font-weight:600;">
                  <?= formatCurrency($balance) ?>
                </span>
              </td>
              <td style="max-width:150px;font-size:12px;word-wrap:break-word;">
                <?= clean($w['reason'] ?? '-') ?>
              </td>
              <td style="font-size:12px;"><?= formatDate($w['withdrawal_date']) ?></td>
              <td class="text-center" onclick="event.stopPropagation();">
                <div class="btn-group btn-group-sm" role="group">
                  <button class="btn btn-outline-info" 
                          onclick="printReceipt('<?= $w['receipt_no'] ?>', '<?= addslashes($w['member_name']) ?>', '<?= $w['amount'] ?>', '<?= $w['withdrawal_date'] ?>', '<?= $w['withdrawal_type'] ?>', '<?= addslashes($w['reason'] ?? '') ?>')" 
                          title="প্রিন্ট রসিদ">
                    <i data-feather="printer" style="width:13px;height:13px"></i>
                  </button>
                  
                  <a href="withdrawals.php?edit=<?= $w['id'] ?>" 
                     class="btn btn-outline-primary" 
                     title="সম্পাদনা">
                    <i data-feather="edit-2" style="width:13px;height:13px"></i>
                  </a>
                  
                  <a href="withdrawals.php?delete=<?= $w['id'] ?>&csrf_token=<?= generateCSRF() ?>" 
                     class="btn btn-outline-danger" 
                     onclick="return confirm('আপনি কি নিশ্চিত? এই উত্তোলন ডিলিট হবে!')" 
                     title="ডিলিট">
                    <i data-feather="trash-2" style="width:13px;height:13px"></i>
                  </a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
          <?php if (!empty($paged['data'])): ?>
          <tfoot>
            <tr class="table-active">
              <td colspan="3"><strong>সর্বমোট</strong></td>
              <td><strong><?= formatCurrency($grandTotal) ?></strong></td>
              <td colspan="4"></td>
            </tr>
          </tfoot>
          <?php endif; ?>
        </table>
      </div>
      <div class="card-body pt-2">
        <?= renderPagination($paged, "withdrawals.php?s=$search&type=$typeF&from=$fromDate&to=$toDate&member_id=$member_id_filter") ?>
      </div>
    </div>
  </div>

</div>
</div>

<!-- ============================================
     EDIT WITHDRAWAL MODAL
     ============================================ -->
<?php if ($editWithdrawal): ?>
<div class="modal fade show" id="editModal" tabindex="-1" style="display:block;background:rgba(0,0,0,0.5);">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i data-feather="edit-2" style="width:18px;height:18px"></i>
                    উত্তোলন সম্পাদনা
                </h5>
                <a href="withdrawals.php" class="btn-close"></a>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="edit_withdrawal">
                    <input type="hidden" name="withdrawal_id" value="<?= $editWithdrawal['id'] ?>">

                    <div class="mb-3">
                        <label class="form-label">সদস্য</label>
                        <input type="text" class="form-control" 
                               value="<?= clean($memberInfo['name'] ?? '') ?> (<?= $memberInfo['member_id'] ?? '' ?>)" disabled>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">রসিদ নং</label>
                        <input type="text" class="form-control" value="<?= $editWithdrawal['receipt_no'] ?>" disabled>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">উত্তোলন পরিমাণ (৳) <span class="text-danger">*</span></label>
                        <input type="number" name="amount" class="form-control" 
                               value="<?= $editWithdrawal['amount'] ?>" step="0.01" min="1" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">তারিখ</label>
                        <input type="date" name="withdrawal_date" class="form-control" 
                               value="<?= $editWithdrawal['withdrawal_date'] ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">ধরন</label>
                        <select name="withdrawal_type" class="form-select">
                            <option value="partial" <?= $editWithdrawal['withdrawal_type'] == 'partial' ? 'selected' : '' ?>>আংশিক</option>
                            <option value="full" <?= $editWithdrawal['withdrawal_type'] == 'full' ? 'selected' : '' ?>>সম্পূর্ণ</option>
                            <option value="profit" <?= $editWithdrawal['withdrawal_type'] == 'profit' ? 'selected' : '' ?>>লাভ</option>
                            <option value="emergency" <?= $editWithdrawal['withdrawal_type'] == 'emergency' ? 'selected' : '' ?>>জরুরি</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">কারণ</label>
                        <input type="text" name="reason" class="form-control" 
                               value="<?= clean($editWithdrawal['reason'] ?? '') ?>" placeholder="ঐচ্ছিক">
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="withdrawals.php" class="btn btn-secondary">বাতিল</a>
                    <button type="submit" class="btn btn-primary">
                        <i data-feather="save" style="width:14px;height:14px"></i> আপডেট করুন
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ============================================
     PRINT RECEIPT MODAL
     ============================================ -->
<div class="modal fade" id="receiptModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">উত্তোলন রসিদ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="receiptContent">
                <!-- Receipt content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary btn-sm" onclick="printReceiptContent()">
                    <i data-feather="printer" style="width:14px;height:14px"></i> প্রিন্ট
                </button>
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">বন্ধ</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
     JQUERY & SELECT2 CDN
     ============================================ -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
.sticky-top {
    position: sticky;
    top: 80px;
    z-index: 100;
}

.amount-negative { color: #E74C3C; font-weight: 600; }
.amount-positive { color: #27AE60; font-weight: 600; }

.member-avatar-sm {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #e9ecef;
    background-color: #f8f9fa;
    flex-shrink: 0;
}
.avatar-placeholder {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #1B4F72, #2E86C1);
    color: white;
    font-weight: bold;
    font-size: 14px;
    border: 2px solid #e9ecef;
    flex-shrink: 0;
}

.search-box {
    position: relative;
}
.search-box .search-icon {
    position: absolute;
    left: 8px;
    top: 50%;
    transform: translateY(-50%);
    width: 14px;
    height: 14px;
    color: #999;
}
.search-box input {
    padding-left: 28px;
}

.card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    margin-bottom: 0;
}
.card-header {
    background: white;
    border-bottom: 1px solid #f0f0f0;
    padding: 10px 16px;
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
    padding: 8px 10px;
}
.table-hover tbody tr:hover {
    background: #f8f9fa;
}

.select2-container--default .select2-selection--single {
    height: 38px !important;
    border: 1px solid #ced4da !important;
    border-radius: 6px !important;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 36px !important;
    padding-left: 12px !important;
    color: #212529 !important;
    font-size: 14px !important;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 36px !important;
}
.select2-container--default .select2-dropdown {
    border: 1px solid #ced4da !important;
    border-radius: 6px !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important;
}
.select2-container--default .select2-search--dropdown {
    padding: 8px !important;
}
.select2-container--default .select2-search--dropdown .select2-search__field {
    border: 1px solid #ced4da !important;
    border-radius: 4px !important;
    padding: 6px 10px !important;
    font-size: 14px !important;
    width: 100% !important;
}
.select2-container--default .select2-results__option {
    padding: 8px 12px !important;
    font-size: 14px !important;
}
.select2-container--default .select2-results__option--highlighted {
    background-color: #e3f2fd !important;
    color: #0d47a1 !important;
}
.select2-container--default .select2-results__option--selected {
    background-color: #bbdefb !important;
    color: #0d47a1 !important;
}

.empty-state { padding: 20px 0; }
.empty-state i { color: #ddd; }

.modal.show { display: block !important; }
.modal-backdrop { display: none; }

.clickable-row { cursor: pointer; }
.clickable-row:hover { background-color: #f0f8ff !important; }
.clickable-row .btn-group { position: relative; z-index: 10; }

.receipt-container {
    font-family: 'Courier New', monospace;
    font-size: 12px;
    padding: 10px;
}
.receipt-container .receipt-header {
    text-align: center;
    border-bottom: 2px dashed #333;
    padding-bottom: 8px;
    margin-bottom: 8px;
}
.receipt-container .receipt-header h5 { margin: 0; font-weight: bold; }
.receipt-container .receipt-body { padding: 5px 0; }
.receipt-container .receipt-row {
    display: flex;
    justify-content: space-between;
    padding: 3px 0;
    border-bottom: 1px dotted #ddd;
}
.receipt-container .receipt-footer {
    text-align: center;
    border-top: 2px dashed #333;
    padding-top: 8px;
    margin-top: 8px;
}

@media (max-width: 992px) {
    .sticky-top { position: relative; top: 0; }
    .card-body { max-height: none !important; overflow-y: visible !important; }
}

@media (max-width: 768px) {
    .table td, .table th { padding: 6px 8px; font-size: 12px; }
    .card-header { padding: 8px 12px; }
    .card-body { padding: 10px 12px; }
    .btn-group.btn-group-sm .btn { padding: 0.25rem 0.4rem; }
}

@media print {
    .btn, .btn-group, .no-print, .clickable-row { display: none !important; }
    .card { box-shadow: none !important; border: 1px solid #ddd !important; }
    .sticky-top { position: relative !important; top: 0 !important; }
    .table { font-size: 12px !important; }
    .modal { position: static !important; display: block !important; background: none !important; }
    .modal-dialog { margin: 0 !important; max-width: 100% !important; }
    .modal-content { border: none !important; box-shadow: none !important; }
    .modal-footer { display: none !important; }
}
</style>

<script>
function loadMemberData(memberId) {
    if (!memberId) {
        document.getElementById('memberInfo').style.display = 'none';
        document.getElementById('maxWithdraw').textContent = '0.00';
        return;
    }
    
    fetch(`withdrawals.php?ajax=member&id=${memberId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('memberInfo').style.display = 'block';
                
                document.getElementById('memberPhoto').src = data.photo;
                document.getElementById('displayName').textContent = data.member.name;
                document.getElementById('displayMemberId').textContent = data.member.member_id;
                document.getElementById('displayMonthlyDeposit').textContent = formatCurrency(data.monthly_deposit);
                document.getElementById('displayTotalDeposit').textContent = formatCurrency(data.total_deposit);
                document.getElementById('displayTotalWithdrawal').textContent = formatCurrency(data.total_withdrawal);
                document.getElementById('displayBalance').textContent = formatCurrency(data.balance);
                
                document.getElementById('maxWithdraw').textContent = data.balance.toFixed(2);
                
                const amountField = document.getElementById('withdrawAmount');
                amountField.value = data.balance;
                amountField.max = data.balance;
                
                checkBalance();
            } else {
                alert(data.message || 'সদস্য ডেটা লোড করতে ব্যর্থ হয়েছে');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('সদস্য ডেটা লোড করতে ব্যর্থ হয়েছে');
        });
}

let currentBalance = 0;

function checkBalance() {
    const amount = parseFloat(document.getElementById('withdrawAmount').value) || 0;
    const maxBalance = parseFloat(document.getElementById('maxWithdraw').textContent) || 0;
    const warning = document.getElementById('balanceWarning');
    const submitBtn = document.getElementById('submitBtn');
    
    if (amount > maxBalance) {
        warning.style.display = 'block';
        warning.textContent = '⚠️ পরিমাণ (' + amount + ') উপলব্ধ ব্যালেন্স (' + maxBalance.toFixed(2) + ') থেকে বেশি!';
        submitBtn.disabled = true;
    } else if (amount <= 0) {
        warning.style.display = 'block';
        warning.textContent = '⚠️ সঠিক পরিমাণ দিন।';
        submitBtn.disabled = true;
    } else {
        warning.style.display = 'none';
        submitBtn.disabled = false;
    }
}

function formatCurrency(amount) {
    return '৳ ' + parseFloat(amount).toFixed(2);
}

function printReceipt(receiptNo, memberName, amount, date, type, reason) {
    const typeLabels = {
        'partial': 'আংশিক উত্তোলন',
        'full': 'সম্পূর্ণ উত্তোলন',
        'profit': 'লাভ উত্তোলন',
        'emergency': 'জরুরি উত্তোলন'
    };
    
    const receiptHTML = `
        <div class="receipt-container">
            <div class="receipt-header">
                <h5>উত্তোলন রসিদ</h5>
                <small>রসিদ নং: ${receiptNo}</small>
            </div>
            <div class="receipt-body">
                <div class="receipt-row"><span><strong>সদস্য:</strong></span><span>${memberName}</span></div>
                <div class="receipt-row"><span><strong>পরিমাণ:</strong></span><span>${formatCurrency(parseFloat(amount))}</span></div>
                <div class="receipt-row"><span><strong>ধরন:</strong></span><span>${typeLabels[type] || type}</span></div>
                ${reason ? `<div class="receipt-row"><span><strong>কারণ:</strong></span><span>${reason}</span></div>` : ''}
                <div class="receipt-row"><span><strong>তারিখ:</strong></span><span>${date}</span></div>
            </div>
            <div class="receipt-footer"><small>ধন্যবাদ</small></div>
        </div>
    `;
    
    document.getElementById('receiptContent').innerHTML = receiptHTML;
    var myModal = new bootstrap.Modal(document.getElementById('receiptModal'));
    myModal.show();
}

function printReceiptContent() {
    var content = document.getElementById('receiptContent').innerHTML;
    var win = window.open('', '', 'width=400,height=500');
    win.document.write(`
        <html>
            <head><title>উত্তোলন রসিদ</title>
            <style>
                body { font-family: 'Courier New', monospace; font-size: 12px; padding: 20px; }
                .receipt-container { max-width: 350px; margin: 0 auto; }
                .receipt-header { text-align: center; border-bottom: 2px dashed #333; padding-bottom: 8px; margin-bottom: 8px; }
                .receipt-header h5 { margin: 0; font-weight: bold; }
                .receipt-body { padding: 5px 0; }
                .receipt-row { display: flex; justify-content: space-between; padding: 3px 0; border-bottom: 1px dotted #ddd; }
                .receipt-footer { text-align: center; border-top: 2px dashed #333; padding-top: 8px; margin-top: 8px; }
                @media print { body { margin: 0; padding: 10px; } }
            </style>
            </head>
            <body>${content}
            <script>window.onload = function() { window.print(); window.close(); }<\/script>
        </body></html>
    `);
    win.document.close();
}

$(document).ready(function() {
    if (typeof feather !== 'undefined') feather.replace();
    
    $('#memberSelect').select2({
        placeholder: '-- সদস্য নির্বাচন করুন --',
        allowClear: true,
        width: '100%',
        language: {
            searching: () => 'খুঁজছেন...',
            noResults: () => 'কোনো সদস্য পাওয়া যায়নি',
            inputTooShort: () => 'অনুগ্রহ করে আরও অক্ষর টাইপ করুন'
        },
        templateResult: function(data) {
            if (!data.id) return data.text;
            var $result = $('<div><strong>' + $(data.element).data('name') + '</strong><br><small>ID: ' + $(data.element).data('member-id') + ' | ফোন: ' + $(data.element).data('phone') + '</small></div>');
            return $result;
        },
        templateSelection: function(data) {
            if (!data.id) return data.text;
            return $(data.element).data('name') + ' (' + $(data.element).data('member-id') + ')';
        }
    });
    
    $('#memberSelect').on('change', function() { loadMemberData(this.value); });
    
    <?php if ($preSelected): ?>loadMemberData(<?= $preSelected['id'] ?>);<?php endif; ?>
    
    $('.form-select-sm, input[type="date"]').on('change', function() {
        if ($(this).closest('form')) $(this).closest('form').submit();
    });
    
    $('.clickable-row').on('click', function(e) {
        if ($(e.target).closest('.btn, .btn-group, a').length) return;
        var href = $(this).data('href');
        if (href) window.location.href = href;
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>