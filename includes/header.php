<?php
// =====================================================
// admin/includes/header.php - Admin Header

// =====================================================

// ============================================
// BACKUP VISIT TRACKER - প্রতিদিন ২ বার প্রয়োজন
// ============================================
function checkBackupVisit() {
    $today = date('Y-m-d');
    $cookieName = 'backup_visits';
    
    $visits = [];
    if (isset($_COOKIE[$cookieName])) {
        $visits = json_decode($_COOKIE[$cookieName], true);
        if (!is_array($visits)) $visits = [];
    }
    
    $todayVisits = isset($visits[$today]) ? $visits[$today] : 0;
    $requiredVisits = 2;
 
    if ($todayVisits < $requiredVisits) {
        return ['status' => 'unvisited'];
    } else {
        return ['status' => 'visited'];
    }
}

// ============================================
// IPHONE FIX: Handle backup visit via POST
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['backup_visited'])) {
    $today = date('Y-m-d');
    $cookieName = 'backup_visits';
    
    $visits = [];
    if (isset($_COOKIE[$cookieName])) {
        $visits = json_decode($_COOKIE[$cookieName], true);
        if (!is_array($visits)) $visits = [];
    }
    
    if (!isset($visits[$today])) $visits[$today] = 0;
    $visits[$today]++;
    
    $weekAgo = date('Y-m-d', strtotime('-7 days'));
    foreach ($visits as $date => $count) {
        if ($date < $weekAgo) unset($visits[$date]);
    }
    
    setcookie($cookieName, json_encode($visits), time() + 2592000, '/');
    
    // JSON response for AJAX
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success']);
        exit;
    }
    
    // Redirect back
    $referer = $_SERVER['HTTP_REFERER'] ?? '/admin/index.php';
    header('Location: ' . $referer);
    exit;
}

$backupStatus = checkBackupVisit();
$showBackupHighlight = ($backupStatus['status'] === 'unvisited');

// ============================================
// LIVE ADMIN SESSION TRACKING
// InfinityFree open_basedir FIX
// ============================================

// Do not start the session again if it is already active.
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// Track the current admin's activity in their PHP session.
// Does not create files or access restricted directories.
if (
    session_status() === PHP_SESSION_ACTIVE &&
    isset($_SESSION['admin_id'])
) {
    $_SESSION['admin_last_activity'] = time();
}
// ============================================
// CHECK SIDEBAR STATE FROM COOKIE
// ============================================
$sidebarCollapsed = isset($_COOKIE['sidebar_collapsed']) && $_COOKIE['sidebar_collapsed'] === 'true';

// ============================================
// GET SOMITY NAME AND LOGO
// ============================================
$somity_name = '';
$logo_url = '';
$favicon_url = '';

try {
    $somity_name = getSetting('somity_name', 'সমিতি');
    $logo_url = getSetting('logo_url', '');
    $favicon_url = getSetting('favicon_url', '');
} catch (Exception $e) {
    $somity_name = 'সমিতি';
    $logo_url = '';
    $favicon_url = '';
}

// ============================================
// FIX: Build full logo URL
// ============================================
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

// ============================================
// FIX: Build full favicon URL
// ============================================
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

// ============================================
// GET CURRENT ADMIN (for profile photo only)
// ============================================
$admin = getCurrentAdmin();

// Get profile photo URL
$profilePhotoUrl = null;
if (!empty($admin['profile_photo'])) {
    $photoPath = $admin['profile_photo'];
    if (strpos($photoPath, 'http') !== 0) {
        if (strpos($photoPath, '/') !== 0) {
            $photoPath = '/' . $photoPath;
        }
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        $profilePhotoUrl = $protocol . $host . $photoPath;
    } else {
        $profilePhotoUrl = $photoPath;
    }
}

// ============================================
// SIDEBAR ACTIVE MENU HELPER FUNCTION
// ============================================
function isActiveMenu($pageName, $queryParam = null, $queryValue = null) {
    $currentPage = basename($_SERVER['PHP_SELF']);
    
    if ($currentPage !== $pageName) {
        return false;
    }
    
    if ($queryParam !== null) {
        $queryString = $_SERVER['QUERY_STRING'] ?? '';
        parse_str($queryString, $params);
        
        if ($queryValue !== null) {
            return isset($params[$queryParam]) && $params[$queryParam] === $queryValue;
        } else {
            return isset($params[$queryParam]);
        }
    }
    
    return true;
}

