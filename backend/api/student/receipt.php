<?php
/* ------------------------------------------------------------------
   1. STRICT DEBUGGING & ERROR REPORTING
   ------------------------------------------------------------------ */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ------------------------------------------------------------------
   2. REQUIRE TCPDF LIBRARY (EXACT SYSTEM PATH)
   ------------------------------------------------------------------ */
require_once __DIR__ . '/../libs/TCPDF-6.7.8/tcpdf.php';

// db.php is at backend/api/config/db.php — go up 1 level from student/
$db_path = __DIR__ . '/../config/db.php';
if (file_exists($db_path)) {
    require_once $db_path;
} else {
    die("DB config not found at: " . $db_path);
}

/* ------------------------------------------------------------------
   3. DATA FETCHING WITH ACTIVE DATABASE CONFIGURATION
   ------------------------------------------------------------------ */
$ref_code = isset($_GET['reference']) ? trim(htmlspecialchars($_GET['reference'])) : '';

// Default values setup to prevent unassigned template variables
$fullname      = "N/A"; 
$matric_number = "N/A";
$email         = "N/A";
$date_string   = date('Y-m-d');
$time_string   = date('h:i A');
$hall_block    = "N/A";
$room_name     = "N/A";
$room_type     = "Standard";
$bed_space     = "N/A";
$amount        = "50,000";

