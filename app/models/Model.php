<?php

abstract class Model
{
    protected static Database $db;
    protected static string $table = '';

    public function __construct(Database $db)
    {
        self::$db = $db;
    }

    public static function db(): Database
    {
        return self::$db;
    }

    public static function tableName(): string
    {
        return self::$db->table(static::$table);
    }

    public static function findById(int $id, string $fields = '*'): ?array
    {
        return self::$db->findById(static::$table, $id, $fields);
    }

    public static function find(array $where, string $fields = '*'): ?array
    {
        return self::$db->find(static::$table, $where, $fields);
    }

    public static function all(array $where = [], string $order = '', int $limit = 0, int $offset = 0, string $fields = '*'): array
    {
        return self::$db->select(static::$table, $where, $order, $limit, $offset, $fields);
    }

    public static function paginate(array $where = [], string $order = '', int $page = 1, int $perPage = 20, string $fields = '*'): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $total = self::count($where);
        $list = self::$db->select(static::$table, $where, $order, $perPage, ($page - 1) * $perPage, $fields);
        return [
            'list'       => $list,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => (int)ceil($total / $perPage),
        ];
    }

    public static function count(array $where = []): int
    {
        return self::$db->count(static::$table, $where);
    }

    public static function sum(string $field, array $where = []): float
    {

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $field)) {
            throw new \InvalidArgumentException('非法聚合字段: ' . $field);
        }
        $row = self::$db->select(static::$table, $where, '', 0, 0, "COALESCE(SUM(`{$field}`),0) AS agg");
        return (float)($row[0]['agg'] ?? 0);
    }

    public static function create(array $data): int
    {
        $data = Hook::applyFilters('db_before_insert', $data, static::$table);
        $id = self::$db->insert(static::$table, $data);
        Hook::doAction('db_after_insert', $id, $data, static::$table);
        return $id;
    }

    public static function update(array $data, array $where): int
    {
        $data = Hook::applyFilters('db_before_update', $data, $where, static::$table);
        $affected = self::$db->update(static::$table, $data, $where);
        Hook::doAction('db_after_update', $affected, $where, static::$table);
        return $affected;
    }

    public static function updateById(array $data, int $id): int
    {
        return self::update($data, ['id' => $id]);
    }

    public static function delete(array $where): int
    {
        $affected = self::$db->delete(static::$table, $where);
        Hook::doAction('db_after_delete', $affected, $where, static::$table);
        return $affected;
    }

    public static function deleteById(int $id): int
    {
        return self::delete(['id' => $id]);
    }

    public static function increment(string $field, int $amount = 1, array $where = []): int
    {
        return self::$db->increment(static::$table, $field, $amount, $where);
    }

    public static function decrement(string $field, int $amount = 1, array $where = []): int
    {
        return self::$db->decrement(static::$table, $field, $amount, $where);
    }

    public static function setDb(Database $db): void
    {
        self::$db = $db;
    }

    public static function isSafeOrderSql(string $sql): bool
    {
        $sql = trim($sql);
        if ($sql === '') return false;
        $col = '[a-z_][a-z0-9_]*(?:\.[a-z_][a-z0-9_]*)?';
        $pattern = '/^' . $col . '\s+(?:ASC|DESC)(?:\s*,\s*' . $col . '\s+(?:ASC|DESC))*$/i';
        return (bool) preg_match($pattern, $sql);
    }
}
