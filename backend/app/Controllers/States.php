<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Pagination;
use CodeIgniter\HTTP\ResponseInterface;

class States extends BaseApiController
{
    /**
     * Reference data, loaded once by the seeder. There is no add / edit /
     * delete screen, so this is the only states endpoint - and it defaults to a
     * page of 100 because the region screen wants the whole dropdown at once.
     */
    public function index(): ResponseInterface
    {
        $query = $this->queryParams();
        $page  = Pagination::fromQuery($query, 100, 500);

        $builder = db_connect()->table('states');

        if ($page['status'] !== null) {
            $builder->where('status', $page['status']);
        }

        if ($page['search'] !== null) {
            $builder->groupStart()
                ->like('name', $page['search'])
                ->orLike('code', $page['search'])
                ->groupEnd();
        }

        $total = $builder->countAllResults(false);

        $rows = $builder->orderBy('name', 'ASC')
            ->limit($page['limit'], $page['offset'])
            ->get()
            ->getResultArray();

        $items = array_map(static function (array $state): array {
            $state['id'] = (int) $state['id'];

            return $state;
        }, $rows);

        return $this->paginated($items, Pagination::meta($page['page'], $page['limit'], $total));
    }
}
