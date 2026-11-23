<?php

class UpgradeService
{
    const OFFICIAL_API = 'https://www.enovelcms.cn';

    const SKIP_DIRS = array('data', 'install');

    const SKIP_FILES = array('config/config.php');

    private $db;
    private $currentVersion;
    private $siteUrl;
    private $backupStamp = '';
    private $backupZip = null;
    private $backupZipPath = '';

    public function __construct($db, $currentVersion, $siteUrl)
    {
        $this->db = $db;
        $this->currentVersion = $currentVersion;
        $this->siteUrl = $siteUrl;
    }

    private function sslVerify(): bool
    {
        return defined('AC_VERIFY_SSL') && AC_VERIFY_SSL === true;
    }

    public function getRemoteUpgrades(): array
    {
        $url = rtrim(self::OFFICIAL_API, '/') . '/api/upgrade/list.php?' . http_build_query(array(
            'version' => $this->currentVersion,
        ));

        $res = Network::get($url, array(
            'uaType'        => 'client',
            'sslVerify'     => $this->sslVerify(),
            'timeout'       => 20,
            'connectTimeout'=> 10,
        ));
        if (!$res['ok']) {
            throw new Exception(t_admin('adm_upg_conn_fail', '无法连接升级服务器：%s', $res['error'] ?: 'HTTP ' . $res['http']));
        }
        $data = json_decode($res['body'], true);
        if (!is_array($data)) {
            throw new Exception(t_admin('adm_upg_bad_format', '服务器返回数据格式错误'));
        }
        if ((int)($data['code'] ?? 0) !== 1) {
            throw new Exception($data['message'] ?? t_admin('adm_upg_list_fail', '获取升级列表失败'));
        }
        return $data['data'] ?? array();
    }

    public function getOfficialNotices(): array
    {
        return Cache::remember('official_notices', 3600, function () {
            $url = rtrim(self::OFFICIAL_API, '/') . '/api/crawl/notice.php';

            $res = Network::get($url, array(
                'uaType'        => 'client',
                'sslVerify'     => $this->sslVerify(),
                'timeout'       => 6,
                'connectTimeout'=> 3,
            ));
            if (!$res['ok']) {
                return array();
            }
            $data = json_decode($res['body'], true);
            if (!is_array($data)) {
                return array();
            }
            $list = array();
            foreach ($data as $item) {
                $text = (string)($item['text'] ?? ($item['title'] ?? ''));
                if ($text === '') continue;
                $level = 'info';
                if (mb_strpos($text, '【置顶】') === 0) $level = 'warning';
                $list[] = array(
                    'title'   => $text,
                    'date'    => (string)($item['date'] ?? ''),
                    'url'     => (string)($item['url'] ?? '#'),
                    'summary' => (string)($item['summary'] ?? ''),
                    'level'   => $level,
                );
            }
            return $list;
        });
    }

    public function getUpgradePath(array $list): array
    {
        $path = array();
        foreach ($list as $item) {
            if (!empty($item['version'])
                && version_compare($item['version'], $this->currentVersion, '>')) {
                $path[] = $item;
            }
        }
        usort($path, function ($a, $b) {
            return version_compare($a['version'], $b['version']);
        });
        return $path;
    }

    public function getLatestVersion(): ?string
    {
        $key = 'upgrade_latest_' . $this->currentVersion;
        try {
            $list = Cache::remember($key, 3600, function () {
                $url = rtrim(self::OFFICIAL_API, '/') . '/api/upgrade/list.php?' . http_build_query(array(
                    'version' => $this->currentVersion,
                ));
                $res = Network::get($url, array(
                    'uaType'        => 'client',
                    'sslVerify'     => $this->sslVerify(),
                    'timeout'       => 8,
                    'connectTimeout'=> 4,
                ));
                if (!$res['ok']) return array();
                $data = json_decode($res['body'], true);
                if (!is_array($data) || (int)($data['code'] ?? 0) !== 1) return array();
                return $data['data'] ?? array();
            });
        } catch (\Throwable $e) {
            return null;
        }
        $path = $this->getUpgradePath(is_array($list) ? $list : array());
        if (empty($path)) return null;
        $latest = end($path);
        return (string)$latest['version'];
    }

