<?php
session_start();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");

require_once "../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);
$allocation_id = $data["allocation_id"] ?? null;

if (!$allocation_id) {
    echo json_encode(["success" => false, "message" => "Missing targeted allocation ID."]);
    exit;
}

try {
    $pdo = DB::get();
    $pdo->beginTransaction();

    // 1. Fetch matching allocation to identify the correct bed ID
    $stmt = $pdo->prepare("SELECT bed_id FROM allocations WHERE id = ?");
    $stmt->execute([$allocation_id]);
    $alloc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$alloc) {
        $pdo->rollBack();
        echo json_encode(["success" => false, "message" => "Allocation record not found."]);
        exit;
    }

    // 2. Free up the bedspace layout instantly
    $updateBed = $pdo->prepare("
        UPDATE bedspaces 
        SET is_occupied = 0, status = 'available', reserved_at = NULL 
        WHERE id = ?
    ");
    $updateBed->execute([$alloc["bed_id"]]);

    // 3. Delete or update the allocation so unique key constraint checks pass on reapplication
    $deleteAlloc = $pdo->prepare("DELETE FROM allocations WHERE id = ?");
    $deleteAlloc->execute([$allocation_id]);

    $pdo->commit();
    echo json_encode(["success" => true, "message" => "Allocation successfully terminated."]);

} catch (PDOException $e) {
if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
    }
    echo json_encode(["success" => false, "message" => "Transaction error: " . $e->getMessage()]);
}