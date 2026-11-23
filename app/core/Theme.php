<?php

class Theme
{
    private static array $registry = [];
    private static $dbh = null;

    private static array $loadedIncludes = [];

    private static string $currentThemeId = '';

    const REGISTRY_TABLE = 'theme_registry';

    public static function setDb($db): void
    {
        self::$dbh = $db;
    }

    public static function init(object $db): void
    {
        self::$dbh = $db;
        self::migrateLegacyInstalled($db);
        self::loadRegistry($db);
    }

    private static function migrateLegacyInstalled(object $db): void
    {
        try {
            if ($db->count(self::REGISTRY_TABLE) > 0) return;
            $row = $db->find('settings', ['key' => 'themes_installed']);
            $legacy = ($row && $row['value']) ? (json_decode((string)$row['value'], true) ?: []) : [];
            foreach ($legacy as $tid) {
                $tid = (string)$tid;
                if (!self::validThemeId($tid)) continue;
                $db->insert(self::REGISTRY_TABLE, [
                    'theme_id' => $tid,
                    'version'  => self::readDiskVersion($tid),
                    'status'   => 1,
                ]);
            }
        } catch (\Throwable $e) {
            error_log('[theme] migrate legacy installed failed: ' . $e->getMessage());
        }
    }

    private static function loadRegistry(object $db): void
    {
        self::$registry = [];
        try {
            foreach ($db->select(self::REGISTRY_TABLE) as $r) {
                $tid = (string)$r['theme_id'];
                if ($tid === '') continue;
                self::$registry[$tid] = ['version' => (string)$r['version'], 'status' => (int)$r['status']];
            }
        } catch (\Throwable $e) {
            error_log('[theme] load registry failed: ' . $e->getMessage());
        }
    }

    private static function readDiskVersion(string $themeId): string
    {
        $xmlFile = ROOT_PATH . "templates/{$themeId}/theme.xml";
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
        $existing = $db->find(self::REGISTRY_TABLE, ['theme_id' => $id]);
        if ($existing) {
            $db->update(self::REGISTRY_TABLE, ['version' => $version, 'status' => $status], ['id' => $existing['id']]);
        } else {
            $db->insert(self::REGISTRY_TABLE, ['theme_id' => $id, 'version' => $version, 'status' => $status]);
        }
        self::$registry[$id] = ['version' => $version, 'status' => $status];
    }

    private static function registrySetStatus(object $db, string $id, int $status): void
    {
        $db->update(self::REGISTRY_TABLE, ['status' => $status], ['theme_id' => $id]);
        if (isset(self::$registry[$id])) self::$registry[$id]['status'] = $status;
    }

