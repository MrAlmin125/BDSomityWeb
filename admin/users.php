<?php
// =====================================================
// admin/users.php - User Management with Profile Photo
// =====================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/functions.php';
startSecureSession();

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    header('Location: /admin/login.php');
    exit;
}

$pageTitle = 'ইউজার ব্যবস্থাপনা';
$error = '';
$success = '';

// Get filter parameters
$roleFilter = $_GET['role'] ?? 'all';
$searchTerm = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? 'all';

// ============================================
// GET PROFILE PHOTO URL FUNCTION
// ============================================
function getUserProfilePhoto($user) {
    if (!empty($user['profile_photo'])) {
        $photoPath = $user['profile_photo'];
        if (strpos($photoPath, 'http') !== 0) {
            if (strpos($photoPath, '/') !== 0) {
                $photoPath = '/' . $photoPath;
            }
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'];
            return $protocol . $host . $photoPath;
        }
        return $photoPath;
    }
    return null;
}

// ============================================
// AJAX ENDPOINT FOR LIVE ADMIN TRACKING
// ============================================
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_active_admins') {
    header('Content-Type: application/json');
    
    try {
        $conn = conn();
        $adminSessionFile = __DIR__ . '/../tmp/admin_sessions.json';
        $activeAdmins = [];
        
        if (file_exists($adminSessionFile)) {
            $sessions = json_decode(file_get_contents($adminSessionFile), true);
            if (is_array($sessions)) {
                $now = time();
                foreach ($sessions as $key => $session) {
                    if (($now - $session['last_activity']) > 120) {
                        unset($sessions[$key]);
                    }
                }
                
                foreach ($sessions as $session) {
                    $stmt = $conn->prepare("SELECT id, name, email, profile_photo, last_login FROM users WHERE id = ? AND role = 'admin' AND is_active = 1");
                    $stmt->bind_param('i', $session['user_id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $admin = $result->fetch_assoc();
                    if ($admin) {
                        $admin['session_start'] = $session['last_activity'];
                        $admin['is_online'] = true;
                        $admin['photo_url'] = getUserProfilePhoto($admin);
                        $activeAdmins[] = $admin;
                    }
                }
                
                file_put_contents($adminSessionFile, json_encode($sessions));
            }
        }
        
        echo json_encode([
            'success' => true,
            'admins' => $activeAdmins,
            'count' => count($activeAdmins),
            'timestamp' => time()
        ]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit;
    }
}

// Update current admin's session
if (isset($_SESSION['admin_id'])) {
    $adminSessionFile = __DIR__ . '/../tmp/admin_sessions.json';
    $sessions = [];
    
    $tmpDir = __DIR__ . '/../tmp';
    if (!file_exists($tmpDir)) {
        mkdir($tmpDir, 0777, true);
    }
    
    if (file_exists($adminSessionFile)) {
        $sessions = json_decode(file_get_contents($adminSessionFile), true);
        if (!is_array($sessions)) $sessions = [];
    }
    
    $sessions[$_SESSION['admin_id']] = [
        'user_id' => $_SESSION['admin_id'],
        'last_activity' => time()
    ];
    
    $now = time();
    foreach ($sessions as $key => $session) {
        if (($now - $session['last_activity']) > 120) {
            unset($sessions[$key]);
        }
    }
    
    file_put_contents($adminSessionFile, json_encode($sessions));
}

try {
    $conn = conn();
    
    // ============================================
    // GET DATA FROM users TABLE WITH PROFILE PHOTO
    // ============================================
    $sql = "SELECT id, name, email, phone, profile_photo, role, is_active, created_at, last_login 
            FROM users 
            WHERE 1=1";
    
    $params = [];
    $types = "";
    
    if ($roleFilter !== 'all') {
        $sql .= " AND role = ?";
        $types .= 's';
        $params[] = $roleFilter;
    }
    
    if ($statusFilter === 'active') {
        $sql .= " AND is_active = 1";
    } elseif ($statusFilter === 'inactive') {
        $sql .= " AND is_active = 0";
    }
    
    if (!empty($searchTerm)) {
        $sql .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)";
        $types .= 'sss';
        $like = "%$searchTerm%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    $users = [];
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $row['photo_url'] = getUserProfilePhoto($row);
            $users[] = $row;
        }
    } else {
        $result = $conn->query($sql);
        while ($row = $result->fetch_assoc()) {
            $row['photo_url'] = getUserProfilePhoto($row);
            $users[] = $row;
        }
    }
    
    // Get active admins for initial display
    $adminSessionFile = __DIR__ . '/../tmp/admin_sessions.json';
    $activeAdmins = [];
    
    if (file_exists($adminSessionFile)) {
        $sessions = json_decode(file_get_contents($adminSessionFile), true);
        if (is_array($sessions)) {
            $now = time();
            foreach ($sessions as $key => $session) {
                if (($now - $session['last_activity']) > 120) {
                    unset($sessions[$key]);
                }
            }
            
            foreach ($sessions as $session) {
                $stmt = $conn->prepare("SELECT id, name, email, profile_photo, last_login FROM users WHERE id = ? AND role = 'admin' AND is_active = 1");
                $stmt->bind_param('i', $session['user_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $admin = $result->fetch_assoc();
                if ($admin) {
                    $admin['session_start'] = $session['last_activity'];
                    $admin['photo_url'] = getUserProfilePhoto($admin);
                    $activeAdmins[] = $admin;
                }
            }
            
            file_put_contents($adminSessionFile, json_encode($sessions));
        }
    }
    
} catch (Exception $e) {
    $error = 'ডেটা লোড করতে ব্যর্থ হয়েছে: ' . $e->getMessage();
    $users = [];
    $activeAdmins = [];
}

// Get statistics
$totalUsers = count($users);
$activeUsers = 0;
$inactiveUsers = 0;
$adminCount = 0;
$userCount = 0;

foreach ($users as $user) {
    if ($user['is_active'] == 1) $activeUsers++;
    else $inactiveUsers++;
    
    if ($user['role'] === 'admin') $adminCount++;
    elseif ($user['role'] === 'user') $userCount++;
}

// ============================================
// HANDLE ADD, EDIT, DELETE, TOGGLE STATUS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে।';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $role = $_POST['role'] ?? 'user';
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $user_id = (int)($_POST['user_id'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Handle profile photo upload
        $profile_photo = null;
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = uploadUserProfilePhoto($_FILES['profile_photo'], $user_id);
            if ($uploadResult['success']) {
                $profile_photo = $uploadResult['path'];
            } else {
                $error = $uploadResult['message'];
            }
        }
        
        if (empty($name) || empty($email)) {
            $error = 'নাম ও ইমেইল দিন।';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'সঠিক ইমেইল ঠিকানা দিন।';
        } else {
            if ($user_id == 0) {
                if (empty($password)) {
                    $error = 'পাসওয়ার্ড দিন।';
                } elseif (strlen($password) < 6) {
                    $error = 'পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে হবে।';
                } elseif ($password !== $confirm_password) {
                    $error = 'পাসওয়ার্ড এবং কনফার্ম পাসওয়ার্ড মিলছে না।';
                }
            } else {
                if (!empty($password) && $password !== $confirm_password) {
                    $error = 'পাসওয়ার্ড এবং কনফার্ম পাসওয়ার্ড মিলছে না।';
                } elseif (!empty($password) && strlen($password) < 6) {
                    $error = 'পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে হবে।';
                }
            }
            
            if (empty($error)) {
                try {
                    $conn = conn();
                    
                    if ($user_id > 0) {
                        // UPDATE existing user
                        if (!empty($password)) {
                            $hashed = password_hash($password, PASSWORD_DEFAULT);
                            if ($profile_photo) {
                                $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, role = ?, password = ?, profile_photo = ?, is_active = ? WHERE id = ?");
                                $stmt->bind_param('ssssssii', $name, $email, $phone, $role, $hashed, $profile_photo, $is_active, $user_id);
                            } else {
                                $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, role = ?, password = ?, is_active = ? WHERE id = ?");
                                $stmt->bind_param('sssssii', $name, $email, $phone, $role, $hashed, $is_active, $user_id);
                            }
                        } else {
                            if ($profile_photo) {
                                $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, role = ?, profile_photo = ?, is_active = ? WHERE id = ?");
                                $stmt->bind_param('sssssii', $name, $email, $phone, $role, $profile_photo, $is_active, $user_id);
                            } else {
                                $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, role = ?, is_active = ? WHERE id = ?");
                                $stmt->bind_param('ssssii', $name, $email, $phone, $role, $is_active, $user_id);
                            }
                        }
                        $stmt->execute();
                        
                        if ($_SESSION['admin_id'] == $user_id) {
                            $_SESSION['admin_name'] = $name;
                            $_SESSION['admin_role'] = $role;
                            if ($profile_photo) {
                                $_SESSION['admin']['profile_photo'] = $profile_photo;
                            }
                        }
                        
                        logActivity('user_edit', 'ইউজার আপডেট: ' . $name);
                        $success = 'ইউজার সফলভাবে আপডেট হয়েছে।';
                        
                    } else {
                        // ADD new user
                        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                        $stmt->bind_param('s', $email);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $existing = $result->fetch_assoc();
                        
                        if ($existing) {
                            $error = 'এই ইমেইলটি ইতিমধ্যে ব্যবহার করা হচ্ছে।';
                        } else {
                            $hashed = password_hash($password, PASSWORD_DEFAULT);
                            if ($profile_photo) {
                                $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, role, profile_photo, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
                                $stmt->bind_param('ssssssi', $name, $email, $phone, $hashed, $role, $profile_photo, $is_active);
                            } else {
                                $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, role, is_active) VALUES (?, ?, ?, ?, ?, ?)");
                                $stmt->bind_param('sssssi', $name, $email, $phone, $hashed, $role, $is_active);
                            }
                            $stmt->execute();
                            
                            logActivity('user_add', 'নতুন ইউজার যোগ: ' . $name);
                            $success = 'ইউজার সফলভাবে যোগ করা হয়েছে।';
                        }
                    }
                    
                    if ($success) {
                        header('Location: users.php?success=' . urlencode($success));
                        exit;
                    }
                    
                } catch (Exception $e) {
                    $error = 'অপারেশন ব্যর্থ হয়েছে: ' . $e->getMessage();
                }
            }
        }
    }
}

