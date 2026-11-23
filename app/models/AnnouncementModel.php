<?php

class AnnouncementModel extends Model
{
    protected static string $table = 'announcements';

    public static function sanitizeLink(string $link): string
    {
        $link = trim($link);
        if ($link === '') return '';
        if (preg_match('#^https?://#i', $link)) return $link;
        if ($link[0] === '/' || $link[0] === '#') return $link;
        return '';
    }

    public static function getActive(int $limit = 10): array
    {
        $t = self::tableName();
        $sql = "SELECT * FROM {$t} WHERE status = 1 AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY sort ASC, id DESC LIMIT {$limit}";
        $list = self::db()->query($sql)->fetchAll();
        foreach ($list as &$row) {
            $row['link'] = self::sanitizeLink((string)($row['link'] ?? ''));
        }
        unset($row);
        return $list;
    }

    public static function adminPaginate(int $page = 1, int $perPage = 15): array
    {
        $t = self::tableName();
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $cnt = (int)self::db()->query("SELECT COUNT(*) AS c FROM {$t}")->fetch()['c'];
        $list = self::db()->query("SELECT * FROM {$t} ORDER BY sort ASC, id DESC LIMIT {$offset}, {$perPage}")->fetchAll();
        return [
            'list' => $list,
            'total' => $cnt,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => (int)ceil($cnt / $perPage),
        ];
    }
}
