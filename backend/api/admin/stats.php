<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

// This line automatically finds the correct path to db_config.php
// It looks for it in the 'backend' folder
$configFile = $_SERVER['DOCUMENT_ROOT'] . '/HostelHub-main/backend/api/config/db.php';

if (!file_exists($configFile)) {
    // If the above doesn't work, try a different common path
    $configFile = __DIR__ . '/../config/db.php';
}

if (file_exists($configFile)) {
    require_once $configFile;
} else {
    echo json_encode(["success" => false, "message" => "Configuration file not found. Check path: " . $configFile]);
    exit;
}

// Bed statuses in the `bedspaces` table.
// ADJUST THESE if your enum values differ from 'available' / 'reserved' / 'occupied'.
const STATUS_AVAILABLE = 'available';
const STATUS_RESERVED  = 'reserved'; // held during the 15-min window / pending admin approval
const STATUS_OCCUPIED  = 'occupied';

// Hall display order used by the frontend chart
$HALL_ORDER = ['A', 'B', 'C1', 'C2', 'D', 'E'];

try {
    $pdo = DB::get();

    // Total Rooms
    $stmt = $pdo->query("SELECT COUNT(*) FROM rooms");
    $total_rooms = (int)($stmt->fetchColumn() ?: 0);

    // Total Beds (actual bed rows, not just capacity sum, so it always matches the breakdown below)
    $stmt = $pdo->query("SELECT COUNT(*) FROM bedspaces");
    $total_beds = (int)($stmt->fetchColumn() ?: 0);

    // Bed status counts (Occupied / Allocated-Pending / Available)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bedspaces WHERE status = ?");

    $stmt->execute([STATUS_OCCUPIED]);
    $occupied_beds = (int)($stmt->fetchColumn() ?: 0);

    $stmt->execute([STATUS_RESERVED]);
    $allocated_beds = (int)($stmt->fetchColumn() ?: 0);

    $stmt->execute([STATUS_AVAILABLE]);
    $available_beds = (int)($stmt->fetchColumn() ?: 0);

    // Occupied / Available Rooms (a room counts as "occupied" if it has at least one occupied bed)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT room_id) FROM bedspaces WHERE status = ?
    ");
    $stmt->execute([STATUS_OCCUPIED]);
    $occupied_rooms = (int)($stmt->fetchColumn() ?: 0);
    $available_rooms = max(0, $total_rooms - $occupied_rooms);

    // Per-hall breakdown for the Hall Distribution chart
    $stmt = $pdo->query("
        SELECT r.hall, b.status, COUNT(*) AS cnt
        FROM bedspaces b
        JOIN rooms r ON b.room_id = r.id
        GROUP BY r.hall, b.status
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Seed every hall with zeros so the chart always shows all 6 bars, even with no data yet
    $hallsMap = [];
    foreach ($HALL_ORDER as $h) {
        $hallsMap[$h] = ['hall' => $h, 'allocated' => 0, 'available' => 0, 'occupied' => 0];
    }

    foreach ($rows as $row) {
        $hall = $row['hall'];
        if (!isset($hallsMap[$hall])) {
            // Unexpected hall code in the DB that isn't in HALL_ORDER — still include it
            $hallsMap[$hall] = ['hall' => $hall, 'allocated' => 0, 'available' => 0, 'occupied' => 0];
        }
        $cnt = (int)$row['cnt'];
        if ($row['status'] === STATUS_RESERVED)      $hallsMap[$hall]['allocated'] = $cnt;
        elseif ($row['status'] === STATUS_AVAILABLE) $hallsMap[$hall]['available'] = $cnt;
        elseif ($row['status'] === STATUS_OCCUPIED)  $hallsMap[$hall]['occupied']  = $cnt;
    }

    $halls = array_values($hallsMap);

    echo json_encode([
        "success"         => true,
        "total_rooms"     => $total_rooms,
        "total_beds"      => $total_beds,
        "occupied_beds"   => $occupied_beds,
        "allocated_beds"  => $allocated_beds,
        "available_beds"  => $available_beds,
        "occupied_rooms"  => $occupied_rooms,
        "available_rooms" => $available_rooms,
        "halls"           => $halls,
    ]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}