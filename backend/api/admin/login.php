<?php

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/session_handler.php";

$handler = new DBSessionHandler();
session_set_save_handler($handler, true);

session_start();
header("Content-Type: application/json");

require_once __DIR__ . "/../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$email = $data["email"] ?? "";
$password = $data["password"] ?? "";

if (!$email || !$password) {
    echo json_encode([
        "success" => false,
        "message" => "Email and password required"
    ]);
    exit;
}

try {
    $pdo = DB::get();

    $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
    $stmt->execute([$email]);

    $admin = $stmt->fetch();

    if (!$admin) {
        echo json_encode([
            "success" => false,
            "message" => "Admin not found"
        ]);
        exit;
    }

    if (!password_verify($password, $admin["password"])) {
        echo json_encode([
            "success" => false,
            "message" => "Incorrect password"
        ]);
        exit;
    }

    // This is the flag update_ticket.php / delete_ticket.php actually check for.
    // It was missing before, which is why login "succeeded" but every
    // protected admin action still returned "Unauthorized access."
    $_SESSION["admin_logged_in"] = true;
    $_SESSION["admin_id"] = $admin["id"];
    $_SESSION["admin_name"] = $admin["fullname"];

    echo json_encode([
        "success" => true,
        "message" => "Login successful",
        "admin" => [
            "id" => $admin["id"],
            "fullname" => $admin["fullname"],
            "email" => $admin["email"]
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
