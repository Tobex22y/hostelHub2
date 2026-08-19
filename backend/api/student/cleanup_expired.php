<?php
/**
 * cleanup_expired.php
 * ───────────────────────────────────────────────────────────────
 * Releases beds whose 2-minute reservation window has passed
 * without a completed payment.
 *
 * Run this file in two ways:
 *   1. Via cron / Windows Task Scheduler (recommended)
 *   2. Triggered automatically on every reserve.php request
 *      (passive cleanup — included via require_once, guard is
 *       function_exists() so no define() conflict)
 * ───────────────────────────────────────────────────────────────
 */

require_once __DIR__ . "/../config/db.php";

/**
 * Expire all pending allocations whose reserved_until has passed.
 *
 * Returns an array with:
 *   - beds_released  : how many beds were freed
 *   - allocs_expired : how many allocation rows were marked expired
 *   - errors         : any error message (null = success)
 */
if (!function_exists('expireStaleReservations')) {
    function expireStaleReservations(): array
    {
        try {
            $pdo = DB::get();
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $pdo->beginTransaction();

            // ── Step 1: Free beds linked to expired pending allocations ──────
            // JOIN ensures we only touch beds that actually have an expired
            // pending allocation — never touches confirmed/occupied beds.
            $freeStmt = $pdo->prepare("
                UPDATE bedspaces b
                JOIN   allocations a ON a.bed_id = b.id
                SET    b.is_occupied = 0,
                       b.status      = 'available'
                WHERE  a.status        = 'pending'
                  AND  a.reserved_until < NOW()
            ");
            $freeStmt->execute();
            $bedsReleased = $freeStmt->rowCount();

            // ── Step 2: Mark those allocations as expired ────────────────────
            $expireStmt = $pdo->prepare("
                UPDATE allocations
                SET    status = 'expired'
                WHERE  status        = 'pending'
                  AND  reserved_until < NOW()
            ");
            $expireStmt->execute();
            $allocsExpired = $expireStmt->rowCount();

            $pdo->commit();

            return [
                "beds_released"  => $bedsReleased,
                "allocs_expired" => $allocsExpired,
                "errors"         => null,
            ];

        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return [
                "beds_released"  => 0,
                "allocs_expired" => 0,
                "errors"         => $e->getMessage(),
            ];
        }
    }
}

// ── CLI / direct-call entry point ────────────────────────────────────────────
// When included by reserve.php, require_once prevents this block running twice.
// When called standalone (cron or browser), it runs normally.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $result = expireStaleReservations();

    if (PHP_SAPI === "cli") {
        if ($result["errors"]) {
            echo "[CLEANUP ERROR] " . $result["errors"] . PHP_EOL;
            exit(1);
        }
        echo "[CLEANUP OK] "
            . "Beds released: {$result['beds_released']}, "
            . "Allocations expired: {$result['allocs_expired']}"
            . PHP_EOL;
        exit(0);
    }

    // Browser direct call — return JSON
    header("Content-Type: application/json");
    echo json_encode([
        "success"        => $result["errors"] === null,
        "beds_released"  => $result["beds_released"],
        "allocs_expired" => $result["allocs_expired"],
        "message"        => $result["errors"] ?? "Cleanup complete",
    ]);
}