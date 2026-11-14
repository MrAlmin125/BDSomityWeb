<?php
// =====================================================
// admin/backup_actions.php - ব্যাকআপ অ্যাকশন হ্যান্ডলার
// IPHONE FIXED - Simple & Clean
// =====================================================

session_name('somity_session');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// অ্যাডমিন চেক
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_id'] < 1) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'লগইন প্রয়োজন']);
        exit;
    }
    header('Location: /admin/login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// =============================================
// ✅ DOWNLOAD - SIMPLE VERSION FOR IPHONE
// =============================================
if (isset($_POST['action']) && $_POST['action'] === 'download') {
    
    // Clean all buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    try {
        // Database backup create
        $backupFile = create_backup_file(); // আপনার ফাংশন
        
        if ($backupFile && file_exists($backupFile)) {
            $filename = 'backup_' . date('Y-m-d') . '.zip';
            $filesize = filesize($backupFile);
            
            // 🔥 IPHONE FIX - স্পেসিফিক হেডার
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . $filesize);
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            
            // Output file
            readfile($backupFile);
            @unlink($backupFile);
            exit;
            
        } else {
            // Error - JSON response for AJAX
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'ব্যাকআপ তৈরি ব্যর্থ']);
                exit;
            }
            $_SESSION['error'] = 'ব্যাকআপ তৈরি ব্যর্থ';
            header('Location: /admin/backup.php');
            exit;
        }
        
    } catch (Exception $e) {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
        $_SESSION['error'] = $e->getMessage();
        header('Location: /admin/backup.php');
        exit;
    }
}

// =============================================
// 📧 EMAIL
// =============================================
if (isset($_POST['action']) && $_POST['action'] === 'email') {
    header('Content-Type: application/json');
    
    try {
        $backupFile = create_backup_file();
        
        if ($backupFile && file_exists($backupFile)) {
            $filename = 'backup_' . date('Y-m-d') . '.zip';
            
            // ইমেইল পাঠান
            $emailSent = sendEmailWithAttachment(
                BACKUP_EMAIL,
                'ডেটাবেস ব্যাকআপ - ' . date('d-m-Y'),
                "ব্যাকআপ ফাইল সংযুক্ত",
                $backupFile,
                $filename
            );
            
            @unlink($backupFile);
            
            if ($emailSent) {
                echo json_encode(['success' => true, 'message' => 'ইমেইল পাঠানো হয়েছে']);
            } else {
                echo json_encode(['success' => false, 'message' => 'ইমেইল পাঠাতে ব্যর্থ']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'ব্যাকআপ তৈরি ব্যর্থ']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Helper function - আপনার existing function use করুন
function create_backup_file() {
    // আপনার existing backup creation logic
    // ফিরিয়ে দিন zip file এর পাথ
    return '/path/to/backup.zip';
}

function sendEmailWithAttachment($to, $subject, $body, $attachment, $filename) {
    // আপনার existing email logic
    return true;
}
?>