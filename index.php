<?php
// =====================================================
// index.php - Home Page (Open for Everyone)
// =====================================================
    
// index.php (root) — redirect to admin
// =====================================================
//header('Location: /admin/login.php');
//exit;
    
// No login required - open for all visitors
$pageTitle = 'হোম';

// Get settings from database
require_once __DIR__ . '/includes/functions.php';

// Get somity name and logo from settings
$somity_name = 'সোমিটি';
$logo_url = '';
$favicon_url = '';

try {
    // Get somity name
    $name_result = queryOne("SELECT key_value FROM settings WHERE key_name = 'somity_name'");
    if ($name_result && !empty($name_result['key_value'])) {
        $somity_name = $name_result['key_value'];
    }
    
    // Get logo URL
    $logo_result = queryOne("SELECT key_value FROM settings WHERE key_name = 'logo_url'");
    if ($logo_result && !empty($logo_result['key_value'])) {
        $logo_url = $logo_result['key_value'];
    }
    
    // Get favicon URL
    $favicon_result = queryOne("SELECT key_value FROM settings WHERE key_name = 'favicon_url'");
    if ($favicon_result && !empty($favicon_result['key_value'])) {
        $favicon_url = $favicon_result['key_value'];
    }
} catch (Exception $e) {
    // Use defaults if settings not found
    $somity_name = 'সোমিটি';
    $logo_url = '';
    $favicon_url = '';
}

// Get stats from database (optional)
$total_members = 0;
$total_deposits = 0;
$total_loans = 0;

