<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/session_handler.php";

$handler = new DBSessionHandler();
session_set_save_handler($handler, true);

session_start();

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) {
    header("Access-Control-Allow-Origin: $origin");
}
header("Access-Control-Allow-Credentials: true");
header("Vary: Origin");
header("Content-Type: application/json");

if (!isset($_SESSION["student_id"])) {
    echo json_encode([
        "success" => false,
        "message" => "Not logged in"
    ]);
    exit;
}
$student_id = $_SESSION["student_id"];
try {
    $pdo = DB::get();
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            a.room_id,
            a.bed_id,
            a.status,
            a.payment_reference,
            a.created_at,
            r.room_number,
            r.hall,
            r.room_type,
            r.price,
            b.bed_number
        FROM allocations a
        JOIN rooms r ON a.room_id = r.id
        JOIN bedspaces b ON a.bed_id = b.id
        WHERE a.student_id = ?
        ORDER BY a.created_at DESC, a.id DESC
    ");
    $stmt->execute([$student_id]);
    $allocations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode([
        "success" => true,
        "allocation" => $allocations[0] ?? null,
        "allocations" => $allocations
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
