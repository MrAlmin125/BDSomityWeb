<?php
// =====================================================
// member/profile.php - Member Profile (ULTRA MODERN UI/UX)
// WITH INDIVIDUAL FIELD EDITING - FULLY RESPONSIVE
// =====================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/functions.php';
startSecureSession();

// Check if member is logged in
if (!isset($_SESSION['member_id'])) {
    header('Location: /member/login.php');
    exit;
}

$mId = $_SESSION['member_id'];
$conn = conn();

// Get complete member profile
$member = queryOne("SELECT * FROM members WHERE id = ?", 'i', [$mId]);

if (!$member) {
    header('Location: /member/login.php');
    exit;
}

// Get somity name
$somityName = getSetting('somity_name') ?: 'সোমিটি';

// ============================================
// HELPER FUNCTION FOR AVATAR
// ============================================
function getMemberAvatar($member) {
    if (!empty($member['photo']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $member['photo'])) {
        return $member['photo'];
    }
    $name = isset($member['name']) ? $member['name'] : 'Member';
    $firstLetter = mb_substr($name, 0, 1, 'UTF-8');
    $colors = ['#117A65', '#1B4F72', '#2E86C1', '#C0392B', '#8E44AD', '#D35400', '#27AE60', '#2980B9', '#7D3C98', '#1A5276'];
    $hash = 0;
    for ($i = 0; $i < strlen($name); $i++) {
        $hash = ($hash * 31 + ord($name[$i])) & 0xFFFFFFFF;
    }
    $color = $colors[abs($hash) % count($colors)];
    return 'data:image/svg+xml;charset=utf-8,' . rawurlencode(
        '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200">' .
        '<rect width="200" height="200" rx="50" fill="' . $color . '"/>' .
        '<text x="100" y="130" font-family="Arial, sans-serif" font-size="90" font-weight="bold" fill="white" text-anchor="middle">' . $firstLetter . '</text>' .
        '</svg>'
    );
}

$hasPhoto = !empty($member['photo']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $member['photo']);
$avatar = getMemberAvatar($member);
$flash = '';

// ============================================
// UPDATE SINGLE FIELD VIA AJAX
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_field') {
    header('Content-Type: application/json');
    
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => '❌ নিরাপত্তা যাচাই ব্যর্থ।']);
        exit;
    }
    
    $field = $_POST['field'] ?? '';
    $value = trim($_POST['value'] ?? '');
    
    $allowedFields = ['father_name', 'mother_name', 'spouse_name', 'email', 'date_of_birth', 'address'];
    
    if (!in_array($field, $allowedFields)) {
        echo json_encode(['success' => false, 'message' => '❌ অবৈধ ফিল্ড।']);
        exit;
    }
    
    if ($field === 'email' && !empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => '❌ সঠিক ইমেইল ঠিকানা দিন।']);
        exit;
    }
    
    if ($field === 'email' && !empty($value)) {
        $check = queryOne("SELECT id FROM members WHERE email = ? AND id != ?", 'si', [$value, $mId]);
        if ($check) {
            echo json_encode(['success' => false, 'message' => '❌ এই ইমেইল অন্য সদস্য ব্যবহার করছে।']);
            exit;
        }
    }
    
    if ($field === 'date_of_birth' && !empty($value)) {
        $dateObj = DateTime::createFromFormat('Y-m-d', $value);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $value) {
            echo json_encode(['success' => false, 'message' => '❌ সঠিক তারিখ দিন।']);
            exit;
        }
    }
    
    $stmt = $conn->prepare("UPDATE members SET $field = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('si', $value, $mId);
    
    if ($stmt->execute()) {
        $member = queryOne("SELECT * FROM members WHERE id = ?", 'i', [$mId]);
        $displayValue = $value ?: '-';
        if ($field === 'date_of_birth' && !empty($value)) {
            $displayValue = formatDate($value);
        }
        
        echo json_encode([
            'success' => true, 
            'message' => '✅ আপডেট হয়েছে!',
            'display_value' => $displayValue
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => '❌ আপডেট করতে ব্যর্থ হয়েছে: ' . $conn->error]);
    }
    $stmt->close();
    exit;
}

// ============================================
// PHOTO UPLOAD
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_photo') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $flash = ['danger', '❌ নিরাপত্তা যাচাই ব্যর্থ।'];
    } else {
        $photo = $_FILES['photo'] ?? null;
        
        if ($photo && $photo['error'] === 0) {
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($photo['type'], $allowed)) {
                $flash = ['danger', '❌ শুধু JPG, PNG, GIF বা WEBP ফাইল অনুমোদিত।'];
            } elseif ($photo['size'] > 2097152) {
                $flash = ['danger', '❌ ফাইলের সাইজ 2MB এর বেশি হতে পারে না।'];
            } else {
                $ext = pathinfo($photo['name'], PATHINFO_EXTENSION);
                $photoName = 'member_' . $mId . '_' . time() . '.' . $ext;
                $uploadPath = __DIR__ . '/../uploads/members/';
                
                if (!is_dir($uploadPath)) {
                    mkdir($uploadPath, 0777, true);
                }
                
                if (!empty($member['photo']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $member['photo'])) {
                    unlink($_SERVER['DOCUMENT_ROOT'] . $member['photo']);
                }
                
                if (move_uploaded_file($photo['tmp_name'], $uploadPath . $photoName)) {
                    $photoPath = '/uploads/members/' . $photoName;
                    $stmt = $conn->prepare("UPDATE members SET photo = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->bind_param('si', $photoPath, $mId);
                    
                    if ($stmt->execute()) {
                        $flash = ['success', '✅ প্রোফাইল ছবি আপলোড হয়েছে!'];
                        $member = queryOne("SELECT * FROM members WHERE id=?", 'i', [$mId]);
                        $hasPhoto = true;
                        $avatar = getMemberAvatar($member);
                    } else {
                        $flash = ['danger', '❌ ছবি সেভ করতে ব্যর্থ হয়েছে।'];
                    }
                } else {
                    $flash = ['danger', '❌ ছবি আপলোড করতে ব্যর্থ হয়েছে।'];
                }
            }
        } else {
            $flash = ['danger', '❌ ছবি নির্বাচন করুন।'];
        }
    }
}

