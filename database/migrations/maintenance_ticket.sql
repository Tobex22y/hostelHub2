-- ══════════════════════════════════════════════════════
--  HostelHub — Maintenance Ticketing System
--  Run this once in phpMyAdmin on your hostel_db
-- ══════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS maintenance_tickets (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id    INT UNSIGNED NOT NULL,
    category      ENUM('electrical','plumbing','furniture','cleaning','internet','security','other') NOT NULL DEFAULT 'other',
    title         VARCHAR(120) NOT NULL,
    description   TEXT NOT NULL,
    location      VARCHAR(120) NOT NULL COMMENT 'e.g. Hall C2 Room 4 Bed 2',
    priority      ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
    status        ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
    admin_note    TEXT NULL COMMENT 'Response/update from admin',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_student  (student_id),
    INDEX idx_status   (status),
    INDEX idx_priority (priority),
    INDEX idx_created  (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;