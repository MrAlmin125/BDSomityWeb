<?php
// =====================================================
// cron/auto_backup.php - অটো ব্যাকআপ (CRON Job)
// =====================================================

// CRON সেটআপ (প্রতি রবিবার সকাল ৬টায়):
// 0 6 * * 0 php /home/yourusername/public_html/cron/auto_backup.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// লগ ফাইল
$logFile = __DIR__ . '/backup_log.txt';

function logMessage($msg) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $msg\n", FILE_APPEND);
}

logMessage("🔄 অটো ব্যাকআপ শুরু...");
logMessage("📊 ডেটাবেস: " . DB_NAME);

try {
    $result = createBackup();
    
    if ($result['success']) {
        // ফাইলের নাম পরিবর্তন
        $emailFilename = 'backup_' . date('Y-m-d') . '.zip';
        
        logMessage("✅ ব্যাকআপ তৈরি সফল! ফাইল: $emailFilename, আকার: " . number_format($result['file_size']/1024, 2) . " KB");
        
        // ইমেইল বডি
        $body = "প্রিয় অ্যাডমিন,\n\n";
        $body .= "আপনার সোমিটি ডেটাবেসের সাপ্তাহিক অটো ব্যাকআপ সংযুক্ত করা হলো।\n\n";
        $body .= "📅 সময়: " . date('d-m-Y h:i:s A') . "\n";
        $body .= "📊 ডেটাবেস: " . DB_NAME . "\n";
        $body .= "📁 টেবিল: " . $result['tables'] . "টি\n";
        $body .= "📦 ফাইলের আকার: " . number_format($result['file_size'] / 1024, 2) . " KB\n\n";
        $body .= "📁 ফাইলের নাম: " . $emailFilename . "\n\n";
        $body .= "💡 টিপ: এই ব্যাকআপটি নিরাপদ স্থানে সংরক্ষণ করুন।\n";
        $body .= "ধন্যবাদ,\n";
        $body .= getSetting('somity_name', 'সোমিটি') . " অটো ব্যাকআপ সিস্টেম";
        
        $subject = "📥 সাপ্তাহিক অটো ব্যাকআপ - " . date('d-m-Y');
        
        $emailSent = sendBackupByEmail(
            BACKUP_EMAIL,
            $subject,
            $body,
            $result['zip_path'],
            $emailFilename
        );
        
        @unlink($result['zip_path']);
        
        if ($emailSent) {
            logMessage("✅ ইমেইল পাঠানো হয়েছে: " . BACKUP_EMAIL);
        } else {
            logMessage("⚠️ ব্যাকআপ তৈরি হয়েছে কিন্তু ইমেইল পাঠাতে ব্যর্থ!");
        }
        
        // পুরনো ব্যাকআপ পরিষ্কার (৩০ দিনের বেশি পুরনো)
        $deleted = cleanOldBackups(BACKUP_RETENTION_DAYS);
        if ($deleted > 0) {
            logMessage("🗑️ $deleted টি পুরনো ব্যাকআপ ফাইল ডিলিট করা হয়েছে।");
        }
        
    } else {
        logMessage("❌ ব্যাকআপ তৈরি করতে ব্যর্থ: " . $result['message']);
    }
    
} catch (Exception $e) {
    logMessage("❌ ত্রুটি: " . $e->getMessage());
}

logMessage("🏁 অটো ব্যাকআপ শেষ।\n" . str_repeat("-", 60) . "\n");
?>