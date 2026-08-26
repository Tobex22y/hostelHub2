<?php
/**
 * config/secrets.php
 *
 * SMTP + third-party API credentials for HostelHub.
 *
 * Values are read from environment variables so this file is safe to deploy.
 *
 * Place this file at: <project-root>/config/secrets.php
 * (i.e. next to db.php, since update_ticket.php loads it via
 *  __DIR__ . '/../config/secrets.php')
 */

// ---- SMTP (email) ----
if ($value = getenv('SMTP_HOST')) define('SMTP_HOST', $value);
if ($value = getenv('SMTP_PORT')) define('SMTP_PORT', $value);
if ($value = getenv('SMTP_USER')) define('SMTP_USER', $value);
if ($value = getenv('SMTP_PASS')) define('SMTP_PASS', $value);
if ($value = getenv('SMTP_FROM')) define('SMTP_FROM', $value);
if ($value = getenv('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', $value);
if ($value = getenv('SMTP_SECURE')) define('SMTP_SECURE', $value);

// ---- Termii (SMS) ----
if ($value = getenv('TERMII_API_KEY')) define('TERMII_API_KEY', $value);