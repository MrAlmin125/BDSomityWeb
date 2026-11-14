<?php
// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/functions.php';
startSecureSession();

if (isAdminLoggedIn()) {
    header('Location: /admin/index.php');
    exit;
}

$error = '';
$remember_email = '';

// Check for remember me cookie (for email only)
if (isset($_COOKIE['remember_email']) && !empty($_COOKIE['remember_email'])) {
    $remember_email = htmlspecialchars($_COOKIE['remember_email']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে।';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember_me = isset($_POST['remember_me']) ? true : false;

        if (empty($email) || empty($password)) {
            $error = 'ইমেইল ও পাসওয়ার্ড দিন।';
        } else {
            try {
                $user = queryOne("SELECT * FROM users WHERE email = ? AND is_active = 1", 's', [$email]);
                
                if ($user && password_verify($password, $user['password'])) {
                    // Regenerate session ID for security
                    session_regenerate_id(true);
                    
                    // Set session variables
                    $_SESSION['admin_id'] = $user['id'];
                    $_SESSION['admin_role'] = $user['role'];
                    $_SESSION['admin_name'] = $user['name'];
                    $_SESSION['created'] = time();
                    
                    // Handle "Remember Me" - store email in cookie for convenience
                    if ($remember_me) {
                        // Set cookie for 30 days (email only, not password)
                        $cookie_expiry = time() + (86400 * 30); // 30 days
                        setcookie('remember_email', $email, $cookie_expiry, '/', '', false, true);
                    } else {
                        // Clear remember me cookie if not selected
                        setcookie('remember_email', '', time() - 3600, '/');
                    }
                    
                    // Update last login
                    $conn = conn();
                    $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $updateStmt->bind_param('i', $user['id']);
                    $updateStmt->execute();
                    $updateStmt->close();
                    
                    logActivity('login', 'Admin logged in');
                    
                    header('Location: /admin/index.php');
                    exit;
                } else {
                    $error = 'ইমেইল বা পাসওয়ার্ড ভুল হয়েছে।';
                    logActivity('login_failed', 'Failed login: ' . $email);
                }
            } catch (Exception $e) {
                $error = 'সিস্টেমে সমস্যা হয়েছে। আবার চেষ্টা করুন।';
                error_log('Login error: ' . $e->getMessage());
            }
        }
    }
}

// Get somity name
$somity_name = 'সোমিটি';
try {
    $somity_name = getSetting('somity_name', 'সোমিটি');
} catch (Exception $e) {
    $somity_name = 'সোমিটি';
}

// Get logo from settings
$logo_url = '';
try {
    $logo_url = getSetting('logo_url', '');
} catch (Exception $e) {
    $logo_url = '';
}

// Get favicon from settings
$favicon_url = '';
try {
    $favicon_url = getSetting('favicon_url', '');
} catch (Exception $e) {
    $favicon_url = '';
}

// Build full logo URL
$full_logo_url = '';
if (!empty($logo_url)) {
    if (strpos($logo_url, 'http://') === 0 || strpos($logo_url, 'https://') === 0) {
        $full_logo_url = $logo_url;
    } else {
        $logo_path = ltrim($logo_url, '/');
        if (strpos($logo_path, 'images/') === 0 || strpos($logo_path, 'uploads/') === 0) {
            $full_logo_url = '/' . $logo_path;
        } else {
            $full_logo_url = '/images/' . $logo_path;
        }
    }
}

// Build full favicon URL
$full_favicon_url = '';
if (!empty($favicon_url)) {
    if (strpos($favicon_url, 'http://') === 0 || strpos($favicon_url, 'https://') === 0) {
        $full_favicon_url = $favicon_url;
    } else {
        $favicon_path = ltrim($favicon_url, '/');
        if (strpos($favicon_path, 'images/') === 0 || strpos($favicon_path, 'uploads/') === 0) {
            $full_favicon_url = '/' . $favicon_path;
        } else {
            $full_favicon_url = '/images/' . $favicon_path;
        }
    }
}