// ============================================
// GET CURRENT PAGE INFO FOR MENU HIGHLIGHTING
// ============================================
$currentPage = basename($_SERVER['PHP_SELF']);
$queryString = $_SERVER['QUERY_STRING'] ?? '';
parse_str($queryString, $queryParams);
?>
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <meta name="theme-color" content="#1B4F72">
  
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
  
  <title><?= clean($pageTitle ?? APP_NAME) ?> — <?= clean($somity_name) ?></title>

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Feather Icons -->
  <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
  <!-- Google Fonts: Bengali -->
  <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6/css/all.min.css">
  <style>
    /* =====================================================
       সমিতি ম্যানেজার — Custom CSS
    ===================================================== */
    :root {
      --primary: #1B4F72;
      --primary-light: #2E86C1;
      --secondary: #117A65;
      --accent: #F39C12;
      --danger: #C0392B;
      --danger-hover: #E74C3C;
      --success: #1E8449;
      --surface: #F4F6F9;
      --card-bg: #FFFFFF;
      --text-dark: #1C2833;
      --text-muted: #566573;
      --border: #DEE2E6;
      --sidebar-w: 260px;
      --sidebar-collapsed-w: 70px;
      --header-h: 60px;
      --transition-speed: 0.3s;
    }

    * { box-sizing: border-box; }

    body {
      font-family: 'Hind Siliguri', sans-serif;
      background: var(--surface);
      color: var(--text-dark);
      font-size: 15px;
      line-height: 1.6;
      margin: 0;
      padding: 0;
    }

    /* =====================================================
       LOADER SYSTEM - Full Page Loader
    ===================================================== */
    #loader-wrapper {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: #ffffff;
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
      stroke: #e8edf3;
    }

    .loader-circle .progress-circle {
      stroke: var(--primary);
      stroke-dasharray: 340;
      stroke-dashoffset: 340;
      animation: spin-loader 1.8s cubic-bezier(0.65, 0, 0.35, 1) infinite;
    }

    @keyframes spin-loader {
      0% { stroke-dashoffset: 340; transform: rotate(0deg); }
      50% { stroke-dashoffset: 85; transform: rotate(180deg); }
      100% { stroke-dashoffset: 340; transform: rotate(360deg); }
    }

    .loader-circle::before {
      content: '';
      position: absolute;
      inset: -8px;
      border-radius: 50%;
      border: 2px solid var(--primary-light);
      opacity: 0.2;
      animation: pulse-ring 2s ease-out infinite;
    }

    @keyframes pulse-ring {
      0% { transform: scale(0.95); opacity: 0.3; }
      50% { transform: scale(1.05); opacity: 0.1; }
      100% { transform: scale(0.95); opacity: 0.3; }
    }

    .loader-logo {
      width: 70px;
      height: 70px;
      border-radius: 50%;
      object-fit: cover;
      z-index: 2;
      background: #ffffff;
      padding: 6px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    }

    .loader-logo-placeholder {
      width: 70px;
      height: 70px;
      border-radius: 50%;
      background: var(--primary);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 28px;
      font-weight: 700;
      z-index: 2;
      box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    }

    .loader-text {
      font-size: 15px;
      color: var(--text-muted);
      font-weight: 500;
      letter-spacing: 0.5px;
      animation: text-pulse 1.5s ease-in-out infinite;
    }

    .loader-text strong {
      color: var(--primary);
    }

    @keyframes text-pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.4; }
    }

    .loader-dots {
      display: inline-flex;
      gap: 4px;
      margin-left: 4px;
    }

    .loader-dots span {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: var(--primary-light);
      animation: dot-bounce 1.4s ease-in-out infinite both;
    }

    .loader-dots span:nth-child(1) { animation-delay: -0.32s; }
    .loader-dots span:nth-child(2) { animation-delay: -0.16s; }
    .loader-dots span:nth-child(3) { animation-delay: 0s; }

    @keyframes dot-bounce {
      0%, 80%, 100% { transform: scale(0.6); opacity: 0.4; }
      40% { transform: scale(1); opacity: 1; }
    }

    /* ============================================
       SIDEBAR - FIXED
    ============================================ */
    .sidebar {
      position: fixed;
      top: 0;
      left: 0;
      bottom: 0;
      width: var(--sidebar-w);
      background: var(--primary);
      z-index: 1000;
      display: flex;
      flex-direction: column;
      transition: width var(--transition-speed) ease, transform var(--transition-speed) ease;
      overflow: visible;
      box-shadow: 2px 0 10px rgba(0,0,0,0.1);
    }

    .sidebar.collapsed {
      width: var(--sidebar-collapsed-w);
    }

    /* ============================================
       SIDEBAR BRAND - CIRCLE LOGO
    ============================================ */
    .sidebar-brand {
      padding: 14px 16px;
      border-bottom: 1px solid rgba(255,255,255,.1);
      display: flex;
      align-items: center;
      gap: 12px;
      flex-shrink: 0;
      background: var(--primary);
      z-index: 2;
      transition: all var(--transition-speed) ease;
      min-height: 68px;
      text-decoration: none;
      color: white;
      cursor: pointer;
      position: relative;
    }

    .sidebar-brand:hover {
      background: rgba(255,255,255,.05);
    }

    .sidebar.collapsed .sidebar-brand {
      padding: 14px 10px;
      justify-content: center;
      gap: 0;
    }

    .sidebar-brand .brand-icon {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;
      color: white;
      font-weight: 700;
      flex-shrink: 0;
      transition: all var(--transition-speed) ease;
      overflow: hidden;
      background: var(--accent);
      border: 2px solid rgba(255,255,255,.2);
    }

    .sidebar-brand .brand-icon.has-logo {
      background: transparent;
      padding: 2px;
      border: 2px solid rgba(255,255,255,.2);
    }

    .sidebar-brand .brand-icon img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 50%;
    }

    .sidebar.collapsed .brand-icon {
      width: 44px;
      height: 44px;
      font-size: 18px;
      flex-shrink: 0;
      border: 2px solid rgba(255,255,255,.3);
    }

    .sidebar.collapsed .brand-icon img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 50%;
    }

    .sidebar-brand .brand-text {
      color: white;
      font-size: 12px;
      font-weight: 600;
      line-height: 1.2;
      white-space: nowrap;
      overflow: hidden;
      transition: opacity var(--transition-speed) ease, width var(--transition-speed) ease, margin var(--transition-speed) ease;
      flex-shrink: 0;
      max-width: 160px;
    }

    .sidebar-brand .brand-text .brand-name {
      display: block;
      font-size: 12px;
      font-weight: 600;
      letter-spacing: 0.1px;
    }

    .sidebar-brand .brand-text .brand-sub {
      display: block;
      font-size: 9px;
      opacity: .7;
      font-weight: 400;
      letter-spacing: 0.2px;
    }

    .sidebar.collapsed .brand-text {
      opacity: 0;
      width: 0;
      margin: 0;
      padding: 0;
      overflow: hidden;
    }

    .sidebar-toggle-btn {
      background: rgba(255,255,255,.1);
      border: none;
      color: white;
      width: 30px;
      height: 30px;
      border-radius: 6px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.2s;
      flex-shrink: 0;
      margin-left: auto;
      position: relative;
      z-index: 10;
    }

    .sidebar-toggle-btn:hover {
      background: rgba(255,255,255,.2);
    }

    .sidebar.collapsed .sidebar-toggle-btn {
      position: absolute;
      right: -15px;
      top: 50%;
      transform: translateY(-50%);
      background: #fff;
      color: #000;
      border: 1px solid rgba(255,255,255,.2);
      border-radius: 50%;
      width: 28px;
      height: 28px;
      padding: 0;
      margin: 0;
      box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }

    .sidebar.collapsed .sidebar-toggle-btn:hover {
      background: var(--primary-light);
    }

    .sidebar-toggle-btn .toggle-icon {
      width: 16px;
      height: 16px;
      transition: transform 0.3s ease;
    }

    .sidebar.collapsed .sidebar-toggle-btn .toggle-icon {
      transform: rotate(180deg);
    }

    @media (max-width: 768px) {
      .sidebar-toggle-btn {
        display: none !important;
      }
    }

    /* ============================================
       Sidebar Menu
    ============================================ */
    .sidebar-menu {
      flex: 1;
      overflow-y: auto;
      overflow-x: hidden;
      padding-bottom: 20px;
      scrollbar-width: thin;
      scrollbar-color: rgba(255,255,255,.2) transparent;
    }

    .sidebar-menu::-webkit-scrollbar {
      width: 3px;
    }
    .sidebar-menu::-webkit-scrollbar-thumb {
      background: rgba(255,255,255,.2);
      border-radius: 2px;
    }
    .sidebar-menu::-webkit-scrollbar-track {
      background: transparent;
    }

    .nav-section {
      padding: 10px 0 4px;
    }

    .nav-section-label {
      padding: 0 20px 4px;
      font-size: 10px;
      letter-spacing: 1px;
      color: rgba(255,255,255,.4);
      text-transform: uppercase;
      font-weight: 600;
      white-space: nowrap;
      overflow: hidden;
      transition: all var(--transition-speed) ease;
    }

    .sidebar.collapsed .nav-section-label {
      opacity: 0;
      height: 0;
      padding: 0 10px;
      margin: 0;
    }

    .sidebar .nav-link {
      padding: 9px 20px;
      color: rgba(255,255,255,.8);
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 14px;
      border-left: 3px solid transparent;
      transition: all .2s;
      text-decoration: none;
      white-space: nowrap;
      overflow: hidden;
      position: relative;
    }

    .sidebar.collapsed .nav-link {
      padding: 9px 12px;
      justify-content: center;
    }

    .sidebar .nav-link:hover,
    .sidebar .nav-link.active {
      color: white;
      background: rgba(255,255,255,.1);
      border-left-color: var(--accent);
    }

    /* ============================================
       BACKUP DOT - লাল ডট + পালসিং অ্যানিমেশন
    ============================================ */
    .nav-badge.backup-dot {
      width: 12px !important;
      height: 12px !important;
      min-width: 12px !important;
      padding: 0 !important;
      border-radius: 50% !important;
      background: #F39C12 !important;
      display: inline-block !important;
      animation: dotPulse 1.2s ease-in-out infinite;
      box-shadow: 0 0 15px rgba(220, 53, 69, 0.6);
      border: 2px solid rgba(255, 255, 255, 0.3);
      margin-left: auto !important;
      flex-shrink: 0 !important;
    }

    @keyframes dotPulse {
      0% {
        transform: scale(1);
        box-shadow: 0 0 15px rgba(220, 53, 69, 0.4);
      }
      50% {
        transform: scale(1.4);
        box-shadow: 0 0 30px rgba(220, 53, 69, 0.8);
      }
      100% {
        transform: scale(1);
        box-shadow: 0 0 15px rgba(220, 53, 69, 0.4);
      }
    }

    .nav-badge.backup-dot.hidden-dot {
      animation: hideDot 0.5s ease forwards;
    }

    @keyframes hideDot {
      0% {
        transform: scale(1);
        opacity: 1;
      }
      50% {
        transform: scale(1.5);
        opacity: 0.5;
      }
      100% {
        transform: scale(0);
        opacity: 0;
        width: 0 !important;
        height: 0 !important;
        min-width: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: hidden;
        border: none;
      }
    }

    /* Collapsed state */
    .sidebar.collapsed .nav-badge.backup-dot {
      width: 10px !important;
      height: 10px !important;
      min-width: 10px !important;
      margin-left: 0 !important;
      animation: dotPulse 1.2s ease-in-out infinite;
    }

    .sidebar.collapsed .nav-badge.backup-dot.hidden-dot {
      animation: hideDot 0.5s ease forwards;
    }

    /* ============================================
       Logout button
    ============================================ */
    .sidebar .nav-link.logout-link {
      color: #ff6b6b !important;
      border-left-color: transparent !important;
      font-weight: 600;
    }

    .sidebar .nav-link.logout-link:hover {
      color: #ff4444 !important;
      background: rgba(255, 0, 0, 0.15) !important;
      border-left-color: #ff4444 !important;
    }

    .sidebar .nav-link.logout-link .nav-icon {
      color: #ff6b6b !important;
      opacity: 1 !important;
    }

    .sidebar .nav-link.logout-link:hover .nav-icon {
      color: #ff4444 !important;
    }

    .sidebar .nav-link .nav-icon {
      width: 18px;
      height: 18px;
      flex-shrink: 0;
      opacity: .8;
    }

    .sidebar .nav-link.active .nav-icon { opacity: 1; }

    .sidebar .nav-link .nav-text {
      transition: opacity var(--transition-speed) ease;
    }

    .sidebar.collapsed .nav-link .nav-text {
      opacity: 0;
      width: 0;
      margin: 0;
      padding: 0;
    }

    /* Tooltip for collapsed state */
    .sidebar.collapsed .nav-link:hover::after {
      content: attr(data-tooltip);
      position: absolute;
      left: calc(var(--sidebar-collapsed-w) + 12px);
      top: 50%;
      transform: translateY(-50%);
      background: var(--primary);
      color: white;
      padding: 6px 14px;
      border-radius: 6px;
      font-size: 13px;
      white-space: nowrap;
      z-index: 1001;
      box-shadow: 0 4px 12px rgba(0,0,0,0.3);
      animation: tooltipFade 0.2s ease;
    }

    .sidebar.collapsed .nav-link.logout-link:hover::after {
      background: #c0392b;
      color: white;
    }

    @keyframes tooltipFade {
      from { opacity: 0; transform: translateY(-50%) translateX(-10px); }
      to { opacity: 1; transform: translateY(-50%) translateX(0); }
    }

    .nav-badge {
      margin-left: auto;
      background: var(--accent);
      color: white;
      font-size: 10px;
      padding: 2px 6px;
      border-radius: 10px;
      font-weight: 600;
      flex-shrink: 0;
      transition: all var(--transition-speed) ease;
    }

    .sidebar.collapsed .nav-badge {
      display: none;
    }

    /* ============================================
       TOPBAR
    ============================================ */
    .topbar {
      position: fixed;
      top: 0;
      left: var(--sidebar-w);
      right: 0;
      height: var(--header-h);
      background: white;
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      padding: 0 20px;
      gap: 12px;
      z-index: 999;
      box-shadow: 0 1px 4px rgba(0,0,0,.06);
      flex-shrink: 0;
      transition: left var(--transition-speed) ease;
    }

    .sidebar.collapsed ~ .topbar {
      left: var(--sidebar-collapsed-w);
    }

    .topbar .page-title {
      font-size: 16px;
      font-weight: 600;
      color: var(--text-dark);
      flex: 1;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .topbar .btn-sidebar-toggle {
      background: none;
      border: none;
      padding: 6px;
      color: var(--text-dark);
      cursor: pointer;
      display: none;
      align-items: center;
      justify-content: center;
    }

    .topbar .btn-sidebar-toggle:hover {
      color: var(--primary);
    }

    /* ============================================
       TOPBAR RIGHT
    ============================================ */
    .topbar-right {
      display: flex;
      align-items: center;
      gap: 12px;
      flex-shrink: 0;
    }

    .topbar-right .notification-btn,
    .topbar-right .dropdown-toggle {
      display: flex;
      align-items: center;
      justify-content: center;
      min-width: 36px;
      min-height: 36px;
      padding: 6px 10px;
      border-radius: 8px;
      background: #f8f9fa;
      border: 1px solid var(--border);
      transition: all 0.2s;
      text-decoration: none;
      color: var(--text-dark);
    }

    .topbar-right .notification-btn:hover,
    .topbar-right .dropdown-toggle:hover {
      background: #e9ecef;
      border-color: #ced4da;
    }

    .topbar-right .notification-btn {
      position: relative;
    }

    .topbar-right .notification-btn .badge-count {
      position: absolute;
      top: -4px;
      right: -4px;
      font-size: 9px;
      padding: 2px 6px;
      border-radius: 50%;
      background: var(--danger);
      color: white;
      min-width: 18px;
      text-align: center;
      font-weight: 600;
    }

    .topbar-right .dropdown-toggle {
      gap: 8px;
      background: transparent;
      border: none;
      padding: 4px 8px;
    }

    .topbar-right .dropdown-toggle .admin-name {
      font-size: 13px;
      font-weight: 500;
      max-width: 120px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .topbar-right .dropdown-toggle .profile-avatar-img,
    .topbar-right .dropdown-toggle .profile-avatar-placeholder {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid var(--primary-light);
      flex-shrink: 0;
    }

    .topbar-right .dropdown-toggle .profile-avatar-placeholder {
      background: var(--primary-light);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      font-weight: 600;
    }

    @media (max-width: 480px) {
      .topbar-right .dropdown-toggle .admin-name {
        display: none;
      }
    }

    /* ============================================
       LIVE INDICATOR
    ============================================ */
    .live-indicator {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 500;
      background: #d4edda;
      color: #155724;
      flex-shrink: 0;
    }
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
      50% { opacity: 0.5; transform: scale(1.2); }
      100% { opacity: 1; transform: scale(1); }
    }

    /* ============================================
       MAIN CONTENT
    ============================================ */
    .main-content {
      margin-left: var(--sidebar-w);
      margin-top: var(--header-h);
      padding: 20px;
      min-height: calc(100vh - var(--header-h));
      transition: margin-left var(--transition-speed) ease;
    }

    .sidebar.collapsed ~ .main-content {
      margin-left: var(--sidebar-collapsed-w);
    }

    /* ============================================
       CARDS
    ============================================ */
    .card {
      border: none;
      border-radius: 12px;
      box-shadow: 0 1px 4px rgba(0,0,0,.08);
      margin-bottom: 20px;
    }

    .card-header {
      background: white;
      border-bottom: 1px solid var(--border);
      border-radius: 12px 12px 0 0 !important;
      padding: 14px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      flex-wrap: wrap;
    }

    .card-header .card-title {
      font-size: 15px;
      font-weight: 600;
      margin: 0;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    /* ============================================
       STAT CARDS
    ============================================ */
    .stat-card {
      border-radius: 12px;
      padding: 20px;
      color: white;
      position: relative;
      overflow: hidden;
    }

    .stat-card::after {
      content: '';
      position: absolute;
      right: -20px; top: -20px;
      width: 100px; height: 100px;
      border-radius: 50%;
      background: rgba(255,255,255,.1);
    }

    .stat-card .stat-icon {
      width: 46px; height: 46px;
      background: rgba(255,255,255,.2);
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      margin-bottom: 12px;
    }

    .stat-card .stat-value {
      font-size: 22px;
      font-weight: 700;
      line-height: 1.2;
    }

    .stat-card .stat-label {
      font-size: 13px;
      opacity: .85;
      margin-top: 2px;
    }

    .bg-primary-grad { background: linear-gradient(135deg, #1B4F72, #2E86C1); }
    .bg-success-grad { background: linear-gradient(135deg, #117A65, #1E8449); }
    .bg-warning-grad { background: linear-gradient(135deg, #B7770D, #F39C12); }
    .bg-danger-grad  { background: linear-gradient(135deg, #922B21, #C0392B); }
    .bg-purple-grad  { background: linear-gradient(135deg, #6C3483, #9B59B6); }
    .bg-teal-grad    { background: linear-gradient(135deg, #0E6655, #17A589); }
    .bg-info-grad    { background: linear-gradient(135deg, #0E4B6E, #2E86C1); }
    .bg-secondary-grad { background: linear-gradient(135deg, #5D6D7E, #95A5A6); }

    /* ============================================
       TABLES
    ============================================ */
    .table-responsive { border-radius: 0 0 12px 12px; }
    .table { margin-bottom: 0; font-size: 14px; }
    .table th {
      background: #F8FAFC;
      font-weight: 600;
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: .5px;
      color: var(--text-muted);
      border-bottom: 2px solid var(--border);
      white-space: nowrap;
      padding: 10px 12px;
    }
    .table td { padding: 10px 12px; vertical-align: middle; }
    .table-hover tbody tr:hover { background: #F8FAFC; }

    /* ============================================
       BADGES
    ============================================ */
    .badge { font-size: 11px; padding: 4px 8px; border-radius: 6px; font-weight: 500; }
    .status-active    { background: #D5F5E3; color: #1E8449; }
    .status-inactive  { background: #F2F3F4; color: #5D6D7E; }
    .status-paid      { background: #D5F5E3; color: #1E8449; }
    .status-unpaid    { background: #FDEDEC; color: #C0392B; }
    .status-partial   { background: #FEF9E7; color: #B7770D; }
    .status-overdue   { background: #FDEDEC; color: #C0392B; }
    .status-pending   { background: #EBF5FB; color: #2471A3; }
    .status-approved  { background: #D5F5E3; color: #1E8449; }
    .status-rejected  { background: #FDEDEC; color: #C0392B; }

    /* ============================================
       FORMS
    ============================================ */
    .form-label { font-weight: 500; font-size: 13px; margin-bottom: 5px; color: var(--text-dark); }
    .form-control, .form-select {
      border-radius: 8px;
      border-color: var(--border);
      font-size: 14px;
      font-family: 'Hind Siliguri', sans-serif;
    }
    .form-control:focus, .form-select:focus {
      border-color: var(--primary-light);
      box-shadow: 0 0 0 3px rgba(46,134,193,.15);
    }
    .input-group-text {
      border-radius: 8px 0 0 8px;
      background: #F4F6F9;
      border-color: var(--border);
      font-size: 14px;
    }

    /* ============================================
       BUTTONS
    ============================================ */
    .btn { border-radius: 8px; font-size: 14px; font-weight: 500; font-family: 'Hind Siliguri', sans-serif; }
    .btn-primary { background: var(--primary); border-color: var(--primary); }
    .btn-primary:hover { background: var(--primary-light); border-color: var(--primary-light); }
    .btn-sm { font-size: 12px; padding: 4px 10px; }

    /* ============================================
       MEMBER AVATAR
    ============================================ */
    .member-avatar {
      width: 36px; height: 36px;
      border-radius: 50%;
      background: var(--primary-light);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 13px;
      font-weight: 600;
      flex-shrink: 0;
    }

    /* ============================================
       PROFILE PHOTO IN HEADER
    ============================================ */
    .profile-avatar-img {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid var(--primary-light);
      flex-shrink: 0;
    }

    .profile-avatar-placeholder {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: var(--primary-light);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      font-weight: 600;
      flex-shrink: 0;
      border: 2px solid var(--primary-light);
    }

    /* ============================================
       OVERLAY
    ============================================ */
    .sidebar-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,.5);
      z-index: 999;
    }

    /* ============================================
       MOBILE
    ============================================ */
    @media (max-width: 768px) {
      .sidebar {
        transform: translateX(-100%);
        width: var(--sidebar-w) !important;
        overflow: hidden !important;
      }
      .sidebar.mobile-open {
        transform: translateX(0);
      }
      .sidebar.collapsed {
        width: var(--sidebar-w) !important;
      }
      .sidebar.collapsed .brand-text {
        opacity: 1;
        width: auto;
      }
      .sidebar.collapsed .nav-link .nav-text {
        opacity: 1;
        width: auto;
      }
      .sidebar.collapsed .nav-section-label {
        opacity: 1;
        height: auto;
        padding: 0 20px 4px;
      }
      .sidebar.collapsed .nav-badge {
        display: inline-block;
      }
      .sidebar.collapsed .sidebar-toggle-btn {
        display: none !important;
      }
      .sidebar-overlay.show { display: block; }
      .topbar {
        left: 0 !important;
        padding: 0 12px;
        gap: 8px;
      }
      .topbar .btn-sidebar-toggle { 
        display: flex !important; 
        padding: 6px;
        min-width: 36px;
        min-height: 36px;
        align-items: center;
        justify-content: center;
      }
      .main-content {
        margin-left: 0 !important;
        padding: 12px;
      }
      .stat-card { padding: 16px; }
      .stat-card .stat-value { font-size: 18px; }
      .table-responsive { font-size: 13px; }
      .topbar .page-title { font-size: 14px; }
      
      .topbar-right {
        gap: 6px;
      }
      
      .topbar-right .notification-btn,
      .topbar-right .dropdown-toggle {
        min-width: 32px;
        min-height: 32px;
        padding: 4px 6px;
      }
      
      .topbar-right .notification-btn .badge-count {
        font-size: 8px;
        min-width: 16px;
        padding: 1px 5px;
        top: -3px;
        right: -3px;
      }
      
      .topbar-right .dropdown-toggle .profile-avatar-img,
      .topbar-right .dropdown-toggle .profile-avatar-placeholder {
        width: 28px;
        height: 28px;
      }
      
      .live-indicator {
        padding: 2px 8px;
        font-size: 10px;
        gap: 4px;
      }
      .live-indicator .live-dot {
        width: 6px;
        height: 6px;
      }
      
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
        font-size: 13px;
      }
      
      .nav-badge.backup-dot {
        width: 10px !important;
        height: 10px !important;
        min-width: 10px !important;
      }
    }

    @media (max-width: 480px) {
      .card-header { flex-direction: column; align-items: flex-start; gap: 8px; }
      .main-content { padding: 10px; }
      .topbar { 
        padding: 0 8px; 
        gap: 4px;
      }
      .topbar .page-title { 
        font-size: 12px;
        margin-right: 4px;
      }
      .live-indicator { 
        display: none !important; 
      }
      
      .topbar-right {
        gap: 4px;
      }
      
      .topbar-right .notification-btn,
      .topbar-right .dropdown-toggle {
        min-width: 28px;
        min-height: 28px;
        padding: 2px 4px;
      }
      
      .topbar-right .notification-btn .badge-count {
        font-size: 7px;
        min-width: 14px;
        padding: 1px 4px;
        top: -2px;
        right: -2px;
      }
      
      .topbar-right .dropdown-toggle .profile-avatar-img,
      .topbar-right .dropdown-toggle .profile-avatar-placeholder {
        width: 24px;
        height: 24px;
        border-width: 1.5px;
      }
      
      .topbar .btn-sidebar-toggle {
        min-width: 28px;
        min-height: 28px;
        padding: 4px;
      }
      
      .topbar .btn-sidebar-toggle i {
        width: 16px;
        height: 16px;
      }
      
      .loader-circle {
        width: 75px;
        height: 75px;
      }
      .loader-logo,
      .loader-logo-placeholder {
        width: 45px;
        height: 45px;
        font-size: 18px;
      }
      .loader-text {
        font-size: 12px;
      }
      
      .nav-badge.backup-dot {
        width: 8px !important;
        height: 8px !important;
        min-width: 8px !important;
      }
    }
  </style>
  <?= $extraCSS ?? '' ?>
</head>
<body>

<!-- =====================================================
     LOADER SYSTEM
     ===================================================== -->
<div id="loader-wrapper">
  <div class="loader-container">
    <div class="loader-circle">
      <svg viewBox="0 0 120 120">
        <circle class="bg-circle" cx="60" cy="60" r="54"/>
        <circle class="progress-circle" cx="60" cy="60" r="54"/>
      </svg>
      
      <?php if (!empty($full_logo_url)): ?>
        <img src="<?= htmlspecialchars($full_logo_url) ?>" alt="Logo" class="loader-logo">
      <?php else: ?>
        <div class="loader-logo-placeholder">স</div>
      <?php endif; ?>
    </div>
    
    <div class="loader-text">
      <?= clean($somity_name) ?>
      <span class="loader-dots">
        <span></span>
        <span></span>
        <span></span>
      </span>
    </div>
  </div>
</div>

<!-- Sidebar Overlay (Mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ============================================
     SIDEBAR - CIRCLE LOGO + ARROW OUTSIDE ON COLLAPSE
     ============================================ -->
<nav class="sidebar <?= $sidebarCollapsed ? 'collapsed' : '' ?>" id="sidebar">
  <!-- Brand - CLICKABLE LINK TO DASHBOARD -->
  <a href="/admin/index.php" class="sidebar-brand" title="ড্যাশবোর্ডে যান">
    <div class="brand-icon <?= !empty($full_logo_url) ? 'has-logo' : '' ?>">
      <?php if (!empty($full_logo_url)): ?>
        <img src="<?= htmlspecialchars($full_logo_url) ?>" alt="Logo">
      <?php else: ?>
        স
      <?php endif; ?>
    </div>
    
    <div class="brand-text">
      <span class="brand-name"><?= clean($somity_name) ?></span>
      <span class="brand-sub">ম্যানেজমেন্ট সিস্টেম</span>
    </div>
    
    <button class="sidebar-toggle-btn" id="sidebarCollapseToggle" title="Toggle Sidebar" onclick="event.preventDefault(); event.stopPropagation();">
      <i data-feather="chevrons-left" class="toggle-icon"></i>
    </button>
  </a>

  <!-- Menu - SCROLLABLE -->
  <div class="sidebar-menu">
    <?php
    function isActive($page, $params = []) {
        global $currentPage, $queryParams;
        
        if ($currentPage !== $page) {
            return false;
        }
        
        foreach ($params as $key => $value) {
            if (!isset($queryParams[$key]) || $queryParams[$key] != $value) {
                return false;
            }
        }
        
        return true;
    }
    ?>

    <!-- SECTION 1: ড্যাশবোর্ড -->
    <div class="nav-section">
      <div class="nav-section-label">ড্যাশবোর্ড</div>
      <a href="/admin/index.php" class="nav-link <?= isActive('index.php') ? 'active' : '' ?>" data-tooltip="ড্যাশবোর্ড">
        <i data-feather="home" class="nav-icon"></i>
        <span class="nav-text">ড্যাশবোর্ড</span>
      </a>
     <a href="/admin/total.php" class="nav-link <?= isActive('total.php') ? 'active' : '' ?>" data-tooltip="মোট আর্থিক হিসাব">
    <i data-feather="activity" class="nav-icon"></i>
    <span class="nav-text">মোট আর্থিক হিসাব</span>
     </a> 
    </div>

    <!-- SECTION 2: সদস্য ব্যবস্থাপনা -->
    <div class="nav-section">
      <div class="nav-section-label">সদস্য ব্যবস্থাপনা</div>
      <a href="/admin/member_add.php" class="nav-link <?= isActive('member_add.php') ? 'active' : '' ?>" data-tooltip="নতুন সদস্য">
        <i data-feather="user-plus" class="nav-icon"></i>
        <span class="nav-text">নতুন সদস্য</span>
      </a>
      <a href="/admin/members.php" class="nav-link <?= isActive('members.php') ? 'active' : '' ?>" data-tooltip="সদস্য তালিকা">
        <i data-feather="users" class="nav-icon"></i>
        <span class="nav-text">সদস্য তালিকা</span>
      </a>
      <a href="/admin/member_search.php" class="nav-link <?= isActive('member_search.php') ? 'active' : '' ?>" data-tooltip="সদস্য খুঁজুন">
        <i data-feather="search" class="nav-icon"></i>
        <span class="nav-text">সদস্য খুঁজুন</span>
      </a>   
    </div>

    <!-- SECTION 3: আর্থিক -->
    <div class="nav-section">
      <div class="nav-section-label">আর্থিক</div>
      <a href="/admin/deposits.php" class="nav-link <?= isActive('deposits.php') ? 'active' : '' ?>" data-tooltip="সঞ্চয় জমা">
        <i data-feather="credit-card" class="nav-icon"></i>
        <span class="nav-text">সঞ্চয় জমা</span>
      </a>
      <a href="/admin/deposit_list.php" class="nav-link <?= isActive('deposit_list.php') ? 'active' : '' ?>" data-tooltip="সঞ্চয় জমার তালিকা">
        <i data-feather="list" class="nav-icon"></i>
        <span class="nav-text">সঞ্চয় জমার তালিকা</span>
      </a>
      <a href="/admin/deposit_members.php" class="nav-link <?= isActive('deposit_members.php') ? 'active' : '' ?>" data-tooltip="সঞ্চয় সদস্য তালিকা">
        <i data-feather="users" class="nav-icon"></i>
        <span class="nav-text">সঞ্চয় সদস্য তালিকা</span>
      </a>
      <a href="/admin/withdrawals.php" class="nav-link <?= isActive('withdrawals.php') ? 'active' : '' ?>" data-tooltip="উত্তোলন">
        <i data-feather="arrow-up-circle" class="nav-icon"></i>
        <span class="nav-text">উত্তোলন</span>
      </a>  
      <a href="/admin/monthly_dues.php" class="nav-link <?= isActive('monthly_dues.php') ? 'active' : '' ?>" data-tooltip="মাসিক সঞ্চয় তালিকা">
        <i data-feather="calendar" class="nav-icon"></i>
        <span class="nav-text">মাসিক সঞ্চয় তালিকা</span>
        <?php 
        $pendingDues = 0;
        try {
          $pendingDues = queryCount('monthly_dues', "status='unpaid'");
        } catch (Exception $e) { /* ignore */ }
        ?>
        <?php if ($pendingDues > 0): ?><span class="nav-badge"><?= $pendingDues ?></span><?php endif; ?>
      </a>
      <a href="/admin/member_due_report.php" class="nav-link <?= isActive('member_due_report.php') ? 'active' : '' ?>" data-tooltip="সদস্যের লেনদেনের তালিকা">
        <i data-feather="list" class="nav-icon"></i>
        <span class="nav-text">সদস্যের লেনদেনের তালিকা</span>
      </a>
      <a href="/admin/fines.php" class="nav-link <?= isActive('fines.php') ? 'active' : '' ?>" data-tooltip="জরিমানা">
        <i data-feather="alert-circle" class="nav-icon"></i>
        <span class="nav-text">জরিমানা</span>
      </a>
      <a href="/admin/expenses.php" class="nav-link <?= isActive('expenses.php') ? 'active' : '' ?>" data-tooltip="খরচ">
        <i data-feather="shopping-bag" class="nav-icon"></i>
        <span class="nav-text">খরচ</span>
      </a>
    </div>

    <!-- SECTION 4: ঋণ -->
    <div class="nav-section">
      <div class="nav-section-label">ঋণ</div>
      <a href="/admin/loans.php?action=add" class="nav-link <?= isActive('loans.php', ['action' => 'add']) ? 'active' : '' ?>" data-tooltip="নতুন ঋণ">
        <i data-feather="plus-circle" class="nav-icon"></i>
        <span class="nav-text">নতুন ঋণ</span>
      </a>
      <a href="/admin/loan_list.php" class="nav-link <?= isActive('loan_list.php') ? 'active' : '' ?>" data-tooltip="ঋণ তালিকা">
        <i data-feather="briefcase" class="nav-icon"></i>
        <span class="nav-text">ঋণ তালিকা</span>
      </a>
      <a href="/admin/loan_payments.php" class="nav-link <?= isActive('loan_payments.php') ? 'active' : '' ?>" data-tooltip="কিস্তি গ্রহণ">
        <i data-feather="dollar-sign" class="nav-icon"></i>
        <span class="nav-text">কিস্তি গ্রহণ</span>
      </a>
    </div>

    <!-- SECTION 5: বিনিয়োগ ও লাভ -->
    <div class="nav-section">
      <div class="nav-section-label">বিনিয়োগ ও লাভ</div>
      <a href="/admin/investments.php" class="nav-link <?= isActive('investments.php') ? 'active' : '' ?>" data-tooltip="বিনিয়োগ">
        <i data-feather="trending-up" class="nav-icon"></i>
        <span class="nav-text">বিনিয়োগ</span>
      </a>
      <a href="/admin/profit.php" class="nav-link <?= isActive('profit.php') ? 'active' : '' ?>" data-tooltip="লাভ-ক্ষতি">
        <i data-feather="pie-chart" class="nav-icon"></i>
        <span class="nav-text">লাভ-ক্ষতি</span>
      </a>
      <a href="/admin/profit_distribution.php" class="nav-link <?= isActive('profit_distribution.php') ? 'active' : '' ?>" data-tooltip="লাভ বণ্টন">
        <i data-feather="share-2" class="nav-icon"></i>
        <span class="nav-text">লাভ বণ্টন</span>
      </a>
    </div>

    <!-- SECTION 6: রিপোর্ট -->
    <div class="nav-section">
      <div class="nav-section-label">রিপোর্ট</div>
      <a href="/admin/reports.php" class="nav-link <?= isActive('reports.php') ? 'active' : '' ?>" data-tooltip="রিপোর্ট">
        <i data-feather="file-text" class="nav-icon"></i>
        <span class="nav-text">রিপোর্ট</span>
      </a> 
      <a href="/admin/full_reports.php" class="nav-link <?= isActive('full_reports.php') ? 'active' : '' ?>" data-tooltip="সম্পূর্ণ পরিসংখ্যান">
        <i data-feather="bar-chart-2" class="nav-icon"></i>
        <span class="nav-text">সম্পূর্ণ পরিসংখ্যান</span>
      </a>           
      <a href="/admin/notifications.php" class="nav-link <?= isActive('notifications.php') ? 'active' : '' ?>" data-tooltip="নোটিফিকেশন">
        <i data-feather="bell" class="nav-icon"></i>
        <span class="nav-text">নোটিফিকেশন</span>
        <?php 
        $notifCount = 0;
        try {
          $notifCount = getUnreadCount('admin');
        } catch (Exception $e) { /* ignore */ }
        ?>
        <?php if ($notifCount > 0): ?><span class="nav-badge"><?= $notifCount ?></span><?php endif; ?>
      </a>
    </div>

    <!-- ============================================
         SECTION 7: ব্যাকআপ - IPHONE FIXED (No JavaScript)
         ============================================ -->
    <div class="nav-section">
      <div class="nav-section-label">ব্যাকআপ</div>
      <a href="/admin/backup.php" 
         class="nav-link <?= isActive('backup.php') ? 'active' : '' ?>" 
         data-tooltip="ডেটাবেস ব্যাকআপ">
        <i data-feather="database" class="nav-icon"></i>
        <span class="nav-text">ব্যাকআপ</span>
        <?php if ($showBackupHighlight): ?>
        <span class="nav-badge backup-dot" id="backupDot"></span>
        <?php endif; ?>
      </a>
    </div>

    <!-- SECTION 8: সেটিং -->
    <div class="nav-section">
      <div class="nav-section-label">সেটিং</div>
      <a href="/admin/settings.php" class="nav-link <?= isActive('settings.php') ? 'active' : '' ?>" data-tooltip="সেটিং">
        <i data-feather="settings" class="nav-icon"></i>
        <span class="nav-text">সেটিং</span>
      </a>
      <a href="/admin/profile.php" class="nav-link <?= isActive('profile.php') ? 'active' : '' ?>" data-tooltip="প্রোফাইল">
        <i data-feather="user" class="nav-icon"></i>
        <span class="nav-text">প্রোফাইল</span>
      </a>
      <a href="/admin/users.php" class="nav-link <?= isActive('users.php') ? 'active' : '' ?>" data-tooltip="স্টাফ ব্যবস্থাপনা">
        <i data-feather="shield" class="nav-icon"></i>
        <span class="nav-text">স্টাফ ব্যবস্থাপনা</span>
      </a>
      <a href="/admin/logout.php" class="nav-link logout-link" data-tooltip="লগআউট">
        <i data-feather="log-out" class="nav-icon"></i>
        <span class="nav-text">লগআউট</span>
      </a>
    </div>
  </div><!-- /.sidebar-menu -->
</nav>

<!-- TOPBAR - FIXED -->
<header class="topbar">
  <button class="btn-sidebar-toggle" id="sidebarToggle">
    <i data-feather="menu"></i>
  </button>
  <div class="page-title"><?= clean($pageTitle ?? 'ড্যাশবোর্ড') ?></div>

  <span class="live-indicator d-none d-md-inline-flex">
    <span class="live-dot"></span>
    লাইভ
  </span>

  <div class="topbar-right">
    <?php 
    $notifCount = 0;
    try {
      $notifCount = getUnreadCount('admin');
    } catch (Exception $e) { /* ignore */ }
    ?>
    <a href="/admin/notifications.php" class="notification-btn" title="নোটিফিকেশন">
      <i data-feather="bell" style="width:18px;height:18px"></i>
      <?php if ($notifCount > 0): ?>
      <span class="badge-count"><?= $notifCount ?></span>
      <?php endif; ?>
    </a>

    <div class="dropdown">
      <button class="dropdown-toggle" data-bs-toggle="dropdown" title="প্রোফাইল">
        <?php if (!empty($profilePhotoUrl)): ?>
          <img src="<?= htmlspecialchars($profilePhotoUrl) ?>" 
               alt="Profile" 
               class="profile-avatar-img"
               onerror="this.style.display='none'; this.parentElement.querySelector('.profile-avatar-placeholder').style.display='flex';">
          <span class="profile-avatar-placeholder" style="display:none;">
            <?= mb_substr($admin['name'] ?? 'A', 0, 1) ?>
          </span>
        <?php else: ?>
          <span class="profile-avatar-placeholder">
            <?= mb_substr($admin['name'] ?? 'A', 0, 1) ?>
          </span>
        <?php endif; ?>
        <span class="admin-name"><?= clean($admin['name'] ?? 'অ্যাডমিন') ?></span>
      </button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="/admin/profile.php"><i data-feather="user" style="width:14px;height:14px" class="me-2"></i>প্রোফাইল</a></li>
        <li><a class="dropdown-item" href="/admin/users.php"><i data-feather="shield" style="width:14px;height:14px" class="me-2"></i>স্টাফ ব্যবস্থাপনা</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger fw-bold" href="/admin/logout.php"><i data-feather="log-out" style="width:14px;height:14px" class="me-2"></i>লগআউট</a></li>
      </ul>
    </div>
  </div>
</header>

<!-- MAIN CONTENT START -->
<main class="main-content">
  <?= showFlash() ?>


<script>
// ============================================
// LOADER SYSTEM
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const loader = document.getElementById('loader-wrapper');
    
    setTimeout(function() {
        loader.classList.add('loader-hidden');
        setTimeout(function() {
            loader.style.display = 'none';
        }, 600);
    }, 800);
});

// ============================================
// SIDEBAR COLLAPSE SYSTEM - WITH ARROW OUTSIDE
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebarCollapseToggle');
    const mobileToggle = document.getElementById('sidebarToggle');
    const overlay = document.getElementById('sidebarOverlay');
    const body = document.body;
    
    function updateArrowIcon() {
        if (toggleBtn) {
            const icon = toggleBtn.querySelector('.toggle-icon');
            if (icon) {
                const isCollapsed = sidebar.classList.contains('collapsed');
                if (isCollapsed) {
                    icon.setAttribute('data-feather', 'chevrons-right');
                } else {
                    icon.setAttribute('data-feather', 'chevrons-left');
                }
                if (typeof feather !== 'undefined') {
                    feather.replace();
                }
            }
        }
    }
    
    const isCollapsed = document.cookie.split('; ').find(row => row.startsWith('sidebar_collapsed='));
    if (isCollapsed && isCollapsed.split('=')[1] === 'true') {
        sidebar.classList.add('collapsed');
    }
    updateArrowIcon();
    
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.toggle('collapsed');
            
            const isNowCollapsed = sidebar.classList.contains('collapsed');
            document.cookie = `sidebar_collapsed=${isNowCollapsed}; path=/; max-age=31536000`;
            
            updateArrowIcon();
        });
    }
    
    if (mobileToggle) {
        mobileToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.toggle('mobile-open');
            overlay.classList.toggle('show');
            body.classList.toggle('sidebar-open');
        });
    }
    
    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('show');
            body.classList.remove('sidebar-open');
        });
    }
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768) {
            const isSidebarOpen = sidebar.classList.contains('mobile-open');
            const isClickInsideSidebar = sidebar.contains(e.target);
            const isClickOnToggle = mobileToggle && mobileToggle.contains(e.target);
            const isClickOnOverlay = overlay && overlay.contains(e.target);
            
            if (isSidebarOpen && !isClickInsideSidebar && !isClickOnToggle && !isClickOnOverlay) {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('show');
                body.classList.remove('sidebar-open');
            }
        }
    });
    
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('show');
            body.classList.remove('sidebar-open');
        }
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('mobile-open')) {
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('show');
            body.classList.remove('sidebar-open');
        }
    });
    
    feather.replace();
});

// ============================================
// BACKUP DOT - IPHONE FIXED (No Navigation Block)
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const backupDot = document.getElementById('backupDot');
    
    if (backupDot) {
        // ডটে ক্লিক করলে (iPhone friendly)
        backupDot.addEventListener('click', function(e) {
            // Stop event from bubbling to parent link
            e.stopPropagation();
            
            // Hide the dot
            this.classList.add('hidden-dot');
            
            // Send background request to mark as visited
            try {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', window.location.href, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.send('backup_visited=1');
            } catch(err) {
                // Ignore
            }
            
            // Don't navigate - just hide dot
            return false;
        });
        
        // Parent link click - hide dot on navigation
        const parentLink = backupDot.closest('.nav-link');
        if (parentLink) {
            parentLink.addEventListener('click', function(e) {
                const dot = document.getElementById('backupDot');
                if (dot && !dot.classList.contains('hidden-dot')) {
                    dot.classList.add('hidden-dot');
                    
                    // Send background request
                    try {
                        const xhr = new XMLHttpRequest();
                        xhr.open('POST', window.location.href, true);
                        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                        xhr.send('backup_visited=1');
                    } catch(err) {
                        // Ignore
                    }
                }
                // ✅ Allow navigation - no preventDefault
                return true;
            });
        }
    }
});
</script>
</body>
</html>