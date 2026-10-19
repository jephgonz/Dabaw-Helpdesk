CREATE TABLE IF NOT EXISTS helpdesk_tickets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    submitted_by BIGINT UNSIGNED NOT NULL,
    requester_name VARCHAR(120) NOT NULL,
    requester_email VARCHAR(190) NOT NULL,
    subject VARCHAR(160) NOT NULL,
    description TEXT NOT NULL,
    status ENUM('new', 'in_progress', 'resolved') NOT NULL DEFAULT 'new',
    resolved_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_helpdesk_tickets_status_created (status, created_at),
    KEY idx_helpdesk_tickets_resolved_at (resolved_at),
    KEY idx_helpdesk_tickets_submitted_by (submitted_by)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
