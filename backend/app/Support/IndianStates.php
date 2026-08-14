<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Reference data loaded once by the seeder. States have no add / edit / delete
 * screen, which is why they carry no permissions of their own.
 */
final class IndianStates
{
    /** @var list<array{name: string, code: string}> */
    public const ALL = [
        ['name' => 'Andhra Pradesh', 'code' => 'AP'],
        ['name' => 'Arunachal Pradesh', 'code' => 'AR'],
        ['name' => 'Assam', 'code' => 'AS'],
        ['name' => 'Bihar', 'code' => 'BR'],
        ['name' => 'Chhattisgarh', 'code' => 'CG'],
        ['name' => 'Goa', 'code' => 'GA'],
        ['name' => 'Gujarat', 'code' => 'GJ'],
        ['name' => 'Haryana', 'code' => 'HR'],
        ['name' => 'Himachal Pradesh', 'code' => 'HP'],
        ['name' => 'Jharkhand', 'code' => 'JH'],
        ['name' => 'Karnataka', 'code' => 'KA'],
        ['name' => 'Kerala', 'code' => 'KL'],
        ['name' => 'Madhya Pradesh', 'code' => 'MP'],
        ['name' => 'Maharashtra', 'code' => 'MH'],
        ['name' => 'Manipur', 'code' => 'MN'],
        ['name' => 'Meghalaya', 'code' => 'ML'],
        ['name' => 'Mizoram', 'code' => 'MZ'],
        ['name' => 'Nagaland', 'code' => 'NL'],
        ['name' => 'Odisha', 'code' => 'OD'],
        ['name' => 'Punjab', 'code' => 'PB'],
        ['name' => 'Rajasthan', 'code' => 'RJ'],
        ['name' => 'Sikkim', 'code' => 'SK'],
        ['name' => 'Tamil Nadu', 'code' => 'TN'],
        ['name' => 'Telangana', 'code' => 'TS'],
        ['name' => 'Tripura', 'code' => 'TR'],
        ['name' => 'Uttar Pradesh', 'code' => 'UP'],
        ['name' => 'Uttarakhand', 'code' => 'UK'],
        ['name' => 'West Bengal', 'code' => 'WB'],
        ['name' => 'Andaman and Nicobar Islands', 'code' => 'AN'],
        ['name' => 'Chandigarh', 'code' => 'CH'],
        ['name' => 'Dadra and Nagar Haveli and Daman and Diu', 'code' => 'DH'],
        ['name' => 'Delhi', 'code' => 'DL'],
        ['name' => 'Jammu and Kashmir', 'code' => 'JK'],
        ['name' => 'Ladakh', 'code' => 'LA'],
        ['name' => 'Lakshadweep', 'code' => 'LD'],
        ['name' => 'Puducherry', 'code' => 'PY'],
    ];
}