try {
    $members_count = queryOne("SELECT COUNT(*) as count FROM members WHERE status = 'active'");
    $total_members = $members_count['count'] ?? 0;
    
    $deposits_sum = queryOne("SELECT SUM(amount) as total FROM deposits");
    $total_deposits = $deposits_sum['total'] ?? 0;
    
    $loans_count = queryOne("SELECT COUNT(*) as count FROM loans WHERE status = 'active'");
    $total_loans = $loans_count['count'] ?? 0;
} catch (Exception $e) {
    // Use defaults if tables don't exist yet
    $total_members = 0;
    $total_deposits = 0;
    $total_loans = 0;
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
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($somity_name) ?> — সমিতি ব্যবস্থাপনা সিস্টেম</title>
    
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
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6/css/all.min.css">
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
           GLOBAL STYLES
           ============================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Hind Siliguri', sans-serif;
            min-height: 100vh;
            background: #0a0a0a;
            position: relative;
            overflow-x: hidden;
        }
        
        /* Animated Background */
        .bg-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            background: linear-gradient(-45deg, #0f0c29, #302b63, #24243e, #1a1a2e);
            background-size: 400% 400%;
            animation: gradientBG 20s ease infinite;
        }
        
        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        /* Floating Particles */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            pointer-events: none;
            overflow: hidden;
        }
        
        .particle {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.04);
            animation: floatParticle linear infinite;
        }
        
        @keyframes floatParticle {
            0% {
                transform: translateY(100vh) rotate(0deg);
                opacity: 0;
            }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% {
                transform: translateY(-100vh) rotate(720deg);
                opacity: 0;
            }
        }
        
        .particle:nth-child(1) { width: 8px; height: 8px; left: 10%; animation-duration: 20s; animation-delay: 0s; }
        .particle:nth-child(2) { width: 14px; height: 14px; left: 25%; animation-duration: 25s; animation-delay: 2s; }
        .particle:nth-child(3) { width: 10px; height: 10px; left: 40%; animation-duration: 22s; animation-delay: 4s; }
        .particle:nth-child(4) { width: 18px; height: 18px; left: 55%; animation-duration: 28s; animation-delay: 1s; }
        .particle:nth-child(5) { width: 6px; height: 6px; left: 70%; animation-duration: 18s; animation-delay: 3s; }
        .particle:nth-child(6) { width: 12px; height: 12px; left: 85%; animation-duration: 21s; animation-delay: 5s; }
        .particle:nth-child(7) { width: 9px; height: 9px; left: 50%; animation-duration: 24s; animation-delay: 2.5s; }
        .particle:nth-child(8) { width: 16px; height: 16px; left: 15%; animation-duration: 26s; animation-delay: 4.5s; }
        
        .container {
            position: relative;
            z-index: 2;
            padding: 20px;
        }
        
        /* ============================================
           GLASS HEADER - FIXED FOR MOBILE
           ============================================ */
        .glass-header {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            padding: 12px 20px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            transition: all 0.3s;
        }
        
        .glass-header:hover {
            border-color: rgba(255, 255, 255, 0.15);
        }
        
        .glass-header .header-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        
        /* Brand - Full name always visible */
        .glass-header .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            text-decoration: none;
            flex-shrink: 0;
        }
        
        .glass-header .brand-icon {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, #f1c40f, #e67e22);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 700;
            color: white;
            box-shadow: 0 4px 15px rgba(241, 196, 15, 0.3);
            animation: pulseGlow 3s ease-in-out infinite;
            flex-shrink: 0;
            overflow: hidden;
        }
        
        .glass-header .brand-icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 12px;
        }
        
        .glass-header .brand-icon.has-logo {
            background: transparent;
            padding: 0;
            box-shadow: none;
            animation: none;
        }
        
        @keyframes pulseGlow {
            0%, 100% { box-shadow: 0 4px 15px rgba(241, 196, 15, 0.3); }
            50% { box-shadow: 0 4px 30px rgba(241, 196, 15, 0.6); }
        }
        
        .glass-header .brand-text {
            font-size: 18px;
            font-weight: 700;
            color: white;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }
        
        .glass-header .brand-sub {
            font-size: 10px;
            opacity: 0.5;
            font-weight: 400;
            display: block;
        }
        
        /* Header Actions */
        .header-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }
        
        /* ============================================
           GORGEOUS ADMIN LOGIN BUTTON - with user icon
           ============================================ */
        .btn-login-gorgeous {
            background: linear-gradient(135deg, #f1c40f, #e67e22);
            border: none;
            color: white;
            padding: 10px 18px;
            border-radius: 12px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 4px 20px rgba(241, 196, 15, 0.3);
            position: relative;
            overflow: hidden;
            white-space: nowrap;
        }
        
        .btn-login-gorgeous::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transform: rotate(45deg);
            animation: btnShine 3s infinite;
        }
        
        @keyframes btnShine {
            0% { transform: translateX(-100%) rotate(45deg); }
            100% { transform: translateX(100%) rotate(45deg); }
        }
        
        .btn-login-gorgeous:hover {
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 8px 30px rgba(241, 196, 15, 0.5);
            color: white;
        }
        
        .btn-login-gorgeous i {
            width: 16px;
            height: 16px;
        }
        
        .btn-login-gorgeous .btn-text {
            position: relative;
            z-index: 1;
        }
        
        .btn-login-gorgeous .btn-badge {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 1px 8px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            position: relative;
            z-index: 1;
        }
        
        .btn-login-gorgeous .btn-icon {
            position: relative;
            z-index: 1;
        }
        
        /* Search Button */
        .btn-search-gorgeous {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            padding: 10px 14px;
            border-radius: 12px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            backdrop-filter: blur(10px);
            white-space: nowrap;
        }
        
        .btn-search-gorgeous:hover {
            background: rgba(255, 255, 255, 0.12);
            transform: translateY(-2px);
            color: white;
            border-color: rgba(255, 255, 255, 0.2);
        }
        
        .btn-search-gorgeous i {
            width: 16px;
            height: 16px;
        }
        
        .btn-search-gorgeous .btn-text {
            font-size: 13px;
        }
        
        /* ============================================
           RESPONSIVE - MOBILE: Hide text, keep user icon
           ============================================ */
        @media (max-width: 768px) {
            .container {
                padding: 12px;
            }
            
            .glass-header {
                padding: 10px 14px;
                border-radius: 16px;
            }
            
            .glass-header .brand {
                gap: 8px;
            }
            
            .glass-header .brand-icon {
                width: 36px;
                height: 36px;
                font-size: 16px;
                border-radius: 10px;
            }
            
            .glass-header .brand-text {
                font-size: 16px;
            }
            
            .glass-header .brand-sub {
                font-size: 9px;
            }
            
            .header-actions {
                gap: 6px;
            }
            
            /* Hide login text, keep only user icon on mobile/tablet */
            .btn-login-gorgeous .btn-text {
                display: none;
            }
            
            .btn-login-gorgeous {
                padding: 8px 10px;
                font-size: 12px;
                border-radius: 10px;
                gap: 0;
                min-width: 36px;
                min-height: 36px;
                justify-content: center;
            }
            
            .btn-login-gorgeous .btn-badge {
                display: none;
            }
            
            .btn-login-gorgeous i {
                width: 18px;
                height: 18px;
                margin: 0;
            }
            
            /* Search button - hide text, keep icon */
            .btn-search-gorgeous .btn-text {
                display: none;
            }
            
            .btn-search-gorgeous {
                padding: 8px 10px;
                font-size: 12px;
                border-radius: 10px;
                gap: 0;
                min-width: 36px;
                min-height: 36px;
                justify-content: center;
            }
            
            .btn-search-gorgeous i {
                width: 18px;
                height: 18px;
                margin: 0;
            }
        }
        
        @media (max-width: 480px) {
            .container {
                padding: 8px;
            }
            
            .glass-header {
                padding: 8px 12px;
                border-radius: 14px;
                margin-bottom: 20px;
            }
            
            .glass-header .brand {
                gap: 6px;
            }
            
            .glass-header .brand-icon {
                width: 32px;
                height: 32px;
                font-size: 14px;
                border-radius: 8px;
            }
            
            .glass-header .brand-text {
                font-size: 14px;
            }
            
            .glass-header .brand-sub {
                display: none;
            }
            
            .header-actions {
                gap: 4px;
            }
            
            .btn-login-gorgeous {
                padding: 6px 8px;
                font-size: 11px;
                border-radius: 8px;
                min-width: 32px;
                min-height: 32px;
            }
            
            .btn-login-gorgeous i {
                width: 16px;
                height: 16px;
            }
            
            .btn-search-gorgeous {
                padding: 6px 8px;
                border-radius: 8px;
                min-width: 32px;
                min-height: 32px;
            }
            
            .btn-search-gorgeous i {
                width: 16px;
                height: 16px;
            }
        }
        
        /* ============================================
           MARQUEE
           ============================================ */
        .marquee-container {
            background: rgba(255, 255, 255, 0.04);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 14px;
            padding: 10px 0;
            margin-bottom: 30px;
            overflow: hidden;
            position: relative;
        }
        
        .marquee-container::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 60px;
            height: 100%;
            background: linear-gradient(90deg, rgba(10, 10, 10, 0.9), transparent);
            z-index: 2;
        }
        
        .marquee-container::after {
            content: '';
            position: absolute;
            right: 0;
            top: 0;
            width: 60px;
            height: 100%;
            background: linear-gradient(270deg, rgba(10, 10, 10, 0.9), transparent);
            z-index: 2;
        }
        
        .marquee-content {
            display: flex;
            animation: marqueeScroll 30s linear infinite;
            white-space: nowrap;
        }
        
        @keyframes marqueeScroll {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
        
        .marquee-item {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            padding: 0 40px;
            color: rgba(255, 255, 255, 0.85);
            font-size: 14px;
            font-weight: 500;
        }
        
        .marquee-item .highlight {
            color: #f1c40f;
            font-weight: 700;
        }
        
        .marquee-item .emoji {
            font-size: 18px;
        }
        
        .marquee-item .separator {
            color: rgba(255, 255, 255, 0.15);
            font-size: 18px;
        }
        
        @media (max-width: 768px) {
            .marquee-item {
                font-size: 12px;
                padding: 0 20px;
            }
            
            .marquee-item .emoji {
                font-size: 15px;
            }
        }
        
        @media (max-width: 480px) {
            .marquee-item {
                font-size: 10px;
                padding: 0 12px;
                gap: 6px;
            }
            
            .marquee-item .emoji {
                font-size: 12px;
            }
            
            .marquee-item .separator {
                font-size: 12px;
            }
        }
        
        /* ============================================
           BANNER SLIDER
           ============================================ */
        .banner-slider {
            border-radius: 20px;
            overflow: hidden;
            margin-bottom: 40px;
            position: relative;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        
        .banner-slider .carousel-item {
            height: 400px;
            background-size: cover;
            background-position: center;
            position: relative;
        }
        
        .banner-slider .carousel-item::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(10, 10, 30, 0.8), rgba(0, 0, 0, 0.4));
        }
        
        .banner-slider .carousel-item .banner-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            color: white;
            width: 80%;
            z-index: 1;
            padding: 20px;
        }
        
        .banner-slider .carousel-item .banner-content .banner-icon {
            font-size: 56px;
            margin-bottom: 16px;
            display: block;
            animation: bounceIcon 2s ease-in-out infinite;
        }
        
        @keyframes bounceIcon {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        .banner-slider .carousel-item .banner-content h3 {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 12px;
        }
        
        .banner-slider .carousel-item .banner-content h3 .highlight {
            color: #f1c40f;
        }
        
        .banner-slider .carousel-item .banner-content p {
            font-size: 17px;
            opacity: 0.85;
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
        }
        
        .banner-slider .carousel-item .banner-content .banner-btn {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 32px;
            background: linear-gradient(135deg, #f1c40f, #e67e22);
            color: white;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 15px;
            border: none;
        }
        
        .banner-slider .carousel-item .banner-content .banner-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(241, 196, 15, 0.4);
            color: white;
        }
        
        .banner-slide-1 {
            background: linear-gradient(135deg, #1a1a3e, #2d2d6b);
        }
        
        .banner-slide-2 {
            background: linear-gradient(135deg, #1a3e2a, #2d6b4a);
        }
        
        .banner-slide-3 {
            background: linear-gradient(135deg, #3e1a2a, #6b2d4a);
        }
        
        .banner-slider .carousel-indicators button {
            width: 40px;
            height: 4px;
            border-radius: 2px;
            border: none;
            background: rgba(255, 255, 255, 0.3);
            transition: all 0.3s;
            margin: 0 4px;
        }
        
        .banner-slider .carousel-indicators button.active {
            background: #f1c40f;
            width: 60px;
        }
        
        .banner-slider .carousel-control-prev,
        .banner-slider .carousel-control-next {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 50%;
            top: 50%;
            transform: translateY(-50%);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            opacity: 0.6;
            transition: all 0.3s;
        }
        
        .banner-slider .carousel-control-prev:hover,
        .banner-slider .carousel-control-next:hover {
            background: rgba(255, 255, 255, 0.2);
            opacity: 1;
        }
        
        @media (max-width: 768px) {
            .banner-slider .carousel-item {
                height: 320px;
            }
            
            .banner-slider .carousel-item .banner-content h3 {
                font-size: 24px;
            }
            
            .banner-slider .carousel-item .banner-content p {
                font-size: 14px;
            }
            
            .banner-slider .carousel-item .banner-content .banner-icon {
                font-size: 40px;
            }
            
            .banner-slider .carousel-control-prev,
            .banner-slider .carousel-control-next {
                width: 36px;
                height: 36px;
            }
        }
        
        @media (max-width: 480px) {
            .banner-slider .carousel-item {
                height: 280px;
            }
            
            .banner-slider .carousel-item .banner-content h3 {
                font-size: 20px;
            }
            
            .banner-slider .carousel-item .banner-content p {
                font-size: 13px;
            }
            
            .banner-slider .carousel-item .banner-content .banner-icon {
                font-size: 32px;
            }
        }
        
        /* ============================================
           FEATURES
           ============================================ */
        .section-title {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .section-title h2 {
            font-size: 36px;
            font-weight: 700;
            color: white;
            margin-bottom: 8px;
        }
        
        .section-title h2 .gradient-text {
            background: linear-gradient(135deg, #f1c40f, #e67e22);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .section-title p {
            color: rgba(255, 255, 255, 0.5);
            font-size: 16px;
        }
        
        @media (max-width: 768px) {
            .section-title h2 {
                font-size: 26px;
            }
        }
        
        .feature-card {
            background: rgba(255, 255, 255, 0.04);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 20px;
            padding: 30px 25px;
            text-align: center;
            transition: all 0.4s;
            height: 100%;
        }
        
        .feature-card:hover {
            transform: translateY(-8px);
            border-color: rgba(241, 196, 15, 0.2);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
            background: rgba(255, 255, 255, 0.06);
        }
        
        .feature-card .feature-icon {
            width: 70px;
            height: 70px;
            background: rgba(241, 196, 15, 0.1);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
            font-size: 32px;
            transition: all 0.3s;
        }
        
        .feature-card:hover .feature-icon {
            background: rgba(241, 196, 15, 0.2);
            transform: scale(1.1);
        }
        
        .feature-card .feature-title {
            font-size: 18px;
            font-weight: 600;
            color: white;
            margin-bottom: 8px;
        }
        
        .feature-card .feature-desc {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.5);
            line-height: 1.6;
        }
        
        @media (max-width: 768px) {
            .feature-card {
                padding: 22px 18px;
            }
        }
        
        /* ============================================
           STATS
           ============================================ */
        .stats-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin: 40px 0;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.04);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 16px;
            padding: 25px 20px;
            text-align: center;
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            background: rgba(255, 255, 255, 0.06);
            transform: translateY(-4px);
            border-color: rgba(241, 196, 15, 0.15);
        }
        
        .stat-card .stat-number {
            font-size: 36px;
            font-weight: 700;
            background: linear-gradient(135deg, #f1c40f, #e67e22);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .stat-card .stat-label {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.5);
            margin-top: 4px;
        }
        
        @media (max-width: 768px) {
            .stat-card .stat-number {
                font-size: 28px;
            }
        }
        
        @media (max-width: 480px) {
            .stats-section {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
            
            .stat-card {
                padding: 16px;
            }
            
            .stat-card .stat-number {
                font-size: 22px;
            }
        }
        
        /* ============================================
           CTA
           ============================================ */
        .cta-section {
            background: rgba(255, 255, 255, 0.04);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 20px;
            padding: 40px 30px;
            margin-top: 40px;
            text-align: center;
        }
        
        .cta-section h3 {
            color: white;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 12px;
        }
        
        .cta-section h3 span {
            color: #f1c40f;
        }
        
        .cta-section p {
            color: rgba(255, 255, 255, 0.5);
            font-size: 15px;
            max-width: 600px;
            margin: 0 auto 20px;
        }
        
        .cta-section .cta-btn {
            display: inline-block;
            padding: 12px 36px;
            background: linear-gradient(135deg, #f1c40f, #e67e22);
            color: white;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s;
            border: none;
        }
        
        .cta-section .cta-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(241, 196, 15, 0.4);
            color: white;
        }
        
        @media (max-width: 768px) {
            .cta-section {
                padding: 30px 20px;
            }
            
            .cta-section h3 {
                font-size: 22px;
            }
        }
        
        /* ============================================
           FOOTER
           ============================================ */
        .glass-footer {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.04);
            border-radius: 16px;
            padding: 16px 20px;
            margin-top: 40px;
            text-align: center;
        }
        
        .glass-footer .footer-text {
            color: rgba(255, 255, 255, 0.3);
            font-size: 13px;
        }
        
        .glass-footer .footer-divider {
            border-top: 1px solid rgba(255, 255, 255, 0.04);
            margin: 8px 0;
        }
        
        .glass-footer .footer-developer {
            color: rgba(255, 255, 255, 0.3);
            font-size: 12px;
        }
        
        .glass-footer .footer-developer a {
            color: #f1c40f;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .glass-footer .footer-developer a:hover {
            color: #e67e22;
            text-decoration: underline;
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

<!-- Animated Background -->
<div class="bg-animation"></div>

<!-- Floating Particles -->
<div class="particles">
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
</div>

<div class="container">

    <!-- ============================================
         HEADER - Mobile: User icon only, Desktop: User icon + Text
         ============================================ -->
    <header class="glass-header">
        <div class="header-inner">
            <!-- Brand - Full name always visible -->
            <a href="/" class="brand">
                <div class="brand-icon <?= !empty($full_logo_url) ? 'has-logo' : '' ?>">
                    <?php if (!empty($full_logo_url)): ?>
                        <img src="<?= htmlspecialchars($full_logo_url) ?>" alt="<?= htmlspecialchars($somity_name) ?> Logo">
                    <?php else: ?>
                        স
                    <?php endif; ?>
                </div>
                <div class="brand-text">
                    <?= htmlspecialchars($somity_name) ?>
                    <span class="brand-sub">সমিতি ব্যবস্থাপনা</span>
                </div>
            </a>
            
            <!-- Actions - Mobile: User icon only, Desktop: User icon + Text -->
            <div class="header-actions">
                <a href="/search.php" class="btn-search-gorgeous">
                    <i data-feather="search"></i>
                    <span class="btn-text">অনুসন্ধান</span>
                </a>
                <a href="/member/login.php" class="btn-login-gorgeous">
                    <i data-feather="user" class="btn-icon"></i>
                    <span class="btn-text">লগইন</span>
                    <span class="btn-badge">admin</span>
                </a>
            </div>
        </div>
    </header>

    <!-- ============================================
         MARQUEE
         ============================================ -->
    <div class="marquee-container">
        <div class="marquee-content">
            <span class="marquee-item">
                <span class="emoji">🌟</span>
                <span>স্বাগতম! <?= htmlspecialchars($somity_name) ?> ব্যবস্থাপনা সিস্টেম</span>
                <span class="separator">|</span>
                <span class="emoji">💰</span>
                <span>সঞ্চয় <span class="highlight">জমা</span></span>
                <span class="separator">|</span>
                <span class="emoji">📈</span>
                <span>ঋণ <span class="highlight">ব্যবস্থাপনা</span></span>
                <span class="separator">|</span>
                <span class="emoji">👥</span>
                <span>সদস্য <span class="highlight">তালিকা</span></span>
                <span class="separator">|</span>
                <span class="emoji">📊</span>
                <span>মাসিক <span class="highlight">চাঁদা</span></span>
                <span class="separator">|</span>
                <span class="emoji">📋</span>
                <span>উত্তোলন <span class="highlight">ইতিহাস</span></span>
                <span class="separator">|</span>
                <span class="emoji">🔒</span>
                <span>নিরাপদ <span class="highlight">ডেটা</span></span>
                <span class="separator">|</span>
                <span class="emoji">🚀</span>
                <span>২৪/৭ <span class="highlight">সেবা</span></span>
                <span class="separator">|</span>
            </span>
            <span class="marquee-item">
                <span class="emoji">🌟</span>
                <span>স্বাগতম! <?= htmlspecialchars($somity_name) ?> ব্যবস্থাপনা সিস্টেম</span>
                <span class="separator">|</span>
                <span class="emoji">💰</span>
                <span>সঞ্চয় <span class="highlight">জমা</span></span>
                <span class="separator">|</span>
                <span class="emoji">📈</span>
                <span>ঋণ <span class="highlight">ব্যবস্থাপনা</span></span>
                <span class="separator">|</span>
                <span class="emoji">👥</span>
                <span>সদস্য <span class="highlight">তালিকা</span></span>
                <span class="separator">|</span>
                <span class="emoji">📊</span>
                <span>মাসিক <span class="highlight">চাঁদা</span></span>
                <span class="separator">|</span>
                <span class="emoji">📋</span>
                <span>উত্তোলন <span class="highlight">ইতিহাস</span></span>
                <span class="separator">|</span>
                <span class="emoji">🔒</span>
                <span>নিরাপদ <span class="highlight">ডেটা</span></span>
                <span class="separator">|</span>
                <span class="emoji">🚀</span>
                <span>২৪/৭ <span class="highlight">সেবা</span></span>
                <span class="separator">|</span>
            </span>
        </div>
    </div>

    <!-- ============================================
         BANNER SLIDER
         ============================================ -->
    <div id="bannerSlider" class="carousel slide banner-slider" data-bs-ride="carousel" data-bs-interval="5000">
        <div class="carousel-indicators">
            <button type="button" data-bs-target="#bannerSlider" data-bs-slide-to="0" class="active"></button>
            <button type="button" data-bs-target="#bannerSlider" data-bs-slide-to="1"></button>
            <button type="button" data-bs-target="#bannerSlider" data-bs-slide-to="2"></button>
        </div>
        <div class="carousel-inner">
            <div class="carousel-item active banner-slide-1">
                <div class="banner-content">
                    <span class="banner-icon">🏦</span>
                    <h3>স্মার্ট <span class="highlight">সমিতি</span> ব্যবস্থাপনা</h3>
                    <p>আপনার সমিতির সকল কাজ ডিজিটালাইজ করুন। সঞ্চয়, ঋণ, সদস্য — সব এক প্ল্যাটফর্মে।</p>
                    <a href="/member/login.php" class="banner-btn">শুরু করুন →</a>
                </div>
            </div>
            <div class="carousel-item banner-slide-2">
                <div class="banner-content">
                    <span class="banner-icon">💰</span>
                    <h3>সঞ্চয় ও <span class="highlight">ঋণ</span> ব্যবস্থাপনা</h3>
                    <p>সদস্যদের সঞ্চয় জমা ও ঋণ ব্যবস্থাপনা সহজে করুন। সম্পূর্ণ স্বচ্ছ ও নিরাপদ।</p>
                    <a href="/member/login.php" class="banner-btn">এখনই দেখুন →</a>
                </div>
            </div>
            <div class="carousel-item banner-slide-3">
                <div class="banner-content">
                    <span class="banner-icon">📊</span>
                    <h3>রিয়েল টাইম <span class="highlight">রিপোর্ট</span></h3>
                    <p>আপনার সমিতির সকল আর্থিক লেনদেনের রিয়েল টাইম রিপোর্ট দেখুন। সম্পূর্ণ নিয়ন্ত্রণ আপনার হাতে।</p>
                    <a href="/member/login.php" class="banner-btn">বিস্তারিত জানুন →</a>
                </div>
            </div>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#bannerSlider" data-bs-slide="prev">
            <span class="carousel-control-prev-icon"></span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#bannerSlider" data-bs-slide="next">
            <span class="carousel-control-next-icon"></span>
        </button>
    </div>

    <!-- ============================================
         ABOUT SECTION
         ============================================ -->
    <div class="section-title">
        <h2><?= htmlspecialchars($somity_name) ?> <span class="gradient-text">কী?</span></h2>
        <p><?= htmlspecialchars($somity_name) ?> একটি সম্পূর্ণ সমিতি ব্যবস্থাপনা সিস্টেম — সহজ, স্মার্ট ও নিরাপদ</p>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-md-6">
            <div class="feature-card" style="text-align:left;padding:30px;">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
                    <span style="font-size:28px;">📖</span>
                    <h5 class="feature-title" style="margin-bottom:0;">আমাদের সম্পর্কে</h5>
                </div>
                <p class="feature-desc" style="color:rgba(255,255,255,0.6);line-height:1.8;">
                    <?= htmlspecialchars($somity_name) ?> একটি আধুনিক সমিতি ব্যবস্থাপনা প্ল্যাটফর্ম। আমরা বিশ্বাস করি যে একটি সমিতির সফলতা নির্ভর করে 
                    তার সঠিক ব্যবস্থাপনার উপর। আমাদের প্ল্যাটফর্ম আপনাকে সদস্য তালিকা, সঞ্চয় জমা, ঋণ ব্যবস্থাপনা, 
                    মাসিক চাঁদা সংগ্রহ এবং আরও অনেক কিছু — সব এক জায়গায় পরিচালনা করতে সহায়তা করে।
                </p>
                <p class="feature-desc" style="color:rgba(255,255,255,0.6);line-height:1.8;margin-top:8px;">
                    আমাদের লক্ষ্য হলো সমিতির কাজকে ডিজিটালাইজ করে সময় ও শ্রম বাঁচানো এবং স্বচ্ছতা নিশ্চিত করা।
                </p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="feature-card" style="text-align:left;padding:30px;">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
                    <span style="font-size:28px;">🎯</span>
                    <h5 class="feature-title" style="margin-bottom:0;">আমাদের লক্ষ্য</h5>
                </div>
                <ul style="color:rgba(255,255,255,0.6);list-style:none;padding:0;line-height:2.2;">
                    <li>✅ <strong style="color:white;">ডিজিটালাইজেশন</strong> — সমিতির সকল কাজ ডিজিটাল প্ল্যাটফর্মে আনা</li>
                    <li>✅ <strong style="color:white;">স্বচ্ছতা</strong> — প্রতিটি লেনদেনের সম্পূর্ণ স্বচ্ছতা নিশ্চিত করা</li>
                    <li>✅ <strong style="color:white;">সহজ ব্যবহার</strong> — যে কেউ সহজেই ব্যবহার করতে পারে</li>
                    <li>✅ <strong style="color:white;">নিরাপত্তা</strong> — আপনার ডেটা সম্পূর্ণ নিরাপদ ও সুরক্ষিত</li>
                    <li>✅ <strong style="color:white;">সাশ্রয়ী</strong> — সময় ও খরচ বাঁচানো</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- ============================================
         STATS
         ============================================ -->
    <div class="stats-section">
        <div class="stat-card">
            <div class="stat-number"><?= number_format($total_members) ?>+</div>
            <div class="stat-label">সক্রিয় সদস্য</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">৳<?= number_format($total_deposits, 0) ?>+</div>
            <div class="stat-label">মোট সঞ্চয়</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= number_format($total_loans) ?>+</div>
            <div class="stat-label">সক্রিয় ঋণ</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">২৪/৭</div>
            <div class="stat-label">অনলাইন সেবা</div>
        </div>
    </div>

    <!-- ============================================
         FEATURES
         ============================================ -->
    <div class="section-title">
        <h2>আমাদের <span class="gradient-text">সেবাসমূহ</span></h2>
        <p><?= htmlspecialchars($somity_name) ?> দিয়ে আপনার সমিতির সব কাজ সহজে করুন</p>
    </div>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="feature-card">
                <div class="feature-icon">💰</div>
                <h5 class="feature-title">সঞ্চয় জমা</h5>
                <p class="feature-desc">সদস্যদের সঞ্চয় জমা গ্রহণ ও ব্যবস্থাপনা। সহজে জমা রেকর্ড রাখুন এবং ইতিহাস দেখুন।</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="feature-card">
                <div class="feature-icon">📈</div>
                <h5 class="feature-title">ঋণ ব্যবস্থাপনা</h5>
                <p class="feature-desc">ঋণ মঞ্জুর, কিস্তি সংগ্রহ ও বকেয়া ট্র্যাকিং। সম্পূর্ণ ঋণ ব্যবস্থাপনা এক জায়গায়।</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="feature-card">
                <div class="feature-icon">👥</div>
                <h5 class="feature-title">সদস্য তালিকা</h5>
                <p class="feature-desc">সম্পূর্ণ সদস্য তথ্য সংরক্ষণ ও ব্যবস্থাপনা। দ্রুত সদস্য খুঁজে বের করুন।</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="feature-card">
                <div class="feature-icon">📊</div>
                <h5 class="feature-title">মাসিক চাঁদা</h5>
                <p class="feature-desc">মাসিক চাঁদা সংগ্রহ ও ট্র্যাকিং। বকেয়া সদস্যদের সহজে চিহ্নিত করুন।</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="feature-card">
                <div class="feature-icon">📋</div>
                <h5 class="feature-title">উত্তোলন ইতিহাস</h5>
                <p class="feature-desc">সদস্যদের উত্তোলন রেকর্ড ও ইতিহাস। সম্পূর্ণ স্বচ্ছতা নিশ্চিত করুন।</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="feature-card">
                <div class="feature-icon">🔒</div>
                <h5 class="feature-title">নিরাপদ ডেটা</h5>
                <p class="feature-desc">আপনার সমিতির সকল ডেটা নিরাপদ ও সুরক্ষিত। শক্তিশালী নিরাপত্তা ব্যবস্থা।</p>
            </div>
        </div>
    </div>

    <!-- ============================================
         CTA SECTION
         ============================================ -->
    <div class="cta-section">
        <h3>আপনার সমিতি পরিচালনা করুন <span>স্মার্ট উপায়ে</span></h3>
        <p>আজই শুরু করুন এবং আপনার সমিতির কাজকে সহজ করুন।</p>
        <a href="/admin/login.php" class="cta-btn">
            <i data-feather="user" style="width:18px;height:18px;vertical-align:middle;margin-right:8px;"></i>
            অ্যাডমিন লগইন
        </a>
    </div>

    <!-- ============================================
         FOOTER
         ============================================ -->
    <footer class="glass-footer">
        <div class="footer-text">
            &copy; <?= date('Y') ?> <?= htmlspecialchars($somity_name) ?> — সমিতি ব্যবস্থাপনা সিস্টেম
        </div>
        <hr class="footer-divider">
        <div class="footer-developer">
        <i class="fa-brands fa-edge-legacy" style="color: rgb(255, 199, 0); margin-right: 4px;"></i> Developed by <a href="https://amin.free.nf/" target="_blank">Mr.Amin</a>
        </div>
    </footer>

</div>

<!-- ============================================
     SCRIPTS
     ============================================ -->
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
    });

    // Initialize Feather Icons
    feather.replace();
    
    // Auto slide banner with pause on hover
    document.addEventListener('DOMContentLoaded', function() {
        const carousel = document.getElementById('bannerSlider');
        if (carousel) {
            carousel.addEventListener('mouseenter', function() {
                const bsCarousel = bootstrap.Carousel.getInstance(this);
                if (bsCarousel) bsCarousel.pause();
            });
            carousel.addEventListener('mouseleave', function() {
                const bsCarousel = bootstrap.Carousel.getInstance(this);
                if (bsCarousel) bsCarousel.cycle();
            });
        }
    });
</script>

</body>
</html>