<?php

class PluginForm
{

    private static $fields = [];

    private static $db = null;

    public static function setDb($db): void
    {
        self::$db = $db;
    }

    public static function addField(string $formKey, array $field): void
    {
        if (empty($field['name']) || empty($field['label'])) return;
        $field['name'] = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$field['name']);
        $field['type'] = isset($field['type']) ? $field['type'] : 'text';
        $field['options'] = isset($field['options']) && is_array($field['options']) ? $field['options'] : [];
        $field['default'] = isset($field['default']) ? $field['default'] : '';
        $field['plugin_id'] = Plugin::currentId();
        self::$fields[$formKey][] = $field;
    }

    public static function fields(string $formKey): array
    {
        return self::$fields[$formKey] ?? [];
    }

    public static function values(string $formKey, string $entityType, int $entityId): array
    {
        $out = [];
        foreach (self::fields($formKey) as $f) {
            $row = self::findRow($f['plugin_id'], $entityType, $entityId, $f['name']);
            $out[$f['name']] = $row !== null ? $row['data_value'] : $f['default'];
        }
        return $out;
    }

    public static function render(string $formKey, string $entityType, int $entityId): string
    {
        $fields = self::fields($formKey);
        if (!$fields || !self::$db) return '';
        $values = self::values($formKey, $entityType, $entityId);
        ob_start();
        echo '<div class="plugin-ext-fields">';
        echo '<h3 class="plugin-ext-title"><i class="fas fa-puzzle-piece"></i> ' . t_admin('adm_plugin_ext_fields_title', '扩展字段（插件提供）') . '</h3>';
        foreach ($fields as $f) {
            $name = htmlspecialchars($f['name']);
            $label = htmlspecialchars($f['label']);
            $v = $values[$f['name']] ?? $f['default'];
            echo '<div class="form-group"><label>' . $label . '</label>';
            switch ($f['type']) {
                case 'textarea':
                    echo '<textarea name="ext[' . $name . ']" class="form-control" rows="3">' . htmlspecialchars((string)$v) . '</textarea>';
                    break;
                case 'number':
                    echo '<input type="number" name="ext[' . $name . ']" class="form-control" value="' . htmlspecialchars((string)$v) . '">';
                    break;
                case 'select':
                    echo '<select name="ext[' . $name . ']" class="form-control">';
                    foreach ($f['options'] as $ov => $ol) {
                        $sel = ((string)$ov === (string)$v) ? ' selected' : '';
                        echo '<option value="' . htmlspecialchars((string)$ov) . '"' . $sel . '>' . htmlspecialchars((string)$ol) . '</option>';
                    }
                    echo '</select>';
                    break;
                case 'switch':
                    $checked = !empty($v) ? ' checked' : '';
                    echo '<label class="inline-check"><input type="checkbox" name="ext[' . $name . ']" value="1"' . $checked . '><span>' . t_admin('adm_on', '开启') . '</span></label>';
                    break;
                case 'checkbox':
                    $checked = !empty($v) ? ' checked' : '';
                    echo '<label class="inline-check"><input type="checkbox" name="ext[' . $name . ']" value="1"' . $checked . '><span>' . $label . '</span></label>';
                    break;
                case 'radio':
                    echo '<div class="inline-checks">';
                    foreach ($f['options'] as $ov => $ol) {
                        $checked = ((string)$ov === (string)$v) ? ' checked' : '';
                        echo '<label class="inline-check"><input type="radio" name="ext[' . $name . ']" value="' . htmlspecialchars((string)$ov) . '"' . $checked . '><span>' . htmlspecialchars((string)$ol) . '</span></label>';
                    }
                    echo '</div>';
                    break;
                case 'checkbox_group':
                    $arr = is_array($v) ? $v : (is_string($v) && $v !== '' ? (array)json_decode($v, true) : array());
                    $arr = array_map('strval', array_filter($arr, 'is_scalar'));
                    echo '<div class="inline-checks">';
                    foreach ($f['options'] as $ov => $ol) {
                        $checked = in_array((string)$ov, $arr, true) ? ' checked' : '';
                        echo '<label class="inline-check"><input type="checkbox" name="ext[' . $name . '][]" value="' . htmlspecialchars((string)$ov) . '"' . $checked . '><span>' . htmlspecialchars((string)$ol) . '</span></label>';
                    }
                    echo '</div>';
                    break;
                case 'multiselect':
                    $arr = is_array($v) ? $v : (is_string($v) && $v !== '' ? (array)json_decode($v, true) : array());
                    $arr = array_map('strval', array_filter($arr, 'is_scalar'));
                    echo '<select name="ext[' . $name . '][]" class="form-control" multiple size="' . max(3, min(8, count($f['options']))) . '">';
                    foreach ($f['options'] as $ov => $ol) {
                        $sel = in_array((string)$ov, $arr, true) ? ' selected' : '';
                        echo '<option value="' . htmlspecialchars((string)$ov) . '"' . $sel . '>' . htmlspecialchars((string)$ol) . '</option>';
                    }
                    echo '</select>';
                    break;
                case 'date':
                    echo '<input type="date" name="ext[' . $name . ']" class="form-control" value="' . htmlspecialchars((string)$v) . '">';
                    break;
                case 'hidden':
                    echo '<input type="hidden" name="ext[' . $name . ']" value="' . htmlspecialchars((string)$v) . '">';
                    break;
                default:
                    echo '<input type="text" name="ext[' . $name . ']" class="form-control" value="' . htmlspecialchars((string)$v) . '">';
            }
            if (!empty($f['hint'])) {
                echo '<p style="font-size:12px;color:#8a8378;margin:4px 0 0">' . htmlspecialchars((string)$f['hint']) . '</p>';
            }
            echo '</div>';
        }
        echo '</div>';
        return ob_get_clean();
    }

    public static function save(string $formKey, string $entityType, int $entityId): int
    {
        $fields = self::fields($formKey);
        if (!$fields || !self::$db) return 0;
        $post = p('ext', null, 'raw') !== null && is_array($_POST['ext']) ? $_POST['ext'] : [];
        $count = 0;
        foreach ($fields as $f) {
            $name = $f['name'];
            if ($f['type'] === 'switch' || $f['type'] === 'checkbox') {

                $val = !empty($post[$name]) ? '1' : '0';
            } elseif ($f['type'] === 'checkbox_group' || $f['type'] === 'multiselect') {
                $raw = $post[$name] ?? array();
                $raw = is_array($raw) ? $raw : array();
                $arr = [];
                foreach ($raw as $x) {
                    if (is_scalar($x)) $arr[] = (string)$x;
                }
                $val = json_encode($arr, JSON_UNESCAPED_UNICODE);
            } elseif (!array_key_exists($name, $post)) {
                continue;
            } else {
                $raw = $post[$name];
                $val = is_string($raw) ? trim($raw) : (string)$raw;
                if ($f['type'] === 'number') $val = (string)(float)$val;
            }
            self::upsert($f['plugin_id'], $entityType, $entityId, $name, (string)$val);
            $count++;
        }

        Hook::doAction('form_saved_' . $formKey, $entityType, $entityId, $post);
        return $count;
    }

    private static function findRow(string $pluginId, string $entityType, int $entityId, string $key): ?array
    {
        return self::$db->find('plugin_data', [
            'plugin_id' => $pluginId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'data_key' => $key,
        ]);
    }

    private static function upsert(string $pluginId, string $entityType, int $entityId, string $key, string $val): void
    {
        $row = self::findRow($pluginId, $entityType, $entityId, $key);
        if ($row) {
            self::$db->update('plugin_data', ['data_value' => $val], ['id' => $row['id']]);
        } else {
            self::$db->insert('plugin_data', [
                'plugin_id' => $pluginId,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'data_key' => $key,
                'data_value' => $val,
            ]);
        }
    }
}
