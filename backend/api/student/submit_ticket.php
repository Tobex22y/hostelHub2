<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/session_handler.php";

$handler = new DBSessionHandler();
session_set_save_handler($handler, true);

session_start();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['student_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in"]);
    exit;
}

$student_id = (int)$_SESSION['student_id'];
$body       = json_decode(file_get_contents("php://input"), true);

$category    = trim($body['category']    ?? '');
$title       = trim($body['title']       ?? '');
$description = trim($body['description'] ?? '');
$location    = trim($body['location']    ?? '');
$priority    = trim($body['priority']    ?? 'medium');

// Validate
if (!$category || !$title || !$description || !$location) {
    echo json_encode(["success" => false, "message" => "All fields are required."]);
    exit;
}

$allowed_categories = ['electrical','plumbing','furniture','cleaning','internet','security','other'];
$allowed_priorities = ['low','medium','high','urgent'];

if (!in_array($category, $allowed_categories)) $category = 'other';
if (!in_array($priority, $allowed_priorities)) $priority = 'medium';

try {
    $pdo = DB::get();

    $stmt = $pdo->prepare("
        INSERT INTO maintenance_tickets
            (student_id, category, title, description, location, priority, status)
        VALUES (?, ?, ?, ?, ?, ?, 'open')
    ");
    $stmt->execute([$student_id, $category, $title, $description, $location, $priority]);

    $ticket_id = $pdo->lastInsertId();

    echo json_encode([
        "success"   => true,
        "message"   => "Ticket submitted successfully.",
        "ticket_id" => $ticket_id
    ]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
