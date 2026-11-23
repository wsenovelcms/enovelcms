<?php

class CategoryModel extends Model
{
    protected static string $table = 'categories';

    private static ?array $allCache = null;

    public static function getAllCategories(): array
    {
        if (self::$allCache !== null) return self::$allCache;
        try {
            self::$allCache = self::all([], 'sort ASC, id ASC', 0, 0, 'id, name, parent_id, sort');
        } catch (\Throwable $e) {
            self::$allCache = [];
        }
        return self::$allCache;
    }

    public static function getCategoryTree(): array
    {
        $all = self::getAllCategories();
        $tree = [];
        foreach ($all as $c) {
            if ((int)($c['parent_id'] ?? 0) <= 0) {
                $c['children'] = [];
                $tree[(int)$c['id']] = $c;
            }
        }
        foreach ($all as $c) {
            $pid = (int)($c['parent_id'] ?? 0);
            if ($pid > 0 && isset($tree[$pid])) {
                $tree[$pid]['children'][] = $c;
            }
        }
        return $tree;
    }

    public static function getTree(): array
    {
        return self::getCategoryTree();
    }

    public static function getCategoryAndChildIds(int $categoryId): array
    {
        if ($categoryId <= 0) return [];
        $ids = [$categoryId];
        foreach (self::getAllCategories() as $c) {
            if ((int)($c['parent_id'] ?? 0) === $categoryId) {
                $ids[] = (int)$c['id'];
            }
        }
        return array_values(array_unique($ids));
    }

    public static function getChildIds(int $parentId): array
    {
        return self::getCategoryAndChildIds($parentId);
    }

    public static function addCategory(string $name, int $parentId = 0, int $sort = 0): int
    {
        return self::create(['name' => $name, 'parent_id' => $parentId, 'sort' => $sort]);
    }
}
