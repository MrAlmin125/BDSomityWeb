<?php
// =====================================================
// member/includes/footer.php - Member Footer with Bottom Nav
// =====================================================

require_once __DIR__.'/../../includes/functions.php';

// ============================================
// FETCH SETTINGS FOR LOADER - USING CORRECT COLUMN NAMES
// ============================================
$loaderImage = '';
$loaderName = '';
$logoUrl = '';
$somityPhone = ''; // Added for WhatsApp
$messengerPageId = ''; // Added for Messenger

// Try to fetch settings from database using correct column names
if (function_exists('queryOne') && function_exists('queryAll')) {
    try {
        // Get all needed settings in one query
        $settingsAll = queryAll("SELECT key_name, key_value FROM settings WHERE key_name IN ('loader_image', 'loader_name', 'logo_url', 'somity_phone', 'messenger_page_id')");
        if (is_array($settingsAll)) {
            foreach ($settingsAll as $setting) {
                if ($setting['key_name'] == 'loader_image') {
                    $loaderImage = $setting['key_value'];
                } elseif ($setting['key_name'] == 'loader_name') {
                    $loaderName = $setting['key_value'];
                } elseif ($setting['key_name'] == 'logo_url') {
                    $logoUrl = $setting['key_value'];
                } elseif ($setting['key_name'] == 'somity_phone') {
                    $somityPhone = $setting['key_value'];
                } elseif ($setting['key_name'] == 'messenger_page_id') {
                    $messengerPageId = $setting['key_value'];
                }
            }
        }
        
        // If loader_image not found, try individual query
        if (empty($loaderImage)) {
            $imgResult = queryOne("SELECT key_value FROM settings WHERE key_name = 'loader_image'");
            if ($imgResult && isset($imgResult['key_value'])) {
                $loaderImage = $imgResult['key_value'];
            }
        }
        
        // If loader_name not found, try individual query
        if (empty($loaderName)) {
            $nameResult = queryOne("SELECT key_value FROM settings WHERE key_name = 'loader_name'");
            if ($nameResult && isset($nameResult['key_value'])) {
                $loaderName = $nameResult['key_value'];
            }
        }
        
        // Get logo_url if not already fetched
        if (empty($logoUrl)) {
            $logoResult = queryOne("SELECT key_value FROM settings WHERE key_name = 'logo_url'");
            if ($logoResult && isset($logoResult['key_value'])) {
                $logoUrl = $logoResult['key_value'];
            }
        }
        
        // Get somity_phone for WhatsApp
        if (empty($somityPhone)) {
            $phoneResult = queryOne("SELECT key_value FROM settings WHERE key_name = 'somity_phone'");
            if ($phoneResult && isset($phoneResult['key_value'])) {
                $somityPhone = $phoneResult['key_value'];
            }
        }
        
        // Get messenger_page_id for Messenger
        if (empty($messengerPageId)) {
            $messengerResult = queryOne("SELECT key_value FROM settings WHERE key_name = 'messenger_page_id'");
            if ($messengerResult && isset($messengerResult['key_value'])) {
                $messengerPageId = $messengerResult['key_value'];
            }
        }
    } catch (Exception $e) {
        // Silently fail and use defaults
    }
}

// ============================================
// WHATSAPP NUMBER PROCESSING - BANGLADESH ONLY
// ============================================
// Clean phone number (remove +, spaces, dashes, etc.)
$whatsappNumber = preg_replace('/[^0-9]/', '', $somityPhone);

// ============================================
// MESSENGER URL GENERATION
// ============================================
$messengerUrl = '';
if (!empty($messengerPageId)) {
    // If it's a numeric page ID, use m.me link
    if (is_numeric($messengerPageId)) {
        $messengerUrl = 'https://m.me/' . $messengerPageId;
    } else {
        // If it's a username, use m.me with username
        $messengerUrl = 'https://m.me/' . ltrim($messengerPageId, '@');
    }
}

