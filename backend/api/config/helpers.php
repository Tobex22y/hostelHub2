<?php

declare(strict_types=1);


function jsonResponse(bool $success, string $message, array $data = [], int $httpCode = 200): never
{
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}


function clientIp(): string
{
    foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            return trim(explode(',', $_SERVER[$key])[0]);
        }
    }
    return 'unknown';
}


function auditLog(
    PDO    $pdo,
    ?int   $studentId,
    string $action,
    string $entity,
    ?int   $entityId  = null,
    array  $details   = []
): void {
    $stmt = $pdo->prepare(
        'INSERT INTO audit_log (student_id, action, entity, entity_id, details, ip_address)
         VALUES (:sid, :act, :ent, :eid, :det, :ip)'
    );
    $stmt->execute([
        ':sid' => $studentId,
        ':act' => $action,
        ':ent' => $entity,
        ':eid' => $entityId,
        ':det' => empty($details) ? null : json_encode($details),
        ':ip'  => clientIp(),
    ]);
}


function generateReference(string $prefix = 'HMS'): string
{
    return $prefix . strtoupper(bin2hex(random_bytes(8)));
}


function requireFields(array $fields): array
{
    $missing = [];
    foreach ($fields as $f) {
        if (empty($_POST[$f])) {
            $missing[] = $f;
        }
    }
    return $missing;
}

function requireAdminSession(): int
{
    session_start();
    if (empty($_SESSION['admin_id'])) {
        jsonResponse(false, 'Admin login required.', [], 401);
    }
    return (int) $_SESSION['admin_id'];
}
