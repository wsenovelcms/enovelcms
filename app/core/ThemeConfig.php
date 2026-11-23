<?php

class ThemeConfig
{
    private static $fields = [];
    private static $db = null;
    private static $currentTheme = '';

    public static function setDb($db): void
    {
        self::$db = $db;
    }

    public static function setCurrent(string $themeId): void
    {
        self::$currentTheme = $themeId;
    }

    public static function add(array $def): void
    {
        $tid = self::$currentTheme;
        if ($tid === '' || empty($def['name'])) return;
        self::$fields[$tid][] = $def;
    }

    public static function fields(string $themeId): array
    {
        return self::$fields[$themeId] ?? [];
    }

    public static function render(string $themeId): string
    {
        $defs = self::fields($themeId);
        if (!$defs) return '';
        ob_start();
        echo '<div class="theme-ext-fields"><h2 style="margin-top:0">' . t_admin('adm_theme_setting_title', '主题设置') . '</h2>';
        foreach ($defs as $def) {
            $name = htmlspecialchars($def['name']);
            $label = htmlspecialchars($def['label'] ?? $name);
            $val = Theme::config($def['name'], $def['default'] ?? '', $themeId);
            echo '<div class="form-group"><label>' . $label . '</label>';
            $type = $def['type'] ?? 'text';
            if ($type === 'switch') {
                $checked = $val ? 'checked' : '';
                echo '<label class="switch"><input type="checkbox" name="tcfg[' . $name . ']" value="1" ' . $checked . '> ' . t_admin('adm_on', '开启') . '</label>';
            } elseif ($type === 'select' && !empty($def['options'])) {
                echo '<select class="form-control" name="tcfg[' . $name . ']">';
                foreach ($def['options'] as $ov => $ol) {
                    $sel = ((string)$ov === (string)$val) ? 'selected' : '';
                    echo '<option value="' . htmlspecialchars($ov) . '" ' . $sel . '>' . htmlspecialchars($ol) . '</option>';
                }
                echo '</select>';
            } elseif ($type === 'textarea') {
                echo '<textarea class="form-control" name="tcfg[' . $name . ']" rows="3">' . htmlspecialchars((string)$val) . '</textarea>';
            } elseif ($type === 'password') {
                echo '<input class="form-control" type="password" name="tcfg[' . $name . ']" value="' . htmlspecialchars((string)$val) . '">';
            } else {
                echo '<input class="form-control" type="' . ($type === 'number' ? 'number' : 'text') . '" name="tcfg[' . $name . ']" value="' . htmlspecialchars((string)$val) . '">';
            }
            echo '</div>';
        }
        echo '<button class="btn btn-green" type="submit"><i class="fas fa-save"></i> ' . t_admin('adm_url_save', '保存设置') . '</button>';
        echo '</div>';
        return ob_get_clean();
    }

    public static function save(string $themeId): void
    {

        if (!isset($_POST['tcfg']) || !is_array($_POST['tcfg'])) return;
        $defs = self::fields($themeId);
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
            Theme::setConfig($k, $v, $themeId);
        }
    }
}
