<?php
// =====================================================
// admin/edit_profile.php - Edit Admin Profile (WITH PHOTO UPLOAD)
// =====================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
requireAdmin();

$pageTitle = 'প্রোফাইল সম্পাদনা';

// Get admin info
$admin = getCurrentAdmin();
if (!$admin) {
    header('Location: /admin/logout.php');
    exit;
}

$error = '';
$success = '';
$name = $admin['name'];
$email = $admin['email'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে।';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        // Validation
        if (empty($name)) {
            $error = 'নাম দিন।';
        } elseif (empty($email)) {
            $error = 'ইমেইল দিন।';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'সঠিক ইমেইল ঠিকানা দিন।';
        } else {
            // Check if email already exists (except current user)
            $existing = queryOne(
                "SELECT id FROM users WHERE email = ? AND id != ?",
                'si', [$email, $admin['id']]
            );
            
            if ($existing) {
                $error = 'এই ইমেইলটি ইতিমধ্যে ব্যবহার করা হচ্ছে।';
            } else {
                // Update profile
                $conn = conn();
                $stmt = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
                $stmt->bind_param('ssi', $name, $email, $admin['id']);
                
                if ($stmt->execute()) {
                    // Update session
                    $_SESSION['admin']['name'] = $name;
                    
                    // Log activity
                    logActivity('profile_update', 'প্রোফাইল আপডেট করা হয়েছে');
                    
                    $success = 'প্রোফাইল সফলভাবে আপডেট হয়েছে।';
                    
                    // Refresh admin data
                    $admin = getCurrentAdmin();
                } else {
                    $error = 'প্রোফাইল আপডেট করতে ব্যর্থ হয়েছে।';
                }
            }
        }
    }
}

