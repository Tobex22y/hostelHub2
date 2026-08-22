<?php
class DBSessionHandler implements SessionHandlerInterface {
    private $pdo;

    public function open($savePath, $sessionName): bool {
        $this->pdo = DB::get();
        return true;
    }

    public function close(): bool {
        return true;
    }

    public function read($id): string|false {
        $stmt = $this->pdo->prepare("SELECT data FROM sessions WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['data'] : '';
    }

    public function write($id, $data): bool {
        $stmt = $this->pdo->prepare("
            INSERT INTO sessions (id, data, last_access)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE data = VALUES(data), last_access = VALUES(last_access)
        ");
        return $stmt->execute([$id, $data, time()]);
    }

    public function destroy($id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function gc($max_lifetime): int|false {
        $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE last_access < ?");
        $stmt->execute([time() - $max_lifetime]);
        return $stmt->rowCount();
    }
}
