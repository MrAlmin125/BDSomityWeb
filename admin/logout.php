<?php
// admin/logout.php
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
logActivity('logout','Admin logged out');
session_destroy();
header('Location: /admin/login.php');
exit;