// Function to get profile photo URL
function getProfilePhotoUrl($admin) {
    if (!empty($admin['profile_photo'])) {
        $photoPath = $admin['profile_photo'];
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

$profilePhotoUrl = getProfilePhotoUrl($admin);

// Include header
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">
            <i data-feather="edit-2" style="width:20px;height:20px"></i> 
            প্রোফাইল সম্পাদনা
        </h4>
        <a href="/admin/profile.php" class="btn btn-outline-secondary btn-sm">
            <i data-feather="arrow-left" style="width:14px;height:14px"></i> প্রোফাইলে ফিরুন
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8">
            <!-- Profile Photo Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <span class="card-title"><i data-feather="camera" style="width:16px;height:16px"></i> প্রোফাইল ছবি</span>
                </div>
                <div class="card-body text-center">
                    <!-- Profile Photo Display -->
                    <div class="profile-photo-container mb-3">
                        <div class="profile-photo-wrapper" id="profilePhotoWrapper">
                            <?php if ($profilePhotoUrl): ?>
                                <img src="<?= htmlspecialchars($profilePhotoUrl) ?>" 
                                     alt="Profile Photo" 
                                     id="profilePhotoPreview"
                                     class="profile-photo-img">
                            <?php else: ?>
                                <?php
                                $initial = mb_substr($admin['name'], 0, 1);
                                $colors = ['#1B4F72', '#2E86C1', '#117A65', '#C0392B', '#8E44AD', '#D35400'];
                                $color = $colors[abs(crc32($admin['name'])) % count($colors)];
                                ?>
                                <div id="profilePhotoPreview" class="profile-photo-placeholder" style="background:<?= $color ?>;">
                                    <?= htmlspecialchars($initial) ?>
                                </div>
                            <?php endif; ?>
                            <div class="photo-upload-overlay" id="photoUploadOverlay">
                                <i data-feather="camera" style="width:24px;height:24px"></i>
                                <span>ছবি পরিবর্তন</span>
                            </div>
                        </div>
                        <input type="file" 
                               id="profilePhotoInput" 
                               accept=".jpg,.jpeg,.png,.gif,.webp"
                               style="display:none;">
                        <div class="mt-2">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="document.getElementById('profilePhotoInput').click();">
                                <i data-feather="upload" style="width:14px;height:14px"></i> ছবি আপলোড
                            </button>
                            <?php if ($profilePhotoUrl): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteProfilePhoto()">
                                    <i data-feather="trash-2" style="width:14px;height:14px"></i> মুছুন
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="form-text small mt-2">
                            সর্বোচ্চ ২MB, JPEG, PNG, GIF বা WebP ফর্ম্যাট সমর্থিত।
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Info Card -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title"><i data-feather="user" style="width:16px;height:16px"></i> আপনার তথ্য পরিবর্তন করুন</span>
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

                    <form method="POST" novalidate>
                        <?= csrfField() ?>

                        <div class="mb-3">
                            <label class="form-label">পূর্ণ নাম <span class="text-danger">*</span></label>
                            <div class="position-relative">
                                <span class="input-group-icon">
                                    <i data-feather="user" style="width:16px;height:16px"></i>
                                </span>
                                <input type="text" name="name" class="form-control input-with-icon"
                                    value="<?= htmlspecialchars($name) ?>"
                                    placeholder="আপনার পূর্ণ নাম" required>
                            </div>
                            <div class="form-text">আপনার পূর্ণ নাম লিখুন যা সিস্টেমে প্রদর্শিত হবে।</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">ইমেইল ঠিকানা <span class="text-danger">*</span></label>
                            <div class="position-relative">
                                <span class="input-group-icon">
                                    <i data-feather="mail" style="width:16px;height:16px"></i>
                                </span>
                                <input type="email" name="email" class="form-control input-with-icon"
                                    value="<?= htmlspecialchars($email) ?>"
                                    placeholder="admin@example.com" required>
                            </div>
                            <div class="form-text">এই ইমেইলটি লগইনের জন্য ব্যবহার করা হবে।</div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="/admin/profile.php" class="btn btn-outline-secondary">
                                <i data-feather="x" style="width:16px;height:16px"></i> বাতিল
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i data-feather="save" style="width:16px;height:16px"></i> সংরক্ষণ করুন
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Info Card -->
            <div class="card mt-3">
                <div class="card-body">
                    <div class="d-flex align-items-start gap-3">
                        <i data-feather="info" style="width:20px;height:20px;color:#2E86C1"></i>
                        <div>
                            <h6 class="mb-1">টিপস</h6>
                            <ul class="small text-muted mb-0" style="padding-left:20px">
                                <li>ইমেইল পরিবর্তন করলে পরবর্তী লগইনে নতুন ইমেইল ব্যবহার করুন</li>
                                <li>পাসওয়ার্ড পরিবর্তন করতে <a href="/admin/change_password.php">পাসওয়ার্ড পরিবর্তন</a> পেজে যান</li>
                                <li>প্রোফাইল ছবি আপলোড করতে ছবিতে ক্লিক করুন বা আপলোড বাটন ব্যবহার করুন</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Upload Status Modal -->
<div class="modal fade" id="uploadStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <div class="upload-status-icon mb-3" id="uploadStatusIcon">
                    <div class="spinner-border text-primary" style="width:50px;height:50px;" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
                <h5 id="uploadStatusTitle">আপলোড হচ্ছে...</h5>
                <p class="text-muted" id="uploadStatusMessage">অনুগ্রহ করে অপেক্ষা করুন</p>
            </div>
        </div>
    </div>
</div>

<style>
/* Profile Photo Styles */
.profile-photo-container {
    position: relative;
    display: inline-block;
}

.profile-photo-wrapper {
    position: relative;
    width: 150px;
    height: 150px;
    border-radius: 50%;
    margin: 0 auto;
    cursor: pointer;
    overflow: hidden;
    border: 4px solid #28a745;
    transition: all 0.3s ease;
}

.profile-photo-wrapper:hover {
    border-color: #1B4F72;
    transform: scale(1.02);
}

.profile-photo-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-photo-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 60px;
    font-weight: 600;
    color: white;
}

.photo-upload-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.6);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: white;
    opacity: 0;
    transition: opacity 0.3s ease;
    gap: 4px;
}