// ============================================
// UPLOAD USER PROFILE PHOTO FUNCTION
// ============================================
function uploadUserProfilePhoto($file, $userId) {
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'ফাইল আপলোড ত্রুটি'];
    }
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        return ['success' => false, 'message' => 'শুধুমাত্র JPEG, PNG, GIF এবং WebP ছবি আপলোড করা যাবে।'];
    }
    
    $maxSize = 2 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'ফাইলের আকার ২MB এর বেশি হতে পারে না।'];
    }
    
    $uploadDir = __DIR__ . '/../uploads/users/';
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            return ['success' => false, 'message' => 'আপলোড ডিরেক্টরি তৈরি করা সম্ভব হয়নি।'];
        }
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'user_' . ($userId > 0 ? $userId : time()) . '_' . time() . '.' . $extension;
    $filePath = $uploadDir . $filename;
    $relativePath = '/uploads/users/' . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        return ['success' => false, 'message' => 'ফাইল আপলোড করতে ব্যর্থ হয়েছে।'];
    }
    
    return ['success' => true, 'path' => $relativePath];
}

// ============================================
// TOGGLE ACTIVE/DEACTIVE
// ============================================
if (isset($_GET['toggle_status']) && $_GET['toggle_status'] > 0) {
    $id = (int)$_GET['toggle_status'];
    $currentStatus = $_GET['current_status'] ?? 0;
    $newStatus = $currentStatus == 1 ? 0 : 1;
    
    if ($id == $_SESSION['admin_id'] && $newStatus == 0) {
        $error = 'আপনি নিজেকে নিষ্ক্রিয় করতে পারবেন না!';
    } else {
        try {
            $conn = conn();
            $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ?");
            $stmt->bind_param('ii', $newStatus, $id);
            $stmt->execute();
            
            $statusText = $newStatus == 1 ? 'সক্রিয়' : 'নিষ্ক্রিয়';
            logActivity('user_status_toggle', "ইউজার {$statusText} করা হয়েছে (ID: {$id})");
            $success = "ইউজার {$statusText} করা হয়েছে।";
            header('Location: users.php?success=' . urlencode($success));
            exit;
        } catch (Exception $e) {
            $error = 'স্ট্যাটাস পরিবর্তন করতে ব্যর্থ হয়েছে।';
        }
    }
}

// ============================================
// DELETE USER
// ============================================
if (isset($_GET['delete']) && $_GET['delete'] > 0) {
    $id = (int)$_GET['delete'];
    
    if ($id == $_SESSION['admin_id']) {
        $error = 'আপনি নিজেকে ডিলিট করতে পারবেন না!';
    } else {
        try {
            $conn = conn();
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                logActivity('user_delete', 'ইউজার ডিলিট করা হয়েছে');
                $success = 'ইউজার ডিলিট করা হয়েছে।';
                header('Location: users.php?success=' . urlencode($success));
                exit;
            }
        } catch (Exception $e) {
            $error = 'ডিলিট করতে ব্যর্থ হয়েছে।';
        }
    }
}

