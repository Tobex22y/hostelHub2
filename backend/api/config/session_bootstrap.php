<?php
// ==============================================================
// session_bootstrap.php
// ==============================================================
// Include this ONE file at the very top of every backend/api/*.php
// endpoint that needs sessions and/or CORS. It replaces the need
// to repeat session_start(), the DB session handler wiring, and
// CORS headers in every single file.
//
// Usage (put this as literally the first line of the file, before
// any other code or output):
//
//     require_once __DIR__ . "/../config/session_bootstrap.php";
//
// After this include, $_SESSION is ready to use, CORS headers are
// set, Content-Type is application/json, and OPTIONS preflight
// requests have already been handled (script exits automatically).
// ==============================================================

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/session_handler.php";

// ── Cookie params (must be set BEFORE session_start()) ──────────
// SameSite=None + Secure=true is required for the cookie to survive
// on Render (HTTPS, and to support any future cross-subdomain setup).
// NOTE: this means the cookie will NOT be set on plain http://localhost.
// If you need local XAMPP testing over http, see the note at the
// bottom of this file.
$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    "lifetime" => 0,
    "path"     => "/",
    "httponly" => true,
    "samesite" => $isHttps ? "None" : "Lax",
    "secure"   => $isHttps,
]);

// ── Use the database-backed session handler instead of the
//    filesystem, so sessions survive Render container restarts
//    and work across all instances. ──────────────────────────────
$handler = new DBSessionHandler();
session_set_save_handler($handler, true);

session_start();

// ── CORS: reflect back whatever origin made the request ─────────
// Access-Control-Allow-Credentials requires an EXACT origin match,
// wildcards are not allowed when sending cookies cross-origin.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) {
    header("Access-Control-Allow-Origin: $origin");
}
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Vary: Origin");
header("Content-Type: application/json");

// ── Handle CORS preflight requests generically ───────────────────
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Optional debug logging (safe to leave in, or comment out) ────
// error_log(basename($_SERVER['SCRIPT_NAME']) . " session_id: " . session_id() .
//     " | student_id: " . ($_SESSION['student_id'] ?? 'NOT SET'));

// ==============================================================
// LOCAL DEV NOTE (XAMPP over http://localhost):
// Because "secure" => true above, this cookie will only be set by
// the browser over HTTPS. If you still test locally over plain
// HTTP and need sessions to work there too, replace the
// session_set_cookie_params() block above with:
//
//     $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
//     session_set_cookie_params([
//         "lifetime" => 0,
//         "path"     => "/",
//         "httponly" => true,
//         "samesite" => $isHttps ? "None" : "Lax",
//         "secure"   => $isHttps,
//     ]);
//
// This makes it adapt automatically: SameSite=None+Secure on Render,
// SameSite=Lax+non-secure on local XAMPP. Cross-origin credentialed
// requests just won't work locally in that mode, only same-origin.
// ==============================================================
