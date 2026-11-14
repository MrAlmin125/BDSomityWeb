<?php
// =====================================================
// admin/change_password.php - Change Password
// =====================================================

require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
requireAdmin();

$pageTitle = 'পাসওয়ার্ড পরিবর্তন';

// Get admin info
$admin = getCurrentAdmin();
if (!$admin) {
    header('Location: /admin/logout.php');
    exit;
}

$error = '';
$success = '';

// Password strength settings
$minLength = 8;
$requireUppercase = true;
$requireLowercase = true;
$requireNumber = true;
$requireSpecial = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে।';
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validation
        if (empty($current_password)) {
            $error = 'বর্তমান পাসওয়ার্ড দিন।';
        } elseif (empty($new_password)) {
            $error = 'নতুন পাসওয়ার্ড দিন।';
        } elseif (strlen($new_password) < $minLength) {
            $error = "পাসওয়ার্ড কমপক্ষে {$minLength} অক্ষরের হতে হবে।";
        } elseif ($requireUppercase && !preg_match('/[A-Z]/', $new_password)) {
            $error = 'পাসওয়ার্ডে কমপক্ষে একটি বড় হাতের অক্ষর থাকতে হবে।';
        } elseif ($requireLowercase && !preg_match('/[a-z]/', $new_password)) {
            $error = 'পাসওয়ার্ডে কমপক্ষে একটি ছোট হাতের অক্ষর থাকতে হবে।';
        } elseif ($requireNumber && !preg_match('/[0-9]/', $new_password)) {
            $error = 'পাসওয়ার্ডে কমপক্ষে একটি সংখ্যা থাকতে হবে।';
        } elseif ($requireSpecial && !preg_match('/[^A-Za-z0-9]/', $new_password)) {
            $error = 'পাসওয়ার্ডে কমপক্ষে একটি বিশেষ অক্ষর (!@#$%^&* ইত্যাদি) থাকতে হবে।';
        } elseif ($new_password !== $confirm_password) {
            $error = 'নতুন পাসওয়ার্ড এবং কনফার্ম পাসওয়ার্ড মেলেনি।';
        } else {
            // Verify current password
            $user = queryOne("SELECT password FROM users WHERE id = ?", 'i', [$admin['id']]);
            
            if ($user && password_verify($current_password, $user['password'])) {
                // Hash new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update password
                $conn = conn();
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param('si', $hashed_password, $admin['id']);
                
                if ($stmt->execute()) {
                    // Log activity
                    logActivity('password_change', 'পাসওয়ার্ড পরিবর্তন করা হয়েছে');
                    
                    $success = 'পাসওয়ার্ড সফলভাবে পরিবর্তন হয়েছে।';
                    
                    // Clear form fields
                    $_POST = [];
                } else {
                    $error = 'পাসওয়ার্ড পরিবর্তন করতে ব্যর্থ হয়েছে।';
                }
            } else {
                $error = 'বর্তমান পাসওয়ার্ড ভুল হয়েছে।';
                logActivity('password_change_failed', 'পাসওয়ার্ড পরিবর্তন ব্যর্থ - ভুল বর্তমান পাসওয়ার্ড');
            }
        }
    }
}