// ============================================
// BULK ACTION
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে।';
    } else {
        $action = $_POST['bulk_action'] ?? '';
        $selectedIds = $_POST['selected_ids'] ?? [];
        
        if (empty($selectedIds)) {
            $error = 'কমপক্ষে একটি ইউজার নির্বাচন করুন।';
        } else {
            try {
                $conn = conn();
                $count = 0;
                
                foreach ($selectedIds as $id) {
                    $id = (int)$id;
                    
                    if ($action === 'activate') {
                        $stmt = $conn->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
                        $stmt->bind_param('i', $id);
                        $stmt->execute();
                        $count++;
                    } elseif ($action === 'deactivate') {
                        if ($id == $_SESSION['admin_id']) continue;
                        $stmt = $conn->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
                        $stmt->bind_param('i', $id);
                        $stmt->execute();
                        $count++;
                    } elseif ($action === 'delete') {
                        if ($id == $_SESSION['admin_id']) continue;
                        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                        $stmt->bind_param('i', $id);
                        $stmt->execute();
                        $count++;
                    }
                }
                
                $actionLabels = [
                    'activate' => 'সক্রিয়',
                    'deactivate' => 'নিষ্ক্রিয়',
                    'delete' => 'ডিলিট'
                ];
                $success = "{$count} জন ইউজার {$actionLabels[$action]} করা হয়েছে।";
                logActivity('user_bulk_action', "বাল্ক {$actionLabels[$action]} করা হয়েছে: {$count} জন");
                header('Location: users.php?success=' . urlencode($success));
                exit;
                
            } catch (Exception $e) {
                $error = 'বাল্ক অপারেশন ব্যর্থ হয়েছে।';
            }
        }
    }
}

// Get single user for edit
$editUser = null;
if (isset($_GET['edit']) && $_GET['edit'] > 0) {
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT id, name, email, phone, profile_photo, role, is_active FROM users WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $editUser = $result->fetch_assoc();
    if ($editUser) {
        $editUser['photo_url'] = getUserProfilePhoto($editUser);
    }
}

// Include header
require_once __DIR__ . '/../includes/header.php';

$successMsg = $_GET['success'] ?? '';
$errorMsg = $_GET['error'] ?? '';
?>

