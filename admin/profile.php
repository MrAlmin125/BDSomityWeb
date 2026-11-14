<?php
// =====================================================
// admin/profile.php - Admin Profile View (MOBILE OPTIMIZED)
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

$pageTitle = 'প্রোফাইল';

// Get admin info
$admin = getCurrentAdmin();
if (!$admin) {
    header('Location: /admin/logout.php');
    exit;
}

// ============================================
// ACCURATE IP ADDRESS DETECTION
// ============================================
function getRealIP() {
    $ip = 'Unknown';
    
    if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    } elseif (isset($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = $_SERVER['HTTP_X_REAL_IP'];
    } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $ip = 'Unknown';
    }
    
    return $ip;
}

// ============================================
// ACCURATE USER AGENT PARSING
// ============================================
function parseUserAgent($userAgent) {
    if (empty($userAgent)) {
        return [
            'browser' => 'Unknown',
            'browser_version' => 'Unknown',
            'os' => 'Unknown',
            'os_version' => 'Unknown',
            'device' => 'Unknown',
            'device_model' => 'Unknown'
        ];
    }
    
    $result = [
        'browser' => 'Unknown',
        'browser_version' => 'Unknown',
        'os' => 'Unknown',
        'os_version' => 'Unknown',
        'device' => 'Unknown',
        'device_model' => 'Unknown'
    ];
    
    // Detect Browser
    if (preg_match('/Edg\/(\d+\.\d+)/', $userAgent, $matches)) {
        $result['browser'] = 'Microsoft Edge';
        $result['browser_version'] = $matches[1];
    } elseif (preg_match('/OPR\/(\d+\.\d+)/', $userAgent, $matches) || preg_match('/Opera\/(\d+\.\d+)/', $userAgent, $matches)) {
        $result['browser'] = 'Opera';
        $result['browser_version'] = $matches[1] ?? 'Unknown';
    } elseif (preg_match('/Chrome\/(\d+\.\d+)/', $userAgent, $matches) && !preg_match('/Edg|OPR|Opera/', $userAgent)) {
        $result['browser'] = 'Google Chrome';
        $result['browser_version'] = $matches[1];
    } elseif (preg_match('/Firefox\/(\d+\.\d+)/', $userAgent, $matches)) {
        $result['browser'] = 'Mozilla Firefox';
        $result['browser_version'] = $matches[1];
    } elseif (preg_match('/Safari\/(\d+\.\d+)/', $userAgent, $matches) && !preg_match('/Chrome|Edg|OPR/', $userAgent)) {
        $result['browser'] = 'Apple Safari';
        $result['browser_version'] = $matches[1] ?? 'Unknown';
    }
    
    // Detect Device
    if (preg_match('/iPhone/', $userAgent)) {
        $result['device'] = 'iPhone';
        if (preg_match('/iPhone14/', $userAgent)) {
            $result['device_model'] = 'iPhone 14';
        } elseif (preg_match('/iPhone13/', $userAgent)) {
            $result['device_model'] = 'iPhone 13';
        } elseif (preg_match('/iPhone12/', $userAgent)) {
            $result['device_model'] = 'iPhone 12';
        } elseif (preg_match('/iPhone11/', $userAgent)) {
            $result['device_model'] = 'iPhone 11';
        } elseif (preg_match('/iPhone10/', $userAgent)) {
            $result['device_model'] = 'iPhone X';
        } elseif (preg_match('/iPhone8/', $userAgent)) {
            $result['device_model'] = 'iPhone 8';
        } elseif (preg_match('/iPhone7/', $userAgent)) {
            $result['device_model'] = 'iPhone 7';
        } else {
            $result['device_model'] = 'iPhone';
        }
    } elseif (preg_match('/iPad/', $userAgent)) {
        $result['device'] = 'iPad';
        if (preg_match('/iPad Pro/', $userAgent)) {
            $result['device_model'] = 'iPad Pro';
        } elseif (preg_match('/iPad Air/', $userAgent)) {
            $result['device_model'] = 'iPad Air';
        } elseif (preg_match('/iPad mini/', $userAgent)) {
            $result['device_model'] = 'iPad mini';
        } else {
            $result['device_model'] = 'iPad';
        }
    } elseif (preg_match('/Android/', $userAgent)) {
        if (preg_match('/Mobile/', $userAgent)) {
            $result['device'] = 'Android Phone';
            if (preg_match('/; (.*?) Build\//', $userAgent, $matches)) {
                $model = $matches[1];
                $model = preg_replace('/\(.*?\)/', '', $model);
                $model = trim($model);
                $model = preg_replace('/SM-|Nokia |LG-|HTC |Samsung /', '', $model);
                $result['device_model'] = $model ?: 'Android Phone';
            }
        } else {
            $result['device'] = 'Android Tablet';
            if (preg_match('/; (.*?) Build\//', $userAgent, $matches)) {
                $result['device_model'] = trim($matches[1]) ?: 'Android Tablet';
            }
        }
    } elseif (preg_match('/Mobile/', $userAgent)) {
        $result['device'] = 'Mobile Device';
        $result['device_model'] = 'Unknown Mobile';
    } else {
        $result['device'] = 'Desktop';
        $result['device_model'] = 'PC / Laptop';
    }
    
    // Detect OS
    if (preg_match('/Windows NT 10\.0/', $userAgent)) {
        $result['os'] = 'Windows 11';
        $result['os_version'] = '10.0';
    } elseif (preg_match('/Windows NT 6\.1/', $userAgent)) {
        $result['os'] = 'Windows 7';
        $result['os_version'] = '6.1';
    } elseif (preg_match('/Mac OS X 10_15/', $userAgent)) {
        $result['os'] = 'macOS Catalina';
        $result['os_version'] = '10.15';
    } elseif (preg_match('/Mac OS X 10_14/', $userAgent)) {
        $result['os'] = 'macOS Mojave';
        $result['os_version'] = '10.14';
    } elseif (preg_match('/iPhone OS (\d+[._]\d+)/', $userAgent, $matches)) {
        $result['os'] = 'iOS';
        $result['os_version'] = str_replace('_', '.', $matches[1]);
    } elseif (preg_match('/Android (\d+\.\d+)/', $userAgent, $matches)) {
        $result['os'] = 'Android';
        $result['os_version'] = $matches[1];
    } elseif (preg_match('/Linux/', $userAgent) && !preg_match('/Android/', $userAgent)) {
        $result['os'] = 'Linux';
        $result['os_version'] = 'Unknown';
    }
    
    return $result;
}

