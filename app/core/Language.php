<?php

class Language
{

    private static $lang = 'zh-cn';

    private static $translations = array();

    private static $theme = 'default';

    private static $defaultTheme = 'default';

    private static $loaded = false;

    private static $pluginIds = array();

    public static function init(string $theme): void
    {
        self::$theme = $theme ?: self::$defaultTheme;
        self::$loaded = false;
        self::ensureLoaded();
    }

    public static function setTheme(string $theme): void
    {
        if ($theme && $theme !== self::$theme) {
            self::$theme = $theme;
            self::$loaded = false;
        }
    }

    public static function setLang(string $code): void
    {
        if (self::isLangAvailable($code)) {
            self::$lang = $code;
            self::$loaded = false;
        }
    }

    public static function reload(): void
    {
        self::$loaded = false;
        self::ensureLoaded();
    }

    private static function ensureLoaded(): void
    {
        if (!self::$loaded) {
            self::loadLanguage();
            self::$loaded = true;
        }
    }

    private static function loadLanguage(): void
    {

        $userLang = $_SESSION['lang'] ?? $_COOKIE['lang'] ?? null;
        if ($userLang && self::isLangAvailable($userLang)) {
            self::$lang = $userLang;
        } else {
            $defaultLang = 'zh-cn';
            try {
                $saved = SettingModel::get('site_lang', 'zh-cn');
                if (self::isLangAvailable($saved)) $defaultLang = $saved;
            } catch (\Throwable $e) {}
            self::$lang = $defaultLang;
        }
        self::doLoad();
    }

    private static function doLoad(): void
    {
        self::$translations = self::themeTranslations();
        $pluginTrans = self::loadPluginTranslations();
        self::$translations = array_merge($pluginTrans, self::$translations);
    }

    private static function themeTranslations(): array
    {
        $base = array();
        if (self::$lang !== 'zh-cn') {
            $base = self::readThemeFile(self::$defaultTheme, 'zh-cn');
        }
        $defaultTrans = self::readThemeFile(self::$defaultTheme, self::$lang);
        $currentTrans = self::readThemeFile(self::$theme, self::$lang);

        return array_merge($base, $defaultTrans, $currentTrans);
    }

    private static function readThemeFile(string $theme, string $code): array
    {
        $file = ROOT_PATH . 'templates/' . $theme . '/lang/' . $code . '.php';
        if (is_file($file)) {
            $result = include $file;
            if (is_array($result)) return $result;
        }
        return array();
    }

    private static function loadPluginTranslations(): array
    {
        $out = array();
        foreach (self::$pluginIds as $pid) {
            $dir = ROOT_PATH . 'plugins/' . $pid . '/lang/';
            if (!is_dir($dir)) continue;
            $file = $dir . self::$lang . '.php';
            if (!is_file($file) && self::$lang !== 'zh-cn') {
                $file = $dir . 'zh-cn.php';
            }
            if (!is_file($file)) continue;
            $trans = include $file;
            if (!is_array($trans)) continue;
            foreach ($trans as $k => $v) {
                if (is_string($k) && is_string($v) && !isset($out[$k])) {
                    $out[$k] = $v;
                }
            }
        }
        return $out;
    }

    public static function registerPlugin(string $id): void
    {
        $id = preg_replace('/[^a-z0-9_\-]/i', '', $id);
        if ($id === '' || in_array($id, self::$pluginIds, true)) return;
        self::$pluginIds[] = $id;
        if (self::$loaded) {
            $pluginTrans = self::loadPluginTranslations();
            self::$translations = array_merge($pluginTrans, self::$translations);
        }
    }

    public static function pluginGet(string $id, string $key, string $default = '', ...$args): string
    {
        self::ensureLoaded();
        $dir = ROOT_PATH . 'plugins/' . $id . '/lang/';
        $file = $dir . self::$lang . '.php';
        if (!is_file($file) && self::$lang !== 'zh-cn') {
            $file = $dir . 'zh-cn.php';
        }
        $trans = is_file($file) ? include $file : array();
        $text = (is_array($trans) && isset($trans[$key])) ? $trans[$key] : ($default !== '' ? $default : $key);
        if ($args) {
            $text = vsprintf($text, $args);
        }
        return $text;
    }

    public static function get(string $key, string $default = '', ...$args): string
    {
        self::ensureLoaded();
        $text = self::$translations[$key] ?? ($default !== '' ? $default : $key);
        if ($args) {
            $text = vsprintf($text, $args);
        }
        return $text;
    }

    public static function current(): string
    {
        self::ensureLoaded();
        return self::$lang;
    }

