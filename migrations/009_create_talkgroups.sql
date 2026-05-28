CREATE TABLE talkgroups (
    id             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    tgid           INT UNSIGNED  NOT NULL,
    name           VARCHAR(128)  NOT NULL,
    description    TEXT          NULL,
    tg_type        ENUM('open','private','club') NOT NULL DEFAULT 'open',
    ownership_tier ENUM('admin','user_partial','user_full') NOT NULL DEFAULT 'admin',
    owner_user_id  INT UNSIGNED  NULL,
    active         TINYINT(1)    NOT NULL DEFAULT 1,
    created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                          ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tgid        (tgid),
    KEY        idx_active     (active),
    KEY        idx_owner      (owner_user_id),
    CONSTRAINT fk_tg_owner FOREIGN KEY (owner_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE talkgroup_requests (
    id                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    requester_id      INT UNSIGNED  NOT NULL,
    proposed_tgid     INT UNSIGNED  NOT NULL,
    proposed_name     VARCHAR(128)  NOT NULL,
    proposed_type     ENUM('open','private','club') NOT NULL DEFAULT 'open',
    description       TEXT          NULL,
    status            ENUM('pending','approved','denied') NOT NULL DEFAULT 'pending',
    reviewed_by       INT UNSIGNED  NULL,
    denial_reason     TEXT          NULL,
    created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at       DATETIME      NULL,
    PRIMARY KEY (id),
    KEY idx_status        (status),
    KEY idx_requester     (requester_id),
    CONSTRAINT fk_tgr_requester FOREIGN KEY (requester_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_tgr_reviewer  FOREIGN KEY (reviewed_by)  REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE talkgroup_access_lists (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    talkgroup_id INT UNSIGNED NOT NULL,
    dmr_id       INT UNSIGNED NOT NULL,
    list_type    ENUM('allow','block') NOT NULL,
    added_by     INT UNSIGNED NOT NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tg_dmr_type (talkgroup_id, dmr_id, list_type),
    KEY idx_talkgroup (talkgroup_id),
    CONSTRAINT fk_tal_talkgroup FOREIGN KEY (talkgroup_id) REFERENCES talkgroups (id) ON DELETE CASCADE,
    CONSTRAINT fk_tal_adder    FOREIGN KEY (added_by)     REFERENCES users      (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE talkgroup_ownership_upgrade_requests (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    talkgroup_id INT UNSIGNED NOT NULL,
    requester_id INT UNSIGNED NOT NULL,
    reason       TEXT         NOT NULL,
    status       ENUM('pending','approved','denied') NOT NULL DEFAULT 'pending',
    reviewed_by  INT UNSIGNED NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at  DATETIME     NULL,
    PRIMARY KEY (id),
    KEY idx_status      (status),
    KEY idx_talkgroup   (talkgroup_id),
    CONSTRAINT fk_tour_tg        FOREIGN KEY (talkgroup_id) REFERENCES talkgroups (id) ON DELETE CASCADE,
    CONSTRAINT fk_tour_requester FOREIGN KEY (requester_id) REFERENCES users      (id) ON DELETE CASCADE,
    CONSTRAINT fk_tour_reviewer  FOREIGN KEY (reviewed_by)  REFERENCES users      (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