// ============================================
// Get login history
// ============================================
try {
    $conn = conn();
    $loginHistory = [];
    
    $stmt = $conn->prepare("
        SELECT created_at, ip_address, user_agent 
        FROM activity_logs 
        WHERE user_id = ? AND user_type = 'admin' AND action = 'login' 
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    $stmt->bind_param('i', $admin['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $loginHistory[] = $row;
    }
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM activity_logs WHERE user_id = ? AND user_type = 'admin' AND action = 'login'");
    $stmt->bind_param('i', $admin['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $totalLogins = $result->fetch_assoc()['total'] ?? 0;
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM activity_logs WHERE user_id = ? AND user_type = 'admin'");
    $stmt->bind_param('i', $admin['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $totalActivities = $result->fetch_assoc()['total'] ?? 0;
    
} catch (Exception $e) {
    $loginHistory = [];
    $totalLogins = 0;
    $totalActivities = 0;
}

$lastLogin = !empty($loginHistory) ? $loginHistory[0] : null;
$realIP = getRealIP();

// Include header
require_once __DIR__ . '/../includes/header.php';

// Show flash messages
$flash = getFlash();
if ($flash): ?>
    <div class="container-fluid px-3 px-md-4 mt-2 mt-md-3">
        <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
            <i data-feather="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-circle' ?>" style="width:16px;height:16px;margin-right:8px;"></i>
            <?= $flash['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
<?php endif; ?>

<div class="container-fluid px-3 px-md-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-3 mb-md-4">
        <h4 class="mb-0 d-flex align-items-center">
            <i data-feather="user" style="width:22px;height:22px;margin-right:8px;color:#1B4F72;"></i> 
            আমার প্রোফাইল
        </h4>
        <span class="badge bg-success d-flex align-items-center gap-1">
            <span class="live-dot"></span>
            <span class="d-none d-sm-inline">লাইভ</span>
        </span>
    </div>

    <!-- Profile Card -->
    <div class="profile-card-wrapper">
        <div class="profile-card">
            <!-- Cover Image -->
            <div class="profile-cover"></div>
            
            <!-- Profile Photo -->
            <div class="profile-photo-wrapper">
                <?php if (!empty($admin['profile_photo'])): ?>
                    <?php 
                    $photoPath = $admin['profile_photo'];
                    if (strpos($photoPath, 'http') !== 0) {
                        if (strpos($photoPath, '/') !== 0) {
                            $photoPath = '/' . $photoPath;
                        }
                        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
                        $baseUrl = $protocol . $_SERVER['HTTP_HOST'];
                        $fullImageUrl = $baseUrl . $photoPath;
                    } else {
                        $fullImageUrl = $photoPath;
                    }
                    ?>
                    <img src="<?= htmlspecialchars($fullImageUrl) ?>" 
                         alt="Profile Photo" 
                         id="profileImage"
                         class="profile-photo"
                         onerror="this.style.display='none'; document.getElementById('fallbackAvatar').style.display='flex';">
                    
                    <?php
                    $initial = mb_substr($admin['name'], 0, 1);
                    $colors = ['#1B4F72', '#2E86C1', '#117A65', '#C0392B', '#8E44AD', '#D35400'];
                    $color = $colors[abs(crc32($admin['name'])) % count($colors)];
                    ?>
                    <div id="fallbackAvatar" class="profile-photo-fallback" style="background:<?= $color ?>;display:none;">
                        <?= htmlspecialchars($initial) ?>
                    </div>
                <?php else: ?>
                    <?php
                    $initial = mb_substr($admin['name'], 0, 1);
                    $colors = ['#1B4F72', '#2E86C1', '#117A65', '#C0392B', '#8E44AD', '#D35400'];
                    $color = $colors[abs(crc32($admin['name'])) % count($colors)];
                    ?>
                    <div class="profile-photo-fallback" style="background:<?= $color ?>;">
                        <?= htmlspecialchars($initial) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Profile Info -->
            <div class="profile-info text-center">
                <h5 class="profile-name"><?= htmlspecialchars($admin['name']) ?></h5>
                <p class="profile-role">
                    <span class="badge bg-primary"><?= htmlspecialchars($admin['role']) ?></span>
                </p>
                <p class="profile-email">
                    <i data-feather="mail" style="width:14px;height:14px;"></i>
                    <?= htmlspecialchars($admin['email']) ?>
                </p>
            </div>

            <!-- Action Buttons - SIDE BY SIDE (FLEX) -->
            <div class="profile-actions">
                <a href="/admin/edit_profile.php" class="btn btn-primary btn-action">
                    <i data-feather="edit-2" style="width:16px;height:16px;"></i>
                    <span>প্রোফাইল সম্পাদনা</span>
                </a>
                <a href="/admin/change_password.php" class="btn btn-outline-secondary btn-action">
                    <i data-feather="lock" style="width:16px;height:16px;"></i>
                    <span>পাসওয়ার্ড</span>
                </a>
            </div>

            <!-- Quick Stats -->
            <div class="profile-stats">
                <div class="stat-item">
                    <div class="stat-value"><?= toBengaliNumber($totalLogins) ?></div>
                    <div class="stat-label">লগইন</div>
                </div>
                <div class="stat-divider"></div>
                <div class="stat-item">
                    <div class="stat-value"><?= toBengaliNumber($totalActivities) ?></div>
                    <div class="stat-label">কার্যক্রম</div>
                </div>
                <div class="stat-divider"></div>
                <div class="stat-item">
                    <div class="stat-value"><?= $lastLogin ? timeAgo($lastLogin['created_at']) : '-' ?></div>
                    <div class="stat-label">শেষ লগইন</div>
                </div>
            </div>
        </div>
    </div>

    <!-- All Info Cards - FULL WIDTH -->
    <div class="row g-3 g-md-4 mt-2">
        <!-- Profile Details -->
        <div class="col-12">
            <div class="info-card">
                <div class="info-card-header">
                    <i data-feather="info" style="width:18px;height:18px;"></i>
                    <span>প্রোফাইল তথ্য</span>
                </div>
                <div class="info-card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">পূর্ণ নাম</span>
                            <span class="info-value"><?= htmlspecialchars($admin['name']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">ইমেইল</span>
                            <span class="info-value text-break"><?= htmlspecialchars($admin['email']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">রোল</span>
                            <span class="info-value">
                                <span class="badge bg-<?= $admin['role'] === 'admin' ? 'primary' : 'info' ?>">
                                    <?= $admin['role'] === 'admin' ? 'অ্যাডমিন' : 'মডারেটর' ?>
                                </span>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">অ্যাকাউন্ট তৈরি</span>
                            <span class="info-value"><?= formatDate($admin['created_at'] ?? date('Y-m-d'), 'd M, Y h:i A') ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Session Info -->
        <div class="col-12">
            <div class="info-card">
                <div class="info-card-header">
                    <i data-feather="shield" style="width:18px;height:18px;"></i>
                    <span>সেশন তথ্য</span>
                </div>
                <div class="info-card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">সেশন আইডি</span>
                            <span class="info-value"><code class="session-id"><?= session_id() ?></code></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">লগইন সময়</span>
                            <span class="info-value"><?= formatDate($_SESSION['login_time'] ?? date('Y-m-d H:i:s'), 'd M, Y h:i A') ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">আইপি ঠিকানা</span>
                            <span class="info-value"><code><?= htmlspecialchars($realIP) ?></code></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">ডিভাইস</span>
                            <span class="info-value">
                                <?php 
                                $currentDevice = parseUserAgent($_SERVER['HTTP_USER_AGENT'] ?? '');
                                ?>
                                <span class="badge bg-info"><?= $currentDevice['device'] ?></span>
                                <?php if ($currentDevice['device_model'] != 'Unknown' && $currentDevice['device_model'] != 'PC / Laptop'): ?>
                                <span class="badge bg-secondary"><?= $currentDevice['device_model'] ?></span>
                                <?php endif; ?>
                                <span class="badge bg-secondary"><?= $currentDevice['browser'] ?></span>
                                <span class="badge bg-dark"><?= $currentDevice['os'] ?></span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Login History -->
        <div class="col-12">
            <div class="info-card">
                <div class="info-card-header d-flex justify-content-between align-items-center">
                    <div>
                        <i data-feather="clock" style="width:18px;height:18px;"></i>
                        <span>লগইন ইতিহাস</span>
                    </div>
                    <span class="badge bg-secondary"><?= count($loginHistory) ?> টি</span>
                </div>
                <div class="info-card-body p-0">
                    <div class="table-responsive">
                        <table class="table history-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>তারিখ ও সময়</th>
                                    <th>আইপি</th>
                                    <th>ডিভাইস</th>
                                    <th>ব্রাউজার</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($loginHistory)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-3">কোনো লগইন ইতিহাস নেই</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach (array_slice($loginHistory, 0, 10) as $index => $log): 
                                    $deviceInfo = parseUserAgent($log['user_agent'] ?? '');
                                ?>
                                <tr>
                                    <td>#<?= $index + 1 ?></td>
                                    <td>
                                        <div class="fw-bold small"><?= formatDate($log['created_at'], 'd M, Y') ?></div>
                                        <div class="small text-muted"><?= formatDate($log['created_at'], 'h:i A') ?></div>
                                    </td>
                                    <td><code class="small"><?= htmlspecialchars(substr($log['ip_address'] ?? 'Unknown', 0, 15)) ?></code></td>
                                    <td>
                                        <span class="badge bg-info"><?= $deviceInfo['device'] ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= $deviceInfo['browser'] ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* ============================================
   PROFILE CARD STYLES
   ============================================ */

.profile-card-wrapper {
    margin-bottom: 24px;
}

.profile-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    overflow: hidden;
    position: relative;
}

/* Cover Image */
.profile-cover {
    height: 100px;
    background: linear-gradient(135deg, #1B4F72 0%, #2E86C1 100%);
    position: relative;
}

.profile-cover::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 40px;
    background: linear-gradient(to top, rgba(255,255,255,1), rgba(255,255,255,0));
}

/* Profile Photo */
.profile-photo-wrapper {
    position: relative;
    margin-top: -50px;
    text-align: center;
}

.profile-photo {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid white;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    background: white;
}

.profile-photo-fallback {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 40px;
    font-weight: 600;
    margin: 0 auto;
    border: 4px solid white;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

/* Profile Info */
.profile-info {
    padding: 12px 20px 8px;
}

.profile-name {
    font-size: 20px;
    font-weight: 700;
    color: #1C2833;
    margin-bottom: 4px;
}

.profile-role {
    margin-bottom: 4px;
}

.profile-email {
    font-size: 13px;
    color: #566573;
    margin-bottom: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

/* Action Buttons - SIDE BY SIDE */
.profile-actions {
    display: flex;
    gap: 10px;
    justify-content: center;
    padding: 12px 20px 0;
}

.btn-action {
    padding: 8px 16px;
    border-radius: 25px;
    font-size: 13px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    transition: all 0.3s ease;
    flex: 1;
    max-width: 200px;
    min-width: 120px;
}

.btn-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.btn-action:active {
    transform: translateY(0);
}

/* Quick Stats */
.profile-stats {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 16px 20px 20px;
    gap: 16px;
    border-top: 1px solid #f0f0f0;
    margin-top: 12px;
}

.stat-item {
    text-align: center;
    flex: 1;
}

.stat-value {
    font-size: 18px;
    font-weight: 700;
    color: #1C2833;
}

.stat-label {
    font-size: 11px;
    color: #566573;
    margin-top: 2px;
}

.stat-divider {
    width: 1px;
    height: 30px;
    background: #e0e0e0;
}

/* Info Cards */
.info-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    overflow: hidden;
}

.info-card-header {
    padding: 12px 16px;
    background: #f8f9fa;
    border-bottom: 1px solid #f0f0f0;
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    font-size: 14px;
    color: #2C3E50;
}

.info-card-body {
    padding: 12px 16px;
}

/* Info Grid */
.info-grid {
    display: flex;
    flex-direction: column;
    gap: 0;
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f5f5f5;
    gap: 12px;
}

.info-item:last-child {
    border-bottom: none;
}

.info-label {
    font-size: 13px;
    color: #566573;
    font-weight: 500;
    white-space: nowrap;
    min-width: 100px;
}

.info-value {
    font-size: 13px;
    color: #1C2833;
    text-align: right;
    word-break: break-word;
    flex: 1;
}

/* History Table */
.history-table {
    font-size: 13px;
    margin-bottom: 0;
}

.history-table th {
    background: #f8f9fa;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #566573;
    padding: 10px 12px;
    border-bottom: 2px solid #e0e0e0;
}

.history-table td {
    padding: 8px 12px;
    vertical-align: middle;
    border-bottom: 1px solid #f0f0f0;
}

/* Live Dot */
.live-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #28a745;
    display: inline-block;
    animation: pulse-dot 1.5s ease-in-out infinite;
}

@keyframes pulse-dot {
    0% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(1.3); }
    100% { opacity: 1; transform: scale(1); }
}

/* Session ID */
.session-id {
    font-size: 10px;
    background: #f0f0f0;
    padding: 2px 8px;
    border-radius: 4px;
    word-break: break-all;
}

/* Badge */
.badge i {
    margin-right: 4px;
}

/* ============================================
   MOBILE RESPONSIVE
   ============================================ */

@media (max-width: 576px) {
    .profile-cover {
        height: 70px;
    }
    
    .profile-photo,
    .profile-photo-fallback {
        width: 80px;
        height: 80px;
        font-size: 32px;
    }
    
    .profile-photo-wrapper {
        margin-top: -40px;
    }
    
    .profile-name {
        font-size: 17px;
    }
    
    .profile-email {
        font-size: 12px;
    }
    
    /* Buttons - Side by Side on Mobile */
    .profile-actions {
        padding: 8px 16px 0;
        gap: 8px;
    }
    
    .btn-action {
        padding: 8px 12px;
        font-size: 12px;
        min-width: unset;
        flex: 1;
        max-width: unset;
        border-radius: 20px;
    }
    
    .btn-action span {
        font-size: 11px;
    }
    
    .btn-action i {
        width: 14px;
        height: 14px;
    }
    
    .profile-stats {
        padding: 12px 16px 16px;
        gap: 8px;
        flex-wrap: nowrap;
    }
    
    .stat-value {
        font-size: 15px;
    }
    
    .stat-label {
        font-size: 9px;
    }
    
    .stat-divider {
        height: 20px;
    }
    
    .info-card {
        border-radius: 10px;
    }
    
    .info-card-header {
        padding: 10px 14px;
        font-size: 13px;
    }
    
    .info-card-body {
        padding: 8px 14px;
    }
    
    .info-item {
        padding: 8px 0;
        flex-wrap: wrap;
        gap: 4px;
    }
    
    .info-label {
        font-size: 12px;
        min-width: 100%;
        white-space: normal;
    }
    
    .info-value {
        font-size: 12px;
        text-align: left;
        width: 100%;
        padding-left: 0;
    }
    
    .history-table {
        font-size: 11px;
    }
    
    .history-table th {
        font-size: 9px;
        padding: 6px 8px;
    }
    
    .history-table td {
        padding: 6px 8px;
        font-size: 10px;
    }
    
    .history-table .badge {
        font-size: 9px;
        padding: 2px 6px;
    }
}

@media (max-width: 400px) {
    .profile-photo,
    .profile-photo-fallback {
        width: 70px;
        height: 70px;
        font-size: 28px;
    }
    
    .profile-photo-wrapper {
        margin-top: -35px;
    }
    
    .profile-name {
        font-size: 15px;
    }
    
    .btn-action {
        padding: 6px 10px;
        font-size: 11px;
    }
    
    .btn-action span {
        font-size: 10px;
    }
    
    .stat-value {
        font-size: 13px;
    }
    
    .stat-label {
        font-size: 8px;
    }
}

@media (min-width: 577px) and (max-width: 768px) {
    .profile-photo,
    .profile-photo-fallback {
        width: 90px;
        height: 90px;
        font-size: 36px;
    }
    
    .profile-photo-wrapper {
        margin-top: -45px;
    }
    
    .btn-action {
        padding: 8px 14px;
        font-size: 12px;
        min-width: 120px;
    }
}

/* Tablet */
@media (min-width: 769px) and (max-width: 992px) {
    .info-item {
        flex-wrap: wrap;
        gap: 4px;
    }
    
    .info-label {
        min-width: 100%;
    }
    
    .info-value {
        text-align: left;
        width: 100%;
        padding-left: 0;
    }
}

/* Fallback */
#fallbackAvatar {
    display: none;
}
</style>

<script>
// Auto-dismiss flash messages
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.style.display = 'none';
            }, 500);
        }, 5000);
    });
});
</script>

<?php
// Include footer
require_once __DIR__ . '/../includes/footer.php';
?>