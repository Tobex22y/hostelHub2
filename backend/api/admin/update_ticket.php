<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/session_handler.php";

$handler = new DBSessionHandler();
session_set_save_handler($handler, true);

session_start();

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#', $origin)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header("Access-Control-Allow-Credentials: true");
    header("Vary: Origin");
}
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once __DIR__ . '/../config/db.php';

/**
 * Strip CR/LF (and other control chars) from a string before it's used
 * in an email subject or header, to prevent header-injection attacks
 * via fields that ultimately come from user-editable data (ticket title,
 * student name, admin note, etc.).
 */
function sanitizeHeaderValue(string $value): string {
    return trim(preg_replace('/[\r\n\x00-\x1F]+/', ' ', $value));
}

// Verify Admin Session
if (empty($_SESSION['admin_logged_in'])) {
    echo json_encode(["success" => false, "message" => "Unauthorized access."]);
    exit;
}

$input      = json_decode(file_get_contents("php://input"), true);
$ticket_id  = intval($input['ticket_id'] ?? 0);
$status     = trim($input['status'] ?? 'open');
$admin_note = trim($input['admin_note'] ?? '');

$allowedStatuses = ['open', 'in_progress', 'resolved', 'closed'];
if (!in_array($status, $allowedStatuses, true)) {
    $status = 'open';
}

if (!$ticket_id) {
    echo json_encode(["success" => false, "message" => "Invalid ticket ID."]);
    exit;
}

