<?php
// =====================================================
// admin/member_add.php - Complete Member Registration (FULLY FIXED)
// =====================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
requireAdmin();

$pageTitle = 'নতুন সদস্য নিবন্ধন';
$error = '';
$success = '';
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $editId > 0;

// ============================================
// GET MEMBER DATA FOR EDIT
// ============================================
$memberData = null;
if ($isEdit) {
    $memberData = queryOne("SELECT * FROM members WHERE id = ?", 'i', [$editId]);
    if (!$memberData) {
        setFlash('danger', 'সদস্য পাওয়া যায়নি।');
        header('Location: /admin/members.php');
        exit;
    }
}

// ============================================
// GENERATE MEMBER ID
// ============================================
$newMemberId = generateMemberId();

// ============================================
// HANDLE FORM SUBMISSION
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'নিরাপত্তা যাচাই ব্যর্থ।');
        header('Location: member_add.php');
        exit;
    }

    // Get all form data
    $member_id = trim($_POST['member_id'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $father_name = trim($_POST['father_name'] ?? '');
    $mother_name = trim($_POST['mother_name'] ?? '');
    $spouse_name = trim($_POST['spouse_name'] ?? '');
    $phone = sanitizePhone(trim($_POST['phone'] ?? ''));
    $alt_phone = sanitizePhone(trim($_POST['alt_phone'] ?? ''));
    $email = trim($_POST['email'] ?? '');
    $nid = trim($_POST['nid'] ?? '');
    $date_of_birth = $_POST['date_of_birth'] ?? null;
    $address = trim($_POST['address'] ?? '');
    $join_date = $_POST['join_date'] ?? date('Y-m-d');
    $monthly_deposit = (float)($_POST['monthly_deposit'] ?? 0);
    $share_count = (int)($_POST['share_count'] ?? 0);
    $share_value = (float)($_POST['share_value'] ?? 0);
    $nominee_name = trim($_POST['nominee_name'] ?? '');
    $nominee_relation = trim($_POST['nominee_relation'] ?? '');
    $nominee_phone = sanitizePhone(trim($_POST['nominee_phone'] ?? ''));
    $status = $_POST['status'] ?? 'active';
    $note = trim($_POST['note'] ?? '');
    
    // File uploads
    $photo = $_FILES['photo'] ?? null;
    $nid_photo = $_FILES['nid_photo'] ?? null;

    // Validation
    if (empty($name)) {
        $error = 'সদস্যের নাম দিন।';
    } elseif (empty($phone)) {
        $error = 'ফোন নম্বর দিন।';
    } elseif (!validatePhone($phone)) {
        $error = 'সঠিক ফোন নম্বর দিন (01XXXXXXXXX)';
    } elseif ($monthly_deposit <= 0) {
        $error = 'মাসিক জমার পরিমাণ দিন।';
    } else {
        try {
            $conn = conn();
            $photoPath = '';
            $nidPhotoPath = '';

            // Handle photo upload
            if ($photo && $photo['error'] === 0) {
                $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $photo['tmp_name']);
                finfo_close($finfo);
                
                if (!in_array($mime, $allowed)) {
                    $error = 'শুধু JPG, PNG, GIF বা WEBP ফাইল অনুমোদিত।';
                } elseif ($photo['size'] > 2097152) {
                    $error = 'ফাইলের সাইজ 2MB এর বেশি হতে পারে না।';
                } else {
                    $ext = pathinfo($photo['name'], PATHINFO_EXTENSION);
                    $photoName = 'member_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                    $uploadPath = __DIR__ . '/../uploads/members/';
                    
                    if (!is_dir($uploadPath)) {
                        mkdir($uploadPath, 0777, true);
                    }
                    
                    if (move_uploaded_file($photo['tmp_name'], $uploadPath . $photoName)) {
                        $photoPath = '/uploads/members/' . $photoName;
                    } else {
                        $error = 'ছবি আপলোড করতে ব্যর্থ হয়েছে।';
                    }
                }
            }

            // Handle NID photo upload
            if (empty($error) && $nid_photo && $nid_photo['error'] === 0) {
                $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $nid_photo['tmp_name']);
                finfo_close($finfo);
                
                if (!in_array($mime, $allowed)) {
                    $error = 'শুধু JPG, PNG, GIF বা WEBP ফাইল অনুমোদিত।';
                } elseif ($nid_photo['size'] > 2097152) {
                    $error = 'ফাইলের সাইজ 2MB এর বেশি হতে পারে না।';
                } else {
                    $ext = pathinfo($nid_photo['name'], PATHINFO_EXTENSION);
                    $nidPhotoName = 'nid_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                    $uploadPath = __DIR__ . '/../uploads/members/';
                    
                    if (!is_dir($uploadPath)) {
                        mkdir($uploadPath, 0777, true);
                    }
                    
                    if (move_uploaded_file($nid_photo['tmp_name'], $uploadPath . $nidPhotoName)) {
                        $nidPhotoPath = '/uploads/members/' . $nidPhotoName;
                    } else {
                        $error = 'এনআইডি ছবি আপলোড করতে ব্যর্থ হয়েছে।';
                    }
                }
            }

            if (empty($error)) {
                if ($isEdit) {
                    // ============================================
                    // UPDATE - All fields except auto-generated ones
                    // ============================================
                    
                    // Build the UPDATE query dynamically
                    $updateFields = [];
                    $params = [];
                    $types = '';
                    
                    // Required fields (always updated)
                    $updateFields[] = "name = ?";
                    $params[] = $name;
                    $types .= 's';
                    
                    $updateFields[] = "father_name = ?";
                    $params[] = $father_name;
                    $types .= 's';
                    
                    $updateFields[] = "mother_name = ?";
                    $params[] = $mother_name;
                    $types .= 's';
                    
                    $updateFields[] = "spouse_name = ?";
                    $params[] = $spouse_name;
                    $types .= 's';
                    
                    $updateFields[] = "phone = ?";
                    $params[] = $phone;
                    $types .= 's';
                    
                    $updateFields[] = "alt_phone = ?";
                    $params[] = $alt_phone;
                    $types .= 's';
                    
                    $updateFields[] = "email = ?";
                    $params[] = $email;
                    $types .= 's';
                    
                    $updateFields[] = "nid = ?";
                    $params[] = $nid;
                    $types .= 's';
                    
                    $updateFields[] = "date_of_birth = ?";
                    $params[] = $date_of_birth;
                    $types .= 's';
                    
                    $updateFields[] = "address = ?";
                    $params[] = $address;
                    $types .= 's';
                    
                    $updateFields[] = "join_date = ?";
                    $params[] = $join_date;
                    $types .= 's';
                    
                    $updateFields[] = "monthly_deposit = ?";
                    $params[] = $monthly_deposit;
                    $types .= 'd';
                    
                    $updateFields[] = "share_count = ?";
                    $params[] = $share_count;
                    $types .= 'i';
                    
                    $updateFields[] = "share_value = ?";
                    $params[] = $share_value;
                    $types .= 'd';
                    
                    $updateFields[] = "nominee_name = ?";
                    $params[] = $nominee_name;
                    $types .= 's';
                    
                    $updateFields[] = "nominee_relation = ?";
                    $params[] = $nominee_relation;
                    $types .= 's';
                    
                    $updateFields[] = "nominee_phone = ?";
                    $params[] = $nominee_phone;
                    $types .= 's';
                    
                    $updateFields[] = "status = ?";
                    $params[] = $status;
                    $types .= 's';
                    
                    $updateFields[] = "note = ?";
                    $params[] = $note;
                    $types .= 's';
                    
                    // Optional photo fields
                    if ($photoPath) {
                        $updateFields[] = "photo = ?";
                        $params[] = $photoPath;
                        $types .= 's';
                    }
                    
                    if ($nidPhotoPath) {
                        $updateFields[] = "nid_photo = ?";
                        $params[] = $nidPhotoPath;
                        $types .= 's';
                    }
                    
                    // Add updated_at
                    $updateFields[] = "updated_at = NOW()";
                    
                    // Build the final SQL
                    $sql = "UPDATE members SET " . implode(", ", $updateFields) . " WHERE id = ?";
                    $params[] = $editId;
                    $types .= 'i';
                    
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        throw new Exception("Prepare failed: " . $conn->error);
                    }
                    
                    $stmt->bind_param($types, ...$params);
                    $stmt->execute();
                    
                    logActivity('member_edit', "সদস্য আপডেট: $name (ID: $editId)");
                    setFlash('success', "✅ সদস্য তথ্য আপডেট হয়েছে।");
                    header("Location: /admin/member_profile.php?id=$editId");
                    exit;
                    
                } else {
                    // ============================================
                    // INSERT - EXACTLY 26 columns (excluding auto-increment id)
                    // ============================================
                    
                    // CHECK: Mobile Unique
                    $existing = queryOne("SELECT id FROM members WHERE phone = ?", 's', [$phone]);
                    if ($existing) {
                        $error = 'এই ফোন নম্বরটি ইতিমধ্যে ব্যবহার করা হচ্ছে।';
                    } else {
                        $member_id = generateMemberId();
                        $created_by = $_SESSION['admin_id'];
                        $defaultPw = password_hash($phone, PASSWORD_BCRYPT);
                        
                        // EXACTLY 26 columns (all except 'id' which is auto_increment)
                        // Columns: member_id, name, father_name, mother_name, spouse_name, 
                        // phone, alt_phone, email, nid, date_of_birth,
                        // address, photo, nid_photo, join_date, monthly_deposit,
                        // share_count, share_value, nominee_name, nominee_relation,
                        // nominee_phone, status, note, password, last_login, created_by, created_at
                        
                        // But we're not setting last_login (NULL by default) and created_at (NOW())
                        // So we have 25 columns we're inserting: member_id through created_by
                        
                        $sql = "INSERT INTO members (
                                member_id, name, father_name, mother_name, spouse_name,
                                phone, alt_phone, email, nid, date_of_birth,
                                address, photo, nid_photo, join_date, monthly_deposit,
                                share_count, share_value, nominee_name, nominee_relation,
                                nominee_phone, status, note, password, created_by, created_at
                            ) VALUES (
                                ?, ?, ?, ?, ?,
                                ?, ?, ?, ?, ?,
                                ?, ?, ?, ?, ?,
                                ?, ?, ?, ?,
                                ?, ?, ?, ?,
                                ?, NOW()
                            )";
                        
                        // Count placeholders: 25 (member_id through created_by)
                        // Types: 25 parameters
                        // Let me count: 
                        // member_id(1), name(2), father_name(3), mother_name(4), spouse_name(5)
                        // phone(6), alt_phone(7), email(8), nid(9), date_of_birth(10)
                        // address(11), photo(12), nid_photo(13), join_date(14), monthly_deposit(15)
                        // share_count(16), share_value(17), nominee_name(18), nominee_relation(19)
                        // nominee_phone(20), status(21), note(22), password(23), created_by(24)
                        // Wait, that's 24! Let me recount...
                        
                        // Actually, let me list all columns in the INSERT:
                        // 1. member_id
                        // 2. name
                        // 3. father_name
                        // 4. mother_name
                        // 5. spouse_name
                        // 6. phone
                        // 7. alt_phone
                        // 8. email
                        // 9. nid
                        // 10. date_of_birth
                        // 11. address
                        // 12. photo
                        // 13. nid_photo
                        // 14. join_date
                        // 15. monthly_deposit
                        // 16. share_count
                        // 17. share_value
                        // 18. nominee_name
                        // 19. nominee_relation
                        // 20. nominee_phone
                        // 21. status
                        // 22. note
                        // 23. password
                        // 24. created_by
                        // 25. created_at = NOW()
                        
                        // So we have 24 placeholders (1-24) + NOW() for created_at
                        // Therefore 24 parameters to bind
                        
                        // Types for 24 parameters:
                        // member_id: s (1)
                        // name: s (2)
                        // father_name: s (3)
                        // mother_name: s (4)
                        // spouse_name: s (5)
                        // phone: s (6)
                        // alt_phone: s (7)
                        // email: s (8)
                        // nid: s (9)
                        // date_of_birth: s (10)
                        // address: s (11)
                        // photo: s (12)
                        // nid_photo: s (13)
                        // join_date: s (14)
                        // monthly_deposit: d (15) - decimal
                        // share_count: i (16) - integer
                        // share_value: d (17) - decimal
                        // nominee_name: s (18)
                        // nominee_relation: s (19)
                        // nominee_phone: s (20)
                        // status: s (21)
                        // note: s (22)
                        // password: s (23)
                        // created_by: i (24)
                        
                        // Types string: 14 strings + 1 decimal + 1 integer + 1 decimal + 6 strings + 1 integer
                        // = 'ssssssssssssssdiidsdssssi'
                        // Let me count: ssssssssssssss (14) + d (15) + i (16) + i (17?) wait...
                        
                        // Let me just build it carefully:
                        $types = 'sssssssssssssdiidsdssssi';
                        // Count: s(1)s(2)s(3)s(4)s(5)s(6)s(7)s(8)s(9)s(10)s(11)s(12)s(13)
                        // d(14) i(15) i(16) d(17) s(18) s(19) s(20) s(21) s(22) s(23) i(24)
                        // Wait that's not right either...
                        
                        // Let me just use this format which I know works:
                        // I'll count each parameter position:
                        $types = '';
                        $params = [];
                        
                        // 1-14: Strings (member_id through join_date)
                        for ($i = 0; $i < 14; $i++) {
                            $types .= 's';
                        }
                        // 15: monthly_deposit - decimal
                        $types .= 'd';
                        // 16: share_count - integer
                        $types .= 'i';
                        // 17: share_value - decimal
                        $types .= 'd';
                        // 18-23: nominee_name through password (6 strings)
                        for ($i = 0; $i < 6; $i++) {
                            $types .= 's';
                        }
                        // 24: created_by - integer
                        $types .= 'i';
                        
                        // Now bind with exactly these parameters:
                        $stmt = $conn->prepare($sql);
                        if (!$stmt) {
                            throw new Exception("Prepare failed: " . $conn->error);
                        }
                        
                        $stmt->bind_param(
                            $types,
                            $member_id, $name, $father_name, $mother_name, $spouse_name,
                            $phone, $alt_phone, $email, $nid, $date_of_birth,
                            $address, $photoPath, $nidPhotoPath, $join_date, 
                            $monthly_deposit, $share_count, $share_value,
                            $nominee_name, $nominee_relation, $nominee_phone,
                            $status, $note, $defaultPw, $created_by
                        );
                        $stmt->execute();
                        $newId = $conn->insert_id;
                        
                        // Generate monthly dues for this member
                        $joinYear = date('Y', strtotime($join_date));
                        $joinMonth = (int)date('m', strtotime($join_date));
                        generateMonthlyDuesForMember($newId, $joinYear, $joinMonth);
                        
                        logActivity('member_add', "নতুন সদস্য যোগ: $name (ID: $member_id)");
                        setFlash('success', "✅ সদস্য নিবন্ধন সফল! সদস্য আইডি: $member_id");
                        header("Location: /admin/member_profile.php?id=$newId");
                        exit;
                    }
                }
            }
        } catch (Exception $e) {
            $error = 'ত্রুটি: ' . $e->getMessage();
            error_log("Member Add/Edit Error: " . $e->getMessage());
        }
    }
}

