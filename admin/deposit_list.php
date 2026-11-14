<?php
// =====================================================
// deposit_list.php - Deposit List (WITH PAGINATION & TOTAL FIXED)
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

$pageTitle = 'সঞ্চয় জমার তালিকা';
$error = '';
$success = '';
$memberId = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;
$viewAll = isset($_GET['view_all']) ? true : false;
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

// Filter variables
$monthFilter = isset($_GET['month']) ? (int)$_GET['month'] : '';
$yearFilter = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$typeFilter = isset($_GET['type']) ? $_GET['type'] : '';
$methodFilter = isset($_GET['method']) ? $_GET['method'] : '';
$searchDeposit = isset($_GET['search_deposit']) ? trim($_GET['search_deposit']) : '';

// ============================================
// FIX: Get current page for pagination
// ============================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$perPage = 20; // Records per page

$conn = conn();

// ============================================
// CALCULATE NET BALANCE
// ============================================
function getTotalBalance($conn) {
    $totalDeposits = queryOne("SELECT COALESCE(SUM(amount),0) as total FROM deposits WHERE deposit_type IN ('regular', 'special', 'share', 'fd')")['total'] ?? 0;
    
    $totalFines = queryOne("
        SELECT COALESCE(SUM(amount),0) as total
        FROM deposits
        WHERE deposit_type = 'fine'
    ")['total'] ?? 0;
    
    $total = ($totalDeposits + $totalFines);
    
    return [
        'total_deposits' => $totalDeposits,
        'total_fines' => $totalFines,
        'net_balance' => $total
    ];
}

// Get balance data
$balanceData = getTotalBalance($conn);

// ============================================
// BUILD DEPOSIT LIST QUERY WITH FILTERS
// ============================================
$where = '1=1';
$params = [];
$types = '';

if (!empty($searchDeposit)) {
    $like = "%$searchDeposit%";
    $where .= " AND (m.name LIKE ? OR m.member_id LIKE ? OR d.receipt_no LIKE ? OR d.transaction_no LIKE ?)";
    $types .= 'ssss';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if (!empty($monthFilter)) {
    $where .= " AND MONTH(d.deposit_date) = ?";
    $types .= 'i';
    $params[] = $monthFilter;
}

if (!empty($yearFilter)) {
    $where .= " AND YEAR(d.deposit_date) = ?";
    $types .= 'i';
    $params[] = $yearFilter;
}

if (!empty($typeFilter)) {
    $where .= " AND d.deposit_type = ?";
    $types .= 's';
    $params[] = $typeFilter;
}

if (!empty($methodFilter)) {
    $where .= " AND d.payment_method = ?";
    $types .= 's';
    $params[] = $methodFilter;
}

// ============================================
// Get total count AND total amount
// ============================================
$countSql = "SELECT 
                COUNT(*) as total, 
                COALESCE(SUM(d.amount), 0) as total_amount 
             FROM deposits d 
             JOIN members m ON m.id = d.member_id 
             WHERE $where";
$countResult = queryOne($countSql, $types, $params);
$totalRecords = $countResult['total'] ?? 0;
$grandTotalAmount = $countResult['total_amount'] ?? 0;

// Calculate pagination
$totalPages = ceil($totalRecords / $perPage);
if ($page > $totalPages && $totalPages > 0) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

// ============================================
// Build main query
// ============================================
$sql = "SELECT d.*, m.name as member_name, m.member_id as member_code, m.phone as member_phone, m.photo as member_photo
        FROM deposits d
        JOIN members m ON m.id = d.member_id
        WHERE $where
        ORDER BY d.deposit_date DESC, d.id DESC
        LIMIT ? OFFSET ?";

// Add limit and offset to params
$types .= 'ii';
$params[] = $perPage;
$params[] = $offset;

// Execute query
$deposits = [];
$fineIds = [];
try {
    $stmt = $conn->prepare($sql);
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $deposits[] = $row;
        
        // Extract fine ID from note if it's a fine deposit
        if ($row['deposit_type'] == 'fine' && !empty($row['note'])) {
            // Look for "Fine payment for fine ID: 82" pattern
            if (preg_match('/fine ID:\s*(\d+)/i', $row['note'], $matches)) {
                $fineIds[] = (int)$matches[1];
            }
        }
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Query error: " . $e->getMessage());
    $deposits = [];
}

// ============================================
// Fetch fine due_dates from fines table using fine IDs
// ============================================
$fineDueDates = [];
if (!empty($fineIds)) {
    try {
        $ids = implode(',', array_fill(0, count($fineIds), '?'));
        $fineSql = "SELECT id, due_date, member_id, month_year FROM fines WHERE id IN ($ids)";
        $stmt = $conn->prepare($fineSql);
        $stmt->bind_param(str_repeat('i', count($fineIds)), ...$fineIds);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $fineDueDates[$row['id']] = [
                'due_date' => $row['due_date'],
                'member_id' => $row['member_id'],
                'month_year' => $row['month_year']
            ];
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error fetching fine due dates: " . $e->getMessage());
    }
}

// ============================================
// Calculate current page total
// ============================================
$currentPageTotal = array_sum(array_column($deposits, 'amount'));

// ============================================
// HANDLE DELETE DEPOSIT
// ============================================
if (isset($_GET['delete']) && $_GET['delete'] > 0) {
    $depositId = (int)$_GET['delete'];
    if (!verifyCSRF($_GET['csrf_token'] ?? '')) {
        setFlash('danger', 'নিরাপত্তা যাচাই ব্যর্থ।');
    } else {
        try {
            $deposit = queryOne("SELECT member_id, amount, receipt_no FROM deposits WHERE id = ?", 'i', [$depositId]);
            if ($deposit) {
                $conn->query("DELETE FROM deposits WHERE id = $depositId");
                setFlash('success', "সঞ্চয় জমা ডিলিট করা হয়েছে। (রসিদ: {$deposit['receipt_no']})");
                logActivity('deposit_delete', "সঞ্চয় জমা ডিলিট: {$deposit['receipt_no']} - ৳{$deposit['amount']}");
            } else {
                setFlash('danger', 'জমা পাওয়া যায়নি।');
            }
        } catch (Exception $e) {
            setFlash('danger', 'ডিলিট করতে ব্যর্থ হয়েছে: ' . $e->getMessage());
        }
    }
    header('Location: deposit_list.php' . buildQueryString(['page' => $page]));
    exit;
}

// ============================================
// HANDLE EDIT DEPOSIT
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_deposit') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'নিরাপত্তা যাচাই ব্যর্থ।');
    } else {
        $depositId = (int)$_POST['deposit_id'];
        $amount = (float)$_POST['amount'];
        $deposit_date = $_POST['deposit_date'];
        $deposit_type = $_POST['deposit_type'];
        $payment_method = $_POST['payment_method'];
        $transaction_no = trim($_POST['transaction_no'] ?? '');
        $note = trim($_POST['note'] ?? '');
        
        if ($amount <= 0) {
            setFlash('danger', 'সঠিক পরিমাণ দিন।');
        } else {
            try {
                $stmt = $conn->prepare("
                    UPDATE deposits SET 
                        amount = ?,
                        deposit_date = ?,
                        deposit_type = ?,
                        payment_method = ?,
                        transaction_no = ?,
                        note = ?
                    WHERE id = ?
                ");
                $stmt->bind_param('dsssssi', $amount, $deposit_date, $deposit_type, $payment_method, $transaction_no, $note, $depositId);
                $stmt->execute();
                
                setFlash('success', 'সঞ্চয় জমা আপডেট করা হয়েছে।');
                logActivity('deposit_edit', "সঞ্চয় জমা আপডেট: ID $depositId");
                
            } catch (Exception $e) {
                setFlash('danger', 'আপডেট করতে ব্যর্থ হয়েছে: ' . $e->getMessage());
            }
        }
    }
    header('Location: deposit_list.php' . buildQueryString(['page' => $page]));
    exit;
}

// Get edit deposit data
$editDeposit = null;
if (isset($_GET['edit']) && $_GET['edit'] > 0) {
    $editId = (int)$_GET['edit'];
    $editDeposit = queryOne("SELECT * FROM deposits WHERE id = ?", 'i', [$editId]);
}

// Helper function to build query string
function buildQueryString($extra = []) {
    $params = $_GET;
    unset($params['delete']);
    unset($params['edit']);
    unset($params['csrf_token']);
    
    foreach ($extra as $key => $value) {
        $params[$key] = $value;
    }
    
    if (empty($params)) {
        return '';
    }
    
    return '?' . http_build_query($params);
}

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

<div class="container-fluid px-4">

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h4 class="mb-0">
            <i data-feather="list" style="width:20px;height:20px"></i> 
            সঞ্চয় জমার তালিকা
        </h4>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge bg-success" style="background:#198754; color:#fff; font-size: 15px; padding: 8px 16px;">
                <i data-feather="dollar-sign" style="width:16px;height:16px;"></i>
                মোট সঞ্চয় জমা: <?= formatCurrency($balanceData['net_balance']) ?>
            </span>
            <button class="btn btn-success btn-sm" onclick="exportExcel()">
                <i data-feather="file-text" style="width:14px;height:14px"></i> Excel
            </button>
            <button class="btn btn-danger btn-sm" onclick="exportPDF()">
                <i data-feather="file" style="width:14px;height:14px"></i> PDF
            </button>
            <button class="btn btn-info btn-sm text-white" onclick="window.print()">
                <i data-feather="printer" style="width:14px;height:14px"></i> প্রিন্ট
            </button>
            <a href="deposits.php?action=add" class="btn btn-primary btn-sm">
                <i data-feather="plus-circle" style="width:14px;height:14px"></i> নতুন জমা
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2" id="filterForm">
                <div class="col-12 col-md-3">
                    <div class="search-box">
                        <i data-feather="search" class="search-icon"></i>
                        <input type="text" name="search_deposit" class="form-control form-control-sm" 
                               placeholder="নাম, আইডি, রসিদ, লেনদেন..." value="<?= clean($searchDeposit) ?>">
                    </div>
                </div>
                <div class="col-4 col-md-2">
                    <select name="month" class="form-select form-select-sm">
                        <option value="">মাস</option>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $monthFilter == $m ? 'selected' : '' ?>>
                            <?= date('F', mktime(0,0,0,$m,1)) ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-4 col-md-2">
                    <select name="year" class="form-select form-select-sm">
                        <option value="">সাল</option>
                        <?php for ($y = date('Y'); $y >= date('Y')-5; $y--): ?>
                        <option value="<?= $y ?>" <?= $yearFilter == $y ? 'selected' : '' ?>>
                            <?= $y ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-4 col-md-2">
                    <select name="type" class="form-select form-select-sm">
                        <option value="">ধরন</option>
                        <option value="regular" <?= $typeFilter === 'regular' ? 'selected' : '' ?>>নিয়মিত</option>
                        <option value="special" <?= $typeFilter === 'special' ? 'selected' : '' ?>>বিশেষ</option>
                        <option value="share" <?= $typeFilter === 'share' ? 'selected' : '' ?>>শেয়ার</option>
                        <option value="fd" <?= $typeFilter === 'fd' ? 'selected' : '' ?>>এফডি</option>
                        <option value="fine" <?= $typeFilter === 'fine' ? 'selected' : '' ?>>ফাইন</option>
                        <option value="withdrawal" <?= $typeFilter === 'withdrawal' ? 'selected' : '' ?>>উত্তোলন</option>
                        <option value="expense" <?= $typeFilter === 'expense' ? 'selected' : '' ?>>খরচ</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select name="method" class="form-select form-select-sm">
                        <option value="">পদ্ধতি</option>
                        <option value="cash" <?= $methodFilter === 'cash' ? 'selected' : '' ?>>নগদ</option>
                        <option value="bank" <?= $methodFilter === 'bank' ? 'selected' : '' ?>>ব্যাংক</option>
                        <option value="mobile" <?= $methodFilter === 'mobile' ? 'selected' : '' ?>>মোবাইল</option>
                        <option value="check" <?= $methodFilter === 'check' ? 'selected' : '' ?>>চেক</option>
                        <option value="online" <?= $methodFilter === 'online' ? 'selected' : '' ?>>অনলাইন</option>
                    </select>
                </div>
                <div class="col-6 col-md-1">
                    <button class="btn btn-primary btn-sm w-100" type="submit">
                        <i data-feather="search" style="width:14px;height:14px"></i>
                    </button>
                </div>
                <input type="hidden" name="page" value="1">
            </form>
        </div>
    </div>

    <!-- Deposit Table -->
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i data-feather="book-open" style="width:16px;height:16px"></i> জমার তালিকা</span>
            <span>
                <span class="badge bg-secondary"><?= $totalRecords ?> টি</span>
                <span class="badge bg-success">মোট: <?= formatCurrency($grandTotalAmount) ?></span>
                <?php if ($totalPages > 1): ?>
                <span class="badge bg-info">পৃষ্ঠা <?= $page ?> / <?= $totalPages ?></span>
                <?php endif; ?>
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover" id="depositTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>রসিদ নং</th>
                        <th>সদস্য</th>
                        <th>পরিমাণ</th>
                        <th>তারিখ</th>
                        <th>জমার মাস</th>
                        <th>ধরন</th>
                        <th>পদ্ধতি</th>
                        <th>লেনদেন নং</th>
                        <th>মন্তব্য</th>
                        <th>সংগ্রহকারী</th>
                        <th class="text-center">কার্যক্রম</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $counter = $offset + 1;
                    ?>
                    <?php if (empty($deposits)): ?>
                    <tr>
                        <td colspan="12" class="text-center text-muted py-4">
                            <i data-feather="inbox" style="width:32px;height:32px;display:block;margin:0 auto 10px"></i>
                            কোনো জমা পাওয়া যায়নি
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($deposits as $d): 
                        // Check if member has photo
                        $photoFile = !empty($d['member_photo']) ? $d['member_photo'] : '';
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
                        
                        // Determine badge color based on type
                        $badgeClass = 'bg-secondary';
                        $typeLabels = [
                            'regular' => 'নিয়মিত',
                            'special' => 'বিশেষ',
                            'share' => 'শেয়ার',
                            'fd' => 'এফডি',
                            'fine' => 'ফাইন',
                            'withdrawal' => 'উত্তোলন',
                            'expense' => 'খরচ'
                        ];
                        
                        if ($d['deposit_type'] == 'regular') $badgeClass = 'bg-success';
                        elseif ($d['deposit_type'] == 'special') $badgeClass = 'bg-primary';
                        elseif ($d['deposit_type'] == 'share') $badgeClass = 'bg-warning text-dark';
                        elseif ($d['deposit_type'] == 'fd') $badgeClass = 'bg-info text-dark';
                        elseif ($d['deposit_type'] == 'fine') $badgeClass = 'bg-danger';
                        elseif ($d['deposit_type'] == 'withdrawal') $badgeClass = 'bg-dark';
                        elseif ($d['deposit_type'] == 'expense') $badgeClass = 'bg-secondary';
                        
                        $typeLabel = $typeLabels[$d['deposit_type']] ?? ucfirst($d['deposit_type']);
                        
                        // ============================================
                        // Month / due_date column logic
                        // ============================================
                        $monthDisplay = date('M Y', strtotime($d['deposit_date']));
                        $fineDueDateDisplay = '';
                        $fineMonthYear = '';
                        
                        // For fine deposits, extract fine ID from note and get due_date
                        if ($d['deposit_type'] == 'fine' && !empty($d['note'])) {
                            if (preg_match('/fine ID:\s*(\d+)/i', $d['note'], $matches)) {
                                $fineId = (int)$matches[1];
                                if (isset($fineDueDates[$fineId])) {
                                    if (!empty($fineDueDates[$fineId]['due_date'])) {
                                        $fineDueDateDisplay = date('d M Y', strtotime($fineDueDates[$fineId]['due_date']));
                                    }
                                    if (!empty($fineDueDates[$fineId]['month_year'])) {
                                        $fineMonthYear = $fineDueDates[$fineId]['month_year'];
                                    }
                                }
                            }
                        }
                    ?>
                    <tr class="clickable-row" data-member-id="<?= $d['member_id'] ?>" style="cursor:pointer;">
                        <td><?= $counter++ ?></td>
                        <td><code><?= clean($d['receipt_no']) ?></code></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <?php if ($hasPhoto): ?>
                                    <img src="<?= htmlspecialchars($photoUrl) ?>" 
                                         alt="<?= clean($d['member_name']) ?>" 
                                         class="member-avatar-sm"
                                         onerror="this.style.display='none'; this.parentElement.querySelector('.avatar-placeholder').style.display='flex';">
                                    <div class="avatar-placeholder" style="display:none;">
                                        <?= strtoupper(substr($d['member_name'], 0, 1)) ?>
                                    </div>
                                <?php else: ?>
                                    <div class="avatar-placeholder">
                                        <?= strtoupper(substr($d['member_name'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <div style="font-weight:500;font-size:12px;"><?= clean($d['member_name']) ?></div>
                                    <small class="text-muted" style="font-size:10px;"><?= clean($d['member_code']) ?></small>
                                </div>
                            </div>
                        </td>
                        <td class="<?= in_array($d['deposit_type'], ['withdrawal', 'expense']) ? 'amount-negative' : 'amount-positive' ?>">
                            <?= formatCurrency($d['amount']) ?>
                        </td>
                        <td style="font-size:12px;"><?= formatDate($d['deposit_date']) ?></td>
                        <td style="font-size:11px;">
                            <?php if ($d['deposit_type'] == 'fine' && !empty($fineDueDateDisplay)): ?>
                                <div>
                                    <div><strong> <?= $fineDueDateDisplay ?></strong></div>
                                   
                            <?php elseif ($d['deposit_type'] == 'fine'): ?>
                                <div>
                                    <div><strong>মাস:</strong> <?= $monthDisplay ?></div>
                                    <div style="color:#999;font-size:10px;">(no due date)</div>
                                </div>
                            <?php else: ?>
                                <?= $monthDisplay ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $badgeClass ?>" style="font-size:10px;">
                                <?= $typeLabel ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-secondary" style="font-size:10px;">
                                <?php 
                                $methodLabels = [
                                    'cash' => 'নগদ',
                                    'bank' => 'ব্যাংক',
                                    'mobile' => 'মোবাইল',
                                    'check' => 'চেক',
                                    'online' => 'অনলাইন'
                                ];
                                echo $methodLabels[$d['payment_method']] ?? ucfirst($d['payment_method']);
                                ?>
                            </span>
                        </td>
                        <td style="font-size:11px;"><?= clean($d['transaction_no'] ?? '-') ?></td>
                        <td style="font-size:11px;max-width:80px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= clean($d['note'] ?? '') ?>">
                            <?= clean($d['note'] ?? '-') ?>
                        </td>
                        <td style="font-size:11px;">
                            <?php 
                            if (!empty($d['collected_by'])) {
                                if (isset($_SESSION['admin_name']) && $_SESSION['admin_id'] == $d['collected_by']) {
                                    echo clean($_SESSION['admin_name']);
                                } else {
                                    echo 'Admin #' . $d['collected_by'];
                                }
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm" role="group" onclick="event.stopPropagation();">
                                <button class="btn btn-outline-info" 
                                        onclick="printReceipt('<?= $d['receipt_no'] ?>', '<?= addslashes($d['member_name']) ?>', '<?= $d['amount'] ?>', '<?= $d['deposit_date'] ?>', '<?= $d['deposit_type'] ?>')" 
                                        title="প্রিন্ট রসিদ">
                                    <i data-feather="printer" style="width:12px;height:12px"></i>
                                </button>
                                <a href="member_profile.php?id=<?= $d['member_id'] ?>" 
                                   class="btn btn-outline-primary" 
                                   title="সদস্য প্রোফাইল"
                                   onclick="event.stopPropagation();">
                                    <i data-feather="eye" style="width:12px;height:12px"></i>
                                </a>
                                <a href="<?= buildQueryString(['edit' => $d['id'], 'page' => $page]) ?>" 
                                   class="btn btn-outline-warning" 
                                   title="সম্পাদনা"
                                   onclick="event.stopPropagation();">
                                    <i data-feather="edit-2" style="width:12px;height:12px"></i>
                                </a>
                                <a href="<?= buildQueryString(['delete' => $d['id'], 'csrf_token' => generateCSRF(), 'page' => $page]) ?>" 
                                   class="btn btn-outline-danger" 
                                   onclick="event.stopPropagation(); return confirm('আপনি কি নিশ্চিত? এই জমা ডিলিট হবে!')" 
                                   title="ডিলিট">
                                    <i data-feather="trash-2" style="width:12px;height:12px"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <?php if (!empty($deposits)): ?>
                    <tr class="table-active">
                        <td colspan="3"><strong>এই পৃষ্ঠার মোট</strong></td>
                        <td>
                            <strong><?= formatCurrency($currentPageTotal) ?></strong>
                        </td>
                        <td colspan="8"></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="table-primary">
                        <td colspan="3"><strong style="font-size:14px;">সর্বমোট (সব পৃষ্ঠা)</strong></td>
                        <td>
                            <strong style="font-size:16px;color:#1B4F72;"><?= formatCurrency($grandTotalAmount) ?></strong>
                        </td>
                        <td colspan="8"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="card-body pt-2">
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center mb-0 flex-wrap">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= buildQueryString(['page' => 1]) ?>">
                            <i data-feather="chevrons-left" style="width:14px;height:14px"></i>
                        </a>
                    </li>
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= buildQueryString(['page' => $page - 1]) ?>">
                            <i data-feather="chevron-left" style="width:14px;height:14px"></i>
                        </a>
                    </li>
                    
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    if ($startPage > 1) {
                        echo '<li class="page-item"><a class="page-link" href="' . buildQueryString(['page' => 1]) . '">1</a></li>';
                        if ($startPage > 2) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                    }
                    
                    for ($i = $startPage; $i <= $endPage; $i++) {
                        $active = $i == $page ? 'active' : '';
                        echo '<li class="page-item ' . $active . '">
                                <a class="page-link" href="' . buildQueryString(['page' => $i]) . '">' . $i . '</a>
                              </li>';
                    }
                    
                    if ($endPage < $totalPages) {
                        if ($endPage < $totalPages - 1) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        echo '<li class="page-item"><a class="page-link" href="' . buildQueryString(['page' => $totalPages]) . '">' . $totalPages . '</a></li>';
                    }
                    ?>
                    
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= buildQueryString(['page' => $page + 1]) ?>">
                            <i data-feather="chevron-right" style="width:14px;height:14px"></i>
                        </a>
                    </li>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= buildQueryString(['page' => $totalPages]) ?>">
                            <i data-feather="chevrons-right" style="width:14px;height:14px"></i>
                        </a>
                    </li>
                </ul>
            </nav>
            <div class="text-center text-muted mt-2" style="font-size:12px;">
                দেখানো হয়েছে <?= count($deposits) ?> টি (মোট <?= $totalRecords ?> টি) 
                | মোট পরিমাণ: <strong><?= formatCurrency($grandTotalAmount) ?></strong>
                <?php if ($totalPages > 1): ?>
                | পৃষ্ঠা <?= $page ?> / <?= $totalPages ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Deposit Modal -->
<?php if ($editDeposit): ?>
<div class="modal fade show" id="editDepositModal" tabindex="-1" style="display:block;background:rgba(0,0,0,0.5);">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i data-feather="edit-2" style="width:18px;height:18px"></i>
                    সঞ্চয় জমা সম্পাদনা
                </h5>
                <a href="<?= buildQueryString() ?>" class="btn-close"></a>
            </div>
            <form method="POST" action="<?= buildQueryString() ?>">
                <div class="modal-body">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="edit_deposit">
                    <input type="hidden" name="deposit_id" value="<?= $editDeposit['id'] ?>">

                    <div class="mb-3">
                        <label class="form-label">রসিদ নং</label>
                        <input type="text" class="form-control" value="<?= $editDeposit['receipt_no'] ?>" disabled>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">পরিমাণ (৳) <span class="text-danger">*</span></label>
                        <input type="number" name="amount" class="form-control" 
                               value="<?= $editDeposit['amount'] ?>" step="0.01" min="1" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">তারিখ <span class="text-danger">*</span></label>
                        <input type="date" name="deposit_date" class="form-control" 
                               value="<?= $editDeposit['deposit_date'] ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">জমার ধরন</label>
                        <select name="deposit_type" class="form-select">
                            <option value="regular" <?= $editDeposit['deposit_type'] == 'regular' ? 'selected' : '' ?>>নিয়মিত</option>
                            <option value="special" <?= $editDeposit['deposit_type'] == 'special' ? 'selected' : '' ?>>বিশেষ</option>
                            <option value="share" <?= $editDeposit['deposit_type'] == 'share' ? 'selected' : '' ?>>শেয়ার</option>
                            <option value="fd" <?= $editDeposit['deposit_type'] == 'fd' ? 'selected' : '' ?>>এফডি</option>
                            <option value="fine" <?= $editDeposit['deposit_type'] == 'fine' ? 'selected' : '' ?>>ফাইন</option>
                            <option value="withdrawal" <?= $editDeposit['deposit_type'] == 'withdrawal' ? 'selected' : '' ?>>উত্তোলন</option>
                            <option value="expense" <?= $editDeposit['deposit_type'] == 'expense' ? 'selected' : '' ?>>খরচ</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">পেমেন্ট পদ্ধতি</label>
                        <select name="payment_method" class="form-select">
                            <option value="cash" <?= $editDeposit['payment_method'] == 'cash' ? 'selected' : '' ?>>নগদ</option>
                            <option value="bank" <?= $editDeposit['payment_method'] == 'bank' ? 'selected' : '' ?>>ব্যাংক</option>
                            <option value="mobile" <?= $editDeposit['payment_method'] == 'mobile' ? 'selected' : '' ?>>মোবাইল</option>
                            <option value="check" <?= $editDeposit['payment_method'] == 'check' ? 'selected' : '' ?>>চেক</option>
                            <option value="online" <?= $editDeposit['payment_method'] == 'online' ? 'selected' : '' ?>>অনলাইন</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">লেনদেন নম্বর</label>
                        <input type="text" name="transaction_no" class="form-control" 
                               value="<?= clean($editDeposit['transaction_no'] ?? '') ?>" placeholder="লেনদেন নম্বর">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">মন্তব্য</label>
                        <textarea name="note" class="form-control" rows="2" placeholder="ঐচ্ছিক"><?= clean($editDeposit['note'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="<?= buildQueryString() ?>" class="btn btn-secondary">বাতিল</a>
                    <button type="submit" class="btn btn-primary">
                        <i data-feather="save" style="width:14px;height:14px"></i> আপডেট করুন
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Print Receipt Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">জমার রসিদ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="receiptContent"></div>
            <div class="modal-footer">
                <button class="btn btn-primary btn-sm" onclick="printReceiptContent()">
                    <i data-feather="printer" style="width:14px;height:14px"></i> প্রিন্ট
                </button>
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">বন্ধ</button>
            </div>
        </div>
    </div>
</div>

<style>
.amount-positive { color: #27AE60; font-weight: 600; }
.amount-negative { color: #E74C3C; font-weight: 600; }

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
    padding: 12px 16px;
}

.table {
    font-size: 12px;
    margin-bottom: 0;
}
.table th {
    font-weight: 600;
    color: #566573;
    border-bottom: 2px solid #f0f0f0;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    padding: 6px 8px;
    white-space: nowrap;
}
.table td {
    vertical-align: middle;
    padding: 5px 8px;
    border-bottom: 1px solid #f0f0f0;
}
.table-hover tbody tr:hover {
    background: #f0f7ff !important;
    cursor: pointer;
}
.table-primary td {
    background: #D6EAF8 !important;
    font-weight: 600;
}
.table-active td {
    background: #f0f0f0 !important;
}

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

.btn-group .btn {
    padding: 1px 4px;
    font-size: 11px;
}
.btn-group .btn i {
    width: 12px;
    height: 12px;
}

.pagination {
    gap: 2px;
}
.pagination .page-item .page-link {
    padding: 4px 10px;
    font-size: 13px;
    border-radius: 4px;
    color: #2C3E50;
}
.pagination .page-item.active .page-link {
    background: #2E86C1;
    border-color: #2E86C1;
    color: white;
}
.pagination .page-item.disabled .page-link {
    color: #999;
}

.modal.show {
    display: block !important;
}
.modal-backdrop {
    display: none;
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

@media (max-width: 992px) {
    .table { font-size: 11px; }
    .table td, .table th { padding: 4px 6px; }
}

@media (max-width: 768px) {
    .table td, .table th { padding: 3px 5px; font-size: 10px; }
    .card-header { padding: 8px 12px; }
    .card-body { padding: 10px 12px; }
    .btn-group .btn { padding: 1px 3px; }
    .btn-group .btn i { width: 10px; height: 10px; }
    .pagination .page-item .page-link { padding: 3px 6px; font-size: 11px; }
    .member-avatar-sm { width: 24px; height: 24px; }
    .avatar-placeholder { width: 24px; height: 24px; font-size: 10px; }
}

@media print {
    .btn, .btn-group, .no-print { display: none !important; }
    .card { box-shadow: none !important; border: 1px solid #ddd !important; }
    .table td { padding: 4px 6px; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
    
    document.querySelectorAll('.form-select-sm').forEach(function(select) {
        select.addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });
    });
    
    document.querySelectorAll('.clickable-row').forEach(function(row) {
        row.addEventListener('click', function(e) {
            if (e.target.closest('.btn, .btn-group, a')) {
                return;
            }
            var memberId = this.getAttribute('data-member-id');
            if (memberId) {
                window.location.href = 'member_profile.php?id=' + memberId;
            }
        });
    });
});

function printReceipt(receiptNo, memberName, amount, date, type) {
    const typeLabels = {
        'regular': 'নিয়মিত',
        'special': 'বিশেষ',
        'share': 'শেয়ার',
        'fd': 'এফডি',
        'fine': 'ফাইন',
        'withdrawal': 'উত্তোলন',
        'expense': 'খরচ'
    };
    
    const receiptHTML = `
        <div class="receipt-container">
            <div class="receipt-header">
                <h5>${type === 'withdrawal' || type === 'expense' ? 'উত্তোলন/খরচ রসিদ' : 'জমার রসিদ'}</h5>
                <small>রসিদ নং: ${receiptNo}</small>
            </div>
            <div class="receipt-body">
                <div class="receipt-row">
                    <span><strong>সদস্য:</strong></span>
                    <span>${memberName}</span>
                </div>
                <div class="receipt-row">
                    <span><strong>পরিমাণ:</strong></span>
                    <span>${formatCurrency(parseFloat(amount))}</span>
                </div>
                <div class="receipt-row">
                    <span><strong>ধরন:</strong></span>
                    <span>${typeLabels[type] || type}</span>
                </div>
                <div class="receipt-row">
                    <span><strong>তারিখ:</strong></span>
                    <span>${date}</span>
                </div>
            </div>
            <div class="receipt-footer">
                <small>ধন্যবাদ</small>
            </div>
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
            <head>
                <title>জমার রসিদ</title>
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

function formatCurrency(amount) {
    return '৳ ' + parseFloat(amount).toFixed(2);
}

function exportExcel() {
    const table = document.getElementById('depositTable');
    const rows = table.querySelectorAll('tr');
    let csv = [];
    
    const headers = ['#', 'রসিদ নং', 'সদস্য', 'পরিমাণ', 'তারিখ', 'মাস/due_date', 'ধরন', 'পদ্ধতি', 'লেনদেন নং', 'মন্তব্য', 'সংগ্রহকারী'];
    csv.push(headers.join(','));
    
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        if (cells.length > 0) {
            const rowData = [];
            for (let i = 0; i < cells.length - 1; i++) {
                let text = cells[i].textContent.trim();
                text = text.replace(/[৳,]/g, '').trim();
                rowData.push('"' + text + '"');
            }
            csv.push(rowData.join(','));
        }
    });
    
    const blob = new Blob(['\uFEFF' + csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `deposits_${new Date().toISOString().slice(0,10)}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
}

function exportPDF() {
    alert('PDF export functionality will be implemented with a library like jsPDF or DomPDF');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>