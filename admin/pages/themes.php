<?php

if (!defined('ADMIN_BOOT')) exit;
$pageTitle = t_admin('adm_theme', '主题管理');

Theme::setDb($db);
Theme::init($db);

$themeService = new ThemeService($db);
$do = (string)(g('do', '', 'str'));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['theme_zip']) && $_FILES['theme_zip']['error'] === UPLOAD_ERR_OK) {
    if (Security::verifyCsrf()) {
        if (!Security::verifyZipUpload($_FILES['theme_zip'])) {
            admin_flash_redirect(t_admin('adm_theme_zip_bad', '只允许上传合法的 ZIP 文件'), 'themes');
        }
        $tempZip = ROOT_PATH . 'data/temp/' . uniqid() . '.zip';
        if (!is_dir(dirname($tempZip))) { @mkdir(dirname($tempZip), 0755, true); }
        if (move_uploaded_file($_FILES['theme_zip']['tmp_name'], $tempZip)) {
            $overwrite = p('overwrite', null, 'raw') !== null && $_POST['overwrite'] === '1';
            $res = $themeService->installFromZip($tempZip, $overwrite);
            @unlink($tempZip);
            admin_flash_redirect($res['msg'], 'themes');
        }
    }
}

if ($do === 'install') {
    if (Security::verifyCsrf()) {
        $id = (string)(g('id', '', 'str'));
        if (!Theme::validThemeId($id)) admin_flash_redirect(t_admin('adm_theme_bad_id', '主题标识不合法或安全验证失败'), 'themes');
        $res = Theme::install($id, $db);
        admin_flash_redirect($res['msg'], 'themes');
    }
}
if ($do === 'enable_pc') {
    if (Security::verifyCsrf()) {
        $id = (string)(g('id', '', 'str'));
        if (!Theme::validThemeId($id)) admin_flash_redirect(t_admin('adm_theme_bad_id', '主题标识不合法或安全验证失败'), 'themes');
        $res = Theme::enable($id, 'pc', $db);
        admin_flash_redirect($res['msg'], 'themes');
    }
}
if ($do === 'enable_mobile') {
    if (Security::verifyCsrf()) {
        $id = (string)(g('id', '', 'str'));
        if (!Theme::validThemeId($id)) admin_flash_redirect(t_admin('adm_theme_bad_id', '主题标识不合法或安全验证失败'), 'themes');
        $res = Theme::enable($id, 'mobile', $db);
        admin_flash_redirect($res['msg'], 'themes');
    }
}
if ($do === 'disable_mobile') {
    if (Security::verifyCsrf()) {
        $res = Theme::disableMobile($db);
        admin_flash_redirect($res['msg'], 'themes');
    }
}
if ($do === 'uninstall') {
    if (Security::verifyCsrf()) {
        $id = (string)(g('id', '', 'str'));
        if (!Theme::validThemeId($id)) admin_flash_redirect(t_admin('adm_theme_bad_id', '主题标识不合法或安全验证失败'), 'themes');
        $res = Theme::uninstall($id, $db);
        admin_flash_redirect($res['msg'], 'themes');
    }
}
if ($do === 'delete') {
    if (Security::verifyCsrf()) {
        $id = (string)(g('id', '', 'str'));
        if (!Theme::validThemeId($id)) admin_flash_redirect(t_admin('adm_theme_bad_id', '主题标识不合法或安全验证失败'), 'themes');
        $res = Theme::delete($id, $db);
        admin_flash_redirect($res['msg'], 'themes');
    }
}
if ($do === 'save_config' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (string)(g('id', '', 'str'));
    if (Security::verifyCsrf() && Theme::validThemeId($id)) {
        $res = Theme::saveConfig($id);
        admin_flash_redirect($res['msg'], 'themes');
    }
    admin_flash_redirect(t_admin('adm_theme_bad_id', '主题标识不合法或安全验证失败'), 'themes');
}

$configId = (string)(g('config', '', 'str'));
$configFields = [];
$configTheme = null;
if ($configId !== '' && Theme::validThemeId($configId)) {
    $all = Theme::scanAll();
    $configTheme = $all[$configId] ?? null;
    if ($configTheme && $configTheme['has_config']) {
        $configFields = Theme::configFields($configId);
    }
}

$themes = Theme::scanAll();
$activePc = Theme::activePc();
$activeMobile = Theme::activeMobile();
?>

