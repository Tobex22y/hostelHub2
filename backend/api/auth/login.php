<?php
session_set_cookie_params([
    "lifetime" => 0,
    "path" => "/",
    // Removed "domain" => "localhost" — leaving domain unset lets the browser
    // default to the current request's actual host (works on both XAMPP and Render).
    "httponly" => true,
    "samesite" => "Lax",
    "secure" => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' // true on Render (https), false on local XAMPP (http)
]);
session_start();
error_log("LOGIN session_id: " . session_id() . " | student_id set to: " . ($_SESSION['student_id'] ?? 'not yet'));

// Reflect whichever origin actually made the request, instead of hardcoding localhost.
// Falls back to '*' only if no Origin header is present (e.g. same-origin requests).
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) {
    header("Access-Control-Allow-Origin: $origin");
}
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