    public static function jsStrings(): array
    {
        $keys = array(
            'ui_ok', 'ui_cancel', 'ui_close', 'ui_remove', 'ui_menu', 'ui_language', 'ui_mobile_nav',
            'ui_tip', 'ui_confirm', 'ui_form', 'ui_submit', 'ui_captcha_ph', 'ui_captcha', 'ui_refresh',
            'ui_field_required', 'ui_number_invalid', 'ui_min_prefix', 'ui_max_prefix',            'confirm_delete', 'confirm_remove',
            'add_to_bookshelf', 'already_in_bookshelf', 'bookshelf_added', 'bookshelf_exists',
            'purchase_chapter', 'purchase_confirm', 'confirm_purchase', 'purchase_success', 'purchase_failed', 'purchase_tip',
            'request_failed', 'error_prefix', 'network_error', 'search_keyword_required',
            'submit_success', 'submit_failed',
            'fill_email_first', 'captcha_error', 'sending', 'send_success', 'send_fail', 'seconds_retry',
            'tp_title', 'tp_search_ph', 'tp_placeholder', 'tp_clear', 'tp_prev', 'tp_next',
            'tp_selected_fmt', 'tp_max_fmt', 'tp_no_match', 'tp_limit_fmt', 'tp_unlimited',
            'expand_tags', 'collapse_tags',
        );
        $out = array();
        foreach ($keys as $key) {
            $out[$key] = self::get($key, $key);
        }
        return $out;
    }

