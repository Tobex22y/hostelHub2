<?php

declare(strict_types=1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// api/admin_signup.php  –  Create a new admin user
// POST: first_name, last_name, username, email, password, confirm_password, role



require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.', [], 405);
}

$missing = requireFields([
    'first_name', 'last_name', 'username', 'email',
    'password', 'confirm_password'
]);
if ($missing) {
    jsonResponse(false, 'Missing required fields: ' . implode(', ', $missing), [], 422);
}

$firstName = trim($_POST['first_name']);
$lastName  = trim($_POST['last_name']);
$username  = trim($_POST['username']);
$email     = strtolower(trim($_POST['email']));
$password  = $_POST['password'];
$confirm   = $_POST['confirm_password'];
$role      = strtolower(trim($_POST['role'] ?? 'admin'));

$validRoles = ['super_admin', 'admin', 'manager', 'staff'];
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, 'Invalid email address.', [], 422);
}
if (strlen($username) < 4) {
    jsonResponse(false, 'Username must be at least 4 characters.', [], 422);
}
if (!in_array($role, $validRoles, true)) {
    jsonResponse(false, 'Invalid role. Use one of: ' . implode(', ', $validRoles), [], 422);
}
if (strlen($password) < 8) {
    jsonResponse(false, 'Password must be at least 8 characters.', [], 422);
}
if ($password !== $confirm) {
    jsonResponse(false, 'Passwords do not match.', [], 422);
}

$pdo = DB::get();

$dup = $pdo->prepare(
    'SELECT admin_id FROM admin WHERE username = :username OR email = :email LIMIT 1'
);
$dup->execute([':username' => $username, ':email' => $email]);

if ($dup->fetch()) {
    jsonResponse(false, 'Username or email already registered.', [], 409);
}

$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

$ins = $pdo->prepare(
    'INSERT INTO admin (username, email, password_hash, first_name, last_name, role)
     VALUES (:username, :email, :hash, :first, :last, :role)'
);
$ins->execute([
    ':username' => $username,
    ':email'    => $email,
    ':hash'     => $hash,
    ':first'    => $firstName,
    ':last'     => $lastName,
    ':role'     => $role,
]);

$adminId = (int) $pdo->lastInsertId();

auditLog($pdo, null, 'ADMIN_SIGNUP', 'admin', $adminId, [
    'username'  => $username,
    'email'     => $email,
    'role'      => $role,
]);

jsonResponse(true, 'Admin account created successfully.', [
    'admin_id'   => $adminId,
    'username'   => $username,
    'email'      => $email,
    'first_name' => $firstName,
    'last_name'  => $lastName,
    'role'       => $role,
], 201);
