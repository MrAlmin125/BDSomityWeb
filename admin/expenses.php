<?php
// =====================================================
// admin/expenses.php - Expense Management 
// Deducts from TOTAL DEPOSIT (not individual member balance)
// WITH Edit, Print & Delete (Modal Popup) - BENGALI FIXED
// =====================================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
requireAdmin();
$pageTitle = 'খরচ ব্যবস্থাপনা';

// ============================================
// CATEGORY LABELS (Bengali)
// ============================================
function getCategoryLabel($category) {
    $labels = [
        'office' => 'অফিস',
        'salary' => 'বেতন/ভাতা',
        'maintenance' => 'রক্ষণাবেক্ষণ',
        'event' => 'অনুষ্ঠান',
        'other' => 'অন্যান্য'
    ];
    return $labels[$category] ?? $category;
}

function getCategoryOptions($selected = '') {
    $options = [
        'office' => 'অফিস',
        'salary' => 'বেতন/ভাতা',
        'maintenance' => 'রক্ষণাবেক্ষণ',
        'event' => 'অনুষ্ঠান',
        'other' => 'অন্যান্য'
    ];
    $html = '';
    foreach ($options as $value => $label) {
        $sel = ($selected == $value) ? 'selected' : '';
        $html .= "<option value=\"{$value}\" {$sel}>{$label}</option>";
    }
    return $html;
}

