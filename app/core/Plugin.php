<?php

class Plugin
{
    private static array $plugins = [];
    private static array $enabledIds = [];

    private static array $registry = [];

    private static array $configCache = [];
    private static string $currentPluginId = '';
    private static $dbh = null;

    const REGISTRY_TABLE = 'plugin_registry';

    public static function setDb($db): void
    {
        self::$dbh = $db;
    }

    public static function db()
    {
        return self::$dbh;
    }

    public static function config(string $key, $default = '', ?string $pluginId = null)
    {
        $pid = $pluginId ?: self::$currentPluginId;
        if (!self::$dbh || $pid === '') return $default;
        $ck = $pid . "\0" . $key;
        if (array_key_exists($ck, self::$configCache)) {
            $cached = self::$configCache[$ck];
            return $cached === null ? $default : $cached;
        }
        $row = self::$dbh->find('plugin_data', [
            'plugin_id' => $pid, 'entity_type' => 'config', 'entity_id' => 0, 'data_key' => $key,
        ]);
        $value = $row ? (string)$row['data_value'] : null;
        self::$configCache[$ck] = $value;
        return $value === null ? $default : $value;
    }

    public static function setConfig(string $key, $value, ?string $pluginId = null): bool
    {
        $pid = $pluginId ?: self::$currentPluginId;
        if (!self::$dbh || $pid === '') return false;
        $row = self::$dbh->find('plugin_data', [
            'plugin_id' => $pid, 'entity_type' => 'config', 'entity_id' => 0, 'data_key' => $key,
        ]);
        $value = (string)$value;
        if ($row) {
            self::$dbh->update('plugin_data', ['data_value' => $value], ['id' => $row['id']]);
        } else {
            self::$dbh->insert('plugin_data', [
                'plugin_id' => $pid, 'entity_type' => 'config', 'entity_id' => 0,
                'data_key' => $key, 'data_value' => $value,
            ]);
        }
        self::$configCache[$pid . "\0" . $key] = $value;
        return true;
    }

    public static function flushConfigCache(?string $pluginId = null): void
    {
        if ($pluginId === null) {
            self::$configCache = [];
            return;
        }
        foreach (array_keys(self::$configCache) as $ck) {
            if (strpos($ck, $pluginId . "\0") === 0) unset(self::$configCache[$ck]);
        }
    }

    public static function init(object $db): void
    {
        self::$dbh = $db;
        self::migrateLegacyEnabled($db);
        self::loadRegistry($db);

        foreach (self::$enabledIds as $pluginId) {
            self::loadPlugin($pluginId, $db);
        }
    }

    private static function migrateLegacyEnabled(object $db): void
    {
        try {
            if ($db->count(self::REGISTRY_TABLE) > 0) return;
            $row = $db->find('settings', ['key' => 'plugins_enabled']);
            $legacy = ($row && $row['value']) ? (json_decode((string)$row['value'], true) ?: []) : [];
            foreach ($legacy as $pid) {
                $pid = (string)$pid;
                if (!Security::validPluginId($pid)) continue;
                $db->insert(self::REGISTRY_TABLE, [
                    'plugin_id' => $pid,
                    'version'   => self::readDiskVersion($pid),
                    'status'    => 1,
                ]);
            }
        } catch (\Throwable $e) {
            error_log('[plugin] migrate legacy enabled failed: ' . $e->getMessage());
        }
    }

    private static function loadRegistry(object $db): void
    {
        self::$registry = [];
        self::$enabledIds = [];
        try {
            foreach ($db->select(self::REGISTRY_TABLE) as $r) {
                $pid = (string)$r['plugin_id'];
                if ($pid === '') continue;
                self::$registry[$pid] = ['version' => (string)$r['version'], 'status' => (int)$r['status']];
                if ((int)$r['status'] === 1) self::$enabledIds[] = $pid;
            }
        } catch (\Throwable $e) {
            error_log('[plugin] load registry failed: ' . $e->getMessage());
        }
    }