// Helper function to generate dues for a single member
function generateMonthlyDuesForMember($memberId, $year, $month) {
    $amount = (float)getSetting('monthly_due_amount', '500');
    $conn = conn();
    
    $check = queryOne("SELECT id FROM monthly_dues WHERE member_id = ? AND due_year = ? AND due_month = ?", 'iii', [$memberId, $year, $month]);
    if (!$check) {
        $stmt = $conn->prepare("INSERT INTO monthly_dues (member_id, due_year, due_month, amount, status, created_at) VALUES (?, ?, ?, ?, 'unpaid', NOW())");
        $stmt->bind_param('iiid', $memberId, $year, $month, $amount);
        $stmt->execute();
    }
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

<div class="container-fluid px-3">

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h4>
        <i data-feather="<?= $isEdit ? 'edit-2' : 'user-plus' ?>" style="width:20px;height:20px"></i> 
        <?= $isEdit ? 'সদস্য সম্পাদনা' : 'নতুন সদস্য নিবন্ধন' ?>
        <?php if ($isEdit): ?>
        <small class="text-muted"><?= clean($memberData['member_id']) ?></small>
        <?php endif; ?>
    </h4>
    <div>
        <!-- Download Form Button -->
        <button class="btn btn-success btn-sm" onclick="downloadForm()">
            <i data-feather="download" style="width:14px;height:14px"></i> ফর্ম ডাউনলোড
        </button>
        <a href="/admin/members.php" class="btn btn-outline-secondary btn-sm">
            <i data-feather="arrow-left" style="width:14px;height:14px"></i> ফিরুন
        </a>
    </div>
</div>

<!-- Error Message -->
<?php if ($error): ?>
<div class="alert alert-danger">
    <i data-feather="alert-circle" style="width:16px;height:16px"></i>
    <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<!-- ============================================
     REGISTRATION FORM - ALL FIELDS
     ============================================ -->
<div class="row" id="memberForm">
    <div class="col-12 col-lg-10 mx-auto">
        <div class="card">
            <div class="card-header">
                <span class="card-title">
                    <i data-feather="<?= $isEdit ? 'edit-2' : 'clipboard' ?>" style="width:16px;height:16px"></i>
                    <?= $isEdit ? 'সদস্য তথ্য আপডেট' : 'সদস্য তথ্য পূরণ করুন' ?>
                </span>
                <span class="badge bg-info text-dark">সব ফিল্ড পূরণ করুন</span>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" id="memberForm">
                    <?= csrfField() ?>
                    <?php if ($isEdit): ?>
                    <input type="hidden" name="member_id" value="<?= $memberData['member_id'] ?>">
                    <?php endif; ?>

                    <!-- ====================== -->
                    <!-- SECTION 1: BASIC INFO -->
                    <!-- ====================== -->
                    <h6 class="border-bottom pb-2 mb-3">
                        <i data-feather="user" style="width:16px;height:16px"></i> 
                        মৌলিক তথ্য
                    </h6>
                    
                    <div class="row g-3">
                        <!-- Member ID -->
                        <div class="col-md-3">
                            <label class="form-label">সদস্য আইডি</label>
                            <input type="text" class="form-control" 
                                   value="<?= $isEdit ? $memberData['member_id'] : $newMemberId ?>" 
                                   disabled style="background:#f0f0f0;">
                            <div class="form-text">স্বয়ংক্রিয়ভাবে তৈরি</div>
                        </div>

                        <!-- Full Name -->
                        <div class="col-md-9">
                            <label class="form-label">পূর্ণ নাম <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" 
                                   value="<?= $isEdit ? clean($memberData['name']) : '' ?>" 
                                   placeholder="সদস্যের পূর্ণ নাম" required>
                        </div>

                        <!-- Father Name -->
                        <div class="col-md-4">
                            <label class="form-label">পিতার নাম</label>
                            <input type="text" name="father_name" class="form-control" 
                                   value="<?= $isEdit ? clean($memberData['father_name'] ?? '') : '' ?>" 
                                   placeholder="পিতার নাম">
                        </div>

                        <!-- Mother Name -->
                        <div class="col-md-4">
                            <label class="form-label">মাতার নাম</label>
                            <input type="text" name="mother_name" class="form-control" 
                                   value="<?= $isEdit ? clean($memberData['mother_name'] ?? '') : '' ?>" 
                                   placeholder="মাতার নাম">
                        </div>

                        <!-- Spouse Name -->
                        <div class="col-md-4">
                            <label class="form-label">স্বামী/স্ত্রীর নাম</label>
                            <input type="text" name="spouse_name" class="form-control" 
                                   value="<?= $isEdit ? clean($memberData['spouse_name'] ?? '') : '' ?>" 
                                   placeholder="স্বামী/স্ত্রীর নাম">
                        </div>

                        <!-- Mobile -->
                        <div class="col-md-6">
                            <label class="form-label">মোবাইল নম্বর <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">+880</span>
                                <input type="tel" name="phone" class="form-control" 
                                       value="<?= $isEdit ? clean($memberData['phone']) : '' ?>" 
                                       placeholder="01XXXXXXXXX" required pattern="01[3-9]\d{8}">
                            </div>
                            <div class="form-text">01XXXXXXXXX ফরম্যাটে দিন</div>
                        </div>

                        <!-- Alternative Mobile -->
                        <div class="col-md-6">
                            <label class="form-label">বিকল্প মোবাইল</label>
                            <div class="input-group">
                                <span class="input-group-text">+880</span>
                                <input type="tel" name="alt_phone" class="form-control" 
                                       value="<?= $isEdit ? clean($memberData['alt_phone'] ?? '') : '' ?>" 
                                       placeholder="01XXXXXXXXX" pattern="01[3-9]\d{8}">
                            </div>
                        </div>

                        <!-- Email -->
                        <div class="col-md-6">
                            <label class="form-label">ইমেইল</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?= $isEdit ? clean($memberData['email'] ?? '') : '' ?>" 
                                   placeholder="example@gmail.com">
                        </div>

                        <!-- NID -->
                        <div class="col-md-6">
                            <label class="form-label">এনআইডি নম্বর</label>
                            <input type="text" name="nid" class="form-control" 
                                   value="<?= $isEdit ? clean($memberData['nid'] ?? '') : '' ?>" 
                                   placeholder="XXXXXXXXXXXXX">
                        </div>

                        <!-- Date of Birth -->
                        <div class="col-md-6">
                            <label class="form-label">জন্ম তারিখ</label>
                            <input type="date" name="date_of_birth" class="form-control" 
                                   value="<?= $isEdit ? ($memberData['date_of_birth'] ?? '') : '' ?>">
                        </div>

                        <!-- Join Date -->
                        <div class="col-md-6">
                            <label class="form-label">যোগদানের তারিখ</label>
                            <input type="date" name="join_date" class="form-control" 
                                   value="<?= $isEdit ? $memberData['join_date'] : date('Y-m-d') ?>">
                        </div>

                        <!-- Address -->
                        <div class="col-12">
                            <label class="form-label">ঠিকানা</label>
                            <textarea name="address" class="form-control" rows="2" 
                                      placeholder="সদস্যের বর্তমান ঠিকানা"><?= $isEdit ? clean($memberData['address'] ?? '') : '' ?></textarea>
                        </div>
                    </div>

                    <!-- ====================== -->
                    <!-- SECTION 2: FINANCIAL -->
                    <!-- ====================== -->
                    <h6 class="border-bottom pb-2 mb-3 mt-4">
                        <i data-feather="dollar-sign" style="width:16px;height:16px"></i> 
                        আর্থিক তথ্য
                    </h6>
                    
                    <div class="row g-3">
                        <!-- Monthly Deposit -->
                        <div class="col-md-4">
                            <label class="form-label">মাসিক জমার পরিমাণ (৳) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">৳</span>
                                <input type="number" name="monthly_deposit" class="form-control" 
                                       value="<?= $isEdit ? $memberData['monthly_deposit'] : getSetting('monthly_due_amount', '500') ?>" 
                                       step="0.01" min="1" required>
                            </div>
                        </div>

                        <!-- Share Count -->
                        <div class="col-md-4">
                            <label class="form-label">শেয়ার সংখ্যা</label>
                            <input type="number" name="share_count" class="form-control" 
                                   value="<?= $isEdit ? ($memberData['share_count'] ?? 0) : 0 ?>" 
                                   min="0" step="1">
                        </div>

                        <!-- Share Value -->
                        <div class="col-md-4">
                            <label class="form-label">শেয়ার মূল্য (৳)</label>
                            <div class="input-group">
                                <span class="input-group-text">৳</span>
                                <input type="number" name="share_value" class="form-control" 
                                       value="<?= $isEdit ? ($memberData['share_value'] ?? 0) : 0 ?>" 
                                       step="0.01" min="0">
                            </div>
                        </div>
                    </div>

                    <!-- ====================== -->
                    <!-- SECTION 3: NOMINEE -->
                    <!-- ====================== -->
                    <h6 class="border-bottom pb-2 mb-3 mt-4">
                        <i data-feather="users" style="width:16px;height:16px"></i> 
                        নমিনি তথ্য
                    </h6>
                    
                    <div class="row g-3">
                        <!-- Nominee Name -->
                        <div class="col-md-4">
                            <label class="form-label">নমিনির নাম</label>
                            <input type="text" name="nominee_name" class="form-control" 
                                   value="<?= $isEdit ? clean($memberData['nominee_name'] ?? '') : '' ?>" 
                                   placeholder="নমিনির নাম">
                        </div>

                        <!-- Nominee Relation -->
                        <div class="col-md-4">
                            <label class="form-label">নমিনির সম্পর্ক</label>
                            <input type="text" name="nominee_relation" class="form-control" 
                                   value="<?= $isEdit ? clean($memberData['nominee_relation'] ?? '') : '' ?>" 
                                   placeholder="পিতা/মাতা/স্বামী/স্ত্রী">
                        </div>

                        <!-- Nominee Phone -->
                        <div class="col-md-4">
                            <label class="form-label">নমিনির মোবাইল</label>
                            <div class="input-group">
                                <span class="input-group-text">+880</span>
                                <input type="tel" name="nominee_phone" class="form-control" 
                                       value="<?= $isEdit ? clean($memberData['nominee_phone'] ?? '') : '' ?>" 
                                       placeholder="01XXXXXXXXX" pattern="01[3-9]\d{8}">
                            </div>
                        </div>
                    </div>

                    <!-- ====================== -->
                    <!-- SECTION 4: PHOTOS -->
                    <!-- ====================== -->
                    <h6 class="border-bottom pb-2 mb-3 mt-4">
                        <i data-feather="camera" style="width:16px;height:16px"></i> 
                        ছবি
                    </h6>
                    
                    <div class="row g-3">
                        <!-- Photo -->
                        <div class="col-md-6">
                            <label class="form-label">সদস্যের ছবি</label>
                            <?php if ($isEdit && !empty($memberData['photo'])): ?>
                            <div class="mb-2">
                                <img src="<?= $memberData['photo'] ?>" alt="Photo" 
                                     style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:2px solid #ddd;">
                            </div>
                            <?php endif; ?>
                            <input type="file" name="photo" class="form-control" accept="image/*" id="photoInput">
                            <div class="form-text">JPG, PNG, GIF, WEBP (সর্বোচ্চ 2MB)</div>
                            <div id="photoPreview" class="mt-2" style="display:none;">
                                <img id="photoPreviewImg" src="#" alt="Preview" style="width:100px;height:100px;border-radius:50%;object-fit:cover;border:2px solid #2E86C1;">
                            </div>
                        </div>

                        <!-- NID Photo -->
                        <div class="col-md-6">
                            <label class="form-label">এনআইডি ছবি</label>
                            <?php if ($isEdit && !empty($memberData['nid_photo'])): ?>
                            <div class="mb-2">
                                <img src="<?= $memberData['nid_photo'] ?>" alt="NID Photo" 
                                     style="width:80px;height:80px;object-fit:cover;border:2px solid #ddd;">
                            </div>
                            <?php endif; ?>
                            <input type="file" name="nid_photo" class="form-control" accept="image/*" id="nidPhotoInput">
                            <div class="form-text">JPG, PNG, GIF, WEBP (সর্বোচ্চ 2MB)</div>
                            <div id="nidPhotoPreview" class="mt-2" style="display:none;">
                                <img id="nidPhotoPreviewImg" src="#" alt="NID Preview" style="width:100px;height:100px;object-fit:cover;border:2px solid #2E86C1;">
                            </div>
                        </div>
                    </div>

                    <!-- ====================== -->
                    <!-- SECTION 5: STATUS & NOTE -->
                    <!-- ====================== -->
                    <h6 class="border-bottom pb-2 mb-3 mt-4">
                        <i data-feather="settings" style="width:16px;height:16px"></i> 
                        অন্যান্য তথ্য
                    </h6>
                    
                    <div class="row g-3">
                        <!-- Status -->
                        <div class="col-md-6">
                            <label class="form-label">অবস্থা <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                <option value="active" <?= ($isEdit && $memberData['status'] === 'active') ? 'selected' : '' ?>>✅ সক্রিয়</option>
                                <option value="inactive" <?= ($isEdit && $memberData['status'] === 'inactive') ? 'selected' : '' ?>>⛔ নিষ্ক্রিয়</option>
                            </select>
                        </div>

                        <!-- Note -->
                        <div class="col-md-6">
                            <label class="form-label">নোট</label>
                            <input type="text" name="note" class="form-control" 
                                   value="<?= $isEdit ? clean($memberData['note'] ?? '') : '' ?>" 
                                   placeholder="ঐচ্ছিক মন্তব্য">
                        </div>
                    </div>

                    <!-- ====================== -->
                    <!-- BUTTONS -->
                    <!-- ====================== -->
                    <div class="col-12 mt-4">
                        <hr>
                        <button type="submit" class="btn btn-primary px-4">
                            <i data-feather="save" style="width:16px;height:16px" class="me-1"></i>
                            <?= $isEdit ? 'আপডেট করুন' : 'নিবন্ধন করুন' ?>
                        </button>
                        <button type="reset" class="btn btn-outline-secondary ms-2">
                            <i data-feather="refresh-cw" style="width:16px;height:16px" class="me-1"></i>
                            রিসেট
                        </button>
                        <a href="/admin/members.php" class="btn btn-outline-danger ms-2">
                            <i data-feather="x" style="width:16px;height:16px" class="me-1"></i>
                            বাতিল
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

</div>

<style>
.card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    margin-bottom: 20px;
}
.card-header {
    background: white;
    border-bottom: 1px solid #f0f0f0;
    padding: 14px 20px;
    border-radius: 12px 12px 0 0 !important;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}
