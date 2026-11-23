<?php

class Extension
{
    const KIND_PLUGIN = 'plugin';
    const KIND_THEME  = 'theme';

    private static $dbh = null;
    private static string $kind = '';
    private static string $id = '';
    private static array $stack = [];
    private static array $infoCache = [];
    private static array $menus = [];
    private static array $settings = [];
    private static array $sidebars = [];
    private static array $loaded = [];

    public static function setDb($db): void
    {
        self::$dbh = $db;
    }

    public static function db()
    {
        return self::$dbh;
    }

    public static function validKind(string $kind): bool
    {
        return $kind === self::KIND_PLUGIN || $kind === self::KIND_THEME;
    }

    public static function validId(string $id): bool
    {
        return $id !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $id) === 1;
    }

    public static function root(string $kind, string $id): string
    {
        $base = $kind === self::KIND_THEME ? 'templates/' : 'plugins/';
        return ROOT_PATH . $base . $id . '/';
    }

    public static function url(string $kind, string $id): string
    {
        $base = $kind === self::KIND_THEME ? '/templates/' : '/plugins/';
        return BASE_URL . $base . $id . '/';
    }

    public static function entry(string $kind, string $id): string
    {
        $name = $kind === self::KIND_THEME ? 'include.php' : 'main.php';
        $file = self::root($kind, $id) . $name;
        return is_file($file) ? $file : '';
    }

    public static function begin(string $kind, string $id): void
    {
        if (self::$kind !== '' || self::$id !== '') {
            self::$stack[] = [self::$kind, self::$id];
        }
        self::$kind = $kind;
        self::$id = $id;
    }

    public static function end(): void
    {
        if (self::$stack) {
            [$k, $i] = array_pop(self::$stack);
            self::$kind = $k;
            self::$id = $i;
            return;
        }
        self::$kind = '';
        self::$id = '';
    }

    public static function currentKind(): string
    {
        return self::$kind;
    }

    public static function currentId(): string
    {
        return self::$id;
    }

    public static function current(): string
    {
        return self::$kind === '' ? '' : self::$kind . ':' . self::$id;
    }

    public static function verify(string $kind, string $id): bool
    {
        return self::$kind === $kind && self::$id === $id;
    }

    public static function context(string $kind, string $id, $db = null): array
    {
        $info = self::info($kind, $id);
        return [
            'id'        => $id,
            'kind'      => $kind,
            'info'      => $info,
            'db'        => $db ?: self::$dbh,
            'path'      => self::root($kind, $id),
            'url'       => self::url($kind, $id),
            'extension' => __CLASS__,
        ];
    }

    public static function load(string $kind, string $id, $db = null): bool
    {
        $key = $kind . ':' . $id;
        if (isset(self::$loaded[$key])) return true;
        $entry = self::entry($kind, $id);
        if ($entry === '') return false;

        self::$loaded[$key] = true;
        $context = self::context($kind, $id, $db);
        self::begin($kind, $id);
        try {
            extract($context);
            require_once $entry;
            $init = $kind === self::KIND_THEME ? 'theme_init' : 'plugin_init_' . $id;
            if ($kind === self::KIND_THEME && function_exists('theme_init')) {
                theme_init($context['db']);
            } elseif ($kind === self::KIND_PLUGIN && function_exists($init)) {
                $init($context['db']);
            }
            return true;
        } catch (\Throwable $e) {
            error_log("[ext:{$key}] load failed: " . $e->getMessage());
            return false;
        } finally {
            self::end();
        }
    }

    public static function isLoaded(string $kind, string $id): bool
    {
        return isset(self::$loaded[$kind . ':' . $id]);
    }

    public static function markLoaded(string $kind, string $id): void
    {
        self::$loaded[$kind . ':' . $id] = true;
    }

    public static function manifestFile(string $kind, string $id): string
    {
        $file = $kind === self::KIND_THEME ? 'theme.xml' : 'plugin.xml';
        return self::root($kind, $id) . $file;
    }

    public static function info(string $kind, string $id): array
    {
        $key = $kind . ':' . $id;
        if (isset(self::$infoCache[$key])) return self::$infoCache[$key];

        $cacheKey = self::manifestCacheKey($kind, $id);
        if ($cacheKey !== '') {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                self::$infoCache[$key] = $cached;
                return $cached;
            }
        }

        $info = $kind === self::KIND_THEME ? self::parseThemeManifest($id) : self::parsePluginManifest($id);
        self::$infoCache[$key] = $info;
        if ($cacheKey !== '') Cache::set($cacheKey, $info, 86400);
        return $info;
    }

    private static function manifestCacheKey(string $kind, string $id): string
    {
        $file = self::manifestFile($kind, $id);
        if (!is_file($file)) return '';
        clearstatcache(true, $file);
        $sig = (string)@filemtime($file) . '|' . (string)@filesize($file);
        return 'ext_manifest_' . $kind . '_' . $id . '_' . md5($file . '|' . $sig);
    }

    public static function flushInfo(string $kind = '', string $id = ''): void
    {
        if ($kind === '') {
            self::$infoCache = [];
            return;
        }
        unset(self::$infoCache[$kind . ':' . $id]);
        $cacheKey = self::manifestCacheKey($kind, $id);
        if ($cacheKey !== '') Cache::delete($cacheKey);
    }

    public static function cacheKeyOf(string $key, string $kind = '', string $id = ''): string
    {
        $kind = $kind !== '' ? $kind : (self::$kind !== '' ? self::$kind : self::KIND_PLUGIN);
        $id = $id !== '' ? $id : self::$id;
        if (!self::validId($id)) return '';
        return 'ext_cache_' . $kind . '_' . $id . '_' . md5($key);
    }

    private static function cacheIndexKey(string $kind, string $id): string
    {
        return 'ext_cache_idx_' . $kind . '_' . $id;
    }

    public static function cacheReady(): bool
    {
        return class_exists('Cache') && Cache::isEnabled();
    }

    public static function cacheGet(string $key, $default = null, string $kind = '', string $id = '')
    {
        $ck = self::cacheKeyOf($key, $kind, $id);
        if ($ck === '' || !self::cacheReady()) return $default;
        $v = Cache::get($ck);
        return $v === null ? $default : $v;
    }

    public static function cacheSet(string $key, $value, ?int $ttl = null, string $kind = '', string $id = ''): bool
    {
        $ck = self::cacheKeyOf($key, $kind, $id);
        if ($ck === '' || !self::cacheReady()) return false;
        Cache::set($ck, $value, $ttl);
        self::cacheIndexAdd($ck, $kind, $id);
        return true;
    }

    public static function cacheRemember(string $key, int $ttl, callable $callback, string $kind = '', string $id = '')
    {
        $v = self::cacheGet($key, null, $kind, $id);
        if ($v !== null) return $v;
        $value = $callback();
        self::cacheSet($key, $value, $ttl, $kind, $id);
        return $value;
    }

    public static function cacheForget(string $key, string $kind = '', string $id = ''): void
    {
        $ck = self::cacheKeyOf($key, $kind, $id);
        if ($ck !== '') Cache::delete($ck);
    }

    public static function cacheFlush(string $kind, string $id): int
    {
        if (!self::validKind($kind) || !self::validId($id)) return 0;
        $idxKey = self::cacheIndexKey($kind, $id);
        $list = Cache::get($idxKey);
        $n = 0;
        if (is_array($list)) {
            foreach ($list as $ck) {
                Cache::delete((string)$ck);
                $n++;
            }
        }
        Cache::delete($idxKey);
        self::flushInfo($kind, $id);
        return $n;
    }

    private static function cacheIndexAdd(string $ck, string $kind, string $id): void
    {
        $kind = $kind !== '' ? $kind : (self::$kind !== '' ? self::$kind : self::KIND_PLUGIN);
        $id = $id !== '' ? $id : self::$id;
        if (!self::validId($id)) return;
        $idxKey = self::cacheIndexKey($kind, $id);
        $list = Cache::get($idxKey);
        if (!is_array($list)) $list = [];
        if (!in_array($ck, $list, true)) {
            $list[] = $ck;
            Cache::set($idxKey, $list, 2592000);
        }
    }

    private static function parseThemeManifest(string $id): array
    {
        $info = self::defaultManifest(self::KIND_THEME, $id);
        $file = self::manifestFile(self::KIND_THEME, $id);
        if (!is_file($file)) {
            $dir = self::root(self::KIND_THEME, $id);
            if (!is_dir($dir)) return $info;
        }
        if (!is_file($file)) return $info;
        try {
            $xml = @simplexml_load_file($file);
            if ($xml === false) return $info;
        } catch (\Throwable $e) {
            return $info;
        }

        foreach (['name' => 'name', 'version' => 'version', 'description' => 'description', 'author' => 'author', 'website' => 'website', 'type' => 'type', 'level' => 'level', 'adapted' => 'adapted', 'price' => 'price', 'screenshot' => 'screenshot'] as $xmlKey => $field) {
            if (isset($xml->$xmlKey)) $info[$field] = trim((string)$xml->$xmlKey);
        }
        if (isset($xml->compatible_versions)) $info['compatible_versions'] = trim((string)$xml->compatible_versions);
        if (isset($xml->requires)) {
            if (isset($xml->requires->php)) $info['requires']['php'] = trim((string)$xml->requires->php);
            if (isset($xml->requires->cms)) $info['requires']['cms'] = trim((string)$xml->requires->cms);
        }
        if (isset($xml->advanced)) {
            $adv = $xml->advanced;
            $info['dependencies'] = self::parseUnitList((string)($adv->dependency ?? ''));
            $info['conflicts']    = self::parseUnitList((string)($adv->conflict ?? ''));
            $info['rewrite_functions'] = self::parseNameList((string)($adv->rewrite_functions ?? ''));
            $info['exists_functions']  = self::parseNameList((string)($adv->exists_functions ?? ''));
        }
        if (isset($xml->sidebars)) {
            $info['sidebars'] = self::parseSlotList((string)$xml->sidebars);
        }
        if (isset($xml->config)) {
            $info['config_file'] = trim((string)$xml->config);
        } elseif (is_file(self::root(self::KIND_THEME, $id) . 'config.php')) {
            $info['config_file'] = 'config.php';
        }
        if (isset($xml->settings)) {
            foreach ($xml->settings->children() as $name => $node) {
                $info['settings'][(string)$name] = [
                    'label'   => trim((string)($node['label'] ?? $name)),
                    'type'    => trim((string)($node['type'] ?? 'text')),
                    'default' => trim((string)($node['default'] ?? '')),
                ];
            }
        }
        if (isset($xml->menus)) {
            foreach ($xml->menus->children() as $node) {
                $info['menus'][] = [
                    'action' => trim((string)($node['action'] ?? '')),
                    'label'  => trim((string)$node),
                    'icon'   => trim((string)($node['icon'] ?? 'fa-circle')),
                    'group'  => trim((string)($node['group'] ?? '')),
                ];
            }
        }
        $info['type'] = self::normalizeThemeType((string)($info['type'] ?? ''));
        return $info;
    }

    private static function parsePluginManifest(string $id): array
    {
        $info = self::defaultManifest(self::KIND_PLUGIN, $id);
        $file = self::manifestFile(self::KIND_PLUGIN, $id);
        if (!is_file($file)) return $info;
        try {
            $xml = @simplexml_load_file($file);
            if ($xml === false) return $info;
        } catch (\Throwable $e) {
            return $info;
        }
        foreach (['name' => 'name', 'version' => 'version', 'description' => 'description', 'category' => 'category', 'cms_adapted' => 'cms_adapted', 'level' => 'level', 'adapted' => 'adapted', 'price' => 'price'] as $xmlKey => $field) {
            if (isset($xml->$xmlKey)) $info[$field] = trim((string)$xml->$xmlKey);
        }
        if (isset($xml->author)) {
            $authorText = trim((string)$xml->author);
            if (isset($xml->author->name)) $info['author'] = trim((string)$xml->author->name);
            elseif ($authorText !== '') $info['author'] = $authorText;
        }
        if (isset($xml->require)) {
            if (isset($xml->require->php)) $info['requires']['php'] = trim((string)$xml->require->php);
            if (isset($xml->require->plugins)) $info['dependencies'] = self::parseUnitList((string)$xml->require->plugins);
        }
        if (isset($xml->advanced)) {
            $adv = $xml->advanced;
            $info['dependencies'] = array_merge($info['dependencies'], self::parseUnitList((string)($adv->dependency ?? '')));
            $info['conflicts']    = self::parseUnitList((string)($adv->conflict ?? ''));
            $info['rewrite_functions'] = self::parseNameList((string)($adv->rewrite_functions ?? ''));
            $info['exists_functions']  = self::parseNameList((string)($adv->exists_functions ?? ''));
        }
        if (isset($xml->sidebars)) {
            $info['sidebars'] = self::parseSlotList((string)$xml->sidebars);
        }
        return $info;
    }

    public static function themeTypeValid(string $raw): bool
    {
        return in_array(strtolower(trim($raw)), array('pc', 'mobile', 'both', 'responsive'), true);
    }

    private static function normalizeThemeType(string $raw): string
    {
        $map = array('pc' => 'pc', 'mobile' => 'mobile', 'both' => 'both', 'responsive' => 'both');
        $key = strtolower(trim($raw));
        return isset($map[$key]) ? $map[$key] : 'pc';
    }

    private static function defaultManifest(string $kind, string $id): array
    {
        return [
            'kind' => $kind, 'id' => $id, 'name' => $id, 'version' => '1.0.0',
            'description' => '', 'author' => '', 'website' => '', 'type' => $kind === self::KIND_THEME ? 'pc' : '',
            'level' => 'free', 'adapted' => '', 'price' => '0', 'screenshot' => '',
            'compatible_versions' => '3.0.0', 'category' => '', 'cms_adapted' => '',
            'requires' => ['php' => '8.0.0', 'cms' => '3.0.0'],
            'dependencies' => [], 'conflicts' => [],
            'rewrite_functions' => [], 'exists_functions' => [],
            'sidebars' => [], 'config_file' => '', 'settings' => [], 'menus' => [],
        ];
    }

    private static function parseUnitList(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') return [];
        $out = [];
        foreach (preg_split('/[,\s]+/', $raw) ?: [] as $token) {
            $token = trim($token);
            if ($token === '') continue;
            $min = '';
            if (strpos($token, '>=') !== false) {
                [$token, $min] = array_map('trim', explode('>=', $token, 2));
            }
            if (strpos($token, ':') === false) {
                $token = self::KIND_PLUGIN . ':' . $token;
            }
            [$kind, $depId] = array_map('trim', explode(':', $token, 2));
            if (!self::validKind($kind) || !self::validId($depId)) continue;
            $out[] = ['kind' => $kind, 'id' => $depId, 'min' => $min];
        }
        return $out;
    }

    private static function parseNameList(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') return [];
        return array_values(array_filter(array_map('trim', preg_split('/[,\s]+/', $raw) ?: []), function ($v) {
            return $v !== '' && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $v) === 1;
        }));
    }

    private static function parseSlotList(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') return [];
        $slots = [];
        foreach (preg_split('/[,\s]+/', $raw) ?: [] as $token) {
            if ($token === '') continue;
            if (strpos($token, '-') !== false) {
                [$a, $b] = array_map('intval', explode('-', $token, 2));
                if ($a > 0 && $b >= $a) {
                    for ($i = $a; $i <= $b; $i++) $slots[$i] = $i;
                }
            } elseif ((int)$token > 0) {
                $slots[(int)$token] = (int)$token;
            }
        }
        ksort($slots);
        return array_values($slots);
    }

    public static function isEnabled(string $kind, string $id): bool
    {
        if ($kind === self::KIND_PLUGIN) {
            return class_exists('Plugin') && Plugin::isEnabled($id);
        }
        return class_exists('Theme') && Theme::isActive($id);
    }

    public static function isInstalled(string $kind, string $id): bool
    {
        if ($kind === self::KIND_PLUGIN) {
            return class_exists('Plugin') && Plugin::isRegistered($id);
        }
        return class_exists('Theme') && Theme::isRegistered($id);
    }

    public static function checkRequirements(string $kind, string $id, ?array $info = null): array
    {
        $info = $info ?: self::info($kind, $id);
        $errors = [];
        $warnings = [];

        $phpReq = (string)($info['requires']['php'] ?? '');
        if ($phpReq !== '') {
            $first = trim(explode(',', $phpReq)[0]);
            $first = ltrim($first, '><= ');
            if ($first !== '' && version_compare(PHP_VERSION, $first, '<')) {
                $errors[] = t_admin('adm_ext_err_php', '要求 PHP %s，当前 %s', $phpReq, PHP_VERSION);
            }
        }

        $cmsReq = (string)($info['requires']['cms'] ?? '');
        if ($cmsReq !== '' && defined('ENOVELCMS_VERSION')) {
            $first = ltrim(trim(explode(',', $cmsReq)[0]), '><= ');
            if ($first !== '' && version_compare(ENOVELCMS_VERSION, $first, '<')) {
                $errors[] = t_admin('adm_ext_err_cms', '要求程序版本 %s，当前 %s', $cmsReq, ENOVELCMS_VERSION);
            }
        }

        foreach (($info['dependencies'] ?? []) as $dep) {
            $depKind = $dep['kind'];
            $depId = $dep['id'];
            if (!is_dir(self::root($depKind, $depId))) {
                $errors[] = t_admin('adm_ext_err_dep_missing', '缺少依赖：%s（未安装）', $depKind . ':' . $depId);
                continue;
            }
            if (!self::isInstalled($depKind, $depId)) {
                $errors[] = t_admin('adm_ext_err_dep_install', '依赖 %s 未安装，请先安装', $depKind . ':' . $depId);
                continue;
            }
            if (!self::isEnabled($depKind, $depId)) {
                $warnings[] = t_admin('adm_ext_warn_dep_disabled', '依赖 %s 已安装但未启用', $depKind . ':' . $depId);
            }
            if (!empty($dep['min'])) {
                $depVersion = (string)(self::info($depKind, $depId)['version'] ?? '');
                if ($depVersion !== '' && version_compare($depVersion, $dep['min'], '<')) {
                    $errors[] = t_admin('adm_ext_err_dep_version', '依赖 %s 需要 %s 及以上，当前 %s', $depKind . ':' . $depId, $dep['min'], $depVersion);
                }
            }
        }

        foreach (($info['conflicts'] ?? []) as $cf) {
            $cfKind = $cf['kind'];
            $cfId = $cf['id'];
            if ($cfKind === $kind && $cfId === $id) continue;
            if (self::isEnabled($cfKind, $cfId)) {
                $errors[] = t_admin('adm_ext_err_conflict', '与已启用的 %s 存在冲突，请先停用', $cfKind . ':' . $cfId);
            }
        }

        $rewrite = $info['rewrite_functions'] ?? [];
        foreach ($rewrite as $fn) {
            if (!function_exists($fn)) {
                $warnings[] = t_admin('adm_ext_warn_rewrite', '待重写的函数 %s 不存在，请确认程序版本', $fn);
            }
        }
        foreach (($info['exists_functions'] ?? []) as $fn) {
            if (!function_exists($fn)) {
                $errors[] = t_admin('adm_ext_err_exists_fn', '依赖的程序函数 %s 不存在', $fn);
            }
        }

        return ['ok' => empty($errors), 'errors' => $errors, 'warnings' => $warnings];
    }

    public static function config(string $kind, string $id, string $key, $default = '')
    {
        if ($kind === self::KIND_THEME) {
            return class_exists('Theme') ? Theme::config($key, $default, $id) : $default;
        }
        return class_exists('Plugin') ? Plugin::config($key, $default, $id) : $default;
    }

    public static function setConfig(string $kind, string $id, string $key, $value): bool
    {
        if ($kind === self::KIND_THEME) {
            return class_exists('Theme') ? Theme::setConfig($key, $value, $id) : false;
        }
        return class_exists('Plugin') ? Plugin::setConfig($key, $value, $id) : false;
    }

    public static function conf(string $key, $default = '')
    {
        if (self::$kind === '') return $default;
        return self::config(self::$kind, self::$id, $key, $default);
    }

    public static function setConf(string $key, $value): bool
    {
        if (self::$kind === '') return false;
        return self::setConfig(self::$kind, self::$id, $key, $value);
    }

    public static function menu(array $items): void
    {
        $unit = self::current();
        if ($unit === '') return;
        foreach ($items as $item) {
            if (!is_array($item) || empty($item['action']) || empty($item['label'])) continue;
            $item['_unit'] = $unit;
            self::$menus[] = $item;
        }
    }

    public static function menuItems(): array
    {
        return self::$menus;
    }

    public static function setting(string $name, string $label, string $type = 'text', $default = '', array $options = []): void
    {
        $unit = self::current();
        if ($unit === '') return;
        self::$settings[$unit][$name] = [
            'label' => $label, 'type' => $type, 'default' => $default, 'options' => $options,
        ];
    }

    public static function settings(?string $unit = null): array
    {
        if ($unit === null) return self::$settings;
        return self::$settings[$unit] ?? [];
    }

    public static function sidebar(int $slot, string $label = ''): void
    {
        if ($slot < 1 || $slot > 9) return;
        $unit = self::current();
        if ($unit === '') return;
        self::$sidebars[$unit][$slot] = $label !== '' ? $label : ('sidebar' . $slot);
    }

    public static function sidebars(?string $unit = null): array
    {
        if ($unit === null) return self::$sidebars;
        return self::$sidebars[$unit] ?? [];
    }

    public static function table(string $tableKey, array $def): void
    {
        if (class_exists('PluginTable')) PluginTable::addColumn($tableKey, $def);
    }

    public static function field(string $formKey, array $def): void
    {
        if (class_exists('PluginForm')) PluginForm::addField($formKey, $def);
    }

    public static function stats(): array
    {
        return [
            'loaded'   => array_keys(self::$loaded),
            'menus'    => count(self::$menus),
            'settings' => count(self::$settings),
            'sidebars' => count(self::$sidebars),
        ];
    }

    public static function reset(): void
    {
        self::$kind = '';
        self::$id = '';
        self::$stack = [];
        self::$loaded = [];
        self::$menus = [];
        self::$settings = [];
        self::$sidebars = [];
    }
}
