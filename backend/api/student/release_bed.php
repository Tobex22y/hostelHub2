<?php
session_start();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");
require_once "../config/db.php";

if (!isset($_SESSION["student_id"])) {
    echo json_encode(["success" => false, "message" => "Not logged in"]);
    exit;
}

$student_id    = $_SESSION["student_id"];
$data          = json_decode(file_get_contents("php://input"), true);
$allocation_id = $data["allocation_id"] ?? null;

if (!$allocation_id) {
    echo json_encode(["success" => false, "message" => "Missing allocation_id"]);
    exit;
}

try {
    $pdo = DB::get();
    $pdo->beginTransaction();

    // Support cancelling reserved or pending allocations (before payment confirmed)
    $stmt = $pdo->prepare("
        SELECT bed_id, status FROM allocations
        WHERE id = ? AND student_id = ?
        AND status IN ('reserved', 'pending')
        LIMIT 1
    ");
    $stmt->execute([$allocation_id, $student_id]);
    $alloc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$alloc) {
        $pdo->rollBack();
        // Check if it exists at all to give a better error message
        $check = $pdo->prepare("SELECT status FROM allocations WHERE id = ? AND student_id = ? LIMIT 1");
        $check->execute([$allocation_id, $student_id]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            echo json_encode([
                "success" => false,
                "message" => "Cannot cancel — allocation status is '{$existing['status']}'. Only reserved or pending allocations can be cancelled."
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "message" => "No cancellable allocation found with ID: $allocation_id"
            ]);
        }
        exit;
    }

    // 1. Release bed space status back to available
    $pdo->prepare("
        UPDATE bedspaces 
        SET status = 'available', is_occupied = 0, reserved_at = NULL
        WHERE id = ?
    ")->execute([$alloc["bed_id"]]);

    // 2. Delete the pending/reserved allocation row from the database
    $pdo->prepare("
        DELETE FROM allocations 
        WHERE id = ? AND (status = 'reserved' OR status = 'pending')
    ")->execute([$allocation_id]);

    $pdo->commit();
    echo json_encode(["success" => true, "message" => "Reservation cancelled successfully. Bed has been released."]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}