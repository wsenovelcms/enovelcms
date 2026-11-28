<?php

class UserModel extends Model
{
    protected static string $table = 'users';

    public static function normalizeVip(array $row): array
    {
        if ((int)($row['vip_level'] ?? 0) > 0 && !empty($row['vip_expire'])
            && strtotime((string)$row['vip_expire']) <= time()) {
            $row['vip_level'] = 0;
        }
        return $row;
    }

    public static function normalizeRows(array $rows): array
    {
        $vip = false;
        foreach ($rows as $row) {
            if ((int)($row['vip_level'] ?? 0) > 0) { $vip = true; break; }
        }
        if (!$vip) return $rows;
        foreach ($rows as &$row) $row = self::normalizeVip($row);
        unset($row);
        return $rows;
    }

    public static function findById(int $id, string $fields = '*'): ?array
    {
        $row = parent::findById($id, $fields);
        return $row ? self::normalizeVip($row) : null;
    }

    public static function find(array $where, string $fields = '*'): ?array
    {
        $row = parent::find($where, $fields);
        return $row ? self::normalizeVip($row) : null;
    }

    public static function all(array $where = [], string $order = '', int $limit = 0, int $offset = 0, string $fields = '*'): array
    {
        return self::normalizeRows(parent::all($where, $order, $limit, $offset, $fields));
    }

    public static function paginate(array $where = [], string $order = '', int $page = 1, int $perPage = 20, string $fields = '*'): array
    {
        $result = parent::paginate($where, $order, $page, $perPage, $fields);
        $result['list'] = self::normalizeRows($result['list']);
        return $result;
    }

    public static function expireVip(): int
    {
        return self::db()->query(
            'UPDATE ' . self::tableName() . ' SET vip_level = 0 WHERE vip_level > 0 AND vip_expire IS NOT NULL AND vip_expire <= NOW()'
        )->rowCount();
    }

    public static function login(string $username, string $password): ?array
    {
        $user = self::find(['username' => $username]);
        if (!$user) return null;
        if (!Security::verifyPassword($password, $user['password'])) return null;
        unset($user['password']);
        return $user;
    }

    public static function findByEmail(string $email, string $fields = '*'): ?array
    {
        $email = trim($email);
        if ($email === '') return null;
        return self::find(['email' => $email], $fields);
    }

    public static function register(string $username, string $password, string $email = ''): int
    {
        return self::create([
            'username' => $username,
            'password' => Security::hashPassword($password),
            'email'    => $email,
            'gold'     => 0,
            'vip_level' => 0,
        ]);
    }

    public static function changePassword(int $userId, string $newPassword): int
    {
        return self::update(['password' => Security::hashPassword($newPassword)], ['id' => $userId]);
    }

    public static function addGold(int $userId, int $amount, string $type = 'admin', string $remark = '', int $novelId = 0, int $chapterId = 0): void
    {
        $user = self::findById($userId, 'gold');
        $after = (int)($user['gold'] ?? 0) + $amount;
        self::update(['gold' => $after], ['id' => $userId]);

        GoldLogModel::create([
            'user_id'      => $userId,
            'type'         => $type,
            'gold_change'  => $amount,
            'gold_after'   => $after,
            'remark'       => $remark,
            'novel_id'     => $novelId,
            'chapter_id'   => $chapterId,
        ]);
    }

    public static function isVip(array $user): bool
    {
        if ((int)$user['vip_level'] < 1) return false;
        if ($user['vip_expire'] && strtotime($user['vip_expire']) < time()) return false;
        return true;
    }

    public static function isAuthor(int $userId): bool
    {
        $user = self::findById($userId, 'is_author');
        return $user && (int)$user['is_author'] === 1;
    }

