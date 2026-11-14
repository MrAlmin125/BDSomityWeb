<?php
// ============================================================
// forgot-password.php - Premium Security Information Page
// Location: /admin/forgot-password.php
// Dynamically fetches settings from database using config & functions
// ============================================================

// --- Error handling for debugging ---
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- Define base paths ---
$base_path = dirname(dirname(__FILE__)); // Go up from /admin/ to root
$config_path = $base_path . '/config/database.php';
$functions_path = $base_path . '/includes/functions.php';

// --- Check if files exist ---
if (!file_exists($config_path)) {
    die('Database configuration file not found at: ' . $config_path);
}

if (!file_exists($functions_path)) {
    die('Functions file not found at: ' . $functions_path);
}

// --- Include required files ---
require_once $config_path;
require_once $functions_path;

// --- Check if getSetting function exists ---
if (!function_exists('getSetting')) {
    die('getSetting() function not found. Please check includes/functions.php');
}

// --- Fetch all required settings with error handling ---
try {
    $somity_phone   = getSetting('somity_phone', '+966576312339');
    $messenger_id   = getSetting('messenger_page_id', 'abid.hassan.205580');
    $somity_name    = getSetting('somity_name', 'আল বারাকা কো-অপাঃ সোসাইটি');
    $currency_symbol = getSetting('currency_symbol', '৳');
    $logo_url       = getSetting('logo_url', '/images/logo_1783370218.jpeg');
    $favicon_url    = getSetting('favicon_url', '/favicon.ico');
} catch (Exception $e) {
    // Fallback to defaults if database fails
    $somity_phone   = '+966576312339';
    $messenger_id   = 'abid.hassan.205580';
    $somity_name    = 'আল বারাকা কো-অপাঃ সোসাইটি';
    $currency_symbol = '৳';
    $logo_url       = '/images/logo_1783370218.jpeg';
    $favicon_url    = '/favicon.ico';
}

// --- Build contact URLs ---
$clean_phone = preg_replace('/[^0-9+]/', '', $somity_phone);
$whatsapp_url = 'https://wa.me/' . $clean_phone;
$messenger_url = 'https://m.me/' . urlencode($messenger_id);

