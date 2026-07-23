-- Run once for the account-approval registration feature.
CREATE TABLE IF NOT EXISTS signup_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    requested_role_id INT NOT NULL,
    department_id INT NOT NULL,
    status ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME NULL,
    reviewed_by INT NULL,
    CONSTRAINT signup_requests_role_fk
        FOREIGN KEY (requested_role_id) REFERENCES roles(id),
    CONSTRAINT signup_requests_department_fk
        FOREIGN KEY (department_id) REFERENCES departments(id),
    CONSTRAINT signup_requests_reviewer_fk
        FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX signup_requests_pending_idx (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
