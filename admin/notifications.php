<?php
// =====================================================
// admin/notifications.php - Advanced Notification System (FULLY FIXED)
// =====================================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
requireAdmin();

$pageTitle = 'নোটিফিকেশন সিস্টেম';
$conn = conn();

// ============================================
// HANDLE POST REQUESTS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'নিরাপত্তা যাচাই ব্যর্থ।');
        header('Location: notifications.php');
        exit;
    }
    
    $action = $_POST['action'] ?? '';

    // ============================================
    // MARK SINGLE AS READ
    // ============================================
    if ($action === 'mark_read_single') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $conn->query("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = $id");
            setFlash('success', 'নোটিফিকেশন পড়া হিসেবে চিহ্নিত হয়েছে।');
        }
        header('Location: notifications.php');
        exit;
    }

    // ============================================
    // MARK ALL AS READ
    // ============================================
    if ($action === 'mark_all_read') {
        $conn->query("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE is_read = 0");
        setFlash('success', 'সব নোটিফিকেশন পড়া হিসেবে চিহ্নিত হয়েছে।');
        header('Location: notifications.php');
        exit;
    }

    // ============================================
    // DELETE SINGLE NOTIFICATION
    // ============================================
    if ($action === 'delete_single') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $conn->query("DELETE FROM notifications WHERE id = $id");
            setFlash('success', 'নোটিফিকেশন ডিলিট করা হয়েছে।');
        }
        header('Location: notifications.php');
        exit;
    }

    // ============================================
    // DELETE ALL NOTIFICATIONS
    // ============================================
    if ($action === 'delete_all') {
        $conn->query("DELETE FROM notifications");
        setFlash('success', 'সব নোটিফিকেশন ডিলিট করা হয়েছে।');
        header('Location: notifications.php');
        exit;
    }

    // ============================================
    // SEND NOTIFICATION
    // ============================================
    if ($action === 'send') {
        $type = $_POST['type'] ?? 'general';
        $title = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $target = $_POST['target'] ?? 'all';
        $member_id = $target === 'member' ? (int)($_POST['member_id'] ?? 0) : 0;
        $member_name = '';
        
        // Get member name if target is specific member
        if ($target === 'member' && $member_id > 0) {
            $member = queryOne("SELECT name FROM members WHERE id = ?", 'i', [$member_id]);
            if ($member) {
                $member_name = $member['name'];
            }
        }
        
        $send_email = isset($_POST['send_email']) ? 1 : 0;
        $send_sms = isset($_POST['send_sms']) ? 1 : 0;
        $priority = $_POST['priority'] ?? 'normal';
        $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
        
        if (!$title || !$message) {
            setFlash('danger', 'শিরোনাম ও বার্তা আবশ্যক।');
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO notifications (type, title, message, target, member_id, member_name, priority, 
                 expiry_date, send_email, send_sms, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->bind_param('ssssissssi', 
                $type, $title, $message, $target, $member_id, $member_name,
                $priority, $expiry_date, $send_email, $send_sms
            );
            
            if ($stmt->execute()) {
                $notif_id = $conn->insert_id;
                
                // Log activity
                if ($target === 'member' && $member_name) {
                    logActivity('notification_send', "নোটিফিকেশন পাঠানো হয়েছে: $title থেকে সদস্য $member_name");
                } elseif ($target === 'all') {
                    logActivity('notification_send', "নোটিফিকেশন পাঠানো হয়েছে: $title থেকে সব সদস্য");
                } elseif ($target === 'admin') {
                    logActivity('notification_send', "নোটিফিকেশন পাঠানো হয়েছে: $title থেকে অ্যাডমিন");
                }
                
                if ($target === 'member' && $member_name) {
                    setFlash('success', "নোটিফিকেশন সফলভাবে পাঠানো হয়েছে! সদস্য: $member_name");
                } else {
                    setFlash('success', 'নোটিফিকেশন সফলভাবে পাঠানো হয়েছে!');
                }
            } else {
                setFlash('danger', 'নোটিফিকেশন পাঠাতে ব্যর্থ হয়েছে: ' . $conn->error);
            }
            header('Location: notifications.php');
            exit;
        }
    }
}

