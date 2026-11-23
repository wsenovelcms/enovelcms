<?php

class SecurityLogModel extends Model
{
    protected static string $table = 'security_logs';

    public static function typeLabels(): array
    {
        return [
            'blacklist_ip'  => t_admin('adm_slog_label_blacklist_ip', 'IP 黑名单'),
            'blacklist_ua'  => t_admin('adm_slog_label_blacklist_ua', 'UA 黑名单'),
            'empty_ua'      => t_admin('adm_slog_label_empty_ua', '空 UA'),
            'bad_ua'        => t_admin('adm_slog_label_bad_ua', '异常 UA'),
            'bad_headers'   => t_admin('adm_slog_label_bad_headers', '缺失浏览器头'),
            'rate_limit'    => t_admin('adm_slog_label_rate_limit', '频率限制'),
            'wechat'        => t_admin('adm_slog_label_wechat', '内置浏览器'),
        ];
    }

    public static function record(array $data): int
    {
        try {
            return self::create([
                'ip'       => excerpt((string)($data['ip'] ?? ''), 45, ''),
                'ua'       => excerpt((string)($data['ua'] ?? ''), 255, ''),
                'method'   => excerpt((string)($data['method'] ?? ''), 10, ''),
                'url'      => excerpt((string)($data['url'] ?? ''), 500, ''),
                'type'     => excerpt((string)($data['type'] ?? ''), 30, ''),
                'reason'   => excerpt((string)($data['reason'] ?? ''), 255, ''),
                'user_id'  => (int)($data['user_id'] ?? 0),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public static function adminLogs(array $filters, int $page = 1, int $perPage = 20): array
    {
        $t = self::tableName();
        $where = '1=1';
        $params = [];

        if (!empty($filters['type'])) {
            $where .= ' AND type = ?';
            $params[] = $filters['type'];
        }
        if (!empty($filters['ip'])) {
            $where .= ' AND ip LIKE ?';
            $params[] = '%' . $filters['ip'] . '%';
        }
        if (!empty($filters['keyword'])) {
            $where .= ' AND (url LIKE ? OR reason LIKE ? OR ua LIKE ?)';
            $kw = '%' . $filters['keyword'] . '%';
            array_push($params, $kw, $kw, $kw);
        }
        if (!empty($filters['start'])) {
            $where .= ' AND DATE(created_at) >= ?';
            $params[] = $filters['start'];
        }
        if (!empty($filters['end'])) {
            $where .= ' AND DATE(created_at) <= ?';
            $params[] = $filters['end'];
        }

        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $cntRow = self::db()->query("SELECT COUNT(*) AS cnt FROM {$t} WHERE {$where}", $params)->fetch();
        $total = (int)($cntRow['cnt'] ?? 0);
        $list = self::db()->query(
            "SELECT id, ip, ua, method, url, type, reason, user_id, created_at
             FROM {$t} WHERE {$where}
             ORDER BY id DESC LIMIT {$offset}, {$perPage}",
            $params
        )->fetchAll();

        return [
            'list'       => $list,
            'total'      => $total,
            'page'       => $page,
            'totalPages' => (int)ceil($total / $perPage),
        ];
    }

    public static function stats(): array
    {
        $t = self::tableName();
        $out = [
            'today'        => 0,
            'last7'        => 0,
            'unique_ip'    => 0,
            'by_type'      => [],
            'daily'        => [],
        ];

        try {
            $row = self::db()->query("SELECT COUNT(*) c FROM {$t} WHERE DATE(created_at) = CURDATE()")->fetch();
            $out['today'] = (int)($row['c'] ?? 0);

            $row = self::db()->query("SELECT COUNT(*) c FROM {$t} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch();
            $out['last7'] = (int)($row['c'] ?? 0);

            $row = self::db()->query("SELECT COUNT(DISTINCT ip) c FROM {$t} WHERE ip <> ''")->fetch();
            $out['unique_ip'] = (int)($row['c'] ?? 0);

            $rows = self::db()->query("SELECT type, COUNT(*) c FROM {$t} GROUP BY type")->fetchAll();
            foreach ($rows as $r) {
                $out['by_type'][$r['type']] = (int)$r['c'];
            }

            $rows = self::db()->query(
                "SELECT DATE(created_at) d, COUNT(*) c FROM {$t}
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                 GROUP BY DATE(created_at) ORDER BY d ASC"
            )->fetchAll();
            foreach ($rows as $r) {
                $out['daily'][$r['d']] = (int)$r['c'];
            }
        } catch (\Throwable $e) {

        }

        return $out;
    }

    public static function clearAll(): int
    {
        try {
            return self::db()->delete(static::$table, []);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public static function deleteByIp(string $ip): int
    {
        try {
            return self::delete(['ip' => $ip]);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public static function purgeOlderThan(int $days): int
    {
        if ($days <= 0) return 0;
        try {
            $t = self::tableName();
            return self::db()->query(
                "DELETE FROM {$t} WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$days]
            )->rowCount();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
