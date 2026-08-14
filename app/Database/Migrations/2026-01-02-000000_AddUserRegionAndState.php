<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Gives `users` the two columns the RSM and SO roles need: an RSM belongs to one
 * region, an SO to one state inside that region. Both stay NULL for every other
 * role, which is why they are nullable rather than defaulted.
 *
 * Written as an ALTER rather than being folded into the CreateTaktakSchema
 * migration so an installation that has already run that one picks the columns
 * up without a rebuild.
 */
class AddUserRegionAndState extends Migration
{
    public function up(): void
    {
        if ($this->db->fieldExists('region_id', 'users')) {
            return;
        }

        $this->db->query('
            ALTER TABLE users
              ADD COLUMN region_id BIGINT UNSIGNED NULL AFTER role_id,
              ADD COLUMN state_id  BIGINT UNSIGNED NULL AFTER region_id,
              ADD KEY idx_users_region (region_id),
              ADD KEY idx_users_state (state_id),
              ADD CONSTRAINT fk_users_region FOREIGN KEY (region_id) REFERENCES regions (id),
              ADD CONSTRAINT fk_users_state  FOREIGN KEY (state_id)  REFERENCES states (id)
        ');
    }

    public function down(): void
    {
        if (! $this->db->fieldExists('region_id', 'users')) {
            return;
        }

        $this->db->query('
            ALTER TABLE users
              DROP FOREIGN KEY fk_users_region,
              DROP FOREIGN KEY fk_users_state,
              DROP COLUMN region_id,
              DROP COLUMN state_id
        ');
    }
}