    private static function readDiskVersion(string $pluginId): string
    {
        $xmlFile = ROOT_PATH . "plugins/{$pluginId}/plugin.xml";
        if (file_exists($xmlFile)) {
            $xml = @simplexml_load_file($xmlFile);
            if ($xml !== false && isset($xml->version)) {
                $v = trim((string)$xml->version);
                if ($v !== '') return $v;
            }
        }
        return '1.0.0';
    }

    private static function registryUpsert(object $db, string $id, string $version, int $status): void
    {
        $existing = $db->find(self::REGISTRY_TABLE, ['plugin_id' => $id]);
        if ($existing) {
            $db->update(self::REGISTRY_TABLE, ['version' => $version, 'status' => $status], ['id' => $existing['id']]);
        } else {
            $db->insert(self::REGISTRY_TABLE, ['plugin_id' => $id, 'version' => $version, 'status' => $status]);
        }
        self::$registry[$id] = ['version' => $version, 'status' => $status];
    }

    private static function registrySetStatus(object $db, string $id, int $status): void
    {
        $db->update(self::REGISTRY_TABLE, ['status' => $status], ['plugin_id' => $id]);
        if (isset(self::$registry[$id])) self::$registry[$id]['status'] = $status;
    }

    private static function registrySetVersion(object $db, string $id, string $version): void
    {
        $db->update(self::REGISTRY_TABLE, ['version' => $version], ['plugin_id' => $id]);
        if (isset(self::$registry[$id])) self::$registry[$id]['version'] = $version;
    }

    public static function isRegistered(string $id): bool
    {
        return isset(self::$registry[$id]);
    }

    public static function registryVersion(string $id): string
    {
        return self::$registry[$id]['version'] ?? '';
    }

    private static function loadPlugin(string $pluginId, object $db): void
    {
        $mainFile = ROOT_PATH . "plugins/{$pluginId}/main.php";
        if (!file_exists($mainFile)) return;

        $info = self::readManifest($pluginId, dirname($mainFile));

        self::$currentPluginId = $pluginId;
        self::$plugins[$pluginId] = $info;
        PluginDB::setPlugin($pluginId);

        $context = Extension::context(Extension::KIND_PLUGIN, $pluginId, $db);
        $context['info'] = $info;

        $loadedOk = false;
        Extension::begin(Extension::KIND_PLUGIN, $pluginId);
        try {
            extract($context);
            if (!defined('PLUGIN_BOOT')) define('PLUGIN_BOOT', true);
            require $mainFile;
            $loadedOk = true;
            Extension::markLoaded(Extension::KIND_PLUGIN, $pluginId);
        } catch (\Throwable $e) {
            error_log("[plugin:{$pluginId}] load failed: " . $e->getMessage());
        } finally {
            Extension::end();
        }

        if ($loadedOk) {
            self::$currentPluginId = $pluginId;
            self::maybeUpgrade($pluginId, $db);
            if (class_exists('Language')) {
                Language::registerPlugin($pluginId);
                if (class_exists('LanguageMetaModel')) {
                    LanguageMetaModel::registerPluginLanguages($pluginId);
                }
            }
        }

        self::$currentPluginId = '';
        PluginDB::setPlugin('');
    }

    public static function manifest(string $pluginId): array
    {
        return Extension::info(Extension::KIND_PLUGIN, $pluginId);
    }

    public static function verifyId(string $pluginId): bool
    {
        return self::$currentPluginId === $pluginId;
    }

    public static function currentId(): string
    {
        $ext = Extension::currentId();
        return $ext !== '' ? $ext : self::$currentPluginId;
    }

    public static function all(): array
    {
        return self::$plugins;
    }

    public static function enabledIds(): array
    {
        return self::$enabledIds;
    }

    public static function isEnabled(string $id): bool
    {
        return in_array($id, self::$enabledIds, true);
    }

