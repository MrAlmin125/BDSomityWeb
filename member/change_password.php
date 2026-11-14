<?php
// =====================================================
// member/change_password.php - Change Password
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

// Get member data
$member = queryOne("SELECT * FROM members WHERE id = ?", 'i', [$mId]);

if (!$member) {
    header('Location: /member/login.php');
    exit;
}

$message = '';
$messageType = '';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = isset($_POST['current_password']) ? $_POST['current_password'] : '';
    $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    $forceChange = isset($_POST['force_change']) ? (int)$_POST['force_change'] : 0;
    
    // CSRF validation
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $message = 'সুরক্ষা ত্রুটি! আবার চেষ্টা করুন।';
        $messageType = 'danger';
    } else {
        // Validate inputs
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $message = 'সব ফিল্ড পূরণ করুন।';
            $messageType = 'danger';
        } elseif (strlen($newPassword) < 8) {
            $message = 'পাসওয়ার্ড কমপক্ষে ৮ অক্ষরের হতে হবে।';
            $messageType = 'danger';
        } elseif ($newPassword !== $confirmPassword) {
            $message = 'নতুন পাসওয়ার্ড মিলছে না।';
            $messageType = 'danger';
        } else {
            // Check current password
            $storedPassword = isset($member['password']) ? $member['password'] : '';
            
            // For first login, current password is the phone number
            $isDefaultPassword = ($storedPassword === password_hash($member['phone'], PASSWORD_DEFAULT)) || 
                                 ($storedPassword === md5($member['phone']));
            
            $validPassword = false;
            
            // Try different password verification methods
            if (password_verify($currentPassword, $storedPassword)) {
                $validPassword = true;
            } elseif (md5($currentPassword) === $storedPassword) {
                $validPassword = true;
            } elseif ($currentPassword === $member['phone'] && $isDefaultPassword) {
                $validPassword = true;
            }
            
            if (!$validPassword) {
                $message = 'বর্তমান পাসওয়ার্ড সঠিক নয়।';
                $messageType = 'danger';
            } else {
                // Update password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $updateQuery = "UPDATE members SET password = ?, password_changed = 1 WHERE id = ?";
                $updateResult = queryOne($updateQuery, 'si', [$hashedPassword, $mId]);
                
                if ($updateResult !== false) {
                    // Clear skip session
                    if (isset($_SESSION['skip_password_change'])) {
                        unset($_SESSION['skip_password_change']);
                    }
                    
                    $message = '✅ পাসওয়ার্ড সফলভাবে পরিবর্তন করা হয়েছে!';
                    $messageType = 'success';
                    
                    // Redirect after successful change
                    if ($forceChange == 1) {
                        echo '<script>
                            setTimeout(function() {
                                window.location.href = "/member/index.php";
                            }, 1500);
                        </script>';
                    }
                } else {
                    $message = '❌ পাসওয়ার্ড পরিবর্তন করতে ব্যর্থ হয়েছে। আবার চেষ্টা করুন।';
                    $messageType = 'danger';
                }
            }
        }
    }
}

$pageTitle = 'পাসওয়ার্ড পরিবর্তন';
require_once __DIR__ . '/includes/header.php';
?>

