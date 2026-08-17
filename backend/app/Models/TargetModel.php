<?php

declare(strict_types=1);

namespace App\Models;

class TargetModel extends BaseModel
{
    protected $table         = 'targets';
    protected $allowedFields = [
        'name',
        'target_type',
        'target_ref_id',
        'target_month',
        'target_year',
        'status',
        'created_by',
        'updated_by',
    ];

    /** Who a target belongs to - the first drop-down on the target screen. */
    public const TYPE_REGION       = 'region';
    public const TYPE_DISTRIBUTOR  = 'distributor';
    public const TYPE_SALES_PERSON = 'sales_person';

    /** @var array<string, string> stored value => label for the drop-down */
    public const TYPES = [
        self::TYPE_REGION       => 'Region',
        self::TYPE_DISTRIBUTOR  => 'Distributors',
        self::TYPE_SALES_PERSON => 'Sales Person',
    ];

    /**
     * The labels above are the drop-down wording from the screen, which reads
     * "Distributors". Messages need the singular.
     *
     * @var array<string, string>
     */
    private const NOUNS = [
        self::TYPE_REGION       => 'region',
        self::TYPE_DISTRIBUTOR  => 'distributor',
        self::TYPE_SALES_PERSON => 'sales person',
    ];

    /**
     * @return list<string>
     */
    public static function typeValues(): array
    {
        return array_keys(self::TYPES);
    }

    public static function noun(string $type): string
    {
        return self::NOUNS[$type] ?? $type;
    }

    /**
     * The live target already covering this owner and month, if there is one.
     * Inactive rows are ignored, so a deactivated target never blocks its
     * replacement.
     *
     * @return array<string, mixed>|null
     */
    public function findActiveForPeriod(
        string $type,
        int $refId,
        int $month,
        int $year,
        ?int $exceptId = null,
    ): ?array {
        $builder = $this->db->table('targets')
            ->where('target_type', $type)
            ->where('target_ref_id', $refId)
            ->where('target_month', $month)
            ->where('target_year', $year)
            ->where('status', 'active');

        if ($exceptId !== null) {
            $builder->where('id !=', $exceptId);
        }

        return $builder->get()->getRowArray();
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    public static function cast(array $row): array
    {
        $row['id']            = (int) $row['id'];
        $row['target_ref_id'] = (int) $row['target_ref_id'];
        $row['target_month']  = (int) $row['target_month'];
        $row['target_year']   = (int) $row['target_year'];

        // Handy for sorting and for a heading on the screen.
        $row['period'] = sprintf('%04d-%02d', $row['target_year'], $row['target_month']);

        return $row;
    }
}
