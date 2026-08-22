<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/session_handler.php";

$handler = new DBSessionHandler();
session_set_save_handler($handler, true);

session_start();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");

require_once __DIR__ . '/../config/db.php';

try {
    $pdo  = DB::get();
    $stmt = $pdo->query("
        SELECT
            t.id, t.category, t.title, t.description, t.location,
            t.priority, t.status, t.admin_note,
            t.created_at, t.updated_at,
            s.fullname, s.matric_number,
            r.room_number, r.hall, b.bed_number
        FROM maintenance_tickets t
        JOIN students  s ON t.student_id = s.id
        LEFT JOIN allocations a ON a.student_id = s.id AND a.status IN ('paid','active')
        LEFT JOIN rooms      r ON a.room_id = r.id
        LEFT JOIN bedspaces  b ON a.bed_id  = b.id
        ORDER BY
            FIELD(t.priority,'urgent','high','medium','low'),
            t.created_at DESC
    ");
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["success" => true, "tickets" => $tickets]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