<!-- MODERN UI - MOBILE FIRST WITH PROPER SCROLLING -->
<div class="container-fluid px-2 px-md-4" style="max-height: 100vh; overflow-y: auto; padding-bottom: 80px;">
    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 mt-2 gap-2">
        <h4 class="mb-0 fs-6 fs-md-4">
            <i data-feather="users" style="width:20px;height:20px"></i> 
            ইউজার ব্যবস্থাপনা
            <span class="badge bg-primary ms-1"><?= $totalUsers ?></span>
        </h4>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#userModal" onclick="resetForm()">
            <i data-feather="user-plus" style="width:16px;height:16px"></i> 
            <span class="d-none d-sm-inline">নতুন ইউজার</span>
            <span class="d-inline d-sm-none">যোগ</span>
        </button>
    </div>

    <!-- Statistics -->
    <div class="row g-2 mb-3">
        <div class="col-3 col-md-3">
            <div class="stat-card p-2">
                <div class="stat-value fs-5"><?= toBengaliNumber($totalUsers) ?></div>
                <div class="stat-label small">মোট</div>
            </div>
        </div>
        <div class="col-3 col-md-3">
            <div class="stat-card bg-success-grad p-2">
                <div class="stat-value fs-5"><?= toBengaliNumber($activeUsers) ?></div>
                <div class="stat-label small">সক্রিয়</div>
            </div>
        </div>
        <div class="col-3 col-md-3">
            <div class="stat-card bg-danger-grad p-2">
                <div class="stat-value fs-5"><?= toBengaliNumber($inactiveUsers) ?></div>
                <div class="stat-label small">নিষ্ক্রিয়</div>
            </div>
        </div>
        <div class="col-3 col-md-3">
            <div class="stat-card bg-warning-grad p-2">
                <div class="stat-value fs-5"><?= toBengaliNumber($adminCount) ?></div>
                <div class="stat-label small">অ্যাডমিন</div>
            </div>
        </div>
    </div>

    <!-- Active Admins Section - FIXED: লাইভ text and icon side by side -->
    <div class="card mb-3 border-success">
        <div class="card-header bg-success text-white py-1 py-md-2">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-1">
                <div class="small d-flex align-items-center gap-2">
                    <i data-feather="users" style="width:16px;height:16px"></i>
                    <span>সক্রিয় অ্যাডমিন</span>
                    <span class="badge bg-light text-success ms-1" id="adminCount">0</span>
                    <span class="badge bg-warning text-dark d-inline-flex align-items-center gap-1">
                        <i data-feather="radio" style="width:10px;height:10px" class="pulse-icon"></i>
                        <span>লাইভ</span>
                    </span>
                </div>
                <div class="small">
                    <span class="badge bg-light text-success" id="lastUpdateTime">আপডেট: এখন</span>
                </div>
            </div>
        </div>
        <div class="card-body p-2" id="activeAdminsContainer">
            <div class="row g-2" id="adminCardsRow">
                <?php if (empty($activeAdmins)): ?>
                <div class="col-12 text-center text-muted py-3 small">
                    <i data-feather="user-x" style="width:24px;height:24px;display:block;margin:0 auto 5px"></i>
                    কোনো অ্যাডমিন লাইভ নেই
                </div>
                <?php else: ?>
                <?php foreach ($activeAdmins as $admin): ?>
                <div class="col-4 col-md-3 col-lg-2 admin-card" data-admin-id="<?= $admin['id'] ?>">
                    <div class="active-admin-card p-2 text-center">
                        <div class="admin-status-dot pulse-dot"></div>
                        <?php if (!empty($admin['photo_url'])): ?>
                        <img src="<?= htmlspecialchars($admin['photo_url']) ?>" 
                             alt="<?= clean($admin['name']) ?>" 
                             class="admin-avatar-img"
                             onerror="this.style.display='none'; this.parentElement.querySelector('.admin-avatar-lg').style.display='flex';">
                        <div class="admin-avatar-lg" style="background:<?= getColor($admin['name']) ?>;display:none;">
                            <?= mb_substr($admin['name'], 0, 1) ?>
                        </div>
                        <?php else: ?>
                        <div class="admin-avatar-lg" style="background:<?= getColor($admin['name']) ?>">
                            <?= mb_substr($admin['name'], 0, 1) ?>
                        </div>
                        <?php endif; ?>
                        <div class="admin-info">
                            <div class="admin-name small fw-bold"><?= clean($admin['name']) ?></div>
                            <div class="admin-email small text-muted d-none d-md-block"><?= clean($admin['email']) ?></div>
                            <div class="admin-time small">
                                <i data-feather="clock" style="width:10px;height:10px"></i>
                                <?= date('h:i A', $admin['session_start']) ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Filter & Search -->
    <div class="card mb-3">
        <div class="card-body p-2">
            <form method="GET" class="row g-1">
                <div class="col-4 col-md-2">
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>সব</option>
                        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>সক্রিয়</option>
                        <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>নিষ্ক্রিয়</option>
                    </select>
                </div>
                <div class="col-4 col-md-2">
                    <select name="role" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="all" <?= $roleFilter === 'all' ? 'selected' : '' ?>>সব</option>
                        <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>অ্যাডমিন</option>
                        <option value="user" <?= $roleFilter === 'user' ? 'selected' : '' ?>>ইউজার</option>
                    </select>
                </div>
                <div class="col-8 col-md-5">
                    <input type="text" name="search" class="form-control form-control-sm" 
                        placeholder="খুঁজুন..." 
                        value="<?= htmlspecialchars($searchTerm) ?>">
                </div>
                <div class="col-4 col-md-3">
                    <button type="submit" class="btn btn-primary btn-sm w-100">খুঁজুন</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($error || $errorMsg): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2 small py-2" role="alert">
        <i data-feather="alert-circle" style="width:16px;height:16px;flex-shrink:0"></i>
        <span><?= htmlspecialchars($error ?: $errorMsg) ?></span>
    </div>
    <?php endif; ?>

    <?php if ($success || $successMsg): ?>
    <div class="alert alert-success d-flex align-items-center gap-2 small py-2" role="alert">
        <i data-feather="check-circle" style="width:16px;height:16px;flex-shrink:0"></i>
        <span><?= htmlspecialchars($success ?: $successMsg) ?></span>
    </div>
    <?php endif; ?>

    <!-- Users Table -->
    <div class="card">
        <div class="card-header py-2">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-1">
                <span class="small fw-bold"><i data-feather="list" style="width:14px;height:14px"></i> ইউজার তালিকা</span>
                <div class="d-flex gap-1">
                    <select id="bulkAction" class="form-select form-select-sm" style="width:100px; font-size:11px;">
                        <option value="">অ্যাকশন</option>
                        <option value="activate">সক্রিয়</option>
                        <option value="deactivate">নিষ্ক্রিয়</option>
                        <option value="delete">ডিলিট</option>
                    </select>
                    <button class="btn btn-sm btn-secondary" onclick="applyBulkAction()" style="font-size:11px;">Go</button>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <form method="POST" id="bulkForm">
                <?= csrfField() ?>
                <input type="hidden" name="bulk_action" id="bulkActionInput" value="">
                <table class="table table-hover mb-0" id="usersTable">
                    <thead>
                        <tr>
                            <th width="30">
                                <input type="checkbox" id="selectAll" onchange="toggleAllCheckboxes(this)" style="transform: scale(0.8);">
                            </th>
                            <th>#</th>
                            <th>নাম</th>
                            <th class="d-none d-sm-table-cell">ইমেইল</th>
                            <th class="d-none d-md-table-cell">রোল</th>
                            <th>অবস্থা</th>
                            <th>অ্যাকশন</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-3 small">
                                <i data-feather="users" style="width:24px;height:24px;display:block;margin:0 auto 5px"></i>
                                কোনো ইউজার নেই
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($users as $index => $user): ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="user-checkbox" name="selected_ids[]" value="<?= $user['id'] ?>" style="transform: scale(0.8);">
                            </td>
                            <td class="small"><?= $index + 1 ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-1">
                                    <?php if (!empty($user['photo_url'])): ?>
                                    <img src="<?= htmlspecialchars($user['photo_url']) ?>" 
                                         alt="<?= clean($user['name']) ?>" 
                                         class="user-avatar-img"
                                         onerror="this.style.display='none'; this.parentElement.querySelector('.user-avatar').style.display='flex';">
                                    <div class="user-avatar" style="background:<?= getColor($user['name']) ?>;display:none;">
                                        <?= mb_substr($user['name'], 0, 1) ?>
                                    </div>
                                    <?php else: ?>
                                    <div class="user-avatar" style="background:<?= getColor($user['name']) ?>">
                                        <?= mb_substr($user['name'], 0, 1) ?>
                                    </div>
                                    <?php endif; ?>
                                    <span class="small fw-semibold"><?= clean($user['name']) ?></span>
                                    <?php if ($user['id'] == $_SESSION['admin_id']): ?>
                                    <span class="badge bg-info small px-1">আপনি</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="d-none d-sm-table-cell small"><?= clean($user['email']) ?></td>
                            <td class="d-none d-md-table-cell">
                                <?php if ($user['role'] === 'admin'): ?>
                                    <span class="badge bg-primary small px-2">অ্যাডমিন</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary small px-2">ইউজার</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['is_active']): ?>
                                    <span class="badge bg-success small px-2">সক্রিয়</span>
                                <?php else: ?>
                                    <span class="badge bg-danger small px-2">নিষ্ক্রিয়</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <?php if ($user['id'] != $_SESSION['admin_id']): ?>
                                    <a href="?toggle_status=<?= $user['id'] ?>&current_status=<?= $user['is_active'] ?>" 
                                       class="btn btn-outline-<?= $user['is_active'] ? 'success' : 'secondary' ?> p-1"
                                       title="<?= $user['is_active'] ? 'নিষ্ক্রিয়' : 'সক্রিয়' ?>"
                                       onclick="return confirm('<?= $user['is_active'] ? 'নিষ্ক্রিয়' : 'সক্রিয়' ?> করতে চান?')">
                                        <i data-feather="<?= $user['is_active'] ? 'toggle-right' : 'toggle-left' ?>" style="width:12px;height:12px"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <a href="?edit=<?= $user['id'] ?>" class="btn btn-outline-primary p-1" 
                                       data-bs-toggle="modal" data-bs-target="#userModal"
                                       onclick="editUser(<?= $user['id'] ?>, '<?= addslashes($user['name']) ?>', '<?= addslashes($user['email']) ?>', '<?= addslashes($user['phone'] ?? '') ?>', '<?= $user['role'] ?>', <?= $user['is_active'] ?>, '<?= addslashes($user['photo_url'] ?? '') ?>')">
                                        <i data-feather="edit-2" style="width:12px;height:12px"></i>
                                    </a>
                                    
                                    <?php if ($user['id'] != $_SESSION['admin_id']): ?>
                                    <a href="?delete=<?= $user['id'] ?>" class="btn btn-outline-danger p-1" 
                                       onclick="return confirm('ডিলিট করতে চান?')">
                                        <i data-feather="trash-2" style="width:12px;height:12px"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </form>
        </div>
    </div>