.card-title {
    font-size: 15px;
    font-weight: 600;
    color: #2C3E50;
    display: flex;
    align-items: center;
    gap: 6px;
}
.card-body {
    padding: 20px;
}

.form-control, .form-select {
    border-radius: 8px;
    border-color: #dce1e8;
    font-family: 'Hind Siliguri', sans-serif;
}
.form-control:focus, .form-select:focus {
    border-color: #2E86C1;
    box-shadow: 0 0 0 0.2rem rgba(46,134,193,0.15);
}
.form-label {
    font-weight: 500;
    font-size: 13px;
    color: #2C3E50;
}
.form-text {
    font-size: 12px;
    color: #888;
}

h6 {
    color: #2C3E50;
    font-weight: 600;
    font-size: 14px;
}

/* Photo preview styles */
#photoPreview, #nidPhotoPreview {
    display: none;
}
#photoPreview.show, #nidPhotoPreview.show {
    display: block;
}

@media (max-width: 768px) {
    .card-body {
        padding: 16px;
    }
    .btn {
        width: 100%;
        margin-bottom: 8px;
    }
    .btn.ms-2 {
        margin-left: 0 !important;
    }
}
</style>

<script>
// Photo preview
document.getElementById('photoInput')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('photoPreviewImg').src = e.target.result;
            document.getElementById('photoPreview').classList.add('show');
        };
        reader.readAsDataURL(file);
    }
});

