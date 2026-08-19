<?php

header("Content-Type: application/json");
// Allow dynamic origin for local frontend to call this endpoint during development
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: " . $origin);
header("Access-Control-Allow-Credentials: true");

require_once "../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$room_number = $data["room_number"] ?? "";
$room_type = $data["room_type"] ?? "";
$room_hall = $data["room_hall"] ?? "";
$room_gender = $data["room_gender"] ?? "unisex";
$capacity = $data["capacity"] ?? 0;

// ── CLEAN AND RE-ROUTE THE PRICE LOGIC ──
// If the price is left completely blank, undefined, or explicitly set to 0, default to 50000
$raw_price = $data["price"] ?? "";
$price = (!isset($data["price"]) || $raw_price === "" || (int)$raw_price === 0) ? 50000 : (int)$raw_price;
if (!$room_number || !$capacity || !$room_hall) {
    echo json_encode([
        "success" => false,
        "message" => "Missing fields"
    ]);
    exit;
}

try {
    $pdo = DB::get();

    // 1. insert room with explicit hall, gender, and price
    $stmt = $pdo->prepare(
        "INSERT INTO rooms (room_number, room_type, hall, gender, capacity, price, occupied, status)
        VALUES (?, ?, ?, ?, ?, ?, 0, 'available')"
    );
    // ── FIXED: Included the $price variable in the execution sequence array to match the SQL parameters
    $stmt->execute([$room_number, $room_type, $room_hall, $room_gender, $capacity, $price]);

    // 2. get room id
    $room_id = $pdo->lastInsertId();

    // 3. CREATE BEDSPACES AUTOMATICALLY
    $bedStmt = $pdo->prepare("
        INSERT INTO bedspaces (room_id, bed_number, is_occupied)
        VALUES (?, ?, 0)
    ");

    for ($i = 1; $i <= $capacity; $i++) {
        $bedStmt->execute([$room_id, $i]);
    }

    // fetch the created room row to return useful data to the frontend
    $roomStmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ?");
    $roomStmt->execute([$room_id]);
    $createdRoom = $roomStmt->fetch();

    echo json_encode([
        "success" => true,
        "message" => "Room and bedspaces created successfully",
        "room" => $createdRoom
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}