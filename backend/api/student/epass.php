<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/session_handler.php";

$handler = new DBSessionHandler();
session_set_save_handler($handler, true);

session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
require_once "../config/db.php";

$pdo = DB::get();
$student_id = $_SESSION['student_id'] ?? null;

$data = null;
$fullname = '';
$matric = '';
$email = '';
$room = '';
$room_type = 'Standard Room';
$bed = '';
$hall = '';
$hall_block = '';
$ref_code = '';
$pay_date = '';
$session_year = date('Y') . '/' . (date('Y') + 1);
$expires = '';
$photo_src = '';
$qr_url = '';

if (!$student_id) {
    header("Location: /login.php");
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT
            s.fullname, s.matric_number, s.email, s.phone,
            s.profile_image AS passport_photo,
            r.room_number, r.room_type, r.hall,
            b.bed_number,
            p.reference, p.amount, p.created_at AS payment_date,
            a.id AS allocation_id
        FROM students s
        JOIN allocations a ON a.student_id = s.id
        JOIN rooms r ON a.room_id = r.id
        JOIN bedspaces b ON a.bed_id = b.id
        JOIN payments p ON p.allocation_id = a.id
        WHERE s.id = ?
        ORDER BY a.id DESC
        LIMIT 1
    ");
    $stmt->execute([$student_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        $no_allocation = true;
    }
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

if (!isset($no_allocation)) {
    $fullname      = htmlspecialchars(strtoupper($data['fullname']));
    $matric        = htmlspecialchars(strtoupper($data['matric_number']));
    $email         = htmlspecialchars($data['email']);
    $room          = htmlspecialchars($data['room_number']);
    $room_type     = htmlspecialchars($data['room_type'] ?? 'Standard Room');
    $bed           = htmlspecialchars($data['bed_number']);
    $hall          = htmlspecialchars($data['hall'] ?? 'Hall ' . substr($room, 0, 1));
    $hall_block    = substr($room, 0, 1);
    $ref_code      = htmlspecialchars($data['reference']);
    $pay_date      = date("d M Y", strtotime($data['payment_date']));
    $session_year  = date("Y") . "/" . (date("Y") + 1);
    $expires       = date("31 DEC Y", strtotime($data['payment_date']));

    // Photo: stored as base64 or file path in DB
    // If it's a file path, convert to base64 for inline embedding
    $photo_src = "";
    if (!empty($data['passport_photo'])) {
        $photo_path = $data['passport_photo'];
        if (file_exists($photo_path)) {
            $img_data  = base64_encode(file_get_contents($photo_path));
            $mime      = mime_content_type($photo_path);
            $photo_src = "data:$mime;base64,$img_data";
        } else {
            // Assume already stored as base64 string
            $photo_src = $data['passport_photo'];
        }
    }

    // QR code data — links to student allocation info page
    //
    // NOTE: This used to build the URL from $_SERVER['HTTP_HOST'], which
    // reflects whatever host you typed in the browser. Loading the page as
    // http://localhost/... baked "localhost" straight into the QR code,
    // so any other device scanning it (like a phone) couldn't resolve it.
    //
    // Fix: pull the base URL from a single config constant instead, so it's
    // consistent regardless of how the page was accessed. Define this once
    // in config/db.php (or a shared config/config.php), e.g.:
    //   define('APP_BASE_URL', 'http://192.168.1.42/hostel'); // your LAN IP
    if (!defined('APP_BASE_URL')) {
        $appBaseHost = '192.168.56.1';
        if (PHP_OS_FAMILY === 'Windows') {
            $ipConfig = [];
            @exec('ipconfig', $ipConfig);
            foreach ($ipConfig as $line) {
                if (preg_match('/IPv4 Address.*?:\s*([0-9.]+)/i', $line, $m)) {
                    $candidate = trim($m[1]);
                    if ($candidate !== '127.0.0.1' && $candidate !== '0.0.0.0') {
                        $appBaseHost = $candidate;
                        break;
                    }
                }
            }
        }
        define('APP_BASE_URL', 'http://' . $appBaseHost . '/HostelHub-main/backend/api/student');
    }
    $qr_url = APP_BASE_URL . "/verify_pass.php?ref=" . urlencode($ref_code) . "&matric=" . urlencode($data['matric_number']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>E-Hostel Pass — HostelHub BOUESTI</title>
<!-- QR Code library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>
    /* ═══════════════════════════════════════
       BOUESTI COLOR TOKENS
       Primary Green : #1a6b3c
       Dark Green    : #0d4a28
       Light Green   : #2d8a4e
       Primary Gold  : #c9a227
       Light Gold    : #f0c040
       Pale Gold bg  : #fdf9e8
       ═══════════════════════════════════════ */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        font-family: 'Segoe UI', system-ui, sans-serif;
        background: #f0f4f1;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }

    /* ── NAV ─────────────────────────────── */
    .nav {
        background: #0d4a28;
        padding: 0 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        height: 56px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.25);
        position: sticky; top: 0; z-index: 100;
    }
    .nav-brand { display: flex; align-items: center; gap: 10px; text-decoration: none; }
    .nav-brand-logo {
        width: 34px; height: 34px; border-radius: 50%;
        background: #c9a227; display: flex; align-items: center; justify-content: center;
        font-size: 14px; font-weight: 700; color: #0d4a28;
    }
    .nav-brand-text { font-size: 16px; font-weight: 700; color: #f0c040; letter-spacing: 0.3px; }
    .nav-links { display: flex; gap: 4px; }
    .nav-link {
        padding: 6px 14px; border-radius: 6px; font-size: 13px; color: #a8d5b5;
        text-decoration: none; transition: background 0.15s, color 0.15s;
    }
    .nav-link:hover { background: rgba(255,255,255,0.1); color: #fff; }
    .nav-link.active { background: #c9a227; color: #0d4a28; font-weight: 600; }
    .nav-avatar {
        width: 34px; height: 34px; border-radius: 50%;
        background: #2d8a4e; border: 2px solid #c9a227;
        display: flex; align-items: center; justify-content: center;
        font-size: 13px; color: #f0c040; font-weight: 700;
        overflow: hidden; cursor: pointer;
    }
    .nav-avatar img { width: 100%; height: 100%; object-fit: cover; }

    /* ── MAIN CONTENT ────────────────────── */
    .main {
        flex: 1;
        padding: 32px 16px 48px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
    }

    .page-label {
        font-size: 10px; font-weight: 700; letter-spacing: 1.8px;
        color: #4a7a5a; text-transform: uppercase;
        display: flex; align-items: center; gap: 8px;
    }
    .page-label::before, .page-label::after {
        content: ''; flex: 1; height: 1px; background: #b5d9c4; display: block; width: 40px;
    }

    /* ── PASS CARD ───────────────────────── */
    .pass-card {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 8px 32px rgba(13,74,40,0.14), 0 2px 8px rgba(0,0,0,0.06);
        width: 100%;
        max-width: 420px;
        overflow: hidden;
        border: 1.5px solid #b5d9c4;
    }

    /* CARD HEADER */
    .card-header {
        background: linear-gradient(135deg, #0d4a28 0%, #1a6b3c 60%, #2d8a4e 100%);
        padding: 16px 18px 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .card-header-title {
        font-size: 13px; font-weight: 800; letter-spacing: 1.2px;
        color: #f0c040; text-transform: uppercase;
    }
    .card-header-badge {
        background: rgba(201,162,39,0.2);
        border: 1px solid #c9a227;
        border-radius: 5px;
        padding: 3px 8px;
        font-size: 9px; font-weight: 700; color: #f0c040;
        letter-spacing: 0.8px;
    }

    /* GOLD STRIPE */
    .gold-stripe { height: 4px; background: linear-gradient(90deg, #c9a227, #f0c040, #c9a227); }

    /* STUDENT IDENTITY SECTION */
    .identity {
        padding: 20px 18px 16px;
        display: flex;
        align-items: flex-start;
        gap: 16px;
        border-bottom: 1px solid #eef5f1;
    }
    .photo-wrap {
        flex-shrink: 0;
        width: 72px; height: 72px;
        border-radius: 10px;
        overflow: hidden;
        border: 2.5px solid #1a6b3c;
        background: #e8f5ee;
        display: flex; align-items: center; justify-content: center;
    }
    .photo-wrap img { width: 100%; height: 100%; object-fit: cover; }
    .photo-placeholder { font-size: 28px; color: #a8d5b5; }
    .identity-info { flex: 1; min-width: 0; }
    .identity-name { font-size: 16px; font-weight: 800; color: #0d1f14; line-height: 1.2; margin-bottom: 3px; }
    .identity-matric { font-size: 11px; color: #4a7a5a; font-family: 'Courier New', monospace; font-weight: 600; letter-spacing: 0.5px; margin-bottom: 8px; }
    .status-badge {
        display: inline-flex; align-items: center; gap: 5px;
        background: #e8f5ee; border: 1px solid #b5d9c4;
        border-radius: 20px; padding: 3px 10px;
        font-size: 10px; font-weight: 700; color: #1a6b3c; letter-spacing: 0.5px;
    }
    .status-dot { width: 7px; height: 7px; border-radius: 50%; background: #2d8a4e; animation: pulse 1.8s infinite; }
    @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.4} }

    /* INFO GRID */
    .info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        padding: 14px 18px;
        border-bottom: 1px solid #eef5f1;
    }
    .info-tile {
        background: #f4fbf7;
        border: 1px solid #d0eadb;
        border-radius: 8px;
        padding: 10px 12px;
    }
    .info-tile.gold { background: #fdf9e8; border-color: #e8d88a; }
    .info-tile-label { font-size: 8.5px; font-weight: 700; letter-spacing: 1px; color: #4a7a5a; text-transform: uppercase; margin-bottom: 4px; }
    .info-tile.gold .info-tile-label { color: #7a6520; }
    .info-tile-value { font-size: 18px; font-weight: 800; color: #0d4a28; line-height: 1; }
    .info-tile.gold .info-tile-value { color: #c9a227; }
    .info-tile-sub { font-size: 9px; color: #6b7280; margin-top: 3px; }

    /* QR SECTION */
    .qr-section {
        padding: 18px;
        text-align: center;
        background: #f9fdf9;
        border: 1px dashed #b5d9c4;
        margin: 14px 18px;
        border-radius: 10px;
    }
    .qr-wrap {
        display: inline-block;
        background: #fff;
        padding: 10px;
        border-radius: 8px;
        border: 2px solid #0d4a28;
        margin-bottom: 8px;
    }
    #qr-code canvas, #qr-code img { display: block; }
    .qr-caption { font-size: 11px; color: #1a6b3c; font-weight: 600; margin-bottom: 2px; }
    .qr-expires { font-size: 9px; font-weight: 700; letter-spacing: 1px; color: #9ca3af; text-transform: uppercase; }

    /* CARD FOOTER META -->*/
    .card-footer {
        display: flex;
        justify-content: space-between;
        padding: 10px 18px;
        border-top: 1px solid #eef5f1;
        background: #f4fbf7;
    }
    .footer-meta { font-size: 8.5px; font-weight: 700; letter-spacing: 0.8px; color: #4a7a5a; text-transform: uppercase; }

    /* ── ACTION BUTTONS ──────────────────── */
    .actions {
        display: flex;
        gap: 12px;
        width: 100%;
        max-width: 420px;
        margin-top: 4px;
    }
    .btn {
        flex: 1; padding: 12px 16px; border-radius: 10px; border: none;
        font-size: 13px; font-weight: 700; cursor: pointer;
        display: flex; align-items: center; justify-content: center; gap: 8px;
        transition: transform 0.1s, box-shadow 0.15s;
        text-decoration: none;
    }
    .btn:active { transform: scale(0.97); }
    .btn-outline {
        background: #fff; border: 1.5px solid #1a6b3c; color: #1a6b3c;
        box-shadow: 0 2px 6px rgba(26,107,60,0.1);
    }
    .btn-outline:hover { background: #e8f5ee; }
    .btn-primary {
        background: linear-gradient(135deg, #1a6b3c, #2d8a4e);
        color: #fff;
        box-shadow: 0 4px 14px rgba(26,107,60,0.35);
    }
    .btn-primary:hover { box-shadow: 0 6px 18px rgba(26,107,60,0.45); }
    .btn-gold {
        background: linear-gradient(135deg, #c9a227, #f0c040);
        color: #0d4a28;
        box-shadow: 0 4px 14px rgba(201,162,39,0.35);
    }
    .btn-gold:hover { box-shadow: 0 6px 18px rgba(201,162,39,0.45); }

    /* DISCLAIMER -->*/
    .disclaimer {
        font-size: 10px; color: #6b7280; text-align: center;
        max-width: 380px; line-height: 1.5;
    }

    /* NO ALLOCATION STATE -->*/
    .empty-state {
        background: #fff; border-radius: 16px; padding: 40px 30px; text-align: center;
        border: 1.5px dashed #b5d9c4; max-width: 420px; width: 100%;
    }
    .empty-icon { font-size: 48px; margin-bottom: 12px; }
    .empty-title { font-size: 16px; font-weight: 700; color: #0d4a28; margin-bottom: 6px; }
    .empty-desc { font-size: 12px; color: #6b7280; line-height: 1.5; margin-bottom: 18px; }

    @media print {
        .nav, .actions, .disclaimer { display: none !important; }
        body { background: #fff; }
        .pass-card { box-shadow: none; border: 1px solid #ccc; }
    }
</style>
</head>
<body>

<!-- NAV -->
<nav class="nav">
    <a class="nav-brand" href="/dashboard.php">
        <div class="nav-brand-logo">B</div>
        <span class="nav-brand-text">HostelHub</span>
    </a>
    <div class="nav-links">
        <a href="/dashboard.php" class="nav-link">Dashboard</a>
        <a href="/my_application.php" class="nav-link">My Application</a>
        <a href="/epass.php" class="nav-link active">E-Pass</a>
        <a href="/support.php" class="nav-link">Support</a>
    </div>
    <div class="nav-avatar">
        <?php if (!empty($photo_src)): ?>
            <img src="<?= $photo_src ?>" alt="Photo">
        <?php else: ?>
            <?= strtoupper(substr($data['fullname'] ?? 'S', 0, 1)) ?>
        <?php endif; ?>
    </div>
</nav>

<!-- MAIN -->
<main class="main">

    <div class="page-label">Verified Resident Digital ID</div>

    <?php if (isset($no_allocation)): ?>
    <!-- NO ALLOCATION -->
    <div class="empty-state">
        <div class="empty-icon">🏠</div>
        <div class="empty-title">No Active Allocation Found</div>
        <div class="empty-desc">You don't have a confirmed hostel allocation yet. Complete your payment and bed space selection to get your E-Hostel Pass.</div>
        <a href="/hostel_allocation.php" class="btn btn-primary" style="display:inline-flex; max-width:220px; margin:0 auto;">Apply for Accommodation</a>
    </div>

    <?php else: ?>

    <!-- E-HOSTEL PASS CARD -->
    <div class="pass-card" id="pass-card">

        <!-- Header -->
        <div class="card-header">
            <div class="card-header-title">E-Hostel Pass</div>
            <div class="card-header-badge">BOUESTI <?= $session_year ?></div>
        </div>
        <div class="gold-stripe"></div>

        <!-- Identity -->
        <div class="identity">
            <div class="photo-wrap">
                <?php if (!empty($photo_src)): ?>
                    <img src="<?= $photo_src ?>" alt="Student Photo">
                <?php else: ?>
                    <span class="photo-placeholder">👤</span>
                <?php endif; ?>
            </div>
            <div class="identity-info">
                <div class="identity-name"><?= $fullname ?></div>
                <div class="identity-matric">MATRIC: <?= $matric ?></div>
                <span class="status-badge">
                    <span class="status-dot"></span>
                    ACTIVE
                </span>
            </div>
        </div>

        <!-- Info Grid -->
        <div class="info-grid">
            <div class="info-tile">
                <div class="info-tile-label">Hall</div>
                <div class="info-tile-value"><?= $hall ?></div>
                <div class="info-tile-sub">Hall of Residence</div>
            </div>
            <div class="info-tile gold">
                <div class="info-tile-label">Room No.</div>
                <div class="info-tile-value"><?= $room ?></div>
                <div class="info-tile-sub"><?= $room_type ?></div>
            </div>
            <div class="info-tile gold">
                <div class="info-tile-label">Bed Space</div>
                <div class="info-tile-value"><?= $bed ?></div>
                <div class="info-tile-sub">Assigned Space</div>
            </div>
            <div class="info-tile">
                <div class="info-tile-label">Block</div>
                <div class="info-tile-value"><?= $hall_block ?></div>
                <div class="info-tile-sub">Hall Block</div>
            </div>
        </div>

        <!-- QR Code -->
        <div class="qr-section">
            <div class="qr-wrap">
                <div id="qr-code"></div>
            </div>
            <div class="qr-caption">Scan for allocation verification</div>
            <div class="qr-expires">EXPIRES: <?= $expires ?></div>
        </div>

        <!-- Footer Meta -->
        <div class="card-footer">
            <span class="footer-meta">INSTITUTION CAMPUS</span>
            <span class="footer-meta">OFFICIAL ID</span>
        </div>
    </div>

    <!-- ACTION BUTTONS -->
    <div class="actions">
        <button class="btn btn-outline" onclick="window.print()">
            🖨 Save Offline
        </button>
        <a class="btn btn-gold" href="/hostel/receipt.php?reference=<?= urlencode($ref_code) ?>">
            🧾 View Receipt
        </a>
        <button class="btn btn-primary" onclick="sharePass()">
            ↗ Share Pass
        </button>
    </div>

    <p class="disclaimer">
        This digital pass is property of BOUESTI Hostel Management.<br>
        Always present physical ID upon request.
    </p>

    <?php endif; ?>
</main>

<?php if (!isset($no_allocation)): ?>
<script>
// Generate QR code pointing to the verify_pass page
const qrData = <?= json_encode($qr_url) ?>;
new QRCode(document.getElementById("qr-code"), {
    text: qrData,
    width: 140,
    height: 140,
    colorDark: "#0d4a28",
    colorLight: "#ffffff",
    correctLevel: QRCode.CorrectLevel.H
});

// Share API
function sharePass() {
    if (navigator.share) {
        navigator.share({
            title: 'BOUESTI E-Hostel Pass',
            text: 'My verified hostel pass — <?= $fullname ?> | <?= $matric ?>',
            url: window.location.href
        });
    } else {
        navigator.clipboard.writeText(window.location.href)
            .then(() => alert('Pass link copied to clipboard!'));
    }
}
</script>
<?php endif; ?>

</body>
</html>
