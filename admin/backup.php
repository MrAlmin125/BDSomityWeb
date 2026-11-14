<?php
// =====================================================
// admin/backup.php - ডেটাবেস ব্যাকআপ
// =====================================================

// সেশন কনফিগার
session_name('somity_session');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// অ্যাডমিন চেক
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_id'] < 1) {
    $_SESSION['error'] = 'অনুগ্রহ করে লগইন করুন।';
    header('Location: /admin/login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'ডেটাবেস ব্যাকআপ';
$admin = getCurrentAdmin();

if (!$admin) {
    $admin = [
        'id' => $_SESSION['admin_id'] ?? 0,
        'name' => $_SESSION['admin_name'] ?? 'অ্যাডমিন',
        'email' => $_SESSION['admin_email'] ?? 'admin@mail.com',
        'role' => $_SESSION['admin_role'] ?? 'admin'
    ];
}

$somity_name = getSetting('somity_name', 'সোমিটি');

// ডাটাবেস স্ট্যাটাস
$totalTables = 0;
$totalRows = 0;
$tables = getAllTables();
$totalTables = count($tables);

foreach ($tables as $table) {
    $result = conn()->query("SELECT COUNT(*) as count FROM `$table`");
    if ($result) {
        $row = $result->fetch_assoc();
        $totalRows += $row['count'] ?? 0;
    }
}

$lastBackupTime = getLastBackupTime();

// ট্র্যাকিং স্ট্যাটাস
$todayCount = getTodayBackupCount();
$totalLogs = getBackupLogCount();
$weeklyCount = getWeeklyBackupCount();
$monthlyCount = getMonthlyBackupCount();
$lastBackup = getLastBackupDetails();

$flash = getFlash();

// হেডার ইনক্লুড
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-2 px-sm-3 px-md-4">

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-3 mb-md-4 flex-wrap gap-2">
    <h4 class="mb-0" style="font-size:16px;font-weight:700;">
        <i data-feather="database" style="width:20px;height:20px"></i> 
        ডেটাবেস ব্যাকআপ
        <small class="text-muted d-block d-sm-inline" style="font-size:13px;font-weight:400;">আপনার ডেটা সুরক্ষিত রাখুন</small>
    </h4>
    <div class="d-flex align-items-center gap-1 gap-sm-2">
        <span class="badge bg-primary" style="font-size:11px;padding:5px 12px;"><?= date('d M Y') ?></span>
        <a href="/admin/index.php" class="btn btn-outline-secondary btn-sm" style="font-size:12px;padding:5px 12px;">
            <i data-feather="arrow-left" style="width:14px;height:14px"></i> 
            <span class="d-none d-sm-inline">ড্যাশবোর্ড</span>
        </a>
    </div>
</div>

<!-- Flash Message -->
<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show py-2 px-3 mb-3" role="alert" style="font-size:13px;">
    <i data-feather="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-circle' ?>" style="width:16px;height:16px"></i>
    <?= $flash['message'] ?>
    <button type="button" class="btn-close p-2" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- ============================================
     STATS CARDS - 4 কলাম (PC) / 2 কলাম (মোবাইল)
     ============================================ -->
<div class="row g-2 g-sm-3 g-md-4 mb-3 mb-md-4">
    <div class="col-6 col-md-3">
        <div class="stat-card bg-primary-grad">
            <div class="stat-icon"><i data-feather="layers"></i></div>
            <div class="stat-value"><?= $totalTables ?></div>
            <div class="stat-label">📊 মোট টেবিল</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card bg-success-grad">
            <div class="stat-icon"><i data-feather="hash"></i></div>
            <div class="stat-value"><?= number_format($totalRows) ?></div>
            <div class="stat-label">📝 মোট রেকর্ড</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card bg-warning-grad">
            <div class="stat-icon"><i data-feather="clock"></i></div>
            <div class="stat-value" style="font-size:18px;"><?= $lastBackupTime ?></div>
            <div class="stat-label">⏰ শেষ ব্যাকআপ</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card bg-teal-grad">
            <div class="stat-icon"><i data-feather="server"></i></div>
            <div class="stat-value" style="font-size:16px;"><?= DB_NAME ?></div>
            <div class="stat-label">🗄️ ডেটাবেস</div>
        </div>
    </div>
</div>

<!-- ============================================
     TRACKING STATS - 4 কলাম (PC) / 2 কলাম (মোবাইল)
     ============================================ -->
<div class="row g-2 g-sm-3 g-md-4 mb-3 mb-md-4">
    <div class="col-6 col-md-3">
        <div class="stat-card stat-track" style="background: linear-gradient(135deg, #EBF5FB, #D6EAF8);">
            <div class="stat-icon"><i data-feather="clock" style="color:#2E86C1;"></i></div>
            <div class="stat-value" style="color:#2E86C1;"><?= $todayCount ?></div>
            <div class="stat-label">📥 আজকের ডাউনলোড</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card stat-track" style="background: linear-gradient(135deg, #EAFAF1, #D5F5E3);">
            <div class="stat-icon"><i data-feather="check-circle" style="color:#27AE60;"></i></div>
            <div class="stat-value" style="color:#27AE60;"><?= $weeklyCount ?></div>
            <div class="stat-label">📊 এই সপ্তাহে</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card stat-track" style="background: linear-gradient(135deg, #FEF9E7, #FDEBD0);">
            <div class="stat-icon"><i data-feather="calendar" style="color:#F39C12;"></i></div>
            <div class="stat-value" style="color:#F39C12;"><?= $monthlyCount ?></div>
            <div class="stat-label">📅 এই মাসে</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card stat-track" style="background: linear-gradient(135deg, #F4ECF7, #EBDEF0);">
            <div class="stat-icon"><i data-feather="user" style="color:#8E44AD;"></i></div>
            <div class="stat-value" style="font-size:16px;color:#8E44AD;">
                <?= $lastBackup ? htmlspecialchars($lastBackup['admin_name'] ?? 'N/A') : '-' ?>
            </div>
            <div class="stat-label">👤 শেষ ডাউনলোড</div>
        </div>
    </div>
</div>

<!-- ============================================
     QUICK ACTIONS
     ============================================ -->
<div class="row g-2 mb-3 mb-md-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i data-feather="zap" style="width:16px;height:16px"></i> ব্যাকআপ অ্যাকশন</span>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">
                    <button onclick="downloadBackup()" class="btn btn-primary" id="downloadBtn">
                        <i data-feather="download" style="width:16px;height:16px"></i> ডাউনলোড
                    </button>
                    <button onclick="emailBackup()" class="btn btn-success" id="emailBtn">
                        <i data-feather="mail" style="width:16px;height:16px"></i> ইমেইল
                    </button>
                    <a href="/admin/index.php" class="btn btn-outline-secondary">
                        <i data-feather="arrow-left" style="width:16px;height:16px"></i> ড্যাশবোর্ড
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
     MAIN 2-COLUMN LAYOUT
     ============================================ -->
<div class="row g-2 g-sm-3 g-md-4">

    <!-- LEFT COLUMN -->
    <div class="col-12 col-lg-6">

        <!-- ==========================================
             ডাউনলোড কার্ড - অ্যানিমেটেড বাটন
             ========================================== -->
        <div class="card card-download">
            <div class="card-header card-header-blue">
                <span class="card-title">
                    <i data-feather="download" style="color:#2E86C1;"></i> ডাউনলোড ব্যাকআপ
                </span>
            </div>
            <div class="card-body text-center">
                <div class="card-icon">📦</div>
                <p class="text-muted">ZIP ফাইল ডাউনলোড করে সংরক্ষণ করুন</p>
                
                <!-- 🔥 অ্যানিমেটেড ডাউনলোড বাটন -->
                <button onclick="downloadBackup()" class="btn-download-animated" id="downloadBtn2">
                    <span class="btn-download-content">
                        <i data-feather="download" class="download-icon"></i>
                        <span class="btn-text">ডাউনলোড</span>
                    </span>
                    <span class="btn-download-pulse"></span>
                </button>
                
                <div class="mt-2 text-muted small">📄 .zip ফাইল</div>
            </div>
        </div>

        <!-- টেবিল লিস্ট -->
        <div class="card mt-2 mt-sm-3">
            <div class="card-header">
                <span class="card-title"><i data-feather="grid"></i> টেবিল লিস্ট</span>
                <span class="badge bg-secondary"><?= $totalTables ?></span>
            </div>
            <div class="table-wrap">
                <table class="table table-hover table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>টেবিল</th>
                            <th>রেকর্ড</th>
                            <th>স্ট্যাটাস</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $counter = 1;
                        foreach ($tables as $table): 
                            $result = conn()->query("SELECT COUNT(*) as count FROM `$table`");
                            $rowCount = $result ? ($result->fetch_assoc()['count'] ?? 0) : 0;
                        ?>
                        <tr>
                            <td><?= $counter++ ?></td>
                            <td><code><?= $table ?></code></td>
                            <td><?= number_format($rowCount) ?></td>
                            <td><span class="badge bg-success">✅</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                <span><i data-feather="info"></i> <?= $totalTables ?>টি টেবিল</span>
                <span><i data-feather="database"></i> <?= number_format($totalRows) ?>টি রেকর্ড</span>
            </div>
        </div>

    </div>

    <!-- RIGHT COLUMN -->
    <div class="col-12 col-lg-6">

        <!-- ইমেইল কার্ড -->
        <div class="card card-email">
            <div class="card-header card-header-green">
                <span class="card-title">
                    <i data-feather="mail" style="color:#27AE60;"></i> ইমেইলে পাঠান
                </span>
            </div>
            <div class="card-body text-center">
                <div class="card-icon">📧</div>
                <p class="text-muted">ইমেইলে পাঠান: <strong><?= BACKUP_EMAIL ?></strong></p>
                <button onclick="emailBackup()" class="btn btn-success btn-lg w-100 w-sm-auto" id="emailBtn2">
                    <i data-feather="mail"></i> ইমেইল
                </button>
                <div class="mt-2 text-muted small">⏱ ১-২ মিনিট সময়</div>
            </div>
        </div>

        <!-- ==========================================
             তথ্য + নিরাপত্তা - ২ কলাম (মোবাইল)
             ========================================== -->
        <div class="row g-2 g-sm-3 mt-2 mt-sm-3">

            <!-- গুরুত্বপূর্ণ তথ্য -->
            <div class="col-6 col-lg-12">
                <div class="card card-info">
                    <div class="card-header card-header-yellow">
                        <span class="card-title">
                            <i data-feather="info" style="color:#F39C12;"></i> গুরুত্বপূর্ণ তথ্য
                        </span>
                    </div>
                    <div class="card-body">
                        <ul class="info-list">
                            <li><i data-feather="check-circle" style="color:#27AE60;"></i> <strong>সাপ্তাহিক</strong> ব্যাকআপ নিন</li>
                            <li><i data-feather="package" style="color:#2E86C1;"></i> ফাইল <strong>ZIP</strong> আকারে</li>
                            <li><i data-feather="lock" style="color:#E74C3C;"></i> কম্পিউটারে সংরক্ষণ করুন</li>
                            <li><i data-feather="alert-triangle" style="color:#E67E22;"></i> <strong>সমস্ত ডেটা</strong> থাকে</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- নিরাপত্তা টিপস -->
            <div class="col-6 col-lg-12">
                <div class="card card-security">
                    <div class="card-header card-header-purple">
                        <span class="card-title">
                            <i data-feather="shield" style="color:#8E44AD;"></i> নিরাপত্তা টিপস
                        </span>
                    </div>
                    <div class="card-body">
                        <ul class="info-list">
                            <li><i data-feather="shield" style="color:#8E44AD;"></i> পাবলিক ডিরেক্টরিতে রাখবেন না</li>
                            <li><i data-feather="clock" style="color:#2E86C1;"></i> <strong>CRON</strong> Job সেটআপ</li>
                            <li><i data-feather="cloud" style="color:#F39C12;"></i> ক্লাউডে ব্যাকআপ কপি</li>
                            <li><i data-feather="user-check" style="color:#27AE60;"></i> শুধুমাত্র অ্যাডমিন অ্যাক্সেস</li>
                        </ul>
                    </div>
                </div>
            </div>

        </div><!-- /তথ্য+নিরাপত্তা সারি -->

    </div><!-- /RIGHT COLUMN -->

</div><!-- /MAIN ROW -->

<!-- ============================================
     BACKUP LOG TABLE
     ============================================ -->
<div class="row mt-2 mt-sm-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i data-feather="list"></i> ব্যাকআপ লগ</span>
                <span class="badge bg-secondary"><?= $totalLogs ?></span>
            </div>
            <div class="table-wrap">
                <table class="table table-hover table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>অ্যাডমিন</th>
                            <th>অ্যাকশন</th>
                            <th>ফাইল</th>
                            <th>আকার</th>
                            <th>সময়</th>
                            <th>স্ট্যাটাস</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $logs = getBackupLogs(50);
                        if (empty($logs)): 
                        ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-3">
                                <i data-feather="inbox" style="width:28px;height:28px;display:block;margin:0 auto 8px;"></i>
                                কোনো ব্যাকআপ লগ নেই
                            </td>
                        </tr>
                        <?php else: 
                            $counter = 1;
                            foreach ($logs as $log): 
                        ?>
                        <tr>
                            <td><?= $counter++ ?></td>
                            <td><span class="fw-semibold"><?= htmlspecialchars($log['admin_name'] ?? 'N/A') ?></span></td>
                            <td>
                                <?php 
                                $actionLabels = [
                                    'download' => '<span class="badge bg-primary">📥</span>',
                                    'email' => '<span class="badge bg-success">📧</span>',
                                    'auto' => '<span class="badge bg-warning text-dark">🤖</span>'
                                ];
                                echo $actionLabels[$log['action']] ?? '<span class="badge bg-secondary">' . $log['action'] . '</span>';
                                ?>
                            </td>
                            <td>
                                <?php if ($log['filename']): ?>
                                <code><?= htmlspecialchars($log['filename']) ?></code>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $log['file_size'] ?? '-' ?></td>
                            <td>
                                <span title="<?= $log['created_at'] ?>">
                                    <?= timeAgo($log['created_at']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($log['status'] === 'success'): ?>
                                <span class="badge bg-success">✅</span>
                                <?php elseif ($log['status'] === 'failed'): ?>
                                <span class="badge bg-danger">❌</span>
                                <?php else: ?>
                                <span class="badge bg-secondary"><?= $log['status'] ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                <span><i data-feather="list"></i> মোট <?= $totalLogs ?>টি</span>
                <span><i data-feather="clock"></i> সর্বশেষ <?= $lastBackup ? timeAgo($lastBackup['created_at']) : 'N/A' ?></span>
            </div>
        </div>
    </div>
</div>

</div><!-- /container-fluid -->

<!-- ==============================================
     CSS - অ্যানিমেটেড ডাউনলোড বাটন সহ
     ============================================== -->
<style>
/* ============================================
   BASE
   ============================================ */
* { box-sizing: border-box; }
body { background: #f0f2f5; }

/* ============================================
   STAT CARDS
   ============================================ */
.stat-card {
    border-radius: 12px;
    padding: 16px 18px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    transition: all 0.3s;
    height: 100%;
    position: relative;
    overflow: hidden;
    text-align: center;
}
.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.1);
}
.stat-card .stat-icon {
    position: absolute;
    top: 10px;
    right: 12px;
    opacity: 0.2;
    font-size: 28px;
}
.stat-card .stat-icon i-feather {
    width: 24px;
    height: 24px;
}
.stat-card .stat-value {
    font-size: 26px;
    font-weight: 700;
    color: #1B4F72;
    margin-bottom: 2px;
}
.stat-card .stat-label {
    font-size: 12px;
    color: #566573;
    font-weight: 500;
}
.stat-card.stat-track .stat-icon {
    position: relative;
    top: auto;
    right: auto;
    opacity: 1;
    font-size: 20px;
    margin-bottom: 4px;
}
.stat-card.stat-track .stat-value {
    font-size: 22px;
}
.stat-card.stat-track .stat-label {
    font-size: 10px;
}

.bg-primary-grad { background: linear-gradient(135deg, #EBF5FB, #D6EAF8); }
.bg-success-grad { background: linear-gradient(135deg, #EAFAF1, #D5F5E3); }
.bg-warning-grad { background: linear-gradient(135deg, #FEF9E7, #FDEBD0); }
.bg-teal-grad { background: linear-gradient(135deg, #E8F8F5, #D1F2EB); }

/* ============================================
   CARDS
   ============================================ */
.card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    margin-bottom: 0;
    background: white;
}
.card-header {
    background: white;
    border-bottom: 1px solid #f0f0f0;
    padding: 10px 16px;
    border-radius: 12px 12px 0 0 !important;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
}
.card-title {
    font-size: 14px;
    font-weight: 600;
    color: #2C3E50;
    display: flex;
    align-items: center;
    gap: 6px;
}
.card-title i-feather {
    width: 18px;
    height: 18px;
}
.card-body {
    padding: 12px 16px;
}
.card-footer {
    background: #f8f9fa;
    border-top: 1px solid #f0f0f0;
    padding: 6px 16px;
    border-radius: 0 0 12px 12px !important;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 12px;
    color: #6c757d;
}
.card-footer i-feather {
    width: 14px;
    height: 14px;
}

/* কার্ড হেডার কালার */
.card-header-blue { background: #EBF5FB; border-bottom: 1px solid #d6eaf8; }
.card-header-green { background: #EAFAF1; border-bottom: 1px solid #d5f5e3; }
.card-header-yellow { background: #FEF9E7; border-bottom: 1px solid #fdebd0; }
.card-header-purple { background: #F4ECF7; border-bottom: 1px solid #ebdef0; }

/* কার্ড বর্ডার */
.card-download { border-left: 4px solid #2E86C1; }
.card-email { border-left: 4px solid #27AE60; }
.card-info { border-left: 4px solid #F39C12; }
.card-security { border-left: 4px solid #8E44AD; }

/* কার্ড আইকন */
.card-icon {
    font-size: 48px;
    margin-bottom: 8px;
}

/* ============================================
   INFO LIST
   ============================================ */
.info-list {
    list-style: none;
    padding: 0;
    margin: 0;
}
.info-list li {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px 0;
    font-size: 13px;
    border-bottom: 1px solid #f5f5f5;
}
.info-list li:last-child {
    border-bottom: none;
}
.info-list li i-feather {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
}

/* ============================================
   TABLE
   ============================================ */
.table-wrap {
    overflow-x: auto;
    max-height: 350px;
    overflow-y: auto;
}
.table-wrap::-webkit-scrollbar {
    width: 4px;
    height: 4px;
}
.table-wrap::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}
.table-wrap::-webkit-scrollbar-thumb {
    background: #c1c7cd;
    border-radius: 4px;
}

.table {
    font-size: 13px;
    margin-bottom: 0;
}
.table th {
    font-weight: 600;
    color: #566573;
    border-bottom: 2px solid #e9ecef;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    padding: 8px 10px;
    position: sticky;
    top: 0;
    background: #f8f9fa;
    z-index: 10;
}
.table td {
    vertical-align: middle;
    padding: 6px 10px;
    border-bottom: 1px solid #f0f0f0;
    font-size: 12px;
}
.table code {
    font-size: 11px;
    background: #f0f0f0;
    padding: 2px 8px;
    border-radius: 4px;
}
.table-hover tbody tr:hover {
    background: #f8f9fa;
}
.table-striped tbody tr:nth-of-type(odd) {
    background: #fcfcfd;
}

/* ============================================
   NORMAL BUTTONS
   ============================================ */
.btn {
    border-radius: 8px;
    font-weight: 500;
    font-size: 13px;
    padding: 6px 16px;
    font-family: 'Hind Siliguri', sans-serif;
}
.btn i-feather {
    width: 16px;
    height: 16px;
}
.btn-lg {
    font-size: 15px;
    padding: 10px 24px;
}
.btn-lg i-feather {
    width: 18px;
    height: 18px;
}
.btn-primary { background: #1B4F72; border-color: #1B4F72; }
.btn-primary:hover { background: #2E86C1; border-color: #2E86C1; }
.btn-success { background: #1E8449; border-color: #1E8449; }
.btn-success:hover { background: #27AE60; border-color: #27AE60; }

/* ============================================
   🔥 ANIMATED DOWNLOAD BUTTON - HIGHLIGHTED
   ============================================ */
.btn-download-animated {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 14px 40px;
    background: linear-gradient(135deg, #1B4F72, #2E86C1);
    color: #ffffff;
    border: none;
    border-radius: 12px;
    font-size: 18px;
    font-weight: 700;
    cursor: pointer;
    overflow: hidden;
    transition: all 0.3s ease;
    box-shadow: 0 4px 20px rgba(46, 134, 193, 0.4);
    font-family: 'Hind Siliguri', sans-serif;
    min-width: 200px;
    gap: 12px;
}

/* Hover Effect */
.btn-download-animated:hover {
    transform: translateY(-3px) scale(1.02);
    box-shadow: 0 8px 35px rgba(46, 134, 193, 0.6);
    background: linear-gradient(135deg, #2E86C1, #1B4F72);
}

/* Active/Click Effect */
.btn-download-animated:active {
    transform: scale(0.95);
}

/* Disabled State */
.btn-download-animated:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none !important;
}

/* Button Content */
.btn-download-content {
    display: flex;
    align-items: center;
    gap: 12px;
    position: relative;
    z-index: 2;
}

/* Download Icon Animation */
.download-icon {
    width: 24px;
    height: 24px;
    animation: bounce-down 2s ease-in-out infinite;
    transition: transform 0.3s ease;
}

.btn-download-animated:hover .download-icon {
    animation: bounce-down-fast 0.8s ease-in-out infinite;
}

/* Text */
.btn-text {
    position: relative;
    z-index: 2;
    letter-spacing: 0.5px;
}

/* ============================================
   PULSE RING ANIMATION (Border Animation)
   ============================================ */
.btn-download-pulse {
    position: absolute;
    inset: -4px;
    border-radius: 14px;
    border: 3px solid rgba(46, 134, 193, 0.4);
    animation: pulse-ring 2s ease-out infinite;
    pointer-events: none;
}

.btn-download-pulse::before {
    content: '';
    position: absolute;
    inset: -8px;
    border-radius: 18px;
    border: 3px solid rgba(46, 134, 193, 0.2);
    animation: pulse-ring 2s ease-out infinite 0.5s;
}

/* ============================================
   ANIMATIONS
   ============================================ */

/* Bounce Down Animation for Icon */
@keyframes bounce-down {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(6px);
    }
}

@keyframes bounce-down-fast {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(8px);
    }
}

/* Pulse Ring Animation for Border */
@keyframes pulse-ring {
    0% {
        transform: scale(1);
        opacity: 1;
    }
    100% {
        transform: scale(1.3);
        opacity: 0;
    }
}

/* Glow Animation */
.btn-download-animated::after {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%);
    animation: glow-rotate 4s linear infinite;
    pointer-events: none;
}

@keyframes glow-rotate {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}

/* ============================================
   BADGE
   ============================================ */
.badge {
    font-weight: 500;
    border-radius: 4px;
    font-size: 10px;
    padding: 3px 8px;
}

/* ============================================
   RESPONSIVE - ALL DEVICES
   ============================================ */

/* ===== মোবাইল (XS) - < 576px ===== */
@media (max-width: 575.98px) {
    /* স্ট্যাট কার্ড */
    .stat-card {
        padding: 10px 8px;
        border-radius: 10px;
    }
    .stat-card .stat-value {
        font-size: 18px;
    }
    .stat-card .stat-icon {
        font-size: 16px;
        top: 6px;
        right: 8px;
    }
    .stat-card .stat-icon i-feather {
        width: 16px;
        height: 16px;
    }
    .stat-card .stat-label {
        font-size: 9px;
    }
    .stat-card.stat-track .stat-value {
        font-size: 18px;
    }
    .stat-card.stat-track .stat-icon {
        font-size: 16px;
    }
    
    /* অ্যানিমেটেড ডাউনলোড বাটন - মোবাইল */
    .btn-download-animated {
        padding: 12px 24px;
        font-size: 15px;
        min-width: 160px;
        gap: 8px;
        border-radius: 10px;
    }
    .download-icon {
        width: 20px;
        height: 20px;
    }
    .btn-download-pulse {
        border-radius: 12px;
        border-width: 2px;
    }
    .btn-download-pulse::before {
        border-radius: 16px;
        border-width: 2px;
    }
    
    /* টেবিল */
    .table-wrap {
        max-height: 200px;
    }
    .table {
        font-size: 10px;
    }
    .table th {
        font-size: 8px;
        padding: 4px 6px;
    }
    .table td {
        padding: 3px 6px;
        font-size: 9px;
    }
    .table code {
        font-size: 8px;
        padding: 1px 4px;
    }
    
    /* কার্ড */
    .card-header {
        padding: 6px 10px;
    }
    .card-title {
        font-size: 12px;
    }
    .card-title i-feather {
        width: 14px;
        height: 14px;
    }
    .card-body {
        padding: 8px 10px;
    }
    .card-footer {
        font-size: 9px;
        padding: 4px 10px;
    }
    .card-footer i-feather {
        width: 12px;
        height: 12px;
    }
    .card-icon {
        font-size: 32px;
    }
    
    /* নরমাল বাটন */
    .btn {
        font-size: 11px;
        padding: 4px 10px;
    }
    .btn-lg {
        font-size: 13px;
        padding: 8px 16px;
    }
    .btn i-feather {
        width: 14px;
        height: 14px;
    }
    .btn-lg i-feather {
        width: 16px;
        height: 16px;
    }
    
    /* কন্টেইনার */
    .container-fluid {
        padding-left: 6px !important;
        padding-right: 6px !important;
    }
    
    /* হেডার */
    h4 {
        font-size: 14px !important;
    }
    h4 small {
        font-size: 11px !important;
    }
    
    /* ব্যাজ */
    .badge {
        font-size: 8px;
        padding: 2px 6px;
    }
    
    /* গ্যাপ */
    .g-2 {
        --bs-gutter-y: 0.25rem;
        --bs-gutter-x: 0.25rem;
    }
    
    /* ২ কলাম নিশ্চিত */
    .col-6 {
        flex: 0 0 auto;
        width: 50%;
        padding-left: 4px;
        padding-right: 4px;
    }
    .row.g-2 > .col-6,
    .row.g-sm-3 > .col-6 {
        padding-left: 4px;
        padding-right: 4px;
    }
    
    /* তথ্য লিস্ট */
    .info-list li {
        font-size: 10px;
        padding: 3px 0;
        gap: 4px;
    }
    .info-list li i-feather {
        width: 12px;
        height: 12px;
    }
    
    /* ইমেইল কার্ড */
    .card-body.text-center .btn {
        font-size: 12px !important;
        padding: 6px 14px !important;
    }
    .card-body.text-center .card-icon {
        font-size: 28px !important;
    }
    .card-body.text-center p {
        font-size: 11px !important;
    }
}

/* ===== ট্যাবলেট (SM) - 576px - 767px ===== */
@media (min-width: 576px) and (max-width: 767.98px) {
    .stat-card .stat-value {
        font-size: 20px;
    }
    .stat-card {
        padding: 12px 10px;
    }
    .table-wrap {
        max-height: 250px;
    }
    .col-6 {
        flex: 0 0 auto;
        width: 50%;
    }
    
    /* ট্যাবলেটে ডাউনলোড বাটন */
    .btn-download-animated {
        padding: 13px 32px;
        font-size: 17px;
        min-width: 180px;
    }
    .download-icon {
        width: 22px;
        height: 22px;
    }
}

/* ===== ডেস্কটপ (MD+) - 768px+ ===== */
@media (min-width: 768px) {
    .stat-card .stat-value {
        font-size: 26px;
    }
    .stat-card {
        padding: 16px 18px;
    }
    .table-wrap {
        max-height: 380px;
    }
    .col-md-3 {
        flex: 0 0 auto;
        width: 25%;
    }
}

/* ===== EXTRA - মোবাইলে ২ কলাম গ্যারান্টি ===== */
@media (max-width: 767.98px) {
    .row.g-2 > *,
    .row.g-sm-3 > * {
        padding-left: 4px;
        padding-right: 4px;
    }
    .g-2 {
        --bs-gutter-y: 0.25rem;
        --bs-gutter-x: 0.25rem;
    }
    .g-sm-3 {
        --bs-gutter-y: 0.25rem;
        --bs-gutter-x: 0.25rem;
    }
}
</style>

<!-- ==============================================
     JAVASCRIPT
     ============================================== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
});

function downloadBackup() {
    var btns = document.querySelectorAll('#downloadBtn, #downloadBtn2');
    btns.forEach(function(btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="btn-download-content"><i data-feather="loader" class="download-icon" style="animation: spin 1s linear infinite;"></i><span class="btn-text">প্রস্তুত...</span></span>';
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    });
    
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '/admin/backup_actions.php';
    form.target = '_blank';
    
    var actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'download';
    form.appendChild(actionInput);
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
    
    setTimeout(function() {
        btns.forEach(function(btn) {
            btn.disabled = false;
            btn.innerHTML = '<span class="btn-download-content"><i data-feather="download" class="download-icon"></i><span class="btn-text">ডাউনলোড</span></span>';
            if (typeof feather !== 'undefined') {
                feather.replace();
            }
        });
    }, 5000);
}

function emailBackup() {
    var btns = document.querySelectorAll('#emailBtn, #emailBtn2');
    btns.forEach(function(btn) {
        btn.disabled = true;
        btn.innerHTML = '⏳...';
    });
    
    fetch('/admin/backup_actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=email'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'সফল!',
                text: data.message,
                timer: 3000,
                showConfirmButton: false
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'ত্রুটি!',
                text: data.message
            });
        }
        btns.forEach(function(btn) {
            btn.disabled = false;
            btn.innerHTML = '<i data-feather="mail"></i> ইমেইল';
            if (typeof feather !== 'undefined') {
                feather.replace();
            }
        });
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'ত্রুটি!',
            text: 'সার্ভার সংযোগে সমস্যা হয়েছে!'
        });
        btns.forEach(function(btn) {
            btn.disabled = false;
            btn.innerHTML = '<i data-feather="mail"></i> ইমেইল';
            if (typeof feather !== 'undefined') {
                feather.replace();
            }
        });
    });
}

// Spin Animation for Loader
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);

if (typeof Swal === 'undefined') {
    var swalScript = document.createElement('script');
    swalScript.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
    document.head.appendChild(swalScript);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>