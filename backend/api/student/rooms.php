<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/session_handler.php";

$handler = new DBSessionHandler();
session_set_save_handler($handler, true);

session_start();
header("Content-Type: application/json");

$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: " . $origin);
header("Access-Control-Allow-Credentials: true");

require_once "../config/db.php";

try {
    $pdo = DB::get();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $studentGender = null;

    if (isset($_SESSION["student_id"])) {
        $sid   = $_SESSION["student_id"];
        $gstmt = $pdo->prepare("SELECT gender FROM students WHERE id = ?");
        $gstmt->execute([$sid]);
        $sg = $gstmt->fetch();
        if ($sg && isset($sg["gender"])) {
            $studentGender = trim(strtolower($sg["gender"]));
        }
    }

    if (!$studentGender) {
        echo json_encode([
            "success" => true,
            "rooms"   => [],
            "halls"   => []
        ]);
        exit;
    }

    $hallFilter = $_GET['hall'] ?? null;

    $query  = "SELECT * FROM rooms WHERE LOWER(gender) = ?";
    $params = [$studentGender];

    if ($hallFilter) {
        $query  .= " AND hall = ?";
        $params[] = $hallFilter;
    }

    $q = $pdo->prepare($query);
    $q->execute($params);
    $rooms = $q->fetchAll();

    $result = [];
    $halls  = [];

    foreach ($rooms as $room) {
        $room_id = $room["id"];

        // Total beds
        $totalStmt = $pdo->prepare("
            SELECT COUNT(*) AS total FROM bedspaces WHERE room_id = ?
        ");
        $totalStmt->execute([$room_id]);
        $totalBeds = (int)$totalStmt->fetch()["total"];

        // Unavailable = occupied OR reserved
        // This prevents dashboard showing phantom availability
        // for beds that are mid-booking (pending payment)
        $unavailStmt = $pdo->prepare("
            SELECT COUNT(*) AS total
            FROM bedspaces
            WHERE room_id = ?
              AND (is_occupied = 1 OR status IN ('reserved', 'occupied', 'maintenance'))
        ");
        $unavailStmt->execute([$room_id]);
        $unavailable = (int)$unavailStmt->fetch()["total"];

        $available = max(0, $totalBeds - $unavailable);

        // Use explicit hall column, fallback to parsing room_number prefix
        $hall = $room["hall"] ?? null;
        if (!$hall) {
            $room_number = $room["room_number"];
            if (preg_match('/^([A-Za-z]+\d*)/', $room_number, $m)) {
                $hall = $m[1];
            } else {
                $hall = substr($room_number, 0, 1);
            }
        }

        $halls[] = $hall;

        $result[] = [
            "id"          => $room["id"],
            "room_number" => $room["room_number"],
            "room_type"   => $room["room_type"],
            "capacity"    => $room["capacity"],
            "unavailable" => $unavailable,
            "available"   => $available,
            "status"      => $room["status"],
            "hall"        => $hall,
            "gender"      => $room["gender"] ?? "unisex",
            "price"       => $room["price"] ?? null
        ];
    }

    $halls = array_values(array_unique($halls));
    sort($halls, SORT_STRING);

    echo json_encode([
        "success" => true,
        "rooms"   => $result,
        "halls"   => $halls
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
