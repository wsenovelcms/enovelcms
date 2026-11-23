<?php

class PluginConfig
{
    private static $fields = [];
    private static $db = null;

    public static function setDb($db): void
    {
        self::$db = $db;
    }

    public static function add(array $def): void
    {
        $pid = Plugin::currentId();
        if ($pid === '' || empty($def['name'])) return;
        self::$fields[$pid][] = $def;
    }

    public static function fields(string $pid): array
    {
        return self::$fields[$pid] ?? [];
    }

    public static function render(string $pid): string
    {
        $defs = self::fields($pid);
        if (!$defs) return '';
        ob_start();
        echo '<div class="plugin-ext-fields"><h2 style="margin-top:0">' . t_admin('adm_plugin_setting_title', '插件设置') . '</h2>';
        foreach ($defs as $def) {
            $name = htmlspecialchars($def['name']);
            $label = htmlspecialchars($def['label'] ?? $name);
            $val = Plugin::config($def['name'], $def['default'] ?? '');
            echo '<div class="form-group"><label>' . $label . '</label>';
            $type = $def['type'] ?? 'text';
            if ($type === 'switch') {
                $checked = $val ? 'checked' : '';
                echo '<label class="switch"><input type="checkbox" name="pcfg[' . $name . ']" value="1" ' . $checked . '> ' . t_admin('adm_on', '开启') . '</label>';
            } elseif ($type === 'select' && !empty($def['options'])) {
                echo '<select class="form-control" name="pcfg[' . $name . ']">';
                foreach ($def['options'] as $ov => $ol) {
                    $sel = ((string)$ov === (string)$val) ? 'selected' : '';
                    echo '<option value="' . htmlspecialchars($ov) . '" ' . $sel . '>' . htmlspecialchars($ol) . '</option>';
                }
                echo '</select>';
            } elseif ($type === 'textarea') {
                echo '<textarea class="form-control" name="pcfg[' . $name . ']" rows="3">' . htmlspecialchars((string)$val) . '</textarea>';
            } else {
                echo '<input class="form-control" type="' . ($type === 'number' ? 'number' : 'text') . '" name="pcfg[' . $name . ']" value="' . htmlspecialchars((string)$val) . '">';
            }
            echo '</div>';
        }
        echo '<button class="btn btn-green" type="submit"><i class="fas fa-save"></i> ' . t_admin('adm_url_save', '保存设置') . '</button>';
        echo '</div>';
        return ob_get_clean();
    }

    public static function save(string $pid): void
    {

        if (!isset($_POST['pcfg']) || !is_array($_POST['pcfg'])) return;
        $defs = self::fields($pid);
        $known = [];
        foreach ($defs as $def) {
            $known[$def['name']] = $def;
        }

        foreach ($defs as $def) {
            if (($def['type'] ?? 'text') === 'switch' && !isset($_POST['pcfg'][$def['name']])) {
                $_POST['pcfg'][$def['name']] = 0;
            }
        }
        foreach ($_POST['pcfg'] as $k => $v) {
            if (!isset($known[$k])) continue;
            $def = $known[$k];
            $type = $def['type'] ?? 'text';
            if ($type === 'number') $v = (float)$v;
            elseif ($type === 'switch') $v = $v ? 1 : 0;
            else $v = trim((string)$v);
            Plugin::setConfig($k, $v, $pid);
        }
    }
}