// ============================================
// FUNCTION: Add expense and deduct from total deposit
// ============================================
function addExpenseWithDeduction($title, $category, $amount, $expenseDate, $description, $createdBy) {
    $conn = conn();
    
    $conn->begin_transaction();
    
    try {
        $stmt = $conn->prepare("INSERT INTO expenses (title, category, amount, expense_date, description, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssdssi', $title, $category, $amount, $expenseDate, $description, $createdBy);
        $stmt->execute();
        $expenseId = $conn->insert_id;
        $stmt->close();
        
        $receiptNo = 'EXP-' . date('Ymd') . '-' . str_pad($expenseId, 5, '0', STR_PAD_LEFT);
        
        $memberId = null;
        $negativeAmount = -$amount;
        $paymentMethod = 'cash';
        $transactionNo = 'EXP-' . $expenseId;
        $note = "খরচ: " . $title . ($description ? " - " . $description : "");
        $collectedBy = $createdBy;
        
        $depositStmt = $conn->prepare("
            INSERT INTO deposits (member_id, amount, deposit_date, deposit_type, payment_method, transaction_no, receipt_no, note, collected_by) 
            VALUES (?, ?, ?, 'expense', ?, ?, ?, ?, ?)
        ");
        $depositStmt->bind_param('idsssssi', $memberId, $negativeAmount, $expenseDate, $paymentMethod, $transactionNo, $receiptNo, $note, $collectedBy);
        $depositStmt->execute();
        $depositStmt->close();
        
        $conn->commit();
        
        return ['success' => true, 'expense_id' => $expenseId, 'receipt_no' => $receiptNo];
        
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// ============================================
// FUNCTION: Update expense
// ============================================
function updateExpense($id, $title, $category, $amount, $expenseDate, $description) {
    $conn = conn();
    
    $conn->begin_transaction();
    
    try {
        $oldExpense = queryOne("SELECT * FROM expenses WHERE id = ?", 'i', [$id]);
        if (!$oldExpense) {
            return ['success' => false, 'message' => 'খরচ পাওয়া যায়নি'];
        }
        
        $stmt = $conn->prepare("UPDATE expenses SET title = ?, category = ?, amount = ?, expense_date = ?, description = ? WHERE id = ?");
        $stmt->bind_param('ssdssi', $title, $category, $amount, $expenseDate, $description, $id);
        $stmt->execute();
        $stmt->close();
        
        $conn->query("DELETE FROM deposits WHERE transaction_no = 'EXP-" . $id . "' AND deposit_type = 'expense'");
        
        $receiptNo = 'EXP-' . date('Ymd') . '-' . str_pad($id, 5, '0', STR_PAD_LEFT);
        $memberId = null;
        $negativeAmount = -$amount;
        $paymentMethod = 'cash';
        $transactionNo = 'EXP-' . $id;
        $note = "খরচ: " . $title . ($description ? " - " . $description : "");
        $collectedBy = $_SESSION['admin_id'];
        
        $depositStmt = $conn->prepare("
            INSERT INTO deposits (member_id, amount, deposit_date, deposit_type, payment_method, transaction_no, receipt_no, note, collected_by) 
            VALUES (?, ?, ?, 'expense', ?, ?, ?, ?, ?)
        ");
        $depositStmt->bind_param('idsssssi', $memberId, $negativeAmount, $expenseDate, $paymentMethod, $transactionNo, $receiptNo, $note, $collectedBy);
        $depositStmt->execute();
        $depositStmt->close();
        
        $conn->commit();
        
        return ['success' => true];
        
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// ============================================
// FUNCTION: Delete expense and reverse deduction
// ============================================
function deleteExpenseWithReverse($expenseId) {
    $conn = conn();
    
    $conn->begin_transaction();
    
    try {
        $expense = queryOne("SELECT * FROM expenses WHERE id = ?", 'i', [$expenseId]);
        if (!$expense) {
            return ['success' => false, 'message' => 'খরচ পাওয়া যায়নি'];
        }
        
        $conn->query("DELETE FROM deposits WHERE transaction_no = 'EXP-" . $expenseId . "' AND deposit_type = 'expense'");
        $conn->query("DELETE FROM expenses WHERE id = " . $expenseId);
        
        $conn->commit();
        
        return ['success' => true];
        
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// ============================================
// HANDLE POST - ADD/EDIT/DELETE
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { 
        setFlash('danger','নিরাপত্তা যাচাই ব্যর্থ।'); 
        header('Location: expenses.php'); 
        exit; 
    }
    
    $pa = $_POST['action'] ?? '';

    // ADD
    if ($pa === 'add') {
        $title    = trim($_POST['title'] ?? '');
        $category = $_POST['category'] ?? 'other';
        $amount   = (float)$_POST['amount'];
        $date     = $_POST['expense_date'] ?? date('Y-m-d');
        $desc     = trim($_POST['description'] ?? '');
        $by       = $_SESSION['admin_id'];

        if (!$title || $amount <= 0) {
            setFlash('danger','শিরোনাম ও পরিমাণ আবশ্যক।');
        } else {
            $result = addExpenseWithDeduction($title, $category, $amount, $date, $desc, $by);
            if ($result['success']) {
                logActivity('expense_add', "খরচ: $title ৳$amount");
                setFlash('success', "খরচ যোগ হয়েছে। রসিদ: {$result['receipt_no']}");
            } else {
                setFlash('danger', 'খরচ যোগ ব্যর্থ: ' . $result['message']);
            }
        }
        header('Location: expenses.php'); 
        exit;
    }

    // EDIT
    if ($pa === 'edit') {
        $id       = (int)$_POST['expense_id'];
        $title    = trim($_POST['title'] ?? '');
        $category = $_POST['category'] ?? 'other';
        $amount   = (float)$_POST['amount'];
        $date     = $_POST['expense_date'] ?? date('Y-m-d');
        $desc     = trim($_POST['description'] ?? '');

        if (!$title || $amount <= 0) {
            setFlash('danger','শিরোনাম ও পরিমাণ আবশ্যক।');
        } else {
            $result = updateExpense($id, $title, $category, $amount, $date, $desc);
            if ($result['success']) {
                logActivity('expense_edit', "খরচ আপডেট: ID $id");
                setFlash('success', 'খরচ আপডেট করা হয়েছে।');
            } else {
                setFlash('danger', 'খরচ আপডেট ব্যর্থ: ' . $result['message']);
            }
        }
        header('Location: expenses.php'); 
        exit;
    }

    // DELETE (AJAX)
    if ($pa === 'delete') {
        $delId = (int)$_POST['expense_id'];
        $result = deleteExpenseWithReverse($delId);
        
        if ($result['success']) {
            echo json_encode(['success' => true, 'message' => 'খরচ মুছে ফেলা হয়েছে']);
        } else {
            echo json_encode(['success' => false, 'message' => $result['message']]);
        }
        exit;
    }
}

// ============================================
// GET EXPENSE FOR EDIT (AJAX)
// ============================================
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_expense' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $id = (int)$_GET['id'];
    $expense = queryOne("SELECT * FROM expenses WHERE id = ?", 'i', [$id]);
    
    if ($expense) {
        echo json_encode(['success' => true, 'data' => $expense]);
    } else {
        echo json_encode(['success' => false, 'message' => 'খরচ পাওয়া যায়নি']);
    }
    exit;
}

// ============================================
// GET DATA FOR LIST
// ============================================
$fromDate = $_GET['from'] ?? date('Y-01-01');
$toDate   = $_GET['to']   ?? date('Y-m-d');
$catF     = $_GET['cat']  ?? '';
$page     = max(1,(int)($_GET['page'] ?? 1));

$where='1=1'; 
$types=''; 
$params=[];

$where.=" AND e.expense_date BETWEEN ? AND ?"; 
$types.='ss'; 
$params=array_merge($params,[$fromDate,$toDate]);

if ($catF) { 
    $where.=" AND e.category=?"; 
    $types.='s'; 
    $params[]=$catF; 
}

$sql="SELECT e.*,u.name AS by_name FROM expenses e LEFT JOIN users u ON u.id=e.created_by WHERE $where ORDER BY e.expense_date DESC";
$totalRow = queryOne("SELECT COALESCE(SUM(amount),0) AS t FROM expenses e WHERE $where",$types,$params);
$grandTotal = (float)($totalRow['t'] ?? 0);

// Total deposit balance calculation
$totalDeposit = queryOne("SELECT COALESCE(SUM(amount),0) AS t FROM deposits WHERE amount > 0 AND deposit_type != 'expense'")['t'] ?? 0;
$totalExpense = queryOne("SELECT COALESCE(SUM(ABS(amount)),0) AS t FROM deposits WHERE deposit_type = 'expense' AND amount < 0")['t'] ?? 0;
$currentBalance = $totalDeposit - $totalExpense;

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
     BALANCE INFO BANNER
     ============================================ -->
<div class="container-fluid px-4 mt-3">
    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body py-2">
                    <small>মোট জমা (সব সদস্য)</small>
                    <h5 class="mb-0"><?= formatCurrency($totalDeposit) ?></h5>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card bg-danger text-white">
                <div class="card-body py-2">
                    <small>মোট খরচ</small>
                    <h5 class="mb-0"><?= formatCurrency($totalExpense) ?></h5>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body py-2">
                    <small>বর্তমান ব্যালেন্স</small>
                    <h5 class="mb-0"><?= formatCurrency($currentBalance) ?></h5>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
     MAIN CONTENT
     ============================================ -->
<div class="container-fluid px-4">
<div class="row g-3">
  <!-- LEFT: ADD EXPENSE FORM -->
  <div class="col-12 col-lg-4">
    <div class="card">
      <div class="card-header"><span class="card-title"><i data-feather="plus-circle" style="width:15px;height:15px"></i> নতুন খরচ</span></div>
      <div class="card-body">
        <form method="POST" id="addExpenseForm">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="add">
          
          <div class="mb-3">
            <label class="form-label">বিবরণ <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control" required placeholder="খরচের বিবরণ">
          </div>
          
          <div class="mb-3">
            <label class="form-label">বিভাগ</label>
            <select name="category" class="form-select" id="addCategory">
              <?= getCategoryOptions() ?>
            </select>
          </div>
          
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label">পরিমাণ (৳) <span class="text-danger">*</span></label>
              <input type="number" name="amount" class="form-control" step="0.01" min="1" required>
            </div>
            <div class="col-6">
              <label class="form-label">তারিখ</label>
              <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
          </div>
          
          <div class="mb-3">
            <label class="form-label">অতিরিক্ত তথ্য</label>
            <textarea name="description" class="form-control" rows="2"></textarea>
          </div>
          
          <div class="alert alert-warning small">
            <i data-feather="info" style="width:14px;height:14px"></i>
            খরচ যোগ করলে <strong>মোট জমা</strong> থেকে স্বয়ংক্রিয়ভাবে কেটে নেওয়া হবে।
          </div>
          
          <button class="btn btn-danger w-100">খরচ যোগ করুন</button>
        </form>
      </div>
    </div>
  </div>

  <!-- RIGHT: EXPENSE LIST -->
  <div class="col-12 col-lg-8">
    <div class="card">
      <div class="card-header">
        <span class="card-title"><i data-feather="shopping-bag" style="width:16px;height:16px"></i> খরচের তালিকা</span>
        <span class="badge bg-danger"><?= formatCurrency($grandTotal) ?></span>
      </div>
      <div class="card-body border-bottom pb-3">
        <form method="GET" class="row g-2" id="filterForm">
          <div class="col-6 col-md-3"><input type="date" name="from" class="form-control" value="<?= $fromDate ?>"></div>
          <div class="col-6 col-md-3"><input type="date" name="to" class="form-control" value="<?= $toDate ?>"></div>
          <div class="col-6 col-md-3">
            <select name="cat" class="form-select" id="filterCategory">
              <option value="">সব বিভাগ</option>
              <?php foreach(['office'=>'অফিস','salary'=>'বেতন/ভাতা','maintenance'=>'রক্ষণাবেক্ষণ','event'=>'অনুষ্ঠান','other'=>'অন্যান্য'] as $k=>$v): ?>
              <option value="<?= $k ?>" <?= $catF===$k?'selected':'' ?>><?= $v ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6 col-md-3"><button class="btn btn-primary w-100">ফিল্টার</button></div>
        </form>
      </div>
      <div class="table-responsive">
        <?php $paged = paginate($sql,$types,$params,$page); ?>
        <table class="table table-hover" id="expenseTable">
          <thead>
            <tr>
              <th>বিবরণ</th>
              <th>বিভাগ</th>
              <th>তারিখ</th>
              <th>পরিমাণ</th>
              <th>রসিদ</th>
              <th class="text-center">কার্যক্রম</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($paged['data'] as $e): ?>
            <tr id="expense-row-<?= $e['id'] ?>">
              <td>
                <div style="font-size:14px;font-weight:500"><?= clean($e['title']) ?></div>
                <div style="font-size:11px;color:#888"><?= clean($e['description'] ?? '') ?></div>
              </td>
              <td><span class="badge bg-light text-dark"><?= getCategoryLabel($e['category']) ?></span></td>
              <td style="font-size:13px"><?= formatDate($e['expense_date']) ?></td>
              <td class="amount-negative fw-bold"><?= formatCurrency((float)$e['amount']) ?></td>
              <td>
                <code style="font-size:11px;">EXP-<?= str_pad($e['id'], 5, '0', STR_PAD_LEFT) ?></code>
              </td>
              <td class="text-center">
                <div class="btn-group btn-group-sm" role="group">
                  <!-- Print Button -->
                  <button class="btn btn-outline-info" 
                          onclick="printReceipt(<?= $e['id'] ?>, '<?= addslashes($e['title']) ?>', '<?= $e['amount'] ?>', '<?= $e['expense_date'] ?>', '<?= addslashes(getCategoryLabel($e['category'])) ?>', '<?= addslashes($e['description'] ?? '') ?>')" 
                          title="প্রিন্ট রসিদ">
                    <i data-feather="printer" style="width:13px;height:13px"></i>
                  </button>
                  
                  <!-- Edit Button -->
                  <button class="btn btn-outline-primary" 
                          onclick="openEditModal(<?= $e['id'] ?>)" 
                          title="সম্পাদনা">
                    <i data-feather="edit-2" style="width:13px;height:13px"></i>
                  </button>
                  
                  <!-- Delete Button -->
                  <button class="btn btn-outline-danger" 
                          onclick="openDeleteModal(<?= $e['id'] ?>, '<?= addslashes($e['title']) ?>', <?= $e['amount'] ?>)" 
                          title="ডিলিট">
                    <i data-feather="trash-2" style="width:13px;height:13px"></i>
                  </button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($paged['data'])): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">কোনো খরচ নেই</td></tr>
            <?php endif; ?>
          </tbody>
          <tfoot>
            <tr class="table-active">
              <td colspan="3"><strong>সর্বমোট</strong></td>
              <td><strong><?= formatCurrency($grandTotal) ?></strong></td>
              <td colspan="2"></td>
            </tr>
          </tfoot>
        </table>
      </div>
      <div class="card-body pt-2">
        <?= renderPagination($paged,"expenses.php?from=$fromDate&to=$toDate&cat=$catF") ?>
      </div>
    </div>
  </div>
</div>
</div>

<!-- ============================================
     EDIT MODAL
     ============================================ -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i data-feather="edit-2" style="width:18px;height:18px"></i>
                    খরচ সম্পাদনা করুন
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editForm">
                <div class="modal-body">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="expense_id" id="edit_expense_id">
                    
                    <div class="mb-3">
                        <label class="form-label">বিবরণ <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="edit_title" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">বিভাগ</label>
                        <select name="category" id="edit_category" class="form-select">
                            <?= getCategoryOptions() ?>
                        </select>
                    </div>
                    
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">পরিমাণ (৳) <span class="text-danger">*</span></label>
                            <input type="number" name="amount" id="edit_amount" class="form-control" step="0.01" min="1" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">তারিখ</label>
                            <input type="date" name="expense_date" id="edit_date" class="form-control">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">অতিরিক্ত তথ্য</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
                    <button type="submit" class="btn btn-primary">
                        <i data-feather="save" style="width:14px;height:14px"></i> আপডেট করুন
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================
     DELETE CONFIRMATION MODAL
     ============================================ -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: #dc3545; color: white;">
                <h5 class="modal-title">
                    <i data-feather="alert-triangle" style="width:18px;height:18px"></i>
                    নিশ্চিত করুন
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <div style="font-size: 48px; margin-bottom: 15px;">
                    <i data-feather="trash-2" style="width:48px;height:48px;color:#dc3545;"></i>
                </div>
                <h5 class="mb-2">আপনি কি নিশ্চিত?</h5>
                <p class="text-muted" id="deleteMessage">
                    "<span id="deleteTitle"></span>" খরচটি মুছে ফেলতে চান?
                </p>
                <p class="text-danger small">
                    <i data-feather="info" style="width:14px;height:14px"></i>
                    খরচ মুছে ফেললে <strong>ব্যালেন্স স্বয়ংক্রিয়ভাবে পুনরুদ্ধার</strong> হবে।
                </p>
                <div class="bg-light p-2 rounded mb-2">
                    <strong>পরিমাণ:</strong> <span id="deleteAmount" class="text-danger"></span>
                </div>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i data-feather="x" style="width:14px;height:14px"></i> বাতিল
                </button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                    <i data-feather="trash-2" style="width:14px;height:14px"></i> ডিলিট করুন
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
     PRINT RECEIPT MODAL
     ============================================ -->
<div class="modal fade" id="receiptModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">খরচ রসিদ</h5>
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
     TOAST NOTIFICATION
     ============================================ -->
<div style="position: fixed; top: 80px; right: 20px; z-index: 9999; max-width: 350px;">
    <div id="toastNotification" class="alert" style="display: none; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
        <span id="toastMessage"></span>
        <button type="button" class="btn-close" onclick="closeToast()"></button>
    </div>
</div>

<style>
/* All styles remain the same */
.btn-group .btn {
    padding: 0.2rem 0.5rem;
    font-size: 0.75rem;
}
.btn-group .btn i {
    width: 14px;
    height: 14px;
}

#deleteModal .modal-content {
    animation: shake 0.5s ease-in-out;
}
@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-10px); }
    75% { transform: translateX(10px); }
}

#toastNotification {
    border-radius: 10px;
    padding: 12px 20px;
    animation: slideIn 0.5s ease;
}
#toastNotification.success { background: #d4edda; border-color: #28a745; color: #155724; }
#toastNotification.danger { background: #f8d7da; border-color: #dc3545; color: #721c24; }

@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

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
.receipt-container .receipt-header h5 {
    margin: 0;
    font-weight: bold;
}
.receipt-container .receipt-body {
    padding: 5px 0;
}
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

.row-removing {
    animation: fadeOut 0.5s ease;
}
@keyframes fadeOut {
    from { opacity: 1; transform: scale(1); }
    to { opacity: 0; transform: scale(0.8); }
}
</style>

<script>
// ============================================
// OPEN EDIT MODAL
// ============================================
function openEditModal(id) {
    fetch(`expenses.php?ajax=get_expense&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const d = data.data;
                document.getElementById('edit_expense_id').value = d.id;
                document.getElementById('edit_title').value = d.title;
                document.getElementById('edit_category').value = d.category;
                document.getElementById('edit_amount').value = d.amount;
                document.getElementById('edit_date').value = d.expense_date;
                document.getElementById('edit_description').value = d.description || '';
                
                new bootstrap.Modal(document.getElementById('editModal')).show();
            } else {
                showToast(data.message, 'danger');
            }
        })
        .catch(error => {
            showToast('ডেটা লোড করতে ব্যর্থ হয়েছে', 'danger');
        });
}

// ============================================
// OPEN DELETE MODAL
// ============================================
let deleteId = 0;

function openDeleteModal(id, title, amount) {
    deleteId = id;
    document.getElementById('deleteTitle').textContent = title;
    document.getElementById('deleteAmount').textContent = formatCurrency(parseFloat(amount));
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// Confirm Delete - With Row Removal
document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
    if (deleteId === 0) return;
    
    const csrf = document.querySelector('input[name="csrf_token"]').value;
    const row = document.getElementById('expense-row-' + deleteId);
    
    if (row) {
        row.classList.add('row-removing');
    }
    
    fetch('expenses.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=delete&expense_id=${deleteId}&csrf_token=${csrf}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
            showToast(data.message, 'success');
            
            if (row) {
                setTimeout(() => {
                    row.remove();
                    updateTotals();
                }, 500);
            } else {
                setTimeout(() => location.reload(), 1000);
            }
        } else {
            showToast(data.message, 'danger');
            if (row) {
                row.classList.remove('row-removing');
            }
        }
    })
    .catch(error => {
        showToast('ডিলিট করতে ব্যর্থ হয়েছে', 'danger');
        if (row) {
            row.classList.remove('row-removing');
        }
    });
});

// ============================================
// UPDATE TOTALS AFTER DELETE
// ============================================
function updateTotals() {
    const rows = document.querySelectorAll('#expenseTable tbody tr');
    let total = 0;
    let count = 0;
    
    rows.forEach(row => {
        const amountCell = row.querySelector('td:nth-child(4)');
        if (amountCell) {
            const amountText = amountCell.textContent.replace(/[৳,]/g, '').trim();
            const amount = parseFloat(amountText);
            if (!isNaN(amount)) {
                total += amount;
                count++;
            }
        }
    });
    
    const footerTotal = document.querySelector('#expenseTable tfoot td:nth-child(4) strong');
    if (footerTotal) {
        footerTotal.textContent = formatCurrency(total);
    }
    
    const headerBadge = document.querySelector('.card-header .badge');
    if (headerBadge) {
        headerBadge.textContent = formatCurrency(total);
    }
    
    if (count === 0) {
        const tbody = document.querySelector('#expenseTable tbody');
        if (tbody) {
            tbody.innerHTML = `
                <tr><td colspan="6" class="text-center text-muted py-4">কোনো খরচ নেই</td></tr>
            `;
        }
    }
}

// ============================================
// PRINT RECEIPT
// ============================================
function printReceipt(id, title, amount, date, category, description) {
    const receiptNo = 'EXP-' + date.replace(/-/g, '') + '-' + String(id).padStart(5, '0');
    
    const receiptHTML = `
        <div class="receipt-container">
            <div class="receipt-header">
                <h5>খরচ রসিদ</h5>
                <small>রসিদ নং: ${receiptNo}</small>
            </div>
            <div class="receipt-body">
                <div class="receipt-row">
                    <span><strong>বিবরণ:</strong></span>
                    <span>${title}</span>
                </div>
                <div class="receipt-row">
                    <span><strong>পরিমাণ:</strong></span>
                    <span>${formatCurrency(parseFloat(amount))}</span>
                </div>
                <div class="receipt-row">
                    <span><strong>বিভাগ:</strong></span>
                    <span>${category}</span>
                </div>
                ${description ? `
                <div class="receipt-row">
                    <span><strong>বিবরণ:</strong></span>
                    <span>${description}</span>
                </div>
                ` : ''}
                <div class="receipt-row">
                    <span><strong>তারিখ:</strong></span>
                    <span>${formatDate(date)}</span>
                </div>
            </div>
            <div class="receipt-footer">
                <small>ধন্যবাদ</small>
            </div>
        </div>
    `;
    
    document.getElementById('receiptContent').innerHTML = receiptHTML;
    new bootstrap.Modal(document.getElementById('receiptModal')).show();
}

function printReceiptContent() {
    var content = document.getElementById('receiptContent').innerHTML;
    var win = window.open('', '', 'width=400,height=500');
    win.document.write(`
        <html>
            <head>
                <title>খরচ রসিদ</title>
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
            <body>
                ${content}
                <script>
                    window.onload = function() { window.print(); window.close(); }
                <\/script>
            </body>
        </html>
    `);
    win.document.close();
}

// ============================================
// TOAST NOTIFICATION
// ============================================
function showToast(message, type = 'success') {
    const toast = document.getElementById('toastNotification');
    const msg = document.getElementById('toastMessage');
    toast.className = `alert ${type}`;
    msg.textContent = message;
    toast.style.display = 'block';
    setTimeout(() => { toast.style.display = 'none'; }, 4000);
}

function closeToast() {
    document.getElementById('toastNotification').style.display = 'none';
}

// ============================================
// FORMAT CURRENCY
// ============================================
function formatCurrency(amount) {
    return '৳ ' + parseFloat(amount).toFixed(2);
}

// ============================================
// FORMAT DATE
// ============================================
function formatDate(dateStr) {
    if (!dateStr) return '-';
    try {
        const d = new Date(dateStr);
        return d.toLocaleDateString('bn-BD', { day: '2-digit', month: 'short', year: 'numeric' });
    } catch(e) {
        return dateStr;
    }
}

// ============================================
// AUTO SUBMIT ON FILTER CHANGE (FIXED)
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    // Auto submit ONLY for filter form (not add form)
    const filterForm = document.getElementById('filterForm');
    if (filterForm) {
        // Select boxes inside filter form
        filterForm.querySelectorAll('.form-select').forEach(function(select) {
            select.addEventListener('change', function() {
                filterForm.submit();
            });
        });
        
        // Date inputs inside filter form
        filterForm.querySelectorAll('input[type="date"]').forEach(function(input) {
            input.addEventListener('change', function() {
                filterForm.submit();
            });
        });
    }
    
    // Add form should NOT auto-submit
    const addForm = document.getElementById('addExpenseForm');
    if (addForm) {
        addForm.querySelectorAll('.form-select, input[type="date"]').forEach(function(element) {
            element.addEventListener('change', function(e) {
                e.stopPropagation(); // Prevent any accidental submit
            });
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>