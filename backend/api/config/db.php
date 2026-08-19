<?php

// Force the PHP script engine to run on West African Time (Nigeria)
date_default_timezone_set('Africa/Lagos');

// Base URL used to build QR codes and any absolute links (E-Pass, receipts,
// verification pages, etc.) so they work from other devices on the network,
// not just this PC. Update the IP if it changes (e.g. after a reboot/DHCP
// renewal) — run `ipconfig` and look for the IPv4 address.define('APP_BASE_URL', 'http://10.239.31.114/HostelHub-main/backend/api/student');
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

class DB {
    private static ?PDO $instance = null;

    private const HOST = '127.0.0.1';
    private const DBNAME = 'hostel_management';
    private const USER = 'root';
    private const PASS = '';

    public static function get(): PDO {
        if (self::$instance === null) {
            $dsn = "mysql:host=" . self::HOST . ";dbname=" . self::DBNAME . ";charset=utf8mb4";

            self::$instance = new PDO($dsn, self::USER, self::PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);

            // ⚡ FORCE the MySQL Database Server to match West African Time (Nigeria UTC+1)
            // This prevents your active bookings from instantly triggering the "Reservation is no longer valid" timeout message.
            self::$instance->exec("SET time_zone = '+01:00';");
        }

        return self::$instance;
    }
}