    public static function createTable(string $table, array $columns): bool
    {
        if (!self::$dbh) return false;
        $table = self::prefixTable($table);
        if (!isset($columns['id'])) {
            $columns = array('id' => 'INT UNSIGNED NOT NULL AUTO_INCREMENT') + $columns;
        }
        if (!isset($columns['__PRIMARY__'])) $columns['__PRIMARY__'] = 'id';
        return self::$dbh->createTable($table, $columns);
    }

    public static function dropTable(string $table): bool
    {
        return self::$dbh ? self::$dbh->dropTable(self::prefixTable($table)) : false;
    }

    public static function addColumn(string $table, string $column, string $definition): bool
    {
        return self::$dbh ? self::$dbh->addColumn(self::prefixTable($table), $column, $definition) : false;
    }

    public static function dropColumn(string $table, string $column): bool
    {
        return self::$dbh ? self::$dbh->dropColumn(self::prefixTable($table), $column) : false;
    }

    private static function prefixTable(string $table): string
    {
        $table = preg_replace('/[^a-z0-9_]/i', '', $table);
        $prefix = self::$currentPluginId !== '' ? self::$currentPluginId . '_' : '';
        if ($prefix !== '' && strpos($table, $prefix) === 0) return $table;
        return $prefix . $table;
    }

    public static function delete(string $id, ?object $db = null): array
    {
        if (!Security::validPluginId($id)) {
            return array('ok' => false, 'msg' => t_admin('adm_plugin_svc_bad_id', '插件标识不合法'));
        }
        if (self::isEnabled($id)) {
            return array('ok' => false, 'msg' => t_admin('adm_plugin_svc_active_first', '请先停用该插件，再删除目录'));
        }
        $dir = ROOT_PATH . 'plugins/' . $id;
        if (!is_dir($dir)) {
            return array('ok' => false, 'msg' => t_admin('adm_plugin_svc_dir_missing', '插件目录不存在'));
        }

        if ($db !== null) {
            $un = self::runUninstall($id, $db);
            if (!$un['ok']) {
                return array('ok' => false, 'msg' => t_admin('adm_plugin_svc_uninstall_fail', '卸载钩子执行失败，目录未删除：%s', $un['msg']));
            }
        }

        self::removeTree($dir);
        if ($db !== null) {
            $db->delete(self::REGISTRY_TABLE, ['plugin_id' => $id]);
        }
        unset(self::$registry[$id]);
        self::flushConfigCache($id);
        if (class_exists('Extension')) Extension::cacheFlush(Extension::KIND_PLUGIN, $id);
        if (class_exists('LanguageMetaModel')) {
            LanguageMetaModel::removeByOwner('plugin', $id);
        }
        return array('ok' => true, 'msg' => t_admin('adm_plugin_svc_deleted', '插件目录已删除：%s', $id));
    }

