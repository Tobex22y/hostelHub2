<?php
session_start();

header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");

require_once "../config/db.php";

// ── Passive cleanup: expire stale reservations on every booking attempt ──────
// require_once ensures the file (and its guard) only loads once per request.
// No define() needed — cleanup_expired.php uses basename() to detect direct calls.
require_once __DIR__ . "/cleanup_expired.php";
expireStaleReservations();
// ─────────────────────────────────────────────────────────────────────────────

if (!isset($_SESSION["student_id"])) {
    echo json_encode([
        "success" => false,
        "message" => "Not logged in"
    ]);
    exit;
}

$student_id = $_SESSION["student_id"];

$data = json_decode(file_get_contents("php://input"), true);

$room_id = $data["room_id"] ?? null;
$bed_id  = $data["bed_id"]  ?? null;

if (!$room_id || !$bed_id) {
    echo json_encode([
        "success" => false,
        "message" => "Missing room or bed"
    ]);
    exit;
}

try {
    $pdo = DB::get();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ─── Open transaction FIRST — all checks happen inside with locks ────────
    $pdo->beginTransaction();

    // 🔒 1. Duplicate allocation check — FOR UPDATE prevents concurrent passes
    //       Expired allocations are already cleaned above, so this only blocks
    //       students with a genuine active/pending record.
    $check = $pdo->prepare("
        SELECT id, status FROM allocations
        WHERE student_id = ?
          AND status IN ('pending', 'confirmed', 'active')
        LIMIT 1
        FOR UPDATE
    ");
    $check->execute([$student_id]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $pdo->rollBack();
        echo json_encode([
            "success" => false,
            "message" => "Access Denied: You already have an active bedspace allocation or a pending reservation record."
        ]);
        exit;
    }

    // 🔒 2. Bed availability check — FOR UPDATE locks the row immediately
    //       No TOCTOU gap: check and lock happen in one atomic step
    $bedCheck = $pdo->prepare("
        SELECT id, is_occupied, status
        FROM bedspaces
        WHERE id = ?
        FOR UPDATE
    ");
    $bedCheck->execute([$bed_id]);
    $bed = $bedCheck->fetch(PDO::FETCH_ASSOC);

    if (!$bed) {
        $pdo->rollBack();
        echo json_encode([
            "success" => false,
            "message" => "Bed not found"
        ]);
        exit;
    }

    if ((int)$bed["is_occupied"] === 1 || $bed["status"] === "reserved") {
        $pdo->rollBack();
        echo json_encode([
            "success" => false,
            "message" => "Bed already taken or reserved"
        ]);
        exit;
    }

    // 🔒 3. Verify the bed belongs to the specified room (integrity guard)
    $roomCheck = $pdo->prepare("
        SELECT id FROM bedspaces
        WHERE id = ? AND room_id = ?
        LIMIT 1
    ");
    $roomCheck->execute([$bed_id, $room_id]);
    if (!$roomCheck->fetch()) {
        $pdo->rollBack();
        echo json_encode([
            "success" => false,
            "message" => "Bed does not belong to the specified room"
        ]);
        exit;
    }

    // 🔒 4. Mark bed as reserved — optimistic lock guard (AND is_occupied = 0)
    //       rowCount() === 0 means another request beat us here
    $upd = $pdo->prepare("
        UPDATE bedspaces
        SET is_occupied = 1,
            status = 'reserved'
        WHERE id = ?
          AND is_occupied = 0
          AND status != 'reserved'
    ");
    $upd->execute([$bed_id]);

    if ($upd->rowCount() === 0) {
        $pdo->rollBack();
        echo json_encode([
            "success" => false,
            "message" => "Bed was just taken by another student. Please choose another bed."
        ]);
        exit;
    }

    // 🔒 5. Insert allocation with a 2-minute expiry timestamp
    $pdo->prepare("
        INSERT INTO allocations
            (student_id, room_id, bed_id, status, reserved_until, created_at)
        VALUES
            (?, ?, ?, 'pending', DATE_ADD(NOW(), INTERVAL 2 MINUTE), NOW())
    ")->execute([$student_id, $room_id, $bed_id]);

    $allocation_id = $pdo->lastInsertId();

    $pdo->commit();

    echo json_encode([
        "success"       => true,
        "message"       => "Reservation successful",
        "allocation_id" => $allocation_id,
        "expires_in"    => 120
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}