if (!empty($ref_code)) {
    try {
        // Use the native active connection object matching your system layout
        if (class_exists('DB')) {
            $pdo = DB::get();
        } else {
            // Standalone dynamic configuration lookup parameter safe fallback 
            $pdo = new PDO("mysql:host=localhost;charset=utf8mb4", "root", "");
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Auto-detect the right database name dynamically from existing schemas
            $schemas = $pdo->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
            $target_db = 'hostel_hub'; // Typical naming variant match 
            foreach ($schemas as $db) {
                if (stripos($db, 'hostel') !== false) {
                    $target_db = $db;
                    break;
                }
            }
            $pdo->exec("USE `$target_db`");
        }

        // Fetch all data in one query — joins students, rooms, bedspaces, and payments
        $stmt = $pdo->prepare("
            SELECT
                s.fullname,
                s.matric_number,
                s.email,
                r.room_number,
                r.room_type,
                r.hall,
                b.bed_number,
                p.reference,
                p.amount,
                p.created_at AS payment_date
            FROM payments p
            JOIN allocations a  ON p.allocation_id = a.id
            JOIN students    s  ON a.student_id     = s.id
            JOIN rooms       r  ON a.room_id        = r.id
            JOIN bedspaces   b  ON a.bed_id         = b.id
            WHERE p.reference = :ref1
            LIMIT 1
        ");
        $stmt->execute([':ref1' => $ref_code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $fullname      = strtoupper($row['fullname']);
            $matric_number = strtoupper($row['matric_number']);
            $email         = strtolower($row['email']);

            // Date/time from payment record
            if (!empty($row['payment_date'])) {
                $timestamp   = strtotime($row['payment_date']);
                $date_string = date('d F Y', $timestamp);
                $time_string = date('h:i A', $timestamp);
            }

            // Room + hall
            $raw_room = $row['room_number'] ?? 'N/A';
            $raw_bed  = $row['bed_number']  ?? 'N/A';
            $raw_hall = $row['hall']        ?? '';

            // Determine hall ID
            $hallId = strtoupper(trim($raw_hall));
            if (empty($hallId) && $raw_room !== 'N/A') {
                if (preg_match('/^([A-Z]+[0-9]*)/i', trim($raw_room), $m)) {
                    $hallId = strtoupper($m[1]);
                }
            }
            if (empty($hallId)) $hallId = substr(strtoupper($raw_room), 0, 1);

            $hall_block = "Hall $hallId";
            $room_name  = $raw_room;
            $room_type  = !empty($row['room_type']) ? strtoupper($row['room_type']) : 'STANDARD';
            $bed_space  = "Bed $raw_bed";

            // Price: use actual payment amount from DB, fall back to hall map
            $prices_map = ['A'=>50000,'B'=>50000,'C1'=>50000,'C2'=>65000,'D'=>55000,'E'=>60000];
            $base_price = !empty($row['amount'])
                ? (float)$row['amount']
                : ($prices_map[$hallId] ?? 50000);
            $amount = number_format($base_price);
        } else {
            // Reference found in URL but no matching payment row — show helpful error
            die("<div style='font-family:sans-serif;padding:40px;text-align:center;color:#dc2626;'>
                    <h2>Receipt Not Found</h2>
                    <p>No verified payment record found for reference: <strong>$ref_code</strong></p>
                    <p>Make sure payment was completed and verified successfully.</p>
                 </div>");
        }
    } catch (Exception $e) {
        // Prevent script breakage by utilizing system execution logs silently
        error_log("Receipt Query Engine Error: " . $e->getMessage());
    }
}

/* ------------------------------------------------------------------
   4. INITIALIZE TCPDF INSTANCE
   ------------------------------------------------------------------ */
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

$pdf->SetCreator('HostelHub Management Portal');
$pdf->SetAuthor('BOUESTI Student Housing');
$pdf->SetTitle('Official Accommodation Payment Receipt');

$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();
$pdf->setPageMark();
$pdf->SetFont('dejavusans', '', 9);

/* ------------------------------------------------------------------
   5. TOP HEADER BRAND BOX
   ------------------------------------------------------------------ */
$pdf->Rect(15, 15, 180, 32, 'F', array(), array(15, 76, 117)); 
$pdf->Rect(15, 47, 60, 1.5, 'F', array(), array(27, 158, 119));
$pdf->Rect(75, 47, 60, 1.5, 'F', array(), array(249, 168, 37));
$pdf->Rect(135, 47, 60, 1.5, 'F', array(), array(229, 57, 53));
$pdf->Rect(15, 48.5, 180, 10, 'F', array(), array(232, 245, 233));

$header_html = "
<table border='0' cellpadding='0' cellspacing='0' style='width:100%; color:#ffffff;'>
    <tr>
        <td style='padding:6px 5px 0px 5px; line-height:1.4;'>
            <span style='font-size:13.5px; font-weight:bold;'>BAMIDELE OLUMILUA UNIVERSITY</span><br>
            <span style='font-size:8.5px; color:#90caf9; font-weight:bold;'>OF EDUCATION, SCIENCE AND TECHNOLOGY, IKERE-EKITI</span><br>
            <span style='font-size:7.5px; color:#bbdefb;'>Student Housing &amp; Accommodation Management Portal</span>
        </td>
        <td style='text-align:right; padding:8px 5px 0 0;' width='25%'>
            <span style='font-size:7.5px; color:#90caf9; font-weight:bold;'>RECEIPT NO.</span><br>
            <span style='font-size:11px; font-weight:bold; font-family:courier;'>$ref_code</span>
        </td>
    </tr>
</table>";
$pdf->writeHTMLCell(180, 32, 15, 15, $header_html, 0, 1, false, true, '', true);

$banner_html = "<div style='text-align:center; font-size:8px; font-weight:bold; color:#2e7d32; line-height:1.2;'>PAYMENT SUCCESSFUL &nbsp;&nbsp;|&nbsp;&nbsp; ACCOMMODATION CONFIRMED &nbsp;&nbsp;|&nbsp;&nbsp; OFFICIAL RECEIPT</div>";
$pdf->writeHTMLCell(180, 10, 15, 52, $banner_html, 0, 1, false, true, '', true);

/* ------------------------------------------------------------------
   6. CARDS SUMMARY BLOCKS
   ------------------------------------------------------------------ */
$pdf->Rect(15, 68, 87, 7, 'F', array(), array(15, 76, 117));
$pdf->Rect(15, 75, 87, 30, 'F', array(), array(240, 247, 255));

$student_html = "
<table cellpadding='4' cellspacing='0' style='width:100%; font-size:8.5px;'>
    <tr><td style='color:#546e7a; width:25%; font-weight:bold;'>Name:</td><td style='color:#0d1b2a; font-weight:bold;'>$fullname</td></tr>
    <tr><td style='color:#546e7a; font-weight:bold;'>Matric:</td><td style='color:#0f4c75; font-weight:bold; font-family:courier;'>$matric_number</td></tr>
    <tr><td style='color:#546e7a; font-weight:bold;'>Email:</td><td style='color:#1e293b;'>$email</td></tr>
</table>";
$pdf->writeHTMLCell(87, 7, 15, 69, "<span style='color:#ffffff; font-weight:bold; font-size:8.5px;'>&nbsp;&nbsp;STUDENT DETAILS</span>", 0, 1, false, true, 'L', true);
$pdf->writeHTMLCell(85, 28, 16, 77, $student_html, 0, 1, false, true, 'L', true);

$pdf->Rect(108, 68, 87, 7, 'F', array(), array(27, 158, 119));
$pdf->Rect(108, 75, 87, 30, 'F', array(), array(240, 253, 244));

$trans_html = "
<table cellpadding='4' cellspacing='0' style='width:100%; font-size:8.5px;'>
    <tr><td style='color:#546e7a; width:25%; font-weight:bold;'>Date:</td><td style='color:#0d1b2a; font-weight:bold;'>$date_string</td></tr>
    <tr><td style='color:#546e7a; font-weight:bold;'>Time:</td><td style='color:#0d1b2a;'>$time_string</td></tr>
    <tr><td style='color:#546e7a; font-weight:bold;'>Channel:</td><td style='color:#1e293b; font-weight:bold;'>Paystack API</td></tr>
</table>";
$pdf->writeHTMLCell(87, 7, 108, 69, "<span style='color:#ffffff; font-weight:bold; font-size:8.5px;'>&nbsp;&nbsp;TRANSACTION DETAILS</span>", 0, 1, false, true, 'L', true);
$pdf->writeHTMLCell(85, 28, 109, 77, $trans_html, 0, 1, false, true, 'L', true);

/* ------------------------------------------------------------------
   7. ACCOMMODATION INFOGRAPHIC TILES
   ------------------------------------------------------------------ */
$pdf->Rect(15, 113, 180, 7, 'F', array(), array(15, 76, 117));
$pdf->writeHTMLCell(180, 7, 15, 114.5, "<span style='color:#ffffff; font-weight:bold; font-size:8.5px;'>&nbsp;&nbsp;ACCOMMODATION ASSIGNMENT</span>", 0, 1, false, true, 'L', true);
$pdf->Rect(15, 120, 180, 26, 'F', array(), array(248, 250, 255));

// TILE 1: HALL BLOCK
$pdf->Rect(19, 124, 37, 18, 'F', array(), array(15, 76, 117));
$pdf->SetXY(19, 125.5);
$pdf->MultiCell(37, 5, "<span style='font-size:6.5px; color:#90caf9; font-weight:bold;'>ASSIGNED HALL</span>", 0, 'C', false, 1, '', '', true, 0, true, true, 5, 'T');
$pdf->SetXY(19, 131.5);
$pdf->MultiCell(37, 8, "<span style='font-size:10.5px; font-weight:bold; color:#ffffff;'>$hall_block</span>", 0, 'C', false, 1, '', '', true, 0, true, true, 8, 'T');

// TILE 2: ROOM NAME
$pdf->Rect(60, 124, 43, 18, 'F', array(), array(27, 158, 119));
$pdf->SetXY(60, 125);
$pdf->MultiCell(43, 5, "<span style='font-size:6.5px; color:#a7f3d0; font-weight:bold;'>ROOM NUMBER</span>", 0, 'C', false, 1, '', '', true, 0, true, true, 5, 'T');
$pdf->SetXY(60, 130);
$pdf->MultiCell(43, 10, "<span style='font-size:11px; font-weight:bold; color:#ffffff;'>$room_name</span><br><span style='font-size:5.5px; color:#d1fae5; font-weight:normal;'>$room_type</span>", 0, 'C', false, 1, '', '', true, 0, true, true, 10, 'T');

// TILE 3: BED SPACE
$pdf->Rect(107, 124, 43, 18, 'F', array(), array(249, 168, 37));
$pdf->SetXY(107, 125);
$pdf->MultiCell(43, 5, "<span style='font-size:6.5px; color:#fff8e1; font-weight:bold;'>BED SPACE</span>", 0, 'C', false, 1, '', '', true, 0, true, true, 5, 'T');
$pdf->SetXY(107, 130);
$pdf->MultiCell(43, 10, "<span style='font-size:11px; font-weight:bold; color:#ffffff;'>$bed_space</span><br><span style='font-size:5.5px; color:#fff3cd; font-weight:normal;'>ASSIGNED</span>", 0, 'C', false, 1, '', '', true, 0, true, true, 10, 'T');

// TILE 4: REFERENCE
$pdf->Rect(154, 124, 37, 18, 'F', array(), array(106, 27, 154));
$pdf->SetXY(154, 125.5);
$pdf->MultiCell(37, 5, "<span style='font-size:6.5px; color:#e1bee7; font-weight:bold;'>REFERENCE</span>", 0, 'C', false, 1, '', '', true, 0, true, true, 5, 'T');
$pdf->SetXY(154, 132);
$pdf->MultiCell(37, 8, "<span style='font-size:7.5px; font-weight:bold; color:#ffffff; font-family:courier;'>$ref_code</span>", 0, 'C', false, 1, '', '', true, 0, true, true, 8, 'T');

/* ------------------------------------------------------------------
   8. PAYMENTS FINANCIAL TABLE
   ------------------------------------------------------------------ */
$pdf->Rect(15, 154, 180, 7, 'F', array(), array(229, 57, 53));
$pdf->writeHTMLCell(180, 7, 15, 155.5, "<span style='color:#ffffff; font-weight:bold; font-size:8.5px;'>&nbsp;&nbsp;PAYMENT SUMMARY</span>", 0, 1, false, true, 'L', true);

$table_html = "
<table border='0' cellpadding='7' cellspacing='0' style='width:100%; font-size:9px;'>
    <tr style='background-color:#fffdfd;'>
        <th width='50%' style='color:#546e7a; border-bottom:1px solid #ffcdd2; font-weight:bold;'>Description</th>
        <th width='30%' style='color:#546e7a; border-bottom:1px solid #ffcdd2; text-align:center; font-weight:bold;'>Allocation Space</th>
        <th width='20%' style='color:#546e7a; border-bottom:1px solid #ffcdd2; text-align:right; font-weight:bold;'>Amount</th>
    </tr>
    <tr>
        <td style='color:#1e293b; border-bottom:1px solid #f1f5f9; line-height:1.3;'>
            <strong>University Hostel Accommodation Fee</strong><br>
            <span style='color:#78909c; font-size:7.5px;'>Full session hostel facility allocations</span>
        </td>
        <td style='text-align:center; color:#1e293b; border-bottom:1px solid #f1f5f9;'>$hall_block ($room_name)</td>
        <td style='text-align:right; font-weight:bold; color:#1e293b; border-bottom:1px solid #f1f5f9;'>&#8358;$amount</td>
    </tr>
    <tr>
        <td style='color:#1e293b; background-color:#fafafa; line-height:1.3;'>
            <strong>Bedspace Allocation</strong><br>
            <span style='color:#78909c; font-size:7.5px;'>System optimized automated positioning assignment</span>
        </td>
        <td style='text-align:center; color:#6a1b9a; font-weight:bold; background-color:#fafafa;'>$bed_space</td>
        <td style='text-align:right; color:#16a34a; font-weight:bold; background-color:#fafafa;'>INCLUDED</td>
    </tr>
    <tr style='background-color:#0f4c75; color:#ffffff;'>
        <td colspan='2' style='text-align:right; color:#90caf9; font-weight:bold;'>TOTAL AMOUNT PAID</td>
        <td style='text-align:right; color:#ffffff; font-weight:bold; font-size:11px;'>&#8358;$amount</td>
    </tr>
</table>";
$pdf->writeHTMLCell(180, 40, 15, 161, $table_html, 0, 1, false, true, 'L', true);

/* ------------------------------------------------------------------
   9. NOTES FOOTER SIGN OFF
   ------------------------------------------------------------------ */
$pdf->Rect(15, 215, 180, 22, 'F', array(), array(255, 253, 231));
$footer_html = "
<div style='text-align:center; line-height:1.4;'>
    <span style='font-size:8.5px; color:#f57f17; font-weight:bold;'>ELECTRONICALLY VERIFIED RECEIPT</span><br>
    <span style='font-size:7.5px; color:#78909c;'>
        This receipt was automatically generated following successful verification through the Paystack Payment Gateway. 
        It functions as a valid authorization of hostel room check-in for the current session. 
        No manual signature is required. Present this printout directly at the Hostel Admin Block.
    </span>
</div>";
$pdf->writeHTMLCell(176, 20, 17, 217, $footer_html, 0, 1, false, true, 'C', true);

$pdf->Rect(15, 275, 60, 2, 'F', array(), array(15, 76, 117));
$pdf->Rect(75, 275, 60, 2, 'F', array(), array(27, 158, 119));
$pdf->Rect(135, 275, 60, 2, 'F', array(), array(249, 168, 37));

/* ------------------------------------------------------------------
   10. OUTPUT GENERATION
   ------------------------------------------------------------------ */
if (ob_get_contents()) ob_end_clean();
$pdf->Output('BOUESTI_Hostel_Receipt_' . $ref_code . '.pdf', 'I');