    public function upgradeTo(array $upgrade): array
    {
        $log = array();
        $tempDir = ROOT_PATH . 'data/temp/upgrade_' . uniqid() . '/';
        if (!is_dir($tempDir) && !mkdir($tempDir, 0755, true)) {
            return array('success' => false, 'log' => array('[ERROR] ' . t_admin('adm_upg_log_temp_fail', '无法创建临时目录')), 'error' => t_admin('adm_upg_temp_fail', '临时目录创建失败'));
        }

        try {
            $hasZip = !empty($upgrade['file_url']);
            $hasSql = !empty($upgrade['data_url']);
            if (!$hasZip && !$hasSql) {
                throw new Exception(t_admin('adm_upg_both_empty', '升级包和数据包不能同时为空'));
            }

            $contentParts = array();
            if ($hasZip) $contentParts[] = t_admin('adm_upg_part_files', '程序文件');
            if ($hasSql) $contentParts[] = t_admin('adm_upg_part_db', '数据库');
            $log[] = '[INFO] ' . t_admin('adm_upg_log_content', '升级内容：%s', implode(' + ', $contentParts));

            if ($hasZip) {
                $zipFile = $this->downloadFile($upgrade['file_url'], $tempDir, 'package.zip');
                $log[] = '[OK] ' . t_admin('adm_upg_log_zip_done', '下载升级包完成：%s KB', round(filesize($zipFile) / 1024, 1));

                if (empty($upgrade['file_hash'])) {
                    throw new Exception(t_admin('adm_upg_zip_no_hash', '升级包缺少 SHA256 校验信息，已拒绝安装'));
                }
                $actual = hash_file('sha256', $zipFile);
                if (!hash_equals(strtolower($upgrade['file_hash']), $actual)) {
                    throw new Exception(
                        t_admin('adm_upg_zip_hash_mismatch', '升级包 SHA256 不匹配（官方：%s，实际：%s）', substr($upgrade['file_hash'], 0, 16), substr($actual, 0, 16))
                    );
                }
                $log[] = '[OK] ' . t_admin('adm_upg_log_zip_hash_ok', '升级包 SHA256 校验通过：%s', substr($actual, 0, 16));

                $extractDir = $tempDir . 'extract/';
                $this->extractZip($zipFile, $extractDir);
                $log[] = '[OK] ' . t_admin('adm_upg_log_zip_extracted', '升级包解压完成');

                $topItems = array_values(array_diff(scandir($extractDir), array('.', '..')));
                $log[] = '[INFO] ' . t_admin('adm_upg_log_top_items', '解压后顶层内容：%s', implode('  ', array_map(function ($it) use ($extractDir) {
                    return (is_dir($extractDir . '/' . $it) ? $it . '/' : $it);
                }, $topItems)));

                $rootDir = $this->detectZipRoot($extractDir);
                $log[] = '[INFO] ' . t_admin('adm_upg_log_root', '探测到内容根目录：%s/', basename(rtrim($rootDir, '/')));

                $counter = array('copied' => 0, 'skipped' => 0, 'failed' => 0);
                $this->backupStamp = date('YmdHis');
                $this->openBackup();
                $this->copyFiles($rootDir, ROOT_PATH, $log, '', $counter);
                $bakCount = $this->closeBackup();
                if ($bakCount > 0) {
                    $log[] = '[OK] ' . t_admin('adm_upg_log_backup_done', '原文件已备份：%s 个，备份包 %s', $bakCount, basename($this->backupZipPath));
                }
                $log[] = '[OK] ' . t_admin('adm_upg_log_files_done', '程序文件覆盖完成：写入 %s 个，跳过 %s 个，失败 %s 个', $counter['copied'], $counter['skipped'], $counter['failed']);

                if ($counter['failed'] > 0) {
                    throw new Exception(t_admin('adm_upg_files_failed', '有 %s 个文件覆盖失败，请检查目录权限', $counter['failed']));
                }
                @unlink($zipFile);
            } else {
                $log[] = '[INFO] ' . t_admin('adm_upg_log_no_zip', '本版本无程序包，跳过文件覆盖');
            }

            if ($hasSql) {
                $sqlFile = $this->downloadFile($upgrade['data_url'], $tempDir, 'upgrade.sql');
                $log[] = '[OK] ' . t_admin('adm_upg_log_sql_done', '下载数据包完成：%s KB', round(filesize($sqlFile) / 1024, 1));

                if (empty($upgrade['data_hash'])) {
                    throw new Exception(t_admin('adm_upg_sql_no_hash', '数据包缺少 SHA256 校验信息，已拒绝安装'));
                }
                $actual = hash_file('sha256', $sqlFile);
                if (!hash_equals(strtolower($upgrade['data_hash']), $actual)) {
                    throw new Exception(
                        t_admin('adm_upg_sql_hash_mismatch', '数据包 SHA256 不匹配（官方：%s，实际：%s）', substr($upgrade['data_hash'], 0, 16), substr($actual, 0, 16))
                    );
                }
                $log[] = '[OK] ' . t_admin('adm_upg_log_sql_hash_ok', '数据包 SHA256 校验通过：%s', substr($actual, 0, 16));

                $sqlResult = $this->executeSqlFile($sqlFile);
                if ($sqlResult['total'] === 0) {
                    throw new Exception(t_admin('adm_upg_sql_empty', '数据包为空或没有可执行的语句，已中止升级'));
                }
                $log[] = '[OK] ' . t_admin('adm_upg_log_sql_exec', '数据库执行完成（成功 %s，跳过 %s，共 %s 条）',
                    $sqlResult['executed'], $sqlResult['ignored'], $sqlResult['total']);
                if (!empty($sqlResult['fatal'])) {
                    $detail = implode('; ', array_slice($sqlResult['fatal'], 0, 3));
                    throw new Exception(t_admin('adm_upg_sql_fatal', '数据库升级失败，%s 条语句执行错误：%s',
                        count($sqlResult['fatal']), $detail));
                }
                @unlink($sqlFile);
            } else {
                $log[] = '[INFO] ' . t_admin('adm_upg_log_no_sql', '本版本无数据包，跳过 SQL 执行');
            }

            $this->updateVersionConstant($upgrade['version']);
            $log[] = '[OK] ' . t_admin('adm_upg_log_version', '版本号更新为 %s', $upgrade['version']);

            Cache::flush();
            $log[] = '[OK] ' . t_admin('adm_upg_log_cache_cleared', '已清理全部文件缓存');

            $this->rmdir($tempDir);
            return array('success' => true, 'log' => $log, 'error' => null);
        } catch (Exception $e) {
            $this->closeBackup();
            $log[] = '[ERROR] ' . t_admin('adm_upg_log_fail', '升级失败：%s', $e->getMessage());
            $this->rmdir($tempDir);
            return array('success' => false, 'log' => $log, 'error' => $e->getMessage());
        }
    }