</div>

<!-- ============================================
     USER MODAL (Add/Edit)
     ============================================ -->
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content" style="max-height: 95vh;">
            <div class="modal-header py-2">
                <h5 class="modal-title fs-6" id="modalTitle">
                    <i data-feather="user-plus" style="width:18px;height:18px"></i>
                    নতুন ইউজার যোগ করুন
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="userForm" enctype="multipart/form-data">
                <div class="modal-body" style="overflow-y: auto; max-height: 70vh;">
                    <?= csrfField() ?>
                    <input type="hidden" name="user_id" id="userId" value="0">

                    <!-- PROFILE PHOTO UPLOAD - TOP SECTION -->
                    <div class="text-center mb-3">
                        <label class="form-label small fw-bold d-block">প্রোফাইল ছবি</label>
                        <div class="profile-photo-upload">
                            <div id="photoPreviewContainer" class="mb-2">
                                <div id="photoPreview" class="photo-preview" style="width:100px;height:100px;margin:0 auto;">
                                    <i data-feather="camera" style="width:28px;height:28px;color:#ccc;"></i>
                                    <span class="small">ছবি</span>
                                </div>
                            </div>
                            <input type="file" name="profile_photo" id="profilePhotoInput" 
                                   accept=".jpg,.jpeg,.png,.gif,.webp"
                                   class="d-none">
                            <button type="button" class="btn btn-sm btn-outline-primary w-100" onclick="document.getElementById('profilePhotoInput').click();">
                                <i data-feather="upload" style="width:14px;height:14px"></i> আপলোড করুন
                            </button>
                            <div class="form-text small">JPEG, PNG, GIF, WebP (max 2MB)</div>
                        </div>
                    </div>

                    <!-- OTHER INFO - BOTTOM SECTION -->
                    <div class="row g-2">
                        <div class="col-12">
                            <div class="mb-2">
                                <label class="form-label small fw-bold">পূর্ণ নাম <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="userName" class="form-control form-control-sm" required>
                            </div>
                            
                            <div class="mb-2">
                                <label class="form-label small fw-bold">ইমেইল <span class="text-danger">*</span></label>
                                <input type="email" name="email" id="userEmail" class="form-control form-control-sm" required>
                            </div>
                            
                            <div class="mb-2">
                                <label class="form-label small fw-bold">মোবাইল</label>
                                <input type="text" name="phone" id="userPhone" class="form-control form-control-sm">
                            </div>
                            
                            <div class="mb-2">
                                <label class="form-label small fw-bold">রোল <span class="text-danger">*</span></label>
                                <select name="role" id="userRole" class="form-select form-select-sm" required>
                                    <option value="admin" selected>🛡️ অ্যাডমিন</option>
                                    <option value="user">👤 ইউজার</option>
                                </select>
                            </div>
                            
                            <div class="mb-2" id="passwordField">
                                <label class="form-label small fw-bold">পাসওয়ার্ড <span class="text-danger" id="passwordRequired">*</span></label>
                                <div class="password-wrapper">
                                    <input type="password" name="password" id="userPassword" class="form-control form-control-sm" minlength="6" placeholder="********">
                                    <button type="button" class="password-toggle" onclick="togglePassword('userPassword', this)">
                                        <i data-feather="eye" class="eye-icon"></i>
                                    </button>
                                </div>
                                <div id="passwordStrength" class="password-strength mt-1">
                                    <div class="strength-bar" style="height:3px;">
                                        <div class="strength-progress" id="strengthProgress" style="width:0%"></div>
                                    </div>
                                    <div class="strength-requirements">
                                        <small class="requirement" id="reqLength">✓ ৬+ অক্ষর</small>
                                        <small class="requirement" id="reqLower">✓ ছোট হাতের</small>
                                        <small class="requirement" id="reqUpper">✓ বড় হাতের</small>
                                        <small class="requirement" id="reqNumber">✓ সংখ্যা</small>
                                        <small class="requirement" id="reqSpecial">✓ বিশেষ অক্ষর</small>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-2" id="confirmPasswordField">
                                <label class="form-label small fw-bold">কনফার্ম <span class="text-danger" id="confirmRequired">*</span></label>
                                <div class="password-wrapper">
                                    <input type="password" name="confirm_password" id="confirmPassword" class="form-control form-control-sm" placeholder="********">
                                    <button type="button" class="password-toggle" onclick="togglePassword('confirmPassword', this)">
                                        <i data-feather="eye" class="eye-icon"></i>
                                    </button>
                                </div>
                                <div id="passwordMatch" class="password-match-msg mt-1 small"></div>
                            </div>

                            <div class="mb-2">
                                <div class="form-check">
                                    <input type="checkbox" name="is_active" id="userActive" class="form-check-input" value="1" checked>
                                    <label class="form-check-label small" for="userActive">✅ সক্রিয়</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">বাতিল</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="submitBtn">
                        <i data-feather="save" style="width:14px;height:14px"></i>
                        <span id="submitText">যোগ</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* ============================================
   MODERN MOBILE-FIRST STYLES - FIXED SCROLLING
   ============================================ */

/* Fix container scrolling */
.container-fluid {
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
}

/* Fix modal scrolling */
.modal-dialog-scrollable .modal-content {
    max-height: 95vh;
}
.modal-dialog-scrollable .modal-body {
    overflow-y: auto;
    max-height: 70vh;
    -webkit-overflow-scrolling: touch;
}

/* User Avatar */
.user-avatar-img {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #e9ecef;
    flex-shrink: 0;
}
.user-avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 600;
    flex-shrink: 0;
}

/* Active Admin Cards */
.active-admin-card {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 8px;
    position: relative;
    border: 2px solid #d4edda;
    transition: all 0.3s ease;
    height: 100%;
}
.active-admin-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(40, 167, 69, 0.15);
}

.admin-status-dot {
    position: absolute;
    top: 6px;
    right: 6px;
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.pulse-dot {
    background: #28a745;
    animation: pulse-dot 1.5s ease-in-out infinite;
}

@keyframes pulse-dot {
    0% { opacity: 1; transform: scale(1); box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.4); }
    50% { opacity: 0.7; transform: scale(1.2); box-shadow: 0 0 0 6px rgba(40, 167, 69, 0); }
    100% { opacity: 1; transform: scale(1); box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); }
}

