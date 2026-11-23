<?php

class Database
{
    private static ?PDO $pdo = null;
    private string $tablePrefix = '';

    public function __construct()
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
            );
            try {
                self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                $this->renderConnectError($e->getMessage());
                exit;
            }
        }
        $this->tablePrefix = DB_PREFIX;
    }

    private function renderConnectError(string $detail): void
    {
        $raw = ($_COOKIE['lang'] ?? '') . ' ' . ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
        $zh = stripos($raw, 'zh') !== false || stripos($raw, 'cn') !== false;
        $T = $zh ? [
            'title' => '系统暂时无法访问',
            'head' => '数据库连接失败',
            'body' => '网站当前无法连接数据库，请稍后刷新重试。如问题持续存在，请联系管理员。',
            'back' => '返回首页',
        ] : [
            'title' => 'Temporarily Unavailable',
            'head' => 'Database connection failed',
            'body' => 'The site cannot reach its database right now. Please refresh later. If the problem persists, contact the administrator.',
            'back' => 'Back to home',
        ];
        $msg = ($zh ? '数据库连接失败: ' : 'Database error: ') . htmlspecialchars($detail, ENT_QUOTES, 'UTF-8');
        while (ob_get_level() > 0) { ob_end_clean(); }
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="' . ($zh ? 'zh-CN' : 'en') . '"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . '<title>' . $T['title'] . '</title><style>'
            . '*{box-sizing:border-box;margin:0;padding:0;}'
            . 'body{font-family:"PingFang SC","Microsoft YaHei",sans-serif;background:#faf7f2;color:#333;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;}'
            . '.card{max-width:560px;width:100%;background:#fff;border-radius:8px;box-shadow:0 2px 20px rgba(0,0,0,.06);overflow:hidden;}'
            . '.card-header{background:#333;color:#faf7f2;padding:16px 24px;font-size:15px;font-weight:600;display:flex;align-items:center;gap:10px;}'
            . '.card-header .dot{width:10px;height:10px;border-radius:50%;background:#b8860b;display:inline-block;}'
            . '.card-body{padding:36px 32px;text-align:center;}'
            . 'h1{color:#222;font-size:18px;font-weight:600;margin-bottom:10px;}'
            . '.msg{color:#666;line-height:1.8;font-size:14px;margin-bottom:20px;}'
            . 'a.btn{display:inline-block;padding:9px 26px;background:#333;color:#faf7f2;border-radius:4px;text-decoration:none;font-size:13px;transition:background .2s;}'
            . 'a.btn:hover{background:#b8860b;}'
            . 'pre{text-align:left;background:#faf7f2;border:1px solid #eee;border-radius:4px;padding:12px 14px;font-family:Consolas,Menlo,monospace;font-size:12px;line-height:1.7;color:#555;overflow:auto;margin:16px 0;white-space:pre-wrap;word-break:break-all;}'
            . '.card-footer{padding:14px 24px;border-top:1px solid #f0f0f0;text-align:center;font-size:12px;color:#999;background:#fcfbf8;}'
            . '.card-footer a{color:#b8860b;text-decoration:none;}'
            . '</style></head><body><div class="card">'
            . '<div class="card-header"><span class="dot"></span>' . $T['title'] . '</div>'
            . '<div class="card-body">'
            . '<h1>' . $T['head'] . '</h1>'
            . '<p class="msg">' . $T['body'] . '</p>'
            . '<pre>' . $msg . '</pre>'
            . '<a class="btn" href="/">' . $T['back'] . '</a>'
            . '</div>'
            . '<div class="card-footer">Powered By <a href="https://www.enovelcms.cn/" target="_blank" rel="noopener">EnovelCms'
            . (defined('ENOVELCMS_VERSION') ? ' v' . ENOVELCMS_VERSION : '')
            . '</a></div>'
            . '</div></body></html>';
    }

    public function table(string $table): string
    {
        return '`' . $this->tablePrefix . $table . '`';
    }

    public function from(string $table, string $alias = ''): QueryBuilder
    {
        return new QueryBuilder(self::$pdo, $this->tablePrefix, $table, $alias);
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function select(string $table, array $where = [], string $order = '', int $limit = 0, int $offset = 0, string $fields = '*'): array
    {
        $sql = "SELECT {$fields} FROM " . $this->table($table);
        [$whereSql, $params] = $this->buildWhere($where);
        $sql .= $whereSql;

        $order = self::sanitizeOrderExpr($order);
        if ($order) $sql .= " ORDER BY {$order}";
        if ($limit > 0) $sql .= " LIMIT {$limit}" . ($offset > 0 ? " OFFSET {$offset}" : '');
        return $this->query($sql, $params)->fetchAll();
    }

    public static function sanitizeOrderExpr(string $expr): string
    {
        $expr = trim($expr);
        if ($expr === '') return '';
        if (!preg_match('/^[a-zA-Z0-9_.,\s()]+$/', $expr)) return '';
        if (!preg_match('/[a-zA-Z_]/', $expr)) return '';
        return $expr;
    }

    public function find(string $table, array $where = [], string $fields = '*'): ?array
    {
        $rows = $this->select($table, $where, '', 1, 0, $fields);
        return $rows[0] ?? null;
    }

    public function findById(string $table, int $id, string $fields = '*'): ?array
    {
        return $this->find($table, ['id' => $id], $fields);
    }

    public function count(string $table, array $where = []): int
    {
        $sql = "SELECT COUNT(*) AS cnt FROM " . $this->table($table);
        [$whereSql, $params] = $this->buildWhere($where);
        $sql .= $whereSql;
        $row = $this->query($sql, $params)->fetch();
        return (int)($row['cnt'] ?? 0);
    }

    public function insert(string $table, array $data): int
    {
        $keys = array_keys($data);
        $placeholders = array_map(fn($k) => ':' . $k, $keys);
        $sql = "INSERT INTO " . $this->table($table)
             . " (`" . implode('`,`', $keys) . "`)"
             . " VALUES (" . implode(',', $placeholders) . ")";
        $this->query($sql, $data);
        return (int)self::$pdo->lastInsertId();
    }

    public function batchInsert(string $table, array $rows): int
    {
        if (empty($rows)) return 0;
        $keys = array_keys($rows[0]);
        $placeholders = '(' . implode(',', array_fill(0, count($keys), '?')) . ')';
        $sql = "INSERT INTO " . $this->table($table)
             . " (`" . implode('`,`', $keys) . "`)"
             . " VALUES " . implode(',', array_fill(0, count($rows), $placeholders));
        $params = [];
        foreach ($rows as $row) {
            foreach ($keys as $k) $params[] = $row[$k] ?? null;
        }
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function update(string $table, array $data, array $where): int
    {
        $setParts = [];
        $params = [];
        foreach ($data as $k => $v) {
            $setParts[] = "`{$k}` = ?";
            $params[] = $v;
        }
        $sql = "UPDATE " . $this->table($table) . " SET " . implode(',', $setParts);
        [$whereSql, $whereParams] = $this->buildWhere($where);
        $sql .= $whereSql;
        $params = array_merge($params, $whereParams);
        return $this->query($sql, $params)->rowCount();
    }

    public function delete(string $table, array $where): int
    {
        $sql = "DELETE FROM " . $this->table($table);
        [$whereSql, $params] = $this->buildWhere($where);
        $sql .= $whereSql;
        return $this->query($sql, $params)->rowCount();
    }

    public function increment(string $table, string $field, int $amount = 1, array $where = []): int
    {
        $sql = "UPDATE " . $this->table($table) . " SET `{$field}` = `{$field}` + {$amount}";
        [$whereSql, $params] = $this->buildWhere($where);
        $sql .= $whereSql;
        return $this->query($sql, $params)->rowCount();
    }

    public function decrement(string $table, string $field, int $amount = 1, array $where = []): int
    {
        return $this->increment($table, $field, -$amount, $where);
    }

    public function createTable(string $table, array $columns, string $engine = 'InnoDB', string $charset = 'utf8mb4'): bool
    {
        $colsSql = [];
        $primary = '';
        foreach ($columns as $name => $definition) {
            if (strtoupper($name) === '__PRIMARY__') {
                $primary = ", PRIMARY KEY ({$definition})";
                continue;
            }
            $colsSql[] = "`{$name}` {$definition}";
        }
        $sql = "CREATE TABLE IF NOT EXISTS " . $this->table($table)
             . " (" . implode(',', $colsSql) . $primary . ")"
             . " ENGINE={$engine} DEFAULT CHARSET={$charset}";
        $this->query($sql);
        return true;
    }

    public function dropTable(string $table): bool
    {
        $this->query("DROP TABLE IF EXISTS " . $this->table($table));
        return true;
    }

    public function truncate(string $table): bool
    {
        $this->query("TRUNCATE TABLE " . $this->table($table));
        return true;
    }

    public function addColumn(string $table, string $column, string $definition): bool
    {
        try {
            $this->query("ALTER TABLE " . $this->table($table) . " ADD COLUMN `{$column}` {$definition}");
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') === false) throw $e;
        }
        return true;
    }

    public function modifyColumn(string $table, string $column, string $definition): bool
    {
        $this->query("ALTER TABLE " . $this->table($table) . " MODIFY `{$column}` {$definition}");
        return true;
    }

    public function dropColumn(string $table, string $column): bool
    {
        try {
            $this->query("ALTER TABLE " . $this->table($table) . " DROP COLUMN `{$column}`");
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'check that column') === false) throw $e;
        }
        return true;
    }

    public function setColumnDefault(string $table, string $column, $default): bool
    {
        $defaultSql = is_null($default) ? 'DEFAULT NULL' : "DEFAULT " . (is_numeric($default) ? $default : "'" . addslashes($default) . "'");
        $this->query("ALTER TABLE " . $this->table($table) . " ALTER COLUMN `{$column}` SET {$defaultSql}");
        return true;
    }

    public function dropColumnDefault(string $table, string $column): bool
    {
        $this->query("ALTER TABLE " . $this->table($table) . " ALTER COLUMN `{$column}` DROP DEFAULT");
        return true;
    }

    public function addIndex(string $table, string $indexName, array $columns): bool
    {
        $cols = '`' . implode('`,`', $columns) . '`';
        try {
            $this->query("ALTER TABLE " . $this->table($table) . " ADD INDEX `{$indexName}` ({$cols})");
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate key') === false) throw $e;
        }
        return true;
    }

    public function dropIndex(string $table, string $indexName): bool
    {
        try {
            $this->query("ALTER TABLE " . $this->table($table) . " DROP INDEX `{$indexName}`");
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'check that there is no index') === false) throw $e;
        }
        return true;
    }

    public function tableExists(string $table): bool
    {
        $stmt = $this->query(
            "SELECT COUNT(*) AS cnt FROM information_schema.tables WHERE table_schema = ? AND table_name = ?",
            [DB_NAME, $this->tablePrefix . $table]
        );
        $row = $stmt->fetch();
        return (int)($row['cnt'] ?? 0) > 0;
    }

    public function getColumns(string $table): array
    {
        $stmt = $this->query("DESCRIBE " . $this->table($table));
        return $stmt->fetchAll();
    }

    public function beginTransaction(): bool
    {
        return self::$pdo->beginTransaction();
    }
    public function commit(): bool
    {
        return self::$pdo->commit();
    }
    public function rollBack(): bool
    {
        return self::$pdo->rollBack();
    }

    private function buildWhere(array $where): array
    {
        if (empty($where)) return ['', []];
        $parts = [];
        $params = [];
        foreach ($where as $key => $value) {

            if (strpos($key, '?') !== false) {
                $parts[] = $key;
                $params[] = $value;
            } else {
                $parts[] = "`{$key}` = ?";
                $params[] = $value;
            }
        }
        return [' WHERE ' . implode(' AND ', $parts), $params];
    }
}