    private function assertSafeDownloadUrl(string $url): void
    {
        $parts = parse_url($url);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            throw new Exception(t_admin('adm_upg_url_bad', '下载地址格式非法'));
        }
        if (strtolower($parts['scheme']) !== 'https') {
            throw new Exception(t_admin('adm_upg_url_not_https', '仅允许 HTTPS 下载地址，已拒绝'));
        }
        $host = strtolower($parts['host']);
        $officialHost = strtolower((string)parse_url(self::OFFICIAL_API, PHP_URL_HOST));
        $officialLabels = explode('.', $officialHost);
        $baseDomain = implode('.', array_slice($officialLabels, -2));
        $allowedHost = $host === $officialHost || $host === $baseDomain
            || preg_match('/\.' . preg_quote($baseDomain, '/') . '$/', $host) === 1;
        if (!$allowedHost) {
            throw new Exception(t_admin('adm_upg_url_not_whitelist', '下载地址不在官方域名白名单内，已拒绝'));
        }
        if (filter_var($host, FILTER_VALIDATE_IP)
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            throw new Exception(t_admin('adm_upg_url_private', '下载地址指向内网/保留地址，已拒绝'));
        }
    }

    private function downloadFile(string $url, string $dir, string $filename): string
    {
        if (empty($url)) throw new Exception(t_admin('adm_upg_url_empty', '文件地址为空'));
        $this->assertSafeDownloadUrl($url);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new Exception(t_admin('adm_upg_temp_fail', '临时目录创建失败'));
        }
        $target = $dir . $filename;

        $dl = Network::download($url, $target, array(
            'uaType'        => 'client',
            'sslVerify'     => $this->sslVerify(),
            'timeout'       => 600,
            'connectTimeout'=> 20,
            'maxBytes'      => 200 * 1024 * 1024,
        ));
        if (!$dl['ok']) {
            @unlink($target);
            throw new Exception(t_admin('adm_upg_download_fail', '下载失败：%s', $dl['error']));
        }
        return $target;
    }

    private function extractZip(string $zipFile, string $targetDir): void
    {
        if (!class_exists('ZipArchive')) {
            if (!class_exists('PharData')) {
                throw new Exception(t_admin('adm_upg_no_unzip', '服务器缺少 zip 扩展，且 phar 扩展不可用，无法解压升级包'));
            }
            try {
                $phar = new PharData($zipFile);
                $prefix = 'phar://' . str_replace('\\', '/', realpath($zipFile)) . '/';
                foreach (new RecursiveIteratorIterator($phar) as $file) {
                    $name = str_replace('\\', '/', $file->getPathName());
                    if (strpos($name, $prefix) === 0) {
                        $name = substr($name, strlen($prefix));
                    } else {
                        $p = strpos($name, '.zip/');
                        if ($p !== false) $name = substr($name, $p + 5);
                    }
                    $this->assertSafePath($name);
                }
                if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
                if (!$phar->extractTo($targetDir, null, true)) {
                    throw new Exception(t_admin('adm_upg_extract_fail', 'ZIP 解压失败'));
                }
                return;
            } catch (Exception $e) {
                throw new Exception(t_admin('adm_upg_extract_fail_detail', 'ZIP 解压失败：%s', $e->getMessage()));
            }
        }

        $zip = new ZipArchive();
        if ($zip->open($zipFile) !== true) {
            throw new Exception(t_admin('adm_upg_open_zip', '无法打开 ZIP 文件'));
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false) continue;
            $this->assertSafePath($name);
        }
        if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
        if (!$zip->extractTo($targetDir)) {
            $zip->close();
            throw new Exception(t_admin('adm_upg_extract_fail', 'ZIP 解压失败'));
        }
        $zip->close();
    }

    private function assertSafePath(string $name): void
    {
        if (strpos($name, '..') !== false
            || strpos($name, '/') === 0
            || strpos($name, '\\') !== false
            || preg_match('#^[a-zA-Z]:#', $name)) {
            throw new Exception(t_admin('adm_upg_bad_path', 'ZIP 包含非法路径：%s', $name));
        }
    }

    private function detectZipRoot(string $dir): string
    {
        $dir = rtrim($dir, '/') . '/';
        $items = array_values(array_diff(scandir($dir), array('.', '..')));

        if (count($items) === 1) {
            $only = $items[0];
            $sub = $dir . $only . '/';
            if (is_dir($sub)) {
                return $sub;
            }
        }
        return $dir;
    }

    private function openBackup(): void
    {
        if ($this->backupZip instanceof \ZipArchive) return;
        $dir = ROOT_PATH . 'backup/' . date('Y') . '/' . date('m') . '/' . date('d') . '/';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) return;
        $path = $dir . time() . '_' . bin2hex(random_bytes(9)) . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) return;
        $this->backupZip = $zip;
        $this->backupZipPath = $path;
    }

    private function archiveOriginal(string $dstPath, string $relPath): void
    {
        if (!$this->backupZip instanceof \ZipArchive) $this->openBackup();
        if (!$this->backupZip instanceof \ZipArchive) return;
        $name = ltrim(str_replace('\\', '/', $relPath), '/');
        if ($name === '') return;
        $content = @file_get_contents($dstPath);
        if ($content === false) return;
        $this->backupZip->addFromString($name, $content);
    }

    private function closeBackup(): int
    {
        if (!$this->backupZip instanceof \ZipArchive) return 0;
        $count = (int)$this->backupZip->numFiles;
        $this->backupZip->close();
        $this->backupZip = null;
        return $count;
    }

    private function copyFiles(string $src, string $dest, array &$log, string $baseRel = '', ?array &$counter = null): void
    {
        if ($counter === null) {
            $counter = array('copied' => 0, 'skipped' => 0, 'failed' => 0);
        }

        $items = @scandir($src);
        if ($items === false) return;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $srcPath = rtrim($src, '/') . '/' . $item;
            $dstPath = rtrim($dest, '/') . '/' . $item;
            $relPath = $baseRel === '' ? $item : $baseRel . '/' . $item;

            if (is_dir($srcPath) && $baseRel === '' && in_array($item, self::SKIP_DIRS, true)) {
                $log[] = '[SKIP] ' . t_admin('adm_upg_log_skip_dir', '跳过保护目录：%s/', $item);
                $counter['skipped']++;
                continue;
            }
            if (is_file($srcPath) && in_array($relPath, self::SKIP_FILES, true)) {
                $log[] = '[SKIP] ' . t_admin('adm_upg_log_skip_file', '跳过保护文件：%s', $relPath);
                $counter['skipped']++;
                continue;
            }

            if (is_dir($srcPath)) {
                if (!is_dir($dstPath)) mkdir($dstPath, 0755, true);
                $this->copyFiles($srcPath, $dstPath, $log, $relPath, $counter);
            } else {
                if (file_exists($dstPath)) {
                    $this->archiveOriginal($dstPath, $relPath);
                }
                $dstDir = dirname($dstPath);
                if (!is_dir($dstDir)) {
                    if (!@mkdir($dstDir, 0755, true) && !is_dir($dstDir)) {
                        $log[] = '[WARN] ' . t_admin('adm_upg_log_mkdir_fail', '无法创建目录：%s', $dstDir);
                        $counter['failed']++;
                        continue;
                    }
                }
                if (@copy($srcPath, $dstPath)) {
                    $counter['copied']++;
                } else {
                    $log[] = '[WARN] ' . t_admin('adm_upg_log_copy_fail', '覆盖失败：%s（可能是目录权限问题）', $relPath);
                    $counter['failed']++;
                }
            }
        }
    }

    private function executeSqlFile(string $sqlFile): array
    {
        $result = array('total' => 0, 'executed' => 0, 'ignored' => 0, 'fatal' => array());
        $raw = file_get_contents($sqlFile);
        if ($raw === false || trim($raw) === '') return $result;

        $raw = preg_replace('/^\s*--.*$/m', '', $raw);
        $raw = preg_replace('/^\s*#.*$/m', '', $raw);
        $raw = preg_replace('#/\*.*?\*/#s', '', $raw);

        $raw = str_replace('{db_prefix}', DB_PREFIX, $raw);

        foreach ($this->splitSql($raw) as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '') continue;
            $result['total']++;
            try {
                $this->db->query($stmt);
                $result['executed']++;
            } catch (\Throwable $e) {
                if ($this->isIgnorableSqlError($e)) {
                    $result['ignored']++;
                    error_log('[Upgrade SQL ignored] ' . $e->getMessage());
                } else {
                    $result['fatal'][] = $e->getMessage() . ' | SQL: ' . substr($stmt, 0, 120);
                    error_log('[Upgrade SQL fatal] ' . $e->getMessage() . ' | SQL: ' . substr($stmt, 0, 200));
                }
            }
        }
        return $result;
    }

    private function isIgnorableSqlError(\Throwable $e): bool
    {
        $native = ($e instanceof \PDOException && isset($e->errorInfo[1])) ? (int)$e->errorInfo[1] : 0;
        return in_array($native, array(1050, 1060, 1061, 1062), true);
    }

    private function splitSql(string $sql): array
    {
        $statements = array();
        $current = '';
        $inString = false;
        $stringChar = '';
        $len = strlen($sql);

        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];

            if ($inString) {
                if ($ch === '\\' && $i + 1 < $len) {
                    $current .= $ch . $sql[$i + 1];
                    $i++;
                    continue;
                }
                if ($ch === $stringChar) $inString = false;
                $current .= $ch;
            } else {
                if ($ch === "'" || $ch === '"' || $ch === '`') {
                    $inString = true;
                    $stringChar = $ch;
                    $current .= $ch;
                } elseif ($ch === ';') {
                    $statements[] = $current;
                    $current = '';
                } else {
                    $current .= $ch;
                }
            }
        }
        if (trim($current) !== '') $statements[] = $current;
        return $statements;
    }

    private function updateVersionConstant(string $version): void
    {
        $configFile = ROOT_PATH . 'config/config.php';
        $content = file_get_contents($configFile);
        if ($content === false) {
            throw new Exception(t_admin('adm_upg_cfg_read', '无法读取配置文件，请检查 config/config.php 权限'));
        }
        $pattern = "/(define\\(\\s*['\"]ENOVELCMS_VERSION['\"]\\s*,\\s*)['\"][^'\"]*['\"](\\s*\\);)/";
        $updated = preg_replace($pattern, "\${1}'" . addslashes($version) . "'\${2}", $content);
        if ($updated === null || $updated === $content) {
            throw new Exception(t_admin('adm_upg_cfg_version', '无法更新版本号，请检查 config/config.php 权限'));
        }
        if (file_put_contents($configFile, $updated) === false) {
            throw new Exception(t_admin('adm_upg_cfg_write', '无法写入配置文件，请检查 config/config.php 权限'));
        }
    }

    private function rmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $items = @scandir($dir);
        if ($items === false) return;
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $p = rtrim($dir, '/') . '/' . $item;
            is_dir($p) ? $this->rmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
