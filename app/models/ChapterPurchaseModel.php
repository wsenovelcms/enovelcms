<?php

class ChapterPurchaseModel extends Model
{
    protected static string $table = 'chapter_purchases';

    public static function exists(int $userId, int $chapterId): bool
    {
        return self::count(['user_id' => $userId, 'chapter_id' => $chapterId]) > 0;
    }

    public static function record(int $userId, int $novelId, int $chapterId, int $price): int
    {
        if (self::exists($userId, $chapterId)) return 0;
        return self::create([
            'user_id' => $userId, 'novel_id' => $novelId,
            'chapter_id' => $chapterId, 'price' => $price,
        ]);
    }

    public static function countByUser(int $userId): int
    {
        return self::count(['user_id' => $userId]);
    }

    public static function getChapterTitlesByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($ids)) return [];

        $titles = [];
        $groups = ChapterShard::groupIdsByShard($ids);
        foreach ($groups as $shardIdx => $shardIds) {
            $table = ChapterShard::shardTable($shardIdx);
            $placeholders = implode(',', array_fill(0, count($shardIds), '?'));
            $rows = self::db()->query(
                'SELECT id, title FROM ' . self::db()->table($table)
                . " WHERE id IN ({$placeholders})",
                $shardIds
            )->fetchAll();
            foreach ($rows as $r) {
                $titles[(int)$r['id']] = $r['title'];
            }
        }
        return $titles;
    }

    public static function getByUser(int $userId, int $page = 1, int $perPage = 20): array
    {
        $t = self::tableName();
        $nt = NovelModel::tableName();
        $total = (int)self::count(['user_id' => $userId]);
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $list = self::db()->query(
            "SELECT p.id, p.price, p.created_at, p.chapter_id, p.novel_id, "
            . "n.title AS novel_title "
            . "FROM {$t} p LEFT JOIN {$nt} n ON p.novel_id = n.id "
            . "WHERE p.user_id = ? ORDER BY p.id DESC LIMIT {$offset}, {$perPage}",
            [$userId]
        )->fetchAll();

        $chapterIds = array_column($list, 'chapter_id');
        $titles = self::getChapterTitlesByIds($chapterIds);
        foreach ($list as &$row) {
            $row['chapter_title'] = $titles[(int)$row['chapter_id']] ?? '';
        }
        unset($row);

        return [
            'list'       => $list,
            'total'      => $total,
            'page'       => $page,
            'totalPages' => (int)ceil($total / $perPage),
        ];
    }

    public static function adminSalesStats(string $period, int $page = 1, int $perPage = 20): array
    {
        $t = self::tableName();
        $where = '1=1';
        if ($period === 'today') {
            $where .= ' AND DATE(p.created_at) = CURDATE()';
        } elseif ($period === 'month') {
            $where .= " AND DATE_FORMAT(p.created_at,'%Y-%m') = DATE_FORMAT(NOW(),'%Y-%m')";
        } elseif ($period === 'year') {
            $where .= ' AND YEAR(p.created_at) = YEAR(NOW())';
        }
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $sumRow = self::db()->query(
            "SELECT COUNT(*) AS c, COALESCE(SUM(p.price),0) AS s FROM {$t} p WHERE {$where}"
        )->fetch();
        $total = (int)($sumRow['c'] ?? 0);
        $ut = UserModel::tableName();
        $nt = NovelModel::tableName();

        $list = self::db()->query(
            "SELECT p.id, p.price, p.created_at, p.chapter_id, p.novel_id, "
            . "u.username, n.title AS novel_title "
            . "FROM {$t} p LEFT JOIN {$ut} u ON p.user_id = u.id "
            . "LEFT JOIN {$nt} n ON p.novel_id = n.id "
            . "WHERE {$where} ORDER BY p.id DESC LIMIT {$offset}, {$perPage}"
        )->fetchAll();

        $chapterIds = array_column($list, 'chapter_id');
        $titles = self::getChapterTitlesByIds($chapterIds);
        foreach ($list as &$row) {
            $row['chapter_title'] = $titles[(int)$row['chapter_id']] ?? '';
        }
        unset($row);

        return [
            'list'       => $list,
            'total'      => $total,
            'sum'        => (float)($sumRow['s'] ?? 0),
            'page'       => $page,
            'totalPages' => (int)ceil($total / $perPage),
        ];
    }
}
