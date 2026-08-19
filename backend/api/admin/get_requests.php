<?php
session_start();

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");

require_once "../config/db.php";

try {
    $pdo = DB::get();

    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            a.status,
            a.created_at,
            s.fullname,
            s.email,
            r.room_number,
            b.bed_number
        FROM allocations a
        JOIN students s ON a.student_id = s.id
        JOIN rooms r ON a.room_id = r.id
        JOIN bedspaces b ON a.bed_id = b.id
        WHERE a.status = 'pending'
        ORDER BY a.created_at DESC
    ");

    $stmt->execute();

    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "requests" => $requests
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}