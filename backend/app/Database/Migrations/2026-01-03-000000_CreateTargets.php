<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Monthly sales targets: a header naming who the target is for and which month
 * it covers, plus one row per product holding the number of units.
 *
 * `target_ref_id` points at `regions`, `distributors` or `users` depending on
 * `target_type`, so it carries no foreign key - one column cannot reference
 * three tables. TargetService checks that the row exists and is active before
 * anything is written, and resolves the name back on the way out.
 *
 * There is deliberately no unique key over (type, ref, month, year): "delete"
 * is a soft delete here as everywhere else, and a hard constraint would make a
 * deactivated target block its own replacement forever. The rule that one
 * active target exists per owner per month is enforced in the service, which
 * can tell an inactive row from a live one.
 *
 * Written as raw DDL to match the rest of the schema.
 */
class CreateTargets extends Migration
{
    public function up(): void
    {
        $charset = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $this->db->query("
            CREATE TABLE IF NOT EXISTS targets (
              id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              name           VARCHAR(200)    NOT NULL,
              target_type    ENUM('region','distributor','sales_person') NOT NULL,
              target_ref_id  BIGINT UNSIGNED NOT NULL,
              target_month   TINYINT UNSIGNED NOT NULL,
              target_year    SMALLINT UNSIGNED NOT NULL,
              status         ENUM('active','inactive') NOT NULL DEFAULT 'active',
              created_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
              created_by     BIGINT UNSIGNED NULL,
              updated_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              updated_by     BIGINT UNSIGNED NULL,
              PRIMARY KEY (id),
              KEY idx_targets_owner (target_type, target_ref_id, target_year, target_month),
              KEY idx_targets_period (target_year, target_month)
            ) {$charset}
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS target_products (
              id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              target_id     BIGINT UNSIGNED NOT NULL,
              product_id    BIGINT UNSIGNED NOT NULL,
              target_units  INT UNSIGNED    NOT NULL DEFAULT 0,
              created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
              created_by    BIGINT UNSIGNED NULL,
              updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              updated_by    BIGINT UNSIGNED NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_target_product (target_id, product_id),
              KEY idx_target_products_product (product_id),
              CONSTRAINT fk_tp_target  FOREIGN KEY (target_id)  REFERENCES targets (id) ON DELETE CASCADE,
              CONSTRAINT fk_tp_product FOREIGN KEY (product_id) REFERENCES products (id)
            ) {$charset}
        ");
    }

    public function down(): void
    {
        // Child first, so the foreign key lets go.
        $this->db->query('DROP TABLE IF EXISTS target_products');
        $this->db->query('DROP TABLE IF EXISTS targets');
    }
}
