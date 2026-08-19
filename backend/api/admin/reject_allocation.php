<?php
session_start();

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");

require_once "../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$allocation_id = $data["allocation_id"] ?? null;

if (!$allocation_id) {
    echo json_encode([
        "success" => false,
        "message" => "Missing allocation_id"
    ]);
    exit;
}

try {
    $pdo = DB::get();

    // 1. Get allocation
    $stmt = $pdo->prepare("SELECT * FROM allocations WHERE id = ?");
    $stmt->execute([$allocation_id]);
    $allocation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$allocation) {
        echo json_encode([
            "success" => false,
            "message" => "Allocation not found"
        ]);
        exit;
    }

    // 2. FREE the bed again
    $freeBed = $pdo->prepare("
        UPDATE bedspaces 
        SET is_occupied = 0 
        WHERE id = ?
    ");
    $freeBed->execute([$allocation["bed_id"]]);

    // 3. Update allocation status
    $update = $pdo->prepare("
        UPDATE allocations 
        SET status = 'rejected'
        WHERE id = ?
    ");
    $update->execute([$allocation_id]);

    echo json_encode([
        "success" => true,
        "message" => "Allocation rejected"
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}