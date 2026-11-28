<?php

class ChapterShard
{

    public const SHARD_SIZE = 1000000;

    public const BASE_TABLE = 'chapters';

    private static ?array $shardCache = null;

    private static ?int $activeIndexCache = null;

    private const COUNT_CACHE_KEY = 'chapter_shard_counts';

    private const COUNT_TTL = 600;

    public static function shardIndex(int $id): int
    {
        return intdiv($id, self::SHARD_SIZE);
    }

    public static function shardTable(int $index): string
    {
        return self::BASE_TABLE . '_' . $index;
    }

    public static function tableByChapterId(int $id): string
    {
        return self::shardTable(self::shardIndex($id));
    }

    public static function allShards(): array
    {
        if (self::$shardCache !== null) {
            return self::$shardCache;
        }

        $db = Model::db();
        $like = self::BASE_TABLE . '\_%';
        $stmt = $db->query(
            "SELECT TABLE_NAME AS t FROM information_schema.tables
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME LIKE ?
             ORDER BY TABLE_NAME",
            [DB_NAME, DB_PREFIX . $like]
        );
        $rows = $stmt->fetchAll();

        $shards = [];
        foreach ($rows as $r) {
            $full = $r['t'];

            $bare = $full;
            if (DB_PREFIX !== '' && strncasecmp($full, DB_PREFIX, strlen(DB_PREFIX)) === 0) {
                $bare = substr($full, strlen(DB_PREFIX));
            }
            if (preg_match('/^chapters_(\d+)$/i', $bare, $m)) {
                $shards[(int)$m[1]] = $bare;
            }
        }

        if (empty($shards)) {
            $shards[0] = self::shardTable(0);
        }

        ksort($shards);
        self::$shardCache = $shards;
        return $shards;
    }

    public static function invalidateCache(): void
    {
        self::$shardCache = null;
        self::$activeIndexCache = null;
    }

    public static function activeShardIndex(): int
    {
        $shards = self::allShards();

        if (self::$activeIndexCache !== null) {
            $idx = self::$activeIndexCache;
            if (isset($shards[$idx]) && self::shardNextId($shards[$idx]) <= ($idx + 1) * self::SHARD_SIZE - 1) {
                return $idx;
            }
            self::$activeIndexCache = null;
        }

        $expect = 0;
        foreach ($shards as $index => $table) {
            if ($index !== $expect) {
                return self::activate($expect);
            }
            $top = ($index + 1) * self::SHARD_SIZE - 1;
            $next = self::shardNextId($table);
            if ($next <= $top) {
                if ($index > 0 && $next < $index * self::SHARD_SIZE) {
                    self::alignShard($index, $table);
                }
                self::$activeIndexCache = $index;
                return $index;
            }
            if ($next > $top + 1) {
                self::alignShard($index, $table);
            }
            $expect++;
        }

        return self::activate(max(array_keys($shards)) + 1);
    }

    public static function activeShardTable(): string
    {
        return self::shardTable(self::activeShardIndex());
    }

    private static function activate(int $index): int
    {
        self::ensureShard($index);
        self::$activeIndexCache = $index;
        return $index;
    }

    private static function ensureShard(int $index): string
    {
        $db = Model::db();
        $newTable = self::shardTable($index);

        if ($db->tableExists($newTable)) {
            self::alignShard($index, $newTable);
            return $newTable;
        }

        $shards = self::allShards();
        $baseTable = self::shardTable(min(array_keys($shards)));
        if (!$db->tableExists($baseTable)) {
            throw new \RuntimeException(t_admin('adm_shard_base_missing', '基础分片表 %s 不存在，无法创建分片 %s', $baseTable, $newTable));
        }

        try {
            $db->query('CREATE TABLE IF NOT EXISTS ' . $db->table($newTable) . ' LIKE ' . $db->table($baseTable));
            $db->query('ALTER TABLE ' . $db->table($newTable) . ' AUTO_INCREMENT = ' . ($index * self::SHARD_SIZE));
        } catch (\PDOException $e) {
            throw new \RuntimeException(t_admin('adm_shard_create_fail', '创建分片表 %s 失败：%s', $newTable, $e->getMessage()));
        }

        self::invalidateCache();
        return $newTable;
    }

    private static function alignShard(int $index, string $table): int
    {
        $floor = $index * self::SHARD_SIZE;
        $top = ($index + 1) * self::SHARD_SIZE - 1;
        $ai = self::autoIncrement($table);
        $maxId = self::maxId($table);
        if ($maxId > $top) {
            throw new \RuntimeException(t_admin('adm_shard_id_over', '分片 %s 已有 id %d 超过本片上限 %d，自增却停在 %d；先把这些章节迁到后面的分片再继续发布', $table, $maxId, $top, $ai));
        }
        if ($ai >= $floor) {
            return $ai;
        }
        if ($maxId >= $floor) {
            throw new \RuntimeException(t_admin('adm_shard_id_over', '分片 %s 已有 id %d 超过本片上限 %d，自增却停在 %d；先把这些章节迁到后面的分片再继续发布', $table, $maxId, $top, $ai));
        }

        $db = Model::db();
        try {
            $db->query('ALTER TABLE ' . $db->table($table) . ' AUTO_INCREMENT = ' . $floor);
        } catch (\PDOException $e) {
            throw new \RuntimeException(t_admin('adm_shard_align_fail', '分片 %s 自增起点对齐到 %d 失败：%s', $table, $floor, $e->getMessage()));
        }

        self::invalidateCache();
        return $floor;
    }