.profile-photo-wrapper:hover .photo-upload-overlay {
    opacity: 1;
}

.photo-upload-overlay i {
    animation: bounceCamera 2s ease-in-out infinite;
}

.photo-upload-overlay span {
    font-size: 12px;
    font-weight: 500;
}

@keyframes bounceCamera {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-5px); }
}

/* Upload Animation */
.uploading .profile-photo-wrapper {
    animation: pulse-border 1s ease-in-out infinite;
}

@keyframes pulse-border {
    0%, 100% { border-color: #28a745; }
    50% { border-color: #F39C12; }
}

.upload-success .profile-photo-wrapper {
    animation: success-pop 0.5s ease;
}

@keyframes success-pop {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

/* Input Group Icon */
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

/* Upload Status Modal */
.upload-status-icon {
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>

<script>
// ============================================
// PROFILE PHOTO UPLOAD WITH PREVIEW
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    const photoInput = document.getElementById('profilePhotoInput');
    const photoWrapper = document.getElementById('profilePhotoWrapper');
    const photoPreview = document.getElementById('profilePhotoPreview');
    const uploadOverlay = document.getElementById('photoUploadOverlay');
    
    if (photoInput) {
        photoInput.addEventListener('change', function(e) {
            const file = this.files[0];
            if (!file) return;
            
            // Validate file type
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                alert('শুধুমাত্র JPEG, PNG, GIF এবং WebP ছবি আপলোড করা যাবে।');
                this.value = '';
                return;
            }
            
            // Validate file size (max 2MB)
            if (file.size > 2 * 1024 * 1024) {
                alert('ফাইলের আকার ২MB এর বেশি হতে পারে না।');
                this.value = '';
                return;
            }
            
            // Show preview
            const reader = new FileReader();
            reader.onload = function(e) {
                // Show loading animation
                photoWrapper.classList.add('uploading');
                uploadOverlay.innerHTML = '<i data-feather="loader" style="width:24px;height:24px" class="spin"></i><span>আপলোড হচ্ছে...</span>';
                feather.replace();
                
                // Update preview
                if (photoPreview.tagName === 'IMG') {
                    photoPreview.src = e.target.result;
                } else {
                    // Replace placeholder with image
                    const img = document.createElement('img');
                    img.id = 'profilePhotoPreview';
                    img.src = e.target.result;
                    img.alt = 'Profile Photo';
                    img.className = 'profile-photo-img';
                    photoWrapper.innerHTML = '';
                    photoWrapper.appendChild(img);
                    photoWrapper.appendChild(uploadOverlay);
                }
                
                // Auto upload after preview
                setTimeout(function() {
                    uploadProfilePhoto(file);
                }, 800);
            };
            reader.readAsDataURL(file);
        });
    }
    
    // Click on photo to upload
    if (photoWrapper) {
        photoWrapper.addEventListener('click', function() {
            photoInput.click();
        });
    }
});

// ============================================
// UPLOAD PROFILE PHOTO FUNCTION
// ============================================

function uploadProfilePhoto(file) {
    const photoWrapper = document.getElementById('profilePhotoWrapper');
    const uploadOverlay = document.getElementById('photoUploadOverlay');
    
    // Get CSRF token
    const csrfToken = document.querySelector('input[name="csrf_token"]');
    if (!csrfToken) {
        alert('সিকিউরিটি ত্রুটি!');
        return;
    }
    
    // Show upload status
    showUploadStatus('uploading', 'আপলোড হচ্ছে...', 'অনুগ্রহ করে অপেক্ষা করুন');
    
    // Create form data
    const formData = new FormData();
    formData.append('profile_photo', file);
    formData.append('csrf_token', csrfToken.value);
    
    fetch('/admin/upload_profile_photo.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        // Check if upload was successful
        if (data.includes('সফলভাবে')) {
            // Success
            showUploadStatus('success', 'ছবি আপলোড হয়েছে!', 'প্রোফাইল ছবি সফলভাবে আপলোড হয়েছে।');
            
            // Remove uploading animation
            photoWrapper.classList.remove('uploading');
            photoWrapper.classList.add('upload-success');
            
            // Update overlay
            uploadOverlay.innerHTML = '<i data-feather="check" style="width:24px;height:24px"></i><span>ছবি পরিবর্তন</span>';
            feather.replace();
            
            // Refresh page after 1.5 seconds
            setTimeout(function() {
                window.location.reload();
            }, 1500);
        } else {
            // Error
            showUploadStatus('error', 'আপলোড ব্যর্থ!', 'ছবি আপলোড করতে ব্যর্থ হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।');
            photoWrapper.classList.remove('uploading');
            
            // Reset overlay
            uploadOverlay.innerHTML = '<i data-feather="camera" style="width:24px;height:24px"></i><span>ছবি পরিবর্তন</span>';
            feather.replace();
        }
    })
    .catch(error => {
        console.error('Upload error:', error);
        showUploadStatus('error', 'আপলোড ব্যর্থ!', 'সার্ভার ত্রুটি। অনুগ্রহ করে আবার চেষ্টা করুন।');
        photoWrapper.classList.remove('uploading');
    });
}

