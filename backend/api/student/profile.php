<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/session_handler.php";

$handler = new DBSessionHandler();
session_set_save_handler($handler, true);
session_start();

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) header("Access-Control-Allow-Origin: $origin");
header("Access-Control-Allow-Credentials: true");
header("Vary: Origin");
header("Content-Type: application/json");

if (!isset($_SESSION["student_id"])) {
    echo json_encode(["success" => false, "message" => "Not logged in"]);
    exit;
}

try {
    $pdo = DB::get();
    $stmt = $pdo->prepare("SELECT id, fullname, email, phone, gender, matric_number, profile_image, created_at FROM students WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION["student_id"]]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$student) {
        echo json_encode(["success" => false, "message" => "Student not found"]);
        exit;
    }
    echo json_encode(["success" => true, "student" => $student]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}