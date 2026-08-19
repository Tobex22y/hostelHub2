<?php
session_start();

header("Content-Type: application/json");

require_once "../config/db.php";

try {
    $pdo = DB::get();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Optional: check admin session
    // if (!isset($_SESSION["admin_id"])) { exit; }

    $pdo->beginTransaction();

    // 1. Clear dependent tables FIRST
    $pdo->exec("DELETE FROM payments");
    $pdo->exec("DELETE FROM allocations");

    // 2. Reset beds
    $pdo->exec("
        UPDATE bedspaces 
        SET is_occupied = 0
    ");

    // 3. Remove beds
    $pdo->exec("DELETE FROM bedspaces");

    // 4. Remove rooms last
    $pdo->exec("DELETE FROM rooms");

    // 5. Reset auto increment (optional but CLEAN)
    $pdo->exec("ALTER TABLE payments AUTO_INCREMENT = 1");
    $pdo->exec("ALTER TABLE allocations AUTO_INCREMENT = 1");
    $pdo->exec("ALTER TABLE bedspaces AUTO_INCREMENT = 1");
    $pdo->exec("ALTER TABLE rooms AUTO_INCREMENT = 1");

    $pdo->commit();

    echo json_encode([
        "success" => true,
        "message" => "Database reset successful"
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}