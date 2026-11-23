<?php

class TagModel extends Model
{
    protected static string $table = 'tags';

    private static ?array $allCache = null;

    public static function getAllTags(): array
    {
        if (self::$allCache !== null) return self::$allCache;
        try {
            self::$allCache = self::all([], 'sort ASC, id ASC', 0, 0, 'id, name, sort');
        } catch (\Throwable $e) {
            self::$allCache = [];
        }
        return self::$allCache;
    }

    public static function paginate(array $where = [], string $order = '', int $page = 1, int $perPage = 20, string $fields = '*'): array
    {
        try {
            return parent::paginate($where, $order, $page, $perPage, $fields);
        } catch (\Throwable $e) {
            return array(
                'list' => array(), 'total' => 0, 'page' => max(1, $page),
                'perPage' => max(1, $perPage), 'totalPages' => 1,
            );
        }
    }

    public static function nameExists(string $name, int $excludeId = 0): bool
    {
        $row = self::find(['name' => $name], 'id');
        if (!$row) return false;
        return $excludeId <= 0 || (int)$row['id'] !== $excludeId;
    }

    public static function addTag(string $name, int $sort = 0): int
    {
        $id = self::create(['name' => $name, 'sort' => $sort]);
        self::clearTagCache();
        return $id;
    }

    public static function updateTag(int $id, string $name, int $sort = 0): int
    {
        $affected = self::updateById(['name' => $name, 'sort' => $sort], $id);
        self::clearTagCache();
        return $affected;
    }

    public static function deleteTag(int $id): int
    {
        $affected = self::deleteById($id);
        if (self::$db->tableExists('novel_tags')) {
            self::$db->delete('novel_tags', ['tag_id' => $id]);
        }
        self::clearTagCache();
        return $affected;
    }

    public static function getTagIdsByNovel(int $novelId): array
    {
        try {
            $rows = self::$db->select('novel_tags', ['novel_id' => $novelId], '', 0, 0, 'tag_id');
        } catch (\Throwable $e) {
            return [];
        }
        return array_map(static fn($r) => (int)$r['tag_id'], $rows);
    }

    public static function getTagsByNovel(int $novelId): array
    {
        $ids = self::getTagIdsByNovel($novelId);
        if (empty($ids)) return [];
        $map = [];
        foreach (self::getAllTags() as $t) $map[(int)$t['id']] = $t;
        $result = [];
        foreach ($ids as $id) {
            if (isset($map[$id])) $result[] = $map[$id];
        }
        return $result;
    }

    public static function getTagsByNovels(array $novelIds): array
    {
        $novelIds = array_values(array_unique(array_map('intval', $novelIds)));
        $result = array_fill_keys($novelIds, []);
        if (empty($novelIds)) return $result;
        $placeholders = implode(',', array_fill(0, count($novelIds), '?'));
        $sql = "SELECT nt.novel_id, nt.tag_id, t.name, t.sort
                FROM " . self::$db->table('novel_tags') . " nt
                INNER JOIN " . self::$db->table('tags') . " t ON t.id = nt.tag_id
                WHERE nt.novel_id IN ($placeholders)
                ORDER BY t.sort ASC, t.id ASC";
        try {
            $rows = self::$db->query($sql, $novelIds)->fetchAll();
        } catch (\Throwable $e) {
            return $result;
        }
        foreach ($rows as $r) {
            $result[(int)$r['novel_id']][] = [
                'id'   => (int)$r['tag_id'],
                'name' => $r['name'],
                'sort' => (int)$r['sort'],
            ];
        }
        return $result;
    }

    public static function syncNovelTags(int $novelId, array $tagIds): void
    {
        if (!self::$db->tableExists('novel_tags')) return;
        $tagIds = array_values(array_unique(array_map('intval', array_filter($tagIds, static fn($v) => (int)$v > 0))));
        $validIds = [];
        foreach (self::getAllTags() as $t) $validIds[(int)$t['id']] = true;
        $tagIds = array_values(array_filter($tagIds, static fn($id) => isset($validIds[$id])));
        self::$db->delete('novel_tags', ['novel_id' => $novelId]);
        foreach ($tagIds as $tagId) {
            self::$db->insert('novel_tags', ['novel_id' => $novelId, 'tag_id' => $tagId]);
        }
    }

    public static function getNovelIdsByTag(int $tagId): array
    {
        $rows = self::$db->select('novel_tags', ['tag_id' => $tagId], '', 0, 0, 'novel_id');
        return array_map(static fn($r) => (int)$r['novel_id'], $rows);
    }

    public static function getMaxTags(): int
    {
        return max(1, (int)SettingModel::get('novel_tag_max', 3));
    }

    public static function getUsageCounts(): array
    {
        try {
            $rows = self::$db->query("SELECT tag_id, COUNT(*) AS cnt FROM "
                . self::$db->table('novel_tags') . " GROUP BY tag_id")->fetchAll();
        } catch (\Throwable $e) {
            return array();
        }
        $result = array();
        foreach ($rows as $r) {
            $result[(int)$r['tag_id']] = (int)$r['cnt'];
        }
        return $result;
    }

    public static function clearTagCache(): void
    {
        self::$allCache = null;
    }
}