// Set loader image - priority: loader_image > logo_url > default SVG
if (!empty($loaderImage) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($loaderImage, '/'))) {
    // Use loader_image from settings
    $loaderImagePath = $loaderImage;
} elseif (!empty($logoUrl) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($logoUrl, '/'))) {
    // Use logo_url as fallback
    $loaderImagePath = $logoUrl;
} else {
    // Use inline SVG as default loader
    $loaderImagePath = 'data:image/svg+xml,' . urlencode('
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
            <circle cx="50" cy="50" r="45" fill="#117A65" opacity="0.1"/>
            <path d="M50 5 A45 45 0 1 1 5 50" stroke="#117A65" stroke-width="6" fill="none">
                <animateTransform attributeName="transform" type="rotate" from="0 50 50" to="360 50 50" dur="1s" repeatCount="indefinite"/>
            </path>
            <circle cx="50" cy="50" r="15" fill="#117A65" opacity="0.8">
                <animate attributeName="r" values="10;20;10" dur="1.5s" repeatCount="indefinite"/>
                <animate attributeName="opacity" values="0.8;0.3;0.8" dur="1.5s" repeatCount="indefinite"/>
            </circle>
        </svg>
    ');
}

// Set loader name - priority: loader_name > somity_name > default
if (empty($loaderName)) {
    // Try to get somity_name from settings
    $somityResult = queryOne("SELECT key_value FROM settings WHERE key_name = 'somity_name'");
    if ($somityResult && isset($somityResult['key_value'])) {
        $loaderName = $somityResult['key_value'];
    } else {
        $loaderName = 'Loading...';
    }
}

// Check if member is logged in for badges
$pendingWdCount = 0;
$pendingFineCount = 0;

if (isset($_SESSION['member_id']) && function_exists('queryOne') && function_exists('queryAll')) {
    $mId = $_SESSION['member_id'];
    
    // Get pending withdrawals count
    $wdResult = queryOne("SELECT COUNT(*) as count FROM withdrawals WHERE member_id=? AND status='pending'", 'i', [$mId]);
    if ($wdResult && isset($wdResult['count'])) {
        $pendingWdCount = (int)$wdResult['count'];
    }
    
    // Get pending fines count
    $finesResult = queryAll("SELECT id FROM fines WHERE member_id=? AND status IN ('pending', 'partially_paid')", 'i', [$mId]);
    if (is_array($finesResult)) {
        $pendingFineCount = count($finesResult);
    }
}

?>

</main><!-- /main-content -->

<!-- ============================================
     PAGE LOADER - WITH SETTINGS IMAGE & NAME
     ============================================ -->
<style>
    /* ============================================
       PAGE LOADER STYLES
    ============================================ */
    #page-loader {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: #ffffff;
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 9999;
        transition: opacity 0.8s ease, visibility 0.8s ease;
        flex-direction: column;
        gap: 20px;
    }

    #page-loader.hide-loader {
        opacity: 0;
        visibility: hidden;
    }

    .loader-content {
        text-align: center;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 15px;
    }

    .loader-image-container {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        animation: loader-pulse 1.5s ease-in-out infinite;
        border: 3px solid #117A65;
        padding: 5px;
        background: #f8f9fa;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .loader-image-container img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 50%;
    }

    /* For SVG loader */
    .loader-image-container svg {
        width: 80px;
        height: 80px;
        border-radius: 50%;
    }

    .loader-name {
        font-size: 20px;
        font-weight: 600;
        color: #117A65;
        margin: 0;
        animation: loader-text-pulse 1.5s ease-in-out infinite;
    }

    .loader-spinner {
        display: flex;
        gap: 8px;
        margin-top: 5px;
    }

    .loader-spinner span {
        width: 12px;
        height: 12px;
        background: #117A65;
        border-radius: 50%;
        animation: loader-dot 1.4s ease-in-out infinite both;
    }

    .loader-spinner span:nth-child(1) {
        animation-delay: -0.32s;
    }

    .loader-spinner span:nth-child(2) {
        animation-delay: -0.16s;
    }

    .loader-spinner span:nth-child(3) {
        animation-delay: 0s;
    }

    .loader-progress-bar {
        width: 200px;
        height: 3px;
        background: #e5e7eb;
        border-radius: 10px;
        overflow: hidden;
        margin-top: 5px;
    }

    .loader-progress-bar .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #117A65, #1ABC9C);
        border-radius: 10px;
        width: 0%;
        animation: loader-progress 2s ease-in-out infinite;
    }

    @keyframes loader-pulse {
        0%, 100% {
            transform: scale(1);
            box-shadow: 0 4px 20px rgba(17, 122, 101, 0.1);
        }
        50% {
            transform: scale(1.05);
            box-shadow: 0 4px 30px rgba(17, 122, 101, 0.25);
        }
    }

    @keyframes loader-text-pulse {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: 0.6;
        }
    }

    @keyframes loader-dot {
        0%, 80%, 100% {
            transform: scale(0);
            opacity: 0.3;
        }
        40% {
            transform: scale(1);
            opacity: 1;
        }
    }

    @keyframes loader-progress {
        0% {
            width: 0%;
            margin-left: 0;
        }
        50% {
            width: 70%;
            margin-left: 0;
        }
        100% {
            width: 100%;
            margin-left: 0;
        }
    }

    /* Responsive loader */
    @media (max-width: 768px) {
        .loader-image-container {
            width: 90px;
            height: 90px;
        }

        .loader-image-container svg {
            width: 60px;
            height: 60px;
        }

        .loader-name {
            font-size: 16px;
        }

        .loader-progress-bar {
            width: 150px;
        }

        .loader-spinner span {
            width: 10px;
            height: 10px;
        }
    }

    @media (max-width: 480px) {
        .loader-image-container {
            width: 70px;
            height: 70px;
        }

        .loader-image-container svg {
            width: 50px;
            height: 50px;
        }

        .loader-name {
            font-size: 14px;
        }

        .loader-progress-bar {
            width: 120px;
        }

        .loader-spinner span {
            width: 8px;
            height: 8px;
        }
    }

    /* ============================================
       SOCIAL FLOATING ICONS - ARROW SHOW/HIDE
       ============================================ */
    .social-float-wrapper {
        position: fixed;
        bottom: 100px;
        right: 0;
        z-index: 998;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 10px;
        padding-right: 2px;
    }

    /* Container for icons - slides in/out */
    .social-icons-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        transform: translateX(120%);
        transition: transform 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        opacity: 0;
        visibility: hidden;
    }

    .social-icons-container.show {
        transform: translateX(0);
        opacity: 1;
        visibility: visible;
    }

    .social-icons-container.hide {
        transform: translateX(120%);
        opacity: 0;
        visibility: hidden;
    }

    /* Individual Social Button */
    .social-float-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 56px;
        height: 56px;
        border-radius: 50%;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        text-decoration: none;
        color: white;
        position: relative;
        border: 3px solid white;
        cursor: pointer;
        flex-shrink: 0;
    }

    .social-float-btn:hover {
        transform: scale(1.1);
        animation-play-state: paused;
    }

    .social-float-btn:active {
        transform: scale(0.95);
        transition-duration: 0.1s;
    }

    .social-float-btn svg {
        width: 28px;
        height: 28px;
        fill: white;
        transition: transform 0.3s ease;
    }

    .social-float-btn:hover svg {
        transform: rotate(10deg);
    }

    /* Messenger Button - TOP */
    .messenger-btn {
        background: #00B2FF;
        animation: float-bounce 2.5s ease-in-out infinite 0.3s;
        box-shadow: 0 4px 20px rgba(0, 178, 255, 0.4);
    }

    .messenger-btn:hover {
        box-shadow: 0 8px 35px rgba(0, 178, 255, 0.6);
    }

    /* WhatsApp Button - BOTTOM */
    .whatsapp-btn {
        background: #25D366;
        animation: float-bounce 2.5s ease-in-out infinite;
        box-shadow: 0 4px 20px rgba(37, 211, 102, 0.4);
    }

    .whatsapp-btn:hover {
        box-shadow: 0 8px 35px rgba(37, 211, 102, 0.6);
    }

    /* Pulse Rings */
    .pulse-ring {
        position: absolute;
        width: 100%;
        height: 100%;
        border-radius: 50%;
        top: 0;
        left: 0;
        opacity: 0;
        pointer-events: none;
    }

    .messenger-btn .pulse-ring {
        border: 2px solid #00B2FF;
    }

    .whatsapp-btn .pulse-ring {
        border: 2px solid #25D366;
    }

    .messenger-btn .pulse-ring:nth-child(1) {
        animation: pulse-ring 2.5s ease-out 0.3s infinite;
    }

    .messenger-btn .pulse-ring:nth-child(2) {
        animation: pulse-ring 2.5s ease-out 1.1s infinite;
    }

    .messenger-btn .pulse-ring:nth-child(3) {
        animation: pulse-ring 2.5s ease-out 1.9s infinite;
    }

    .whatsapp-btn .pulse-ring:nth-child(1) {
        animation: pulse-ring 2.5s ease-out infinite;
    }

    .whatsapp-btn .pulse-ring:nth-child(2) {
        animation: pulse-ring 2.5s ease-out 0.8s infinite;
    }

    .whatsapp-btn .pulse-ring:nth-child(3) {
        animation: pulse-ring 2.5s ease-out 1.6s infinite;
    }

    /* Online Status Badge */
    .online-badge {
        position: absolute;
        top: -2px;
        right: -2px;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        border: 2.5px solid white;
        box-shadow: 0 2px 8px rgba(76, 175, 80, 0.4);
        background: #4CAF50;
    }

    .online-badge::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 6px;
        height: 6px;
        background: #4CAF50;
        border-radius: 50%;
        animation: online-pulse 1.5s ease-in-out infinite;
    }

    /* ============================================
       TOGGLE ARROW BUTTON - VISIBLE ONLY WHEN ICONS HIDDEN
       ============================================ */
    .social-toggle-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        background: #117A65;
        border-radius: 50% 0 0 50%;
        box-shadow: 0 4px 20px rgba(17, 122, 101, 0.4);
        border: 2.5px solid white;
        border-right: none;
        color: white;
        cursor: pointer;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        position: relative;
        flex-shrink: 0;
        animation: toggle-pulse 2s ease-in-out infinite;
        opacity: 1;
        transform: translateX(0);
        visibility: visible;
        pointer-events: auto;
    }

    /* When icons are visible, hide the arrow button */
    .social-icons-container.show ~ .social-toggle-btn {
        opacity: 0;
        transform: translateX(50px);
        visibility: hidden;
        pointer-events: none;
        animation-play-state: paused;
    }

    .social-toggle-btn:hover {
        transform: scale(1.1);
        background: #0d5e4e;
        box-shadow: 0 4px 30px rgba(17, 122, 101, 0.6);
        animation-play-state: paused;
    }

    .social-toggle-btn:active {
        transform: scale(0.95);
    }

    .social-toggle-btn svg {
        width: 22px;
        height: 22px;
        fill: white;
        transition: transform 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        margin-left: 4px;
    }

    /* Arrow rotates when icons are shown */
    .social-icons-container.show ~ .social-toggle-btn svg {
        transform: rotate(180deg);
    }

    /* Toggle button pulse animation */
    @keyframes toggle-pulse {
        0%, 100% {
            box-shadow: 0 4px 20px rgba(17, 122, 101, 0.4);
        }
        50% {
            box-shadow: 0 4px 30px rgba(17, 122, 101, 0.7), 0 0 40px rgba(17, 122, 101, 0.2);
        }
    }

    /* Tooltip on toggle button */
    .social-toggle-tooltip {
        position: absolute;
        right: 55px;
        background: rgba(0, 0, 0, 0.85);
        backdrop-filter: blur(10px);
        color: white;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 500;
        white-space: nowrap;
        opacity: 0;
        transform: translateX(10px) scale(0.9);
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        pointer-events: none;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        z-index: 999;
    }

    .social-toggle-tooltip::after {
        content: '';
        position: absolute;
        right: -6px;
        top: 50%;
        transform: translateY(-50%);
        border-top: 6px solid transparent;
        border-bottom: 6px solid transparent;
        border-left: 6px solid rgba(0, 0, 0, 0.85);
    }

    .social-toggle-btn:hover .social-toggle-tooltip {
        opacity: 1;
        transform: translateX(0) scale(1);
    }

    /* ============================================
       INDIVIDUAL TOOLTIPS ON BUTTONS
       ============================================ */
    .social-tooltip {
        position: absolute;
        right: 65px;
        background: rgba(0, 0, 0, 0.85);
        backdrop-filter: blur(10px);
        color: white;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 500;
        white-space: nowrap;
        opacity: 0;
        transform: translateX(10px) scale(0.9);
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        pointer-events: none;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        z-index: 999;
    }

    .social-tooltip::after {
        content: '';
        position: absolute;
        right: -6px;
        top: 50%;
        transform: translateY(-50%);
        border-top: 6px solid transparent;
        border-bottom: 6px solid transparent;
        border-left: 6px solid rgba(0, 0, 0, 0.85);
    }

    .social-float-btn:hover .social-tooltip {
        opacity: 1;
        transform: translateX(0) scale(1);
    }

    /* ============================================
       ANIMATIONS
    ============================================ */

    /* Float Bounce Animation */
    @keyframes float-bounce {
        0%, 100% {
            transform: translateY(0px);
            animation-timing-function: ease-in-out;
        }
        25% {
            transform: translateY(-8px);
            animation-timing-function: ease-out;
        }
        55% {
            transform: translateY(-3px);
            animation-timing-function: ease-in;
        }
        75% {
            transform: translateY(-6px);
            animation-timing-function: ease-out;
        }
        90% {
            transform: translateY(-1px);
            animation-timing-function: ease-in;
        }
    }

    /* Pulse Ring Animation */
    @keyframes pulse-ring {
        0% {
            transform: scale(1);
            opacity: 0.8;
        }
        40% {
            opacity: 0.4;
        }
        100% {
            transform: scale(1.8);
            opacity: 0;
        }
    }

    /* Online Pulse */
    @keyframes online-pulse {
        0%, 100% {
            transform: translate(-50%, -50%) scale(1);
            opacity: 1;
        }
        50% {
            transform: translate(-50%, -50%) scale(1.5);
            opacity: 0.5;
        }
    }

    /* Notification Shake Animation */
    @keyframes notification-shake {
        0%, 100% {
            transform: rotate(0deg);
        }
        10%, 30%, 50%, 70%, 90% {
            transform: rotate(-8deg);
        }
        20%, 40%, 60%, 80% {
            transform: rotate(8deg);
        }
    }

    .notification-shake {
        animation: notification-shake 0.8s ease-in-out 3;
    }

    /* ============================================
       RESPONSIVE STYLES
    ============================================ */
    @media (max-width: 768px) {
        .social-float-wrapper {
            bottom: 85px;
            gap: 8px;
        }

        .social-icons-container {
            gap: 8px;
        }

        .social-float-btn {
            width: 48px;
            height: 48px;
        }

        .social-float-btn svg {
            width: 24px;
            height: 24px;
        }

        .social-toggle-btn {
            width: 38px;
            height: 38px;
        }

        .social-toggle-btn svg {
            width: 18px;
            height: 18px;
            margin-left: 3px;
        }

        .online-badge {
            width: 14px;
            height: 14px;
        }

        .online-badge::after {
            width: 5px;
            height: 5px;
        }

        .social-tooltip,
        .social-toggle-tooltip {
            font-size: 10px;
            padding: 4px 10px;
            right: 55px;
        }
    }

    @media (max-width: 480px) {
        .social-float-wrapper {
            bottom: 80px;
            gap: 6px;
        }

        .social-icons-container {
            gap: 6px;
        }

        .social-float-btn {
            width: 42px;
            height: 42px;
        }

        .social-float-btn svg {
            width: 20px;
            height: 20px;
        }

        .social-toggle-btn {
            width: 34px;
            height: 34px;
        }

        .social-toggle-btn svg {
            width: 16px;
            height: 16px;
            margin-left: 2px;
        }

        .online-badge {
            width: 12px;
            height: 12px;
        }

        .online-badge::after {
            width: 4px;
            height: 4px;
        }

        .social-tooltip,
        .social-toggle-tooltip {
            font-size: 9px;
            padding: 3px 8px;
            right: 48px;
        }

        @keyframes pulse-ring {
            0% {
                transform: scale(1);
                opacity: 0.8;
            }
            100% {
                transform: scale(1.6);
                opacity: 0;
            }
        }
    }

    @media (min-width: 769px) {
        .social-float-wrapper {
            bottom: 105px;
            gap: 12px;
        }

        .social-icons-container {
            gap: 12px;
        }
    }

    body {
        overflow-x: hidden;
    }

    /* ============================================
       BOTTOM NAVIGATION - FULL CSS
    ============================================ */
    .bottom-nav {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: #ffffff;
        border-top: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-around;
        align-items: center;
        z-index: 999;
        padding: 6px 0 env(safe-area-inset-bottom, 6px);
        box-shadow: 0 -2px 16px rgba(0,0,0,0.06);
        backdrop-filter: blur(10px);
        background: rgba(255,255,255,0.95);
        width: 100%;
        border-radius: 0;
    }

    .bottom-nav a {
        flex: 1;
        max-width: 70px;
        padding: 4px 0;
        text-align: center;
        color: #9ca3af;
        text-decoration: none;
        font-size: 9px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 2px;
        transition: all 0.3s ease;
        border-radius: 10px;
        margin: 0 2px;
        position: relative;
        background: transparent;
    }

    .bottom-nav a svg {
        width: 24px;
        height: 24px;
        transition: all 0.3s ease;
        stroke-width: 1.8;
    }

    .bottom-nav a span {
        font-size: 10px;
        font-weight: 500;
        letter-spacing: 0.2px;
        color: #9ca3af;
        transition: all 0.3s ease;
    }

    .bottom-nav a.active {
        color: #117A65;
    }

    .bottom-nav a.active svg {
        stroke-width: 2.5;
        color: #117A65;
        transform: translateY(-2px);
    }

    .bottom-nav a.active span {
        color: #117A65;
        font-weight: 600;
    }

    .bottom-nav a:hover:not(.active) {
        color: #6b7280;
        transform: translateY(-1px);
    }

    .bottom-nav a:hover:not(.active) svg {
        stroke-width: 2.2;
    }

    .bottom-nav .nav-badge {
        position: absolute;
        top: -4px;
        right: 50%;
        transform: translateX(14px);
        background: #EF4444;
        color: white;
        border-radius: 50%;
        padding: 1px 6px;
        font-size: 9px;
        font-weight: 700;
        min-width: 18px;
        text-align: center;
        line-height: 16px;
        box-shadow: 0 2px 6px rgba(239, 68, 68, 0.35);
        border: 2px solid white;
    }

    .bottom-nav .nav-badge.fine-badge {
        background: #E74C3C;
        animation: pulse-badge 2s ease-in-out infinite;
        border-color: #E74C3C;
    }

    .bottom-nav .nav-badge.withdraw-badge {
        background: #F59E0B;
        border-color: #F59E0B;
    }

    @keyframes pulse-badge {
        0%, 100% { 
            transform: translateX(14px) scale(1); 
            box-shadow: 0 2px 6px rgba(239, 68, 68, 0.35);
        }
        50% { 
            transform: translateX(14px) scale(1.15); 
            box-shadow: 0 2px 12px rgba(239, 68, 68, 0.6);
        }
    }

    @media (max-width: 768px) {
        .bottom-nav {
            width: 100%;
            left: 0;
            right: 0;
            border-radius: 0;
            padding: 6px 8px env(safe-area-inset-bottom, 6px);
        }
        
        .bottom-nav a {
            max-width: 60px;
            padding: 4px 0;
        }
        
        .bottom-nav a svg {
            width: 22px;
            height: 22px;
        }
        
        .bottom-nav a span {
            font-size: 9px;
        }
        
        .bottom-nav .nav-badge {
            font-size: 8px;
            min-width: 16px;
            line-height: 14px;
            transform: translateX(10px);
            padding: 0 5px;
            top: -3px;
        }
        
        @keyframes pulse-badge {
            0%, 100% { transform: translateX(10px) scale(1); }
            50% { transform: translateX(10px) scale(1.15); }
        }
    }

    @media (max-width: 480px) {
        .bottom-nav {
            padding: 4px 4px env(safe-area-inset-bottom, 4px);
        }
        
        .bottom-nav a {
            max-width: 50px;
            padding: 2px 0;
        }
        
        .bottom-nav a svg {
            width: 18px;
            height: 18px;
        }
        
        .bottom-nav a span {
            font-size: 8px;
        }
        
        .bottom-nav .nav-badge {
            font-size: 7px;
            min-width: 14px;
            line-height: 12px;
            transform: translateX(8px);
            padding: 0 4px;
            top: -2px;
        }
        
        @keyframes pulse-badge {
            0%, 100% { transform: translateX(8px) scale(1); }
            50% { transform: translateX(8px) scale(1.15); }
        }
    }

    @media (min-width: 769px) {
        .bottom-nav {
            max-width: 700px;
            left: 50%;
            transform: translateX(-50%);
            border-radius: 16px 16px 0 0;
            width: 100%;
        }
        
        .bottom-nav a {
            max-width: 70px;
        }
        
        .bottom-nav a svg {
            width: 24px;
            height: 24px;
        }
        
        .bottom-nav a span {
            font-size: 10px;
        }
        
        .bottom-nav .nav-badge {
            font-size: 9px;
            min-width: 18px;
            line-height: 16px;
            transform: translateX(14px);
            padding: 0 6px;
            top: -4px;
        }
    }

    /* ============================================
       DEVELOPER CREDIT STYLES
    ============================================ */
    .developer-credit {
        text-align: center;
        padding: 10px 20px;
        background: #F0F4F8;
        border: 1px dashed #198754;
        border-radius: 6px;
        font-size: 14px;
        color: #4a5568;
        margin: 0 20px;
        box-sizing: border-box;
        clear: both;
    }

    .developer-credit a {
        color: #117A65;
        text-decoration: none;
        font-weight: 600;
        transition: color 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .developer-credit a:hover {
        color: #0d5e4e;
        text-decoration: underline;
    }

    .developer-credit .fb-icon {
        display: inline-block;
        width: 20px;
        height: 20px;
        background: #1877F2;
        color: #fff;
        border-radius: 4px;
        text-align: center;
        line-height: 20px;
        font-size: 12px;
        font-weight: 700;
        transition: transform 0.3s ease;
    }

    .developer-credit a:hover .fb-icon {
        transform: scale(1.1);
    }

    @media (max-width: 768px) {
        .developer-credit {
            font-size: 12px;
            padding: 8px 15px;
            margin: 0 15px;
        }

        .developer-credit .fb-icon {
            width: 18px;
            height: 18px;
            line-height: 18px;
            font-size: 10px;
        }
    }
</style>

<!-- ============================================
     SOCIAL FLOATING ICONS - WITH TOGGLE ARROW
     ============================================ -->
<div class="social-float-wrapper" id="socialFloatWrapper">
    
    <!-- Social Icons Container (slides in/out) -->
    <div class="social-icons-container" id="socialIconsContainer">
        
        <!-- Messenger Button - TOP -->
        <?php if (!empty($messengerUrl)): ?>
        <a href="<?= $messengerUrl ?>" 
           target="_blank" 
           rel="noopener noreferrer"
           class="social-float-btn messenger-btn"
           aria-label="Chat on Messenger"
           title="Chat on Messenger">
            
            <!-- Pulse Rings -->
            <span class="pulse-ring"></span>
            <span class="pulse-ring"></span>
            <span class="pulse-ring"></span>
            
            <!-- Messenger SVG Icon (Chat Bubble with Lightning Bolt) -->
<svg viewBox="0 0 512 512"><path d="M256.6 8c-140 0-248.6 102.3-248.6 240.6 0 72.3 29.7 134.8 78.1 177.9 8.3 7.5 6.6 11.9 8 58.2 .1 3.2 1 6.4 2.6 9.2s3.9 5.2 6.7 6.9 5.9 2.8 9.1 3 6.5-.3 9.5-1.6C174.9 479 175.6 477.2 184.6 479.6 337.8 521.8 504 423.7 504 248.6 504 110.3 396.6 8 256.6 8zM405.8 193.1l-73 115.6c-2.8 4.3-6.4 8.1-10.6 11s-9.1 4.8-14.1 5.8-10.3 .8-15.3-.4-9.7-3.4-13.8-6.4l-58.1-43.5c-2.6-1.9-5.8-3-9-3s-6.4 1.1-9 3l-78.4 59.4c-10.5 7.9-24.2-4.6-17.1-15.7l73-115.6c2.8-4.3 6.4-8.1 10.6-11s9.1-4.8 14.1-5.8 10.3-.8 15.3 .4 9.7 3.4 13.9 6.4l58.1 43.5c2.6 1.9 5.8 3 9 3s6.4-1.1 9-3l78.4-59.4c10.4-8 24.1 4.5 17.1 15.6z"/></svg>            
            <!-- Online Status Badge -->
            <span class="online-badge"></span>
            
            <!-- Tooltip -->
            <span class="social-tooltip">💬 Messenger</span>
        </a>
        <?php endif; ?>
        
        <!-- WhatsApp Button - BOTTOM -->
        <?php if (!empty($whatsappNumber)): ?>
        <a href="https://wa.me/<?= $whatsappNumber ?>?text=<?= urlencode('How can I assist you?') ?>" 
           target="_blank" 
           rel="noopener noreferrer"
           class="social-float-btn whatsapp-btn"
           aria-label="Chat on WhatsApp"
           title="Chat on WhatsApp">
            
            <!-- Pulse Rings -->
            <span class="pulse-ring"></span>
            <span class="pulse-ring"></span>
            <span class="pulse-ring"></span>
            
            <!-- WhatsApp SVG Icon -->
            <svg viewBox="0 0 24 24">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
            </svg>
            
            <!-- Online Status Badge -->
            <span class="online-badge"></span>
            
            <!-- Tooltip -->
            <span class="social-tooltip">💬 WhatsApp</span>
        </a>
        <?php endif; ?>
    </div>
    
    <!-- Toggle Arrow Button - Visible ONLY when icons are hidden -->
    <div class="social-toggle-btn" id="socialToggleBtn" title="Open Chat">
        <!-- Arrow Icon -->
        <svg viewBox="0 0 24 24">
            <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/>
        </svg>
        
        <!-- Tooltip -->
        <span class="social-toggle-tooltip" id="toggleTooltip">Open Chat</span>
    </div>
</div>

<!-- ============================================
     PAGE LOADER
     ============================================ -->
<div id="page-loader">
    <div class="loader-content">
        <div class="loader-image-container">
            <?php if (strpos($loaderImagePath, 'data:image') === 0): ?>
                <?php echo urldecode(htmlspecialchars_decode($loaderImagePath)); ?>
            <?php else: ?>
                <img src="<?= htmlspecialchars($loaderImagePath) ?>" alt="<?= htmlspecialchars($loaderName) ?>" id="loader-img">
            <?php endif; ?>
        </div>
        <h3 class="loader-name"><?= htmlspecialchars($loaderName) ?></h3>
        <div class="loader-spinner">
            <span></span>
            <span></span>
            <span></span>
        </div>
        <div class="loader-progress-bar">
            <div class="progress-fill"></div>
        </div>
    </div>
</div>

<!-- ============================================
     BOTTOM NAVIGATION
     ============================================ -->
<nav class="bottom-nav" role="navigation" aria-label="Bottom Navigation">
    <a href="/member/index.php" class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>" data-page="home">
        <i data-feather="home"></i>
        <span>হোম</span>
    </a>
    <a href="/member/deposits.php" class="<?= basename($_SERVER['PHP_SELF']) == 'deposits.php' ? 'active' : '' ?>" data-page="deposit">
        <i data-feather="credit-card"></i>
        <span>সঞ্চয়</span>
    </a>
    <a href="/member/withdrawals.php" class="<?= basename($_SERVER['PHP_SELF']) == 'withdrawals.php' ? 'active' : '' ?>" data-page="withdraw">
        <i data-feather="arrow-up-circle"></i>
        <span>উত্তোলন</span>
        <?php if ($pendingWdCount > 0): ?>
        <span class="nav-badge withdraw-badge"><?= $pendingWdCount ?></span>
        <?php endif; ?>
    </a>
    <a href="/member/fines.php" class="<?= basename($_SERVER['PHP_SELF']) == 'fines.php' ? 'active' : '' ?>" data-page="fines">
        <i data-feather="alert-triangle"></i>
        <span>জরিমানা</span>
        <?php if ($pendingFineCount > 0): ?>
        <span class="nav-badge fine-badge"><?= $pendingFineCount ?></span>
        <?php endif; ?>
    </a>
    <a href="/member/profile.php" class="<?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>" data-page="profile">
        <i data-feather="user"></i>
        <span>প্রোফাইল</span>
    </a>
</nav>

<!-- ============================================
     DEVELOPER CREDIT
     ============================================ -->
<div class="developer-credit">
   <i class="fa-brands fa-edge-legacy" style="color: rgb(15, 111, 92); margin-right: 4px;"></i> Developed By 
     <a href="https://amin.free.nf/" target="_blank" rel="noopener noreferrer"> Mr. Amin </a>
</div>

<!-- ============================================
     SCRIPTS
     ============================================ -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
    
    // ============================================
    // HIDE PAGE LOADER
    // ============================================
    window.addEventListener('load', function() {
        const loader = document.getElementById('page-loader');
        if (loader) {
            setTimeout(function() {
                loader.classList.add('hide-loader');
            }, 500);
        }
    });
    
    setTimeout(function() {
        const loader = document.getElementById('page-loader');
        if (loader && !loader.classList.contains('hide-loader')) {
            loader.classList.add('hide-loader');
        }
    }, 3000);
    
    // ============================================
    // SOCIAL TOGGLE CONTROLLER - SLIDE IN/OUT
    // ============================================
    const iconsContainer = document.getElementById('socialIconsContainer');
    const toggleBtn = document.getElementById('socialToggleBtn');
    const toggleTooltip = document.getElementById('toggleTooltip');
    let autoHideTimeout = null;
    let isVisible = false;
    
    if (iconsContainer && toggleBtn) {
        // Function to show icons (slide in)
        function showIcons() {
            iconsContainer.classList.remove('hide');
            iconsContainer.classList.add('show');
            if (toggleTooltip) {
                toggleTooltip.textContent = 'Close Chat';
            }
            isVisible = true;
            
            if (autoHideTimeout) {
                clearTimeout(autoHideTimeout);
                autoHideTimeout = null;
            }
        }
        
        // Function to hide icons (slide out)
        function hideIcons() {
            iconsContainer.classList.remove('show');
            iconsContainer.classList.add('hide');
            if (toggleTooltip) {
                toggleTooltip.textContent = 'Open Chat';
            }
            isVisible = false;
        }
        
        // Toggle on click
        toggleBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            
            if (isVisible) {
                hideIcons();
            } else {
                showIcons();
                // Auto hide after 6 seconds
                autoHideTimeout = setTimeout(function() {
                    hideIcons();
                }, 6000);
            }
        });
        
        // Auto show after page load
        setTimeout(function() {
            showIcons();
            // Auto hide after 6 seconds
            autoHideTimeout = setTimeout(function() {
                hideIcons();
            }, 6000);
        }, 1000);
        
        // Hover to keep visible
        iconsContainer.addEventListener('mouseenter', function() {
            if (autoHideTimeout) {
                clearTimeout(autoHideTimeout);
                autoHideTimeout = null;
            }
        });
        
        iconsContainer.addEventListener('mouseleave', function() {
            if (isVisible) {
                autoHideTimeout = setTimeout(function() {
                    hideIcons();
                }, 3000);
            }
        });
    }
    
    // ============================================
    // WHATSAPP NOTIFICATION SHAKE
    // ============================================
    const whatsappBtn = document.querySelector('.whatsapp-btn');
    if (whatsappBtn) {
        setTimeout(function() {
            whatsappBtn.classList.add('notification-shake');
            setTimeout(function() {
                whatsappBtn.classList.remove('notification-shake');
            }, 2400);
        }, 3000);
    }
});

// ============================================
// CLOSE ALERTS AFTER 5 SECONDS
// ============================================
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        if (alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }
    });
}, 5000);
</script>

<?php if (isset($extraJS)): ?>
    <?= $extraJS ?>
<?php endif; ?>

</body>
</html>