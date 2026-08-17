<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Renames `permissions.module` / `permissions.action` to `page_route` /
 * `page_action`, so a permission reads as "this page, this action on it"
 * rather than a generic module/action pair. `slug` is unchanged - it is still
 * `{route}:{action}`.
 *
 * Also drops `refresh_tokens`: the API no longer issues refresh tokens, the
 * access token alone carries a 24 hour session, and there is nothing left
 * that reads or writes this table.
 */
class RepointPermissionsToPageRoutes extends Migration
{
    public function up(): void
    {
        $this->db->query('
            ALTER TABLE permissions
              DROP INDEX uq_permissions_module_action,
              CHANGE COLUMN module      page_route  VARCHAR(50) NOT NULL,
              CHANGE COLUMN action      page_action VARCHAR(50) NOT NULL,
              ADD UNIQUE KEY uq_permissions_route_action (page_route, page_action)
        ');

        $this->db->query('DROP TABLE IF EXISTS refresh_tokens');
    }

    public function down(): void
    {
        $this->db->query('
            ALTER TABLE permissions
              DROP INDEX uq_permissions_route_action,
              CHANGE COLUMN page_route  module VARCHAR(50) NOT NULL,
              CHANGE COLUMN page_action action VARCHAR(50) NOT NULL,
              ADD UNIQUE KEY uq_permissions_module_action (module, action)
        ');

        $this->db->query("
            CREATE TABLE IF NOT EXISTS refresh_tokens (
              id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              user_id      BIGINT UNSIGNED NOT NULL,
              token_hash   CHAR(64)        NOT NULL,
              expires_at   DATETIME        NOT NULL,
              revoked_at   DATETIME        NULL,
              user_agent   VARCHAR(255)    NULL,
              ip_address   VARCHAR(64)     NULL,
              status       ENUM('active','inactive') NOT NULL DEFAULT 'active',
              created_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
              created_by   BIGINT UNSIGNED NULL,
              updated_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              updated_by   BIGINT UNSIGNED NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_refresh_token_hash (token_hash),
              KEY idx_refresh_user (user_id, revoked_at),
              CONSTRAINT fk_refresh_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}