.pulse-icon {
    animation: pulse-icon 1.5s ease-in-out infinite;
}
@keyframes pulse-icon {
    0% { opacity: 1; }
    50% { opacity: 0.3; }
    100% { opacity: 1; }
}

.admin-avatar-img {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    margin: 0 auto 4px;
    border: 2px solid #28a745;
}
.admin-avatar-lg {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    font-weight: 700;
    margin: 0 auto 4px;
    border: 2px solid #28a745;
}
.admin-name {
    font-weight: 600;
    font-size: 11px;
    color: #1B4F72;
}
.admin-email {
    font-size: 9px;
    color: #6c757d;
    word-break: break-all;
}
.admin-time {
    font-size: 9px;
    color: #6c757d;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 2px;
}

/* Profile Photo Upload */
.photo-preview {
    border-radius: 50%;
    border: 3px dashed #ced4da;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
    overflow: hidden;
    transition: all 0.3s ease;
}
.photo-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.photo-preview i {
    margin-bottom: 2px;
}
.photo-preview span {
    font-size: 10px;
    color: #6c757d;
}

/* Statistics Cards */
.stat-card {
    background: white;
    border-radius: 8px;
    padding: 8px 10px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.06);
    height: 100%;
    transition: transform 0.2s;
    text-align: center;
}
.stat-card:hover {
    transform: translateY(-2px);
}
.stat-card .stat-value {
    font-size: 18px;
    font-weight: 700;
    color: #1B4F72;
    line-height: 1.2;
}
.stat-card .stat-label {
    font-size: 10px;
    color: #566573;
    font-weight: 500;
}
.bg-success-grad { background: linear-gradient(135deg, #EAFAF1, #D5F5E3); }
.bg-danger-grad { background: linear-gradient(135deg, #FDEDEC, #FADBD8); }
.bg-warning-grad { background: linear-gradient(135deg, #FEF9E7, #FDEBD0); }

/* Password Wrapper */
.password-wrapper {
    position: relative;
}
.password-wrapper input {
    padding-right: 32px;
}
.password-toggle {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #6c757d;
    cursor: pointer;
    padding: 2px;
}
.eye-icon {
    width: 14px;
    height: 14px;
}

/* Password Strength */
.password-strength {
    margin-top: 4px;
}
.strength-bar {
    height: 3px;
    background: #e9ecef;
    border-radius: 3px;
    overflow: hidden;
    margin-bottom: 3px;
}
.strength-progress {
    height: 100%;
    border-radius: 3px;
    transition: width 0.3s ease, background 0.3s ease;
}
.strength-requirements {
    display: flex;
    flex-wrap: wrap;
    gap: 2px 8px;
    margin-top: 2px;
}
.requirement {
    font-size: 9px;
    color: #dc3545;
    transition: color 0.3s ease;
}
.requirement.met {
    color: #28a745;
}
.password-match-msg {
    font-size: 10px;
    font-weight: 500;
}
.password-match-msg.match {
    color: #28a745;
}
.password-match-msg.no-match {
    color: #dc3545;
}

/* Table Responsive */
.table th {
    font-size: 9px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    color: #566573;
    font-weight: 700;
    border-bottom: 2px solid #e9ecef;
    padding: 6px 4px;
}
.table td {
    vertical-align: middle;
    padding: 6px 4px;
    font-size: 11px;
}
.table tbody tr:hover {
    background-color: #f8f9fa;
}

/* Button Styles */
.btn-primary {
    background: linear-gradient(135deg, #1B4F72, #2E86C1);
    border: none;
    padding: 4px 12px;
    font-weight: 500;
    box-shadow: 0 2px 6px rgba(27, 79, 114, 0.3);
    transition: all 0.3s;
    font-size: 12px;
}
.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(27, 79, 114, 0.4);
}

/* Modal */
.modal-content {
    border-radius: 10px;
    border: none;
    box-shadow: 0 8px 30px rgba(0,0,0,0.12);
}
.modal-header {
    background: linear-gradient(135deg, #1B4F72, #2E86C1);
    color: white;
    border-radius: 10px 10px 0 0;
    padding: 10px 16px;
}
.modal-title {
    font-weight: 600;
    font-size: 14px;
}
.modal-body {
    padding: 12px 16px;
}
.modal-footer {
    padding: 8px 16px;
    border-top: 1px solid #e9ecef;
}

/* Form Controls */
.form-control, .form-select {
    border-radius: 6px;
    border: 1px solid #ced4da;
    padding: 6px 10px;
    font-size: 12px;
}
.form-control:focus, .form-select:focus {
    border-color: #2E86C1;
    box-shadow: 0 0 0 2px rgba(46, 134, 193, 0.1);
}
.form-check-input:checked {
    background-color: #2E86C1;
    border-color: #2E86C1;
}
.form-label {
    font-size: 11px;
    margin-bottom: 2px;
}

/* Alerts */
.alert {
    border-radius: 6px;
    padding: 6px 12px;
    border: none;
    font-size: 12px;
}
.alert-success {
    background: #d4edda;
    color: #155724;
}
.alert-danger {
    background: #f8d7da;
    color: #721c24;
}

/* Responsive Breakpoints */
@media (max-width: 576px) {
    .table td:nth-child(4), .table th:nth-child(4) { /* email */
        display: none;
    }
    .table td:nth-child(5), .table th:nth-child(5) { /* role */
        display: none;
    }
    .stat-card .stat-value {
        font-size: 16px;
    }
    .btn-group .btn {
        padding: 2px 4px;
    }
    .btn-group .btn i {
        width: 12px;
        height: 12px;
    }
}

@media (min-width: 768px) {
    .stat-card .stat-value {
        font-size: 22px;
    }
    .admin-avatar-img, .admin-avatar-lg {
        width: 50px;
        height: 50px;
    }
}
</style>

<script>
// ============================================
// PROFILE PHOTO PREVIEW
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const photoInput = document.getElementById('profilePhotoInput');
    const previewContainer = document.getElementById('photoPreview');
    
    if (photoInput) {
        photoInput.addEventListener('change', function(e) {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewContainer.innerHTML = `<img src="${e.target.result}" alt="Profile Photo">`;
                    previewContainer.style.border = '3px solid #28a745';
                };
                reader.readAsDataURL(file);
            }
        });
    }
});

// ============================================
// LIVE ADMIN TRACKING
// ============================================
let adminUpdateInterval;

function startAdminTracking() {
    adminUpdateInterval = setInterval(updateActiveAdmins, 5000);
    updateActiveAdmins();
}

function updateActiveAdmins() {
    fetch('users.php?ajax=get_active_admins&t=' + Date.now())
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateAdminUI(data.admins, data.count);
                updateLastUpdateTime();
            }
        })
        .catch(error => console.error('Error updating admins:', error));
}

