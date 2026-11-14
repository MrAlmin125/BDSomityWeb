<?php
// =====================================================
// admin/members.php - Member Card View (PREMIUM STYLES)
// =====================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
requireAdmin();

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$pageTitle = 'সদস্য কার্ড';

$conn = conn();

// ============================================
// HELPER FUNCTION FOR AVATAR GENERATION
// ============================================
function getAvatarColor($name) {
    $colors = [
        '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7', 
        '#DDA0DD', '#98D8C8', '#F7DC6F', '#BB8FCE', '#85C1E9',
        '#F8C471', '#82E0AA', '#F1948A', '#85929E', '#73C6B6',
        '#E59866', '#AF7AC5', '#5DADE2', '#58D68D', '#F4D03F'
    ];
    $hash = 0;
    for ($i = 0; $i < strlen($name); $i++) {
        $hash = ($hash * 31 + ord($name[$i])) & 0xFFFFFFFF;
    }
    return $colors[abs($hash) % count($colors)];
}

function generateAvatar($name, $size = 80, $circle = true) {
    $firstLetter = mb_substr($name, 0, 1, 'UTF-8');
    $color = getAvatarColor($name);
    
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 ' . $size . ' ' . $size . '">';
    
    if ($circle) {
        $svg .= '<circle cx="' . ($size/2) . '" cy="' . ($size/2) . '" r="' . ($size/2) . '" fill="' . $color . '"/>';
    } else {
        $svg .= '<rect width="' . $size . '" height="' . $size . '" rx="12" fill="' . $color . '"/>';
    }
    
    $svg .= '<defs>';
    $svg .= '<linearGradient id="grad' . $size . '" x1="0%" y1="0%" x2="100%" y2="100%">';
    $svg .= '<stop offset="0%" style="stop-color:#ffffff;stop-opacity:1" />';
    $svg .= '<stop offset="100%" style="stop-color:#000000;stop-opacity:0.2" />';
    $svg .= '</linearGradient>';
    $svg .= '</defs>';
    
    if ($circle) {
        $svg .= '<circle cx="' . ($size/2) . '" cy="' . ($size/2) . '" r="' . ($size/2) . '" fill="url(#grad' . $size . ')" opacity="0.3"/>';
    } else {
        $svg .= '<rect width="' . $size . '" height="' . $size . '" rx="12" fill="url(#grad' . $size . ')" opacity="0.3"/>';
    }
    
    $fontSize = $size * 0.5;
    $svg .= '<text x="' . ($size/2) . '" y="' . ($size/2 + $size * 0.04) . '" ';
    $svg .= 'font-family="Arial, Helvetica, sans-serif" ';
    $svg .= 'font-size="' . $fontSize . '" ';
    $svg .= 'font-weight="bold" ';
    $svg .= 'fill="white" ';
    $svg .= 'text-anchor="middle" ';
    $svg .= 'dominant-baseline="central" ';
    $svg .= 'letter-spacing="1" ';
    $svg .= 'style="text-shadow: 0 2px 4px rgba(0,0,0,0.2);">';
    $svg .= htmlspecialchars($firstLetter);
    $svg .= '</text>';
    $svg .= '</svg>';
    
    return 'data:image/svg+xml;charset=utf-8,' . rawurlencode($svg);
}

function getMemberPhoto($member, $size = 80) {
    if (!empty($member['photo']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $member['photo'])) {
        return $member['photo'];
    }
    return generateAvatar($member['name'], $size, true);
}

// ============================================
// GET MEMBER DUE SUMMARY
// ============================================
if (!function_exists('getMemberDueSummary')) {
    function getMemberDueSummary($memberId) {
        $conn = conn();
        
        try {
            $tableCheck = $conn->query("SHOW TABLES LIKE 'monthly_dues'");
            if ($tableCheck->num_rows == 0) {
                return ['total_due' => 0, 'unpaid_months' => 0, 'oldest_due' => null, 'has_due' => false];
            }
            
            $query = "SELECT 
                        id,
                        due_year,
                        due_month,
                        amount,
                        paid_amount,
                        (amount - paid_amount) as due_amount,
                        status
                      FROM monthly_dues 
                      WHERE member_id = ? AND (status = 'unpaid' OR amount > paid_amount)";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $memberId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $totalDue = 0;
            $unpaidMonths = 0;
            $oldestYear = null;
            $oldestMonth = null;
            
            while ($row = $result->fetch_assoc()) {
                $dueAmount = $row['due_amount'];
                if ($dueAmount > 0) {
                    $totalDue += $dueAmount;
                    $unpaidMonths++;
                    
                    if ($oldestYear === null || $row['due_year'] < $oldestYear || 
                        ($row['due_year'] == $oldestYear && $row['due_month'] < $oldestMonth)) {
                        $oldestYear = $row['due_year'];
                        $oldestMonth = $row['due_month'];
                    }
                }
            }
            
            $oldestDue = null;
            if ($oldestYear !== null && $oldestMonth !== null) {
                $oldestDue = $oldestYear . '-' . str_pad($oldestMonth, 2, '0', STR_PAD_LEFT) . '-01';
            }
            
            return [
                'total_due' => $totalDue,
                'unpaid_months' => $unpaidMonths,
                'oldest_due' => $oldestDue,
                'has_due' => $totalDue > 0
            ];
        } catch (Exception $e) {
            return ['total_due' => 0, 'unpaid_months' => 0, 'oldest_due' => null, 'has_due' => false];
        }
    }
}

// ============================================
// FIXED: GET MEMBER FINANCIAL SUMMARY
// ============================================
if (!function_exists('getMemberFinancialSummary')) {
    function getMemberFinancialSummary($memberId) {
        $conn = conn();
        
        // ===== Regular Deposits ONLY =====
        $totalRegularDeposits = queryOne("
            SELECT COALESCE(SUM(amount), 0) as total 
            FROM deposits 
            WHERE member_id = ? AND deposit_type = 'regular'
        ", 'i', [$memberId])['total'] ?? 0;
        
        // ===== Fines/Penalties ONLY =====
        $totalFines = queryOne("
            SELECT COALESCE(SUM(amount), 0) as total 
            FROM deposits 
            WHERE member_id = ? AND deposit_type IN ('fine', 'penalty', 'expense')
        ", 'i', [$memberId])['total'] ?? 0;
        
        // ===== Withdrawals =====
        $totalWithdrawals = queryOne("
            SELECT COALESCE(SUM(amount), 0) as total 
            FROM withdrawals 
            WHERE member_id = ? AND status = 'completed'
        ", 'i', [$memberId])['total'] ?? 0;
        
        // ===== Balance = (Regular Deposits + Fines) - Withdrawals =====
        $balance = ($totalRegularDeposits + $totalFines) - $totalWithdrawals;
        
        return [
            'regular_deposits' => $totalRegularDeposits,
            'fines' => $totalFines,
            'withdrawals' => $totalWithdrawals,
            'balance' => $balance
        ];
    }
}

// ============================================
// HANDLE PHOTO UPLOAD
// ============================================
if (isset($_POST['action']) && $_POST['action'] === 'upload_photo') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'নিরাপত্তা যাচাই ব্যর্থ।');
        header('Location: members.php');
        exit;
    }
    
    $memberId = (int)($_POST['member_id'] ?? 0);
    if ($memberId <= 0) {
        setFlash('danger', 'সঠিক সদস্য আইডি দিন।');
        header('Location: members.php');
        exit;
    }
    
    $member = queryOne("SELECT photo FROM members WHERE id = ?", 'i', [$memberId]);
    if (!$member) {
        setFlash('danger', 'সদস্য পাওয়া যায়নি।');
        header('Location: members.php');
        exit;
    }
    
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 2 * 1024 * 1024;
        
        if (!in_array($_FILES['photo']['type'], $allowed)) {
            setFlash('danger', 'শুধু JPG, PNG, GIF বা WebP ফাইল অনুমোদিত।');
            header('Location: members.php');
            exit;
        }
        
        if ($_FILES['photo']['size'] > $maxSize) {
            setFlash('danger', 'ফাইলের সাইজ 2MB এর বেশি হতে পারে না।');
            header('Location: members.php');
            exit;
        }
        
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/members/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        if (!empty($member['photo']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $member['photo'])) {
            unlink($_SERVER['DOCUMENT_ROOT'] . $member['photo']);
        }
        
        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $filename = 'member_' . $memberId . '_' . time() . '.' . $ext;
        $filepath = '/uploads/members/' . $filename;
        $fullpath = $_SERVER['DOCUMENT_ROOT'] . $filepath;
        
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $fullpath)) {
            $stmt = $conn->prepare("UPDATE members SET photo = ? WHERE id = ?");
            $stmt->bind_param('si', $filepath, $memberId);
            if ($stmt->execute()) {
                setFlash('success', 'প্রোফাইল ছবি আপডেট করা হয়েছে।');
                logActivity('member_photo_upload', "Member #$memberId photo uploaded");
            } else {
                setFlash('danger', 'ডেটাবেস আপডেট ব্যর্থ।');
            }
            $stmt->close();
        } else {
            setFlash('danger', 'ফাইল আপলোড ব্যর্থ।');
        }
    } else {
        setFlash('danger', 'কোনো ফাইল নির্বাচন করা হয়নি বা ফাইল আপলোড ব্যর্থ।');
    }
    
    header('Location: members.php');
    exit;
}