    public static function getAllLanguages(): array
    {
        $available = array();
        $meta = LanguageMetaModel::allNames();
        $dirs = array(ROOT_PATH . 'templates/' . self::$theme . '/lang/');
        if (self::$theme !== self::$defaultTheme) {
            $dirs[] = ROOT_PATH . 'templates/' . self::$defaultTheme . '/lang/';
        }
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) continue;
            foreach (glob($dir . '*.php') as $file) {
                $code = basename($file, '.php');
                if (!preg_match('/^[a-z]{2}-[a-z]{2}$/', $code)) continue;
                if (!isset($available[$code])) {
                    $available[$code] = ($meta[$code] ?? '') ?: strtoupper($code);
                }
            }
        }
        return $available;
    }

    public static function isLangAvailable(string $code): bool
    {
        if (!preg_match('/^[a-z]{2}-[a-z]{2}$/', $code)) return false;
        if (is_file(ROOT_PATH . 'templates/' . self::$theme . '/lang/' . $code . '.php')) return true;
        if (is_file(ROOT_PATH . 'templates/' . self::$defaultTheme . '/lang/' . $code . '.php')) return true;
        return false;
    }

    public static function themeLanguages(string $theme): array
    {
        $theme = preg_replace('/[^a-z0-9_\-]/', '', $theme);
        if ($theme === '') return array();
        return self::scanLangDir(ROOT_PATH . 'templates/' . $theme . '/lang/', 'theme', $theme);
    }

    public static function pluginLanguages(string $id): array
    {
        $id = preg_replace('/[^a-z0-9_\-]/', '', $id);
        if ($id === '') return array();
        return self::scanLangDir(ROOT_PATH . 'plugins/' . $id . '/lang/', 'plugin', $id);
    }

    private static function scanLangDir(string $dir, string $scope = '', string $owner = ''): array
    {
        $out = array();
        if (!is_dir($dir)) return $out;
        $meta = $scope !== '' ? LanguageMetaModel::nameMap($scope, $owner) : LanguageMetaModel::allNames();
        foreach (glob($dir . '*.php') as $file) {
            $code = basename($file, '.php');
            if (!preg_match('/^[a-z]{2}-[a-z]{2}$/', $code)) continue;
            $out[$code] = ($meta[$code] ?? '') ?: strtoupper($code);
        }
        return $out;
    }

    public static function installThemePackage(string $theme, array $file, string $code, string $name = ''): array
    {
        $theme = preg_replace('/[^a-z0-9_\-]/', '', $theme);
        if ($theme === '') {
            return array('success' => false, 'message' => t_admin('adm_lang_pkg_code_invalid', '语言代码格式无效（应为如 zh-cn）'));
        }
        $res = self::installPackageTo(ROOT_PATH . 'templates/' . $theme . '/lang/', $file, $code);
        if ($res['success']) {
            LanguageMetaModel::record('theme', $theme, $code, $name, 'templates/' . $theme . '/lang/' . $code . '.php');
        }
        return $res;
    }

    public static function uninstallThemePackage(string $theme, string $code): array
    {
        $theme = preg_replace('/[^a-z0-9_\-]/', '', $theme);
        if (!preg_match('/^[a-z]{2}-[a-z]{2}$/', $code)) {
            return array('success' => false, 'message' => t_admin('adm_lang_pkg_code_invalid_short', '语言代码格式无效'));
        }
        $default = SettingModel::get('site_lang', 'zh-cn');
        if ($code === $default || $code === 'zh-cn') {
            return array('success' => false, 'message' => t_admin('adm_lang_pkg_default_keep', '默认语言不可删除'));
        }
        $filePath = ROOT_PATH . 'templates/' . $theme . '/lang/' . $code . '.php';
        if (is_file($filePath)) { @unlink($filePath); }
        LanguageMetaModel::remove('theme', $theme, $code);
        return array('success' => true, 'message' => t_admin('adm_lang_pkg_deleted', '语言包已删除：%s', $code));
    }

    public static function installPluginPackage(string $id, array $file, string $code, string $name = ''): array
    {
        $id = preg_replace('/[^a-z0-9_\-]/', '', $id);
        if ($id === '') {
            return array('success' => false, 'message' => t_admin('adm_lang_pkg_code_invalid', '语言代码格式无效（应为如 zh-cn）'));
        }
        $res = self::installPackageTo(ROOT_PATH . 'plugins/' . $id . '/lang/', $file, $code);
        if ($res['success']) {
            LanguageMetaModel::record('plugin', $id, $code, $name, 'plugins/' . $id . '/lang/' . $code . '.php');
        }
        return $res;
    }

    public static function uninstallPluginPackage(string $id, string $code): array
    {
        $id = preg_replace('/[^a-z0-9_\-]/', '', $id);
        if (!preg_match('/^[a-z]{2}-[a-z]{2}$/', $code)) {
            return array('success' => false, 'message' => t_admin('adm_lang_pkg_code_invalid_short', '语言代码格式无效'));
        }
        if ($code === 'zh-cn') {
            return array('success' => false, 'message' => t_admin('adm_lang_pkg_default_keep', '默认语言不可删除'));
        }
        $filePath = ROOT_PATH . 'plugins/' . $id . '/lang/' . $code . '.php';
        if (is_file($filePath)) { @unlink($filePath); }
        LanguageMetaModel::remove('plugin', $id, $code);
        return array('success' => true, 'message' => t_admin('adm_lang_pkg_deleted', '语言包已删除：%s', $code));
    }

    private static function installPackageTo(string $dir, array $file, string $code): array
    {
        if (!preg_match('/^[a-z]{2}-[a-z]{2}$/', $code)) {
            return array('success' => false, 'message' => t_admin('adm_lang_pkg_code_invalid', '语言代码格式无效（应为如 zh-cn）'));
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return array('success' => false, 'message' => t_admin('adm_lang_pkg_upload_fail', '语言文件上传失败'));
        }
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if ($ext !== 'php') {
            return array('success' => false, 'message' => t_admin('adm_lang_pkg_ext_only_php', '仅允许上传 .php 语言文件'));
        }
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true)) {
                return array('success' => false, 'message' => t_admin('adm_lang_pkg_dir_fail', '语言目录创建失败'));
            }
        }
        $content = @file_get_contents($file['tmp_name'] ?? '');
        if ($content === false || strpos($content, '<?php') === false) {
            return array('success' => false, 'message' => t_admin('adm_lang_pkg_format_invalid', '语言文件格式无效'));
        }
        $guard = Security::validateLanguagePack($content);
        if (!$guard['ok']) {
            error_log('[lang pack] rejected: ' . $guard['reason']);
            return array('success' => false, 'message' => t_admin('adm_lang_pkg_unsafe', '语言包含禁止的代码结构（变量/函数/标签等），已拒绝安装'));
        }
        $tmpFile = $dir . '.tmp_' . bin2hex(random_bytes(6)) . '.php';
        if (!move_uploaded_file($file['tmp_name'] ?? '', $tmpFile)) {
            return array('success' => false, 'message' => t_admin('adm_lang_pkg_write_fail', '语言文件写入失败'));
        }
        $trans = include $tmpFile;
        $valid = is_array($trans);
        if ($valid) {
            foreach ($trans as $k => $v) {
                if (!is_string($k) || !is_string($v)) { $valid = false; break; }
            }
        }
        if (!$valid) {
            @unlink($tmpFile);
            return array('success' => false, 'message' => t_admin('adm_lang_pkg_validate_fail', '语言包校验失败：必须返回键值均为字符串的翻译数组，已拒绝安装'));
        }
        $targetFile = $dir . $code . '.php';
        if (!@rename($tmpFile, $targetFile)) {
            @unlink($tmpFile);
            return array('success' => false, 'message' => t_admin('adm_lang_pkg_write_fail', '语言文件写入失败'));
        }
        return array('success' => true, 'message' => t_admin('adm_lang_pkg_installed', '语言包已安装：%s', $code));
    }
}
