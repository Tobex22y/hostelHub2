<?php
/**
 * config/secrets.php
 *
 * SMTP + third-party API credentials for HostelHub.
 *
 * ⚠️ DO NOT commit this file to version control.
 * Add it to .gitignore:
 *     config/secrets.php
 *
 * Place this file at: <project-root>/config/secrets.php
 * (i.e. next to db.php, since update_ticket.php loads it via
 *  __DIR__ . '/../config/secrets.php')
 */

// ---- SMTP (email) ----
define('SMTP_HOST', 'smtp.gmail.com');       // or your provider's SMTP host
define('SMTP_PORT', 465);
define('SMTP_USER', 'tobiosuntoki@gmail.com');
define('SMTP_PASS', 'gseu dhnm dtsr gquw'); // Gmail: use an App Password, not your login password
define('SMTP_FROM', 'tobiosuntoki@gmail.com');
define('SMTP_FROM_NAME', 'HostelHub');

// ---- Termii (SMS) ----
define('TERMII_API_KEY', 'tlv_IifSiSfgaKT5z6F1xINPoY9u9qowkHn6sJSLfQw-BSw');