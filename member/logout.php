<?php
// member/logout.php
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
logActivity('logout','Member logged out','member');
session_destroy();
header('Location: /member/login.php');
exit;
