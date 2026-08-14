<?php

declare(strict_types=1);

namespace App\Models;

class RefreshTokenModel extends BaseModel
{
    protected $table         = 'refresh_tokens';
    protected $allowedFields = [
        'user_id',
        'token_hash',
        'expires_at',
        'revoked_at',
        'user_agent',
        'ip_address',
        'status',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, mixed>|null
     */
    public function findByHash(string $hash): ?array
    {
        return $this->where('token_hash', $hash)->first();
    }

    /** Ends one session. */
    public function revokeByHash(string $hash, int $actorId): void
    {
        $this->db->table('refresh_tokens')
            ->where('token_hash', $hash)
            ->where('revoked_at', null)
            ->update([
                'revoked_at' => date('Y-m-d H:i:s'),
                'status'     => 'inactive',
                'updated_by' => $actorId,
            ]);
    }

    /** Ends every session a user has - used on logout-all, role change and password change. */
    public function revokeAllForUser(int $userId, int $actorId): void
    {
        $this->db->table('refresh_tokens')
            ->where('user_id', $userId)
            ->where('revoked_at', null)
            ->update([
                'revoked_at' => date('Y-m-d H:i:s'),
                'status'     => 'inactive',
                'updated_by' => $actorId,
            ]);
    }
}
