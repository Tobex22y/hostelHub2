<?php
session_start();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['student_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in"]);
    exit;
}

$student_id = (int)$_SESSION['student_id'];

try {
    $pdo  = DB::get();
    $stmt = $pdo->prepare("
        SELECT id, category, title, description, location,
               priority, status, admin_note, created_at, updated_at
        FROM maintenance_tickets
        WHERE student_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$student_id]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["success" => true, "tickets" => $tickets]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}