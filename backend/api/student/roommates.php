<?php
/**
 * backend/api/student/roommates.php
 *
 * Returns everyone currently allocated (paid/active/approved) to the same
 * room as the logged-in student, along with the bed each person holds.
 *
 * ✅ Confirmed against your actual backend/config/db.php:
 *   - Connection is a static class: DB::get() returns the PDO instance
 *     (not a bare $pdo variable like earlier assumed).
 *   - DB name: hostel_management, via 127.0.0.1.
 *
 * ⚠️ Still worth double-checking:
 *   1. The session key that holds the logged-in student's id. I've used
 *      $_SESSION['student_id'] to match the pattern your other student/*.php
 *      endpoints appear to use. If your login.php sets a different key,
 *      update AUTH_SESSION_KEY below.
 */

session_start();
header('Content-Type: application/json');

// ── CORS (mirror whatever dynamic-origin logic your other student endpoints use) ──
// If you already have a shared cors.php / bootstrap.php that the other
// student endpoints include, replace this block with that include instead.
$allowedOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($allowedOrigin) {
    header("Access-Control-Allow-Origin: $allowedOrigin");
    header('Access-Control-Allow-Credentials: true');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

const AUTH_SESSION_KEY = 'student_id'; // ← adjust if your session key differs

require_once dirname(__DIR__) . '/config/db.php';

$pdo = DB::get();

if (empty($_SESSION[AUTH_SESSION_KEY])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

$studentId = $_SESSION[AUTH_SESSION_KEY];

try {
    // 1. Find the current student's active/paid allocation and its room_id
    $stmt = $pdo->prepare("
        SELECT a.id, a.room_id, a.bed_id, a.status
        FROM allocations a
        WHERE a.student_id = :student_id
          AND a.status IN ('paid', 'active', 'approved')
        ORDER BY a.id DESC
        LIMIT 1
    ");
    $stmt->execute(['student_id' => $studentId]);
    $myAlloc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$myAlloc) {
        echo json_encode(['success' => false, 'message' => 'No active allocation found.']);
        exit;
    }

    $roomId = $myAlloc['room_id'];

    // 2. Pull everyone allocated to that same room (paid/active/approved only)
    $stmt = $pdo->prepare("
        SELECT
            s.id            AS student_id,
            s.fullname,
            s.matric_number,
            b.bed_number,
            a.status
        FROM allocations a
        JOIN students s ON s.id = a.student_id
        JOIN bedspaces b ON b.id = a.bed_id
        WHERE a.room_id = :room_id
          AND a.status IN ('paid', 'active', 'approved')
        ORDER BY b.bed_number ASC
    ");
    $stmt->execute(['room_id' => $roomId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $roommates = array_map(function ($row) use ($studentId) {
        return [
            'fullname'      => $row['fullname'],
            'matric_number' => $row['matric_number'],
            'bed_number'    => $row['bed_number'],
            'is_you'        => ((int)$row['student_id'] === (int)$studentId),
        ];
    }, $rows);

    echo json_encode([
        'success'    => true,
        'room_id'    => $roomId,
        'roommates'  => $roommates,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}