    private static function autoIncrement(string $table): int
    {
        $db = Model::db();
        $row = $db->query(
            'SELECT AUTO_INCREMENT AS ai FROM information_schema.tables WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [DB_NAME, DB_PREFIX . $table]
        )->fetch();
        return (int)($row['ai'] ?? 0);
    }

    private static function maxId(string $table): int
    {
        $db = Model::db();
        try {
            $row = $db->query('SELECT COALESCE(MAX(id),0) AS m FROM ' . $db->table($table))->fetch();
            return (int)($row['m'] ?? 0);
        } catch (\PDOException $e) {
            return 0;
        }
    }

    private static function shardNextId(string $table): int
    {
        $ai = self::autoIncrement($table);
        return $ai > 0 ? $ai : self::maxId($table) + 1;
    }

    public static function addColumnToShards(string $column, string $definition): void
    {
        $db = Model::db();
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        if ($column === '') return;
        foreach (self::allShards() as $table) {
            if (!$db->tableExists($table)) continue;
            $db->addColumn($table, $column, $definition);
        }
        self::invalidateCache();
    }

    public static function groupIdsByShard(array $ids): array
    {
        $groups = [];
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id <= 0) continue;
            $idx = self::shardIndex($id);
            $groups[$idx][] = $id;
        }
        return $groups;
    }

    public static function unionAllAcrossShards(string $innerSelect, array $whereBind = [], string $extraWhere = '', string $join = ''): array
    {
        $shards = self::allShards();
        $unionParts = [];
        $params = [];
        foreach ($shards as $idx => $table) {
            $part = "({$innerSelect} FROM " . Model::db()->table($table) . $join;
            if ($extraWhere !== '') {
                $part .= ' WHERE ' . $extraWhere;
                $params = array_merge($params, $whereBind);
            }
            $part .= ')';
            $unionParts[] = $part;
        }
        return [implode(' UNION ALL ', $unionParts), $params];
    }

    public static function shardCounts(): array
    {
        $shards = self::allShards();
        $cached = Cache::get(self::COUNT_CACHE_KEY);
        $state = is_array($cached) && isset($cached['counts'], $cached['markers'], $cached['at'])
            ? $cached
            : ['counts' => [], 'markers' => [], 'at' => time()];
        if (time() - (int)$state['at'] > self::COUNT_TTL) {
            $state = ['counts' => [], 'markers' => [], 'at' => time()];
        }
        foreach (array_keys($state['counts']) as $idx) {
            if (!isset($shards[$idx])) {
                unset($state['counts'][$idx], $state['markers'][$idx]);
            }
        }
        $markers = self::shardMarkers();
        $db = Model::db();
        foreach ($shards as $idx => $table) {
            $marker = $markers[$idx] ?? '';
            if (isset($state['counts'][$idx]) && ($state['markers'][$idx] ?? null) === $marker) continue;
            $row = $db->query('SELECT COUNT(*) AS cnt FROM ' . $db->table($table))->fetch();
            $state['counts'][$idx] = (int)($row['cnt'] ?? 0);
            $state['markers'][$idx] = $marker;
            unset($row);
            ksort($state['counts']);
            Cache::set(self::COUNT_CACHE_KEY, $state, self::COUNT_TTL);
        }
        return $state['counts'];
    }

    private static function shardMarkers(): array
    {
        $db = Model::db();
        try {
            $db->query('SET SESSION information_schema_stats_expiry = 0');
        } catch (\Throwable $e) {
        }
        $rows = $db->query(
            'SELECT TABLE_NAME AS t, AUTO_INCREMENT AS a, UPDATE_TIME AS u FROM information_schema.tables
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME LIKE ?',
            [DB_NAME, DB_PREFIX . self::BASE_TABLE . '\_%']
        )->fetchAll();
        $markers = [];
        foreach ($rows as $r) {
            $bare = $r['t'];
            if (DB_PREFIX !== '' && strncasecmp($bare, DB_PREFIX, strlen(DB_PREFIX)) === 0) {
                $bare = substr($bare, strlen(DB_PREFIX));
            }
            if (preg_match('/^' . self::BASE_TABLE . '_(\d+)$/i', $bare, $m)) {
                $markers[(int)$m[1]] = ($r['a'] === null ? '0' : (string)$r['a']) . '|' . ($r['u'] === null ? '' : (string)$r['u']);
            }
        }
        return $markers;
    }

    public static function totalChapters(): int
    {
        return array_sum(self::shardCounts());
    }

    public static function invalidateCounts(?int $index = null): void
    {
        if ($index === null) {
            Cache::delete(self::COUNT_CACHE_KEY);
            return;
        }
        $cached = Cache::get(self::COUNT_CACHE_KEY);
        if (!is_array($cached) || !isset($cached['counts'][$index])) return;
        unset($cached['counts'][$index], $cached['markers'][$index]);
        Cache::set(self::COUNT_CACHE_KEY, $cached, self::COUNT_TTL);
    }

    public static function locateOffset(int $offset, int $limit, bool $desc = true): array
    {
        $counts = self::shardCounts();
        if ($desc) $counts = array_reverse($counts, true);
        $offset = max(0, $offset);
        $limit = max(0, $limit);
        $want = $offset + $limit;
        $plan = [];
        $start = 0;
        foreach ($counts as $idx => $cnt) {
            $end = $start + $cnt;
            $from = max($offset, $start);
            $to = min($want, $end);
            if ($to > $from) {
                $plan[] = ['index' => (int)$idx, 'offset' => $from - $start, 'rows' => $to - $from];
            }
            $start = $end;
            if ($start >= $want) break;
        }
        return $plan;
    }
}
