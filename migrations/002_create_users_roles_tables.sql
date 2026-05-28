-- Migration 002: Create users, roles, user_roles, mod_log, audit_log, config_change_queue
-- Part of F2: Role & Permission System
-- Applied after 001_create_admin_users_table.sql

CREATE TABLE users (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username         VARCHAR(64)  NOT NULL,
    email            VARCHAR(255) NOT NULL,
    password_hash    VARCHAR(255) NOT NULL,
    display_name     VARCHAR(128) NOT NULL,
    dmr_id           INT UNSIGNED NULL,
    moderation_state ENUM('active','suspended','banned','muted_on_network')
                                  NOT NULL DEFAULT 'active',
    mute_expires_at  DATETIME     NULL,
    tier             ENUM('free','gold','premium')
                                  NOT NULL DEFAULT 'free',
    invite_count     INT UNSIGNED NOT NULL DEFAULT 0,
    last_login_at    DATETIME     NULL,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                           ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_username (username),
    UNIQUE KEY uq_email    (email),
    UNIQUE KEY uq_dmr_id   (dmr_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE roles (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name           VARCHAR(64)  NOT NULL,
    display_name   VARCHAR(128) NOT NULL,
    description    TEXT         NULL,
    is_system_role TINYINT(1)   NOT NULL DEFAULT 0,
    sort_order     INT UNSIGNED NOT NULL DEFAULT 0,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_roles (
    user_id             INT UNSIGNED NOT NULL,
    role_id             INT UNSIGNED NOT NULL,
    assigned_by_user_id INT UNSIGNED NOT NULL,
    assigned_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_id),
    CONSTRAINT fk_ur_user     FOREIGN KEY (user_id)             REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ur_role     FOREIGN KEY (role_id)             REFERENCES roles(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ur_assigner FOREIGN KEY (assigned_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mod_log (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_user_id  INT UNSIGNED NOT NULL,
    target_user_id INT UNSIGNED NOT NULL,
    action         VARCHAR(64)  NOT NULL,
    reason         TEXT         NOT NULL,
    duration_hours SMALLINT UNSIGNED NULL,
    expires_at     DATETIME     NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_ml_actor  FOREIGN KEY (actor_user_id)  REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ml_target FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_log (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_user_id INT UNSIGNED NOT NULL,
    action_type   VARCHAR(64)  NOT NULL,
    target_type   VARCHAR(64)  NOT NULL,
    target_id     INT UNSIGNED NULL,
    detail_json   JSON         NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_al_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE config_change_queue (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    triggered_by_user_id INT UNSIGNED NOT NULL,
    change_type          VARCHAR(64)  NOT NULL,
    details_json         JSON         NULL,
    created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    applied_at           DATETIME     NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_ccq_user FOREIGN KEY (triggered_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