try {
    $pdo = DB::get();

    // 1. Fetch the ticket together with the student's contact details
    $stmt = $pdo->prepare("
        SELECT t.id, t.title, t.status, s.email, s.phone, s.fullname
        FROM maintenance_tickets t
        JOIN students s ON t.student_id = s.id
        WHERE t.id = ?
    ");
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        echo json_encode(["success" => false, "message" => "Ticket not found."]);
        exit;
    }

    // 2. Update status + admin note
    $updateStmt = $pdo->prepare("
        UPDATE maintenance_tickets
        SET status = ?, admin_note = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $updateStmt->execute([$status, $admin_note, $ticket_id]);

    $statusLabels = [
        'open'        => 'Open',
        'in_progress' => 'In Progress',
        'resolved'    => 'Resolved',
        'closed'      => 'Closed',
    ];
    $formattedStatus = $statusLabels[$status] ?? ucfirst($status);

    $studentEmail = trim($ticket['email'] ?? '');
    $studentPhone = $ticket['phone'] ?? '';
    $studentName  = sanitizeHeaderValue($ticket['fullname'] ?? 'Student');
    $ticketTitle  = sanitizeHeaderValue($ticket['title'] ?? '');

    // notif now carries per-channel success flags AND the reason for failure,
    // so the admin dashboard (or your error log) can actually tell you why
    // a notification didn't go out instead of just "false".
    $notif = [
        "email" => ["sent" => false, "error" => null],
        "sms"   => ["sent" => false, "error" => null],
    ];

    // 3. Email notification
    if (!empty($studentEmail)) {
        $subject = "HostelHub Ticket Update: [#{$ticket_id}] {$ticketTitle}";
        $message  = "Hello {$studentName},\n\n";
        $message .= "Your maintenance ticket \"{$ticketTitle}\" has been updated.\n";
        $message .= "New Status: {$formattedStatus}\n";
        if (!empty($admin_note)) {
            $message .= "Admin Response: {$admin_note}\n";
        }
        $message .= "\nLog in to your HostelHub portal to view more details.\n\nRegards,\nHostelHub Admin Team";

        $emailResult = sendEmailNotification($studentEmail, $subject, $message);
        $notif['email']['sent']  = $emailResult['sent'];
        $notif['email']['error'] = $emailResult['error'];
    } else {
        $notif['email']['error'] = 'Student has no email on file.';
    }

    // 4. SMS notification
    if (!empty($studentPhone)) {
        $smsMessage = "HostelHub: Ticket '#{$ticketTitle}' status is now {$formattedStatus}.";
        if (!empty($admin_note)) {
            $smsMessage .= " Note: " . mb_substr($admin_note, 0, 60);
        }
        $smsResult = sendSMSNotification($studentPhone, $smsMessage);
        $notif['sms']['sent']  = $smsResult['sent'];
        $notif['sms']['error'] = $smsResult['error'];
    } else {
        $notif['sms']['error'] = 'Student has no phone number on file.';
    }

    echo json_encode([
        "success"       => true,
        "message"       => "Ticket updated successfully.",
        "notifications" => $notif,
    ]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}

/**
 * Send an email notification.
 *
 * Uses PHPMailer + SMTP if it's installed (recommended — see notes below),
 * and falls back to PHP's built-in mail() otherwise. Either way, this
 * returns exactly what happened instead of swallowing it with @.
 *
 * To enable SMTP (strongly recommended, since plain mail() fails silently
 * on most XAMPP installs and gets flagged as spam even when it "works"):
 *   1. composer require phpmailer/phpmailer
 *   2. In config/secrets.php define:
 *        define('SMTP_HOST', 'smtp.gmail.com');
 *        define('SMTP_PORT', 587);
 *        define('SMTP_USER', 'you@gmail.com');
 *        define('SMTP_PASS', 'your_16_char_app_password');
 *        define('SMTP_FROM', 'you@gmail.com');
 *        define('SMTP_FROM_NAME', 'HostelHub');
 *
 * Without those constants defined, this quietly uses mail() instead.
 */
function sendEmailNotification($toEmail, $subject, $message) {
    $toEmail = trim($toEmail);
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        $errorText = "Invalid recipient email address: '{$toEmail}'";
        error_log($errorText);
        return ["sent" => false, "error" => $errorText];
    }

    $secretsFile = __DIR__ . '/../config/secrets.php';
    if (file_exists($secretsFile)) {
        require_once $secretsFile;
    };
    $vendorAutoload = __DIR__ . '/../vendor/autoload.php';
    $smtpHost = defined('SMTP_HOST') ? SMTP_HOST : getenv('SMTP_HOST');
    $smtpPort = defined('SMTP_PORT') ? SMTP_PORT : getenv('SMTP_PORT');
    $smtpUser = defined('SMTP_USER') ? SMTP_USER : getenv('SMTP_USER');
    $smtpPass = defined('SMTP_PASS') ? SMTP_PASS : getenv('SMTP_PASS');
    $smtpFrom = defined('SMTP_FROM') ? SMTP_FROM : getenv('SMTP_FROM');
    $smtpFromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : getenv('SMTP_FROM_NAME');
    $smtpSecure = defined('SMTP_SECURE') ? SMTP_SECURE : getenv('SMTP_SECURE');
    $canUseSMTP = !empty($smtpHost) && !empty($smtpUser) && !empty($smtpPass)
        && file_exists($vendorAutoload);

    if ($canUseSMTP) {
        require_once __DIR__ . '/../vendor/autoload.php';

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $smtpPass = preg_replace('/\s+/', '', (string) $smtpPass);
            $smtpPort = (int) ($smtpPort ?: 465);
            $useStartTls = strtolower(trim((string) $smtpSecure)) === 'tls' || $smtpPort === 587;

            $mail->isSMTP();
            $mail->Host       = $smtpHost;
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtpUser;
            $mail->Password   = $smtpPass;
            $mail->SMTPSecure = $useStartTls
                ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS
                : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = $smtpPort;
            if (getenv('SMTP_DEBUG') === '1') {
                $mail->SMTPDebug = 2;
                $mail->Debugoutput = 'error_log';
            }

            $fromEmail = $smtpFrom ?: $smtpUser;
            $fromName  = $smtpFromName ?: 'HostelHub';
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($toEmail);

            $mail->Subject = $subject;
            $mail->Body    = $message;

            $mail->send();
            return ["sent" => true, "error" => null];

        } catch (\Throwable $e) {
            $errorText = "PHPMailer error: " . $mail->ErrorInfo;
            if (empty($mail->ErrorInfo)) {
                $errorText .= $e->getMessage();
            }
            error_log($errorText);
            return ["sent" => false, "error" => $errorText];
        }
    }

    // Fallback: built-in mail(). Note this requires a working mail
    // transport (sendmail/postfix) configured in php.ini — a stock
    // XAMPP install usually does NOT have this set up, which is the
    // most common reason emails silently never arrive locally.
    $headers  = "From: no-reply@hostelhub.com\r\n";
    $headers .= "Reply-To: support@hostelhub.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    $sent = mail($toEmail, $subject, $message, $headers);

    if (!$sent) {
        $lastError = error_get_last();
        $errorText = "mail() failed."
            . (!empty($lastError['message']) ? " Last PHP error: {$lastError['message']}." : '')
            . " Most likely cause: no SMTP/sendmail transport configured in php.ini. "
            . "Consider setting SMTP_HOST/SMTP_USER/SMTP_PASS in config/secrets.php and installing PHPMailer.";
        error_log($errorText);
        return ["sent" => false, "error" => $errorText];
    }

    return ["sent" => true, "error" => null];
}