// ============================================
// HANDLE PHOTO DELETE
// ============================================
if (isset($_GET['delete_photo']) && $_GET['delete_photo'] > 0) {
    $memberId = (int)$_GET['delete_photo'];
    if (!verifyCSRF($_GET['csrf_token'] ?? '')) {
        setFlash('danger', 'নিরাপত্তা যাচাই ব্যর্থ।');
        header('Location: members.php');
        exit;
    }
    
    $member = queryOne("SELECT photo FROM members WHERE id = ?", 'i', [$memberId]);
    if ($member && !empty($member['photo'])) {
        if (file_exists($_SERVER['DOCUMENT_ROOT'] . $member['photo'])) {
            unlink($_SERVER['DOCUMENT_ROOT'] . $member['photo']);
        }
        
        $stmt = $conn->prepare("UPDATE members SET photo = NULL WHERE id = ?");
        $stmt->bind_param('i', $memberId);
        if ($stmt->execute()) {
            setFlash('success', 'প্রোফাইল ছবি ডিলিট করা হয়েছে।');
            logActivity('member_photo_delete', "Member #$memberId photo deleted");
        } else {
            setFlash('danger', 'ডেটাবেস আপডেট ব্যর্থ।');
        }
        $stmt->close();
    } else {
        setFlash('warning', 'কোনো ছবি নেই।');
    }
    
    header('Location: members.php');
    exit;
}