// Include header
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">
            <i data-feather="lock" style="width:20px;height:20px"></i> 
            পাসওয়ার্ড পরিবর্তন
        </h4>
        <a href="/admin/profile.php" class="btn btn-outline-secondary btn-sm">
            <i data-feather="arrow-left" style="width:14px;height:14px"></i> প্রোফাইলে ফিরুন
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <span class="card-title"><i data-feather="shield" style="width:16px;height:16px"></i> পাসওয়ার্ড পরিবর্তন করুন</span>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                    <div class="alert alert-danger d-flex align-items-center gap-2">
                        <i data-feather="alert-circle" style="width:16px;height:16px"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                    <div class="alert alert-success d-flex align-items-center gap-2">
                        <i data-feather="check-circle" style="width:16px;height:16px"></i>
                        <span><?= htmlspecialchars($success) ?></span>
                    </div>
                    <?php endif; ?>

                    <form method="POST" novalidate id="passwordForm">
                        <?= csrfField() ?>

                        <!-- Current Password -->
                        <div class="mb-3">
                            <label class="form-label">বর্তমান পাসওয়ার্ড <span class="text-danger">*</span></label>
                            <div class="position-relative">
                                <span class="input-group-icon">
                                    <i data-feather="lock" style="width:16px;height:16px"></i>
                                </span>
                                <input type="password" name="current_password" id="currentPassword" 
                                    class="form-control input-with-icon"
                                    placeholder="বর্তমান পাসওয়ার্ড দিন" required
                                    style="padding-right:44px">
                                <button type="button" class="pw-toggle" onclick="togglePassword('currentPassword')">
                                    <i data-feather="eye" id="currentEyeIcon" style="width:18px;height:18px"></i>
                                </button>
                            </div>
                        </div>

                        <!-- New Password -->
                        <div class="mb-3">
                            <label class="form-label">নতুন পাসওয়ার্ড <span class="text-danger">*</span></label>
                            <div class="position-relative">
                                <span class="input-group-icon">
                                    <i data-feather="key" style="width:16px;height:16px"></i>
                                </span>
                                <input type="password" name="new_password" id="newPassword" 
                                    class="form-control input-with-icon"
                                    placeholder="নতুন পাসওয়ার্ড দিন" required
                                    style="padding-right:44px">
                                <button type="button" class="pw-toggle" onclick="togglePassword('newPassword')">
                                    <i data-feather="eye" id="newEyeIcon" style="width:18px;height:18px"></i>
                                </button>
                            </div>
                            <!-- Password Strength Meter -->
                            <div class="mt-2">
                                <div class="progress" style="height:4px">
                                    <div id="strengthBar" class="progress-bar" role="progressbar" style="width:0%"></div>
                                </div>
                                <div id="strengthText" class="small text-muted mt-1">পাসওয়ার্ড শক্তি: <span id="strengthLabel">দুর্বল</span></div>
                            </div>
                            <div class="form-text">
                                <small>পাসওয়ার্ডে কমপক্ষে <?= $minLength ?> অক্ষর, একটি বড় হাতের অক্ষর, একটি ছোট হাতের অক্ষর, একটি সংখ্যা এবং একটি বিশেষ অক্ষর থাকতে হবে।</small>
                            </div>
                        </div>

                        <!-- Confirm Password -->
                        <div class="mb-3">
                            <label class="form-label">পাসওয়ার্ড নিশ্চিত করুন <span class="text-danger">*</span></label>
                            <div class="position-relative">
                                <span class="input-group-icon">
                                    <i data-feather="check-circle" style="width:16px;height:16px"></i>
                                </span>
                                <input type="password" name="confirm_password" id="confirmPassword" 
                                    class="form-control input-with-icon"
                                    placeholder="আবার নতুন পাসওয়ার্ড দিন" required
                                    style="padding-right:44px">
                                <button type="button" class="pw-toggle" onclick="togglePassword('confirmPassword')">
                                    <i data-feather="eye" id="confirmEyeIcon" style="width:18px;height:18px"></i>
                                </button>
                            </div>
                            <!-- Password Match Indicator -->
                            <div id="matchIndicator" class="small mt-1"></div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="/admin/profile.php" class="btn btn-outline-secondary">
                                <i data-feather="x" style="width:16px;height:16px"></i> বাতিল
                            </a>
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <i data-feather="save" style="width:16px;height:16px"></i> পাসওয়ার্ড পরিবর্তন করুন
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Security Tips -->
            <div class="card mt-3">
                <div class="card-body">
                    <div class="d-flex align-items-start gap-3">
                        <i data-feather="shield" style="width:20px;height:20px;color:#2E86C1"></i>
                        <div>
                            <h6 class="mb-1">পাসওয়ার্ড নিরাপত্তা টিপস</h6>
                            <ul class="small text-muted mb-0" style="padding-left:20px">
                                <li>ন্যূনতম ৮ অক্ষরের পাসওয়ার্ড ব্যবহার করুন</li>
                                <li>বড় ও ছোট হাতের অক্ষর, সংখ্যা ও বিশেষ অক্ষর মিশিয়ে তৈরি করুন</li>
                                <li>আপনার জন্ম তারিখ বা নাম ব্যবহার করবেন না</li>
                                <li>নিয়মিত পাসওয়ার্ড পরিবর্তন করুন</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.input-group-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #999;
    pointer-events: none;
}
.input-with-icon {
    padding-left: 38px;
}
.pw-toggle {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    color: #666;
    padding: 0;
}
.pw-toggle:hover {
    color: #1B4F72;
}
</style>

<script>
// Toggle password visibility
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = document.getElementById(fieldId.replace('Password', 'EyeIcon'));
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.setAttribute('data-feather', 'eye-off');
    } else {
        field.type = 'password';
        icon.setAttribute('data-feather', 'eye');
    }
    feather.replace();
}

// Password strength meter
document.getElementById('newPassword').addEventListener('input', function() {
    const password = this.value;
    const bar = document.getElementById('strengthBar');
    const label = document.getElementById('strengthLabel');
    
    let score = 0;
    if (password.length >= 8) score++;
    if (password.length >= 12) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[a-z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;
    
    const percentage = Math.min((score / 6) * 100, 100);
    bar.style.width = percentage + '%';
    
    let strength = 'দুর্বল';
    let color = '#E74C3C';
    if (percentage >= 80) {
        strength = 'শক্তিশালী';
        color = '#27AE60';
    } else if (percentage >= 50) {
        strength = 'মাঝারি';
        color = '#F39C12';
    } else if (percentage >= 30) {
        strength = 'দুর্বল';
        color = '#E67E22';
    }
    
    bar.style.background = color;
    label.textContent = strength;
    label.style.color = color;
});

// Password match check
document.getElementById('confirmPassword').addEventListener('input', function() {
    const newPw = document.getElementById('newPassword').value;
    const confirmPw = this.value;
    const indicator = document.getElementById('matchIndicator');
    
    if (confirmPw.length === 0) {
        indicator.innerHTML = '';
        return;
    }
    
    if (newPw === confirmPw) {
        indicator.innerHTML = '<span style="color:#27AE60">✓ পাসওয়ার্ড মিলেছে</span>';
    } else {
        indicator.innerHTML = '<span style="color:#E74C3C">✗ পাসওয়ার্ড মেলেনি</span>';
    }
});

// Form validation before submit
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    const newPw = document.getElementById('newPassword').value;
    const confirmPw = document.getElementById('confirmPassword').value;
    
    if (newPw !== confirmPw) {
        e.preventDefault();
        alert('পাসওয়ার্ড এবং কনফার্ম পাসওয়ার্ড মেলেনি!');
        return false;
    }
});
</script>

<?php
// Include footer
require_once __DIR__ . '/../includes/footer.php';
?>