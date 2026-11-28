<?php

class ChapterModel extends Model
{
    protected static string $table = 'chapters';

    public static function findById(int $id, string $fields = '*'): ?array
    {
        $shard = ChapterShard::tableByChapterId($id);
        return self::$db->find($shard, ['id' => $id], $fields);
    }

    public static function getChapter(int $id): ?array
    {
        return self::findById($id);
    }

    public static function find(array $where, string $fields = '*'): ?array
    {

        if (isset($where['id'])) {
            $id = (int)$where['id'];
            $shard = ChapterShard::tableByChapterId($id);
            return self::$db->find($shard, $where, $fields);
        }

        [$sql, $params] = ChapterShard::unionAllAcrossShards(
            "SELECT {$fields}",
            array_values($where),
            self::buildWhereClause($where)
        );
        $sql = "SELECT * FROM ({$sql}) AS u LIMIT 1";
        $row = self::$db->query($sql, $params)->fetch();
        return $row ?: null;
    }

    public static function all(array $where = [], string $order = '', int $limit = 0, int $offset = 0, string $fields = '*'): array
    {

        if (isset($where['id'])) {
            $id = (int)$where['id'];
            $shard = ChapterShard::tableByChapterId($id);
            return self::$db->select($shard, $where, $order, $limit, $offset, $fields);
        }

        [$unionSql, $params] = ChapterShard::unionAllAcrossShards(
            "SELECT {$fields}",
            array_values($where),
            self::buildWhereClause($where)
        );
        $sql = "SELECT * FROM ({$unionSql}) AS u";
        if ($order) $sql .= " ORDER BY {$order}";
        if ($limit > 0) $sql .= " LIMIT {$limit}" . ($offset > 0 ? " OFFSET {$offset}" : '');
        return self::$db->query($sql, $params)->fetchAll();
    }

