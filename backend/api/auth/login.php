<?php

session_set_cookie_params([
    "lifetime" => 0,
    "path" => "/",
    "domain" => "localhost",
    "httponly" => true,
    "samesite" => "Lax"
]);

session_start();

header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once "../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$matric = $data["matric_number"] ?? "";
$password = $data["password"] ?? "";

if (!$matric || !$password) {
    echo json_encode([
        "success" => false,
        "message" => "Matric number and password required"
    ]);
    exit;
}

try {
    $pdo = DB::get();

    $stmt = $pdo->prepare("SELECT * FROM students WHERE matric_number = ?");
    $stmt->execute([$matric]);

    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode([
            "success" => false,
            "message" => "Student not found"
        ]);
        exit;
    }

    if (!password_verify($password, $user["password"])) {
        echo json_encode([
            "success" => false,
            "message" => "Incorrect password"
        ]);
        exit;
    }

    // SESSION
    $_SESSION["student_id"] = $user["id"];
    $_SESSION["student_name"] = $user["fullname"];

    echo json_encode([
        "success" => true,
        "message" => "Login successful",
        "user" => [
            "id" => $user["id"],
            "name" => $user["fullname"],
            "matric_number" => $user["matric_number"]
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}