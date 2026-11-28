<?php

class Cron
{
    const TASK_TABLE = 'cron_tasks';
    const SOURCE_SYSTEM = 'system';
    const SOURCE_PLUGIN = 'plugin';

    private static array $registry = [];
    private static bool $tableReady = false;

    private static function db(): Database
    {
        return Model::db();
    }

    public static function resolveCliBinary(): string
    {
        return self::resolveCliBinaryFrom(PHP_BINARY, PHP_SAPI);
    }

    public static function resolveCliBinaryFrom(string $bin, string $sapi): string
    {
        if ($sapi === 'cli' || $sapi === 'cli-server') {
            return $bin;
        }
        if ($bin === '') return 'php';
        $dir = dirname($bin);
        $base = basename($bin);
        $parent = dirname($dir);
        $ext = DIRECTORY_SEPARATOR === '\\' ? '.exe' : '';
        $candidates = array(
            $parent . '/bin/php' . $ext,
            $dir . '/php' . $ext,
        );
        if (preg_match('/^php(?:-fpm|-cgi)?(\d+(?:\.\d+)*)?/', $base, $m)) {
            $ver = isset($m[1]) && $m[1] !== '' ? $m[1] : '';
            $candidates[] = $dir . '/php' . $ver . $ext;
            $candidates[] = $parent . '/bin/php' . $ver . $ext;
        }
        foreach ($candidates as $candidate) {
            if (is_file($candidate) && @is_executable($candidate)) {
                return $candidate;
            }
        }
        return 'php';
    }