// ============================================
// HANDLE PASSWORD RESET
// ============================================
if (isset($_GET['reset_password']) && $_GET['reset_password'] > 0) {
    $memberId = (int)$_GET['reset_password'];
    if (!verifyCSRF($_GET['csrf_token'] ?? '')) {
        setFlash('danger', 'নিরাপত্তা যাচাই ব্যর্থ।');
        header('Location: members.php');
        exit;
    }
    
    $member = queryOne("SELECT name, phone FROM members WHERE id = ?", 'i', [$memberId]);
    if ($member) {
        $defaultPassword = password_hash($member['phone'], PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE members SET password = ? WHERE id = ?");
        $stmt->bind_param('si', $defaultPassword, $memberId);
        if ($stmt->execute()) {
            setFlash('success', "{$member['name']} এর পাসওয়ার্ড ডিফল্ট (ফোন নম্বর) এ রিসেট করা হয়েছে।");
            logActivity('member_password_reset', "Member #$memberId password reset to default");
        } else {
            setFlash('danger', 'পাসওয়ার্ড রিসেট ব্যর্থ।');
        }
        $stmt->close();
    } else {
        setFlash('danger', 'সদস্য পাওয়া যায়নি।');
    }
    
    header('Location: members.php');
    exit;
}

// ============================================
// HANDLE DELETE
// ============================================
if (isset($_GET['delete']) && $_GET['delete'] > 0) {
    $delId = (int)$_GET['delete'];
    if (!verifyCSRF($_GET['csrf_token'] ?? '')) {
        setFlash('danger', 'নিরাপত্তা যাচাই ব্যর্থ।');
    } else {
        $hasDeposits = queryOne("SELECT COUNT(*) as count FROM deposits WHERE member_id = ?", 'i', [$delId])['count'] ?? 0;
        $hasLoans = queryOne("SELECT COUNT(*) as count FROM loans WHERE member_id = ?", 'i', [$delId])['count'] ?? 0;
        
        if ($hasDeposits > 0 || $hasLoans > 0) {
            setFlash('danger', 'এই সদস্যের জমা বা ঋণ আছে। প্রথমে সেগুলো ডিলিট করুন।');
        } else {
            $member = queryOne("SELECT name, member_id, photo FROM members WHERE id = ?", 'i', [$delId]);
            if ($member && !empty($member['photo']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $member['photo'])) {
                unlink($_SERVER['DOCUMENT_ROOT'] . $member['photo']);
            }
            $conn->query("DELETE FROM members WHERE id = $delId");
            setFlash('success', "সদস্য {$member['name']} ({$member['member_id']}) ডিলিট করা হয়েছে।");
            logActivity('member_delete', "সদস্য ডিলিট: {$member['name']}");
        }
    }
    header('Location: members.php');
    exit;
}

// ============================================
// HANDLE TOGGLE STATUS
// ============================================
if (isset($_GET['toggle_status']) && $_GET['toggle_status'] > 0) {
    $togId = (int)$_GET['toggle_status'];
    if (!verifyCSRF($_GET['csrf_token'] ?? '')) {
        setFlash('danger', 'নিরাপত্তা যাচাই ব্যর্থ।');
    } else {
        $current = queryOne("SELECT status FROM members WHERE id = ?", 'i', [$togId]);
        if ($current) {
            $newStatus = $current['status'] === 'active' ? 'inactive' : 'active';
            $conn->query("UPDATE members SET status = '$newStatus' WHERE id = $togId");
            $msg = $newStatus === 'active' ? 'সক্রিয়' : 'নিষ্ক্রিয়';
            setFlash('success', "সদস্যের অবস্থা $msg করা হয়েছে।");
            logActivity('member_toggle', "সদস্য অবস্থা পরিবর্তন: $newStatus");
        }
    }
    header('Location: members.php');
    exit;
}

// ============================================
// HANDLE EDIT POST
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'নিরাপত্তা যাচাই ব্যর্থ।');
        header('Location: members.php');
        exit;
    }

    $editId = (int)($_POST['member_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $father = trim($_POST['father_name'] ?? '');
    $mother = trim($_POST['mother_name'] ?? '');
    $spouse = trim($_POST['spouse_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $altPhone = trim($_POST['alt_phone'] ?? '');
    $nid = trim($_POST['nid'] ?? '');
    $dob = $_POST['date_of_birth'] ?? '';
    $address = trim($_POST['address'] ?? '');
    $joinDate = $_POST['join_date'] ?? date('Y-m-d');
    $monthlyDeposit = (float)($_POST['monthly_deposit'] ?? 500);
    $nominee = trim($_POST['nominee_name'] ?? '');
    $nomineeRel = trim($_POST['nominee_relation'] ?? '');
    $nomineePhone = trim($_POST['nominee_phone'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $note = trim($_POST['note'] ?? '');

    if (empty($name) || empty($phone)) {
        setFlash('danger', 'নাম ও ফোন নম্বর আবশ্যক।');
        header('Location: members.php');
        exit;
    }

    $stmt = $conn->prepare("
        UPDATE members SET 
            name=?, father_name=?, mother_name=?, spouse_name=?, phone=?, alt_phone=?, 
            email=?, nid=?, date_of_birth=?, address=?, join_date=?, 
            monthly_deposit=?, 
            nominee_name=?, nominee_relation=?, nominee_phone=?, 
            status=?, note=? 
        WHERE id=?
    ");
    
    $stmt->bind_param(
        'sssssssssssdissssi',
        $name, $father, $mother, $spouse, $phone, $altPhone,
        $email, $nid, $dob, $address, $joinDate,
        $monthlyDeposit,
        $nominee, $nomineeRel, $nomineePhone,
        $status, $note, $editId
    );
    
    if ($stmt->execute()) {
        logActivity('member_edit', "Edited member ID: $editId");
        setFlash('success', 'সদস্য তথ্য আপডেট হয়েছে।');
    } else {
        setFlash('danger', 'আপডেট ব্যর্থ হয়েছে: ' . $stmt->error);
    }
    $stmt->close();
    
    header('Location: members.php');
    exit;
}

// ============================================
// LIST SEARCH & PAGINATION
// ============================================
$search  = trim($_GET['s'] ?? '');
$statusFilter  = $_GET['status'] ?? '';
$monthStatus = $_GET['month_status'] ?? '';
$page    = max(1, (int)($_GET['page'] ?? 1));

$where = '1=1';
$types = ''; 
$params = [];

if ($search) {
    $where .= " AND (name LIKE ? OR phone LIKE ? OR member_id LIKE ? OR nid LIKE ?)";
    $like = "%$search%";
    $types .= 'ssss'; 
    $params = array_merge($params, [$like, $like, $like, $like]);
}
if ($statusFilter) {
    $where .= " AND status = ?";
    $types .= 's'; 
    $params[] = $statusFilter;
}

if ($monthStatus) {
    $currentMonth = date('m');
    $currentYear = date('Y');
    if ($monthStatus === 'paid') {
        $where .= " AND id IN (SELECT DISTINCT member_id FROM deposits WHERE MONTH(deposit_date) = ? AND YEAR(deposit_date) = ?)";
        $types .= 'ii';
        $params[] = $currentMonth;
        $params[] = $currentYear;
    } elseif ($monthStatus === 'unpaid') {
        $where .= " AND id NOT IN (SELECT DISTINCT member_id FROM deposits WHERE MONTH(deposit_date) = ? AND YEAR(deposit_date) = ?)";
        $types .= 'ii';
        $params[] = $currentMonth;
        $params[] = $currentYear;
    }
}

$sql = "SELECT id, member_id, name, father_name, mother_name, spouse_name, phone, alt_phone, email, nid,
               date_of_birth, address, photo, join_date, monthly_deposit,
               nominee_name, nominee_relation, nominee_phone, status, note, created_at 
        FROM members 
        WHERE $where 
        ORDER BY created_at DESC";

$countSql = "SELECT COUNT(*) as total FROM members WHERE $where";
$countResult = queryOne($countSql, $types, $params);
$totalMembers = $countResult['total'] ?? 0;

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

<!-- ============ CARD VIEW ============ -->
<div class="member-cards-container">
    <!-- Header -->
    <div class="cards-header">
        <div class="header-left">
            <div class="header-icon-wrapper">
                <i data-feather="users" class="header-icon"></i>
            </div>
            <div>
                <h4>সদস্য কার্ড</h4>
                <span class="member-count"><?= $totalMembers ?> জন সদস্য</span>
            </div>
        </div>
        <a href="member_add.php" class="btn btn-primary btn-add">
            <i data-feather="user-plus"></i> নতুন সদস্য
        </a>
    </div>

    <!-- Filters -->
    <div class="filters-bar">
        <form method="GET" class="filters-form">
            <div class="filter-group">
                <div class="search-box">
                    <i data-feather="search" class="search-icon"></i>
                    <input type="text" name="s" placeholder="নাম, ফোন, সদস্য ID..." value="<?= clean($search) ?>">
                </div>
                <select name="status" onchange="this.form.submit()">
                    <option value="">📋 সব</option>
                    <option value="active" <?= $statusFilter==='active'?'selected':'' ?>>✅ সক্রিয়</option>
                    <option value="inactive" <?= $statusFilter==='inactive'?'selected':'' ?>>⛔ নিষ্ক্রিয়</option>
                    <option value="suspended" <?= $statusFilter==='suspended'?'selected':'' ?>>⏸️ স্থগিত</option>
                    <option value="deceased" <?= $statusFilter==='deceased'?'selected':'' ?>>⚰️ মৃত</option>
                </select>
                <select name="month_status" onchange="this.form.submit()">
                    <option value="">📆 সব</option>
                    <option value="paid" <?= $monthStatus==='paid'?'selected':'' ?>>✅ পরিশোধিত</option>
                    <option value="unpaid" <?= $monthStatus==='unpaid'?'selected':'' ?>>❌ বকেয়া</option>
                </select>
                <button type="submit" class="btn-search">
                    <i data-feather="search"></i>
                </button>
                <?php if ($search || $statusFilter || $monthStatus): ?>
                <a href="members.php" class="btn-reset">
                    <i data-feather="x"></i>
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Cards Grid -->
    <?php $paged = paginate($sql, $types, $params, $page); ?>
    <div class="cards-grid">
        <?php if (empty($paged['data'])): ?>
            <div class="empty-state">
                <div class="empty-icon-wrapper">
                    <i data-feather="inbox" class="empty-icon"></i>
                </div>
                <h5>কোনো সদস্য পাওয়া যায়নি</h5>
                <p>আপনার অনুসন্ধানের সাথে মিলে এমন কোনো সদস্য নেই</p>
                <a href="member_add.php" class="btn btn-primary btn-sm mt-2">
                    <i data-feather="user-plus"></i> নতুন সদস্য যোগ করুন
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($paged['data'] as $m): 
                $currentMonth = date('m');
                $currentYear = date('Y');
                
                $monthDeposit = queryOne(
                    "SELECT amount FROM deposits WHERE member_id = ? AND MONTH(deposit_date) = ? AND YEAR(deposit_date) = ? AND deposit_type != 'expense'",
                    'iii', [$m['id'], $currentMonth, $currentYear]
                );
                $hasDeposited = $monthDeposit !== null;
                $monthStatusBadge = $hasDeposited ? 'পরিশোধিত' : 'বকেয়া';
                $monthStatusClass = $hasDeposited ? 'badge-paid' : 'badge-unpaid';
                
                // ===== FIXED: Get Financial Summary =====
                $financial = getMemberFinancialSummary($m['id']);
                
                $dueSummary = getMemberDueSummary($m['id']);
                $dueSummary = array_merge([
                    'total_due' => 0,
                    'unpaid_months' => 0,
                    'oldest_due' => null,
                    'has_due' => false
                ], $dueSummary);
                
                $photo = getMemberPhoto($m, 80);
                $statusClass = $m['status'] === 'active' ? 'status-active' : ($m['status'] === 'inactive' ? 'status-inactive' : ($m['status'] === 'suspended' ? 'status-suspended' : 'status-deceased'));
                $statusText = $m['status'] === 'active' ? 'সক্রিয়' : ($m['status'] === 'inactive' ? 'নিষ্ক্রিয়' : ($m['status'] === 'suspended' ? 'স্থগিত' : 'মৃত'));
                $statusIcon = $m['status'] === 'active' ? '✅' : ($m['status'] === 'inactive' ? '⛔' : ($m['status'] === 'suspended' ? '⏸️' : '⚰️'));
                
                $viewModalId = 'viewModal_' . $m['id'];
                $editModalId = 'editModal_' . $m['id'];
                $deleteModalId = 'deleteModal_' . $m['id'];
                $photoModalId = 'photoModal_' . $m['id'];
                $resetPwModalId = 'resetPwModal_' . $m['id'];
            ?>
            
            <!-- Member Card -->
            <div class="member-card <?= $statusClass ?>">
                <!-- Card Header -->
                <div class="card-header-status">
                    <div class="status-group">
                        <span class="status-badge <?= $statusClass ?>">
                            <span class="status-dot"></span> <?= $statusIcon ?> <?= $statusText ?>
                        </span>
                    </div>
                    <div class="badge-group">
                        <span class="month-badge <?= $monthStatusClass ?>">
                            <?= $monthStatusBadge ?>
                        </span>
                    </div>
                </div>
                
                <!-- Card Body -->
                <div class="card-body-member">
                    <div class="member-avatar-wrapper">
                        <div class="avatar-ring"></div>
                        <img src="<?= $photo ?>" alt="<?= clean($m['name']) ?>" class="member-avatar" loading="lazy">
                        <span class="member-id-badge"><?= clean($m['member_id']) ?></span>
                    </div>
                    
                    <h5 class="member-name"><?= clean($m['name']) ?></h5>
                    
                    <div class="member-details">
                        <div class="detail-item">
                            <i data-feather="phone" class="detail-icon"></i>
                            <span><?= clean($m['phone']) ?></span>
                        </div>
                        <?php if (!empty($m['email'])): ?>
                        <div class="detail-item">
                            <i data-feather="mail" class="detail-icon"></i>
                            <span><?= clean($m['email']) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="detail-item">
                            <i data-feather="calendar" class="detail-icon"></i>
                            <span>যোগদান: <?= formatDate($m['join_date']) ?></span>
                        </div>
                        <div class="detail-item">
                            <i data-feather="dollar-sign" class="detail-icon"></i>
                            <span>মাসিক: <?= formatCurrency($m['monthly_deposit'] ?? 500) ?></span>
                        </div>
                    </div>
                    
                    <!-- ============================================== -->
                    <!-- FIXED: Member Stats - Deposits, Fines, Withdrawals, Balance -->
                    <!-- ============================================== -->
                    <div class="member-stats">
                        <div class="stat-item">
                            <span class="stat-label">মোট জমা</span>
                            <span class="stat-value amount-positive">
                                <?= formatCurrency($financial['regular_deposits']) ?>
                            </span>
                        </div>
                        <div class="stat-divider"></div>
                        <div class="stat-item">
                            <span class="stat-label">জরিমানা</span>
                            <span class="stat-value amount-negative">
                                <?= formatCurrency($financial['fines']) ?>
                            </span>
                        </div>
                        <div class="stat-divider"></div>
                        <div class="stat-item">
                            <span class="stat-label">উত্তোলন</span>
                            <span class="stat-value amount-negative">
                                <?= formatCurrency($financial['withdrawals']) ?>
                            </span>
                        </div>
                        <div class="stat-divider"></div>
                        <div class="stat-item stat-balance">
                            <span class="stat-label">ব্যালেন্স</span>
                            <span class="stat-value <?= $financial['balance'] >= 0 ? 'amount-positive' : 'amount-negative' ?>">
                                <?= formatCurrency($financial['balance']) ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Due Information -->
                    <?php if ($dueSummary['has_due']): ?>
                    <div class="due-info">
                        <div class="due-item due-amount">
                            <i data-feather="alert-circle"></i>
                            <span>বকেয়া: <strong><?= formatCurrency($dueSummary['total_due']) ?></strong></span>
                        </div>
                        <div class="due-divider"></div>
                        <div class="due-item due-months">
                            <i data-feather="clock"></i>
                            <span><?= $dueSummary['unpaid_months'] ?> মাস</span>
                        </div>
                        <?php if ($dueSummary['oldest_due']): ?>
                        <div class="due-divider"></div>
                        <div class="due-item due-oldest">
                            <i data-feather="calendar"></i>
                            <span><?= date('M Y', strtotime($dueSummary['oldest_due'])) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="due-info due-clear">
                        <i data-feather="check-circle"></i>
                        <span>কোনো বকেয়া নেই</span>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Card Actions -->
                    <div class="card-actions">
                        <button class="btn-action btn-view" data-bs-toggle="modal" data-bs-target="#<?= $viewModalId ?>">
                            <i data-feather="eye"></i> বিস্তারিত
                        </button>
                        <button class="btn-action btn-edit" data-bs-toggle="modal" data-bs-target="#<?= $editModalId ?>">
                            <i data-feather="edit-2"></i>
                        </button>
                        <button class="btn-action btn-photo" data-bs-toggle="modal" data-bs-target="#<?= $photoModalId ?>">
                            <i data-feather="camera"></i>
                        </button>
                        <button class="btn-action btn-password" data-bs-toggle="modal" data-bs-target="#<?= $resetPwModalId ?>">
                            <i data-feather="key"></i>
                        </button>
                        <button class="btn-action btn-delete" data-bs-toggle="modal" data-bs-target="#<?= $deleteModalId ?>">
                            <i data-feather="trash-2"></i>
                        </button>
                        <a href="members.php?toggle_status=<?= $m['id'] ?>&csrf_token=<?= generateCSRF() ?>" 
                           class="btn-action btn-toggle" 
                           onclick="return confirm('<?= $m['status'] === 'active' ? 'সদস্যকে নিষ্ক্রিয় করবেন?' : 'সদস্যকে সক্রিয় করবেন?' ?>')"
                           title="<?= $m['status'] === 'active' ? 'নিষ্ক্রিয় করুন' : 'সক্রিয় করুন' ?>">
                            <i data-feather="<?= $m['status'] === 'active' ? 'user-x' : 'user-check' ?>"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- VIEW MODAL - All Details -->
            <!-- ============================================ -->
            <div class="modal fade" id="<?= $viewModalId ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header modal-header-view">
                            <h5 class="modal-title">
                                <i data-feather="user" class="modal-icon"></i>
                                <?= clean($m['name']) ?>
                                <span class="modal-id">#<?= clean($m['member_id']) ?></span>
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="modal-profile">
                                <div class="modal-avatar-wrapper">
                                    <img src="<?= $photo ?>" alt="Profile" class="modal-avatar">
                                </div>
                                <div class="modal-profile-info">
                                    <h4><?= clean($m['name']) ?></h4>
                                    <span class="status-badge <?= $statusClass ?>">
                                        <span class="status-dot"></span> <?= $statusIcon ?> <?= $statusText ?>
                                    </span>
                                    <div class="modal-contact">
                                        <span><i data-feather="phone"></i> <?= clean($m['phone']) ?></span>
                                        <?php if (!empty($m['email'])): ?>
                                        <span><i data-feather="mail"></i> <?= clean($m['email']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="modal-details-grid">
                                <!-- Personal Info -->
                                <div class="detail-section">
                                    <h6 class="section-title">
                                        <i data-feather="user" class="section-icon"></i> ব্যক্তিগত তথ্য
                                    </h6>
                                    <div class="detail-row">
                                        <span class="label">নাম</span>
                                        <span class="value"><?= clean($m['name']) ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="label">পিতার নাম</span>
                                        <span class="value"><?= clean($m['father_name'] ?? '-') ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="label">মাতার নাম</span>
                                        <span class="value"><?= clean($m['mother_name'] ?? '-') ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="label">স্বামী/স্ত্রীর নাম</span>
                                        <span class="value"><?= clean($m['spouse_name'] ?? '-') ?></span>
                                    </div>
                                </div>
                                
                                <!-- Contact Info -->
                                <div class="detail-section">
                                    <h6 class="section-title">
                                        <i data-feather="phone" class="section-icon"></i> যোগাযোগ
                                    </h6>
                                    <div class="detail-row">
                                        <span class="label">মোবাইল</span>
                                        <span class="value"><?= clean($m['phone']) ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="label">বিকল্প ফোন</span>
                                        <span class="value"><?= clean($m['alt_phone'] ?? '-') ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="label">ইমেইল</span>
                                        <span class="value"><?= clean($m['email'] ?? '-') ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="label">NID</span>
                                        <span class="value"><?= clean($m['nid'] ?? '-') ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="label">জন্ম তারিখ</span>
                                        <span class="value"><?= $m['date_of_birth'] ? formatDate($m['date_of_birth']) : '-' ?></span>
                                    </div>
                                </div>
                                
                                <!-- Membership Info -->
                                <div class="detail-section">
                                    <h6 class="section-title">
                                        <i data-feather="credit-card" class="section-icon"></i> সদস্যপদ
                                    </h6>
                                    <div class="detail-row">
                                        <span class="label">যোগদানের তারিখ</span>
                                        <span class="value"><?= formatDate($m['join_date']) ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="label">মাসিক জমা</span>
                                        <span class="value"><?= formatCurrency($m['monthly_deposit'] ?? 500) ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="label">অবস্থা</span>
                                        <span class="value">
                                            <span class="status-badge <?= $statusClass ?>">
                                                <?= $statusIcon ?> <?= $statusText ?>
                                            </span>
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- Nominee Info -->
                                <div class="detail-section">
                                    <h6 class="section-title">
                                        <i data-feather="users" class="section-icon"></i> নমিনি
                                    </h6>
                                    <div class="detail-row">
                                        <span class="label">নাম</span>
                                        <span class="value"><?= clean($m['nominee_name'] ?? '-') ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="label">সম্পর্ক</span>
                                        <span class="value"><?= clean($m['nominee_relation'] ?? '-') ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="label">ফোন</span>
                                        <span class="value"><?= clean($m['nominee_phone'] ?? '-') ?></span>
                                    </div>
                                </div>
                                
                                <!-- Due Info -->
                                <div class="detail-section full-width">
                                    <h6 class="section-title">
                                        <i data-feather="alert-circle" class="section-icon"></i> বকেয়া তথ্য
                                    </h6>
                                    <div class="detail-row">
                                        <span class="label">মোট বকেয়া</span>
                                        <span class="value <?= $dueSummary['total_due'] > 0 ? 'amount-negative' : 'amount-positive' ?>">
                                            <?= formatCurrency($dueSummary['total_due']) ?>
                                        </span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="label">বকেয়া মাস</span>
                                        <span class="value"><?= $dueSummary['unpaid_months'] ?> মাস</span>
                                    </div>
                                    <?php if ($dueSummary['oldest_due']): ?>
                                    <div class="detail-row">
                                        <span class="label">সর্বোচ্চ বকেয়া</span>
                                        <span class="value"><?= date('F Y', strtotime($dueSummary['oldest_due'])) ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Address -->
                                <?php if (!empty($m['address'])): ?>
                                <div class="detail-section full-width">
                                    <h6 class="section-title">
                                        <i data-feather="map-pin" class="section-icon"></i> ঠিকানা
                                    </h6>
                                    <div class="detail-row">
                                        <span class="value note-text"><?= clean($m['address']) ?></span>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Note -->
                                <?php if (!empty($m['note'])): ?>
                                <div class="detail-section full-width">
                                    <h6 class="section-title">
                                        <i data-feather="file-text" class="section-icon"></i> নোট
                                    </h6>
                                    <div class="detail-row">
                                        <span class="value note-text"><?= clean($m['note']) ?></span>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- ============================================== -->
                            <!-- FIXED: Financial Summary -->
                            <!-- ============================================== -->
                            <div class="modal-financial">
                                <div class="fin-item">
                                    <span class="fin-label">মোট জমা</span>
                                    <span class="fin-value amount-positive"><?= formatCurrency($financial['regular_deposits']) ?></span>
                                </div>
                                <div class="fin-divider"></div>
                                <div class="fin-item">
                                    <span class="fin-label">জরিমানা</span>
                                    <span class="fin-value amount-negative"><?= formatCurrency($financial['fines']) ?></span>
                                </div>
                                <div class="fin-divider"></div>
                                <div class="fin-item">
                                    <span class="fin-label">মোট উত্তোলন</span>
                                    <span class="fin-value amount-negative"><?= formatCurrency($financial['withdrawals']) ?></span>
                                </div>
                                <div class="fin-divider"></div>
                                <div class="fin-item">
                                    <span class="fin-label">বর্তমান ব্যালেন্স</span>
                                    <span class="fin-value <?= $financial['balance'] >= 0 ? 'amount-positive' : 'amount-negative' ?>">
                                        <?= formatCurrency($financial['balance']) ?>
                                    </span>
                                </div>
                                <div class="fin-divider"></div>
                                <div class="fin-item">
                                    <span class="fin-label">মোট বকেয়া</span>
                                    <span class="fin-value <?= $dueSummary['total_due'] > 0 ? 'amount-negative' : 'amount-positive' ?>">
                                        <?= formatCurrency($dueSummary['total_due']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বন্ধ</button>
                            <a href="member_profile.php?id=<?= $m['id'] ?>" class="btn btn-primary">
                                <i data-feather="external-link"></i> সম্পূর্ণ প্রোফাইল
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- PHOTO UPLOAD MODAL -->
            <!-- ============================================ -->
            <div class="modal fade" id="<?= $photoModalId ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header modal-header-photo">
                            <h5 class="modal-title">
                                <i data-feather="camera" class="modal-icon"></i>
                                প্রোফাইল ছবি পরিবর্তন
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-center">
                            <div class="mb-3">
                                <label class="form-label fw-bold">বর্তমান ছবি</label>
                                <div>
                                    <img src="<?= getMemberPhoto($m, 120) ?>" alt="Current Photo" 
                                         style="width:120px;height:120px;border-radius:50%;object-fit:cover;border:3px solid #ddd;">
                                </div>
                            </div>
                            
                            <?php if (!empty($m['photo']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $m['photo'])): ?>
                            <div class="mb-3">
                                <a href="members.php?delete_photo=<?= $m['id'] ?>&csrf_token=<?= generateCSRF() ?>" 
                                   class="btn btn-danger btn-sm" 
                                   onclick="return confirm('ছবি ডিলিট করবেন?')">
                                    <i data-feather="trash-2"></i> ছবি ডিলিট
                                </a>
                            </div>
                            <?php endif; ?>
                            
                            <form method="POST" enctype="multipart/form-data">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="upload_photo">
                                <input type="hidden" name="member_id" value="<?= $m['id'] ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold">নতুন ছবি আপলোড</label>
                                    <input type="file" name="photo" class="form-control" accept="image/*" required>
                                    <small class="text-muted">JPG, PNG, GIF, WebP (Max 2MB)</small>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i data-feather="upload"></i> আপলোড করুন
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- PASSWORD RESET MODAL -->
            <!-- ============================================ -->
            <div class="modal fade" id="<?= $resetPwModalId ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-sm modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header modal-header-password">
                            <h5 class="modal-title">
                                <i data-feather="key" class="modal-icon"></i>
                                পাসওয়ার্ড রিসেট
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-center">
                            <div class="password-icon-wrapper">
                                <i data-feather="lock" class="password-icon"></i>
                            </div>
                            <h6 class="password-title">পাসওয়ার্ড রিসেট করুন</h6>
                            <p class="password-text">
                                <strong><?= clean($m['name']) ?></strong> এর পাসওয়ার্ড 
                                <strong><?= clean($m['phone']) ?></strong> (ফোন নম্বর) এ রিসেট করা হবে।
                            </p>
                            <div class="alert alert-warning small">
                                <i data-feather="info" style="width:14px;height:14px"></i>
                                সদস্য তার ফোন নম্বর ব্যবহার করে লগইন করতে পারবেন।
                            </div>
                            <a href="members.php?reset_password=<?= $m['id'] ?>&csrf_token=<?= generateCSRF() ?>" 
                               class="btn btn-warning" 
                               onclick="return confirm('পাসওয়ার্ড রিসেট করবেন?')">
                                <i data-feather="refresh-cw"></i> পাসওয়ার্ড রিসেট করুন
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- EDIT MODAL -->
            <!-- ============================================ -->
            <div class="modal fade" id="<?= $editModalId ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <form method="POST">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="member_id" value="<?= $m['id'] ?>">
                            
                            <div class="modal-header modal-header-edit">
                                <h5 class="modal-title">
                                    <i data-feather="edit-2" class="modal-icon"></i>
                                    সম্পাদনা: <?= clean($m['name']) ?>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="edit-form-grid">
                                    <!-- Personal Info -->
                                    <div class="form-group">
                                        <label>নাম</label>
                                        <input type="text" name="name" class="form-control" value="<?= clean($m['name']) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>ফোন</label>
                                        <input type="tel" name="phone" class="form-control" value="<?= clean($m['phone']) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>পিতার নাম</label>
                                        <input type="text" name="father_name" class="form-control" value="<?= clean($m['father_name'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>মাতার নাম</label>
                                        <input type="text" name="mother_name" class="form-control" value="<?= clean($m['mother_name'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>স্বামী/স্ত্রীর নাম</label>
                                        <input type="text" name="spouse_name" class="form-control" value="<?= clean($m['spouse_name'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>ইমেইল</label>
                                        <input type="email" name="email" class="form-control" value="<?= clean($m['email'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>বিকল্প ফোন</label>
                                        <input type="tel" name="alt_phone" class="form-control" value="<?= clean($m['alt_phone'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>NID</label>
                                        <input type="text" name="nid" class="form-control" value="<?= clean($m['nid'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>জন্ম তারিখ</label>
                                        <input type="date" name="date_of_birth" class="form-control" value="<?= $m['date_of_birth'] ?? '' ?>">
                                    </div>
                                    <div class="form-group full-width">
                                        <label>ঠিকানা</label>
                                        <textarea name="address" class="form-control" rows="2"><?= clean($m['address'] ?? '') ?></textarea>
                                    </div>
                                    
                                    <!-- Membership Info -->
                                    <div class="form-group">
                                        <label>যোগদানের তারিখ</label>
                                        <input type="date" name="join_date" class="form-control" value="<?= $m['join_date'] ?? date('Y-m-d') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>অবস্থা</label>
                                        <select name="status" class="form-control">
                                            <option value="active" <?= ($m['status'] ?? '')==='active'?'selected':'' ?>>✅ সক্রিয়</option>
                                            <option value="inactive" <?= ($m['status'] ?? '')==='inactive'?'selected':'' ?>>⛔ নিষ্ক্রিয়</option>
                                            <option value="suspended" <?= ($m['status'] ?? '')==='suspended'?'selected':'' ?>>⏸️ স্থগিত</option>
                                            <option value="deceased" <?= ($m['status'] ?? '')==='deceased'?'selected':'' ?>>⚰️ মৃত</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>মাসিক জমা (৳)</label>
                                        <input type="number" name="monthly_deposit" class="form-control" step="0.01" value="<?= $m['monthly_deposit'] ?? 500 ?>">
                                    </div>
                                    
                                    <!-- Nominee Info -->
                                    <div class="form-group">
                                        <label>নমিনির নাম</label>
                                        <input type="text" name="nominee_name" class="form-control" value="<?= clean($m['nominee_name'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>সম্পর্ক</label>
                                        <input type="text" name="nominee_relation" class="form-control" value="<?= clean($m['nominee_relation'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>নমিনির ফোন</label>
                                        <input type="tel" name="nominee_phone" class="form-control" value="<?= clean($m['nominee_phone'] ?? '') ?>">
                                    </div>
                                    <div class="form-group full-width">
                                        <label>নোট</label>
                                        <textarea name="note" class="form-control" rows="2"><?= clean($m['note'] ?? '') ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
                                <button type="submit" class="btn btn-primary">
                                    <i data-feather="save"></i> আপডেট করুন
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- DELETE MODAL -->
            <!-- ============================================ -->
            <div class="modal fade" id="<?= $deleteModalId ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-sm modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header modal-header-delete">
                            <h5 class="modal-title">
                                <i data-feather="alert-triangle" class="modal-icon text-danger"></i>
                                নিশ্চিতকরণ
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-center">
                            <div class="delete-icon-wrapper">
                                <i data-feather="trash-2" class="delete-icon"></i>
                            </div>
                            <h6 class="delete-title">আপনি কি নিশ্চিত?</h6>
                            <p class="delete-text">
                                <strong><?= clean($m['name']) ?></strong> (<?= clean($m['member_id']) ?>)<br>
                                এই সদস্যকে ডিলিট করলে সমস্ত তথ্য মুছে যাবে!
                            </p>
                            <?php 
                            $hasDeposits = queryOne("SELECT COUNT(*) as count FROM deposits WHERE member_id = ?", 'i', [$m['id']])['count'] ?? 0;
                            $hasLoans = queryOne("SELECT COUNT(*) as count FROM loans WHERE member_id = ?", 'i', [$m['id']])['count'] ?? 0;
                            if ($hasDeposits > 0 || $hasLoans > 0): 
                            ?>
                            <div class="delete-warning">
                                <i data-feather="info"></i>
                                <span>এই সদস্যের জমা বা ঋণ রয়েছে। প্রথমে সেগুলো ডিলিট করুন।</span>
                            </div>
                            <?php else: ?>
                            <p class="delete-text text-muted small">এই কাজটি পূর্বাবস্থায় ফেরানো যাবে না।</p>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer justify-content-center">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
                            <?php if ($hasDeposits == 0 && $hasLoans == 0): ?>
                            <a href="members.php?delete=<?= $m['id'] ?>&csrf_token=<?= generateCSRF() ?>" class="btn btn-danger">
                                <i data-feather="trash-2"></i> ডিলিট করুন
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <div class="pagination-wrapper">
        <?= renderPagination($paged, "members.php?s=$search&status=$statusFilter&month_status=$monthStatus") ?>
    </div>
</div>

<!-- ============================================ -->
<!-- STYLES -->
<!-- ============================================ -->
<style>
.member-cards-container {
    padding: 20px 24px;
    max-width: 1440px;
    margin: 0 auto;
    background: #F0F2F5;
    min-height: 100vh;
}

.cards-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    flex-wrap: wrap;
    gap: 15px;
    background: white;
    padding: 18px 24px;
    border-radius: 16px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
}

.header-left {
    display: flex;
    align-items: center;
    gap: 16px;
}

.header-icon-wrapper {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #1B4F72, #2E86C1);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.header-icon-wrapper .header-icon {
    width: 24px;
    height: 24px;
    color: white;
}

.header-left h4 {
    margin: 0;
    font-weight: 700;
    color: #1B4F72;
    font-size: 22px;
}

.member-count {
    font-size: 13px;
    color: #666;
    font-weight: 500;
}

.btn-add {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 10px 24px;
    border-radius: 10px;
    font-weight: 600;
    background: linear-gradient(135deg, #1B4F72, #2E86C1);
    border: none;
    transition: all 0.3s;
    white-space: nowrap;
    color: white;
    text-decoration: none;
}

.btn-add:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(46, 134, 193, 0.3);
    color: white;
}

.filters-bar {
    background: white;
    padding: 16px 20px;
    border-radius: 16px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    margin-bottom: 25px;
}

.filters-form {
    width: 100%;
}

.filter-group {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
}

.filter-group .search-box {
    flex: 1;
    min-width: 220px;
    position: relative;
}

.filter-group .search-box .search-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    width: 18px;
    height: 18px;
    color: #999;
}

.filter-group .search-box input {
    width: 100%;
    padding: 10px 14px 10px 42px;
    border: 2px solid #e8ecf1;
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.3s;
    background: #FAFBFC;
}

.filter-group .search-box input:focus {
    border-color: #2E86C1;
    outline: none;
    background: white;
    box-shadow: 0 0 0 4px rgba(46, 134, 193, 0.1);
}

.filter-group select {
    padding: 10px 36px 10px 16px;
    border: 2px solid #e8ecf1;
    border-radius: 10px;
    font-size: 14px;
    background: #FAFBFC;
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23555' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 14px center;
    transition: all 0.3s;
}

.filter-group select:focus {
    border-color: #2E86C1;
    outline: none;
    background: white;
    box-shadow: 0 0 0 4px rgba(46, 134, 193, 0.1);
}

.btn-search, .btn-reset {
    padding: 10px 16px;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
}

.btn-search {
    background: linear-gradient(135deg, #1B4F72, #2E86C1);
    color: white;
}

.btn-search:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(46, 134, 193, 0.3);
}

.btn-reset {
    background: #f0f0f0;
    color: #555;
}

.btn-reset:hover {
    background: #e0e0e0;
    transform: rotate(90deg);
}

.cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 24px;
    margin-bottom: 30px;
}

.member-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 2px 16px rgba(0,0,0,0.06);
    overflow: hidden;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    border-top: 5px solid #ddd;
    position: relative;
}

.member-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 40px rgba(0,0,0,0.12);
}

.member-card.status-active {
    border-top-color: #27AE60;
}

.member-card.status-inactive {
    border-top-color: #95A5A6;
    opacity: 0.8;
}

.member-card.status-suspended {
    border-top-color: #F39C12;
}

.member-card.status-deceased {
    border-top-color: #34495E;
    opacity: 0.7;
}

.card-header-status {
    padding: 14px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #FAFBFC;
    border-bottom: 1px solid #f0f0f0;
    flex-wrap: wrap;
    gap: 8px;
}

.status-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.status-badge {
    font-size: 13px;
    font-weight: 600;
    padding: 5px 14px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
    animation: pulse-dot 2s infinite;
}

@keyframes pulse-dot {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.4; }
}

.status-badge.status-active {
    color: #1E8449;
    background: #D5F5E3;
}

.status-badge.status-active .status-dot {
    background: #27AE60;
}

.status-badge.status-inactive {
    color: #5D6D7E;
    background: #F2F3F4;
}

.status-badge.status-inactive .status-dot {
    background: #95A5A6;
}

.status-badge.status-suspended {
    color: #B7950B;
    background: #FEF9E7;
}

.status-badge.status-suspended .status-dot {
    background: #F39C12;
}

.status-badge.status-deceased {
    color: #5D6D7E;
    background: #E8E8E8;
}

.status-badge.status-deceased .status-dot {
    background: #34495E;
}

.badge-group {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.month-badge {
    font-size: 11px;
    font-weight: 600;
    padding: 4px 14px;
    border-radius: 20px;
}

.badge-paid {
    color: #1E8449;
    background: #D5F5E3;
}

.badge-unpaid {
    color: #C0392B;
    background: #FDEDEC;
}

.card-body-member {
    padding: 24px 20px 20px;
    text-align: center;
}

.member-avatar-wrapper {
    position: relative;
    display: inline-block;
    margin-bottom: 14px;
}

.avatar-ring {
    position: absolute;
    top: -6px;
    left: -6px;
    right: -6px;
    bottom: -6px;
    border-radius: 50%;
    background: conic-gradient(
        from 0deg,
        #FF6B6B,
        #4ECDC4,
        #45B7D1,
        #96CEB4,
        #FFEAA7,
        #DDA0DD,
        #F7DC6F,
        #BB8FCE,
        #85C1E9,
        #FF6B6B
    );
    z-index: 0;
    animation: spin-ring 3s linear infinite;
}

@keyframes spin-ring {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.avatar-ring::after {
    content: '';
    position: absolute;
    top: 2px;
    left: 2px;
    right: 2px;
    bottom: 2px;
    border-radius: 50%;
    background: white;
}

.member-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid white;
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    position: relative;
    z-index: 1;
    background: white;
}

.member-id-badge {
    position: absolute;
    bottom: -4px;
    left: 50%;
    transform: translateX(-50%);
    background: linear-gradient(135deg, #1B4F72, #2E86C1);
    color: white;
    font-size: 10px;
    padding: 3px 14px;
    border-radius: 14px;
    font-weight: 600;
    white-space: nowrap;
    box-shadow: 0 2px 8px rgba(27, 79, 114, 0.3);
    z-index: 2;
}

.member-name {
    font-size: 19px;
    font-weight: 700;
    color: #2C3E50;
    margin: 10px 0 12px;
    letter-spacing: 0.3px;
    word-break: break-word;
}

.member-details {
    text-align: left;
    margin: 12px 0 18px;
    padding: 14px 16px;
    background: linear-gradient(135deg, #F8F9FA, #F0F2F5);
    border-radius: 12px;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 5px 0;
    font-size: 13px;
    color: #444;
    word-break: break-word;
}

.detail-item .detail-icon {
    width: 16px;
    height: 16px;
    color: #2E86C1;
    flex-shrink: 0;
}

.member-stats {
    display: flex;
    justify-content: space-around;
    align-items: center;
    padding: 14px 0;
    border-top: 2px solid #f0f0f0;
    border-bottom: 2px solid #f0f0f0;
    margin-bottom: 12px;
    flex-wrap: wrap;
}

.stat-item {
    text-align: center;
    flex: 1;
    min-width: 60px;
}

.stat-item.stat-balance {
    flex: 1.2;
}

.stat-value {
    display: block;
    font-size: 18px;
    font-weight: 700;
    color: #2C3E50;
}

.stat-value.amount-positive {
    color: #27AE60;
}

.stat-value.amount-negative {
    color: #E74C3C;
}

.stat-item.stat-balance .stat-value {
    font-size: 22px;
    font-weight: 800;
}

.stat-label {
    font-size: 11px;
    color: #888;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-divider {
    width: 1px;
    height: 32px;
    background: #e8ecf1;
    flex-shrink: 0;
}

.due-info {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    background: #FDEDEC;
    border-radius: 10px;
    margin-bottom: 16px;
    border: 1px solid #FADBD8;
    flex-wrap: wrap;
}

.due-info.due-clear {
    background: #D5F5E3;
    border-color: #A9DFBF;
    color: #1E8449;
}

.due-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: #C0392B;
}

.due-item i {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
}

.due-item.due-amount strong {
    font-size: 15px;
}

.due-item.due-months {
    color: #D35400;
}

.due-item.due-oldest {
    color: #6C3483;
}

.due-divider {
    width: 1px;
    height: 20px;
    background: #FADBD8;
    flex-shrink: 0;
}

.due-info.due-clear i {
    color: #27AE60;
}

.card-actions {
    display: flex;
    gap: 6px;
    justify-content: center;
    flex-wrap: wrap;
}

.btn-action {
    padding: 8px 12px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.3s ease;
    text-decoration: none;
    background: #f0f0f0;
    color: #555;
    min-width: 36px;
}

.btn-action:hover {
    transform: translateY(-2px) scale(1.02);
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
}

.btn-action i {
    width: 16px;
    height: 16px;
}

.btn-view {
    background: #E8F0FE;
    color: #1B4F72;
    flex: 1;
    justify-content: center;
    min-width: 60px;
}

.btn-view:hover {
    background: #D4E4FD;
}

.btn-edit {
    background: #FEF9E7;
    color: #D4AC0D;
    min-width: 40px;
}

.btn-edit:hover {
    background: #FDEBD0;
}

.btn-photo {
    background: #E8F8F5;
    color: #1ABC9C;
    min-width: 40px;
}

.btn-photo:hover {
    background: #D1F2EB;
}

.btn-password {
    background: #F4ECF7;
    color: #8E44AD;
    min-width: 40px;
}

.btn-password:hover {
    background: #EBDEF0;
}

.btn-delete {
    background: #FDEDEC;
    color: #C0392B;
    min-width: 40px;
}

.btn-delete:hover {
    background: #FADBD8;
}

.btn-toggle {
    background: #EAFAF1;
    color: #1E8449;
    min-width: 40px;
}

.btn-toggle:hover {
    background: #D5F5E3;
}

/* ===== MODAL STYLES ===== */
.modal-header-view {
    background: linear-gradient(135deg, #1B4F72, #2E86C1);
    color: white;
}
.modal-header-view .btn-close { filter: brightness(0) invert(1); }

.modal-header-edit {
    background: linear-gradient(135deg, #F39C12, #F1C40F);
    color: white;
}
.modal-header-edit .btn-close { filter: brightness(0) invert(1); }

.modal-header-delete {
    background: linear-gradient(135deg, #E74C3C, #C0392B);
    color: white;
}
.modal-header-delete .btn-close { filter: brightness(0) invert(1); }

.modal-header-photo {
    background: linear-gradient(135deg, #1ABC9C, #16A085);
    color: white;
}
.modal-header-photo .btn-close { filter: brightness(0) invert(1); }

.modal-header-password {
    background: linear-gradient(135deg, #8E44AD, #9B59B6);
    color: white;
}
.modal-header-password .btn-close { filter: brightness(0) invert(1); }

.modal-id {
    font-size: 14px;
    font-weight: 400;
    color: rgba(255,255,255,0.8);
    margin-left: 8px;
}

.modal-profile {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 20px;
    background: #F8F9FA;
    border-radius: 12px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.modal-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #ddd;
}

.modal-profile-info h4 {
    margin: 0 0 6px 0;
    font-weight: 700;
    color: #2C3E50;
}

.modal-contact {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    margin-top: 8px;
}

.modal-contact span {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
    color: #555;
}

.modal-contact span i {
    width: 16px;
    height: 16px;
    color: #2E86C1;
}

.modal-details-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.detail-section {
    background: #F8F9FA;
    border-radius: 12px;
    padding: 16px 18px;
}

.detail-section.full-width {
    grid-column: 1 / -1;
}

.section-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 700;
    color: #2C3E50;
    margin-bottom: 12px;
    border-bottom: 2px solid #e8ecf1;
    padding-bottom: 8px;
}

.section-title .section-icon {
    width: 18px;
    height: 18px;
    color: #2E86C1;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 0;
    border-bottom: 1px solid #f0f0f0;
    gap: 10px;
    flex-wrap: wrap;
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-row .label {
    font-size: 13px;
    color: #888;
    font-weight: 500;
    flex-shrink: 0;
}

.detail-row .value {
    font-size: 14px;
    color: #2C3E50;
    font-weight: 500;
    text-align: right;
    word-break: break-word;
}

.detail-row .value.note-text {
    text-align: left;
    font-weight: 400;
    color: #555;
}

.modal-financial {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    background: linear-gradient(135deg, #F8F9FA, #E8F0FE);
    border-radius: 12px;
    margin-top: 20px;
    flex-wrap: wrap;
    gap: 10px;
}

.fin-item {
    text-align: center;
    flex: 1;
    min-width: 80px;
}

.fin-label {
    display: block;
    font-size: 11px;
    color: #888;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}

.fin-value {
    font-size: 16px;
    font-weight: 700;
}

.fin-value.amount-positive {
    color: #27AE60;
}

.fin-value.amount-negative {
    color: #E74C3C;
}

.fin-divider {
    width: 1px;
    height: 30px;
    background: #d0d7e2;
    flex-shrink: 0;
}

.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 20px;
}

.empty-icon-wrapper {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: #F0F2F5;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
}

.empty-icon {
    width: 40px;
    height: 40px;
    color: #999;
}

.empty-state h5 {
    color: #2C3E50;
    font-weight: 700;
    margin-bottom: 8px;
}

.empty-state p {
    color: #888;
}

.pagination-wrapper {
    display: flex;
    justify-content: center;
    padding: 10px 0;
}

.edit-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.edit-form-grid .form-group {
    margin-bottom: 0;
}

.edit-form-grid .form-group.full-width {
    grid-column: 1 / -1;
}

.edit-form-grid .form-group label {
    font-size: 13px;
    font-weight: 600;
    color: #444;
    margin-bottom: 4px;
    display: block;
}

.edit-form-grid .form-group .form-control {
    padding: 8px 12px;
    border: 2px solid #e8ecf1;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s;
    width: 100%;
}

.edit-form-grid .form-group .form-control:focus {
    border-color: #2E86C1;
    outline: none;
    box-shadow: 0 0 0 4px rgba(46, 134, 193, 0.1);
}

.password-icon-wrapper {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: #F4ECF7;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
}

.password-icon {
    width: 36px;
    height: 36px;
    color: #8E44AD;
}

.password-title {
    font-size: 18px;
    font-weight: 700;
    color: #2C3E50;
    margin-bottom: 8px;
}

.password-text {
    color: #555;
    margin-bottom: 12px;
}

.delete-icon-wrapper {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: #FDEDEC;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
}

.delete-icon {
    width: 36px;
    height: 36px;
    color: #E74C3C;
}

.delete-title {
    font-size: 18px;
    font-weight: 700;
    color: #2C3E50;
    margin-bottom: 8px;
}

.delete-text {
    color: #555;
    margin-bottom: 12px;
}

.delete-warning {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    background: #FDEDEC;
    border-radius: 8px;
    color: #C0392B;
    font-size: 13px;
    margin-top: 12px;
}

.delete-warning i {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
}

/* Responsive */
@media (max-width: 992px) {
    .member-cards-container { padding: 16px; }
    .cards-header { padding: 14px 18px; }
    .header-left h4 { font-size: 19px; }
    .cards-grid { grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 18px; }
    .modal-details-grid { grid-template-columns: 1fr; }
    .detail-section.full-width { grid-column: 1; }
    .edit-form-grid { grid-template-columns: 1fr; }
    .edit-form-grid .form-group.full-width { grid-column: 1; }
}

@media (max-width: 768px) {
    .member-cards-container { padding: 12px; }
    .cards-header { flex-direction: column; align-items: stretch; gap: 12px; padding: 14px 16px; }
    .header-left { gap: 12px; }
    .header-icon-wrapper { width: 40px; height: 40px; }
    .header-icon-wrapper .header-icon { width: 20px; height: 20px; }
    .header-left h4 { font-size: 17px; }
    .btn-add { width: 100%; justify-content: center; padding: 10px; font-size: 14px; }
    .filters-bar { padding: 12px 14px; }
    .filter-group { flex-direction: column; align-items: stretch; gap: 10px; }
    .filter-group .search-box { min-width: auto; }
    .filter-group select { width: 100%; }
    .btn-search, .btn-reset { padding: 10px; width: 100%; }
    .cards-grid { grid-template-columns: 1fr; gap: 16px; }
    .member-card { border-radius: 16px; }
    .card-header-status { padding: 10px 14px; flex-direction: column; align-items: flex-start; gap: 6px; }
    .badge-group { width: 100%; justify-content: flex-start; }
    .card-body-member { padding: 16px 14px 14px; }
    .member-avatar { width: 64px; height: 64px; }
    .member-name { font-size: 17px; }
    .member-details { padding: 10px 12px; }
    .member-stats { flex-wrap: wrap; }
    .stat-value { font-size: 16px; }
    .stat-item.stat-balance .stat-value { font-size: 20px; }
    .modal-financial { flex-direction: column; gap: 8px; padding: 14px; }
    .fin-divider { width: 80%; height: 1px; margin: 0 auto; }
    .fin-item { width: 100%; }
    .modal-dialog { margin: 10px; }
    .modal-body { padding: 16px; }
    .modal-profile { flex-direction: column; align-items: center; text-align: center; padding: 16px; gap: 12px; }
    .modal-profile-info { text-align: center; }
    .modal-contact { justify-content: center; }
    .detail-section { padding: 12px 14px; }
    .edit-form-grid { gap: 12px; }
}

@media (max-width: 480px) {
    .member-cards-container { padding: 8px; }
    .cards-header { padding: 12px 14px; border-radius: 12px; }
    .header-left h4 { font-size: 15px; }
    .member-count { font-size: 12px; }
    .cards-grid { gap: 12px; }
    .member-avatar { width: 56px; height: 56px; }
    .member-name { font-size: 15px; }
    .stat-value { font-size: 14px; }
    .stat-item.stat-balance .stat-value { font-size: 18px; }
    .stat-label { font-size: 9px; }
    .stat-divider { height: 24px; }
    .btn-action { padding: 6px 8px; font-size: 11px; min-width: 30px; }
    .btn-action i { width: 14px; height: 14px; }
    .btn-view { font-size: 11px; padding: 6px 8px; min-width: 40px; flex: 0 1 auto; }
    .modal-footer .btn { width: 100%; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>