    private static function registrySetVersion(object $db, string $id, string $version): void
    {
        $db->update(self::REGISTRY_TABLE, ['version' => $version], ['theme_id' => $id]);
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

    public static function installedIds(): array
    {
        return array_keys(self::$registry);
    }

    public static function isInstalled(string $id): bool
    {
        return self::isRegistered($id);
    }

    public static function activePc(): string
    {
        if (!self::$dbh) return 'default';
        $row = self::$dbh->find('settings', ['key' => 'active_theme']);
        return $row['value'] ?? 'default';
    }

    public static function activeMobile(): string
    {
        if (!self::$dbh) return '';
        $row = self::$dbh->find('settings', ['key' => 'mobile_theme']);
        return $row['value'] ?? '';
    }

    public static function loadActiveIncludes(): void
    {
        $ids = array_filter([self::activePc(), self::activeMobile()], function ($v) {
            return is_string($v) && $v !== '';
        });
        foreach (array_unique($ids) as $tid) {
            self::loadInclude($tid);
        }
    }

    public static function loadInclude(string $themeId): void
    {
        $themeId = trim($themeId);
        if ($themeId === '' || !self::validThemeId($themeId)) return;
        if (isset(self::$loadedIncludes[$themeId])) return;

        $dir = ROOT_PATH . 'templates/' . $themeId;
        $file = $dir . '/include.php';
        if (!is_file($file)) return;
        self::$loadedIncludes[$themeId] = true;

        $context = Extension::context(Extension::KIND_THEME, $themeId, self::$dbh);
        self::$currentThemeId = $themeId;
        Extension::begin(Extension::KIND_THEME, $themeId);
        try {
            extract($context);
            require_once $file;
            if (function_exists('theme_init')) {
                theme_init($context['db']);
            }
            Extension::markLoaded(Extension::KIND_THEME, $themeId);
        } catch (\Throwable $e) {
            error_log("[theme:{$themeId}] include.php load failed: " . $e->getMessage());
        } finally {
            Extension::end();
            self::$currentThemeId = '';
        }
    }

    public static function currentId(): string
    {
        return self::$currentThemeId;
    }

    public static function typeOf(string $themeId): string
    {
        $info = Extension::info(Extension::KIND_THEME, $themeId);
        $type = (string)($info['type'] ?? '');
        return $type !== '' ? $type : 'pc';
    }

    public static function supports(string $themeType, string $target): bool
    {
        if ($themeType === 'both') return true;
        return $themeType === $target;
    }

    public static function typeLabel(string $themeType): string
    {
        if ($themeType === 'both') return 'PC + ' . t_admin('adm_theme_mobile', '手机');
        if ($themeType === 'mobile') return t_admin('adm_theme_mobile', '手机');
        return 'PC';
    }

    public static function verifyId(string $themeId): bool
    {
        return self::$currentThemeId === $themeId;
    }

    public static function manifest(string $themeId): array
    {
        return Extension::info(Extension::KIND_THEME, $themeId);
    }

    public static function isActive(string $id): bool
    {
        return $id === self::activePc() || $id === self::activeMobile();
    }

    public static function config(string $key, $default = '', ?string $themeId = null)
    {
        $tid = $themeId ?: self::activePc();
        if (!self::$dbh || $tid === '') return $default;
        $row = self::$dbh->find('settings', ['key' => "theme_config.{$tid}.{$key}"]);
        return $row ? $row['value'] : $default;
    }

    public static function setConfig(string $key, $value, ?string $themeId = null): bool
    {
        $tid = $themeId ?: self::activePc();
        if (!self::$dbh || $tid === '') return false;
        $fullKey = "theme_config.{$tid}.{$key}";
        $value = (string)$value;
        $existing = self::$dbh->find('settings', ['key' => $fullKey]);
        if ($existing) {
            self::$dbh->update('settings', ['value' => $value], ['key' => $fullKey]);
        } else {
            self::$dbh->insert('settings', ['key' => $fullKey, 'value' => $value]);
        }
        return true;
    }

    public static function scanAll(): array
    {
        $dirs = glob(ROOT_PATH . 'templates/*', GLOB_ONLYDIR);
        $list = [];
        foreach ($dirs as $dir) {
            $id = basename($dir);
            $info = self::readManifest($id, $dir);
            $info['installed'] = self::isRegistered($id);
            $info['active_pc']  = ($id === self::activePc());
            $info['active_mobile'] = ($id === self::activeMobile());
            $info['has_config'] = is_file($dir . '/config.php');
            $list[$id] = $info;
        }
        return $list;
    }

    private static function readManifest(string $id, string $dir): array
    {
        $norm = Extension::info(Extension::KIND_THEME, $id);
        $type = (string)($norm['type'] ?? '');
        if ($type === '') $type = 'pc';
        $info = [
            'id'           => $id,
            'name'         => $norm['name'],
            'title'        => $norm['name'],
            'description'  => $norm['description'],
            'version'      => $norm['version'],
            'author'       => $norm['author'],
            'website'      => $norm['website'],
            'type'         => $type,
            'supports_pc'  => self::supports($type, 'pc'),
            'supports_mobile' => self::supports($type, 'mobile'),
            'min_version'  => $norm['compatible_versions'],
            'level'        => $norm['level'],
            'adapted'      => $norm['adapted'],
            'price'        => $norm['price'],
            'requires'     => $norm['requires'],
            'dependencies' => $norm['dependencies'],
            'conflicts'    => $norm['conflicts'],
            'sidebars'     => $norm['sidebars'],
            'settings'     => $norm['settings'],
            'menus'        => $norm['menus'],
            'config_file'  => $norm['config_file'],
            'screenshot'   => '',
        ];

        $screenshot = '';
        foreach (['preview.png', 'screenshot.png', 'cover.jpg', 'cover.png', 'preview.jpg'] as $candidate) {
            if (file_exists($dir . '/' . $candidate)) {
                $screenshot = '/templates/' . $id . '/' . $candidate;
                break;
            }
        }
        if ($screenshot === '' && file_exists($dir . '/assets/images/screenshot.png')) {
            $screenshot = '/templates/' . $id . '/assets/images/screenshot.png';
        }
        if ($screenshot === '' && file_exists(ROOT_PATH . 'assets/images/default_template_logo.png')) {
            $screenshot = '/assets/images/default_template_logo.png';
        }
        $info['screenshot'] = $screenshot;
        return $info;
    }

    public static function install(string $themeId, object $db): array
    {
        if (!self::validThemeId($themeId)) {
            return ['ok' => false, 'msg' => t_admin('adm_theme_svc_bad_id', '主题标识不合法')];
        }
        $dir = ROOT_PATH . 'templates/' . $themeId;
        if (!is_dir($dir)) {
            return ['ok' => false, 'msg' => t_admin('adm_theme_svc_dir_missing_id', '主题目录不存在：%s', $themeId)];
        }
        if (!file_exists($dir . '/theme.xml')) {
            return ['ok' => false, 'msg' => t_admin('adm_theme_svc_no_meta_file', '缺少 theme.xml 清单文件')];
        }

        $check = Extension::checkRequirements(Extension::KIND_THEME, $themeId);
        if (!$check['ok']) {
            return ['ok' => false, 'msg' => implode('；', $check['errors'])];
        }

        if (!self::isRegistered($themeId)) {
            self::runInstallHook($themeId, $db);
            self::registryUpsert($db, $themeId, self::readDiskVersion($themeId), 1);
        } else {
            self::registrySetStatus($db, $themeId, 1);
        }

        return ['ok' => true, 'msg' => t_admin('adm_theme_svc_installed', '主题安装成功：%s', $themeId)];
    }

    public static function enable(string $themeId, string $type, object $db): array
    {
        if (!self::validThemeId($themeId)) {
            return ['ok' => false, 'msg' => t_admin('adm_theme_svc_bad_id', '主题标识不合法')];
        }
        if (!self::isRegistered($themeId)) {
            return ['ok' => false, 'msg' => t_admin('adm_theme_svc_install_first', '请先安装主题再启用：%s', $themeId)];
        }
        if (!is_dir(ROOT_PATH . 'templates/' . $themeId)) {
            return ['ok' => false, 'msg' => t_admin('adm_theme_svc_dir_missing_id', '主题目录不存在：%s', $themeId)];
        }

        $target = ($type === 'mobile') ? 'mobile' : 'pc';
        $themeType = self::typeOf($themeId);
        if (!self::supports($themeType, $target)) {
            $targetLabel = ($target === 'mobile') ? t_admin('adm_theme_mobile_label', '手机主题') : t_admin('adm_theme_pc_label', 'PC主题');
            return ['ok' => false, 'msg' => t_admin('adm_theme_svc_type_mismatch', '该主题类型为 %s，不能设为%s', self::typeLabel($themeType), $targetLabel)];
        }

        $key = ($target === 'mobile') ? 'mobile_theme' : 'active_theme';
        $old = ($target === 'mobile') ? self::activeMobile() : self::activePc();

        $existing = $db->find('settings', ['key' => $key]);
        if ($existing) {
            $db->update('settings', ['value' => $themeId], ['key' => $key]);
        } else {
            $db->insert('settings', ['key' => $key, 'value' => $themeId]);
        }

        self::registrySetStatus($db, $themeId, 1);

        if ($old !== '' && $old !== $themeId && !self::isActive($old)) {
            self::registrySetStatus($db, $old, 0);
        }

        $label = ($target === 'mobile') ? t_admin('adm_theme_mobile_label', '手机主题') : t_admin('adm_theme_pc_label', 'PC主题');
        return ['ok' => true, 'msg' => t_admin('adm_theme_svc_enabled', '%s已切换为：%s', $label, $themeId)];
    }

    public static function disableMobile(object $db): array
    {
        $oldMobile = self::activeMobile();
        $existing = $db->find('settings', ['key' => 'mobile_theme']);
        if ($existing) {
            $db->update('settings', ['value' => ''], ['key' => 'mobile_theme']);
        }
        if ($oldMobile !== '' && $oldMobile !== self::activePc()) {
            self::registrySetStatus($db, $oldMobile, 0);
        }
        return ['ok' => true, 'msg' => t_admin('adm_theme_svc_mobile_disabled', '手机主题已停用，恢复跟随PC主题')];
    }

    public static function uninstall(string $themeId, object $db): array
    {
        if (!self::validThemeId($themeId)) {
            return ['ok' => false, 'msg' => t_admin('adm_theme_svc_bad_id', '主题标识不合法')];
        }
        if ($themeId === 'default') {
            return ['ok' => false, 'msg' => t_admin('adm_theme_svc_default_uninstall_deny', '默认主题不可卸载')];
        }
        if (!self::isRegistered($themeId)) {
            return ['ok' => false, 'msg' => t_admin('adm_theme_svc_not_installed', '主题未安装：%s', $themeId)];
        }
        if (self::isActive($themeId)) {
            return ['ok' => false, 'msg' => t_admin('adm_theme_svc_switch_before_uninstall', '请先切换到其他主题，再卸载：%s', $themeId)];
        }

        $un = self::runUninstallHook($themeId, $db);
        if (!$un['ok']) {
            return ['ok' => false, 'msg' => t_admin('adm_theme_svc_uninstall_fail', '卸载钩子执行失败，目录未删除：%s', $un['msg'])];
        }

        self::clearConfig($themeId, $db);

        Hook::removeOwner('theme:' . $themeId);

        $dir = ROOT_PATH . 'templates/' . $themeId;
        if (is_dir($dir)) self::removeTree($dir);

        $db->delete(self::REGISTRY_TABLE, ['theme_id' => $themeId]);
        unset(self::$registry[$themeId]);

        return ['ok' => true, 'msg' => t_admin('adm_theme_svc_uninstalled', '主题已卸载：%s', $themeId)];
    }

    public static function delete(string $themeId, object $db): array
    {
        if (!self::isRegistered($themeId)) {
            $dir = ROOT_PATH . 'templates/' . $themeId;
            if (is_dir($dir)) self::removeTree($dir);
            return ['ok' => true, 'msg' => t_admin('adm_theme_svc_uninstalled', '主题已删除：%s', $themeId)];
        }
        return self::uninstall($themeId, $db);
    }

    public static function maybeUpgrade(string $themeId, object $db): void
    {
        if (!self::validThemeId($themeId)) return;
        if (!self::isRegistered($themeId)) return;
        $dir = ROOT_PATH . 'templates/' . $themeId;
        if (!is_dir($dir)) return;

        $oldVer = (string)(self::$registry[$themeId]['version'] ?? '1.0.0');
        $newVer = self::readDiskVersion($themeId);
        if ($newVer === '' || version_compare($newVer, $oldVer, '<=')) return;

        $self = $themeId;
        $thisDir = $dir;
        $entry = self::entryFile($themeId);
        if ($entry !== '' && file_exists($entry)) {
            require_once $entry;
        }
        $fn = 'theme_upgrade_' . $themeId;
        try {
            if (function_exists($fn)) {
                $fn($db, $oldVer, $newVer);
            } elseif (function_exists('theme_upgrade')) {
                theme_upgrade($db, $oldVer, $newVer);
            }
            self::registrySetVersion($db, $themeId, $newVer);
        } catch (\Throwable $e) {
            error_log("[theme:{$themeId}] upgrade failed: " . $e->getMessage());
        }
    }

    private static function runInstallHook(string $themeId, object $db): void
    {
        $entry = self::entryFile($themeId);
        if ($entry !== '' && file_exists($entry)) {
            require_once $entry;
        }
        $fn = 'theme_install_' . $themeId;
        if (function_exists($fn)) {
            $fn($db);
        } elseif (function_exists('theme_install')) {
            theme_install($db);
        }
    }

    public static function runUninstallHook(string $themeId, object $db): array
    {
        $entry = self::entryFile($themeId);
        if ($entry !== '' && file_exists($entry)) {
            require_once $entry;
        }
        $fn = 'theme_uninstall_' . $themeId;
        try {
            if (function_exists($fn)) {
                $fn($db);
            } elseif (function_exists('theme_uninstall')) {
                theme_uninstall($db);
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'msg' => $e->getMessage()];
        }
        return ['ok' => true, 'msg' => ''];
    }

    private static function entryFile(string $themeId): string
    {
        $dir = ROOT_PATH . 'templates/' . $themeId;
        if (file_exists($dir . '/include.php')) return $dir . '/include.php';
        return '';
    }

    private static function removeTree(string $dir): void
    {
        if (!is_dir($dir)) return;
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

    public static function configFields(string $themeId): array
    {
        if (!self::validThemeId($themeId)) return [];
        $file = ROOT_PATH . 'templates/' . $themeId . '/config.php';
        if (!file_exists($file)) return [];
        $defs = require $file;
        return is_array($defs) ? $defs : [];
    }

    public static function saveConfig(string $themeId): array
    {
        if (!self::validThemeId($themeId)) {
            return ['ok' => false, 'msg' => t_admin('adm_theme_bad_id', '主题标识不合法或安全验证失败')];
        }
        if (!isset($_POST['tcfg']) || !is_array($_POST['tcfg'])) {
            return ['ok' => false, 'msg' => t_admin('adm_theme_svc_no_config_data', '无配置数据')];
        }
        $defs = self::configFields($themeId);
        $known = [];
        foreach ($defs as $def) {
            $known[$def['name']] = $def;
        }
        foreach ($defs as $def) {
            if (($def['type'] ?? 'text') === 'switch' && !isset($_POST['tcfg'][$def['name']])) {
                $_POST['tcfg'][$def['name']] = 0;
            }
        }
        foreach ($_POST['tcfg'] as $k => $v) {
            if (!isset($known[$k])) continue;
            $def = $known[$k];
            $type = $def['type'] ?? 'text';
            if ($type === 'number') $v = (float)$v;
            elseif ($type === 'switch') $v = $v ? 1 : 0;
            else $v = trim((string)$v);
            self::setConfig($k, $v, $themeId);
        }
        return ['ok' => true, 'msg' => t_admin('adm_theme_config_saved', '主题配置已保存')];
    }

    private static function clearConfig(string $themeId, object $db): void
    {
        try {
            $prefix = "theme_config.{$themeId}.";
            $rows = $db->select('settings', [], '', 0, 0, 'key,value');
            foreach ($rows as $row) {
                if (strpos($row['key'], $prefix) === 0) {
                    $db->delete('settings', ['key' => $row['key']]);
                }
            }
        } catch (\Throwable $e) {}
    }

    public static function validThemeId(string $id): bool
    {
        return (bool)preg_match('/^[a-z0-9_]+$/', $id);
    }
}
