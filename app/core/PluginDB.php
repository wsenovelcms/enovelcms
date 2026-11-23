<?php

class PluginDB
{

    private static $db = null;
    private static $prefix = '';

    public static function setDb($db): void
    {
        self::$db = $db;
    }

    public static function setPlugin(string $pluginId): void
    {
        self::$prefix = $pluginId !== '' ? $pluginId . '_' : '';
    }

    private static function t(string $table): string
    {
        $table = preg_replace('/[^a-z0-9_]/i', '', $table);

        if (self::$prefix !== '' && strpos($table, self::$prefix) === 0) {
            return $table;
        }
        return self::$prefix . $table;
    }

    public static function insert(string $table, array $data): int
    {
        return self::$db->insert(self::t($table), $data);
    }

    public static function find(string $table, array $where = []): ?array
    {
        return self::$db->find(self::t($table), $where);
    }

    public static function findById(string $table, int $id): ?array
    {
        return self::$db->findById(self::t($table), $id);
    }

    public static function all(string $table, array $where = [], string $order = '', int $limit = 0, int $offset = 0): array
    {
        return self::$db->select(self::t($table), $where, $order, $limit, $offset);
    }

    public static function update(string $table, array $data, array $where): int
    {
        return self::$db->update(self::t($table), $data, $where);
    }

    public static function delete(string $table, array $where): int
    {
        return self::$db->delete(self::t($table), $where);
    }

    public static function count(string $table, array $where = []): int
    {
        return self::$db->count(self::t($table), $where);
    }

    public static function paginate(string $table, array $where = [], int $page = 1, int $pageSize = 20, string $order = ''): array
    {
        $page = max(1, $page);
        $pageSize = max(1, $pageSize);
        $total = self::count($table, $where);
        $pages = $total > 0 ? (int)ceil($total / $pageSize) : 0;
        $rows = self::$db->select(self::t($table), $where, $order, $pageSize, ($page - 1) * $pageSize);
        return ['rows' => $rows, 'total' => $total, 'pages' => $pages, 'page' => $page, 'pageSize' => $pageSize];
    }

    public static function search(string $table, string $keyword, array $fields, int $page = 1, int $pageSize = 20, string $order = ''): array
    {
        $realTable = self::$db->table(self::t($table));
        $page = max(1, $page);
        $pageSize = max(1, $pageSize);
        $kw = '%' . $keyword . '%';

        $whereSql = '';
        $params = [];
        if ($keyword !== '' && $fields) {
            $likes = [];
            foreach ($fields as $f) {
                $f = preg_replace('/[^a-z0-9_]/i', '', $f);
                if ($f === '') continue;
                $likes[] = "`{$f}` LIKE ?";
                $params[] = $kw;
            }
            if ($likes) $whereSql = 'WHERE (' . implode(' OR ', $likes) . ')';
        }
        $order = $order !== '' ? 'ORDER BY ' . preg_replace('/[^a-z0-9_,\s]/i', '', $order) : '';

        $cnt = self::$db->query("SELECT COUNT(*) AS c FROM {$realTable} {$whereSql}", $params)->fetch();
        $total = (int)($cnt['c'] ?? 0);
        $pages = $total > 0 ? (int)ceil($total / $pageSize) : 0;
        $offset = ($page - 1) * $pageSize;
        $rows = self::$db->query(
            "SELECT * FROM {$realTable} {$whereSql} {$order} LIMIT {$pageSize} OFFSET {$offset}",
            $params
        )->fetchAll();

        return ['rows' => $rows, 'total' => $total, 'pages' => $pages, 'page' => $page, 'pageSize' => $pageSize];
    }
}