// --- Login page URL ---
$login_url = 'https://shopbd.free.nf/admin/login.php';
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.5">
    <title>Forgot Password · <?php echo htmlspecialchars($somity_name); ?></title>
    
    <?php if (!empty($favicon_url)): ?>
    <link rel="icon" href="<?php echo htmlspecialchars($favicon_url); ?>" type="image/x-icon">
    <?php endif; ?>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome 6 (Free) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* ============================================================
           PREMIUM STYLES - GORGEOUS & ATTRACTIVE DESIGN
           ============================================================ */

        /* ----- CSS Variables ----- */
        :root {
            --primary: #6C3CE1;
            --primary-dark: #5A2BC8;
            --primary-light: #8B6BE8;
            --primary-gradient: linear-gradient(135deg, #6C3CE1 0%, #A855F7 50%, #EC4899 100%);
            --secondary-gradient: linear-gradient(135deg, #3B82F6 0%, #06B6D4 100%);
            --success-gradient: linear-gradient(135deg, #10B981 0%, #34D399 100%);
            --warning-gradient: linear-gradient(135deg, #F59E0B 0%, #FBBF24 100%);
            --bg-gradient: linear-gradient(135deg, #EEF2FF 0%, #E0E7FF 50%, #DBEAFE 100%);
            --card-shadow: 0 25px 50px -12px rgba(108, 60, 225, 0.25);
            --text-dark: #1E1B4B;
            --text-muted: #6B7280;
            --glass-bg: rgba(255, 255, 255, 0.7);
            --glass-border: rgba(255, 255, 255, 0.3);
            --glass-shadow: 0 8px 32px rgba(108, 60, 225, 0.08);
        }

        /* ----- Reset & Base ----- */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            margin: 0;
            background: var(--bg-gradient);
            background-attachment: fixed;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated background particles */
        body::before {
            content: '';
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(ellipse at 20% 50%, rgba(108, 60, 225, 0.05) 0%, transparent 60%),
                        radial-gradient(ellipse at 80% 20%, rgba(236, 72, 153, 0.04) 0%, transparent 50%),
                        radial-gradient(ellipse at 50% 80%, rgba(59, 130, 246, 0.04) 0%, transparent 50%);
            animation: floatBackground 20s ease-in-out infinite alternate;
            z-index: 0;
            pointer-events: none;
        }

        @keyframes floatBackground {
            0% { transform: translate(0, 0) rotate(0deg); }
            100% { transform: translate(2%, 1%) rotate(2deg); }
        }

        /* Glowing orb decorations */
        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.15;
            pointer-events: none;
            z-index: 0;
            animation: orbFloat 15s ease-in-out infinite alternate;
        }

        .orb-1 {
            width: 400px;
            height: 400px;
            background: #6C3CE1;
            top: -100px;
            right: -100px;
            animation-delay: 0s;
        }

        .orb-2 {
            width: 300px;
            height: 300px;
            background: #EC4899;
            bottom: -50px;
            left: -50px;
            animation-delay: -5s;
        }

        .orb-3 {
            width: 250px;
            height: 250px;
            background: #3B82F6;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            animation-delay: -10s;
            opacity: 0.08;
        }

        @keyframes orbFloat {
            0% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(30px, -20px) scale(1.1); }
            66% { transform: translate(-20px, 30px) scale(0.9); }
            100% { transform: translate(10px, -10px) scale(1.05); }
        }

        /* ----- Main Container - FULL WIDTH ----- */
        .main-container {
            max-width: 1100px;
            width: 100%;
            position: relative;
            z-index: 1;
            padding: 0 16px;
        }

        /* ----- Main Card - FULL WIDTH ----- */
        .card {
            width: 100%;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 32px;
            padding: 32px 32px 28px;
            border: 1px solid var(--glass-border);
            box-shadow: var(--card-shadow), var(--glass-shadow);
            position: relative;
            z-index: 1;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 30px 60px -16px rgba(108, 60, 225, 0.3);
        }

        /* ============================================================
           FLEX 2 COLUMN LAYOUT
           ============================================================ */

        /* ----- ROW: 2 Columns ----- */
        .row-2col {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 24px;
            width: 100%;
        }

        .row-2col > .col {
            flex: 1 1 calc(50% - 10px);
            min-width: 280px;
        }

        /* ----- Header - Full Width ----- */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 4px;
            flex-wrap: wrap;
            gap: 8px 12px;
            width: 100%;
        }

        .header h1 {
            font-size: 1.75rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-dark);
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header h1 .lock-icon {
            -webkit-text-fill-color: initial;
            color: var(--primary);
        }

        .badge {
            font-size: 0.7rem;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 40px;
            letter-spacing: 0.03em;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            text-transform: uppercase;
            background: linear-gradient(135deg, #FEF3C7, #FDE68A);
            color: #92400E;
            border: 1px solid #FCD34D;
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.2);
            animation: pulseBadge 2s ease-in-out infinite;
        }

        @keyframes pulseBadge {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .badge i {
            font-size: 0.8rem;
        }

        /* ----- Subhead - Full Width ----- */
        .subhead {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-top: 2px;
            margin-bottom: 24px;
            padding-left: 14px;
            border-left: 4px solid var(--primary);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
        }

        .subhead i {
            color: var(--primary);
            font-size: 0.8rem;
        }

        /* ----- Info Box - 2 Column Container ----- */
        .info-box-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 24px;
            width: 100%;
        }

        .info-box-container > .col {
            flex: 1 1 calc(50% - 10px);
            min-width: 280px;
        }

        .info-box {
            background: linear-gradient(135deg, #EEF2FF, #E0E7FF);
            border-radius: 20px;
            padding: 20px 22px;
            display: flex;
            gap: 16px;
            align-items: flex-start;
            border: 1px solid rgba(108, 60, 225, 0.1);
            position: relative;
            overflow: hidden;
            height: 100%;
        }

        .info-box::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 150px;
            height: 150px;
            background: radial-gradient(circle, rgba(108, 60, 225, 0.05) 0%, transparent 70%);
            border-radius: 50%;
        }

        .info-box .icon {
            font-size: 1.8rem;
            line-height: 1;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .info-box .text {
            font-size: 0.95rem;
            color: #3730A3;
            position: relative;
            z-index: 1;
        }

        .info-box .text strong {
            font-weight: 700;
            display: block;
            margin-bottom: 4px;
        }

        .info-box .text p {
            opacity: 0.8;
            line-height: 1.6;
        }

        /* Info Box - Alternative Style */
        .info-box-alt {
            background: linear-gradient(135deg, #FEF3C7, #FDE68A);
            border-color: rgba(245, 158, 11, 0.15);
        }

        .info-box-alt .text {
            color: #92400E;
        }

        /* ----- Security Tips - 2 Columns (left) ----- */
        .tips-card {
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.5);
            padding: 20px 18px 18px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.02);
            height: 100%;
        }

        .tips-header {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            font-size: 1rem;
            color: var(--text-dark);
            margin-bottom: 14px;
        }

        .tips-header i {
            font-size: 1.3rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Tips Grid - 2 Columns Inside */
        .tips-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px 12px;
        }

        .tip-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
            padding: 6px 6px 6px 2px;
            border-bottom: 1px solid rgba(229, 231, 235, 0.4);
            color: var(--text-dark);
            transition: all 0.2s ease;
            border-radius: 8px;
        }

        .tip-item:hover {
            background: rgba(108, 60, 225, 0.04);
            padding-left: 10px;
        }

        .tip-item:last-child {
            border-bottom: none;
        }

        .tip-item .tip-icon {
            font-size: 1rem;
            width: 26px;
            text-align: center;
            flex-shrink: 0;
        }

        .tip-item .tip-text {
            font-weight: 500;
            font-size: 0.82rem;
        }

        .tip-divider {
            margin-top: 16px;
            padding-top: 14px;
            border-top: 1px dashed rgba(108, 60, 225, 0.15);
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .tip-badge {
            background: linear-gradient(135deg, #DBEAFE, #E0E7FF);
            padding: 4px 14px;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 600;
            color: #4338CA;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        /* ----- Contact Box - 2 Columns (right) ----- */
        .contact-box {
            background: linear-gradient(135deg, #ECFDF5, #D1FAE5);
            border-radius: 20px;
            padding: 20px 22px;
            border: 1px solid rgba(16, 185, 129, 0.15);
            position: relative;
            overflow: hidden;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .contact-box::before {
            content: '';
            position: absolute;
            bottom: -30%;
            right: -10%;
            width: 120px;
            height: 120px;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.06) 0%, transparent 70%);
            border-radius: 50%;
        }

        .contact-box .note-icon {
            font-size: 1.5rem;
            flex-shrink: 0;
            position: relative;
            z-index: 1;
            margin-bottom: 8px;
        }

        .contact-box .contact-content {
            display: flex;
            flex-direction: column;
            gap: 12px;
            width: 100%;
            position: relative;
            z-index: 1;
            flex: 1;
        }

        .contact-box .contact-content strong {
            color: #065F46;
            font-weight: 700;
        }

        /* ----- Contact Buttons - 2 Column on Mobile ----- */
        .contact-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .contact-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 12px 20px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            border: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            flex: 1;
            min-width: 100px;
        }

        .contact-btn::after {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(255, 255, 255, 0.2);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .contact-btn:hover::after {
            opacity: 1;
        }

        .contact-btn-whatsapp {
            background: linear-gradient(135deg, #25D366, #128C7E);
            color: white;
            box-shadow: 0 4px 12px rgba(37, 211, 102, 0.3);
        }

        .contact-btn-whatsapp:hover {
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 8px 24px rgba(37, 211, 102, 0.4);
        }

        .contact-btn-messenger {
            background: linear-gradient(135deg, #0084FF, #0066CC);
            color: white;
            box-shadow: 0 4px 12px rgba(0, 132, 255, 0.3);
        }

        .contact-btn-messenger:hover {
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 8px 24px rgba(0, 132, 255, 0.4);
        }

        .contact-note {
            font-size: 0.7rem;
            opacity: 0.6;
            color: #065F46;
            margin-top: auto;
        }

        /* ----- Action Buttons - Full Width ----- */
        .action-group {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 4px;
            margin-bottom: 24px;
            width: 100%;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            padding: 14px 28px;
            border-radius: 60px;
            border: none;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            flex: 1;
            min-width: 140px;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .btn::after {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(255, 255, 255, 0.15);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .btn:hover::after {
            opacity: 1;
        }

        .btn-primary {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 6px 20px rgba(108, 60, 225, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 32px rgba(108, 60, 225, 0.4);
        }

        .btn-primary:active {
            transform: translateY(-1px);
        }

        .btn .arrow {
            font-size: 1.1rem;
            transition: transform 0.3s ease;
        }

        .btn-primary:hover .arrow {
            transform: translateX(-4px);
        }

        /* ----- Footnote - Full Width ----- */
        .footnote-wrapper {
            display: flex;
            justify-content: center;
            margin-bottom: 16px;
            width: 100%;
        }

        .footnote {
            font-size: 0.8rem;
            color: var(--text-muted);
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(10px);
            padding: 8px 20px;
            border-radius: 40px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .footnote strong {
            font-weight: 600;
            color: var(--text-dark);
        }

        .footnote i {
            color: var(--primary);
        }

        /* ============================================================
           FOOTER - 2 COLUMNS WITH DEVELOPER CREDIT
           ============================================================ */
        .footer-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            width: 100%;
            margin-top: 8px;
            border-top: 1px solid rgba(229, 231, 235, 0.3);
            padding-top: 18px;
        }

        .footer-wrapper > .col {
            flex: 1 1 calc(50% - 8px);
            min-width: 200px;
        }

        .footer-left {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.75rem;
            color: var(--text-muted);
            flex-wrap: wrap;
        }

        .footer-left i {
            margin-right: 4px;
            color: var(--primary);
        }

        .footer-right {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 16px;
            font-size: 0.75rem;
            color: var(--text-muted);
            flex-wrap: wrap;
        }

        .footer-right a {
            color: var(--primary);
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .footer-right a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .footer-divider {
            color: rgba(229, 231, 235, 0.5);
        }

        /* ----- Developer Credit Styles ----- */
        .dev-credit {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .dev-label {
            font-size: 0.7rem;
            color: var(--text-muted);
            opacity: 0.7;
        }

        .dev-name {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-weight: 600;
            color: var(--primary);
            text-decoration: none;
            font-size: 0.75rem;
            transition: all 0.3s ease;
        }

        .dev-name:hover {
            color: var(--primary-dark);
            transform: translateY(-1px);
        }

        .dev-name i {
            font-size: 0.85rem;
        }

        /* ============================================================
           RESPONSIVE - FULLY MOBILE OPTIMIZED
           ============================================================ */
        
        /* ----- Tablet: Stack to 1 column ----- */
        @media (max-width: 768px) {
            .row-2col > .col,
            .info-box-container > .col {
                flex: 1 1 100%;
                min-width: unset;
            }

            .tips-grid {
                grid-template-columns: 1fr 1fr;
            }

            .footer-wrapper > .col {
                flex: 1 1 100%;
                min-width: unset;
            }

            .footer-right {
                justify-content: flex-start;
            }

            .card {
                padding: 28px 24px 24px;
            }

            .header h1 {
                font-size: 1.5rem;
            }
        }

        /* ----- Mobile: 1 column, optimized ----- */
        @media (max-width: 640px) {
            body {
                padding: 0.75rem;
            }

            .main-container {
                padding: 0;
            }

            .card {
                padding: 20px 14px 18px;
                border-radius: 20px;
            }

            .header h1 {
                font-size: 1.2rem;
                gap: 6px;
            }

            .header h1 .lock-icon {
                font-size: 1rem;
            }

            .badge {
                font-size: 0.5rem;
                padding: 3px 8px;
                gap: 4px;
            }

            .badge i {
                font-size: 0.6rem;
            }

            .subhead {
                font-size: 0.75rem;
                padding-left: 8px;
                margin-bottom: 16px;
                border-left-width: 3px;
            }

            .subhead i {
                font-size: 0.7rem;
            }

            /* Info Box - Mobile */
            .info-box-container {
                gap: 12px;
                margin-bottom: 16px;
            }

            .info-box {
                padding: 14px 14px;
                gap: 10px;
                border-radius: 16px;
            }

            .info-box .icon {
                font-size: 1.4rem;
            }

            .info-box .text {
                font-size: 0.82rem;
            }

            .info-box .text strong {
                font-size: 0.85rem;
            }

            .info-box .text p {
                font-size: 0.78rem;
            }

            /* Tips - Mobile */
            .tips-card {
                padding: 16px 12px 14px;
                border-radius: 16px;
            }

            .tips-header {
                font-size: 0.9rem;
                margin-bottom: 10px;
            }

            .tips-header i {
                font-size: 1.1rem;
            }

            .tips-grid {
                grid-template-columns: 1fr 1fr;
                gap: 2px 8px;
            }

            .tip-item {
                font-size: 0.75rem;
                padding: 4px 4px;
                gap: 6px;
            }

            .tip-item .tip-icon {
                font-size: 0.8rem;
                width: 20px;
            }

            .tip-item .tip-text {
                font-size: 0.7rem;
            }

            .tip-divider {
                margin-top: 10px;
                padding-top: 10px;
                gap: 6px;
            }

            .tip-badge {
                font-size: 0.6rem;
                padding: 3px 10px;
            }

            .tip-divider span:last-child {
                font-size: 0.7rem !important;
            }

            /* Contact - Mobile */
            .contact-box {
                padding: 14px 14px;
                border-radius: 16px;
            }

            .contact-box .note-icon {
                font-size: 1.2rem;
                margin-bottom: 4px;
            }

            .contact-box .contact-content {
                gap: 8px;
            }

            .contact-box .contact-content strong {
                font-size: 0.85rem;
            }

            .contact-box .contact-content > div:first-child {
                font-size: 0.82rem;
            }

            /* 🔥 CONTACT BUTTONS - 2 COLUMN ON MOBILE 🔥 */
            .contact-buttons {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }

            .contact-btn {
                padding: 10px 12px;
                font-size: 0.78rem;
                min-width: unset;
                width: 100%;
                border-radius: 30px;
                flex: none;
            }

            .contact-btn i {
                font-size: 0.95rem;
            }

            .contact-note {
                font-size: 0.65rem;
                margin-top: 4px;
            }

            /* 🔥 ACTION BUTTONS - 2 COLUMN ON MOBILE 🔥 */
            .action-group {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 10px;
                margin-bottom: 16px;
            }

            .btn {
                font-size: 0.82rem;
                padding: 12px 14px;
                width: 100%;
                flex: none;
                min-width: unset;
                border-radius: 40px;
            }

            .btn .arrow {
                font-size: 0.85rem;
            }

            /* Footnote - Mobile */
            .footnote-wrapper {
                margin-bottom: 12px;
            }

            .footnote {
                font-size: 0.65rem;
                padding: 6px 12px;
                text-align: center;
                border-radius: 30px;
                gap: 6px;
                flex-wrap: wrap;
                justify-content: center;
            }

            .footnote i {
                font-size: 0.7rem;
            }

            /* 🔥 FOOTER - 2 COLUMN ON MOBILE 🔥 */
            .footer-wrapper {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 8px 16px;
                padding-top: 12px;
                margin-top: 4px;
            }

            .footer-wrapper > .col {
                flex: none;
                min-width: unset;
            }

            .footer-left {
                justify-content: flex-start;
                text-align: left;
                font-size: 0.6rem;
                gap: 4px;
                width: 100%;
                flex-wrap: wrap;
            }

            .footer-right {
                justify-content: flex-end;
                text-align: right;
                font-size: 0.6rem;
                gap: 8px;
                width: 100%;
                flex-wrap: wrap;
            }

            .footer-divider {
                display: inline-block;
            }

            .dev-label {
                font-size: 0.6rem;
            }

            .dev-name {
                font-size: 0.65rem;
            }

            .dev-name i {
                font-size: 0.75rem;
            }

            /* Card hover - disable on mobile */
            .card:hover {
                transform: none;
            }

            /* Orbs - reduce on mobile */
            .orb-1 {
                width: 200px;
                height: 200px;
                top: -60px;
                right: -60px;
            }

            .orb-2 {
                width: 150px;
                height: 150px;
                bottom: -30px;
                left: -30px;
            }

            .orb-3 {
                width: 120px;
                height: 120px;
                opacity: 0.05;
            }

            /* Row gap reduce */
            .row-2col {
                gap: 12px;
                margin-bottom: 16px;
            }
        }

        /* ----- Very Small Phones (<= 420px) ----- */
        @media (max-width: 420px) {
            body {
                padding: 0.5rem;
            }

            .card {
                padding: 14px 10px 14px;
                border-radius: 16px;
            }

            .header h1 {
                font-size: 1rem;
            }

            .header h1 .lock-icon {
                font-size: 0.85rem;
            }

            .badge {
                font-size: 0.45rem;
                padding: 2px 6px;
            }

            .subhead {
                font-size: 0.65rem;
                padding-left: 6px;
                margin-bottom: 12px;
            }

            .info-box {
                padding: 10px 10px;
                gap: 8px;
                border-radius: 12px;
            }

            .info-box .icon {
                font-size: 1.2rem;
            }

            .info-box .text {
                font-size: 0.7rem;
            }

            .info-box .text strong {
                font-size: 0.75rem;
            }

            .info-box .text p {
                font-size: 0.68rem;
            }

            .tips-card {
                padding: 12px 8px 10px;
                border-radius: 12px;
            }

            .tips-header {
                font-size: 0.8rem;
                margin-bottom: 8px;
            }

            .tips-grid {
                grid-template-columns: 1fr 1fr;
                gap: 1px 6px;
            }

            .tip-item {
                font-size: 0.65rem;
                padding: 3px 2px;
                gap: 4px;
            }

            .tip-item .tip-icon {
                font-size: 0.7rem;
                width: 16px;
            }

            .tip-item .tip-text {
                font-size: 0.62rem;
            }

            .contact-box {
                padding: 10px 10px;
                border-radius: 12px;
            }

            .contact-box .contact-content strong {
                font-size: 0.78rem;
            }

            .contact-box .contact-content > div:first-child {
                font-size: 0.72rem;
            }

            /* 🔥 CONTACT BUTTONS - 2 COLUMN */
            .contact-buttons {
                grid-template-columns: 1fr 1fr;
                gap: 6px;
            }

            .contact-btn {
                padding: 8px 8px;
                font-size: 0.68rem;
                gap: 4px;
                border-radius: 24px;
            }

            .contact-btn i {
                font-size: 0.8rem;
            }

            /* 🔥 ACTION BUTTONS - 2 COLUMN */
            .action-group {
                grid-template-columns: 1fr 1fr;
                gap: 8px;
                margin-bottom: 12px;
            }

            .btn {
                font-size: 0.72rem;
                padding: 8px 10px;
                border-radius: 30px;
            }

            .btn .arrow {
                font-size: 0.75rem;
            }

            .footnote {
                font-size: 0.55rem;
                padding: 4px 8px;
            }

            /* 🔥 FOOTER - 2 COLUMN */
            .footer-wrapper {
                grid-template-columns: 1fr 1fr;
                gap: 4px 12px;
                padding-top: 8px;
            }

            .footer-left,
            .footer-right {
                font-size: 0.55rem;
                gap: 4px;
            }

            .dev-label {
                font-size: 0.55rem;
            }

            .dev-name {
                font-size: 0.58rem;
            }

            .dev-name i {
                font-size: 0.65rem;
            }
        }

        /* ============================================================
           LANDSCAPE PHONE SUPPORT
           ============================================================ */
        @media (max-height: 600px) and (orientation: landscape) {
            body {
                padding: 0.5rem;
            }

            .card {
                padding: 16px 16px 14px;
                border-radius: 16px;
            }

            .header h1 {
                font-size: 1.2rem;
            }

            .subhead {
                margin-bottom: 12px;
            }

            .info-box-container {
                gap: 10px;
                margin-bottom: 12px;
            }

            .info-box {
                padding: 12px 14px;
            }

            .row-2col {
                gap: 10px;
                margin-bottom: 12px;
            }

            .tips-card {
                padding: 12px 12px 10px;
            }

            .tips-grid {
                grid-template-columns: 1fr 1fr;
            }

            .tip-item {
                padding: 3px 4px;
                font-size: 0.7rem;
            }

            .contact-box {
                padding: 12px 14px;
            }

            .contact-btn {
                padding: 6px 12px;
                font-size: 0.7rem;
            }

            .action-group {
                margin-bottom: 12px;
            }

            .btn {
                padding: 8px 16px;
                font-size: 0.8rem;
            }

            .footer-wrapper {
                padding-top: 10px;
                margin-top: 2px;
            }

            .footnote-wrapper {
                margin-bottom: 8px;
            }
        }

        /* ============================================================
           EXTRA SMALL LANDSCAPE
           ============================================================ */
        @media (max-height: 450px) and (orientation: landscape) {
            .card {
                padding: 12px 12px 10px;
                border-radius: 12px;
            }

            .header h1 {
                font-size: 1rem;
            }

            .badge {
                font-size: 0.45rem;
                padding: 2px 8px;
            }

            .subhead {
                font-size: 0.6rem;
                margin-bottom: 8px;
            }

            .info-box {
                padding: 8px 10px;
                gap: 6px;
            }

            .info-box .icon {
                font-size: 1rem;
            }

            .info-box .text {
                font-size: 0.65rem;
            }

            .info-box .text p {
                font-size: 0.6rem;
            }

            .tips-card {
                padding: 8px 8px 6px;
            }

            .tip-item {
                font-size: 0.6rem;
                padding: 2px 2px;
            }

            .contact-box {
                padding: 8px 10px;
            }

            .contact-btn {
                padding: 4px 8px;
                font-size: 0.6rem;
            }

            .btn {
                padding: 6px 12px;
                font-size: 0.7rem;
            }

            .footer-wrapper {
                padding-top: 6px;
                margin-top: 0;
            }

            .footer-left,
            .footer-right {
                font-size: 0.5rem;
            }
        }
    </style>
</head>
<body>

    <!-- Decorative Orbs -->
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <div class="main-container">
        <div class="card" role="main" aria-label="Password recovery information">

            <!-- ===== HEADER - FULL WIDTH ===== -->
            <div class="header">
                <h1>
                    <span class="lock-icon">🔒</span> Forgot Password
                </h1>
                <span class="badge">
                    <i class="fas fa-clock"></i> Coming Soon
                </span>
            </div>

            <div class="subhead">
                <i class="fas fa-shield-alt"></i> Password Recovery · উন্নয়নাধীন
            </div>

            <!-- ===== ROW 1: INFO BOX (2 Columns) ===== -->
            <div class="info-box-container">
                <!-- Column 1: Info -->
                <div class="col">
                    <div class="info-box">
                        <span class="icon">ℹ️</span>
                        <div class="text">
                            <strong>Password recovery via email is currently under development.</strong>
                            <p>This feature will be available in a future update. আমরা নিরাপদ পাসওয়ার্ড রিসেট সিস্টেম তৈরির কাজ করছি।</p>
                        </div>
                    </div>
                </div>
                <!-- Column 2: Additional Info -->
                <div class="col">
                    <div class="info-box info-box-alt">
                        <span class="icon">🛡️</span>
                        <div class="text">
                            <strong>Your account security is our priority.</strong>
                            <p>We're building a robust password recovery system to ensure your data stays protected.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== ROW 2: SECURITY TIPS + CONTACT (2 Columns) ===== -->
            <div class="row-2col">
                <!-- Column 1: Security Tips -->
                <div class="col">
                    <div class="tips-card">
                        <div class="tips-header">
                            <i class="fas fa-shield-halved"></i> নিরাপত্তা পরামর্শ
                        </div>
                        <div class="tips-grid">
                            <div class="tip-item">
                                <span class="tip-icon">🔑</span>
                                <span class="tip-text">শক্তিশালী পাসওয়ার্ড</span>
                            </div>
                            <div class="tip-item">
                                <span class="tip-icon">🚫</span>
                                <span class="tip-text">সহজ পাসওয়ার্ড এড়িয়ে চলুন</span>
                            </div>
                            <div class="tip-item">
                                <span class="tip-icon">💻</span>
                                <span class="tip-text">পাবলিক ডিভাইসে লগআউট</span>
                            </div>
                            <div class="tip-item">
                                <span class="tip-icon">🔄</span>
                                <span class="tip-text">নিয়মিত পাসওয়ার্ড পরিবর্তন</span>
                            </div>
                            <div class="tip-item">
                                <span class="tip-icon">⚠️</span>
                                <span class="tip-text">অপরিচিত সাইটে তথ্য দেবেন না</span>
                            </div>
                            <div class="tip-item">
                                <span class="tip-icon">📱</span>
                                <span class="tip-text">2FA চালু করুন (যখন পাওয়া যাবে)</span>
                            </div>
                        </div>

                        <div class="tip-divider">
                            <span class="tip-badge">
                                <i class="fas fa-lock"></i> নিরাপত্তা
                            </span>
                            <span style="font-size: 0.8rem; color: var(--text-dark);">পাসওয়ার্ড কখনো শেয়ার করবেন না</span>
                        </div>
                    </div>
                </div>

                <!-- Column 2: Contact -->
                <div class="col">
                    <div class="contact-box">
                        <div>
                            <span class="note-icon">📢</span>
                        </div>
                        <div class="contact-content">
                            <div><strong>তাৎক্ষণিক সহায়তা</strong> — নিচের যে কোনো মাধ্যমে যোগাযোগ করুন</div>
                            
                            <!-- 🔥 CONTACT BUTTONS - 2 COLUMN ON MOBILE 🔥 -->
                            <div class="contact-buttons">
                                <a href="<?php echo htmlspecialchars($whatsapp_url); ?>" 
                                   target="_blank" 
                                   rel="noopener noreferrer" 
                                   class="contact-btn contact-btn-whatsapp">
                                    <i class="fab fa-whatsapp"></i> WhatsApp
                                </a>
                                <a href="<?php echo htmlspecialchars($messenger_url); ?>" 
                                   target="_blank" 
                                   rel="noopener noreferrer" 
                                   class="contact-btn contact-btn-messenger">
                                    <i class="fab fa-facebook-messenger"></i> Messenger
                                </a>
                            </div>
                            
                            <div class="contact-note">
                                <i class="fas fa-location-dot"></i> <?php echo htmlspecialchars($somity_name); ?> · ফোন ও মেসেঞ্জার আইডি থেকে সরাসরি যোগাযোগ করুন
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== ACTION BUTTONS - 2 COLUMN ON MOBILE ===== -->
            <div class="action-group">
                <a href="<?php echo htmlspecialchars($login_url); ?>" 
                   class="btn btn-primary" 
                   role="button" 
                   aria-label="Back to login page">
                    <span class="arrow"><i class="fas fa-arrow-left"></i></span> Back to Login
                </a>
                <a href="/" 
                   class="btn btn-primary" 
                   role="button" 
                   style="background: linear-gradient(135deg, #6B7280, #9CA3AF); box-shadow: 0 6px 20px rgba(107, 114, 128, 0.3);">
                    <span class="arrow"><i class="fas fa-home"></i></span> Home
                </a>
            </div>

            <!-- ===== FOOTNOTE - FULL WIDTH ===== -->
            <div class="footnote-wrapper">
                <span class="footnote">
                    <i class="fas fa-hammer"></i>
                    <strong>We're building a secure password recovery system</strong> to protect your account. Thank you for your patience.
                </span>
            </div>

            <!-- ===== FOOTER - 2 COLUMNS WITH DEVELOPER CREDIT ===== -->
            <div class="footer-wrapper">
                <div class="col footer-left">
                    <span>
                        <i class="fas fa-copyright"></i> <?php echo date('Y'); ?> <?php echo htmlspecialchars($somity_name); ?>
                    </span>
                    <span class="footer-divider">|</span>
                 
                </div>
                <span class="dev-credit">
                        <span class="dev-label">Developed By</span>
                        <a href="https://onlinebd.free.nf/" target="_blank" rel="noopener noreferrer" class="dev-name">
                            <i class="fa-brands fa-edge-legacy"></i>
                            Mr.Amin
                        </a>
                    </span>
            </div>

        </div>
    </div>
    
</body>
</html>