// ============================================
// GET STATISTICS
// ============================================
$totalNotifs = $conn->query("SELECT COUNT(*) as count FROM notifications")->fetch_assoc()['count'];
$unreadNotifs = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE is_read = 0")->fetch_assoc()['count'];
$todayNotifs = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['count'];
$memberNotifs = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE target = 'member'")->fetch_assoc()['count'];
$allNotifs = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE target = 'all'")->fetch_assoc()['count'];

// ============================================
// GET NOTIFICATIONS WITH FILTERS & PAGINATION
// ============================================
$filter = $_GET['filter'] ?? 'all';
$type_filter = $_GET['type'] ?? '';
$search = trim($_GET['s'] ?? '');

$where = "1=1";
$params = [];
$types = '';

if ($filter === 'unread') {
    $where .= " AND is_read = 0";
}
if ($filter === 'read') {
    $where .= " AND is_read = 1";
}
if (!empty($type_filter)) {
    $where .= " AND type = ?";
    $types .= 's';
    $params[] = $type_filter;
}
if (!empty($search)) {
    $where .= " AND (title LIKE ? OR message LIKE ?)";
    $like = "%$search%";
    $types .= 'ss';
    $params[] = $like;
    $params[] = $like;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;

// Get total count for pagination
$countSql = "SELECT COUNT(*) as cnt FROM notifications WHERE $where";
$countResult = queryOne($countSql, $types, $params);
$totalRecords = (int)($countResult['cnt'] ?? 0);
$totalPages = max(1, ceil($totalRecords / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// Get paginated data
$sql = "SELECT * FROM notifications WHERE $where 
        ORDER BY 
        CASE WHEN is_read = 0 THEN 1 ELSE 2 END, 
        created_at DESC 
        LIMIT $perPage OFFSET $offset";
$notifications = queryAll($sql, $types, $params);

// ============================================
// GET MEMBERS FOR DROPDOWN
// ============================================
$members = queryAll("SELECT id, name, member_id, phone, email FROM members WHERE status='active' ORDER BY name");

require_once __DIR__ . '/../includes/header.php';
?>

<!-- ============================================
     FLASH MESSAGES
     ============================================ -->
<?php $flash = getFlash(); if ($flash): ?>
<div class="container-fluid px-4 mt-2">
    <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
        <i data-feather="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-circle' ?>" 
           style="width:16px;height:16px;vertical-align:middle"></i>
        <?= htmlspecialchars($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
</div>
<?php endif; ?>

<!-- ============================================
     STATISTICS CARDS
     ============================================ -->
<div class="container-fluid px-4">
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small">মোট নোটিফিকেশন</div>
                            <h4 class="mb-0"><?= $totalNotifs ?></h4>
                        </div>
                        <div class="bg-primary bg-opacity-10 p-2 rounded">
                            <i data-feather="bell" style="width:20px;height:20px;color:#2E86C1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small">অপঠিত</div>
                            <h4 class="mb-0 text-danger"><?= $unreadNotifs ?></h4>
                        </div>
                        <div class="bg-danger bg-opacity-10 p-2 rounded">
                            <i data-feather="mail" style="width:20px;height:20px;color:#dc3545"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small">আজকের</div>
                            <h4 class="mb-0 text-success"><?= $todayNotifs ?></h4>
                        </div>
                        <div class="bg-success bg-opacity-10 p-2 rounded">
                            <i data-feather="calendar" style="width:20px;height:20px;color:#27AE60"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small">সদস্য নোটিফিকেশন</div>
                            <h4 class="mb-0 text-info"><?= $memberNotifs ?></h4>
                        </div>
                        <div class="bg-info bg-opacity-10 p-2 rounded">
                            <i data-feather="users" style="width:20px;height:20px;color:#2980B9"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================
         MAIN CONTENT
         ============================================ -->
    <div class="row g-3">

        <!-- ============================================
             LEFT: SEND NOTIFICATION FORM
             ============================================ -->
        <div class="col-12 col-lg-4">
            <div class="card sticky-top" style="top: 80px; z-index: 100;">
                <div class="card-header">
                    <span class="card-title">
                        <i data-feather="send" style="width:16px;height:16px"></i>
                        নতুন নোটিফিকেশন পাঠান
                    </span>
                </div>
                <div class="card-body" style="max-height: calc(100vh - 200px); overflow-y: auto;">
                    <form method="POST" id="notificationForm">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="send">

                        <div class="mb-3">
                            <label class="form-label">নোটিফিকেশন টাইপ</label>
                            <select name="type" class="form-select">
                                <option value="general">সাধারণ</option>
                                <option value="announcement">ঘোষণা</option>
                                <option value="deposit">সঞ্চয়</option>
                                <option value="withdrawal">উত্তোলন</option>
                                <option value="loan_installment">কিস্তি স্মারক</option>
                                <option value="due_reminder">বকেয়া স্মারক</option>
                                <option value="profit_distribution">লাভ বণ্টন</option>
                                <option value="urgent">জরুরী</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">প্রেরণ করুন <span class="text-danger">*</span></label>
                            <select name="target" id="targetSel" class="form-select" onchange="toggleTarget(this.value)">
                                <option value="all">সব সদস্য</option>
                                <option value="admin">শুধু অ্যাডমিন</option>
                                <option value="member">নির্দিষ্ট সদস্য</option>
                            </select>
                        </div>

                        <div class="mb-3" id="memberSelDiv" style="display:none">
                            <label class="form-label">সদস্য নির্বাচন <span class="text-danger">*</span></label>
                            <select name="member_id" id="memberSelect" class="form-control select2-member">
                                <option value="">-- সদস্য নির্বাচন করুন --</option>
                                <?php foreach ($members as $m): ?>
                                <option value="<?= $m['id'] ?>" 
                                        data-name="<?= clean($m['name']) ?>"
                                        data-member-id="<?= $m['member_id'] ?>"
                                        data-phone="<?= $m['phone'] ?>">
                                    <?= clean($m['name']) ?> (<?= $m['member_id'] ?>) - <?= $m['phone'] ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">নাম, আইডি বা ফোন দিয়ে খুঁজুন</small>
                        </div>

                        <!-- Selected Member Info Display -->
                        <div id="selectedMemberInfo" class="mb-3" style="display:none">
                            <div class="alert alert-info small">
                                <i data-feather="user" style="width:14px;height:14px"></i>
                                <strong>নির্বাচিত সদস্য:</strong>
                                <span id="selectedMemberName"></span> 
                                (ID: <span id="selectedMemberId"></span>)
                                <br><small>ফোন: <span id="selectedMemberPhone"></span></small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">অগ্রাধিকার</label>
                            <select name="priority" class="form-select">
                                <option value="low">নিম্ন</option>
                                <option value="normal" selected>সাধারণ</option>
                                <option value="high">উচ্চ</option>
                                <option value="urgent">জরুরী</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">শিরোনাম <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required 
                                   placeholder="নোটিফিকেশনের শিরোনাম">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">বার্তা <span class="text-danger">*</span></label>
                            <textarea name="message" class="form-control" rows="3" required 
                                      placeholder="বিস্তারিত বার্তা"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">মেয়াদ শেষের তারিখ (ঐচ্ছিক)</label>
                            <input type="datetime-local" name="expiry_date" class="form-control datetime-input" 
                                   value="<?= date('Y-m-d\TH:i', strtotime('+1 month')) ?>">
                            <small class="text-muted">খালি রাখলে মেয়াদ শেষ হবে না</small>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="send_email" class="form-check-input" id="sendEmail">
                                <label class="form-check-label" for="sendEmail">
                                    <i data-feather="mail" style="width:14px;height:14px"></i> ইমেইলেও পাঠান
                                </label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" name="send_sms" class="form-check-input" id="sendSms">
                                <label class="form-check-label" for="sendSms">
                                    <i data-feather="message-circle" style="width:14px;height:14px"></i> এসএমএসেও পাঠান
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="alert alert-info small">
                                <i data-feather="info" style="width:14px;height:14px"></i>
                                <strong>সদস্যরা দেখতে পাবেন:</strong> নোটিফিকেশন ড্যাশবোর্ডে প্রদর্শিত হবে
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i data-feather="send" style="width:14px;height:14px" class="me-1"></i>
                            নোটিফিকেশন পাঠান
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- ============================================
             RIGHT: NOTIFICATION LIST
             ============================================ -->
        <div class="col-12 col-lg-8">
            <div class="card">
                <div class="card-header">
                    <span class="card-title">
                        <i data-feather="list" style="width:16px;height:16px"></i>
                        সকল নোটিফিকেশন
                    </span>
                    <div class="d-flex gap-2 flex-wrap">
                        <!-- Mark All Read Button -->
                        <form method="POST" style="display:inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="mark_all_read">
                            <button class="btn btn-outline-success btn-sm" title="সব পড়া হিসেবে চিহ্নিত">
                                <i data-feather="check-circle" style="width:14px;height:14px"></i>
                                <span class="d-none d-sm-inline">সব পড়া</span>
                            </button>
                        </form>
                        <!-- Delete All Button -->
                        <button class="btn btn-outline-danger btn-sm" onclick="confirmDeleteAll()" title="সব ডিলিট">
                            <i data-feather="trash-2" style="width:14px;height:14px"></i>
                            <span class="d-none d-sm-inline">সব ডিলিট</span>
                        </button>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card-body border-bottom pb-2">
                    <form method="GET" class="row g-2">
                        <div class="col-12 col-md-4">
                            <div class="search-box">
                                <i data-feather="search" class="search-icon"></i>
                                <input type="text" name="s" class="form-control form-control-sm" 
                                       placeholder="খুঁজুন..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                        </div>
                        <div class="col-4 col-md-3">
                            <select name="filter" class="form-select form-select-sm">
                                <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>সব</option>
                                <option value="unread" <?= $filter === 'unread' ? 'selected' : '' ?>>অপঠিত</option>
                                <option value="read" <?= $filter === 'read' ? 'selected' : '' ?>>পঠিত</option>
                            </select>
                        </div>
                        <div class="col-4 col-md-3">
                            <select name="type" class="form-select form-select-sm">
                                <option value="">সব ধরন</option>
                                <option value="general" <?= $type_filter === 'general' ? 'selected' : '' ?>>সাধারণ</option>
                                <option value="announcement" <?= $type_filter === 'announcement' ? 'selected' : '' ?>>ঘোষণা</option>
                                <option value="deposit" <?= $type_filter === 'deposit' ? 'selected' : '' ?>>সঞ্চয়</option>
                                <option value="withdrawal" <?= $type_filter === 'withdrawal' ? 'selected' : '' ?>>উত্তোলন</option>
                                <option value="loan_installment" <?= $type_filter === 'loan_installment' ? 'selected' : '' ?>>কিস্তি</option>
                                <option value="due_reminder" <?= $type_filter === 'due_reminder' ? 'selected' : '' ?>>বকেয়া</option>
                                <option value="profit_distribution" <?= $type_filter === 'profit_distribution' ? 'selected' : '' ?>>লাভ</option>
                                <option value="urgent" <?= $type_filter === 'urgent' ? 'selected' : '' ?>>জরুরী</option>
                            </select>
                        </div>
                        <div class="col-4 col-md-2">
                            <button class="btn btn-primary btn-sm w-100">ফিল্টার</button>
                        </div>
                    </form>
                </div>

                <!-- Notification List -->
                <div class="list-group list-group-flush">
                    <?php if (empty($notifications)): ?>
                    <div class="list-group-item text-center text-muted py-4">
                        <i data-feather="inbox" style="width:32px;height:32px;display:block;margin:0 auto 10px"></i>
                        কোন নোটিফিকেশন নেই
                    </div>
                    <?php else: ?>
                    <?php foreach ($notifications as $n): 
                        $typeIcons = [
                            'general' => 'bell',
                            'announcement' => 'megaphone',
                            'deposit' => 'arrow-down-circle',
                            'withdrawal' => 'arrow-up-circle',
                            'loan_installment' => 'dollar-sign',
                            'due_reminder' => 'calendar',
                            'profit_distribution' => 'pie-chart',
                            'urgent' => 'alert-circle'
                        ];
                        $icon = $typeIcons[$n['type']] ?? 'bell';
                        
                        $typeColors = [
                            'general' => '#2E86C1',
                            'announcement' => '#8E44AD',
                            'deposit' => '#27AE60',
                            'withdrawal' => '#E74C3C',
                            'loan_installment' => '#F39C12',
                            'due_reminder' => '#E67E22',
                            'profit_distribution' => '#1ABC9C',
                            'urgent' => '#C0392B'
                        ];
                        $color = $typeColors[$n['type']] ?? '#2E86C1';
                        
                        // ============================================
                        // GET MEMBER NAME
                        // ============================================
                        $displayName = '';
                        if (!empty($n['member_id'])) {
                            if (!empty($n['member_name'])) {
                                $displayName = $n['member_name'];
                            } else {
                                $memberInfo = queryOne("SELECT name FROM members WHERE id = ?", 'i', [$n['member_id']]);
                                if ($memberInfo) {
                                    $displayName = $memberInfo['name'];
                                }
                            }
                        }
                        
                        // Fix message: Replace "সদস্য X" with actual name
                        $message = $n['message'];
                        if (!empty($displayName)) {
                            $message = preg_replace('/সদস্য\s+\d+/', $displayName, $message);
                            $message = preg_replace('/Member\s+\d+/', $displayName, $message);
                        }
                        
                        // ============================================
                        // MEMBER BUTTON COLOR BASED ON TYPE
                        // ============================================
                        $btnClass = 'btn-member';
                        if ($n['type'] === 'deposit') {
                            $btnClass = 'btn-member btn-member-deposit';
                        } elseif ($n['type'] === 'withdrawal') {
                            $btnClass = 'btn-member btn-member-withdrawal';
                        } elseif ($n['type'] === 'loan_installment') {
                            $btnClass = 'btn-member btn-member-loan';
                        } elseif ($n['type'] === 'profit_distribution') {
                            $btnClass = 'btn-member btn-member-profit';
                        } elseif ($n['type'] === 'urgent') {
                            $btnClass = 'btn-member btn-member-urgent';
                        }
                    ?>
                    <div class="list-group-item px-3 py-2 <?= !$n['is_read'] ? 'bg-light' : '' ?>" style="<?= !$n['is_read'] ? 'border-left: 4px solid #dc3545;' : '' ?>">
                        <div class="d-flex align-items-start gap-2">
                            <!-- Icon -->
                            <div class="mt-1 flex-shrink-0">
                                <i data-feather="<?= $icon ?>" style="width:18px;height:18px;color:<?= $color ?>"></i>
                            </div>
                            
                            <div style="flex:1;min-width:0">
                                <!-- Title & Time -->
                                <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                                    <div>
                                        <span style="font-size:13px;font-weight:<?= !$n['is_read'] ? '600' : '400' ?>;<?= !$n['is_read'] ? 'color:#dc3545;' : '' ?>">
                                            <?= clean($n['title']) ?>
                                        </span>
                                        <?php if ($n['priority'] === 'urgent'): ?>
                                        <span class="badge bg-danger ms-1" style="font-size:9px">জরুরী</span>
                                        <?php elseif ($n['priority'] === 'high'): ?>
                                        <span class="badge bg-warning ms-1" style="font-size:9px">উচ্চ</span>
                                        <?php endif; ?>
                                        <?php if ($n['expiry_date'] && strtotime($n['expiry_date']) < time()): ?>
                                        <span class="badge bg-danger ms-1" style="font-size:9px">মেয়াদ শেষ</span>
                                        <?php endif; ?>
                                        <?php if (!$n['is_read']): ?>
                                        <span class="badge bg-danger ms-1" style="font-size:9px">অপঠিত</span>
                                        <?php endif; ?>
                                    </div>
                                    <span style="font-size:11px;color:#888;white-space:nowrap">
                                        <?= timeAgo($n['created_at']) ?>
                                        <?php if ($n['expiry_date']): ?>
                                        <br><small>⏰ <?= date('d M Y', strtotime($n['expiry_date'])) ?></small>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                
                                <!-- Message -->
                                <div style="font-size:12px;color:#666;margin-top:2px">
                                    <?= clean($message) ?>
                                </div>
                                
                                <!-- Tags & Member Name Button Style -->
                                <div class="d-flex gap-2 mt-1 flex-wrap align-items-center">
                                    <!-- ============================================
                                         MEMBER NAME AS BUTTON WITH ICON
                                         ============================================ -->
                                    <?php if (!empty($displayName)): ?>
                                    <span class="<?= $btnClass ?>">
                                        <i data-feather="user" style="width:12px;height:12px;"></i>
                                        <?= clean($displayName) ?>
                                    </span>
                                    <?php endif; ?>
                                    
                                    <span class="badge bg-light text-dark" style="font-size:10px;padding:4px 8px;border-radius:12px;">
                                        <?php 
                                        $targetLabels = [
                                            'all' => 'সব সদস্য',
                                            'member' => 'নির্দিষ্ট সদস্য',
                                            'admin' => 'অ্যাডমিন'
                                        ];
                                        echo $targetLabels[$n['target']] ?? $n['target'];
                                        ?>
                                    </span>
                                    
                                    <span class="badge bg-light text-dark" style="font-size:10px;padding:4px 8px;border-radius:12px;">
                                        <?= $n['type'] ?>
                                    </span>
                                    
                                    <?php if ($n['send_email']): ?>
                                    <span class="badge bg-info" style="font-size:9px;padding:3px 6px;border-radius:10px;">📧</span>
                                    <?php endif; ?>
                                    
                                    <?php if ($n['send_sms']): ?>
                                    <span class="badge bg-success" style="font-size:9px;padding:3px 6px;border-radius:10px;">📱</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="btn-group btn-group-sm flex-shrink-0">
                                <?php if (!$n['is_read']): ?>
                                <form method="POST" style="display:inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="mark_read_single">
                                    <input type="hidden" name="id" value="<?= $n['id'] ?>">
                                    <button class="btn btn-outline-success" title="পঠিত হিসেবে চিহ্নিত">
                                        <i data-feather="check" style="width:14px;height:14px"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <button class="btn btn-outline-danger" onclick="deleteNotification(<?= $n['id'] ?>)" title="ডিলিট">
                                    <i data-feather="trash-2" style="width:14px;height:14px"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <div class="card-body pt-2">
                    <?php if ($totalPages > 1): ?>
                    <nav>
                        <ul class="pagination pagination-sm justify-content-center flex-wrap">
                            <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page-1 ?>&filter=<?= $filter ?>&type=<?= $type_filter ?>&s=<?= urlencode($search) ?>">«</a>
                            </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&filter=<?= $filter ?>&type=<?= $type_filter ?>&s=<?= urlencode($search) ?>"><?= $i ?></a>
                            </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page+1 ?>&filter=<?= $filter ?>&type=<?= $type_filter ?>&s=<?= urlencode($search) ?>">»</a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                    <div class="text-center text-muted small mt-2">
                        মোট <?= $totalRecords ?> টি রেকর্ড (পৃষ্ঠা <?= $page ?> / <?= $totalPages ?>)
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
     DELETE SINGLE CONFIRMATION MODAL
     ============================================ -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">নিশ্চিত করুন</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>আপনি কি এই নোটিফিকেশন ডিলিট করতে চান?</p>
                <form method="POST" id="deleteForm">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete_single">
                    <input type="hidden" name="id" id="deleteId" value="">
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
                <button class="btn btn-danger" onclick="submitDelete()">ডিলিট</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
     DELETE ALL CONFIRMATION MODAL
     ============================================ -->
<div class="modal fade" id="deleteAllModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">সব ডিলিট নিশ্চিত করুন</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-danger"><strong>সতর্কতা:</strong> আপনি কি সব নোটিফিকেশন ডিলিট করতে চান?</p>
                <p class="text-muted small">এই কাজটি পূর্বাবস্থায় ফেরানো যাবে না।</p>
                <form method="POST" id="deleteAllForm">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete_all">
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
                <button class="btn btn-danger" onclick="submitDeleteAll()">সব ডিলিট</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
     JQUERY & SELECT2
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
    padding: 12px 18px;
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

.list-group-item {
    transition: background-color 0.2s;
}
.list-group-item:hover {
    background-color: #f8f9fa;
}

/* Select2 Customization */
.select2-container {
    width: 100% !important;
}
.select2-container--default .select2-selection--single {
    height: 38px !important;
    border: 1px solid #ced4da !important;
    border-radius: 8px !important;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 36px !important;
    padding-left: 12px !important;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 36px !important;
}
.select2-container--default .select2-dropdown {
    border: 1px solid #ced4da !important;
    border-radius: 8px !important;
    z-index: 9999 !important;
}

/* Date/Time input styling */
input[type="datetime-local"] {
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 8px;
    width: 100%;
    font-size: 14px;
    background: white;
    color: #333;
}
input[type="datetime-local"]:focus {
    border-color: #2E86C1;
    outline: none;
    box-shadow: 0 0 0 0.2rem rgba(46, 134, 193, 0.25);
}
input[type="datetime-local"]::-webkit-calendar-picker-indicator {
    padding: 4px;
    cursor: pointer;
}
input[type="datetime-local"]:hover {
    border-color: #2E86C1;
}

@media (max-width: 768px) {
    input[type="datetime-local"] {
        font-size: 16px;
    }
}

@media (max-width: 992px) {
    .sticky-top {
        position: relative;
        top: 0;
    }
    .card-body {
        max-height: none !important;
        overflow-y: visible !important;
    }
}

.pagination {
    margin-bottom: 0;
}
.pagination .page-link {
    padding: 4px 10px;
    font-size: 12px;
}

/* ============================================
   MEMBER NAME BUTTON STYLE
   ============================================ */
.btn-member {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 12px;
    background: linear-gradient(135deg, #1B4F72, #2E86C1);
    color: white;
    font-size: 11px;
    font-weight: 500;
    border-radius: 20px;
    border: none;
    box-shadow: 0 2px 6px rgba(46, 134, 193, 0.3);
    transition: all 0.3s ease;
    cursor: default;
    line-height: 1.8;
}

.btn-member i {
    color: white;
    opacity: 0.9;
}

.btn-member:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(46, 134, 193, 0.4);
    background: linear-gradient(135deg, #154360, #1B4F72);
}

/* Deposit Type */
.btn-member-deposit {
    background: linear-gradient(135deg, #1E8449, #27AE60);
    box-shadow: 0 2px 6px rgba(39, 174, 96, 0.3);
}
.btn-member-deposit:hover {
    background: linear-gradient(135deg, #196F3D, #1E8449);
    box-shadow: 0 4px 12px rgba(39, 174, 96, 0.4);
}

/* Withdrawal Type */
.btn-member-withdrawal {
    background: linear-gradient(135deg, #922B21, #E74C3C);
    box-shadow: 0 2px 6px rgba(231, 76, 60, 0.3);
}
.btn-member-withdrawal:hover {
    background: linear-gradient(135deg, #78281F, #C0392B);
    box-shadow: 0 4px 12px rgba(231, 76, 60, 0.4);
}

/* Loan Type */
.btn-member-loan {
    background: linear-gradient(135deg, #B7950B, #F39C12);
    box-shadow: 0 2px 6px rgba(243, 156, 18, 0.3);
}
.btn-member-loan:hover {
    background: linear-gradient(135deg, #9A7D0A, #D68910);
    box-shadow: 0 4px 12px rgba(243, 156, 18, 0.4);
}

/* Profit Type */
.btn-member-profit {
    background: linear-gradient(135deg, #0E6655, #1ABC9C);
    box-shadow: 0 2px 6px rgba(26, 188, 156, 0.3);
}
.btn-member-profit:hover {
    background: linear-gradient(135deg, #0B5345, #16A085);
    box-shadow: 0 4px 12px rgba(26, 188, 156, 0.4);
}

/* Urgent Type */
.btn-member-urgent {
    background: linear-gradient(135deg, #7B241C, #C0392B);
    box-shadow: 0 2px 6px rgba(192, 57, 43, 0.3);
}
.btn-member-urgent:hover {
    background: linear-gradient(135deg, #641E16, #922B21);
    box-shadow: 0 4px 12px rgba(192, 57, 43, 0.4);
}
</style>

<script>
// ============================================
// TOGGLE MEMBER SELECTION
// ============================================
function toggleTarget(val) {
    var memberSelDiv = document.getElementById('memberSelDiv');
    var memberInfoDiv = document.getElementById('selectedMemberInfo');
    
    if (val === 'member') {
        memberSelDiv.style.display = 'block';
        memberInfoDiv.style.display = 'block';
    } else {
        memberSelDiv.style.display = 'none';
        memberInfoDiv.style.display = 'none';
    }
}

// ============================================
// DELETE SINGLE NOTIFICATION
// ============================================
function deleteNotification(id) {
    $('#deleteId').val(id);
    $('#deleteModal').modal('show');
}

function submitDelete() {
    $('#deleteForm').submit();
}

// ============================================
// DELETE ALL NOTIFICATIONS
// ============================================
function confirmDeleteAll() {
    $('#deleteAllModal').modal('show');
}

function submitDeleteAll() {
    $('#deleteAllForm').submit();
}

// ============================================
// INITIALIZE
// ============================================
$(document).ready(function() {
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
    
    // ============================================
    // Select2 Initialization
    // ============================================
    $('.select2-member').select2({
        placeholder: '-- সদস্য নির্বাচন করুন --',
        allowClear: true,
        width: '100%',
        dropdownCssClass: 'select2-dropdown-custom',
        language: {
            searching: function() { return 'খুঁজছেন...'; },
            noResults: function() { return 'কোনো সদস্য পাওয়া যায়নি'; },
            inputTooShort: function() { return 'অনুগ্রহ করে আরও অক্ষর টাইপ করুন'; }
        },
        templateResult: function(data) {
            if (!data.id) {
                return data.text;
            }
            var $result = $(
                '<div><strong>' + $(data.element).data('name') + '</strong><br>' +
                '<small>ID: ' + $(data.element).data('member-id') + ' | ফোন: ' + $(data.element).data('phone') + '</small></div>'
            );
            return $result;
        },
        templateSelection: function(data) {
            if (!data.id) {
                return data.text;
            }
            return $(data.element).data('name') + ' (' + $(data.element).data('member-id') + ')';
        }
    });
    
    // ============================================
    // Show selected member info
    // ============================================
    $('#memberSelect').on('change', function() {
        var selected = $(this).find('option:selected');
        if (selected.val()) {
            $('#selectedMemberName').text(selected.data('name'));
            $('#selectedMemberId').text(selected.data('member-id'));
            $('#selectedMemberPhone').text(selected.data('phone'));
            $('#selectedMemberInfo').show();
        } else {
            $('#selectedMemberInfo').hide();
        }
    });
    
    // ============================================
    // Auto-submit on filter change
    // ============================================
    $('.form-select-sm').on('change', function() {
        this.closest('form').submit();
    });
    
    // ============================================
    // Initialize datetime-local with default value
    // ============================================
    var datetimeInput = document.querySelector('input[type="datetime-local"]');
    if (datetimeInput && !datetimeInput.value) {
        var now = new Date();
        now.setMonth(now.getMonth() + 1);
        var year = now.getFullYear();
        var month = String(now.getMonth() + 1).padStart(2, '0');
        var day = String(now.getDate()).padStart(2, '0');
        var hours = String(now.getHours()).padStart(2, '0');
        var minutes = String(now.getMinutes()).padStart(2, '0');
        datetimeInput.value = year + '-' + month + '-' + day + 'T' + hours + ':' + minutes;
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>