// ============================================
// CHANGE PASSWORD
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $flash = ['danger', '❌ নিরাপত্তা যাচাই ব্যর্থ।'];
    } else {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!password_verify($current, $member['password'] ?? '')) {
            $flash = ['danger', '❌ বর্তমান পাসওয়ার্ড সঠিক নয়।'];
        } elseif ($new !== $confirm) {
            $flash = ['danger', '❌ নতুন পাসওয়ার্ড মিলছে না।'];
        } elseif (strlen($new) < 8) {
            $flash = ['danger', '❌ পাসওয়ার্ড কমপক্ষে ৮ অক্ষর হতে হবে।'];
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            $conn->query("UPDATE members SET password='$hash', password_changed=1 WHERE id=$mId");
            $flash = ['success', '✅ পাসওয়ার্ড সফলভাবে পরিবর্তন হয়েছে।'];
        }
    }
}

$pageTitle = 'আমার প্রোফাইল';

// ============================================
// INCLUDE HEADER
// ============================================
require_once __DIR__ . '/includes/header.php';
?>

<!-- ============================================
     PROFILE.PHP CUSTOM CSS
     ============================================ -->
<style>
:root {
    --primary: #117A65;
    --primary-dark: #0E6655;
    --primary-light: #D5F5E3;
    --secondary: #F4F6F9;
    --shadow: 0 8px 32px rgba(0,0,0,0.08);
    --shadow-hover: 0 12px 48px rgba(0,0,0,0.15);
    --radius: 20px;
    --transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.profile-header {
    background: linear-gradient(135deg, #117A65, #0E6655, #1A5276);
    border-radius: var(--radius);
    padding: 30px 24px;
    margin-bottom: 20px;
    color: white;
    position: relative;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(17, 122, 101, 0.3);
}

.profile-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 400px;
    height: 400px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
    animation: floatBubble 8s ease-in-out infinite;
}

.profile-header::after {
    content: '';
    position: absolute;
    bottom: -40%;
    left: -10%;
    width: 300px;
    height: 300px;
    background: rgba(255,255,255,0.03);
    border-radius: 50%;
    animation: floatBubble 6s ease-in-out infinite reverse;
}

@keyframes floatBubble {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(30px, -20px) scale(1.1); }
}

.profile-header .profile-top {
    display: flex;
    align-items: center;
    gap: 24px;
    position: relative;
    z-index: 1;
}

.profile-photo-wrapper {
    position: relative;
    flex-shrink: 0;
}

.profile-photo-wrapper .profile-avatar {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    overflow: hidden;
    border: 4px solid rgba(255,255,255,0.4);
    background: rgba(255,255,255,0.1);
    box-shadow: 0 8px 32px rgba(0,0,0,0.2);
    position: relative;
    cursor: pointer;
    transition: var(--transition);
}

.profile-photo-wrapper .profile-avatar::before {
    content: '';
    position: absolute;
    inset: -4px;
    border-radius: 50%;
    padding: 4px;
    background: conic-gradient(
        from 0deg,
        #117A65,
        #1ABC9C,
        #2ECC71,
        #117A65,
        #1ABC9C,
        #2ECC71,
        #117A65
    );
    -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    animation: spinBorder 4s linear infinite;
    opacity: 0;
    transition: opacity 0.5s ease;
}

.profile-photo-wrapper .profile-avatar:hover::before {
    opacity: 1;
}

@keyframes spinBorder {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.profile-photo-wrapper .pulse-ring {
    position: absolute;
    inset: -8px;
    border-radius: 50%;
    border: 3px solid rgba(255,255,255,0.2);
    animation: pulseRing 2s ease-out infinite;
    pointer-events: none;
}

.profile-photo-wrapper .pulse-ring:nth-child(2) {
    animation-delay: 0.6s;
}

.profile-photo-wrapper .pulse-ring:nth-child(3) {
    animation-delay: 1.2s;
}

@keyframes pulseRing {
    0% { transform: scale(1); opacity: 0.8; }
    100% { transform: scale(1.3); opacity: 0; }
}

.profile-photo-wrapper .profile-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}

.profile-photo-wrapper .profile-avatar:hover img {
    transform: scale(1.05);
}

.profile-photo-wrapper .profile-avatar .avatar-text {
    font-size: 40px;
    font-weight: 700;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
}

.profile-photo-wrapper .photo-overlay {
    position: absolute;
    bottom: 2px;
    right: 2px;
    width: 32px;
    height: 32px;
    background: rgba(0,0,0,0.7);
    backdrop-filter: blur(4px);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid rgba(255,255,255,0.3);
    transition: var(--transition);
    cursor: pointer;
    opacity: 1;
    z-index: 2;
}

.profile-photo-wrapper .photo-overlay svg {
    width: 16px;
    height: 16px;
    color: white;
}

.profile-photo-wrapper .photo-overlay:hover {
    background: rgba(17, 122, 101, 0.9);
    transform: scale(1.1);
    border-color: #1ABC9C;
}

.profile-photo-wrapper .photo-overlay .tooltip-text {
    position: absolute;
    bottom: 110%;
    right: 50%;
    transform: translateX(50%);
    background: rgba(0,0,0,0.8);
    color: white;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 10px;
    white-space: nowrap;
    opacity: 0;
    transition: var(--transition);
    pointer-events: none;
}

.profile-photo-wrapper .photo-overlay:hover .tooltip-text {
    opacity: 1;
}

.profile-photo-wrapper .photo-overlay .tooltip-text::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    border: 5px solid transparent;
    border-top-color: rgba(0,0,0,0.8);
}

.profile-photo-wrapper .online-status {
    position: absolute;
    bottom: 4px;
    left: 4px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #2ECC71;
    border: 3px solid white;
    box-shadow: 0 2px 8px rgba(46, 204, 113, 0.4);
    animation: onlinePulse 2s ease-in-out infinite;
    z-index: 2;
}

@keyframes onlinePulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.15); }
}

.profile-header .profile-info {
    flex: 1;
    min-width: 0;
}

.profile-header .profile-info .name {
    font-size: 24px;
    font-weight: 700;
    margin-bottom: 2px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.profile-header .profile-info .name .verified-badge {
    background: #2ECC71;
    border-radius: 50%;
    width: 22px;
    height: 22px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    color: white;
}

.profile-header .profile-info .member-id {
    font-size: 13px;
    opacity: 0.8;
    display: flex;
    align-items: center;
    gap: 6px;
}

.profile-header .profile-info .member-id svg {
    width: 16px;
    height: 16px;
}

.profile-header .profile-info .badge-member {
    background: rgba(255,255,255,0.2);
    padding: 3px 16px;
    border-radius: 20px;
    font-size: 11px;
    display: inline-block;
    margin-top: 4px;
    border: 1px solid rgba(255,255,255,0.15);
    backdrop-filter: blur(4px);
}

.profile-header .profile-info .contact-info {
    display: flex;
    gap: 16px;
    margin-top: 6px;
    flex-wrap: wrap;
}

.profile-header .profile-info .contact-info .item {
    font-size: 12px;
    opacity: 0.8;
    display: flex;
    align-items: center;
    gap: 4px;
}

.profile-header .profile-info .contact-info .item svg {
    width: 14px;
    height: 14px;
}

.stats-grid-profile {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}

.stat-card-profile {
    background: white;
    border-radius: 16px;
    padding: 16px 12px;
    text-align: center;
    box-shadow: var(--shadow);
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}

.stat-card-profile::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #117A65, #1ABC9C);
    opacity: 0;
    transition: var(--transition);
}

