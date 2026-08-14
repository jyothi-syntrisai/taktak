<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The whole Tak Tak schema, MySQL 8+ / InnoDB.
 *
 * Written as raw DDL rather than through the Forge so it matches the schema
 * document line for line: enum columns, composite unique keys, the quoted
 * `row_number` column (ROW_NUMBER is reserved in MySQL 8), and the
 * `v_current_price_list` view.
 *
 * Tables are created parent-first so the foreign keys resolve. `created_by` /
 * `updated_by` are plain BIGINT columns and are intentionally not declared as
 * FKs, because the very first rows are inserted by the seeder before any user
 * exists.
 */
class CreateTaktakSchema extends Migration
{
    public function up(): void
    {
        $charset = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        // --- roles, permissions, users -------------------------------------

        $this->db->query("
            CREATE TABLE IF NOT EXISTS roles (
              id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              name         VARCHAR(50)     NOT NULL,
              description  VARCHAR(255)    NULL,
              is_system    BOOLEAN         NOT NULL DEFAULT FALSE,
              status       ENUM('active','inactive') NOT NULL DEFAULT 'active',
              created_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
              created_by   BIGINT UNSIGNED NULL,
              updated_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              updated_by   BIGINT UNSIGNED NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_roles_name (name)
            ) {$charset}
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS permissions (
              id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              slug         VARCHAR(100)    NOT NULL,
              module       VARCHAR(50)     NOT NULL,
              action       VARCHAR(50)     NOT NULL,
              name         VARCHAR(150)    NOT NULL,
              status       ENUM('active','inactive') NOT NULL DEFAULT 'active',
              created_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
              created_by   BIGINT UNSIGNED NULL,
              updated_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              updated_by   BIGINT UNSIGNED NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_permissions_slug (slug),
              UNIQUE KEY uq_permissions_module_action (module, action)
            ) {$charset}
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS role_permissions (
              id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              role_id        BIGINT UNSIGNED NOT NULL,
              permission_id  BIGINT UNSIGNED NOT NULL,
              created_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
              created_by     BIGINT UNSIGNED NULL,
              updated_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              updated_by     BIGINT UNSIGNED NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_role_permission (role_id, permission_id),
              CONSTRAINT fk_rp_role       FOREIGN KEY (role_id)       REFERENCES roles (id) ON DELETE CASCADE,
              CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions (id)
            ) {$charset}
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS users (
              id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              full_name      VARCHAR(150)    NOT NULL,
              email          VARCHAR(255)    NOT NULL,
              password_hash  VARCHAR(255)    NOT NULL,
              role_id        BIGINT UNSIGNED NOT NULL,
              status         ENUM('active','inactive') NOT NULL DEFAULT 'active',
              last_login_at  DATETIME        NULL,
              created_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
              created_by     BIGINT UNSIGNED NULL,
              updated_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              updated_by     BIGINT UNSIGNED NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_users_email (email),
              KEY idx_users_role (role_id),
              CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (id)
            ) {$charset}
        ");

        // --- geography ------------------------------------------------------

        $this->db->query("
            CREATE TABLE IF NOT EXISTS states (
              id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              name         VARCHAR(100)    NOT NULL,
              code         VARCHAR(10)     NULL,
              status       ENUM('active','inactive') NOT NULL DEFAULT 'active',
              created_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
              created_by   BIGINT UNSIGNED NULL,
              updated_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              updated_by   BIGINT UNSIGNED NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_states_name (name),
              UNIQUE KEY uq_states_code (code)
            ) {$charset}
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS regions (
              id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              name         VARCHAR(100)    NOT NULL,
              status       ENUM('active','inactive') NOT NULL DEFAULT 'active',
              created_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
              created_by   BIGINT UNSIGNED NULL,
              updated_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              updated_by   BIGINT UNSIGNED NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_regions_name (name)
            ) {$charset}
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS region_states (
              id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              region_id    BIGINT UNSIGNED NOT NULL,
              state_id     BIGINT UNSIGNED NOT NULL,
              created_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
              created_by   BIGINT UNSIGNED NULL,
              updated_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              updated_by   BIGINT UNSIGNED NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_region_state (region_id, state_id),
              CONSTRAINT fk_rs_region FOREIGN KEY (region_id) REFERENCES regions (id) ON DELETE CASCADE,
              CONSTRAINT fk_rs_state  FOREIGN KEY (state_id)  REFERENCES states (id)
            ) {$charset}
        ");

        // --- distributors ---------------------------------------------------

        $this->db->query("
            CREATE TABLE IF NOT EXISTS distributors (
              id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              name         VARCHAR(200)    NOT NULL,
              code         VARCHAR(50)     NULL,
              status       ENUM('active','inactive') NOT NULL DEFAULT 'active',
              created_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
              created_by   BIGINT UNSIGNED NULL,
              updated_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              updated_by   BIGINT UNSIGNED NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_distributors_code (code)
            ) {$charset}
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS distributor_regions (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              distributor_id  BIGINT UNSIGNED NOT NULL,
              region_id       BIGINT UNSIGNED NOT NULL,
              created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
              created_by      BIGINT UNSIGNED NULL,
              updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              updated_by      BIGINT UNSIGNED NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_distributor_region (distributor_id, region_id),
              CONSTRAINT fk_dr_distributor FOREIGN KEY (distributor_id) REFERENCES distributors (id) ON DELETE CASCADE,
              CONSTRAINT fk_dr_region      FOREIGN KEY (region_id)      REFERENCES regions (id)
            ) {$charset}
        ");

        // --- catalogue ------------------------------------------------------

        $this->db->query("
            CREATE TABLE IF NOT EXISTS brands (
              id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              name         VARCHAR(150)    NOT NULL,
              code         VARCHAR(50)     NULL,
              status       ENUM('active','inactive') NOT NULL DEFAULT 'active',
              created_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
              created_by   BIGINT UNSIGNED NULL,
              updated_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              updated_by   BIGINT UNSIGNED NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_brands_name (name),
              UNIQUE KEY uq_brands_code (code)
            ) {$charset}
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS products (
              id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              brand_id      BIGINT UNSIGNED NOT NULL,
              sku           VARCHAR(100)    NOT NULL,
              product_name  VARCHAR(255)    NOT NULL,
              status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
              created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
              created_by    BIGINT UNSIGNED NULL,
              updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              updated_by    BIGINT UNSIGNED NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_products_sku (sku),
              KEY idx_products_brand_status (brand_id, status),
              CONSTRAINT fk_products_brand FOREIGN KEY (brand_id) REFERENCES brands (id)
            ) {$charset}
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS product_mrp (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              product_id      BIGINT UNSIGNED NOT NULL,
              mrp             DECIMAL(12,2)   NOT NULL,
              effective_from  DATE            NOT NULL,
              effective_to    DATE            NULL,
              created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
              created_by      BIGINT UNSIGNED NULL,
              updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              updated_by      BIGINT UNSIGNED NULL,
              PRIMARY KEY (id),
              KEY idx_mrp_product_from (product_id, effective_from),
              CONSTRAINT fk_mrp_product FOREIGN KEY (product_id) REFERENCES products (id)
            ) {$charset}
        ");

        // --- CSV imports ----------------------------------------------------

        $this->db->query("
            CREATE TABLE IF NOT EXISTS import_batches (
              id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              file_name        VARCHAR(255)    NOT NULL,
              module           ENUM('products','product_mrp') NOT NULL,
              total_records    INT UNSIGNED    NOT NULL DEFAULT 0,
              success_records  INT UNSIGNED    NOT NULL DEFAULT 0,
              failed_records   INT UNSIGNED    NOT NULL DEFAULT 0,
              status           ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
              created_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
              created_by       BIGINT UNSIGNED NULL,
              updated_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              updated_by       BIGINT UNSIGNED NULL,
              PRIMARY KEY (id)
            ) {$charset}
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS product_import_staging (
              id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              import_batch_id  BIGINT UNSIGNED NOT NULL,
              `row_number`     INT UNSIGNED    NOT NULL,
              brand_name       VARCHAR(150)    NULL,
              sku              VARCHAR(100)    NULL,
              product_name     VARCHAR(255)    NULL,
              mrp              VARCHAR(50)     NULL,
              status           ENUM('pending','valid','error','processed') NOT NULL DEFAULT 'pending',
              error_message    VARCHAR(500)    NULL,
              created_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
              created_by       BIGINT UNSIGNED NULL,
              updated_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              updated_by       BIGINT UNSIGNED NULL,
              PRIMARY KEY (id),
              KEY idx_staging_batch_status (import_batch_id, status),
              CONSTRAINT fk_staging_batch FOREIGN KEY (import_batch_id) REFERENCES import_batches (id) ON DELETE CASCADE
            ) {$charset}
        ");

        // --- refresh tokens -------------------------------------------------
        // Not part of the schema document. Added so a refresh token can be
        // revoked on logout / password change instead of staying valid until it
        // expires. Only the SHA-256 hash of the token is stored.

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
            ) {$charset}
        ");

        // --- views ----------------------------------------------------------

        $this->db->query('
            CREATE OR REPLACE VIEW v_current_price_list AS
            SELECT  p.id AS product_id,
                    p.sku,
                    p.product_name,
                    b.name AS brand_name,
                    m.mrp,
                    m.effective_from
            FROM    products p
            JOIN    brands   b ON b.id = p.brand_id
            LEFT JOIN product_mrp m
                   ON m.product_id = p.id
                  AND m.effective_to IS NULL
            WHERE   p.status = "active"
        ');
    }

    public function down(): void
    {
        $this->db->query('DROP VIEW IF EXISTS v_current_price_list');

        // Children first, so the foreign keys let go.
        foreach ([
            'refresh_tokens',
            'product_import_staging',
            'import_batches',
            'product_mrp',
            'products',
            'brands',
            'distributor_regions',
            'distributors',
            'region_states',
            'regions',
            'states',
            'users',
            'role_permissions',
            'permissions',
            'roles',
        ] as $table) {
            $this->db->query("DROP TABLE IF EXISTS {$table}");
        }
    }
}
