<?php
session_start();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");
require_once "../config/db.php";

$pdo = DB::get();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (!isset($_SESSION["student_id"])) {
    echo json_encode(["success" => false, "message" => "Not logged in"]);
    exit;
}

$student_id = $_SESSION["student_id"];
$data       = json_decode(file_get_contents("php://input"), true);
$reference     = $data["reference"]     ?? null;
$allocation_id = $data["allocation_id"] ?? null;

if (!$reference || !$allocation_id) {
    echo json_encode(["success" => false, "message" => "Missing reference or allocation_id"]);
    exit;
}

// 1. Confirm allocation belongs to this student
$allocStmt = $pdo->prepare("
    SELECT id, bed_id, status FROM allocations
    WHERE id = ? AND student_id = ?
    LIMIT 1
");
$allocStmt->execute([$allocation_id, $student_id]);
$alloc = $allocStmt->fetch(PDO::FETCH_ASSOC);

if (!$alloc) {
    echo json_encode(["success" => false, "message" => "Reservation not found"]);
    exit;
}

if ($alloc["status"] === "paid") {
    echo json_encode(["success" => true, "message" => "Already paid"]);
    exit;
}

$bed_id = $alloc["bed_id"];

// 2. Verify with Paystack
$secretKey = "sk_test_2c821a32f556645dbfcdb1727bcaf3ffcad4e683";
$ch = curl_init("https://api.paystack.co/transaction/verify/" . rawurlencode($reference));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $secretKey"]);
$response = json_decode(curl_exec($ch), true);
curl_close($ch);

$paymentSuccess = isset($response["data"]["status"]) && $response["data"]["status"] === "success";

try {
    $pdo->beginTransaction();

    if ($paymentSuccess) {
        $amount = $response["data"]["amount"] / 100;

        // Ensure this status stays 'paid' to match what your app.js layout checks for!
        $pdo->prepare("
            UPDATE allocations SET status = 'paid', payment_reference = ?
            WHERE id = ?
        ")->execute([$reference, $allocation_id]);

        $pdo->prepare("
            UPDATE bedspaces SET is_occupied = 1, status = 'occupied'
            WHERE id = ?
        ")->execute([$bed_id]);

        $pdo->prepare("
            INSERT INTO payments (allocation_id, reference, amount, status, created_at)
            VALUES (?, ?, ?, 'success', NOW())
        ")->execute([$allocation_id, $reference, $amount]);

        $pdo->commit();

        echo json_encode([
            "success" => true,
            "message" => "Payment confirmed. Reservation complete!"
        ]);
        exit;

    } else {
        $pdo->prepare("
            UPDATE bedspaces SET status = 'available', reserved_at = NULL
            WHERE id = ?
        ")->execute([$bed_id]);

        $pdo->prepare("
            DELETE FROM allocations WHERE id = ?
        ")->execute([$allocation_id]);

        $pdo->commit();

        echo json_encode([
            "success" => false,
            "message" => "Payment failed. Your bed has been released."
        ]);
        exit;
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
    exit;
}