// ============================================
// SHOW UPLOAD STATUS MODAL
// ============================================

function showUploadStatus(type, title, message) {
    const modal = new bootstrap.Modal(document.getElementById('uploadStatusModal'));
    const iconContainer = document.getElementById('uploadStatusIcon');
    const titleEl = document.getElementById('uploadStatusTitle');
    const messageEl = document.getElementById('uploadStatusMessage');
    
    let iconHtml = '';
    let iconClass = '';
    
    switch(type) {
        case 'uploading':
            iconHtml = '<div class="spinner-border text-primary" style="width:50px;height:50px;" role="status"><span class="visually-hidden">Loading...</span></div>';
            iconClass = 'text-primary';
            break;
        case 'success':
            iconHtml = '<div class="rounded-circle bg-success d-flex align-items-center justify-content-center" style="width:50px;height:50px;"><i data-feather="check" style="width:30px;height:30px;color:white;"></i></div>';
            iconClass = 'text-success';
            break;
        case 'error':
            iconHtml = '<div class="rounded-circle bg-danger d-flex align-items-center justify-content-center" style="width:50px;height:50px;"><i data-feather="x" style="width:30px;height:30px;color:white;"></i></div>';
            iconClass = 'text-danger';
            break;
    }
    
    iconContainer.innerHTML = iconHtml;
    titleEl.textContent = title;
    messageEl.textContent = message;
    titleEl.className = iconClass;
    
    if (type !== 'uploading') {
        feather.replace();
    }
    
    modal.show();
    
    // Auto hide after 3 seconds for success/error
    if (type !== 'uploading') {
        setTimeout(function() {
            modal.hide();
        }, 3000);
    }
}

// ============================================
// DELETE PROFILE PHOTO
// ============================================

function deleteProfilePhoto() {
    if (!confirm('আপনি কি আপনার প্রোফাইল ছবি মুছে ফেলতে চান?')) {
        return;
    }
    
    // Get CSRF token
    const csrfToken = document.querySelector('input[name="csrf_token"]');
    if (!csrfToken) {
        alert('সিকিউরিটি ত্রুটি!');
        return;
    }
    
    // Show loading
    const deleteBtn = event.target.closest('button');
    if (deleteBtn) {
        deleteBtn.disabled = true;
        deleteBtn.innerHTML = '<i data-feather="loader" style="width:14px;height:14px" class="spinner-border spinner-border-sm me-1"></i> মুছছি...';
    }
    
    // Create form
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/admin/delete_profile_photo.php';
    
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = csrfToken.value;
    form.appendChild(csrfInput);
    
    document.body.appendChild(form);
    form.submit();
}

// ============================================
// SPINNER ANIMATION CSS
// ============================================

// Add spin animation for loader
const style = document.createElement('style');
style.textContent = `
    .spin {
        animation: spin 1s linear infinite;
    }
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);
</script>

<?php
// Include footer
require_once __DIR__ . '/../includes/footer.php';
?>