function updateAdminUI(admins, count) {
    const container = document.getElementById('adminCardsRow');
    const countBadge = document.getElementById('adminCount');
    
    countBadge.textContent = count;
    
    const currentCards = container.querySelectorAll('.admin-card');
    const currentIds = new Set();
    currentCards.forEach(card => {
        currentIds.add(parseInt(card.dataset.adminId));
    });
    
    const newIds = new Set();
    admins.forEach(admin => {
        newIds.add(admin.id);
    });
    
    currentCards.forEach(card => {
        const id = parseInt(card.dataset.adminId);
        if (!newIds.has(id)) {
            card.remove();
        }
    });
    
    if (admins.length > 0) {
        const emptyMsg = container.querySelector('.col-12.text-center');
        if (emptyMsg) {
            emptyMsg.remove();
        }
    } else {
        if (!container.querySelector('.col-12.text-center')) {
            container.innerHTML = `
                <div class="col-12 text-center text-muted py-3 small">
                    <i data-feather="user-x" style="width:24px;height:24px;display:block;margin:0 auto 5px"></i>
                    কোনো অ্যাডমিন লাইভ নেই
                </div>
            `;
        }
    }
    
    admins.forEach(admin => {
        if (!currentIds.has(admin.id)) {
            const card = createAdminCard(admin);
            container.appendChild(card);
        }
    });
    
    updateLiveBadges(admins);
}

function createAdminCard(admin) {
    const col = document.createElement('div');
    col.className = 'col-4 col-md-3 col-lg-2 admin-card';
    col.dataset.adminId = admin.id;
    
    const color = getColorFromName(admin.name);
    const photoHtml = admin.photo_url 
        ? `<img src="${escapeHtml(admin.photo_url)}" alt="${escapeHtml(admin.name)}" class="admin-avatar-img" onerror="this.style.display='none'; this.parentElement.querySelector('.admin-avatar-lg').style.display='flex';">`
        : '';
    
    col.innerHTML = `
        <div class="active-admin-card p-2 text-center">
            <div class="admin-status-dot pulse-dot"></div>
            ${photoHtml}
            <div class="admin-avatar-lg" style="background:${color};${admin.photo_url ? 'display:none;' : ''}">
                ${admin.name.charAt(0).toUpperCase()}
            </div>
            <div class="admin-info">
                <div class="admin-name small fw-bold">${escapeHtml(admin.name)}</div>
                <div class="admin-email small text-muted d-none d-md-block">${escapeHtml(admin.email)}</div>
                <div class="admin-time small">
                    <i data-feather="clock" style="width:10px;height:10px"></i>
                    ${formatTime(admin.session_start)}
                </div>
            </div>
        </div>
    `;
    
    setTimeout(() => feather.replace(), 100);
    return col;
}

function updateLiveBadges(admins) {
    const adminIds = new Set(admins.map(a => a.id));
    const tableRows = document.querySelectorAll('#usersTable tbody tr');
    
    tableRows.forEach(row => {
        const userIdCell = row.querySelector('input[type="checkbox"]');
        if (!userIdCell) return;
        
        const userId = parseInt(userIdCell.value);
        const nameCell = row.querySelector('td:nth-child(3)');
        
        if (adminIds.has(userId)) {
            if (!nameCell.querySelector('.badge.bg-success')) {
                const badge = document.createElement('span');
                badge.className = 'badge bg-success small px-1 ms-1 d-none d-md-inline-flex align-items-center gap-1';
                badge.innerHTML = `<i data-feather="radio" style="width:8px;height:8px" class="pulse-icon"></i> লাইভ`;
                nameCell.appendChild(badge);
                setTimeout(() => feather.replace(), 100);
            }
        }
    });
}

function updateLastUpdateTime() {
    const now = new Date();
    const timeStr = now.toLocaleTimeString('bn-BD', { 
        hour: '2-digit', 
        minute: '2-digit' 
    });
    document.getElementById('lastUpdateTime').textContent = `আপডেট: ${timeStr}`;
}

function formatTime(timestamp) {
    const date = new Date(timestamp * 1000);
    return date.toLocaleTimeString('bn-BD', { 
        hour: '2-digit', 
        minute: '2-digit' 
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function getColorFromName(name) {
    const colors = ['#1B4F72', '#2E86C1', '#117A65', '#C0392B', '#8E44AD', '#D35400', '#27AE60', '#2980B9'];
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    return colors[Math.abs(hash) % colors.length];
}

// ============================================
// PASSWORD FUNCTIONS
// ============================================
function togglePassword(inputId, button) {
    const input = document.getElementById(inputId);
    const icon = button.querySelector('.eye-icon');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.setAttribute('data-feather', 'eye-off');
    } else {
        input.type = 'password';
        icon.setAttribute('data-feather', 'eye');
    }
    feather.replace();
}

document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('userPassword');
    const confirmInput = document.getElementById('confirmPassword');
    
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            checkPasswordStrength(this.value);
            checkPasswordMatch();
        });
    }
    
    if (confirmInput) {
        confirmInput.addEventListener('input', checkPasswordMatch);
    }
    
    startAdminTracking();
});

function checkPasswordStrength(password) {
    const progress = document.getElementById('strengthProgress');
    const text = document.getElementById('strengthText');
    
    const hasLength = password.length >= 6;
    const hasLower = /[a-z]/.test(password);
    const hasUpper = /[A-Z]/.test(password);
    const hasNumber = /\d/.test(password);
    const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);
    
    updateRequirement('reqLength', hasLength);
    updateRequirement('reqLower', hasLower);
    updateRequirement('reqUpper', hasUpper);
    updateRequirement('reqNumber', hasNumber);
    updateRequirement('reqSpecial', hasSpecial);
    
    let score = 0;
    if (hasLength) score++;
    if (hasLower) score++;
    if (hasUpper) score++;
    if (hasNumber) score++;
    if (hasSpecial) score++;
    
    let width = 0;
    let color = '#dc3545';
    let label = 'দুর্বল';
    
    if (password.length === 0) {
        width = 0;
        color = '#e9ecef';
        label = 'পাসওয়ার্ড দিন';
    } else if (score <= 1) {
        width = 20;
        color = '#dc3545';
        label = 'দুর্বল';
    } else if (score <= 2) {
        width = 40;
        color = '#ffc107';
        label = 'মাঝারি';
    } else if (score <= 3) {
        width = 60;
        color = '#17a2b8';
        label = 'ভালো';
    } else if (score <= 4) {
        width = 80;
        color = '#28a745';
        label = 'শক্তিশালী';
    } else {
        width = 100;
        color = '#28a745';
        label = 'অত্যন্ত শক্তিশালী';
    }
    
    progress.style.width = width + '%';
    progress.style.background = color;
    text.textContent = label;
    text.style.color = color;
}

