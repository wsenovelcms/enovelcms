<?php

class DiyBlockModel extends Model
{
    protected static string $table = 'diy_blocks';

    public static function getByName(string $name): ?array
    {
        return self::find(['name' => $name]);
    }

    public static function getAllBlocks(): array
    {
        return self::all([], 'id ASC');
    }

    public static function cacheData(int $id, string $data): void
    {
        self::updateById(['data' => $data, 'updated_at' => date('Y-m-d H:i:s')], $id);
    }

    public static function invalidate(int $id): void
    {
        $t = self::tableName();
        self::db()->query("UPDATE {$t} SET data = NULL, updated_at = NULL WHERE id = ?", [$id]);
    }

    public static function invalidateAll(): void
    {
        $t = self::tableName();
        self::db()->query("UPDATE {$t} SET data = NULL, updated_at = NULL");
    }

    public static function nameExists(string $name, int $exceptId = 0): bool
    {
        $t = self::tableName();
        $row = self::db()->query(
            "SELECT id FROM {$t} WHERE name = ? AND id <> ? LIMIT 1",
            [$name, $exceptId]
        )->fetch();
        return !empty($row);
    }
}
