<?php
// =====================================================
// api/me.php - বর্তমান অ্যাডমিন তথ্য
// GET (Header: Authorization: Bearer <token>)
// =====================================================
require_once __DIR__ . '/config.php';

$admin = requireToken();

jsonResponse(true, [
    'admin' => formatAdmin($admin),
], '');