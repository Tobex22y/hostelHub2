<?php
// api/admin_logout.php  –  Destroy the admin session
// POST (no fields required)

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

header('Content-Type: application/json; charset=utf-8');

session_start();
$adminId = $_SESSION['admin_id'] ?? null;

$pdo = DB::get();
if ($adminId) {
    auditLog($pdo, null, 'ADMIN_LOGOUT', 'auth', (int) $adminId);
}

unset($_SESSION['admin_id'], $_SESSION['admin_user'], $_SESSION['admin_email'], $_SESSION['admin_role'], $_SESSION['admin_name'], $_SESSION['admin_logged_in_at']);

jsonResponse(true, 'Admin logged out successfully.');