    public static function paginate(array $where = [], string $order = '', int $page = 1, int $perPage = 20, string $fields = '*'): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        if (empty($where) && preg_match('/^id\s+(asc|desc)$/i', trim($order)) === 1) {
            $list = self::sliceByShard(($page - 1) * $perPage, $perPage, stripos($order, 'desc') !== false, $fields);
            $total = ChapterShard::totalChapters();
        } else {
            $total = self::count($where);
            $list = self::all($where, $order, $perPage, ($page - 1) * $perPage, $fields);
        }
        return [
            'list'       => $list,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => (int)ceil($total / $perPage),
        ];
    }

    private static function sliceByShard(int $offset, int $limit, bool $desc, string $fields): array
    {
        if (!preg_match('/^[a-zA-Z0-9_*,\s]+$/', $fields)) $fields = '*';
        $list = [];
        foreach (ChapterShard::locateOffset($offset, $limit, $desc) as $seg) {
            $table = self::$db->table(ChapterShard::shardTable($seg['index']));
            $sql = "SELECT {$fields} FROM {$table} ORDER BY id " . ($desc ? 'DESC' : 'ASC')
                 . " LIMIT {$seg['rows']} OFFSET {$seg['offset']}";
            foreach (self::$db->query($sql)->fetchAll() as $row) $list[] = $row;
        }
        return $list;
    }

    public static function count(array $where = []): int
    {
        if (empty($where)) return ChapterShard::totalChapters();
        if (isset($where['id'])) {
            $id = (int)$where['id'];
            $shard = ChapterShard::tableByChapterId($id);
            return self::$db->count($shard, $where);
        }

        [$unionSql, $params] = ChapterShard::unionAllAcrossShards(
            'SELECT COUNT(*) AS cnt',
            array_values($where),
            self::buildWhereClause($where)
        );
        $row = self::$db->query("SELECT SUM(cnt) AS s FROM ({$unionSql}) AS t", $params)->fetch();
        return (int)($row['s'] ?? 0);
    }

    public static function create(array $data): int
    {
        $data = Hook::applyFilters('db_before_insert', $data, 'chapters');
        $shard = ChapterShard::activeShardTable();
        $id = self::$db->insert($shard, $data);
        Hook::doAction('db_after_insert', $id, $data, 'chapters');
        if (!empty($data['novel_id'])) self::forgetNovelList((int)$data['novel_id']);
        return $id;
    }

    private static array $listFields = ['id' => 1, 'novel_id' => 1, 'volume_id' => 1, 'sort' => 1, 'title' => 1,
        'is_vip' => 1, 'price' => 1, 'word_count' => 1, 'file_path' => 1];

    private static function touchNovelList(array $where): int
    {
        if (isset($where['novel_id'])) return (int)$where['novel_id'];
        if (isset($where['id'])) {
            $row = self::findById((int)$where['id'], 'novel_id');
            return (int)($row['novel_id'] ?? 0);
        }
        if (isset($where['volume_id'])) {
            $vol = VolumeModel::findById((int)$where['volume_id'], 'novel_id');
            return (int)($vol['novel_id'] ?? 0);
        }
        return 0;
    }

    public static function update(array $data, array $where): int
    {
        $data = Hook::applyFilters('db_before_update', $data, $where, 'chapters');
        $listChanged = (bool)array_intersect_key($data, self::$listFields);

        if (isset($where['id'])) {
            $id = (int)$where['id'];
            $novelId = $listChanged ? self::touchNovelList($where) : 0;
            $shard = ChapterShard::tableByChapterId($id);
            $affected = self::$db->update($shard, $data, $where);
            Hook::doAction('db_after_update', $affected, $where, 'chapters');
            if ($affected > 0 && $novelId > 0) self::forgetNovelList($novelId);
            return $affected;
        }

        $total = 0;
        foreach (ChapterShard::allShards() as $idx => $table) {
            $total += self::$db->update($table, $data, $where);
        }
        Hook::doAction('db_after_update', $total, $where, 'chapters');
        if ($total > 0 && $listChanged) {
            $novelId = self::touchNovelList($where);
            if ($novelId > 0) self::forgetNovelList($novelId);
        }
        return $total;
    }

    public static function updateById(array $data, int $id): int
    {
        return self::update($data, ['id' => $id]);
    }

    public static function delete(array $where): int
    {
        $novelId = self::touchNovelList($where);
        if (isset($where['id'])) {
            $id = (int)$where['id'];
            $shard = ChapterShard::tableByChapterId($id);
            $affected = self::$db->delete($shard, $where);
            Hook::doAction('db_after_delete', $affected, $where, 'chapters');
        } else {
            $total = 0;
            foreach (ChapterShard::allShards() as $idx => $table) {
                $total += self::$db->delete($table, $where);
            }
            Hook::doAction('db_after_delete', $total, $where, 'chapters');
            $affected = $total;
        }
        if ($affected > 0) {
            ChapterShard::invalidateCounts(isset($where['id']) ? ChapterShard::shardIndex((int)$where['id']) : null);
            if ($novelId > 0) self::forgetNovelList($novelId);
        }
        return $affected;
    }

    public static function deleteById(int $id): int
    {
        return self::delete(['id' => $id]);
    }

    private static function novelListKey(int $novelId): string
    {
        return 'novel_chapter_list_' . $novelId;
    }

    private static function novelMarker(int $novelId): string
    {
        $row = self::$db->find('novels', ['id' => $novelId], 'total_chapters, last_chapter_id, updated_at');
        if (!$row) return '';
        return (string)($row['total_chapters'] ?? '') . '|' . (string)($row['last_chapter_id'] ?? '') . '|' . (string)($row['updated_at'] ?? '');
    }

    public static function forgetNovelList(int $novelId): void
    {
        if ($novelId > 0) Cache::delete(self::novelListKey($novelId));
    }

    public static function getListByNovel(int $novelId, string $order = ''): array
    {
        if ($order !== '') return self::queryListByNovel($novelId, $order);
        $key = self::novelListKey($novelId);
        $marker = self::novelMarker($novelId);
        $cached = Cache::get($key);
        if (is_array($cached) && ($cached['marker'] ?? null) === $marker) return $cached['list'];
        $list = self::queryListByNovel($novelId, '');
        Cache::set($key, ['marker' => $marker, 'list' => $list], 600);
        return $list;
    }

    private const LIST_FIELDS = 'c.id, c.novel_id, c.volume_id, c.title, c.word_count, c.file_path, c.source_url, c.source_chapter_id, c.sort, c.is_vip, c.price, c.created_at';

    private static function queryListByNovel(int $novelId, string $order): array
    {
        $volTable = self::$db->table('volumes');
        $join = " AS c LEFT JOIN {$volTable} v ON v.id = c.volume_id";
        [$unionSql, $params] = ChapterShard::unionAllAcrossShards(
            'SELECT ' . self::LIST_FIELDS . ', COALESCE(v.sort, 0) AS volume_sort',
            [$novelId],
            'c.novel_id = ?',
            $join
        );
        if ($order === '') $order = 'volume_sort ASC, volume_id ASC, sort ASC, id ASC';
        $sql = "SELECT * FROM ({$unionSql}) AS u ORDER BY {$order}";
        return self::$db->query($sql, $params)->fetchAll();
    }

    public static function paginateByNovel(int $novelId, int $page = 1, int $perPage = 50): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $volTable = self::$db->table('volumes');
        $join = " AS c LEFT JOIN {$volTable} v ON v.id = c.volume_id";
        [$unionSql, $params] = ChapterShard::unionAllAcrossShards(
            'SELECT ' . self::LIST_FIELDS . ', COALESCE(v.sort, 0) AS volume_sort',
            [$novelId],
            'c.novel_id = ?',
            $join
        );
        $total = self::count(array('novel_id' => $novelId));
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT * FROM ({$unionSql}) AS u"
             . " ORDER BY volume_sort ASC, volume_id ASC, sort ASC, id ASC"
             . " LIMIT {$perPage} OFFSET {$offset}";
        $list = self::$db->query($sql, $params)->fetchAll();
        return array(
            'list'       => $list,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => (int)ceil($total / $perPage),
        );
    }

    public static function getMaxSort(int $novelId): int
    {
        [$unionSql, $params] = ChapterShard::unionAllAcrossShards(
            'SELECT MAX(sort) AS m',
            [$novelId],
            'novel_id = ?'
        );
        $row = self::$db->query("SELECT MAX(m) AS maxM FROM ({$unionSql}) AS t", $params)->fetch();
        $raw = $row['maxM'] ?? null;
        if ($raw === null) return -1;
        return (int)$raw;
    }

    public static function getMaxSortInVolume(int $volumeId): int
    {
        [$unionSql, $params] = ChapterShard::unionAllAcrossShards(
            'SELECT MAX(sort) AS m',
            [$volumeId],
            'volume_id = ?'
        );
        $row = self::$db->query("SELECT MAX(m) AS maxM FROM ({$unionSql}) AS t", $params)->fetch();
        $raw = $row['maxM'] ?? null;
        return $raw === null ? 0 : (int)$raw;
    }

    public static function renumberVolume(int $volumeId): int
    {
        $rows = self::all(['volume_id' => $volumeId], 'sort ASC, id ASC');
        $i = 0;
        foreach ($rows as $row) {
            $i++;
            if ((int)$row['sort'] !== $i) {
                self::update(['sort' => $i], ['id' => (int)$row['id']]);
            }
        }
        return $i;
    }

    public static function deleteByNovel(int $novelId): int
    {
        return self::delete(['novel_id' => $novelId]);
    }

    public static function countByNovel(int $novelId): int
    {
        return self::count(['novel_id' => $novelId]);
    }

    public static function getPrevNext(int $novelId, int $chapterId): array
    {
        $list = self::getListByNovel($novelId);
        $prev = null; $next = null; $found = false;
        foreach ($list as $ch) {
            if ((int)$ch['id'] === $chapterId) { $found = true; continue; }
            if (!$found) $prev = $ch;
            else if (!$next) { $next = $ch; break; }
        }
        return ['prev' => $prev, 'next' => $next];
    }

    public static function chapterFilePath(string $filePath): string
    {
        $fp = ltrim($filePath, '/\\');
        if (str_starts_with($fp, 'data/chapters/')) {
            return ROOT_PATH . $fp;
        }
        return ROOT_PATH . 'data/chapters/' . $fp;
    }

    public static function deleteChapterFile(array $chapter): bool
    {
        if (empty($chapter['file_path'])) return false;
        $root = realpath(ROOT_PATH . 'data/chapters/');
        $file = self::chapterFilePath((string)$chapter['file_path']);
        $real = realpath($file);
        if ($root === false || $real === false) return false;
        if (strpos($real, $root . DIRECTORY_SEPARATOR) !== 0) return false;
        return @unlink($real);
    }

    public static function getContent(array $chapter): string
    {
        if (empty($chapter['file_path'])) return '';
        $fp = ltrim($chapter['file_path'], '/\\');
        if (str_starts_with($fp, 'data/chapters/')) {
            $file = ROOT_PATH . $fp;
        } else {
            $file = ROOT_PATH . 'data/chapters/' . $fp;
        }
        $raw = self::readFile($file);
        if ($raw === '') return '';
        $raw = str_replace(array("\r\n", "\r"), "\n", $raw);
        if (preg_match('#<(p|div|br|h[1-6]|li|blockquote)\b#i', $raw)) {
            $raw = CommonService::htmlToPlainText($raw);
        }
        $lines = array();
        foreach (explode("\n", $raw) as $l) {
            $l = rtrim($l, " \t");
            if ($l !== '') $lines[] = $l;
        }
        $html = '';
        foreach ($lines as $p) {
            $html .= '<p>' . htmlspecialchars($p, ENT_QUOTES, 'UTF-8') . '</p>';
        }
        return $html;
    }

    public static function getRawContent(array $chapter): string
    {
        if (empty($chapter['file_path'])) return '';
        $fp = ltrim($chapter['file_path'], '/\\');
        if (str_starts_with($fp, 'data/chapters/')) {
            $file = ROOT_PATH . $fp;
        } else {
            $file = ROOT_PATH . 'data/chapters/' . $fp;
        }
        $raw = self::readFile($file);
        if ($raw === '') return '';
        $raw = str_replace(array("\r\n", "\r"), "\n", $raw);
        if (preg_match('#<(p|div|br|h[1-6]|li|blockquote)\b#i', $raw)) {
            $raw = CommonService::htmlToPlainText($raw);
        }
        return trim($raw, "\n");
    }

    public static function saveContent(int $novelId, int $chapterId, string $content): string
    {
        $dir = ROOT_PATH . 'data/chapters/' . $novelId . '/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $filename = $chapterId . '_' . md5($chapterId . time()) . '.txt';
        file_put_contents($dir . $filename, $content);
        return $novelId . '/' . $filename;
    }

    public static function writeContent(int $novelId, string $content): string
    {
        $dir = ROOT_PATH . 'data/chapters/' . $novelId . '/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $filename = bin2hex(random_bytes(8)) . '.txt';
        file_put_contents($dir . $filename, $content);
        return $novelId . '/' . $filename;
    }

    private static function readFile(string $file): string
    {
        if (!is_file($file)) return '';
        return (string)@file_get_contents($file);
    }

    public static function getPrice(array $chapter, array $settings = []): int
    {
        if (!$chapter['is_vip']) return 0;
        if ((int)$chapter['price'] > 0) return (int)$chapter['price'];
        $per300 = (int)($settings['vip_default_price_per_300'] ?? 1);
        $words = max(1, (int)$chapter['word_count']);
        return max(1, ceil($words / 300) * $per300);
    }

    public static function tableName(): string
    {
        return self::$db->table(ChapterShard::shardTable(0));
    }

    private static function buildWhereClause(array $where): string
    {
        if (empty($where)) return '';
        $parts = [];
        foreach ($where as $key => $value) {
            if (strpos($key, '?') !== false) {
                $parts[] = $key;
            } else {
                $parts[] = "`{$key}` = ?";
            }
        }
        return implode(' AND ', $parts);
    }
}