.stat-card-profile:hover::before {
    opacity: 1;
}

.stat-card-profile:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-hover);
}

.stat-card-profile .icon {
    width: 44px;
    height: 44px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 8px;
    transition: var(--transition);
}

.stat-card-profile:hover .icon {
    transform: scale(1.1) rotate(-5deg);
}

.stat-card-profile .icon.green { background: #D5F5E3; color: #1E8449; }
.stat-card-profile .icon.blue { background: #D6EAF8; color: #1A5276; }
.stat-card-profile .icon.orange { background: #FEF9E7; color: #B7950B; }
.stat-card-profile .icon.purple { background: #F5EEF8; color: #7D3C98; }

.stat-card-profile .value {
    font-size: 20px;
    font-weight: 700;
    color: #1C2833;
    transition: var(--transition);
}

.stat-card-profile:hover .value {
    transform: scale(1.05);
}

.stat-card-profile .label {
    font-size: 11px;
    color: #7f8c8d;
    font-weight: 500;
}

/* ============================================
   INFO ROWS - HORIZONTAL ON ALL DEVICES
   ============================================ */
.info-card {
    background: white;
    border-radius: 16px;
    box-shadow: var(--shadow);
    margin-bottom: 16px;
    overflow: hidden;
    transition: var(--transition);
}

.info-card:hover {
    box-shadow: var(--shadow-hover);
}

.info-card .card-title {
    padding: 14px 20px;
    background: #F8F9FA;
    font-weight: 600;
    font-size: 14px;
    border-bottom: 1px solid #eee;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: var(--transition);
}

.info-card:hover .card-title {
    background: #F0F2F5;
}

.info-card .card-title svg {
    width: 18px;
    height: 18px;
    color: #117A65;
}

.info-card .card-body {
    padding: 0;
}

/* HORIZONTAL LAYOUT - SAME ON ALL DEVICES */
.info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 20px;
    border-bottom: 1px solid #f5f5f5;
    transition: var(--transition);
    position: relative;
    gap: 10px;
}

.info-row:last-child {
    border-bottom: none;
}

.info-row:hover {
    background: #FAFBFC;
}

.info-row .label {
    color: #7f8c8d;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
    min-width: 140px;
}

.info-row .label svg {
    width: 14px;
    height: 14px;
    color: #95A5A6;
}

.info-row .label .label-text {
    font-weight: 500;
    color: #566573;
}

.info-row .value-wrapper {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
    justify-content: flex-end;
    min-width: 0;
}

.info-row .value {
    font-weight: 500;
    color: #1C2833;
    text-align: right;
    word-break: break-word;
    flex: 1;
    min-width: 0;
}

.info-row .btn-edit-field {
    background: none;
    border: none;
    color: #95A5A6;
    padding: 4px 8px;
    border-radius: 6px;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    opacity: 0;
    flex-shrink: 0;
}

.info-row:hover .btn-edit-field {
    opacity: 1;
}

.info-row .btn-edit-field:hover {
    background: #D5F5E3;
    color: #117A65;
}

.info-row .btn-edit-field svg {
    width: 14px;
    height: 14px;
}

.info-row.edit-mode {
    background: #F0F8F5;
    border-left: 3px solid #117A65;
}

.info-row.edit-mode .value-wrapper {
    flex: 1;
    justify-content: flex-end;
    flex-wrap: nowrap;
    gap: 10px;
}

/* Input styling in edit mode */
.info-row.edit-mode input,
.info-row.edit-mode textarea {
    flex: 1;
    min-width: 150px;
    max-width: 280px;
    padding: 8px 12px;
    border: 2px solid #D5F5E3;
    border-radius: 8px;
    font-size: 14px;
    font-family: 'Hind Siliguri', sans-serif;
    transition: var(--transition);
    background: white;
}

.info-row.edit-mode input:focus,
.info-row.edit-mode textarea:focus {
    outline: none;
    border-color: #117A65;
    box-shadow: 0 0 0 4px rgba(17, 122, 101, 0.1);
}

.info-row.edit-mode textarea {
    resize: vertical;
    min-height: 60px;
}

/* Date input styling - better calendar on all devices */
.info-row.edit-mode input[type="date"] {
    min-height: 44px;
    -webkit-appearance: none;
    appearance: none;
    padding-right: 40px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='%23117A65' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='3' y='4' width='18' height='18' rx='2' ry='2'%3E%3C/rect%3E%3Cline x1='16' y1='2' x2='16' y2='6'%3E%3C/line%3E%3Cline x1='8' y1='2' x2='8' y2='6'%3E%3C/line%3E%3Cline x1='3' y1='10' x2='21' y2='10'%3E%3C/line%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    background-size: 20px;
    cursor: pointer;
}

.info-row.edit-mode input[type="date"]::-webkit-calendar-picker-indicator {
    opacity: 0;
    position: absolute;
    right: 0;
    top: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;
    background: transparent;
    color: transparent;
}

.info-row.edit-mode input[type="date"]::-webkit-datetime-edit {
    padding: 4px 0;
}

/* Better calendar on mobile */
.info-row.edit-mode input[type="date"]::-webkit-inner-spin-button,
.info-row.edit-mode input[type="date"]::-webkit-clear-button {
    display: none;
}

.info-row.edit-mode input[type="date"]:focus {
    border-color: #117A65;
    box-shadow: 0 0 0 4px rgba(17, 122, 101, 0.1);
}

/* Edit actions - Save button */
.info-row .edit-actions {
    display: flex;
    gap: 6px;
    flex-shrink: 0;
}

.info-row .edit-actions .btn-save {
    background: #117A65;
    color: white;
    padding: 8px 16px;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-width: 85px;
    min-height: 40px;
    font-family: 'Hind Siliguri', sans-serif;
    white-space: nowrap;
}

.info-row .edit-actions .btn-save:hover {
    background: #0E6655;
    transform: scale(1.02);
}

.info-row .edit-actions .btn-save:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.spinner-sm {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid rgba(255,255,255,0.3);
    border-radius: 50%;
    border-top-color: #fff;
    animation: spin 0.8s ease-in-out infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.field-saved {
    position: absolute;
    right: 20px;
    top: 50%;
    transform: translateY(-50%);
    color: #27AE60;
    font-size: 12px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 4px;
    animation: fadeInOut 2s ease forwards;
}

@keyframes fadeInOut {
    0% { opacity: 0; transform: translateY(-50%) scale(0.8); }
    20% { opacity: 1; transform: translateY(-50%) scale(1); }
    80% { opacity: 1; transform: translateY(-50%) scale(1); }
    100% { opacity: 0; transform: translateY(-50%) scale(0.9); }
}

.badge-status.active { background: #D5F5E3; color: #1E8449; }
.badge-status.inactive { background: #FDEDEC; color: #C0392B; }

.password-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    box-shadow: var(--shadow);
    margin-bottom: 16px;
    transition: var(--transition);
}

.password-card:hover {
    box-shadow: var(--shadow-hover);
}

.password-card .card-title {
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.password-card .card-title svg {
    width: 18px;
    height: 18px;
    color: #F39C12;
}

.password-card .form-group {
    margin-bottom: 14px;
}

.password-card .form-group label {
    font-size: 13px;
    font-weight: 500;
    color: #2C3E50;
    margin-bottom: 4px;
    display: block;
}

.password-card .form-group .input-group-custom {
    border: 2px solid #E5E7EB;
    border-radius: 12px;
    overflow: hidden;
    transition: var(--transition);
    background: white;
    display: flex;
    align-items: center;
}

.password-card .form-group .input-group-custom:focus-within {
    border-color: #117A65;
    box-shadow: 0 0 0 4px rgba(17,122,101,0.1);
    transform: scale(1.01);
}

.password-card .form-group .input-group-custom .input-icon {
    padding: 0 14px;
    display: flex;
    align-items: center;
    color: #95A5A6;
    background: #F8F9FA;
    border-right: 2px solid #E5E7EB;
    height: 100%;
    min-height: 46px;
}

.password-card .form-group .input-group-custom .input-icon svg {
    width: 18px;
    height: 18px;
}

.password-card .form-group input {
    border: none;
    padding: 10px 14px;
    font-size: 14px;
    flex: 1;
    outline: none;
    background: transparent;
    font-family: 'Hind Siliguri', sans-serif;
    width: 100%;
    min-height: 46px;
}

.password-card .form-group input:focus {
    box-shadow: none;
}

.password-card .btn-change-password {
    width: 100%;
    padding: 14px;
    background: linear-gradient(135deg, #117A65, #0E6655);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 600;
    font-family: 'Hind Siliguri', sans-serif;
    transition: var(--transition);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    position: relative;
    overflow: hidden;
}

.password-card .btn-change-password::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s ease;
}

.password-card .btn-change-password:hover::before {
    left: 100%;
}

.password-card .btn-change-password:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 32px rgba(17,122,101,0.4);
}

.password-card .btn-change-password:active {
    transform: scale(0.98);
}

/* ============================================
   ACTION BUTTONS - HORIZONTAL ON ALL DEVICES
   ============================================ */
.action-buttons {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-top: 8px;
}

.action-buttons .btn-action {
    padding: 14px;
    border-radius: 14px;
    font-weight: 600;
    font-size: 14px;
    font-family: 'Hind Siliguri', sans-serif;
    border: none;
    transition: var(--transition);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    text-decoration: none;
    position: relative;
    overflow: hidden;
}

.action-buttons .btn-action::before {
    content: '';
    position: absolute;
    inset: 0;
    background: rgba(255,255,255,0.1);
    transform: translateX(-100%);
    transition: transform 0.5s ease;
}

.action-buttons .btn-action:hover::before {
    transform: translateX(0);
}

.action-buttons .btn-action.btn-logout {
    background: #FDEDEC;
    color: #C0392B;
}

.action-buttons .btn-action.btn-logout:hover {
    background: #FADBD8;
    transform: translateY(-3px);
    box-shadow: 0 8px 32px rgba(192, 57, 43, 0.25);
}

.action-buttons .btn-action.btn-dashboard {
    background: #D6EAF8;
    color: #1A5276;
}

.action-buttons .btn-action.btn-dashboard:hover {
    background: #AED6F1;
    transform: translateY(-3px);
    box-shadow: 0 8px 32px rgba(26, 82, 118, 0.25);
}

.modal-content-custom {
    border-radius: 24px;
    border: none;
    box-shadow: 0 20px 80px rgba(0,0,0,0.3);
    overflow: hidden;
    animation: modalSlideIn 0.4s ease;
}

@keyframes modalSlideIn {
    from { opacity: 0; transform: scale(0.9) translateY(20px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

.modal-content-custom .modal-header {
    border-bottom: 1px solid #eee;
    padding: 18px 24px;
    background: #F8F9FA;
}

.modal-content-custom .modal-header .modal-title {
    font-weight: 700;
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-content-custom .modal-body {
    padding: 28px 24px;
}

.modal-content-custom .modal-footer {
    border-top: 1px solid #eee;
    padding: 14px 24px;
    background: #F8F9FA;
}

.photo-preview-container {
    position: relative;
    width: 150px;
    height: 150px;
    border-radius: 50%;
    margin: 0 auto 20px;
    box-shadow: 0 8px 32px rgba(17,122,101,0.2);
}

.photo-preview-container .preview-ring {
    position: absolute;
    inset: -6px;
    border-radius: 50%;
    border: 3px solid transparent;
    border-top-color: #117A65;
    border-right-color: #1ABC9C;
    animation: spinRing 3s linear infinite;
}

@keyframes spinRing {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.photo-preview-container .preview-ring:nth-child(2) {
    inset: -12px;
    animation-duration: 4s;
    animation-direction: reverse;
    border-top-color: #2ECC71;
    border-right-color: #117A65;
}

.photo-preview-container img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid white;
    box-shadow: 0 4px 16px rgba(0,0,0,0.1);
}

.file-upload-wrapper {
    position: relative;
}

.file-upload-wrapper input[type="file"] {
    border: 2px dashed #DEE2E6;
    border-radius: 14px;
    padding: 14px;
    width: 100%;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Hind Siliguri', sans-serif;
    background: #FAFBFC;
}

.file-upload-wrapper input[type="file"]:hover {
    border-color: #117A65;
    background: #F0F8F5;
    transform: scale(1.01);
}

.file-upload-wrapper input[type="file"]:focus {
    outline: none;
    border-color: #117A65;
    box-shadow: 0 0 0 4px rgba(17,122,101,0.1);
}

.file-upload-wrapper .form-text {
    font-size: 12px;
    color: #95A5A6;
    margin-top: 8px;
    text-align: center;
}

/* ============================================
   MOBILE - HORIZONTAL LAYOUT (FIXED)
   ============================================ */
@media (max-width: 768px) {
    .stats-grid-profile {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
    .stat-card-profile .value {
        font-size: 18px;
    }
}

@media (max-width: 576px) {
    .profile-header {
        padding: 20px 16px;
    }
    .profile-header .profile-top {
        flex-direction: column;
        text-align: center;
        gap: 16px;
    }
    .profile-photo-wrapper .profile-avatar {
        width: 80px;
        height: 80px;
    }
    .profile-photo-wrapper .profile-avatar .avatar-text {
        font-size: 32px;
    }
    .profile-photo-wrapper .photo-overlay {
        width: 28px;
        height: 28px;
        bottom: 1px;
        right: 1px;
    }
    .profile-photo-wrapper .photo-overlay svg {
        width: 14px;
        height: 14px;
    }
    .profile-header .profile-info .name {
        font-size: 20px;
        justify-content: center;
    }
    .profile-header .profile-info .member-id {
        justify-content: center;
    }
    .profile-header .profile-info .contact-info {
        justify-content: center;
    }
    .profile-header .profile-info .badge-member {
        display: block;
        text-align: center;
    }
    
    .stats-grid-profile {
        grid-template-columns: 1fr 1fr;
        gap: 8px;
    }
    .stat-card-profile {
        padding: 12px 10px;
    }
    .stat-card-profile .value {
        font-size: 16px;
    }
    .stat-card-profile .icon {
        width: 36px;
        height: 36px;
    }
    
    /* HORIZONTAL LAYOUT - SAME AS DESKTOP */
    .info-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 14px;
        gap: 8px;
        flex-direction: row;
        flex-wrap: wrap;
    }
    .info-row .label {
        min-width: 100px;
        font-size: 12px;
        flex-shrink: 0;
    }
    .info-row .value-wrapper {
        flex: 1;
        justify-content: flex-end;
        min-width: 0;
        flex-wrap: wrap;
        gap: 8px;
    }
    .info-row .value {
        text-align: right;
        font-size: 13px;
        word-break: break-word;
        overflow-wrap: break-word;
        white-space: normal;
        flex: 1;
        min-width: 0;
    }
    .info-row .btn-edit-field {
        opacity: 1;
        padding: 2px 6px;
        font-size: 10px;
        flex-shrink: 0;
    }
    
    /* Edit mode on mobile - FIXED */
    .info-row.edit-mode .value-wrapper {
        flex-wrap: nowrap;
        gap: 8px;
        justify-content: flex-end;
    }
    .info-row.edit-mode input,
    .info-row.edit-mode textarea {
        max-width: 60%;
        min-width: 120px;
        width: auto;
        font-size: 16px;
        flex: 1;
    }
    
    .info-row .edit-actions {
        flex-shrink: 0;
    }
    
    .info-row .edit-actions .btn-save {
        min-width: 70px;
        padding: 6px 12px;
        font-size: 12px;
        min-height: 36px;
        white-space: nowrap;
    }
    
    /* Fix for date input on mobile - show calendar icon */
    .info-row.edit-mode input[type="date"] {
        min-height: 40px;
        -webkit-appearance: none;
        appearance: none;
        padding-right: 36px;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='%23117A65' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='3' y='4' width='18' height='18' rx='2' ry='2'%3E%3C/rect%3E%3Cline x1='16' y1='2' x2='16' y2='6'%3E%3C/line%3E%3Cline x1='8' y1='2' x2='8' y2='6'%3E%3C/line%3E%3Cline x1='3' y1='10' x2='21' y2='10'%3E%3C/line%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 8px center;
        background-size: 18px;
    }
    
    .info-row.edit-mode input[type="date"]::-webkit-calendar-picker-indicator {
        opacity: 0;
        position: absolute;
        right: 0;
        top: 0;
        width: 100%;
        height: 100%;
        cursor: pointer;
        background: transparent;
        color: transparent;
    }
    
    .info-row.edit-mode input[type="date"]::-webkit-datetime-edit {
        padding: 4px 0;
    }
    
    .action-buttons {
        grid-template-columns: 1fr 1fr;
        gap: 8px;
    }
    .action-buttons .btn-action {
        padding: 12px 8px;
        font-size: 12px;
        border-radius: 10px;
    }
    .action-buttons .btn-action svg {
        width: 16px;
        height: 16px;
    }
    
    .password-card {
        padding: 16px;
    }
    
    .modal-content-custom .modal-body {
        padding: 20px 16px;
    }
    .photo-preview-container {
        width: 120px;
        height: 120px;
    }
}

@media (max-width: 400px) {
    .profile-photo-wrapper .profile-avatar {
        width: 70px;
        height: 70px;
    }
    .profile-photo-wrapper .profile-avatar .avatar-text {
        font-size: 28px;
    }
    .profile-header .profile-info .name {
        font-size: 17px;
    }
    .stats-grid-profile {
        gap: 6px;
    }
    .stat-card-profile {
        padding: 10px 8px;
    }
    .stat-card-profile .value {
        font-size: 14px;
    }
    .stat-card-profile .label {
        font-size: 10px;
    }
    
    .info-row {
        padding: 8px 12px;
        gap: 6px;
    }
    .info-row .label {
        min-width: 80px;
        font-size: 11px;
    }
    .info-row .value {
        font-size: 12px;
    }
    
    .info-row.edit-mode input,
    .info-row.edit-mode textarea {
        max-width: 50%;
        min-width: 80px;
        font-size: 14px;
    }
    
    .info-row .edit-actions .btn-save {
        min-width: 60px;
        padding: 4px 10px;
        font-size: 11px;
        min-height: 32px;
    }
    
    .action-buttons {
        grid-template-columns: 1fr 1fr;
        gap: 6px;
    }
    .action-buttons .btn-action {
        padding: 10px 6px;
        font-size: 11px;
        border-radius: 8px;
    }
    .action-buttons .btn-action svg {
        width: 14px;
        height: 14px;
    }
}
</style>

<!-- ============================================
     PROFILE CONTENT
     ============================================ -->

<?php if ($flash): ?>
<div class="alert alert-<?= $flash[0] ?> alert-dismissible fade show mb-3" style="border-radius:14px;font-size:14px;box-shadow:0 4px 16px rgba(0,0,0,0.06);">
    <?= clean($flash[1]) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- ============================================
     PROFILE HEADER
     ============================================ -->
<div class="profile-header">
    <div class="profile-top">
        <div class="profile-photo-wrapper">
            <div class="pulse-ring"></div>
            <div class="pulse-ring"></div>
            <div class="pulse-ring"></div>
            
            <div class="profile-avatar" data-bs-toggle="modal" data-bs-target="#photoModal">
                <?php if ($hasPhoto): ?>
                <img src="<?= $member['photo'] ?>" alt="Profile Photo">
                <?php else: ?>
                <span class="avatar-text"><?= mb_substr($member['name'] ?? 'M', 0, 1) ?></span>
                <?php endif; ?>
                
                <div class="photo-overlay">
                    <i data-feather="camera"></i>
                    <span class="tooltip-text">ছবি পরিবর্তন</span>
                </div>
            </div>
            
            <div class="online-status"></div>
        </div>
        
        <div class="profile-info">
            <div class="name">
                <?= clean($member['name']) ?>
                <span class="verified-badge">
                    <i data-feather="check" style="width:14px;height:14px;"></i>
                </span>
            </div>
            <div class="member-id">
                <i data-feather="hash"></i>
                সদস্য আইডি: <?= clean($member['member_id']) ?>
            </div>
            
            <div class="contact-info">
                <?php if (!empty($member['phone'])): ?>
                <span class="item">
                    <i data-feather="phone"></i>
                    <?= clean($member['phone']) ?>
                </span>
                <?php endif; ?>
                <?php if (!empty($member['email'])): ?>
                <span class="item">
                    <i data-feather="mail"></i>
                    <?= clean($member['email']) ?>
                </span>
                <?php endif; ?>
            </div>
            
            <span class="badge-member">
                <i data-feather="check-circle" style="width:12px;height:12px;"></i>
                সক্রিয় সদস্য
            </span>
        </div>
    </div>
</div>

<!-- ============================================
     STATS CARDS
     ============================================ -->
<?php
$totalDeposit = 0;
$depositResult = queryOne("SELECT COALESCE(SUM(amount),0) AS total FROM deposits WHERE member_id=?", 'i', [$mId]);
if ($depositResult && isset($depositResult['total'])) {
    $totalDeposit = (float)$depositResult['total'];
}

$totalWithdrawal = 0;
$withdrawalResult = queryOne("SELECT COALESCE(SUM(amount),0) AS total FROM withdrawals WHERE member_id=? AND status='completed'", 'i', [$mId]);
if ($withdrawalResult && isset($withdrawalResult['total'])) {
    $totalWithdrawal = (float)$withdrawalResult['total'];
}

$balance = $totalDeposit - $totalWithdrawal;

$pendingFine = 0;
$fineResult = queryOne("SELECT COALESCE(SUM(fine_amount - paid_amount - waived_amount),0) AS total FROM fines WHERE member_id=? AND status IN ('pending','partially_paid')", 'i', [$mId]);
if ($fineResult && isset($fineResult['total'])) {
    $pendingFine = (float)$fineResult['total'];
}
?>

<div class="stats-grid-profile">
    <div class="stat-card-profile">
        <div class="icon green"><i data-feather="arrow-down-circle" style="width:22px;height:22px;"></i></div>
        <div class="value"><?= formatCurrency($totalDeposit) ?></div>
        <div class="label">মোট জমা</div>
    </div>
    <div class="stat-card-profile">
        <div class="icon blue"><i data-feather="arrow-up-circle" style="width:22px;height:22px;"></i></div>
        <div class="value"><?= formatCurrency($totalWithdrawal) ?></div>
        <div class="label">মোট উত্তোলন</div>
    </div>
    <div class="stat-card-profile">
        <div class="icon orange"><i data-feather="credit-card" style="width:22px;height:22px;"></i></div>
        <div class="value"><?= formatCurrency($balance) ?></div>
        <div class="label">বর্তমান ব্যালেন্স</div>
    </div>
    <div class="stat-card-profile">
        <div class="icon purple"><i data-feather="alert-triangle" style="width:22px;height:22px;"></i></div>
        <div class="value" style="color:<?= $pendingFine > 0 ? '#E74C3C' : '#27AE60' ?>;">
            <?= formatCurrency($pendingFine) ?>
        </div>
        <div class="label">pending জরিমানা</div>
    </div>
</div>

<!-- ============================================
     PERSONAL INFORMATION - HORIZONTAL ON ALL DEVICES
     ============================================ -->
<div class="info-card">
    <div class="card-title">
        <i data-feather="user"></i>
        ব্যক্তিগত তথ্য
        <span style="margin-left:auto;font-size:11px;color:#198754;font-weight:400;">
            সম্পাদনা করুন
            <i data-feather="edit-2" style="width:14px;height:14px;"></i>
               
        </span>
    </div>
    <div class="card-body" id="personalInfoBody">
        <?php
        $editableFields = [
            ['father_name', 'পিতার নাম', $member['father_name'] ?? '-', 'user'],
            ['mother_name', 'মাতার নাম', $member['mother_name'] ?? '-', 'user'],
            ['spouse_name', 'স্বামী/স্ত্রীর নাম', $member['spouse_name'] ?? '-', 'heart'],
            ['email', 'ইমেইল', $member['email'] ?? '-', 'mail'],
            ['date_of_birth', 'জন্ম তারিখ', $member['date_of_birth'] ? formatDate($member['date_of_birth']) : '-', 'calendar'],
            ['address', 'ঠিকানা', $member['address'] ?? '-', 'map-pin'],
        ];
        ?>
        <?php foreach ($editableFields as [$field, $label, $val, $icon]): ?>
        <div class="info-row" data-field="<?= $field ?>" id="row_<?= $field ?>">
            <span class="label">
                <i data-feather="<?= $icon ?>"></i>
                <span class="label-text"><?= $label ?>:</span>
            </span>
            <div class="value-wrapper">
                <span class="value" id="val_<?= $field ?>">
                    <?= clean((string)$val) ?>
                </span>
                <button class="btn-edit-field" onclick="editField('<?= $field ?>')" title="সম্পাদনা">
                    <i data-feather="edit-2"></i>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ============================================
     NOMINEE INFORMATION
     ============================================ -->
<?php if (!empty($member['nominee_name']) || !empty($member['nominee_phone'])): ?>
<div class="info-card">
    <div class="card-title">
        <i data-feather="users"></i>
        নমিনি তথ্য
    </div>
    <div class="card-body">
        <?php
        $nomineeInfo = [
            ['নমিনির নাম', $member['nominee_name'] ?? '-', 'user'],
            ['সম্পর্ক', $member['nominee_relation'] ?? '-', 'heart'],
            ['ফোন নম্বর', $member['nominee_phone'] ?? '-', 'phone'],
        ];
        ?>
        <?php foreach ($nomineeInfo as [$label, $val, $icon]): ?>
        <div class="info-row">
            <span class="label">
                <i data-feather="<?= $icon ?>"></i>
                <span class="label-text"><?= $label ?>:</span>
            </span>
            <span class="value"><?= clean((string)$val) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ============================================
     MEMBERSHIP INFORMATION
     ============================================ -->
<div class="info-card">
    <div class="card-title">
        <i data-feather="award"></i>
        সদস্যতা তথ্য
    </div>
    <div class="card-body">
        <?php
        $membershipInfo = [
            ['শেয়ার সংখ্যা', ($member['share_count'] ?? 0) . ' টি', 'layers'],
            ['মাসিক সঞ্চয়', isset($member['monthly_deposit']) ? formatCurrency($member['monthly_deposit']) : '৳ 0.00', 'dollar-sign'],
            ['সদস্যতা অবস্থা', '<span class="badge-status active">✅ সক্রিয়</span>', 'check-circle'],
        ];
        ?>
        <?php foreach ($membershipInfo as [$label, $val, $icon]): ?>
        <div class="info-row">
            <span class="label">
                <i data-feather="<?= $icon ?>"></i>
                <span class="label-text"><?= $label ?>:</span>
            </span>
            <span class="value"><?= $val ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ============================================
     PASSWORD CHANGE
     ============================================ -->
<div class="password-card">
    <div class="card-title">
        <i data-feather="lock"></i>
        পাসওয়ার্ড পরিবর্তন
        <span style="margin-left:auto;font-size:11px;color:#95A5A6;font-weight:400;">
            <i data-feather="shield" style="width:14px;height:14px;"></i> নিরাপদ
        </span>
    </div>
    <form method="POST" id="passwordForm">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="change_password">
        
        <div class="form-group">
            <label>বর্তমান পাসওয়ার্ড</label>
            <div class="input-group-custom">
                <span class="input-icon"><i data-feather="key"></i></span>
                <input type="password" name="current_password" placeholder="বর্তমান পাসওয়ার্ড দিন" required>
            </div>
        </div>
        
        <div class="form-group">
            <label>নতুন পাসওয়ার্ড</label>
            <div class="input-group-custom">
                <span class="input-icon"><i data-feather="lock"></i></span>
                <input type="password" name="new_password" placeholder="৮+ অক্ষর (সংখ্যা ও বিশেষ চিহ্ন)" required minlength="8">
            </div>
        </div>
        
        <div class="form-group">
            <label>নতুন পাসওয়ার্ড নিশ্চিত করুন</label>
            <div class="input-group-custom">
                <span class="input-icon"><i data-feather="check-circle"></i></span>
                <input type="password" name="confirm_password" placeholder="আবার পাসওয়ার্ড দিন" required minlength="8">
            </div>
        </div>
        
        <button type="submit" class="btn-change-password">
            <i data-feather="save" style="width:18px;height:18px;"></i>
            পাসওয়ার্ড পরিবর্তন করুন
        </button>
    </form>
</div>

<!-- ============================================
     ACTION BUTTONS - HORIZONTAL
     ============================================ -->
<div class="action-buttons">
    <a href="/member/index.php" class="btn-action btn-dashboard">
        <i data-feather="home" style="width:18px;height:18px;"></i>
        ড্যাশবোর্ড
    </a>
    <a href="/member/logout.php" class="btn-action btn-logout" onclick="return confirm('আপনি কি নিশ্চিত যে আপনি লগআউট করতে চান?')">
        <i data-feather="log-out" style="width:18px;height:18px;"></i>
        লগআউট
    </a>
</div>

<!-- ============================================
     PHOTO UPLOAD MODAL
     ============================================ -->
<div class="modal fade" id="photoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i data-feather="camera" style="width:20px;height:20px;color:#117A65;"></i>
                    প্রোফাইল ছবি পরিবর্তন
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body text-center">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="upload_photo">
                    
                    <div class="photo-preview-container">
                        <div class="preview-ring"></div>
                        <div class="preview-ring"></div>
                        <?php if ($hasPhoto): ?>
                        <img src="<?= $member['photo'] ?>" alt="Current Photo" id="photoPreview">
                        <?php else: ?>
                        <img src="<?= $avatar ?>" alt="Current Avatar" id="photoPreview">
                        <?php endif; ?>
                    </div>
                    
                    <div class="file-upload-wrapper">
                        <input type="file" name="photo" id="photoInput" accept="image/*" required>
                        <div class="form-text">
                            <i data-feather="image" style="width:14px;height:14px;"></i>
                            JPG, PNG, GIF, WEBP (সর্বোচ্চ 2MB)
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius:12px;font-family:'Hind Siliguri',sans-serif;padding:10px 24px;">
                        বাতিল
                    </button>
                    <button type="submit" class="btn btn-primary" style="border-radius:12px;font-family:'Hind Siliguri',sans-serif;background:linear-gradient(135deg,#117A65,#0E6655);border:none;padding:10px 24px;transition:var(--transition);">
                        <i data-feather="upload" style="width:16px;height:16px;"></i>
                        আপলোড করুন
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================
     SCRIPTS
     ============================================ -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
});

const csrfToken = '<?= generateCSRF() ?>';
let isSaving = false; // Flag to prevent double saves
let saveTimeout = null;

function editField(field) {
    const row = document.getElementById('row_' + field);
    const valueSpan = document.getElementById('val_' + field);
    const currentValue = valueSpan.textContent.trim();
    
    if (row.classList.contains('edit-mode')) {
        return;
    }
    
    // Reset saving flag
    isSaving = false;
    
    const editBtn = row.querySelector('.btn-edit-field');
    editBtn.style.display = 'none';
    
    const displayValue = currentValue === '-' ? '' : currentValue;
    
    // BENGALI PLACEHOLDERS
    const placeholders = {
        'father_name': 'পিতার নাম লিখুন',
        'mother_name': 'মাতার নাম লিখুন',
        'spouse_name': 'স্বামী/স্ত্রীর নাম লিখুন',
        'email': 'ইমেইল ঠিকানা লিখুন',
        'date_of_birth': 'জন্ম তারিখ নির্বাচন করুন',
        'address': 'ঠিকানা লিখুন'
    };
    
    let input;
    if (field === 'address') {
        input = document.createElement('textarea');
        input.rows = 2;
        input.value = displayValue;
        input.placeholder = placeholders[field] || 'ঠিকানা লিখুন';
    } else if (field === 'date_of_birth') {
        input = document.createElement('input');
        input.type = 'date';
        input.id = 'date_input_' + field;
        
        // Try to parse existing date
        let existingDate = null;
        const dateParts = currentValue.split('-');
        
        // Check if date is in YYYY-MM-DD format
        if (dateParts.length === 3 && dateParts[0].length === 4) {
            existingDate = currentValue;
        } 
        // Check if date is in DD-MM-YYYY format
        else if (dateParts.length === 3 && dateParts[2].length === 4) {
            const day = dateParts[0].padStart(2, '0');
            const month = dateParts[1].padStart(2, '0');
            const year = dateParts[2];
            existingDate = year + '-' + month + '-' + day;
        }
        // Check if it's a MySQL date format
        else if (currentValue.match(/^\d{4}-\d{2}-\d{2}$/)) {
            existingDate = currentValue;
        }
        
        // Set the date value if exists, otherwise use today
        if (existingDate) {
            input.value = existingDate;
        } else {
            // Set to today's date
            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const day = String(today.getDate()).padStart(2, '0');
            input.value = year + '-' + month + '-' + day;
        }
        
        input.placeholder = placeholders[field] || 'YYYY-MM-DD';
        
        // Add click handler to ensure calendar opens on mobile
        input.addEventListener('click', function(e) {
            if (this.showPicker) {
                this.showPicker();
            } else {
                this.focus();
            }
        });
        
        // Add touch handler for mobile
        input.addEventListener('touchstart', function(e) {
            this.focus();
        });
        
    } else if (field === 'email') {
        input = document.createElement('input');
        input.type = 'email';
        input.value = displayValue;
        input.placeholder = placeholders[field] || 'ইমেইল ঠিকানা লিখুন';
    } else {
        input = document.createElement('input');
        input.type = 'text';
        input.value = displayValue;
        input.placeholder = placeholders[field] || field.replace('_', ' ') + ' লিখুন';
    }
    
    input.className = 'edit-input';
    input.id = 'input_' + field;
    
    // Create save button
    const actions = document.createElement('div');
    actions.className = 'edit-actions';
    actions.innerHTML = `
        <button class="btn-save" onclick="saveField('${field}')" title="সংরক্ষণ করুন">
            <i data-feather="check" style="width:16px;height:16px;"></i> সংরক্ষণ
        </button>
    `;
    
    const wrapper = row.querySelector('.value-wrapper');
    valueSpan.innerHTML = '';
    valueSpan.appendChild(input);
    valueSpan.appendChild(actions);
    row.classList.add('edit-mode');
    
    // Remove any existing blur listener to prevent duplicates
    input.removeEventListener('blur', function() {});
    
    // Auto-save on Enter key
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            // Clear any pending timeout
            if (saveTimeout) {
                clearTimeout(saveTimeout);
                saveTimeout = null;
            }
            saveField(field);
        }
    });
    
    // Auto-save when user tabs out (blur) - with delay to prevent conflicts
    input.addEventListener('blur', function(e) {
        // Check if we're already saving or if the row is still in edit mode
        if (isSaving || !row.classList.contains('edit-mode')) {
            return;
        }
        
        // Clear any existing timeout
        if (saveTimeout) {
            clearTimeout(saveTimeout);
            saveTimeout = null;
        }
        
        // Small delay to prevent conflict with click events on the save button
        saveTimeout = setTimeout(function() {
            if (row.classList.contains('edit-mode') && !isSaving) {
                saveField(field);
            }
            saveTimeout = null;
        }, 300);
    });
    
    // Auto focus after a short delay
    setTimeout(() => {
        if (field === 'date_of_birth') {
            // For date inputs, try to show the calendar picker
            try {
                if (input.showPicker) {
                    input.showPicker();
                } else {
                    input.focus();
                    input.click();
                }
            } catch(e) {
                input.focus();
            }
        } else {
            input.focus();
        }
    }, 300);
}

function saveField(field) {
    // Prevent multiple simultaneous saves
    if (isSaving) {
        return;
    }
    
    const row = document.getElementById('row_' + field);
    
    // Check if row is still in edit mode
    if (!row.classList.contains('edit-mode')) {
        return;
    }
    
    const input = document.getElementById('input_' + field);
    const value = input.value.trim();
    const valueSpan = document.getElementById('val_' + field);
    const saveBtn = row.querySelector('.btn-save');
    
    if (!saveBtn) return;
    
    // Set saving flag
    isSaving = true;
    
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="spinner-sm"></span>';
    
    const formData = new FormData();
    formData.append('action', 'update_field');
    formData.append('field', field);
    formData.append('value', value);
    formData.append('csrf_token', csrfToken);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            valueSpan.textContent = data.display_value || '-';
            showFieldSaved(row, '✅ সংরক্ষিত');
            row.classList.remove('edit-mode');
            const editBtn = row.querySelector('.btn-edit-field');
            editBtn.style.display = 'flex';
        } else {
            alert(data.message || '❌ আপডেট করতে ব্যর্থ হয়েছে।');
            row.classList.remove('edit-mode');
            const editBtn = row.querySelector('.btn-edit-field');
            editBtn.style.display = 'flex';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ সার্ভারে সমস্যা হয়েছে। আবার চেষ্টা করুন।');
        row.classList.remove('edit-mode');
        const editBtn = row.querySelector('.btn-edit-field');
        editBtn.style.display = 'flex';
    })
    .finally(() => {
        isSaving = false;
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i data-feather="check" style="width:16px;height:16px;"></i> সংরক্ষণ';
        }
    });
}

function showFieldSaved(row, message) {
    const existing = row.querySelector('.field-saved');
    if (existing) existing.remove();
    
    const saved = document.createElement('span');
    saved.className = 'field-saved';
    saved.innerHTML = '<i data-feather="check-circle"></i> ' + message;
    row.appendChild(saved);
    
    setTimeout(() => {
        if (saved.parentNode) saved.remove();
    }, 2500);
}

document.getElementById('photoInput')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.getElementById('photoPreview');
            img.style.transition = 'transform 0.3s ease, opacity 0.3s ease';
            img.style.transform = 'scale(0.8)';
            img.style.opacity = '0.5';
            
            setTimeout(() => {
                img.src = e.target.result;
                img.style.transform = 'scale(1)';
                img.style.opacity = '1';
            }, 200);
        };
        reader.readAsDataURL(file);
    }
});

setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        const bsAlert = new bootstrap.Alert(alert);
        setTimeout(() => bsAlert.close(), 5000);
    });
}, 1000);
</script>

<!-- ============================================
     INCLUDE FOOTER (Bottom Nav)
     ============================================ -->
<?php require_once __DIR__ . '/includes/footer.php'; ?>