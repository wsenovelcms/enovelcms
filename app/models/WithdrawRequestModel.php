<?php

class WithdrawRequestModel extends Model
{
    protected static string $table = 'withdraw_requests';

    public static function getByUser(int $userId): array
    {
        return self::all(['user_id' => $userId], 'id DESC');
    }

    public static function getPending(int $page = 1, int $perPage = 20): array
    {
        return self::paginate(['status' => 0], 'id DESC', $page, $perPage);
    }

    public static function getAllWithUser(int $page = 1, int $perPage = 20): array
    {
        $result = self::db()->from('withdraw_requests', 'w')
            ->select('w.*, u.username')
            ->leftJoin('users', 'u', 'u.id = w.user_id')
            ->orderBy('w.id DESC')
            ->paginate($perPage, $page);
        return ['list' => $result['list'], 'total' => $result['total'], 'page' => $page];
    }

    public static function audit(int $id, int $status): void
    {
        self::updateById(['status' => $status, 'updated_at' => date('Y-m-d H:i:s')], $id);
    }

    public static function adminPaginate(int $status, int $page = 1, int $perPage = 20): array
    {
        $t = self::tableName();
        $ut = UserModel::tableName();
        $where = '1=1';
        $params = [];
        if ($status === 0 || $status === 1 || $status === 2) {
            $where .= ' AND w.status = ?';
            $params[] = $status;
        }
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $cntRow = self::db()->query(
            "SELECT COUNT(*) AS cnt FROM {$t} w WHERE {$where}", $params
        )->fetch();
        $total = (int)($cntRow['cnt'] ?? 0);
        $list = self::db()->query(
            "SELECT w.*, u.username FROM {$t} w LEFT JOIN {$ut} u ON u.id = w.user_id "
            . "WHERE {$where} ORDER BY w.created_at DESC LIMIT {$offset}, {$perPage}",
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
