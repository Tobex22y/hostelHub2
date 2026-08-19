<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
/**
 * verify_pass.php
 * Opened when someone scans the QR code on the E-Hostel Pass.
 * Shows the student's allocation details — no login required (public verify).
 *
 * FIX APPLIED:
 * The original query joined a non-existent `halls` table via `r.hall_id = h.id`
 * and selected `h.hall_name`. Hall data actually lives directly on the
 * `rooms.hall` column (per schema), same as the fix already applied in
 * epass.php. This version selects r.hall directly and drops the bad join.
 */
require_once "../config/db.php";
$pdo = DB::get();

$ref = isset($_GET['ref']) ? trim(rawurldecode((string) $_GET['ref'])) : null;
$matric = isset($_GET['matric']) ? trim(rawurldecode((string) $_GET['matric'])) : null;

$data = null;
$error = null;
if ($ref && $matric) {
    try {
        $stmt = $pdo->prepare("
            SELECT s.fullname, s.matric_number, s.email, s.profile_image AS passport_photo,
                   r.room_number, r.room_type, r.hall,
                   b.bed_number,
                   p.reference, p.amount, p.status,
                   p.created_at AS payment_date
            FROM payments p
            JOIN allocations a ON p.allocation_id = a.id
            JOIN students s ON a.student_id = s.id
            JOIN rooms r ON a.room_id = r.id
            JOIN bedspaces b ON a.bed_id = b.id
            WHERE p.reference = :ref
              AND REPLACE(UPPER(TRIM(s.matric_number)), ' ', '') = REPLACE(UPPER(TRIM(:matric)), ' ', '')
            LIMIT 1
        ");
        $stmt->execute([
            ':ref' => $ref,
            ':matric' => $matric,
        ]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = "Verification error. Please try again.";
    }
}

$fullname   = $fullname   ?? '';
$matric_no  = $matric_no  ?? '';
$room       = $room       ?? '';
$room_type  = $room_type  ?? '';
$bed        = $bed        ?? '';
$hall       = $hall       ?? '';
$hall_block = $hall_block ?? '';
$ref_code   = $ref_code   ?? '';
$pay_date   = $pay_date   ?? '';
$amount     = $amount     ?? 0;
$photo_src  = $photo_src  ?? '';

$verified = !empty($data) && strtolower($data['status']) === 'success';

if ($verified) {
    $fullname   = htmlspecialchars(strtoupper($data['fullname']));
    $matric_no  = htmlspecialchars(strtoupper($data['matric_number']));
    $room       = htmlspecialchars($data['room_number']);
    $room_type  = htmlspecialchars($data['room_type'] ?? 'Standard');
    $bed        = htmlspecialchars($data['bed_number']);
    $hall       = htmlspecialchars($data['hall'] ?? 'N/A');
    $hall_block = substr($room, 0, 1);
    $ref_code   = htmlspecialchars($data['reference']);
    $pay_date   = date("d M Y", strtotime($data['payment_date']));
    $amount     = number_format($data['amount'], 2);
    $photo_src  = "";
    if (!empty($data['passport_photo'])) {
        $p = $data['passport_photo'];
        if (file_exists($p)) {
            $img_data  = base64_encode(file_get_contents($p));
            $mime      = mime_content_type($p);
            $photo_src = "data:$mime;base64,$img_data";
        } else {
            $photo_src = $p;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hostel Pass Verification — BOUESTI</title>
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: 'Segoe UI', system-ui, sans-serif;
        background: #f0f4f1;
        min-height: 100vh;
        display: flex; flex-direction: column; align-items: center;
        padding: 0 12px 40px;
    }
    .top-bar {
        width: 100%; background: #0d4a28;
        padding: 12px 20px; display: flex; align-items: center; gap: 10px;
        margin-bottom: 28px;
    }
    .top-bar-logo {
        width: 32px; height: 32px; border-radius: 50%;
        background: #c9a227; display: flex; align-items: center; justify-content: center;
        font-size: 14px; font-weight: 800; color: #0d4a28; flex-shrink: 0;
    }
    .top-bar-name { font-size: 15px; font-weight: 700; color: #f0c040; }
    .top-bar-sub  { font-size: 9.5px; color: #a8d5b5; margin-top: 1px; }
    .page-title {
        font-size: 10px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase;
        color: #4a7a5a; margin-bottom: 14px; text-align: center;
    }

    /* VERIFIED CARD */
    .verify-card {
        background: #fff; border-radius: 16px; overflow: hidden;
        width: 100%; max-width: 400px;
        box-shadow: 0 8px 28px rgba(13,74,40,0.13);
        border: 1.5px solid #b5d9c4;
    }
    .verify-header {
        padding: 14px 18px;
        display: flex; align-items: center; gap: 12px;
    }
    .verify-header.success { background: linear-gradient(135deg, #0d4a28, #1a6b3c); }
    .verify-header.fail    { background: linear-gradient(135deg, #7f1d1d, #b91c1c); }
    .verify-icon { font-size: 32px; }
    .verify-title { font-size: 15px; font-weight: 800; color: #f0c040; }
    .verify-sub   { font-size: 10px; color: #a8d5b5; margin-top: 2px; }

    .gold-stripe { height: 3px; background: linear-gradient(90deg, #c9a227, #f0c040, #c9a227); }

    /* STUDENT ROW */
    .student-row {
        display: flex; align-items: center; gap: 14px;
        padding: 16px 18px; border-bottom: 1px solid #eef5f1;
    }
    .student-photo {
        width: 60px; height: 60px; border-radius: 8px; overflow: hidden;
        border: 2px solid #1a6b3c; background: #e8f5ee; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center; font-size: 24px; color: #a8d5b5;
    }
    .student-photo img { width: 100%; height: 100%; object-fit: cover; }
    .student-name { font-size: 15px; font-weight: 800; color: #0d1f14; }
    .student-matric { font-size: 10.5px; color: #4a7a5a; font-family: monospace; font-weight: 600; margin-top: 3px; }

    /* DETAILS TABLE */
    .details { padding: 0 18px 14px; }
    .detail-row {
        display: flex; justify-content: space-between; align-items: center;
        padding: 9px 0; border-bottom: 1px solid #f0f5f1; font-size: 12px;
    }
    .detail-row:last-child { border-bottom: none; }
    .detail-label { color: #6b7280; font-weight: 500; }
    .detail-value { font-weight: 700; color: #0d1f14; }
    .detail-value.green  { color: #1a6b3c; }
    .detail-value.gold   { color: #c9a227; }
    .detail-value.mono   { font-family: monospace; font-size: 11px; }

    /* SEAL */
    .seal {
        margin: 12px 18px 16px;
        background: #e8f5ee; border: 1.5px solid #b5d9c4;
        border-radius: 8px; padding: 10px 14px; text-align: center;
    }
    .seal-title { font-size: 10px; font-weight: 700; letter-spacing: 1px; color: #0d4a28; text-transform: uppercase; margin-bottom: 3px; }
    .seal-body  { font-size: 9px; color: #4a7a5a; line-height: 1.5; }

    /* FAILED STATE */
    .fail-body { padding: 24px 20px; text-align: center; }
    .fail-body p { font-size: 13px; color: #6b7280; line-height: 1.6; margin-top: 10px; }
    .fail-badge {
        display: inline-block; background: #fee2e2; border: 1px solid #fca5a5;
        border-radius: 6px; padding: 6px 16px; font-size: 12px; font-weight: 700; color: #991b1b;
        margin-top: 14px;
    }
    .timestamp { font-size: 9px; color: #9ca3af; text-align: center; margin-top: 10px; }
</style>
</head>
<body>

<div class="top-bar">
    <div class="top-bar-logo">B</div>
    <div>
        <div class="top-bar-name">BOUESTI HostelHub</div>
        <div class="top-bar-sub">Hostel Pass Verification Portal</div>
    </div>
</div>

<p class="page-title">Resident Identity Verification</p>

<?php if ($verified): ?>
<div class="verify-card">
    <div class="verify-header success">
        <span class="verify-icon">✅</span>
        <div>
            <div class="verify-title">Pass Verified</div>
            <div class="verify-sub">This resident has a valid, confirmed hostel allocation</div>
        </div>
    </div>
    <div class="gold-stripe"></div>

    <div class="student-row">
        <div class="student-photo">
            <?php if (!empty($photo_src)): ?>
                <img src="<?= $photo_src ?>" alt="Student Photo">
            <?php else: ?>👤<?php endif; ?>
        </div>
        <div>
            <div class="student-name"><?= $fullname ?></div>
            <div class="student-matric"><?= $matric_no ?></div>
        </div>
    </div>

    <div class="details">
        <div class="detail-row">
            <span class="detail-label">Hall of Residence</span>
            <span class="detail-value green"><?= $hall ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Room Number</span>
            <span class="detail-value green"><?= $room ?> <small style="font-weight:500;color:#6b7280;">(<?= $room_type ?>)</small></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Hall Block</span>
            <span class="detail-value gold"><?= $hall_block ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Bed Space</span>
            <span class="detail-value gold"><?= $bed ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Amount Paid</span>
            <span class="detail-value">&#8358;<?= $amount ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Payment Reference</span>
            <span class="detail-value mono"><?= $ref_code ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Payment Date</span>
            <span class="detail-value"><?= $pay_date ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Status</span>
            <span class="detail-value green">✔ CONFIRMED &amp; ACTIVE</span>
        </div>
    </div>

    <div class="seal">
        <div class="seal-title">Official Verification Seal — BOUESTI HMS</div>
        <div class="seal-body">
            This pass was verified automatically by the BOUESTI Hostel Management System.
            The student listed above is an authorized resident for the current academic session.
        </div>
    </div>
</div>

<?php else: ?>
<div class="verify-card">
    <div class="verify-header fail">
        <span class="verify-icon">❌</span>
        <div>
            <div class="verify-title">Verification Failed</div>
            <div class="verify-sub">This pass could not be verified</div>
        </div>
    </div>
    <div style="height:3px; background:#ef4444;"></div>
    <div class="fail-body">
        <p>No valid allocation record was found for the scanned QR code. This pass may be:</p>
        <ul style="text-align:left;font-size:12px;color:#6b7280;margin:10px 0 0 20px;line-height:1.8;">
            <li>Expired or from a previous session</li>
            <li>Invalid or tampered</li>
            <li>Not yet confirmed by the system</li>
        </ul>
        <div class="fail-badge">⚠ NOT AUTHORIZED</div>
        <p style="margin-top:14px;font-size:11px;">
            Contact BOUESTI Hostel Admin Office for assistance.
        </p>
    </div>
</div>
<?php endif; ?>

<p class="timestamp">Verified at <?= date("D, d M Y h:i A") ?> WAT</p>

</body>
</html>