<?php if ($configTheme && $configFields): ?>
<div class="card">
    <a href="<?= htmlspecialchars(admin_url('themes')) ?>" class="text-muted" style="font-size:12.5px"><i class="fas fa-arrow-left"></i> <?= t_admin('adm_theme_back_list', '返回主题列表') ?></a>
    <h2><i class="fas fa-gear"></i> <?= t_admin('adm_theme_config_title', '主题配置：%s', htmlspecialchars((string)$configTheme['title'] ?? $configId)) ?></h2>
    <form method="post" action="<?= htmlspecialchars(admin_url('themes', ['do' => 'save_config', 'id' => $configId])) ?>">
        <?= Security::csrfField() ?>
        <?php foreach ($configFields as $fieldKey => $def):
            $name = isset($def['name']) ? (string)$def['name'] : (string)$fieldKey;
            $label = htmlspecialchars($def['label'] ?? $name);
            $nameEsc = htmlspecialchars($name);
            $val = Theme::config($name, $def['default'] ?? '', $configId);
            $type = $def['type'] ?? 'text';
        ?>
        <div class="form-group">
            <label><?= $label ?></label>
            <?php if ($type === 'switch' || $type === 'radio'):
                $checked = $val ? 'checked' : ''; ?>
                <label class="switch"><input type="checkbox" name="tcfg[<?= $nameEsc ?>]" value="1" <?= $checked ?>> <?= t_admin('adm_theme_on', '开启') ?></label>
            <?php elseif ($type === 'select' && !empty($def['options'])): ?>
                <select class="form-control" name="tcfg[<?= $nameEsc ?>]">
                    <?php foreach ($def['options'] as $ov => $ol):
                        $sel = ((string)$ov === (string)$val) ? 'selected' : ''; ?>
                        <option value="<?= htmlspecialchars($ov) ?>" <?= $sel ?>><?= htmlspecialchars($ol) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php elseif ($type === 'textarea'): ?>
                <textarea class="form-control" name="tcfg[<?= $nameEsc ?>]" rows="3"><?= htmlspecialchars((string)$val) ?></textarea>
            <?php else: ?>
                <input class="form-control" type="<?= $type === 'number' ? 'number' : 'text' ?>" name="tcfg[<?= $nameEsc ?>]" value="<?= htmlspecialchars((string)$val) ?>">
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <button class="btn btn-green" type="submit"><i class="fas fa-save"></i> <?= t_admin('adm_theme_save_config', '保存配置') ?></button>
    </form>
</div>
<?php else: ?>

<div class="card">
    <h2><i class="fas fa-upload"></i> <?= t_admin('adm_theme_upload', '上传主题（ZIP）') ?></h2>
    <form method="post" enctype="multipart/form-data" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
        <?= Security::csrfField() ?>
        <div class="form-group" style="margin:0">
            <label><?= t_admin('adm_theme_zip_label', '主题 ZIP 包') ?></label>
            <input type="file" name="theme_zip" accept=".zip" required>
        </div>
        <div class="form-group" style="margin:0">
            <label style="font-weight:400;display:inline-flex;gap:6px;align-items:center">
                <input type="checkbox" name="overwrite" value="1"> <?= t_admin('adm_theme_overwrite', '允许覆盖同名主题') ?>
            </label>
        </div>
        <button class="btn btn-primary"><i class="fas fa-upload"></i> <?= t_admin('adm_theme_upload_extract', '上传并解压') ?></button>
    </form>
</div>

<div class="card">
    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
        <span class="badge badge-green"><i class="fas fa-desktop"></i> <?= t_admin('adm_theme_current_pc', '当前PC：%s', htmlspecialchars($activePc)) ?></span>
        <span class="badge badge-blue"><i class="fas fa-mobile-screen"></i> <?= t_admin('adm_theme_current_mobile', '当前手机：%s', htmlspecialchars($activeMobile ?: t_admin('adm_theme_follow_pc', '跟随PC'))) ?></span>
        <?php if ($activeMobile): ?>
        <a href="<?= htmlspecialchars(admin_url('themes', ['do' => 'disable_mobile', '_csrf' => Security::csrfToken()])) ?>"
           class="text-muted" style="font-size:12px" data-confirm="<?= t_admin('adm_theme_disable_mobile_confirm', '停用手机主题？') ?>">
            <i class="fas fa-toggle-off"></i> <?= t_admin('adm_theme_disable_mobile', '停用手机主题') ?>
        </a>
        <?php endif; ?>
    </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px">
