<?php

class GoldLogModel extends Model
{
    protected static string $table = 'gold_logs';

    public static function getByUser(int $userId, int $page = 1, int $perPage = 20): array
    {
        $result = self::paginate(['user_id' => $userId], 'id DESC', $page, $perPage);
        foreach ($result['list'] as &$row) {
            $row['novel_title'] = '';
            $row['chapter_title'] = '';

            if (!empty($row['chapter_id'])
                && in_array($row['type'], ['purchase_chapter', 'read_vip'], true)
                && preg_match('/^购买《(?<novel>.+)》-\s*(?<chapter>.+)$/u', (string)$row['remark'], $m)) {
                $row['novel_title'] = $m['novel'];
                $row['chapter_title'] = $m['chapter'];
            }
        }
        unset($row);
        return $result;
    }

    public static function getLastByType(int $userId, string $type): ?array
    {
        return self::find(['user_id' => $userId, 'type' => $type]);
    }

    public static function existsToday(int $userId, string $type, string $today): bool
    {
        $t = self::tableName();
        $sql = "SELECT id FROM {$t} WHERE user_id = ? AND type = ? AND DATE(created_at) = ? LIMIT 1";
        $row = self::db()->query($sql, [$userId, $type, $today])->fetch();
        return !empty($row);
    }

    public static function adminSignLogs(array $filters, int $page = 1, int $perPage = 20): array
    {
        $t = self::tableName();
        $ut = UserModel::tableName();
        $where = "l.type = 'sign'";
        $params = [];
        if (($filters['user'] ?? '') !== '') {
            $where .= ' AND u.username LIKE ?';
            $params[] = '%' . $filters['user'] . '%';
        }
        if (!empty($filters['start'])) {
            $where .= ' AND DATE(l.created_at) >= ?';
            $params[] = $filters['start'];
        }
        if (!empty($filters['end'])) {
            $where .= ' AND DATE(l.created_at) <= ?';
            $params[] = $filters['end'];
        }
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $cntRow = self::db()->query(
            "SELECT COUNT(*) AS cnt FROM {$t} l JOIN {$ut} u ON l.user_id = u.id WHERE {$where}",
            $params
        )->fetch();
        $total = (int)($cntRow['cnt'] ?? 0);
        $list = self::db()->query(
            "SELECT l.id, l.user_id, u.username, l.gold_change, l.gold_after, l.remark, l.created_at "
            . "FROM {$t} l JOIN {$ut} u ON l.user_id = u.id WHERE {$where} "
            . "ORDER BY l.created_at DESC LIMIT {$offset}, {$perPage}",
            $params
        )->fetchAll();

        return [
            'list'       => $list,
            'total'      => $total,
            'page'       => $page,
            'totalPages' => (int)ceil($total / $perPage),
        ];
    }
}
