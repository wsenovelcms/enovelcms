<?php

class NovelModel extends Model
{
    protected static string $table = 'novels';

    public static function getDetail(int $id): ?array
    {
        $novel = self::findById($id);
        if (!$novel) return null;

        if (!empty($novel['category_id'])) {
            $cat = CategoryModel::findById((int)$novel['category_id']);
            $novel['category_name'] = $cat['name'] ?? '';
        } else {
            $novel['category_name'] = '';
        }

        if (!empty($novel['user_id'])) {
            $novel['author_info'] = UserModel::findById((int)$novel['user_id']);
        }
        return $novel;
    }

    public static function getLibrary(array $filters, int $page = 1, int $perPage = 20): array
    {
        $where = '1=1';
        $params = [];

        if (!empty($filters['category_id'])) {
            $childIds = CategoryModel::getCategoryAndChildIds((int)$filters['category_id']);
            $placeholders = implode(',', array_fill(0, count($childIds), '?'));
            $where .= " AND category_id IN ({$placeholders})";
            array_push($params, ...$childIds);
        }

        if (($filters['status'] ?? '') === 'ongoing') {
            $where .= ' AND status = 0';
        } elseif (($filters['status'] ?? '') === 'finished') {
            $where .= ' AND status = 1';
        }

        if (!empty($filters['min_words'])) {
            $where .= ' AND total_words >= ?';
            $params[] = (int)$filters['min_words'];
        }
        if (!empty($filters['max_words'])) {
            $where .= ' AND total_words <= ?';
            $params[] = (int)$filters['max_words'];
        }

        if (($filters['vip_filter'] ?? 'all') === 'vip') {
            $where .= ' AND is_vip = 1';
        } elseif (($filters['vip_filter'] ?? 'all') === 'free') {
            $where .= ' AND is_vip = 0';
        }

        if (!empty($filters['tag_id']) && self::db()->tableExists('novel_tags')) {
            $nt = self::db()->table('novel_tags');
            $novelTable = self::tableName();
            $where .= " AND EXISTS (SELECT 1 FROM {$nt} WHERE novel_id = {$novelTable}.id AND tag_id = ?)";
            $params[] = (int)$filters['tag_id'];
        }

        $extraWhere = Hook::applyFilters('library_where', ['where' => '', 'params' => []], $filters);
        if (is_array($extraWhere) && !empty($extraWhere['where']) && is_string($extraWhere['where'])) {
            $where .= ' ' . $extraWhere['where'];
            if (!empty($extraWhere['params']) && is_array($extraWhere['params'])) {
                array_push($params, ...array_values($extraWhere['params']));
            }
        }

        $defaultOrder = 'COALESCE(last_chapter_time, created_at) DESC, id DESC';
        switch ($filters['order_by'] ?? 'update') {
            case 'views':     $orderSql = 'views DESC'; break;
            case 'favorites': $orderSql = 'favorites DESC'; break;
            case 'words':     $orderSql = 'total_words DESC'; break;
            case 'new':       $orderSql = 'created_at DESC, id DESC'; break;
            case 'id':        $orderSql = 'id DESC'; break;
            default:          $orderSql = $defaultOrder;
        }

        $orderSql = Hook::applyFilters('library_order_sql', $orderSql, $filters['order_by'] ?? 'update', $filters);
        if (!Model::isSafeOrderSql($orderSql)) {
            $orderSql = $defaultOrder;
        }

        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $t = self::tableName();

        $cntRow = self::db()->query("SELECT COUNT(*) AS cnt FROM {$t} WHERE {$where}", $params)->fetch();
        $total = (int)($cntRow['cnt'] ?? 0);

        $list = self::db()
            ->query("SELECT * FROM {$t} WHERE {$where} ORDER BY {$orderSql} LIMIT {$offset}, {$perPage}", $params)
            ->fetchAll();

        return [
            'list'       => $list,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => (int)ceil($total / $perPage),
        ];
    }

    public static function getRank(string $type = 'views', int $limit = 10, string $timeRange = 'all'): array
    {
        $key = "novel_rank_{$type}_{$limit}_{$timeRange}";
        return Cache::remember($key, 600, function () use ($type, $limit, $timeRange) {
            switch ($type) {
                case 'favorites': $order = 'favorites DESC'; break;
                case 'chapters':  $order = 'total_chapters DESC'; break;
                case 'words':     $order = 'total_words DESC'; break;
                case 'new':       $order = 'COALESCE(last_chapter_time, created_at) DESC, id DESC'; break;
                default:          $order = 'views DESC';
            }
            $defaultOrder = $order;
            $order = (string)Hook::applyFilters('rank_order_sql', $order, $type, $timeRange);
            if (!Model::isSafeOrderSql($order)) {
                $order = $defaultOrder;
            }
            $where = [];
            if ($timeRange === 'week') {
                $where['last_chapter_time > ?'] = date('Y-m-d H:i:s', strtotime('-7 days'));
            }
            $rows = self::all($where, $order, $limit);
            foreach ($rows as &$row) {
                if (!empty($row['category_id'])) {
                    $cat = CategoryModel::findById((int)$row['category_id']);
                    $row['category_name'] = $cat['name'] ?? '';
                } else {
                    $row['category_name'] = '';
                }
            }
            unset($row);
            return $rows;
        });
    }

