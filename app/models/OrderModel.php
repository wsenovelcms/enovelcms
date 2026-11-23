<?php

class OrderModel extends Model
{
    protected static string $table = 'orders';

    public static function findByTradeNo(string $no): ?array
    {
        return self::find(['out_trade_no' => $no]);
    }

    public static function getByUser(int $userId, int $page = 1, int $perPage = 20): array
    {
        return self::paginate(['user_id' => $userId], 'id DESC', $page, $perPage);
    }

    public static function getPendingByUser(int $userId): array
    {
        return self::all(['user_id' => $userId, 'status' => 0]);
    }

    public static function generateTradeNo(): string
    {
        return date('YmdHis') . str_pad((string)random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
    }

    public static function markPaid(int $orderId): bool
    {
        $t = self::tableName();
        $affected = self::db()->query(
            "UPDATE {$t} SET status = 1, pay_time = ? WHERE id = ? AND status = 0",
            [date('Y-m-d H:i:s'), $orderId]
        )->rowCount();
        return $affected > 0;
    }

    public static function salesAmount(string $from = '', string $to = ''): float
    {
        $where = ['status' => 1];
        $t = self::tableName();
        $sql = "SELECT COALESCE(SUM(money),0) AS agg FROM {$t} WHERE status = 1";
        $params = [];
        if ($from) { $sql .= ' AND created_at >= ?'; $params[] = $from; }
        if ($to)   { $sql .= ' AND created_at <= ?'; $params[] = $to; }
        return (float)(self::db()->query($sql, $params)->fetch()['agg'] ?? 0);
    }

    public static function adminPaginate(string $keyword, int $status, int $page = 1, int $perPage = 20): array
    {
        $t = self::tableName();
        $ut = UserModel::tableName();
        $where = '1=1';
        $params = [];
        if ($keyword !== '') {
            $where .= ' AND (o.out_trade_no LIKE ? OR u.username LIKE ?)';
            $like = '%' . $keyword . '%';
            $params[] = $like;
            $params[] = $like;
        }
        if ($status === 0 || $status === 1) {
            $where .= ' AND o.status = ?';
            $params[] = $status;
        }
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $cntRow = self::db()->query(
            "SELECT COUNT(*) AS cnt FROM {$t} o LEFT JOIN {$ut} u ON u.id = o.user_id WHERE {$where}",
            $params
        )->fetch();
        $total = (int)($cntRow['cnt'] ?? 0);
        $list = self::db()->query(
            "SELECT o.*, u.username FROM {$t} o LEFT JOIN {$ut} u ON u.id = o.user_id "
            . "WHERE {$where} ORDER BY o.id DESC LIMIT {$offset}, {$perPage}",
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
}