    public static function getAuthors(int $page = 1, int $perPage = 20): array
    {
        $t = self::tableName();
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $cntRow = self::db()->query("SELECT COUNT(*) AS cnt FROM {$t} WHERE is_author = 1")->fetch();
        $total = (int)($cntRow['cnt'] ?? 0);

        $list = self::db()->query(
            "SELECT u.id, u.username, u.pen_name, u.avatar, u.signature, u.is_author, u.created_at,
                    (SELECT COUNT(*) FROM " . tb('novels') . " n WHERE n.user_id = u.id) AS work_count,
                    COALESCE((SELECT SUM(n.total_words) FROM " . tb('novels') . " n WHERE n.user_id = u.id), 0) AS total_words,
                    COALESCE((SELECT SUM(n.views) FROM " . tb('novels') . " n WHERE n.user_id = u.id), 0) AS total_views
             FROM {$t} u
             WHERE u.is_author = 1
             ORDER BY u.id DESC
             LIMIT {$offset}, {$perPage}"
        )->fetchAll();

        return [
            'list'       => $list,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => (int)ceil($total / $perPage),
        ];
    }

    public static function adminAuthorPaginate(string $keyword, int $page = 1, int $perPage = 20): array
    {
        $t = self::tableName();
        $where = 'u.is_author = 1';
        $params = [];
        if ($keyword !== '') {
            $where .= ' AND (u.username LIKE ? OR u.pen_name LIKE ?)';
            $like = '%' . $keyword . '%';
            $params[] = $like;
            $params[] = $like;
        }
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $cntRow = self::db()->query("SELECT COUNT(*) AS cnt FROM {$t} u WHERE {$where}", $params)->fetch();
        $total = (int)($cntRow['cnt'] ?? 0);
        $list = self::db()->query(
            "SELECT u.id, u.username, u.pen_name, u.gold, u.created_at,
                    (SELECT COUNT(*) FROM " . tb('novels') . " n WHERE n.user_id = u.id) AS work_count,
                    (SELECT COUNT(*) FROM " . tb('chapter_purchases') . " p
                        INNER JOIN " . tb('novels') . " n ON p.novel_id = n.id WHERE n.user_id = u.id) AS sales_count
             FROM {$t} u WHERE {$where} ORDER BY u.id DESC LIMIT {$offset}, {$perPage}",
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

    public static function adminVipStats(int $page = 1, int $perPage = 20): array
    {
        $t = self::tableName();
        $pt = ChapterPurchaseModel::tableName();

        $activeVip = (int)(self::db()->query("SELECT COUNT(*) c FROM {$t} WHERE vip_expire > NOW()")->fetch()['c'] ?? 0);
        $totalUsers = self::count();
        $monthBuyers = (int)(self::db()->query(
            "SELECT COUNT(DISTINCT user_id) c FROM {$pt} WHERE DATE_FORMAT(created_at,'%Y-%m') = DATE_FORMAT(NOW(),'%Y-%m')"
        )->fetch()['c'] ?? 0);
        $allBuyers = (int)(self::db()->query(
            "SELECT COUNT(DISTINCT user_id) c FROM {$pt}"
        )->fetch()['c'] ?? 0);

        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $vipUsers = self::db()->query(
            "SELECT id, username, pen_name, vip_level, vip_expire, gold,
                    (SELECT COUNT(*) FROM {$pt} p WHERE p.user_id = u.id) AS buy_count,
                    (SELECT COALESCE(SUM(price),0) FROM {$pt} p WHERE p.user_id = u.id) AS spend
             FROM {$t} u WHERE vip_expire > NOW() ORDER BY vip_expire DESC LIMIT {$offset}, {$perPage}"
        )->fetchAll();

        return array(
            'active_vip' => $activeVip,
            'total_users' => $totalUsers,
            'month_buyers' => $monthBuyers,
            'all_buyers' => $allBuyers,
            'list' => $vipUsers,
            'page' => $page,
            'totalPages' => (int)ceil($activeVip / $perPage),
        );
    }

    public static function displayNameMap(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));
        if (!$ids) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = self::db()->query(
            'SELECT id, pen_name, username FROM ' . self::tableName() . ' WHERE id IN (' . $placeholders . ')',
            $ids
        )->fetchAll();
        $map = [];
        foreach ($rows as $row) {
            $name = trim((string)($row['pen_name'] ?? ''));
            if ($name === '') $name = trim((string)($row['username'] ?? ''));
            $map[(int)$row['id']] = $name;
        }
        return $map;
    }
}
