<?php
class DBSessionHandler implements SessionHandlerInterface {
    private $pdo;

    public function open($savePath, $sessionName): bool {
        try {
            $this->pdo = DB::get();
            return true;
        } catch (Exception $e) {
            error_log("SESSION open() failed: " . $e->getMessage());
            return false;
        }
    }

    public function close(): bool {
        return true;
    }

    public function read($id): string|false {
        try {
            $stmt = $this->pdo->prepare("SELECT data FROM sessions WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $row['data'] : '';
        } catch (Exception $e) {
            error_log("SESSION read() failed: " . $e->getMessage());
            return '';
        }
    }

    public function write($id, $data): bool {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO sessions (id, data, last_access)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE data = VALUES(data), last_access = VALUES(last_access)
            ");
            return $stmt->execute([$id, $data, time()]);
        } catch (Exception $e) {
            error_log("SESSION write() failed: " . $e->getMessage());
            return false;
        }
    }

    public function destroy($id): bool {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            error_log("SESSION destroy() failed: " . $e->getMessage());
            return false;
        }
    }

    public function gc($max_lifetime): int|false {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE last_access < ?");
            $stmt->execute([time() - $max_lifetime]);
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("SESSION gc() failed: " . $e->getMessage());
            return false;
        }
    }
}
