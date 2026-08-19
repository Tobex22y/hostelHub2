<?php
session_start();

// ─────────────────────────────────────────────────────────────
// CORS — was previously hardcoded to "http://localhost", which
// silently blocks every request from any other device (like a
// phone hitting this via your LAN IP, e.g. http://10.0.112.97).
// Because Access-Control-Allow-Credentials is true, browsers
// require an EXACT Origin match — no wildcards allowed — so this
// now reflects back the request's Origin if it's either localhost
// or a private LAN address, same pattern already used in
// update_ticket.php.
// ─────────────────────────────────────────────────────────────
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (preg_match('#^https?://(localhost|127\.0\.0\.1|10\.\d{1,3}\.\d{1,3}\.\d{1,3}|192\.168\.\d{1,3}\.\d{1,3}|172\.(1[6-9]|2\d|3[0-1])\.\d{1,3}\.\d{1,3})(:\d+)?$#', $origin)) {
    header("Access-Control-Allow-Origin: {$origin}");
} else {
    // Fallback so the endpoint doesn't fatal / silently 0-byte if Origin
    // is missing or unrecognized — adjust or remove if you want to lock
    // this down strictly once you know your final deployment host.
    header("Access-Control-Allow-Origin: http://localhost");
}
header("Access-Control-Allow-Credentials: true");
header("Vary: Origin");
header("Content-Type: application/json");

require_once __DIR__ . "/../config/db.php";

if (!isset($_SESSION["student_id"])) {
    echo json_encode(["success" => false, "message" => "Not logged in"]);
    exit;
}

$student_id = $_SESSION["student_id"];

try {
    $pdo = DB::get();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ── 1. Fetch student info (including gender) ──────────────────────────
    $stuStmt = $pdo->prepare("
        SELECT id, fullname, email, matric_number, gender, profile_image
        FROM students
        WHERE id = ?
        LIMIT 1
    ");
    $stuStmt->execute([$student_id]);
    $student = $stuStmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        echo json_encode(["success" => false, "message" => "Student not found"]);
        exit;
    }

    // ── 2. Define gender → halls mapping ─────────────────────────────────
    // Female halls: A, B, E  |  Male halls: C1, C2, D
    $studentGender = strtolower(trim($student['gender'] ?? 'male'));

    $femaleHalls = ['A', 'B', 'E'];
    $maleHalls   = ['C1', 'C2', 'D'];

    $allowedHalls = ($studentGender === 'female') ? $femaleHalls : $maleHalls;

    // ── 3. Fetch latest allocation for this student ───────────────────────
    $allocStmt = $pdo->prepare("
        SELECT a.id, a.status, a.payment_reference, a.created_at,
               r.room_number, r.hall, r.room_type, r.price, b.bed_number
        FROM allocations a
        JOIN rooms r ON a.room_id = r.id
        JOIN bedspaces b ON a.bed_id = b.id
        WHERE a.student_id = ?
        ORDER BY a.id DESC
        LIMIT 1
    ");
    $allocStmt->execute([$student_id]);
    $allocation = $allocStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    // ── 4. Build halls_summary — only halls matching this student's gender ─
    // For each allowed hall, fetch live stats from rooms/bedspaces.
    // If a hall has no rooms yet, it still appears with a "not_available" flag.

    $halls_summary = [];

    foreach ($allowedHalls as $hallId) {
        // Count rooms in this hall
        $roomStmt = $pdo->prepare("
            SELECT
                COUNT(r.id)                                          AS total_rooms,
                SUM(CASE WHEN bs_avail.cnt > 0 THEN 1 ELSE 0 END)  AS available_rooms,
                COALESCE(SUM(bs_all.total_beds), 0)                 AS total_beds,
                COALESCE(SUM(bs_all.available_beds), 0)             AS available_beds,
                COALESCE(SUM(bs_all.occupied_beds), 0)              AS occupied_beds
            FROM rooms r
            LEFT JOIN (
                SELECT room_id,
                       COUNT(*) AS total_beds,
                       SUM(CASE WHEN is_occupied = 0 AND (status IS NULL OR status = 'available') THEN 1 ELSE 0 END) AS available_beds,
                       SUM(CASE WHEN is_occupied = 1  OR  status = 'occupied' THEN 1 ELSE 0 END) AS occupied_beds
                FROM bedspaces
                GROUP BY room_id
            ) bs_all ON bs_all.room_id = r.id
            LEFT JOIN (
                SELECT room_id,
                       SUM(CASE WHEN is_occupied = 0 AND (status IS NULL OR status = 'available') THEN 1 ELSE 0 END) AS cnt
                FROM bedspaces
                GROUP BY room_id
            ) bs_avail ON bs_avail.room_id = r.id
            WHERE UPPER(TRIM(r.hall)) = ?
        ");
        $roomStmt->execute([strtoupper($hallId)]);
        $row = $roomStmt->fetch(PDO::FETCH_ASSOC);

        $halls_summary[] = [
            'id'              => $hallId,
            'gender'          => $studentGender,
            'total_rooms'     => (int)($row['total_rooms']     ?? 0),
            'available_rooms' => (int)($row['available_rooms'] ?? 0),
            'total_beds'      => (int)($row['total_beds']      ?? 0),
            'available_beds'  => (int)($row['available_beds']  ?? 0),
            'occupied_beds'   => (int)($row['occupied_beds']   ?? 0),
            // Flag for frontend: true = admin hasn't released any rooms yet
            'not_available'   => ((int)($row['total_rooms'] ?? 0) === 0),
        ];
    }

    echo json_encode([
        "success"       => true,
        "student"       => $student,
        "allocation"    => $allocation,
        "halls_summary" => $halls_summary,
    ]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}