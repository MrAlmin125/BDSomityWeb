<?php
// =====================================================
// member/includes/header.php - Member Header
// =====================================================

// Check if member is logged in
if (!isset($_SESSION['member_id'])) {
    header('Location: /member/login.php');
    exit;
}

// Get member data
$mId = $_SESSION['member_id'];
$conn = conn();
$member = queryOne("SELECT * FROM members WHERE id = ?", 'i', [$mId]);

if (!$member) {
    header('Location: /member/login.php');
    exit;
}

// Get somity name and logo from database
$somityName = getSetting('somity_name') ?: 'সোমিটি';
$logoUrl = getSetting('logo_url', '');

// Build full logo URL
$fullLogoUrl = '';
if (!empty($logoUrl)) {
    if (strpos($logoUrl, 'http://') === 0 || strpos($logoUrl, 'https://') === 0) {
        $fullLogoUrl = $logoUrl;
    } else {
        $logoPath = ltrim($logoUrl, '/');
        if (strpos($logoPath, 'images/') === 0 || strpos($logoPath, 'uploads/') === 0) {
            $fullLogoUrl = '/' . $logoPath;
        } else {
            $fullLogoUrl = '/images/' . $logoPath;
        }
    }
}

// Check if member has photo
$hasPhoto = !empty($member['photo']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $member['photo']);

// Get unread notifications count
$totalUnread = 0;
$unreadResult = queryOne(
    "SELECT COUNT(*) as count FROM notifications 
     WHERE (target='all' OR (target='member' AND member_id=?)) AND is_read=0",
    'i', [$mId]
);
if ($unreadResult && isset($unreadResult['count'])) {
    $totalUnread = (int)$unreadResult['count'];
}

$pageTitle = $pageTitle ?? 'ড্যাশবোর্ড';
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="theme-color" content="#117A65">
    <title><?= clean($pageTitle) ?> — <?= clean($somityName) ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6/css/all.min.css">
    
    <style>
        /* ============================================
           RESET & BASE
        ============================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Hind Siliguri', sans-serif;
            background: #F0F4F8;
            color: #1C2833;
            padding-top: 0;
            padding-bottom: 70px;
        }

        /* ============================================
           HEADER - FULL WIDTH
        ============================================ */
        .member-header {
            background: linear-gradient(135deg, #117A65, #0E6655);
            padding: 10px 20px;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 12px rgba(0,0,0,0.15);
            width: 100%;
        }

        .member-header .header-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            max-width: 100%; /* Full width */
            margin: 0 auto;
            padding: 0 10px;
        }

        /* Left: Logo + Name */
        .member-header .header-left {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: white;
        }

        .member-header .header-left:hover {
            text-decoration: none;
            color: white;
        }

        .member-header .header-logo {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            overflow: hidden;
            border: 2px solid rgba(255,255,255,0.3);
            flex-shrink: 0;
            background: rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .member-header .header-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .member-header .header-logo .no-logo {
            font-size: 18px;
            font-weight: 700;
            color: white;
        }

        .member-header .header-name {
            font-size: 16px;
            font-weight: 600;
            color: white;
            line-height: 1.2;
        }

        /* Right: Notification, Profile, Logout */
        .member-header .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .member-header .header-right a {
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
            display: flex;
            align-items: center;
            padding: 4px;
            border-radius: 8px;
        }

        .member-header .header-right a:hover {
            color: white;
            background: rgba(255,255,255,0.15);
            transform: scale(1.05);
        }

        .member-header .header-right a svg {
            width: 22px;
            height: 22px;
        }

        /* Notification Badge */
        .member-header .notif-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: #E74C3C;
            color: white;
            border-radius: 50%;
            padding: 1px 6px;
            font-size: 9px;
            font-weight: 700;
            min-width: 18px;
            text-align: center;
            line-height: 16px;
            box-shadow: 0 2px 6px rgba(231, 76, 60, 0.4);
        }

        /* Profile Avatar in Header */
        .member-header .profile-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            overflow: hidden;
            border: 2px solid rgba(255,255,255,0.3);
            flex-shrink: 0;
            background: rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .member-header .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .member-header .profile-avatar .avatar-text {
            font-size: 13px;
            font-weight: 700;
            color: white;
        }

        /* ============================================
           MAIN CONTENT - CENTERED WITH MAX-WIDTH
        ============================================ */
        .main-content {
            max-width: 700px;
            margin: 0 auto;
            padding: 14px;
        }

        /* ============================================
           BOTTOM NAVIGATION
        ============================================ */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-top: 1px solid #e5e7eb;
            display: flex;
            z-index: 999;
            padding: 6px 0 env(safe-area-inset-bottom, 6px);
            box-shadow: 0 -2px 12px rgba(0,0,0,0.06);
        }

        .bottom-nav a {
            flex: 1;
            padding: 4px 0;
            text-align: center;
            color: #9ca3af;
            text-decoration: none;
            font-size: 9px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1px;
            transition: all 0.3s ease;
            border-radius: 8px;
            margin: 0 2px;
            position: relative;
        }

        .bottom-nav a svg {
            width: 20px;
            height: 20px;
            transition: all 0.3s ease;
        }

        .bottom-nav a span {
            font-size: 9px;
            font-weight: 500;
            letter-spacing: 0.3px;
        }

        .bottom-nav a.active {
            color: #117A65;
        }

        .bottom-nav a.active svg {
            stroke-width: 2.5;
            transform: translateY(-2px);
        }

        .bottom-nav a:hover:not(.active) {
            color: #6b7280;
        }

        .bottom-nav .nav-badge {
            position: absolute;
            top: -2px;
            right: 50%;
            transform: translateX(14px);
            background: #EF4444;
            color: white;
            border-radius: 50%;
            padding: 1px 5px;
            font-size: 8px;
            font-weight: 700;
            min-width: 16px;
            text-align: center;
            line-height: 14px;
            box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3);
        }

        /* ============================================
           RESPONSIVE
        ============================================ */
        @media (max-width: 576px) {
            .member-header {
                padding: 8px 14px;
            }
            .member-header .header-name {
                font-size: 14px;
            }
            .member-header .header-logo {
                width: 32px;
                height: 32px;
            }
            .member-header .header-right {
                gap: 10px;
            }
            .member-header .header-right a svg {
                width: 18px;
                height: 18px;
            }
            .member-header .profile-avatar {
                width: 28px;
                height: 28px;
            }
            .member-header .profile-avatar .avatar-text {
                font-size: 11px;
            }
            .bottom-nav a svg {
                width: 18px;
                height: 18px;
            }
            .bottom-nav a span {
                font-size: 8px;
            }
            .main-content {
                padding: 10px;
            }
        }

        @media (max-width: 400px) {
            .member-header .header-name {
                font-size: 12px;
            }
            .member-header .header-logo {
                width: 28px;
                height: 28px;
            }
            .member-header .header-right {
                gap: 6px;
            }
            .member-header .header-right a svg {
                width: 16px;
                height: 16px;
            }
            .member-header .profile-avatar {
                width: 24px;
                height: 24px;
            }
            .member-header .profile-avatar .avatar-text {
                font-size: 10px;
            }
        }

        /* ============================================
           PC - HEADER FULL WIDTH, CONTENT CENTERED
        ============================================ */
        @media (min-width: 768px) {
            .member-header .header-container {
                max-width: 1200px; /* Wider for PC */
                padding: 0 20px;
            }
            
            .member-header .header-name {
                font-size: 18px;
            }
            
            .member-header .header-logo {
                width: 42px;
                height: 42px;
            }
            
            .member-header .header-right a svg {
                width: 24px;
                height: 24px;
            }
            
            .member-header .profile-avatar {
                width: 36px;
                height: 36px;
            }
        }

        @media (min-width: 1200px) {
            .member-header .header-container {
                max-width: 1400px; /* Even wider for large screens */
            }
        }
    </style>
    <?= $extraCSS ?? '' ?>