    public static function getVipBestsellers(int $limit = 12): array
    {
        $rows = self::db()->from('novels', 'n')
            ->selectRaw("n.*, (SELECT COALESCE(SUM(price),0) FROM " . tb('chapter_purchases') . " WHERE novel_id = n.id) AS vip_sales_amount")
            ->where('n.is_vip = 1')
            ->orderBy('vip_sales_amount DESC, n.views DESC')
            ->limit($limit)
            ->get();
        foreach ($rows as &$row) {
            if (!empty($row['category_id'])) {
                $cat = CategoryModel::findById((int)$row['category_id']);
                $row['category_name'] = $cat['name'] ?? '';
            } else {
                $row['category_name'] = '';
            }
        }
        unset($row);
        return $rows;
    }

    public static function search(string $keyword, int $page = 1, int $perPage = 20): array
    {
        $kw = '%' . $keyword . '%';
        $t = self::tableName();
        $offset = ($page - 1) * $perPage;

        $where = 'title LIKE ? OR author LIKE ? OR description LIKE ?';
        $params = [$kw, $kw, $kw];

        $cntRow = self::db()->query("SELECT COUNT(*) AS cnt FROM {$t} WHERE {$where}", $params)->fetch();
        $list = self::db()
            ->query("SELECT * FROM {$t} WHERE {$where} ORDER BY updated_at DESC LIMIT {$offset}, {$perPage}", $params)
            ->fetchAll();

        return [
            'list'       => $list,
            'total'      => (int)($cntRow['cnt'] ?? 0),
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => (int)ceil((int)($cntRow['cnt'] ?? 0) / $perPage),
            'keyword'    => $keyword,
        ];
    }

    public static function adminPaginate(string $keyword, int $page = 1, int $perPage = 20): array
    {
        $t = self::tableName();
        $ct = CategoryModel::tableName();
        $where = '1=1';
        $params = [];
        if ($keyword !== '') {
            $where .= ' AND (n.title LIKE ? OR n.author LIKE ?)';
            $params[] = '%' . $keyword . '%';
            $params[] = '%' . $keyword . '%';
        }
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $cntRow = self::db()->query("SELECT COUNT(*) AS cnt FROM {$t} n WHERE {$where}", $params)->fetch();
        $total = (int)($cntRow['cnt'] ?? 0);
        $list = self::db()->query(
            "SELECT n.*, c.name AS cat FROM {$t} n LEFT JOIN {$ct} c ON n.category_id = c.id "
            . "WHERE {$where} ORDER BY n.id DESC LIMIT {$offset}, {$perPage}",
            $params
        )->fetchAll();

        return [
            'list'       => $list,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => (int)ceil($total / $perPage),
        ];
    }

    public static function addViews(int $id): void
    {
        self::increment('views', 1, ['id' => $id]);
    }

    public static function changeFavorites(int $id, int $amount): void
    {
        self::increment('favorites', $amount, ['id' => $id]);
        self::invalidateHomeBlocks();
    }

    public static function getAuthorStats(int $userId): array
    {
        $row = self::db()->from('novels')
            ->selectRaw('COUNT(*) AS c, COALESCE(SUM(total_words),0) AS w, COALESCE(SUM(views),0) AS v')
            ->where('user_id = ?', [$userId])
            ->first();
        return [
            'count' => (int)($row['c'] ?? 0),
            'words' => (int)($row['w'] ?? 0),
            'views' => (int)($row['v'] ?? 0),
        ];
    }

    public static function getByAuthor(int $userId, int $page = 1, int $perPage = 20): array
    {
        return self::paginate(['user_id' => $userId], 'id DESC', $page, $perPage);
    }

    public static function getRandom(int $limit = 6): array
    {
        return self::all([], 'RAND()', $limit);
    }

    public static function getByIds(array $ids): array
    {
        if (empty($ids)) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $t = self::tableName();
        return self::db()->query("SELECT * FROM {$t} WHERE id IN ({$placeholders})", $ids)->fetchAll();
    }

    public static function refreshStats(int $novelId): void
    {
        $chapters = ChapterModel::getListByNovel($novelId);
        $totalWords = 0;
        foreach ($chapters as $ch) $totalWords += (int)$ch['word_count'];
        $last = $chapters[count($chapters) - 1] ?? null;

        self::update([
            'total_words'           => $totalWords,
            'total_chapters'        => count($chapters),
            'last_chapter_id'       => $last['id'] ?? null,
            'last_chapter_title'    => $last['title'] ?? null,
            'last_chapter_is_vip'   => $last['is_vip'] ?? 0,
            'last_chapter_time'     => $last['created_at'] ?? null,
        ], ['id' => $novelId]);
    }

    public static function create(array $data): int
    {
        $id = parent::create($data);
        self::invalidateHomeBlocks();
        return $id;
    }

    public static function update(array $data, array $where): int
    {
        $affected = parent::update($data, $where);
        self::invalidateHomeBlocks();
        return $affected;
    }

    public static function updateById(array $data, int $id): int
    {
        $affected = parent::updateById($data, $id);
        self::invalidateHomeBlocks();
        return $affected;
    }

    public static function delete(array $where): int
    {
        $affected = parent::delete($where);
        self::invalidateHomeBlocks();
        return $affected;
    }

    public static function deleteById(int $id): int
    {
        $affected = parent::deleteById($id);
        self::invalidateHomeBlocks();
        return $affected;
    }

    private static function invalidateHomeBlocks(): void
    {
        try {
            DiyBlockModel::invalidateAll();
        } catch (\Throwable $e) {
        }
    }
}