/**
 * Send an SMS via Termii (common for Nigeria/West Africa numbers).
 * Returns ["sent" => bool, "error" => string|null] so the caller can
 * actually see WHY a message failed, instead of a bare true/false.
 */
function sendSMSNotification($toPhone, $messageText) {
    // API key is loaded from config/secrets.php (not committed to version control).
    // Create that file yourself with: define('TERMII_API_KEY', 'your_key_here');
    $secretsFile = __DIR__ . '/../config/secrets.php';
    if (file_exists($secretsFile)) {
        require_once $secretsFile;
    }
    if (!defined('TERMII_API_KEY') || empty(TERMII_API_KEY)) {
        $errorText = "TERMII_API_KEY not configured in config/secrets.php";
        error_log("Termii SMS skipped: " . $errorText);
        return ["sent" => false, "error" => $errorText];
    }
    $apiKey = TERMII_API_KEY;

    // Normalize local format (0801...) to international (234801...)
    $formattedPhone = preg_replace('/^0/', '234', trim($toPhone));

    $payload = [
        "to"      => $formattedPhone,
        "from"    => "HostelHub",
        "sms"     => $messageText,
        "type"    => "plain",
        "api_key" => $apiKey,
        "channel" => "generic",
    ];

    $ch = curl_init("https://api.ng.termii.com/api/sms/send");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    // Always log the raw response while you're debugging this — comment
    // this line out once SMS delivery is confirmed working end-to-end.
    error_log("Termii SMS response (HTTP {$httpCode}): " . $response);

    if ($curlErr) {
        $errorText = "cURL error: " . $curlErr;
        error_log("Termii SMS error: " . $errorText);
        return ["sent" => false, "error" => $errorText];
    }

    $decoded = json_decode($response, true);

    // Termii returns HTTP 200 even for some failure cases (e.g. insufficient
    // balance, unregistered sender ID), so the HTTP code alone isn't enough —
    // check the body for an explicit message_id or error message.
    if ($httpCode >= 400) {
        $errorText = "Termii API returned HTTP {$httpCode}: " . $response;
        return ["sent" => false, "error" => $errorText];
    }

    if (!empty($decoded['message_id'])) {
        return ["sent" => true, "error" => null];
    }

    $errorText = "Termii did not return a message_id. Response: " . $response
        . " (Common causes: insufficient wallet balance, unregistered sender ID 'HostelHub', or invalid API key.)";
    return ["sent" => false, "error" => $errorText];
}
