<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/session_handler.php";

$handler = new DBSessionHandler();
session_set_save_handler($handler, true);

session_start();
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");

require_once __DIR__ . '/../config/db.php';

// Verify Admin Session
if (empty($_SESSION['admin_logged_in'])) {
    echo json_encode(["success" => false, "message" => "Unauthorized access."]);
    exit;
}

$input     = json_decode(file_get_contents("php://input"), true);
$ticket_id = intval($input['ticket_id'] ?? 0);

if (!$ticket_id) {
    echo json_encode(["success" => false, "message" => "Invalid ticket ID."]);
    exit;
}

try {
    $pdo = DB::get();

    $stmt = $pdo->prepare("DELETE FROM maintenance_tickets WHERE id = ?");
    $stmt->execute([$ticket_id]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(["success" => false, "message" => "Ticket not found or already deleted."]);
        exit;
    }

    echo json_encode(["success" => true, "message" => "Ticket deleted."]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
