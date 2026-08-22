<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/session_handler.php";

$handler = new DBSessionHandler();
session_set_save_handler($handler, true);

session_start();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");

require_once "../config/db.php";

if (!isset($_SESSION["student_id"])) {
    echo json_encode(["success" => false, "message" => "Not logged in"]);
    exit;
}

// Accept room_id from GET or JSON body
$room_id = $_GET["room_id"] ?? $_GET["id"] ?? null;

if (!$room_id) {
    $inputData = json_decode(file_get_contents("php://input"), true);
    $room_id = $inputData["room_id"] ?? null;
}

if (!$room_id) {
    echo json_encode(["success" => false, "message" => "Missing room_id parameter"]);
    exit;
}

try {
    $pdo = DB::get();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Return is_occupied AND status so the frontend can correctly
    // mark reserved beds (status = 'reserved') as unavailable
    $stmt = $pdo->prepare("
        SELECT id, bed_number, is_occupied, status
        FROM bedspaces
        WHERE room_id = ?
        ORDER BY bed_number ASC
    ");
    $stmt->execute([$room_id]);
    $beds = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Normalise: a bed is unavailable if occupied OR reserved
    foreach ($beds as &$bed) {
        $bed["is_available"] = (
            (int)$bed["is_occupied"] === 0 &&
            $bed["status"] !== "reserved" &&
            $bed["status"] !== "occupied"
        ) ? true : false;
    }
    unset($bed);

    echo json_encode([
        "success" => true,
        "beds"    => $beds
    ]);

} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Database query failure: " . $e->getMessage()
    ]);
}
