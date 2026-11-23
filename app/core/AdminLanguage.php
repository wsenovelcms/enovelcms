<?php

class AdminLanguage
{
    private static string $lang = 'zh-cn';

    private static array $translations = array();

    private static bool $loaded = false;

    public static function init(): void
    {
        self::ensureLoaded();
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
            'adm_ui_ok', 'adm_ui_cancel', 'adm_ui_close', 'adm_ui_tip', 'adm_ui_confirm',
            'adm_ui_form', 'adm_ui_submit',
            'adm_ui_captcha', 'adm_ui_captcha_ph', 'adm_ui_refresh',
            'adm_ui_required', 'adm_ui_number', 'adm_ui_min', 'adm_ui_max', 'adm_ui_remove',
            'adm_tp_title', 'adm_tp_search_ph', 'adm_tp_placeholder', 'adm_tp_clear', 'adm_tp_prev', 'adm_tp_next',
            'adm_tp_selected_fmt', 'adm_tp_max_fmt', 'adm_tp_no_match', 'adm_tp_limit_fmt', 'adm_tp_unlimited',
        );
        $out = array();
        foreach ($keys as $key) {
            $out[substr($key, 4)] = self::get($key, $key);
        }
        return $out;
    }

    public static function getAll(): array
    {
        return self::listPackages();
    }

    public static function listPackages(): array
    {
        $out = array();
        $meta = LanguageMetaModel::nameMap('admin', '');
        $dir = ROOT_PATH . 'admin/lang/';
        if (is_dir($dir)) {
            foreach (glob($dir . '*.php') as $file) {
                $code = basename($file, '.php');
                if (!preg_match('/^[a-z]{2}-[a-z]{2}$/', $code)) continue;
                $out[$code] = ($meta[$code] ?? '') ?: strtoupper($code);
            }
        }
        return $out;
    }

    public static function installPackage(array $file, string $code, string $name = ''): array
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
        $targetDir = ROOT_PATH . 'admin/lang/';
        if (!is_dir($targetDir)) {
            if (!@mkdir($targetDir, 0755, true)) {
                return array('success' => false, 'message' => t_admin('adm_lang_pkg_dir_fail', '语言目录创建失败'));
            }
        }
        $content = @file_get_contents($file['tmp_name'] ?? '');
        if ($content === false || strpos($content, '<?php') === false) {
            return array('success' => false, 'message' => t_admin('adm_lang_pkg_format_invalid', '语言文件格式无效'));
        }
        $guard = Security::validateLanguagePack($content);
        if (!$guard['ok']) {
            error_log('[lang pack admin] rejected: ' . $guard['reason']);
            return array('success' => false, 'message' => t_admin('adm_lang_pkg_unsafe', '语言包含禁止的代码结构（变量/函数/标签等），已拒绝安装'));
        }
        $tmpFile = $targetDir . '.tmp_' . bin2hex(random_bytes(6)) . '.php';
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
        $targetFile = $targetDir . $code . '.php';
        if (!@rename($tmpFile, $targetFile)) {
            @unlink($tmpFile);
            return array('success' => false, 'message' => t_admin('adm_lang_pkg_write_fail', '语言文件写入失败'));
        }
        LanguageMetaModel::record('admin', '', $code, $name, 'admin/lang/' . $code . '.php');
        return array('success' => true, 'message' => t_admin('adm_lang_pkg_installed', '语言包已安装：%s', $code));
    }

    public static function uninstallPackage(string $code): array
    {
        if (!preg_match('/^[a-z]{2}-[a-z]{2}$/', $code)) {
            return array('success' => false, 'message' => t_admin('adm_lang_pkg_code_invalid_short', '语言代码格式无效'));
        }
        $default = SettingModel::get('admin_lang', 'zh-cn');
        if ($code === $default || $code === 'zh-cn') {
            return array('success' => false, 'message' => t_admin('adm_lang_pkg_default_keep', '默认语言不可删除'));
        }
        $file = ROOT_PATH . 'admin/lang/' . $code . '.php';
        if (is_file($file)) { @unlink($file); }
        LanguageMetaModel::remove('admin', '', $code);
        return array('success' => true, 'message' => t_admin('adm_lang_pkg_deleted', '语言包已删除：%s', $code));
    }

    public static function isAvailable(string $code): bool
    {
        if (!preg_match('/^[a-z]{2}-[a-z]{2}$/', $code)) return false;
        return is_file(ROOT_PATH . 'admin/lang/' . $code . '.php');
    }

    public static function setLang(string $code): bool
    {
        if (!self::isAvailable($code)) return false;
        self::$lang = $code;
        self::$loaded = false;
        $_SESSION['admin_lang'] = $code;
        return true;
    }

    private static function ensureLoaded(): void
    {
        if (!self::$loaded) {
            self::load();
            self::$loaded = true;
        }
    }

    private static function load(): void
    {
        $sessionLang = (string)($_SESSION['admin_lang'] ?? '');
        if ($sessionLang !== '' && self::isAvailable($sessionLang)) {
            self::$lang = $sessionLang;
        } else {
            $default = (string)SettingModel::get('admin_lang', 'zh-cn');
            self::$lang = self::isAvailable($default) ? $default : 'zh-cn';
        }
        self::$translations = self::loadFile(self::$lang);
        if (self::$lang !== 'zh-cn') {
            self::$translations = array_merge(self::loadFile('zh-cn'), self::$translations);
        }
    }

    private static function loadFile(string $code): array
    {
        $file = ROOT_PATH . 'admin/lang/' . $code . '.php';
        if (is_file($file)) {
            $trans = include $file;
            if (is_array($trans)) return $trans;
        }
        return array();
    }
}