</head>
<body>

<!-- ============================================
     HEADER - FULL WIDTH
     ============================================ -->
<header class="member-header">
    <div class="header-container">
        <!-- Left: Logo + Name -->
        <a href="/member/index.php" class="header-left">
            <div class="header-logo">
                <?php if (!empty($fullLogoUrl)): ?>
                    <img src="<?= htmlspecialchars($fullLogoUrl) ?>" alt="Logo">
                <?php else: ?>
                    <span class="no-logo"><?= mb_substr($somityName, 0, 1) ?></span>
                <?php endif; ?>
            </div>
            <span class="header-name"><?= clean($somityName) ?></span>
        </a>

        <!-- Right: Notification, Profile, Logout -->
        <div class="header-right">
            <!-- Notification -->
            <a href="/member/notifications.php" title="নোটিফিকেশন">
                <i data-feather="bell"></i>
                <?php if ($totalUnread > 0): ?>
                <span class="notif-badge"><?= $totalUnread > 99 ? '99+' : $totalUnread ?></span>
                <?php endif; ?>
            </a>

            <!-- Profile -->
            <a href="/member/profile.php?id=<?= urlencode($member['member_id']) ?>" title="প্রোফাইল">
                <div class="profile-avatar">
                    <?php if ($hasPhoto): ?>
                        <img src="<?= $member['photo'] ?>" alt="Profile">
                    <?php else: ?>
                        <span class="avatar-text"><?= mb_substr($member['name'] ?? 'M', 0, 1) ?></span>
                    <?php endif; ?>
                </div>
            </a>

            <!-- Logout -->
            <a href="/member/logout.php" title="লগআউট">
                <i data-feather="log-out"></i>
            </a>
        </div>
    </div>
</header>

<!-- ============================================
     MAIN CONTENT START - CENTERED WITH MAX-WIDTH
     ============================================ -->
<main class="main-content">