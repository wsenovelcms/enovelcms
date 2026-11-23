<?php

class CrawlProxyModel extends Model
{
    protected static string $table = 'crawl_proxies';

    public static function getActive(): array
    {
        return self::all(['status' => 'active'], 'id ASC');
    }

    public static function recordResult(int $id, bool $success): void
    {
        if ($success) {
            self::increment('success_count', 1, ['id' => $id]);
        } else {
            self::increment('fail_count', 1, ['id' => $id]);
        }
    }

    public static function getAll(): array
    {
        return self::all(array(), 'status DESC, id DESC');
    }

    public static function getNext(): ?array
    {
        $t = self::tableName();
        $row = self::db()->query(
            "SELECT * FROM {$t} WHERE status='active' ORDER BY last_used ASC, id ASC LIMIT 1"
        )->fetch();
        if ($row) {
            self::db()->query("UPDATE {$t} SET last_used=NOW() WHERE id=?", array((int)$row['id']));
            return $row;
        }
        return null;
    }

    public static function markSuccess(int $id): void
    {
        $t = self::tableName();
        self::db()->query(
            "UPDATE {$t} SET success_count=success_count+1, last_error='' WHERE id=?",
            array($id)
        );
    }

    public static function markFail(int $id, string $error): void
    {
        $t = self::tableName();
        self::db()->query(
            "UPDATE {$t} SET fail_count=fail_count+1, last_error=? WHERE id=?",
            array(excerpt($error, 200, ''), $id)
        );
        $row = self::db()->query("SELECT fail_count FROM {$t} WHERE id=?", array($id))->fetch();
        if ($row && (int)$row['fail_count'] >= 3) {
            self::db()->query("UPDATE {$t} SET status='dead' WHERE id=?", array($id));
        }
    }

    public static function addProxy(string $proxy, string $label): int
    {
        try {
            return self::create(array(
                'proxy' => $proxy,
                'label' => $label,
                'status' => 'active',
                'success_count' => 0,
                'fail_count' => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ));
        } catch (PDOException $e) {
            return 0;
        }
    }

    public static function revive(int $id): void
    {
        self::updateById(array(
            'status' => 'active',
            'fail_count' => 0,
            'last_error' => '',
        ), $id);
    }
}
