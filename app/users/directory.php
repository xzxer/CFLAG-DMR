<?php
declare(strict_types=1);

require_once __DIR__ . '/../database/connection.php';

function _directory_base_where(bool $admin): string
{
    $where = "u.moderation_state = 'active' AND u.email_verified_at IS NOT NULL";
    if (!$admin) {
        $where .= ' AND u.show_in_directory = 1';
    }
    return $where;
}

function _directory_order(): string
{
    return "ORDER BY (u.callsign IS NULL OR u.callsign = '') ASC, u.callsign ASC, u.username ASC";
}

function get_directory_users(int $page, int $per_page, bool $admin = false): array
{
    $offset = ($page - 1) * $per_page;
    $where  = _directory_base_where($admin);
    $order  = _directory_order();

    $stmt = get_db()->prepare(
        "SELECT u.id, u.callsign, u.username, u.display_name, u.grid_square,
                u.show_name_publicly, u.first_name, u.last_name,
                (SELECT COUNT(*) FROM devices d
                 WHERE d.user_id = u.id AND d.status = 'approved') AS device_count
         FROM users u
         WHERE {$where}
         {$order}
         LIMIT ? OFFSET ?"
    );
    $stmt->execute([$per_page, $offset]);
    return $stmt->fetchAll();
}

function count_directory_users(bool $admin = false): int
{
    $where = _directory_base_where($admin);
    $stmt  = get_db()->prepare("SELECT COUNT(*) FROM users u WHERE {$where}");
    $stmt->execute();
    return (int) $stmt->fetchColumn();
}

function search_directory_users(string $q, int $page, int $per_page, bool $admin = false): array
{
    $offset = ($page - 1) * $per_page;
    $where  = _directory_base_where($admin);
    $like   = '%' . $q . '%';
    $order  = _directory_order();

    $stmt = get_db()->prepare(
        "SELECT u.id, u.callsign, u.username, u.display_name, u.grid_square,
                u.show_name_publicly, u.first_name, u.last_name,
                (SELECT COUNT(*) FROM devices d
                 WHERE d.user_id = u.id AND d.status = 'approved') AS device_count
         FROM users u
         WHERE {$where}
           AND (u.callsign LIKE ? OR u.username LIKE ? OR u.display_name LIKE ?)
         {$order}
         LIMIT ? OFFSET ?"
    );
    $stmt->execute([$like, $like, $like, $per_page, $offset]);
    return $stmt->fetchAll();
}

function count_search_results(string $q, bool $admin = false): int
{
    $where = _directory_base_where($admin);
    $like  = '%' . $q . '%';

    $stmt = get_db()->prepare(
        "SELECT COUNT(*) FROM users u
         WHERE {$where}
           AND (u.callsign LIKE ? OR u.username LIKE ? OR u.display_name LIKE ?)"
    );
    $stmt->execute([$like, $like, $like]);
    return (int) $stmt->fetchColumn();
}