<style>
.password-page {
    max-width: 500px;
    margin: 60px auto;
    padding: 0 20px;
}
.password-card {
    background: white;
    border-radius: 20px;
    padding: 40px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
}
.password-card .lock-icon {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: #FDEDEC;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
    color: #E74C3C;
}
.password-card h3 {
    text-align: center;
    color: #1C2833;
    font-weight: 700;
    margin-bottom: 10px;
}
.password-card .sub-text {
    text-align: center;
    color: #7f8c8d;
    font-size: 14px;
    margin-bottom: 24px;
}
.form-group {
    margin-bottom: 18px;
}
.form-group label {
    font-weight: 600;
    font-size: 14px;
    color: #2C3E50;
    display: block;
    margin-bottom: 6px;
}
.form-group .input-group {
    border: 2px solid #E5E7EB;
    border-radius: 10px;
    overflow: hidden;
    display: flex;
    align-items: center;
    background: white;
    transition: all 0.3s;
}
.form-group .input-group:focus-within {
    border-color: #117A65;
    box-shadow: 0 0 0 4px rgba(17, 122, 101, 0.1);
}
.form-group .input-group .input-icon {
    padding: 0 14px;
    color: #95A5A6;
    background: #F8F9FA;
    border-right: 2px solid #E5E7EB;
    min-height: 48px;
    display: flex;
    align-items: center;
}
.form-group .input-group input {
    border: none;
    padding: 12px 16px;
    font-size: 14px;
    flex: 1;
    outline: none;
    background: transparent;
    font-family: 'Hind Siliguri', sans-serif;
    min-height: 48px;
    width: 100%;
}
.password-strength {
    margin-top: 8px;
    height: 4px;
    border-radius: 4px;
    background: #E5E7EB;
    overflow: hidden;
}
.password-strength .bar {
    height: 100%;
    width: 0%;
    border-radius: 4px;
    transition: all 0.4s ease;
}
.password-strength .bar.weak { width: 33%; background: #E74C3C; }
.password-strength .bar.medium { width: 66%; background: #F39C12; }
.password-strength .bar.strong { width: 100%; background: #27AE60; }
.password-match-status {
    font-size: 13px;
    margin-top: 6px;
}
.password-match-status.error { color: #E74C3C; }
.password-match-status.success { color: #27AE60; }
.btn-change-password {
    width: 100%;
    padding: 14px;
    background: linear-gradient(135deg, #117A65, #0E6655);
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 700;
    font-family: 'Hind Siliguri', sans-serif;
    transition: all 0.3s;
    cursor: pointer;
    margin-top: 8px;
}
.btn-change-password:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(17, 122, 101, 0.3);
}
.btn-change-password:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none !important;
    box-shadow: none !important;
}
.btn-back {
    display: block;
    text-align: center;
    margin-top: 12px;
    color: #7f8c8d;
    text-decoration: none;
    font-size: 14px;
}
.btn-back:hover {
    color: #2C3E50;
}
.alert {
    border-radius: 10px;
    padding: 12px 16px;
    margin-bottom: 16px;
}
.alert-success { background: #D5F5E3; color: #1E8449; border: 1px solid #A9DFBF; }
.alert-danger { background: #FDEDEC; color: #C0392B; border: 1px solid #F5B7B1; }
.alert-warning { background: #FEF9E7; color: #7D6608; border: 1px solid #F9E79F; }
</style>

<div class="password-page">
    <div class="password-card">
        <div class="lock-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
        </div>
        
        <h3>🔐 পাসওয়ার্ড পরিবর্তন</h3>
        <p class="sub-text">আপনার অ্যাকাউন্টের নিরাপত্তার জন্য পাসওয়ার্ড পরিবর্তন করুন</p>
        
        <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>">
            <?= $message ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
            <input type="hidden" name="force_change" value="<?= isset($_GET['force']) ? 1 : 0 ?>">
            
            <div class="form-group">
                <label>বর্তমান পাসওয়ার্ড</label>
                <div class="input-group">
                    <span class="input-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2v4"></path>
                            <path d="M12 22v-4"></path>
                            <path d="M4.93 4.93l2.83 2.83"></path>
                            <path d="M16.24 16.24l2.83 2.83"></path>
                            <path d="M2 12h4"></path>
                            <path d="M18 12h4"></path>
                            <path d="M4.93 19.07l2.83-2.83"></path>
                            <path d="M16.24 7.76l2.83-2.83"></path>
                        </svg>
                    </span>
                    <input type="password" name="current_password" placeholder="ডিফল্ট পাসওয়ার্ড: <?= $member['phone'] ?>" required autofocus>
                </div>
            </div>
            
            <div class="form-group">
                <label>নতুন পাসওয়ার্ড (৮+ অক্ষর)</label>
                <div class="input-group">
                    <span class="input-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                    </span>
                    <input type="password" name="new_password" id="newPassword" placeholder="৮+ অক্ষর, সংখ্যা ও বিশেষ চিহ্ন" required minlength="8">
                </div>
                <div class="password-strength">
                    <div class="bar" id="strengthBar"></div>
                </div>
            </div>
            
            <div class="form-group">
                <label>নতুন পাসওয়ার্ড নিশ্চিত করুন</label>
                <div class="input-group">
                    <span class="input-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    </span>
                    <input type="password" name="confirm_password" id="confirmPassword" placeholder="আবার পাসওয়ার্ড দিন" required>
                </div>
                <div class="password-match-status" id="matchStatus"></div>
            </div>
            
            <button type="submit" class="btn-change-password" id="submitBtn">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;margin-right:8px;">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                    <polyline points="7 3 7 8 15 8"></polyline>
                </svg>
                পাসওয়ার্ড পরিবর্তন করুন
            </button>
        </form>
        
        <a href="/member/index.php" class="btn-back">
            ← ড্যাশবোর্ডে ফিরে যান
        </a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const newPassword = document.getElementById('newPassword');
    const confirmPassword = document.getElementById('confirmPassword');
    const matchStatus = document.getElementById('matchStatus');
    const strengthBar = document.getElementById('strengthBar');
    const submitBtn = document.getElementById('submitBtn');
    
    // Password strength checker
    newPassword.addEventListener('input', function() {
        const val = this.value;
        let strength = 0;
        let strengthText = '';
        
        if (val.length >= 8) strength++;
        if (val.match(/[a-z]+/)) strength++;
        if (val.match(/[A-Z]+/)) strength++;
        if (val.match(/[0-9]+/)) strength++;
        if (val.match(/[$@#&!]+/)) strength++;
        
        strengthBar.className = 'bar';
        if (val.length === 0) {
            strengthBar.style.width = '0%';
        } else if (strength <= 2) {
            strengthBar.classList.add('weak');
            strengthText = 'দুর্বল';
        } else if (strength <= 3) {
            strengthBar.classList.add('medium');
            strengthText = 'মাঝারি';
        } else {
            strengthBar.classList.add('strong');
            strengthText = 'শক্তিশালী';
        }
        
        checkMatch();
    });
    
    // Password match checker
    function checkMatch() {
        const newVal = newPassword.value;
        const confirmVal = confirmPassword.value;
        
        if (confirmVal.length === 0) {
            matchStatus.textContent = '';
            matchStatus.className = 'password-match-status';
            submitBtn.disabled = false;
            return;
        }
        
        if (newVal === confirmVal) {
            matchStatus.textContent = '✅ পাসওয়ার্ড মিলেছে';
            matchStatus.className = 'password-match-status success';
            submitBtn.disabled = false;
        } else {
            matchStatus.textContent = '❌ পাসওয়ার্ড মিলছে না';
            matchStatus.className = 'password-match-status error';
            submitBtn.disabled = true;
        }
    }
    
    confirmPassword.addEventListener('input', checkMatch);
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>