    public static function ensureTable(): void
    {
        if (self::$tableReady) return;
        self::$tableReady = true;
        try {
            $db = self::db();
            if (!$db->tableExists(self::TASK_TABLE)) {
                $db->query("CREATE TABLE IF NOT EXISTS " . tb(self::TASK_TABLE) . " (
                  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                  `task_key` VARCHAR(64) NOT NULL,
                  `source` VARCHAR(16) NOT NULL DEFAULT 'system',
                  `plugin_id` VARCHAR(64) NOT NULL DEFAULT '',
                  `name` VARCHAR(120) NOT NULL DEFAULT '',
                  `description` VARCHAR(255) NOT NULL DEFAULT '',
                  `interval` INT NOT NULL DEFAULT 0,
                  `status` TINYINT NOT NULL DEFAULT 1,
                  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  `last_run_at` DATETIME NULL,
                  `last_status` VARCHAR(16) NOT NULL DEFAULT '',
                  `last_message` VARCHAR(255) NOT NULL DEFAULT '',
                  `run_count` INT NOT NULL DEFAULT 0,
                  `success_count` INT NOT NULL DEFAULT 0,
                  `fail_count` INT NOT NULL DEFAULT 0,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `uniq_task_key` (`task_key`),
                  KEY `idx_source` (`source`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            }
        } catch (\Throwable $e) {
            error_log('[cron] ensureTable: ' . $e->getMessage());
        }
    }

    public static function register(array $def): void
    {
        $key = trim((string)($def['key'] ?? ''));
        $name = trim((string)($def['name'] ?? ''));
        $callback = $def['callback'] ?? null;
        if ($key === '' || $name === '' || !is_callable($callback)) return;

        $pluginId = class_exists('Plugin') ? Plugin::currentId() : '';
        $source = $pluginId !== '' ? self::SOURCE_PLUGIN : self::SOURCE_SYSTEM;
        $interval = max(0, (int)($def['interval'] ?? 0));
        $desc = mb_substr(trim((string)($def['description'] ?? '')), 0, 255);

        self::ensureTable();
        try {
            $db = self::db();
            $exist = $db->find(self::TASK_TABLE, ['task_key' => $key]);
            if ($exist) {
                $changed = (string)$exist['source'] !== $source
                    || (string)$exist['plugin_id'] !== $pluginId
                    || (string)$exist['name'] !== $name
                    || (string)$exist['description'] !== $desc
                    || (int)$exist['interval'] !== $interval;
                if ($changed) {
                    $db->update(self::TASK_TABLE, [
                        'source' => $source,
                        'plugin_id' => $pluginId,
                        'name' => $name,
                        'description' => $desc,
                        'interval' => $interval,
                    ], ['id' => $exist['id']]);
                }
            } else {
                $db->insert(self::TASK_TABLE, [
                    'task_key' => $key,
                    'source' => $source,
                    'plugin_id' => $pluginId,
                    'name' => $name,
                    'description' => $desc,
                    'interval' => $interval,
                    'status' => 1,
                ]);
            }
        } catch (\Throwable $e) {
            error_log('[cron] register ' . $key . ': ' . $e->getMessage());
        }

        self::$registry[$key] = [
            'callback' => $callback,
            'name' => $name,
            'description' => $desc,
            'interval' => $interval,
            'source' => $source,
            'plugin_id' => $pluginId,
        ];
    }

    public static function unregister(string $key): void
    {
        self::ensureTable();
        try {
            self::db()->delete(self::TASK_TABLE, ['task_key' => $key]);
        } catch (\Throwable $e) {
            error_log('[cron] unregister ' . $key . ': ' . $e->getMessage());
        }
        unset(self::$registry[$key]);
    }

    private static function registerSystemTasks(): void
    {
        self::register([
            'key' => 'system:warm_cache',
            'name' => t_admin('adm_cron_warm_name', '缓存预热'),
            'description' => t_admin('adm_cron_warm_desc', '提前访问首页/榜单页，避免缓存过期后第一个用户慢'),
            'interval' => 240,
            'callback' => [self::class, 'warmCache'],
        ]);
        self::register([
            'key' => 'system:cleanup',
            'name' => t_admin('adm_cron_clean_name', '清理过期数据'),
            'description' => t_admin('adm_cron_clean_desc', '清理过期的临时登录态、过期验证码等'),
            'interval' => 3600,
            'callback' => [self::class, 'cleanup'],
        ]);
        self::register([
            'key' => 'system:vip_expire',
            'name' => t_admin('adm_cron_vip_name', 'VIP到期处理'),
            'description' => t_admin('adm_cron_vip_desc', '将已到期的VIP用户状态更正为普通用户'),
            'interval' => 300,
            'callback' => [self::class, 'expireVip'],
        ]);
        self::register([
            'key' => 'system:dashboard_stats',
            'name' => t_admin('adm_cron_stats_name', '后台统计刷新'),
            'description' => t_admin('adm_cron_stats_desc', '重新统计后台首页的小说数、章节数、用户数、订单数、点击与总字数并写入缓存'),
            'interval' => 300,
            'callback' => [self::class, 'refreshDashboardStats'],
        ]);
    }

    public static function allTasks(): array
    {
        self::ensureTable();
        self::registerSystemTasks();
        $rows = [];
        try {
            $rows = self::db()->select(self::TASK_TABLE, [], 'source ASC, id ASC');
        } catch (\Throwable $e) {
            error_log('[cron] allTasks: ' . $e->getMessage());
        }
        foreach ($rows as &$r) {
            $mem = self::$registry[$r['task_key']] ?? null;
            $r['callback'] = $mem['callback'] ?? null;
            $r['available'] = $r['callback'] !== null;
        }
        unset($r);
        return $rows;
    }

    private static function dueToRun(array $task, bool $force): bool
    {
        if ($force) return true;
        if ((int)$task['status'] !== 1) return false;
        $interval = (int)$task['interval'];
        if ($interval <= 0) return true;
        $last = $task['last_run_at'] ?? null;
        if (!$last) return true;
        return (time() - strtotime($last)) >= $interval;
    }

    public static function runTask(string $key, bool $force = false): array
    {
        self::ensureTable();
        self::registerSystemTasks();
        $row = null;
        try {
            $row = self::db()->find(self::TASK_TABLE, ['task_key' => $key]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
        if (!$row) return ['ok' => false, 'message' => t_admin('adm_cron_task_not_found', '任务不存在')];
        if (!$force && (int)$row['status'] !== 1) {
            return ['ok' => false, 'message' => t_admin('adm_cron_task_disabled', '任务已停用')];
        }
        $mem = self::$registry[$key] ?? null;
        if (!$mem || !is_callable($mem['callback'])) {
            self::markFailed($row, t_admin('adm_cron_callback_missing', '任务回调不可用'));
            return ['ok' => false, 'message' => t_admin('adm_cron_callback_missing', '任务回调不可用')];
        }
        if (!self::dueToRun($row, $force)) {
            return ['ok' => true, 'skipped' => true, 'message' => t_admin('adm_cron_not_due', '未到执行时间')];
        }

        $ok = true;
        $msg = '';
        try {
            $ret = call_user_func($mem['callback']);
            if ($ret === false) {
                $ok = false;
                $msg = t_admin('adm_cron_return_false', '任务返回失败');
            }
        } catch (\Throwable $e) {
            $ok = false;
            $msg = $e->getMessage();
        }
        $msg = mb_substr($msg, 0, 255);
        self::markResult($row, $ok, $msg);
        return ['ok' => $ok, 'message' => $msg];
    }

    private static function markResult(array $row, bool $ok, string $message): void
    {
        try {
            self::db()->update(self::TASK_TABLE, [
                'last_run_at' => date('Y-m-d H:i:s'),
                'last_status' => $ok ? 'success' : 'failed',
                'last_message' => $message,
                'run_count' => (int)$row['run_count'] + 1,
                'success_count' => (int)$row['success_count'] + ($ok ? 1 : 0),
                'fail_count' => (int)$row['fail_count'] + ($ok ? 0 : 1),
            ], ['id' => $row['id']]);
        } catch (\Throwable $e) {
            error_log('[cron] markResult: ' . $e->getMessage());
        }
    }

    private static function markFailed(array $row, string $message): void
    {
        self::markResult($row, false, $message);
    }

    public function runAll(): void
    {
        foreach (self::allTasks() as $task) {
            if ((int)$task['status'] !== 1) continue;
            self::runTask((string)$task['task_key'], false);
        }
        Hook::doAction('cron_run');
    }

    public static function warmCache(): void
    {
        try {
            $service = new NovelService();
            $service->getHomeData();
            echo "  [✓] " . t_admin('adm_cron_warm_ok', '首页缓存已预热') . "\n";
        } catch (\Throwable $e) {
            echo "  [✗] " . t_admin('adm_cron_warm_fail', '首页预热失败：%s', $e->getMessage()) . "\n";
        }
    }

    public static function refreshDashboardStats(): void
    {
        try {
            $payload = DashboardService::refresh();
            echo "  [✓] " . t_admin('adm_cron_stats_ok', '后台统计已刷新（%s 章 / %s 部小说）', number_format((int)$payload['data']['chapters']), number_format((int)$payload['data']['novels'])) . "\n";
        } catch (\Throwable $e) {
            echo "  [✗] " . t_admin('adm_cron_stats_fail', '后台统计刷新失败：%s', $e->getMessage()) . "\n";
        }
    }

    public static function expireVip(): void
    {
        try {
            $count = UserModel::expireVip();
            echo "  [✓] " . t_admin('adm_cron_vip_ok', '已更正 %s 位到期VIP用户', $count) . "\n";
        } catch (\Throwable $e) {
            echo "  [✗] " . t_admin('adm_cron_vip_fail', 'VIP到期处理失败：%s', $e->getMessage()) . "\n";
        }
    }

    public static function cleanup(): void
    {
        $db = Model::db();

        if (!$db->tableExists('login_attempts')) {
            return;
        }
        try {
            $db->query("DELETE FROM " . tb('login_attempts') . " WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
            echo "  [✓] " . t_admin('adm_cron_clean_ok', '过期登录记录已清理') . "\n";
        } catch (\Throwable $e) {
            echo "  [✗] " . t_admin('adm_cron_clean_fail', '清理失败：%s', $e->getMessage()) . "\n";
        }
    }
}