    private static function removeTree(string $dir): void
    {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $item) {
            if ($item->isDir()) { @rmdir($item->getRealPath()); }
            else { @unlink($item->getRealPath()); }
        }
        @rmdir($dir);
    }

    public static function hasAdminPage(string $id): bool
    {
        return is_file(ROOT_PATH . 'plugins/' . $id . '/admin.php');
    }

    public static function view(string $template, array $data = []): string
    {
        $pluginId = self::$currentPluginId;
        $tplFile = ROOT_PATH . "plugins/{$pluginId}/views/{$template}";
        if (!file_exists($tplFile)) return '';

        extract($data);
        ob_start();
        include $tplFile;
        return ob_get_clean();
    }

    public static function enable(string $pluginId, object $db): bool
    {
        if (!Security::validPluginId($pluginId)) return false;
        $mainFile = ROOT_PATH . "plugins/{$pluginId}/main.php";
        if (!file_exists($mainFile)) return false;

        $chk = self::validate($pluginId);
        if (!$chk['ok']) {
            throw new \RuntimeException($chk['msg']);
        }

        $diskVer = self::readDiskVersion($pluginId);

        if (!isset(self::$registry[$pluginId])) {

            self::runInstallHook($pluginId, $db);
            self::registryUpsert($db, $pluginId, $diskVer, 1);
        } else {

            self::registrySetStatus($db, $pluginId, 1);
        }

        if (!in_array($pluginId, self::$enabledIds, true)) {
            self::$enabledIds[] = $pluginId;
        }
        if (class_exists('LanguageMetaModel')) {
            LanguageMetaModel::registerPluginLanguages($pluginId);
        }
        return true;
    }

    public static function disable(string $pluginId, object $db): bool
    {
        if (!Security::validPluginId($pluginId)) return false;
        self::registrySetStatus($db, $pluginId, 0);
        self::$enabledIds = array_values(array_diff(self::$enabledIds, [$pluginId]));
        unset(self::$plugins[$pluginId]);
        Hook::removeOwner('plugin:' . $pluginId);
        self::flushConfigCache($pluginId);
        if (class_exists('Extension')) Extension::cacheFlush(Extension::KIND_PLUGIN, $pluginId);
        return true;
    }

    private static function runInstallHook(string $pluginId, object $db): void
    {
        $mainFile = ROOT_PATH . "plugins/{$pluginId}/main.php";
        $info = self::readManifest($pluginId, dirname($mainFile));

        self::$currentPluginId = $pluginId;
        self::$dbh = $db;
        PluginDB::setPlugin($pluginId);
        if (!isset(self::$plugins[$pluginId])) {
            self::$plugins[$pluginId] = $info;
        }

        $id = $pluginId;
        $path = dirname($mainFile) . '/';
        $url = BASE_URL . '/plugins/' . $pluginId . '/';
        if (!defined('PLUGIN_BOOT')) define('PLUGIN_BOOT', true);
        require_once $mainFile;

        $fn = 'plugin_install_' . $pluginId;
        if (function_exists($fn)) {
            $fn($db);
        } elseif (function_exists('plugin_install')) {
            plugin_install($db);
        }

        self::$currentPluginId = '';
        PluginDB::setPlugin('');
    }

    public static function runUninstall(string $pluginId, object $db): array
    {
        if (!Security::validPluginId($pluginId)) {
            return ['ok' => false, 'msg' => t_admin('adm_plugin_svc_bad_id', '插件标识不合法')];
        }
        $mainFile = ROOT_PATH . "plugins/{$pluginId}/main.php";
        if (file_exists($mainFile)) {
            self::$currentPluginId = $pluginId;
            self::$dbh = $db;
            $id = $pluginId;
            $path = dirname($mainFile) . '/';
            $url = BASE_URL . '/plugins/' . $pluginId . '/';
            if (!defined('PLUGIN_BOOT')) define('PLUGIN_BOOT', true);
            require_once $mainFile;

            $fn = 'plugin_uninstall_' . $pluginId;
            try {
                if (function_exists($fn)) {
                    $fn($db);
                } elseif (function_exists('plugin_uninstall')) {
                    plugin_uninstall($db);
                }
            } catch (\Throwable $e) {
                self::$currentPluginId = '';
                PluginDB::setPlugin('');
                return ['ok' => false, 'msg' => $e->getMessage()];
            }
            self::$currentPluginId = '';
            PluginDB::setPlugin('');
        }
        return ['ok' => true, 'msg' => ''];
    }

    public static function maybeUpgrade(string $pluginId, object $db): void
    {
        if (!Security::validPluginId($pluginId)) return;
        if (!isset(self::$registry[$pluginId])) return;
        $mainFile = ROOT_PATH . "plugins/{$pluginId}/main.php";
        if (!file_exists($mainFile)) return;

        $oldVer = (string)(self::$registry[$pluginId]['version'] ?? '1.0.0');
        $newVer = self::readDiskVersion($pluginId);
        if ($newVer === '' || version_compare($newVer, $oldVer, '<=')) return;

        self::$currentPluginId = $pluginId;
        self::$dbh = $db;
        PluginDB::setPlugin($pluginId);
        $id = $pluginId;
        $path = dirname($mainFile) . '/';
        $url = BASE_URL . '/plugins/' . $pluginId . '/';
        if (!defined('PLUGIN_BOOT')) define('PLUGIN_BOOT', true);
        require_once $mainFile;

        $fn = 'plugin_upgrade_' . $pluginId;
        try {
            if (function_exists($fn)) {
                $fn($db, $oldVer, $newVer);
            } elseif (function_exists('plugin_upgrade')) {
                plugin_upgrade($db, $oldVer, $newVer);
            }
            self::registrySetVersion($db, $pluginId, $newVer);
        } catch (\Throwable $e) {
            error_log("[plugin:{$pluginId}] upgrade failed: " . $e->getMessage());
        }

        self::$currentPluginId = '';
        PluginDB::setPlugin('');
    }

    public static function purgeConfig(string $pluginId): void
    {
        if (!self::$dbh) return;
        self::$dbh->delete('plugin_data', ['plugin_id' => $pluginId, 'entity_type' => 'config']);
        self::flushConfigCache($pluginId);
    }

    public static function purgeTables(string $pluginId): void
    {
        if (!self::$dbh) return;
        $prefix = preg_replace('/[^a-z0-9_]/i', '', $pluginId) . '_';
        try {
            $stmt = self::$dbh->query("SHOW TABLES LIKE ?", [$prefix . '%']);
            foreach ($stmt->fetchAll(\PDO::FETCH_NUM) as $row) {
                $table = $row[0];
                self::$dbh->query("DROP TABLE IF EXISTS `$table`");
            }
        } catch (\Throwable $e) {
            error_log('[plugin] purgeTables failed: ' . $e->getMessage());
        }
    }

    public static function scanAll(): array
    {
        $dirs = glob(ROOT_PATH . 'plugins/*', GLOB_ONLYDIR);
        $list = [];
        foreach ($dirs as $dir) {
            $id = basename($dir);
            $info = self::readManifest($id, $dir);
            $info['enabled'] = in_array($id, self::$enabledIds);
            $list[$id] = $info;
        }
        return $list;
    }

    private static function readManifest(string $id, string $dir): array
    {
        $info = array(
            'id' => $id, 'name' => $id, 'description' => '', 'version' => '1.0.0',
            'cms_adapted' => '', 'category' => '', 'gateway' => '', 'icon' => '',
            'author' => array('name' => '', 'email' => '', 'url' => ''),
            'require' => array('php' => array(), 'ext' => array(), 'plugins' => array()),
        );
        $xmlFile = $dir . '/plugin.xml';
        if (file_exists($xmlFile)) {
            $info = self::parsePluginXml($info, $xmlFile);
        }

        if ($info['icon'] === '' && file_exists($dir . '/logo.png')) {
            $info['icon'] = '/plugins/' . $id . '/logo.png';
        } elseif ($info['icon'] !== '' && strpos($info['icon'], 'http') !== 0 && strpos($info['icon'], '/') !== 0) {
            $info['icon'] = '/plugins/' . $id . '/' . $info['icon'];
        }
        return $info;
    }

    private static function parsePluginXml(array $info, string $xmlFile): array
    {
        try {
            $xml = @simplexml_load_file($xmlFile);
            if ($xml === false) return $info;

            foreach (array('name', 'description', 'version', 'cms_adapted', 'category', 'gateway', 'icon') as $k) {
                if (isset($xml->$k)) $info[$k] = trim((string)$xml->$k);
            }

            if (isset($xml->author)) {
                $authorText = trim((string)$xml->author);
                if (isset($xml->author->name)) $info['author']['name'] = trim((string)$xml->author->name);
                elseif ($authorText !== '') $info['author']['name'] = $authorText;
                if (isset($xml->author->email)) $info['author']['email'] = trim((string)$xml->author->email);
                if (isset($xml->author->url)) $info['author']['url'] = trim((string)$xml->author->url);
            }
            if (isset($xml->author_url)) $info['author']['url'] = trim((string)$xml->author_url);

            if (isset($xml->require)) {
                if (isset($xml->require->php)) $info['require']['php'] = self::parsePhpList(trim((string)$xml->require->php));
                $ext = '';
                if (isset($xml->require->ext)) $ext = trim((string)$xml->require->ext);
                elseif (isset($xml->require->extensions)) $ext = trim((string)$xml->require->extensions);
                $info['require']['ext'] = array_values(array_filter(preg_split('/[,\s]+/', $ext) ?: []));
                $deps = '';
                if (isset($xml->require->plugins)) $deps = trim((string)$xml->require->plugins);
                elseif (isset($xml->dependencies)) $deps = trim((string)$xml->dependencies);
                $info['require']['plugins'] = array_values(array_filter(preg_split('/[,\s]+/', $deps) ?: []));
            }
        } catch (\Throwable $e) {}
        return $info;
    }

    public static function validate(string $id): array
    {
        $info = self::scanAll()[$id] ?? null;
        if (!$info) return array('ok' => false, 'msg' => t_admin('adm_plugin_svc_not_found', '插件不存在'));

        $phpList = $info['require']['php'] ?? array();
        if (!self::phpListCompatible(PHP_VERSION, $phpList)) {
            $phpReq = implode(', ', $phpList);
            return array('ok' => false, 'msg' => t_admin('adm_plugin_svc_php_req', '插件要求 PHP %s，当前为 %s', $phpReq, PHP_VERSION));
        }

        foreach (($info['require']['ext'] ?? array()) as $ext) {
            if (!extension_loaded($ext)) {
                return array('ok' => false, 'msg' => t_admin('adm_plugin_svc_ext_missing', '缺少必需的 PHP 扩展：%s', $ext));
            }
        }

        foreach (($info['require']['plugins'] ?? array()) as $dep) {
            if (!in_array($dep, self::$enabledIds, true)) {
                return array('ok' => false, 'msg' => t_admin('adm_plugin_svc_dep_disabled', '依赖插件未启用：%s', $dep));
            }
        }

        $extChk = Extension::checkRequirements(Extension::KIND_PLUGIN, $id);
        if (!$extChk['ok']) {
            return array('ok' => false, 'msg' => implode('；', $extChk['errors']));
        }

        return array('ok' => true, 'msg' => '');
    }

    private static function parsePhpList(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') return array();
        $raw = preg_replace('/php/i', ' ', $raw);
        $raw = str_replace(array('>=', '<=', '>', '<', '='), ' ', $raw);
        $parts = preg_split('/[,\s]+/', $raw) ?: array();
        $out = array();
        foreach ($parts as $p) {
            if (preg_match('/(\d+)\.(\d+)(?:\.(\d+))?/', $p, $m)) {
                $out[] = $m[1] . '.' . $m[2];
            }
        }
        return array_values(array_unique($out));
    }

    private static function phpVersionCode(string $ver): int
    {
        if (!preg_match('/(\d+)\.(\d+)(?:\.(\d+))?/', $ver, $m)) return 0;
        return (int)$m[1] * 10000 + (int)$m[2] * 100 + (int)($m[3] ?? 0);
    }

    private static function phpListCompatible(string $env, array $list): bool
    {
        if (empty($list)) return true;
        $envCode = self::phpVersionCode($env);
        if ($envCode === 0) return true;
        foreach ($list as $want) {
            $base = self::phpVersionCode($want . '.0');
            if ($base !== 0 && $envCode >= $base && $envCode <= $base + 99) return true;
        }
        return false;
    }
}
