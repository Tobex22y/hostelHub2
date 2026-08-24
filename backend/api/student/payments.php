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
    $studentId = $_SESSION["student_id"];
    $stmt = $pdo->prepare("
        SELECT p.id, p.allocation_id, p.amount, p.status, p.reference, p.created_at,
               r.room_number, r.hall, b.bed_number
        FROM payments p
        LEFT JOIN allocations a ON p.allocation_id = a.id
        LEFT JOIN rooms r ON a.room_id = r.id
        LEFT JOIN bedspaces b ON a.bed_id = b.id
        WHERE p.student_id = ? OR a.student_id = ?
        ORDER BY p.created_at DESC, p.id DESC
    ");
    $stmt->execute([$studentId, $studentId]);
    echo json_encode(["success" => true, "payments" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}