<?php foreach ($themes as $id => $t):
    $isInstalled = $t['installed'];
    $isPc = $t['active_pc'];
    $isMobile = $t['active_mobile'];
    $typeLabel = Theme::typeLabel((string)($t['type'] ?? 'pc'));
    $shotUrl = $t['screenshot'] ? ((strpos($t['screenshot'], 'http') === 0) ? $t['screenshot'] : BASE_URL . $t['screenshot']) : '';
?>
    <div class="card" style="margin-bottom:0;<?= ($isPc || $isMobile) ? 'border-color:var(--green);box-shadow:0 0 0 1px var(--green)' : '' ?>">
        <div style="position:relative;aspect-ratio:4/3;background:#f4f6fa;border-radius:8px;overflow:hidden;margin-bottom:10px;display:flex;align-items:center;justify-content:center">
            <?php if ($shotUrl): ?>
            <img src="<?= htmlspecialchars($shotUrl) ?>" alt="" style="width:100%;height:100%;object-fit:cover">
            <?php else: ?>
            <i class="fas fa-paint-roller" style="font-size:48px;color:#cbd5e1"></i>
            <?php endif; ?>
            <?php if ($isPc): ?><span class="badge badge-green" style="position:absolute;top:8px;left:8px"><i class="fas fa-desktop"></i> <?= t_admin('adm_theme_type_pc', 'PC') ?></span><?php endif; ?>
            <?php if ($isMobile): ?><span class="badge badge-blue" style="position:absolute;top:8px;left:<?= $isPc ? '60px' : '8px' ?>"><i class="fas fa-mobile-screen"></i> <?= t_admin('adm_theme_mobile', '手机') ?></span><?php endif; ?>
        </div>
        <h3 style="margin:0 0 4px;font-size:15px"><?= htmlspecialchars((string)$t['title'] ?? $id) ?></h3>
        <code style="font-size:11.5px;color:#64748b">/<?= htmlspecialchars($id) ?></code>
        <?php
        $metaParts = [$typeLabel, 'v' . htmlspecialchars((string)$t['version'] ?? '1.0.0')];
        $author = (string)($t['author'] ?? '');
        $website = (string)($t['website'] ?? '');
        if ($author !== '') {
            $authorHtml = htmlspecialchars($author);
            if ($website !== '') {
                $authorHtml = '<a href="' . htmlspecialchars($website) . '" target="_blank" rel="noopener" style="color:inherit;text-decoration:underline">' . $authorHtml . '</a>';
            }
            $metaParts[] = $authorHtml;
        }
        ?>
        <div class="text-muted" style="font-size:12.5px;margin:6px 0">
            <?= implode(' · ', $metaParts) ?>
        </div>
        <p style="font-size:13px;color:var(--text);margin-bottom:12px;line-height:1.5;min-height:38px"><?= htmlspecialchars((string)$t['description'] ?? '') ?></p>
        <?php
        $levelMap = ['official' => ['badge-green', t_admin('adm_theme_level_official', '官方')],
                     'free'     => ['badge-blue', t_admin('adm_theme_level_free', '免费')],
                     'paid'     => ['badge-orange', t_admin('adm_theme_level_paid', '付费')]];
        $level = (string)($t['level'] ?? 'free');
        $levelView = $levelMap[$level] ?? [$level === 'official' ? 'badge-green' : 'badge-blue', $level];
        ?>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;font-size:11.5px;margin-bottom:10px;line-height:1.9">
            <span class="badge <?= $levelView[0] ?>"><?= htmlspecialchars($levelView[1]) ?></span>
            <?php if (!empty($t['requires']['php']) || !empty($t['requires']['cms'])): ?>
            <span class="text-muted" title="<?= t_admin('adm_theme_requires_title', '运行要求') ?>"><i class="fas fa-microchip"></i> PHP <?= htmlspecialchars((string)($t['requires']['php'] ?? '')) ?> · CMS <?= htmlspecialchars((string)($t['requires']['cms'] ?? '')) ?></span>
            <?php endif; ?>
            <?php if (!empty($t['sidebars'])): ?>
            <span class="text-muted" title="<?= t_admin('adm_theme_sidebars_title', '可用侧栏槽位') ?>"><i class="fas fa-table-columns"></i> <?= t_admin('adm_theme_sidebars_count', '侧栏 %d 个', count($t['sidebars'])) ?></span>
            <?php endif; ?>
            <?php if (!empty($t['dependencies'])): ?>
            <span class="text-muted" title="<?= t_admin('adm_theme_deps_title', '依赖单元') ?>"><i class="fas fa-link"></i> <?= htmlspecialchars(implode(', ', array_map(function ($d) { return $d['kind'] . ':' . $d['id']; }, $t['dependencies']))) ?></span>
            <?php endif; ?>
            <?php if (!empty($t['conflicts'])): ?>
            <span style="color:#c0353a" title="<?= t_admin('adm_theme_conflict_title', '冲突单元') ?>"><i class="fas fa-triangle-exclamation"></i> <?= htmlspecialchars(implode(', ', array_map(function ($d) { return $d['kind'] . ':' . $d['id']; }, $t['conflicts']))) ?></span>
            <?php endif; ?>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap">
            <?php if (!$isInstalled): ?>
                <a href="<?= htmlspecialchars(admin_url('themes', ['do' => 'install', 'id' => $id, '_csrf' => Security::csrfToken()])) ?>"
                   class="btn btn-sm btn-green" title="<?= t_admin('adm_theme_install_title', '安装主题（执行 theme_install_{id}）') ?>">
                    <i class="fas fa-download"></i> <?= t_admin('adm_theme_install', '安装') ?>
                </a>
                <a href="<?= htmlspecialchars(admin_url('themes', ['do' => 'delete', 'id' => $id, '_csrf' => Security::csrfToken()])) ?>"
                   class="btn btn-sm btn-danger" title="<?= t_admin('adm_theme_delete_title', '删除主题目录') ?>" data-confirm="<?= t_admin('adm_theme_delete_confirm', '确认删除目录？不可恢复') ?>">
                    <i class="fas fa-xmark"></i> <?= t_admin('adm_theme_delete', '删除') ?>
                </a>
            <?php else: ?>
                <?php if (!empty($t['supports_pc']) && !$isPc): ?>
                <a href="<?= htmlspecialchars(admin_url('themes', ['do' => 'enable_pc', 'id' => $id, '_csrf' => Security::csrfToken()])) ?>"
                   class="btn btn-sm btn-outline" title="<?= t_admin('adm_theme_enable_pc_title', '设为PC主题') ?>" data-confirm="<?= t_admin('adm_theme_enable_pc_confirm', '确认将该主题设为PC主题？') ?>">
                    <i class="fas fa-desktop"></i> <?= t_admin('adm_theme_set_pc', '设为PC') ?>
                </a>
                <?php endif; ?>
                <?php if (!empty($t['supports_mobile']) && !$isMobile): ?>
                <a href="<?= htmlspecialchars(admin_url('themes', ['do' => 'enable_mobile', 'id' => $id, '_csrf' => Security::csrfToken()])) ?>"
                   class="btn btn-sm btn-outline" title="<?= t_admin('adm_theme_enable_mobile_title', '设为手机主题') ?>" data-confirm="<?= t_admin('adm_theme_enable_mobile_confirm', '确认将该主题设为手机主题？') ?>">
                    <i class="fas fa-mobile-screen"></i> <?= t_admin('adm_theme_set_mobile', '设为手机') ?>
                </a>
                <?php endif; ?>
                <?php
                $hasAdmin = is_file(ROOT_PATH . 'templates/' . $id . '/admin.php');
                $hasConfig = $t['has_config'] || !empty(ThemeConfig::fields($id));
                if ($hasAdmin || $hasConfig):
                ?>
                <a href="<?= htmlspecialchars(admin_url('theme_admin', ['name' => $id])) ?>"
                   class="btn btn-sm btn-outline" title="<?= t_admin('adm_theme_settings_title', '主题设置') ?>">
                    <i class="fas fa-gear"></i> <?= t_admin('adm_theme_settings', '设置') ?>
                </a>
                <?php endif; ?>
                <?php if (!$isPc && !$isMobile): ?>
                <a href="<?= htmlspecialchars(admin_url('themes', ['do' => 'uninstall', 'id' => $id, '_csrf' => Security::csrfToken()])) ?>"
                   class="btn btn-sm btn-danger" title="<?= t_admin('adm_theme_uninstall_title', '卸载主题（执行 theme_uninstall_{id}）') ?>" data-confirm="<?= t_admin('adm_theme_uninstall_confirm', '确认卸载？将执行回滚SQL并清除配置') ?>">
                    <i class="fas fa-trash-can"></i> <?= t_admin('adm_theme_uninstall', '卸载') ?>
                </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
</div>
<?php if (empty($themes)): ?>
<p class="empty">templates/ <?= t_admin('adm_theme_empty', '目录下暂无主题') ?></p>
<?php endif; ?>
<?php endif; ?>