// NID Photo preview
document.getElementById('nidPhotoInput')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('nidPhotoPreviewImg').src = e.target.result;
            document.getElementById('nidPhotoPreview').classList.add('show');
        };
        reader.readAsDataURL(file);
    }
});

// ============================================
// DOWNLOAD FORM
// ============================================
function downloadForm() {
    const printWindow = window.open('', '_blank', 'width=900,height=700');
    if (!printWindow) {
        alert('পপ-আপ ব্লকার বন্ধ করুন!');
        return;
    }
    
    const currentDate = new Date().toLocaleDateString('bn-BD', {
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    });
    
    // Get form values
    const getValue = (selector) => {
        const el = document.querySelector(selector);
        return el ? el.value || '-' : '-';
    };
    
    const getSelectText = (selector) => {
        const el = document.querySelector(selector);
        if (!el) return '-';
        return el.options[el.selectedIndex]?.text || '-';
    };
    
    const memberId = document.querySelector('input[disabled]')?.value || 'N/A';
    const name = getValue('input[name="name"]');
    const fatherName = getValue('input[name="father_name"]');
    const motherName = getValue('input[name="mother_name"]');
    const spouseName = getValue('input[name="spouse_name"]');
    const phone = getValue('input[name="phone"]');
    const altPhone = getValue('input[name="alt_phone"]');
    const email = getValue('input[name="email"]');
    const nid = getValue('input[name="nid"]');
    const dob = getValue('input[name="date_of_birth"]');
    const joinDate = getValue('input[name="join_date"]');
    const address = document.querySelector('textarea[name="address"]')?.value || '-';
    const monthlyDeposit = getValue('input[name="monthly_deposit"]');
    const shareCount = getValue('input[name="share_count"]');
    const shareValue = getValue('input[name="share_value"]');
    const nomineeName = getValue('input[name="nominee_name"]');
    const nomineeRelation = getValue('input[name="nominee_relation"]');
    const nomineePhone = getValue('input[name="nominee_phone"]');
    const status = getSelectText('select[name="status"]');
    const note = getValue('input[name="note"]');
    
    printWindow.document.write(`
        <html>
        <head>
            <title>সদস্য নিবন্ধন ফর্ম</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: 'Hind Siliguri', 'Arial', sans-serif;
                    padding: 30px;
                    max-width: 1000px;
                    margin: 0 auto;
                    background: #ffffff;
                    color: #1a1a2e;
                }
                .form-header {
                    display: flex;
                    align-items: center;
                    gap: 30px;
                    padding: 20px 25px;
                    margin-bottom: 30px;
                    border-bottom: 4px solid #1a5276;
                    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                    border-radius: 12px 12px 0 0;
                }
                .header-logo {
                    width: 80px;
                    height: 80px;
                    border-radius: 50%;
                    background: linear-gradient(135deg, #1a5276, #2e86c1);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    font-size: 32px;
                    font-weight: 700;
                    box-shadow: 0 4px 15px rgba(26, 82, 118, 0.3);
                }
                .header-content h1 {
                    font-size: 26px;
                    color: #1a5276;
                    font-weight: 700;
                }
                .header-right {
                    text-align: right;
                    margin-left: auto;
                }
                .header-right .member-id span {
                    font-weight: 700;
                    background: #eaf2f8;
                    padding: 2px 10px;
                    border-radius: 4px;
                }
                .form-section {
                    margin-bottom: 20px;
                    border: 1px solid #e9ecef;
                    border-radius: 10px;
                    overflow: hidden;
                }
                .section-title {
                    background: linear-gradient(135deg, #1a5276, #2e86c1);
                    color: white;
                    padding: 10px 20px;
                    font-size: 14px;
                    font-weight: 600;
                }
                .section-body {
                    padding: 15px 20px;
                }
                .row {
                    display: flex;
                    flex-wrap: wrap;
                    margin: 0 -8px;
                }
                .col-6 { width: 50%; padding: 0 8px; }
                .col-12 { width: 100%; padding: 0 8px; }
                .col-4 { width: 33.333%; padding: 0 8px; }
                .field {
                    display: flex;
                    padding: 6px 0;
                    border-bottom: 1px dotted #f0f0f0;
                }
                .field .label {
                    width: 140px;
                    font-weight: 600;
                    color: #4a5568;
                    font-size: 13px;
                    flex-shrink: 0;
                }
                .field .value {
                    flex: 1;
                    color: #1a202c;
                    font-size: 13px;
                }
                .field .value .empty {
                    color: #cbd5e0;
                    font-style: italic;
                }
                .signature-section {
                    display: flex;
                    justify-content: space-between;
                    margin-top: 25px;
                    padding-top: 20px;
                    border-top: 2px solid #e9ecef;
                }
                .signature-section .sig {
                    text-align: center;
                    width: 45%;
                }
                .signature-section .sig .line {
                    border-bottom: 2px solid #1a202c;
                    padding: 8px 0;
                    margin-top: 30px;
                    min-height: 40px;
                }
                .form-footer {
                    text-align: center;
                    margin-top: 25px;
                    padding-top: 15px;
                    border-top: 2px solid #e9ecef;
                    font-size: 11px;
                    color: #a0aec0;
                }
                @media print {
                    body { padding: 15px; }
                    .form-section { break-inside: avoid; }
                }
                @media (max-width: 600px) {
                    .form-header { flex-direction: column; text-align: center; }
                    .header-right { text-align: center; margin-left: 0; }
                    .col-6, .col-4 { width: 100%; }
                }
            </style>
        </head>
        <body>
            <div class="form-header">
                <div class="header-logo">🏛️</div>
                <div class="header-content">
                    <h1>সদস্য নিবন্ধন ফর্ম</h1>
                    <div>${document.querySelector('h4')?.textContent.trim() || 'নতুন সদস্য নিবন্ধন'}</div>
                </div>
                <div class="header-right">
                    <div>তারিখ: ${currentDate}</div>
                    <div class="member-id">সদস্য নং: <span>${memberId}</span></div>
                </div>
            </div>
            
            <div class="form-section">
                <div class="section-title">👤 মৌলিক তথ্য</div>
                <div class="section-body">
                    <div class="row">
                        <div class="col-6"><div class="field"><span class="label">পূর্ণ নাম</span><span class="value">${name}</span></div></div>
                        <div class="col-6"><div class="field"><span class="label">সদস্য আইডি</span><span class="value">${memberId}</span></div></div>
                        <div class="col-4"><div class="field"><span class="label">পিতার নাম</span><span class="value">${fatherName || '<span class="empty">—</span>'}</span></div></div>
                        <div class="col-4"><div class="field"><span class="label">মাতার নাম</span><span class="value">${motherName || '<span class="empty">—</span>'}</span></div></div>
                        <div class="col-4"><div class="field"><span class="label">স্বামী/স্ত্রীর নাম</span><span class="value">${spouseName || '<span class="empty">—</span>'}</span></div></div>
                        <div class="col-6"><div class="field"><span class="label">মোবাইল নম্বর</span><span class="value">${phone}</span></div></div>
                        <div class="col-6"><div class="field"><span class="label">বিকল্প মোবাইল</span><span class="value">${altPhone || '<span class="empty">—</span>'}</span></div></div>
                        <div class="col-6"><div class="field"><span class="label">ইমেইল</span><span class="value">${email || '<span class="empty">—</span>'}</span></div></div>
                        <div class="col-6"><div class="field"><span class="label">এনআইডি নম্বর</span><span class="value">${nid || '<span class="empty">—</span>'}</span></div></div>
                        <div class="col-6"><div class="field"><span class="label">জন্ম তারিখ</span><span class="value">${dob || '<span class="empty">—</span>'}</span></div></div>
                        <div class="col-6"><div class="field"><span class="label">যোগদানের তারিখ</span><span class="value">${joinDate}</span></div></div>
                        <div class="col-12"><div class="field"><span class="label">ঠিকানা</span><span class="value">${address || '<span class="empty">—</span>'}</span></div></div>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <div class="section-title">💰 আর্থিক তথ্য</div>
                <div class="section-body">
                    <div class="row">
                        <div class="col-4"><div class="field"><span class="label">মাসিক জমা</span><span class="value">${monthlyDeposit} ৳</span></div></div>
                        <div class="col-4"><div class="field"><span class="label">শেয়ার সংখ্যা</span><span class="value">${shareCount || '0'}</span></div></div>
                        <div class="col-4"><div class="field"><span class="label">শেয়ার মূল্য</span><span class="value">${shareValue || '0'} ৳</span></div></div>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <div class="section-title">👥 নমিনি তথ্য</div>
                <div class="section-body">
                    <div class="row">
                        <div class="col-4"><div class="field"><span class="label">নমিনির নাম</span><span class="value">${nomineeName || '<span class="empty">—</span>'}</span></div></div>
                        <div class="col-4"><div class="field"><span class="label">সম্পর্ক</span><span class="value">${nomineeRelation || '<span class="empty">—</span>'}</span></div></div>
                        <div class="col-4"><div class="field"><span class="label">মোবাইল</span><span class="value">${nomineePhone || '<span class="empty">—</span>'}</span></div></div>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <div class="section-title">⚙️ অন্যান্য তথ্য</div>
                <div class="section-body">
                    <div class="row">
                        <div class="col-6"><div class="field"><span class="label">অবস্থা</span><span class="value">${status}</span></div></div>
                        <div class="col-6"><div class="field"><span class="label">নোট</span><span class="value">${note || '<span class="empty">—</span>'}</span></div></div>
                    </div>
                </div>
            </div>
            
            <div class="signature-section">
                <div class="sig"><div class="line"></div><div class="sig-label">সদস্যের স্বাক্ষর</div></div>
                <div class="sig"><div class="line"></div><div class="sig-label">কমিটির স্বাক্ষর</div></div>
            </div>
            
            <div class="form-footer">
                <p>এই ফর্মটি কম্পিউটার দ্বারা জেনারেট করা হয়েছে | তারিখ: ${currentDate}</p>
            </div>
            
            <script>
                window.onload = function() {
                    window.print();
                    setTimeout(function() { window.close(); }, 1500);
                };
            <\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>