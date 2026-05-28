CREATE TABLE admin_users (
    id           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    username     VARCHAR(64)      NOT NULL,
    password_hash VARCHAR(255)    NOT NULL,
    display_name VARCHAR(128)     NOT NULL,
    is_active    TINYINT(1)       NOT NULL DEFAULT 1,
    last_login_at DATETIME        NULL     DEFAULT NULL,
    created_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