function updateRequirement(id, met) {
    const element = document.getElementById(id);
    if (met) {
        element.classList.add('met');
    } else {
        element.classList.remove('met');
    }
}

function checkPasswordMatch() {
    const password = document.getElementById('userPassword').value;
    const confirm = document.getElementById('confirmPassword').value;
    const matchMsg = document.getElementById('passwordMatch');
    
    if (confirm.length === 0) {
        matchMsg.textContent = '';
        matchMsg.className = 'password-match-msg';
        return;
    }
    
    if (password === confirm) {
        matchMsg.textContent = '✓ পাসওয়ার্ড মিলছে';
        matchMsg.className = 'password-match-msg match';
    } else {
        matchMsg.textContent = '✗ পাসওয়ার্ড মিলছে না';
        matchMsg.className = 'password-match-msg no-match';
    }
}

// ============================================
// FORM FUNCTIONS
// ============================================
function resetForm() {
    document.getElementById('modalTitle').innerHTML = '<i data-feather="user-plus" style="width:16px;height:16px"></i> নতুন ইউজার যোগ';
    document.getElementById('userId').value = 0;
    document.getElementById('userName').value = '';
    document.getElementById('userEmail').value = '';
    document.getElementById('userPhone').value = '';
    document.getElementById('userRole').value = 'user';
    document.getElementById('userPassword').value = '';
    document.getElementById('confirmPassword').value = '';
    document.getElementById('userActive').checked = true;
    
    const preview = document.getElementById('photoPreview');
    preview.innerHTML = '<i data-feather="camera" style="width:28px;height:28px;color:#ccc;"></i><span class="small">ছবি</span>';
    preview.style.border = '3px dashed #ced4da';
    document.getElementById('profilePhotoInput').value = '';
    
    document.getElementById('passwordField').style.display = 'block';
    document.getElementById('confirmPasswordField').style.display = 'block';
    document.getElementById('passwordRequired').textContent = '*';
    document.getElementById('confirmRequired').textContent = '*';
    document.getElementById('userPassword').setAttribute('required', 'required');
    document.getElementById('confirmPassword').setAttribute('required', 'required');
    
    document.getElementById('submitText').textContent = 'যোগ';
    
    document.getElementById('strengthProgress').style.width = '0%';
    document.getElementById('strengthText').textContent = 'পাসওয়ার্ড শক্তি';
    document.getElementById('strengthText').style.color = '#6c757d';
    document.getElementById('passwordMatch').textContent = '';
    document.getElementById('passwordMatch').className = 'password-match-msg';
    
    ['reqLength', 'reqLower', 'reqUpper', 'reqNumber', 'reqSpecial'].forEach(id => {
        document.getElementById(id).classList.remove('met');
    });
}

function editUser(id, name, email, phone, role, isActive, photoUrl) {
    document.getElementById('modalTitle').innerHTML = '<i data-feather="edit-2" style="width:16px;height:16px"></i> ইউজার সম্পাদনা';
    document.getElementById('userId').value = id;
    document.getElementById('userName').value = name;
    document.getElementById('userEmail').value = email;
    document.getElementById('userPhone').value = phone || '';
    document.getElementById('userRole').value = role;
    document.getElementById('userPassword').value = '';
    document.getElementById('confirmPassword').value = '';
    document.getElementById('userActive').checked = isActive == 1;
    
    const preview = document.getElementById('photoPreview');
    if (photoUrl) {
        preview.innerHTML = `<img src="${photoUrl}" alt="Profile Photo">`;
        preview.style.border = '3px solid #28a745';
    } else {
        preview.innerHTML = '<i data-feather="camera" style="width:28px;height:28px;color:#ccc;"></i><span class="small">ছবি</span>';
        preview.style.border = '3px dashed #ced4da';
    }
    document.getElementById('profilePhotoInput').value = '';
    
    document.getElementById('passwordField').style.display = 'block';
    document.getElementById('confirmPasswordField').style.display = 'block';
    document.getElementById('passwordRequired').textContent = '(ঐচ্ছিক)';
    document.getElementById('confirmRequired').textContent = '(ঐচ্ছিক)';
    document.getElementById('userPassword').removeAttribute('required');
    document.getElementById('confirmPassword').removeAttribute('required');
    
    document.getElementById('submitText').textContent = 'আপডেট';
    
    document.getElementById('strengthProgress').style.width = '0%';
    document.getElementById('strengthText').textContent = 'পাসওয়ার্ড শক্তি';
    document.getElementById('strengthText').style.color = '#6c757d';
    document.getElementById('passwordMatch').textContent = '';
    document.getElementById('passwordMatch').className = 'password-match-msg';
    
    ['reqLength', 'reqLower', 'reqUpper', 'reqNumber', 'reqSpecial'].forEach(id => {
        document.getElementById(id).classList.remove('met');
    });
}

// ============================================
// BULK ACTION FUNCTIONS
// ============================================
function toggleAllCheckboxes(selectAll) {
    const checkboxes = document.querySelectorAll('.user-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
}

function applyBulkAction() {
    const action = document.getElementById('bulkAction').value;
    if (!action) {
        alert('অ্যাকশন নির্বাচন করুন।');
        return;
    }
    
    const checkboxes = document.querySelectorAll('.user-checkbox:checked');
    if (checkboxes.length === 0) {
        alert('কমপক্ষে একটি ইউজার নির্বাচন করুন।');
        return;
    }
    
    const actionLabels = {
        'activate': 'সক্রিয়',
        'deactivate': 'নিষ্ক্রিয়',
        'delete': 'ডিলিট'
    };
    
    if (confirm(`${checkboxes.length} জন ইউজার ${actionLabels[action]} করতে চান?`)) {
        document.getElementById('bulkActionInput').value = action;
        document.getElementById('bulkForm').submit();
    }
}

// ============================================
// AUTO OPEN MODAL FOR EDIT
// ============================================
<?php if (isset($_GET['edit']) && $editUser): ?>
document.addEventListener('DOMContentLoaded', function() {
    const modal = new bootstrap.Modal(document.getElementById('userModal'));
    modal.show();
});
<?php endif; ?>
</script>

<?php
// Helper functions
function getColor($name) {
    $colors = ['#1B4F72', '#2E86C1', '#117A65', '#C0392B', '#8E44AD', '#D35400', '#27AE60', '#2980B9'];
    return $colors[abs(crc32($name)) % count($colors)];
}

// Include footer
require_once __DIR__ . '/../includes/footer.php';
?>