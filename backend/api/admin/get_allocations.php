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

// Optional: Put your admin role validation checks here if applicable

try {
    $pdo = DB::get();
    
    // Joint query to capture Student details alongside Room assignment information
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            a.status,
            s.fullname,
            s.matric_number,
            r.room_number,
            r.hall,
            b.bed_number
        FROM allocations a
        JOIN students s ON a.student_id = s.id
        JOIN rooms r ON a.room_id = r.id
        JOIN bedspaces b ON a.bed_id = b.id
        ORDER BY a.id DESC
    ");
    $stmt->execute();
    $allocations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "allocations" => $allocations
    ]);

} catch (PDOException $e) {
    echo json_encode([
        "success" => false, 
        "message" => "Database breakdown: " . $e->getMessage()
    ]);
}
