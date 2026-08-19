<?php
// api/admin_login.php  –  Admin authentication
// POST: identifier (email or username), password

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.', [], 405);
}

// Accept either a JSON body (what admin_login.js currently sends) or a
// traditional form-encoded POST body, and accept either "identifier" or
// "email" as the field name.
$jsonBody = json_decode(file_get_contents('php://input'), true) ?? [];

$identifier = trim(
    $_POST['identifier'] ?? $_POST['email'] ?? $jsonBody['identifier'] ?? $jsonBody['email'] ?? ''
);
$password = $_POST['password'] ?? $jsonBody['password'] ?? '';

if ($identifier === '' || $password === '') {
    jsonResponse(false, 'Identifier and password are required.', [], 422);
}

$pdo = DB::get();
$stmt = $pdo->prepare(
    'SELECT admin_id, username, email, password_hash, first_name, last_name, role, is_active
     FROM   admin
     WHERE  username = :id OR email = :id
     LIMIT  1'
);
$stmt->execute([':id' => $identifier]);
$admin = $stmt->fetch();

if (!$admin || !password_verify($password, $admin['password_hash'])) {
    auditLog($pdo, null, 'ADMIN_LOGIN_FAIL', 'auth', null, [
        'identifier' => $identifier,
    ]);
    jsonResponse(false, 'Invalid admin credentials.', [], 401);
}

if (!$admin['is_active']) {
    jsonResponse(false, 'Admin account is disabled. Contact a super admin.', [], 403);
}

// This is the flag every protected admin endpoint (update_ticket.php,
// delete_ticket.php, etc.) actually checks for. It was missing before,
// which is why those endpoints always returned "Unauthorized access."
// even after a successful login.
$_SESSION['admin_logged_in']    = true;
$_SESSION['admin_id']           = $admin['admin_id'];
$_SESSION['admin_user']         = $admin['username'];
$_SESSION['admin_email']        = $admin['email'];
$_SESSION['admin_role']         = $admin['role'];
$_SESSION['admin_name']         = trim($admin['first_name'] . ' ' . $admin['last_name']);
$_SESSION['admin_logged_in_at'] = date('Y-m-d H:i:s');

if (password_needs_rehash($admin['password_hash'], PASSWORD_BCRYPT, ['cost' => 12])) {
    $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $pdo->prepare('UPDATE admin SET password_hash = ? WHERE admin_id = ?')
        ->execute([$newHash, $admin['admin_id']]);
}

$pdo->prepare('UPDATE admin SET last_login = NOW() WHERE admin_id = :id')
    ->execute([':id' => $admin['admin_id']]);

auditLog($pdo, null, 'ADMIN_LOGIN_SUCCESS', 'auth', $admin['admin_id'], [
    'username' => $admin['username'],
    'role'     => $admin['role'],
]);

jsonResponse(true, 'Admin login successful.', [
    'admin_id'   => $admin['admin_id'],
    'username'   => $admin['username'],
    'email'      => $admin['email'],
    'first_name' => $admin['first_name'],
    'last_name'  => $admin['last_name'],
    'role'       => $admin['role'],
]);