// Forgot Password URL
$forgot_password_url = '/admin/forgot-password.php';
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>অ্যাডমিন লগইন — <?= htmlspecialchars($somity_name) ?></title>
    
    <!-- Favicon -->
    <?php if (!empty($full_favicon_url)): ?>
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($full_favicon_url) ?>">
    <link rel="shortcut icon" type="image/x-icon" href="<?= htmlspecialchars($full_favicon_url) ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($full_favicon_url) ?>">
    <?php else: ?>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="shortcut icon" type="image/x-icon" href="/favicon.ico">
    <link rel="apple-touch-icon" href="/favicon.ico">
    <?php endif; ?>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        /* ============================================
           LOADER SYSTEM - Full Page Loader
           ============================================ */
        #loader-wrapper {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #0a0a0a;
            z-index: 99999;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: opacity 0.6s ease, visibility 0.6s ease;
            opacity: 1;
            visibility: visible;
        }

        #loader-wrapper.loader-hidden {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }

        .loader-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 20px;
        }

        /* Animated Circle with Logo */
        .loader-circle {
            position: relative;
            width: 120px;
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .loader-circle svg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            transform: rotate(-90deg);
        }

        .loader-circle svg circle {
            fill: none;
            stroke-width: 4;
            stroke-linecap: round;
        }

        .loader-circle .bg-circle {
            stroke: rgba(255, 255, 255, 0.1);
        }

        .loader-circle .progress-circle {
            stroke: #f1c40f;
            stroke-dasharray: 340;
            stroke-dashoffset: 340;
            animation: spin-loader 1.8s cubic-bezier(0.65, 0, 0.35, 1) infinite;
        }

        @keyframes spin-loader {
            0% {
                stroke-dashoffset: 340;
                transform: rotate(0deg);
            }
            50% {
                stroke-dashoffset: 85;
                transform: rotate(180deg);
            }
            100% {
                stroke-dashoffset: 340;
                transform: rotate(360deg);
            }
        }

        /* Pulsing ring effect */
        .loader-circle::before {
            content: '';
            position: absolute;
            inset: -8px;
            border-radius: 50%;
            border: 2px solid rgba(241, 196, 15, 0.3);
            opacity: 0.2;
            animation: pulse-ring 2s ease-out infinite;
        }

        @keyframes pulse-ring {
            0% {
                transform: scale(0.95);
                opacity: 0.3;
            }
            50% {
                transform: scale(1.05);
                opacity: 0.1;
            }
            100% {
                transform: scale(0.95);
                opacity: 0.3;
            }
        }

        /* Logo inside circle */
        .loader-logo {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            object-fit: cover;
            z-index: 2;
            background: #0a0a0a;
            padding: 6px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.3);
            border: 2px solid rgba(241, 196, 15, 0.2);
        }

        .loader-logo-placeholder {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, #f1c40f, #e67e22);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: 700;
            z-index: 2;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.3);
            border: 2px solid rgba(241, 196, 15, 0.2);
        }

        /* Loading text */
        .loader-text {
            font-size: 16px;
            color: rgba(255, 255, 255, 0.6);
            font-weight: 500;
            letter-spacing: 0.5px;
            animation: text-pulse 1.5s ease-in-out infinite;
        }

        .loader-text strong {
            color: #f1c40f;
        }

        @keyframes text-pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        /* Loading dots animation */
        .loader-dots {
            display: inline-flex;
            gap: 4px;
            margin-left: 4px;
        }

        .loader-dots span {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #f1c40f;
            animation: dot-bounce 1.4s ease-in-out infinite both;
        }

        .loader-dots span:nth-child(1) { animation-delay: -0.32s; }
        .loader-dots span:nth-child(2) { animation-delay: -0.16s; }
        .loader-dots span:nth-child(3) { animation-delay: 0s; }

        @keyframes dot-bounce {
            0%, 80%, 100% { 
                transform: scale(0.6);
                opacity: 0.4;
            }
            40% { 
                transform: scale(1);
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .loader-circle {
                width: 90px;
                height: 90px;
            }
            .loader-logo,
            .loader-logo-placeholder {
                width: 55px;
                height: 55px;
                font-size: 22px;
            }
            .loader-text {
                font-size: 14px;
            }
        }

        @media (max-width: 480px) {
            .loader-circle {
                width: 75px;
                height: 75px;
            }
            .loader-logo,
            .loader-logo-placeholder {
                width: 45px;
                height: 45px;
                font-size: 18px;
                padding: 4px;
            }
            .loader-text {
                font-size: 12px;
            }
        }

        /* ============================================
           LOGIN PAGE STYLES
           ============================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Hind Siliguri', sans-serif;
            background: linear-gradient(135deg, #1B4F72 0%, #117A65 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            margin: 0;
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            background: white;
            border-radius: 20px;
            padding: 40px 36px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: fadeIn 0.5s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .login-logo {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #1B4F72, #2E86C1);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
            font-weight: 700;
            margin: 0 auto 20px;
        }
        .login-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 16px;
        }
        .login-logo.has-logo {
            background: none;
            padding: 0;
            overflow: hidden;
        }
        .login-title {
            text-align: center;
            font-weight: 700;
            font-size: 22px;
            color: #1B4F72;
            margin-bottom: 5px;
        }
        .login-sub {
            text-align: center;
            color: #566573;
            font-size: 13px;
            margin-bottom: 28px;
        }
        .form-control {
            border-radius: 10px;
            padding: 11px 14px;
            font-family: 'Hind Siliguri', sans-serif;
            border: 2px solid #e0e0e0;
            transition: border-color 0.3s, padding-right 0.3s, box-shadow 0.3s;
        }
        .form-control:focus {
            border-color: #2E86C1;
            box-shadow: 0 0 0 0.2rem rgba(46, 134, 193, 0.25);
        }
        .form-label {
            font-weight: 500;
            font-size: 13px;
            color: #2C3E50;
            margin-bottom: 6px;
        }
          .form-label .saved-badge {
            display: inline-block;
            background: #e8f5e9;
            color: #2e7d32;
            font-size: 10px;
            padding: 1px 8px;
            border-radius: 10px;
            font-weight: 500;
            margin-left: 4px;
            white-space: nowrap;
            transform: translateY(-2px);  
        }
        .form-label .saved-badge i {
            width: 12px;
            height: 12px;
        }
        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #1B4F72, #2E86C1);
            border: none;
            border-radius: 10px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            font-family: 'Hind Siliguri', sans-serif;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-login:hover {
            opacity: 0.85;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(27, 79, 114, 0.3);
        }
        .btn-login:active {
            transform: translateY(0);
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
            transition: color 0.3s;
        }
        .pw-toggle:hover {
            color: #1B4F72;
        }
        .alert {
            border-radius: 10px;
            font-size: 13px;
            padding: 10px 16px;
        }
        .member-link {
            font-size: 13px;
            color: #2E86C1;
            text-decoration: none;
            transition: all 0.3s;
        }
        .member-link:hover {
            color: #1B4F72;
            text-decoration: underline;
        }
        .position-relative {
            position: relative;
        }
        .input-group-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            pointer-events: none;
            transition: color 0.3s;
        }
        .input-with-icon {
            padding-left: 38px;
        }
        
       /* =====================
             Footer Text CSS
          ===================== */       
        
         .divider {
        border-top: 1px Dashed #2b7cb2;
        margin: 20px 0 15px 0;
        }
        .footer-text {
            text-align: center;
            margin-top: 15px;
        }
        .footer-text .copyright {
            font-size: 12px;
            color: #999;
            display: block;
            margin-top: 8px;
        }
        .footer-text .developer {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .footer-text .developer .dev-label {
            font-size: 14px;
            color: #666;
            font-weight: 500;
        }
        .footer-text .developer .dev-name {
            font-size: 13px;
            font-weight: 700;
            color: #1B4F72;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
            padding: 4px 14px;
            border-radius: 8px;
            background: linear-gradient(135deg, #e8f4fd, #d4e8ff);
        }
        .footer-text .developer .dev-name:hover {
            color: #117A65;
            transform: scale(1.05);
            box-shadow: 0 2px 10px rgba(27, 79, 114, 0.15);
        }
        .footer-text .developer .dev-name i {
            color: #1877f2;
        }
        .footer-text .developer .dev-name .fb-icon {
            width: 15px;
            height: 15px;
            transform: translateY(-2.5px);
        }
        
        /* ============================================
           REMEMBER ME & FORGOT PASSWORD - SIDE BY SIDE
           ============================================ */
        .remember-forgot-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: nowrap;
            gap: 8px 12px;
            margin-bottom: 20px;
            min-height: 30px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            user-select: none;
            flex-shrink: 0;
        }
        .remember-me input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #2E86C1;
            margin: 0;
            flex-shrink: 0;
        }
        .remember-me label {
            font-size: 14px;
            color: #2C3E50;
            cursor: pointer;
            margin: 0;
            font-weight: 500;
            user-select: none;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }
        .remember-me .remember-icon {
            color: #2E86C1;
            width: 14px;
            height: 14px;
        }
        .remember-me .saved-badge {
            display: inline-block;
            background: #e8f5e9;
            color: #2e7d32;
            font-size: 10px;
            padding: 1px 8px;
            border-radius: 10px;
            font-weight: 500;
            margin-left: 4px;
            white-space: nowrap;
        }
        .remember-me .saved-badge i {
            width: 12px;
            height: 12px;
        }
         

        /* Forgot Password Link */
        .forgot-password-link {
            font-size: 13px;
            color: #2E86C1;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .forgot-password-link:hover {
            color: #1B4F72;
            text-decoration: underline;
        }
        .forgot-password-link i {
            width: 15px;
            height: 15px;
        }

        /* ============================================
           EMAIL VALIDATION - FEATHER ICONS
           ============================================ */
        .email-input-wrapper {
            position: relative;
        }
        .email-input-wrapper .form-control {
            padding-right: 50px;
            transition: border-color 0.4s ease, box-shadow 0.4s ease, padding-right 0.3s ease;
        }
        
        /* Valid state */
        .email-input-wrapper .form-control.valid-email {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.15);
            background-image: linear-gradient(to right, #f8fff9, #ffffff);
        }
        .email-input-wrapper .form-control.valid-email:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.25rem rgba(40, 167, 69, 0.25);
        }
        
        /* Invalid state */
        .email-input-wrapper .form-control.invalid-email {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.15);
            background-image: linear-gradient(to right, #fff8f8, #ffffff);
        }
        .email-input-wrapper .form-control.invalid-email:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25);
        }
        
        /* Validation Icon Container - Feather Icons */
        .email-validation-icon {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.4s ease, transform 0.4s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: transparent;
        }
        .email-validation-icon.show {
            opacity: 1;
        }
        
        /* Valid - Green Check Circle with background */
        .email-validation-icon.valid {
            color: #28a745;
            background: rgba(40, 167, 69, 0.12);
            animation: popIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        }
        .email-validation-icon.valid svg {
            stroke: #28a745;
            fill: none;
            width: 20px;
            height: 20px;
        }
        
        /* Invalid - Red X Circle with background */
        .email-validation-icon.invalid {
            color: #dc3545;
            background: rgba(220, 53, 69, 0.12);
            animation: popIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        }
        .email-validation-icon.invalid svg {
            stroke: #dc3545;
            fill: none;
            width: 20px;
            height: 20px;
        }
        
        /* Pop-in animation */
        @keyframes popIn {
            0% {
                transform: translateY(-50%) scale(0.3);
                opacity: 0;
            }
            60% {
                transform: translateY(-50%) scale(1.15);
                opacity: 1;
            }
            100% {
                transform: translateY(-50%) scale(1);
                opacity: 1;
            }
        }
        
        /* Email status text with Feather icons */
        #emailStatusText {
            font-size: 12px;
            margin-top: 5px;
            transition: color 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: 400;
        }
        #emailStatusText .status-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 16px;
            height: 16px;
        }
        #emailStatusText .status-icon svg {
            width: 14px;
            height: 14px;
        }
        #emailStatusText.valid {
            color: #28a745;
        }
        #emailStatusText.valid .status-icon svg {
            stroke: #28a745;
        }
        #emailStatusText.invalid {
            color: #dc3545;
        }
        #emailStatusText.invalid .status-icon svg {
            stroke: #dc3545;
        }
        #emailStatusText.default {
            color: #6c757d;
        }
        #emailStatusText.default .status-icon svg {
            stroke: #6c757d;
        }
        
        /* Loading state for feather icon */
        .email-validation-icon.loading svg {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        /* ============================================
           RESPONSIVE - Side by side on all devices
           ============================================ */
        @media (max-width: 480px) {
            .login-card {
                padding: 30px 20px;
            }
            
            /* Keep side by side on mobile too */
            .remember-forgot-row {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
                gap: 6px;
                flex-wrap: nowrap;
            }
            
            .remember-me label {
                font-size: 12px;
                white-space: nowrap;
            }
            
            .remember-me input[type="checkbox"] {
                width: 16px;
                height: 16px;
            }
            
            .forgot-password-link {
                font-size: 11px;
                white-space: nowrap;
            }
            
            .forgot-password-link i {
                width: 13px;
                height: 13px;
            }
            
            .remember-me .saved-badge {
                font-size: 8px;
                padding: 0px 6px;
            }
            
            .remember-me .remember-icon {
                width: 12px;
                height: 12px;
            }
            
            .login-logo {
                width: 56px;
                height: 56px;
                font-size: 24px;
            }
            .email-validation-icon {
                right: 10px;
                width: 22px;
                height: 22px;
            }
            .email-validation-icon.valid svg,
            .email-validation-icon.invalid svg {
                width: 17px;
                height: 17px;
            }
            .email-input-wrapper .form-control {
                padding-right: 42px;
            }
            .footer-text .developer {
                flex-direction: flex;
                gap: 4px;
            }
            .footer-text .developer .dev-name {
                font-size: 13px;
                padding: 4px 12px;
            }
            .footer-text .developer .dev-name .fb-icon {
                width: 15px;
                height: 15px;
            }
        }

        @media (max-width: 380px) {
            .remember-me label {
                font-size: 10px;
            }
            
            .forgot-password-link {
                font-size: 10px;
            }
            
            .remember-me input[type="checkbox"] {
                width: 14px;
                height: 14px;
            }
            
            .forgot-password-link i {
                width: 11px;
                height: 11px;
            }
        }
    </style>
</head>
<body>

<!-- =====================================================
     LOADER SYSTEM - Full Page Loader
     ===================================================== -->
<div id="loader-wrapper">
    <div class="loader-container">
        <div class="loader-circle">
            <!-- Animated SVG Circle -->
            <svg viewBox="0 0 120 120">
                <circle class="bg-circle" cx="60" cy="60" r="54"/>
                <circle class="progress-circle" cx="60" cy="60" r="54"/>
            </svg>
            
            <!-- Dynamic Logo -->
            <?php if (!empty($full_logo_url)): ?>
                <img src="<?= htmlspecialchars($full_logo_url) ?>" alt="Logo" class="loader-logo">
            <?php else: ?>
                <div class="loader-logo-placeholder">স</div>
            <?php endif; ?>
        </div>
        
        <div class="loader-text">
            <strong><?= htmlspecialchars($somity_name) ?></strong>
            <span class="loader-dots">
                <span></span>
                <span></span>
                <span></span>
            </span>
        </div>
    </div>
</div>

<!-- =====================================================
     LOGIN CARD
     ===================================================== -->
<div class="login-card">
    <!-- Logo Section -->
    <?php if (!empty($full_logo_url)): ?>
        <div class="login-logo has-logo">
            <img src="<?= htmlspecialchars($full_logo_url) ?>" alt="<?= htmlspecialchars($somity_name) ?> Logo">
        </div>
    <?php else: ?>
        <div class="login-logo">স</div>
    <?php endif; ?>
    
    <h1 class="login-title"><?= htmlspecialchars($somity_name) ?></h1>
    <p class="login-sub">অ্যাডমিন প্যানেলে লগইন করুন</p>

    <?php if ($error): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2 mb-3">
        <i data-feather="alert-circle" style="width:16px;height:16px;flex-shrink:0"></i>
        <span><?= htmlspecialchars($error) ?></span>
    </div>
    <?php endif; ?>

    <form method="POST" novalidate autocomplete="on" id="loginForm">
        <?= csrfField() ?>

        <div class="mb-3">
            <label class="form-label">
                ইমেইল ঠিকানা:
                <?php if ($remember_email): ?>
                <span class="saved-badge">
                        <i data-feather="check-circle" style= "width:14px; height:14px; margin-right:3px;"></i> সংরক্ষিত
                    </span>
                <?php endif; ?>
            </label>
            <div class="email-input-wrapper">
                <span class="input-group-icon">
                    <i data-feather="mail" style="width:16px;height:16px"></i>
                </span>
                <input type="email" name="email" id="emailField" 
                       class="form-control input-with-icon"
                       value="<?= htmlspecialchars($remember_email ?: ($_POST['email'] ?? '')) ?>"
                       placeholder="admin@example.com" 
                       required 
                       autocomplete="username email"
                       autofocus>
                <span class="email-validation-icon" id="emailValidationIcon">
                    <i data-feather="check-circle"></i>
                </span>
            </div>
            <div id="emailStatusText" class="default">
                <span class="status-icon">
                    <i data-feather="info"></i>
                </span>
                একটি বৈধ ইমেইল ঠিকানা দিন
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label">পাসওয়ার্ড:</label>
            <div class="password-field-wrapper position-relative">
                <span class="input-group-icon">
                    <i data-feather="lock" style="width:16px;height:16px"></i>
                </span>
                <input type="password" name="password" id="passwordField" 
                       class="form-control input-with-icon"
                       placeholder="পাসওয়ার্ড দিন" 
                       required 
                       autocomplete="current-password"
                       style="padding-right:44px">
                <button type="button" class="pw-toggle" onclick="togglePw()" 
                        aria-label="Toggle password visibility">
                    <i data-feather="eye" id="eyeIcon" style="width:18px;height:18px"></i>
                </button>
            </div>
        </div>

        <!-- Remember Me & Forgot Password - Side by Side -->
        <div class="remember-forgot-row">
            <div class="remember-me">
                <input type="checkbox" name="remember_me" id="remember_me" 
                       <?= $remember_email ? 'checked' : '' ?>>
                <label for="remember_me">
                 <!--   <i data-feather="check-circle" class="remember-icon"></i>   -->
                    ইমেইল মনে রাখুন
                    <?php if ($remember_email): ?>
              <!--      <span class="saved-badge">
                        <i data-feather="check-circle"></i> সংরক্ষিত
                    </span>      -->
                    <?php endif; ?>
                </label>
            </div>
            
            <!-- Forgot Password Link -->
            <a href="<?= htmlspecialchars($forgot_password_url) ?>" class="forgot-password-link">
                <i data-feather="lock" style="width:14px; height:14px; transform:translateY(-1px);"></i>
                পাসওয়ার্ড ভুলে গেছেন?
            </a>
        </div>

        <button type="submit" class="btn-login" id="loginBtn">
            <i data-feather="user" style="width:18px;height:18px;vertical-align:middle;margin-right:6px"></i>
            লগইন করুন
        </button>
    </form>

    <div class="text-center mt-4">
        <a href="/member/login.php" class="member-link">
            সদস্য হিসেবে লগইন করুন →
        </a>
    </div>

    <div class="divider"></div>
    
    <div class="footer-text">
        <small class="copyright">© <?= date('Y') ?> <?= htmlspecialchars($somity_name) ?></small>
        <div class="developer">
            <span class="dev-label">Developed By</span>
            <a href="https://amin.free.nf/" target="_blank" rel="noopener noreferrer" class="dev-name">
                <i class="fa-brands fa-edge-legacy" style="color: rgb(27, 79, 114); position: relative; top: -1px;"></i>
                Mr.Amin
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // ============================================
    // LOADER SYSTEM - Hide loader when page is ready
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        // Hide loader after a minimum display time for smooth animation
        const loader = document.getElementById('loader-wrapper');
        
        // Minimum display time (800ms) for better UX
        setTimeout(function() {
            loader.classList.add('loader-hidden');
            
            // Remove from DOM after transition completes
            setTimeout(function() {
                loader.style.display = 'none';
            }, 600);
        }, 800);

        // Initialize feather icons
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
        
        // Setup email validation
        setupEmailValidation();
        
        // Auto-focus logic
        const emailField = document.getElementById('emailField');
        const passwordField = document.getElementById('passwordField');
        
        if (emailField && !emailField.value) {
            setTimeout(function() {
                emailField.focus();
            }, 100);
        } else if (emailField && emailField.value && passwordField) {
            setTimeout(function() {
                passwordField.focus();
            }, 150);
        }
    });

    // ============================================
    // EMAIL VALIDATION WITH FEATHER ICONS
    // ============================================
    function setupEmailValidation() {
        const emailField = document.getElementById('emailField');
        const validationIcon = document.getElementById('emailValidationIcon');
        const statusText = document.getElementById('emailStatusText');
        
        if (!emailField || !validationIcon) return;
        
        // Function to validate email and show tick
        function validateEmail() {
            const email = emailField.value.trim();
            const isValid = email.length > 0 && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
            
            // Empty field - hide icon
            if (email.length === 0) {
                validationIcon.classList.remove('show', 'valid', 'invalid', 'loading');
                emailField.classList.remove('valid-email', 'invalid-email');
                statusText.className = 'default';
                statusText.innerHTML = '<span class="status-icon"><i data-feather="info"></i></span> একটি সঠিক ইমেইল দিন।';
                if (typeof feather !== 'undefined') {
                    feather.replace();
                }
                return;
            }
            
            // Show icon with pop animation
            validationIcon.classList.add('show');
            
            // Check if email format is valid
            if (isValid) {
                // Valid - Green Check Circle
                validationIcon.classList.remove('invalid', 'loading');
                validationIcon.classList.add('valid');
                emailField.classList.remove('invalid-email');
                emailField.classList.add('valid-email');
                
                // Set feather icon - check-circle
                validationIcon.innerHTML = '<i data-feather="check-circle"></i>';
                if (typeof feather !== 'undefined') {
                    feather.replace();
                }
                
                // Update status text
                statusText.className = 'valid';
                statusText.innerHTML = '<span class="status-icon"><i data-feather="check-circle"></i></span> আপনার ইমেইল সঠিক। ';
                if (typeof feather !== 'undefined') {
                    feather.replace();
                }
                
            } else {
                // Invalid - Red X Circle
                validationIcon.classList.remove('valid', 'loading');
                validationIcon.classList.add('invalid');
                emailField.classList.remove('valid-email');
                emailField.classList.add('invalid-email');
                
                // Set feather icon - x-circle
                validationIcon.innerHTML = '<i data-feather="x-circle"></i>';
                if (typeof feather !== 'undefined') {
                    feather.replace();
                }
                
                // Update status text
                statusText.className = 'invalid';
                statusText.innerHTML = '<span class="status-icon"><i data-feather="x-circle"></i></span> অনুগ্রহ করে সঠিক ইমেইল দিন।';
                if (typeof feather !== 'undefined') {
                    feather.replace();
                }
            }
        }
        
        // Listen to input events
        emailField.addEventListener('input', validateEmail);
        emailField.addEventListener('blur', validateEmail);
        
        // Initial validation if there's a value
        if (emailField.value) {
            setTimeout(validateEmail, 150);
        }
    }

    // Toggle password visibility
    function togglePw() {
        const passwordField = document.getElementById('passwordField');
        const eyeIcon = document.getElementById('eyeIcon');
        
        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            eyeIcon.setAttribute('data-feather', 'eye-off');
        } else {
            passwordField.type = 'password';
            eyeIcon.setAttribute('data-feather', 'eye');
        }
        
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    }

    // Keyboard shortcut: Enter key to submit
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            const activeElement = document.activeElement;
            if (activeElement && (activeElement.tagName === 'INPUT')) {
                const form = activeElement.closest('form');
                if (form) {
                    form.submit();
                }
            }
        }
    });
</script>
</body>
</html>