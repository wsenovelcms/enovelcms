<?php

if (!defined('ADMIN_BOOT')) exit;
$pageTitle = t_admin('adm_language', '语言管理');

$error = '';

$defaultTheme = 'default';
$activeTheme  = Theme::activePc();
$isDefaultActive = ($activeTheme === $defaultTheme);

$adminLangs          = AdminLanguage::listPackages();
$currentAdminDefault = SettingModel::get('admin_lang', 'zh-cn');

$frontLangs       = Language::themeLanguages($defaultTheme);
$currentFrontLang = SettingModel::get('site_lang', 'zh-cn');

$themeLangs   = $isDefaultActive ? array() : Language::themeLanguages($activeTheme);
$showThemeTab = !$isDefaultActive && !empty($themeLangs);

$allThemes        = Theme::scanAll();
$activeThemeTitle = $allThemes[$activeTheme]['title'] ?? $activeTheme;

$pinLang = function (array $langs, string $pin): array {
    if ($pin !== '' && isset($langs[$pin])) {
        $pinned = array($pin => $langs[$pin]);
        unset($langs[$pin]);
        return $pinned + $langs;
    }
    return $langs;
};
$adminLangsSorted = $pinLang($adminLangs, $currentAdminDefault);
$frontLangsSorted = $pinLang($frontLangs, $currentFrontLang);
$themeLangsSorted = $pinLang($themeLangs, $currentFrontLang);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && p('set_default_admin_lang', null, 'raw') !== null) {
    if (!Security::verifyCsrf()) {
        $error = t_admin('adm_csrf_failed_short', 'CSRF验证失败');
    } else {
        $adminDefault = (string)(p('admin_default_lang', '', 'str'));
        if (AdminLanguage::isAvailable($adminDefault)) {
            SettingModel::set('admin_lang', $adminDefault);
            admin_flash_redirect(t_admin('adm_lang_admin_default_updated', '后台默认语言已更新'), 'language', ['tab' => 'admin']);
        }
        $error = t_admin('adm_lang_code_invalid2', '无效的语言代码');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && p('upload_admin_lang', null, 'raw') !== null) {
    if (!Security::verifyCsrf()) {
        $error = t_admin('adm_csrf_failed_short', 'CSRF验证失败');
    } else {
        $langCode = (string)(p('lang_code', '', 'str'));
        $langName = (string)(p('lang_name', '', 'str'));
        $res = AdminLanguage::installPackage($_FILES['lang_file'] ?? array(), $langCode, $langName);
        if ($res['success']) {
            admin_flash_redirect($res['message'], 'language', ['tab' => 'admin']);
        }
        $error = $res['message'];
    }
}

if (g('delete_admin_lang', null, 'raw') !== null) {
    if (Security::verifyCsrf()) {
        $deleteCode = (string)$_GET['delete_admin_lang'];
        $res = AdminLanguage::uninstallPackage($deleteCode);
        if ($res['success']) {
            admin_flash_redirect($res['message'], 'language', ['tab' => 'admin']);
        }
        $error = $res['message'];
    } else {
        $error = t_admin('adm_csrf_failed_short', 'CSRF验证失败');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && p('set_default_front_lang', null, 'raw') !== null) {
    if (!Security::verifyCsrf()) {
        $error = t_admin('adm_csrf_failed_short', 'CSRF验证失败');
    } else {
        $frontCode = (string)(p('front_default_lang', '', 'str'));
        if (isset($frontLangs[$frontCode]) || isset($themeLangs[$frontCode])) {
            SettingModel::set('site_lang', $frontCode);
            SettingModel::clearCache();
            admin_flash_redirect(t_admin('adm_theme_front_lang_updated', '前台默认语言已更新'), 'language', ['tab' => 'front']);
        }
        $error = t_admin('adm_lang_code_invalid2', '无效的语言代码');
    }
}

if (g('set_default_lang', null, 'raw') !== null) {
    if (Security::verifyCsrf()) {
        $code = (string)$_GET['set_default_lang'];
        if (isset($themeLangs[$code]) || isset($frontLangs[$code])) {
            SettingModel::set('site_lang', $code);
            SettingModel::clearCache();
            $backTab = (g('tab', 'front', 'str') === 'theme') ? 'theme' : 'front';
            admin_flash_redirect(t_admin('adm_theme_front_lang_updated', '前台默认语言已更新'), 'language', ['tab' => $backTab]);
        }
        $error = t_admin('adm_lang_code_invalid2', '无效的语言代码');
    } else {
        $error = t_admin('adm_csrf_failed_short', 'CSRF验证失败');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && p('upload_default_lang', null, 'raw') !== null) {
    if (!Security::verifyCsrf()) {
        $error = t_admin('adm_csrf_failed_short', 'CSRF验证失败');
    } else {
        $code = (string)(p('lang_code', '', 'str'));
        $langName = (string)(p('lang_name', '', 'str'));
        $res = Language::installThemePackage($defaultTheme, $_FILES['lang_file'] ?? array(), $code, $langName);
        if ($res['success']) {
            admin_flash_redirect($res['message'], 'language', ['tab' => 'front']);
        }
        $error = $res['message'];
    }
}

if (g('delete_default_lang', null, 'raw') !== null) {
    if (Security::verifyCsrf()) {
        $code = (string)$_GET['delete_default_lang'];
        $res = Language::uninstallThemePackage($defaultTheme, $code);
        if ($res['success']) {
            admin_flash_redirect($res['message'], 'language', ['tab' => 'front']);
        }
        $error = $res['message'];
    } else {
        $error = t_admin('adm_csrf_failed_short', 'CSRF验证失败');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && p('upload_theme_lang', null, 'raw') !== null) {
    if (!Security::verifyCsrf()) {
        $error = t_admin('adm_csrf_failed_short', 'CSRF验证失败');
    } else {
        $code = (string)(p('lang_code', '', 'str'));
        $langName = (string)(p('lang_name', '', 'str'));
        $res = Language::installThemePackage($activeTheme, $_FILES['lang_file'] ?? array(), $code, $langName);
        if ($res['success']) {
            admin_flash_redirect($res['message'], 'language', ['tab' => 'theme']);
        }
        $error = $res['message'];
    }
}

if (g('delete_theme_lang', null, 'raw') !== null) {
    if (Security::verifyCsrf()) {
        $code = (string)$_GET['delete_theme_lang'];
        $res = Language::uninstallThemePackage($activeTheme, $code);
        if ($res['success']) {
            admin_flash_redirect($res['message'], 'language', ['tab' => 'theme']);
        }
        $error = $res['message'];
    } else {
        $error = t_admin('adm_csrf_failed_short', 'CSRF验证失败');
    }
}
?>
<?php if ($error !== ''): ?>
<div class="alert alert-err"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card">
    <div class="tabs">
        <button type="button" class="tab-btn" data-tab="admin"><i class="fas fa-flag"></i> <?= t_admin('adm_lang_tab_admin', '后台语言包管理') ?></button>
        <button type="button" class="tab-btn" data-tab="front"><i class="fas fa-globe"></i> <?= t_admin('adm_lang_tab_front', '前端默认语言管理') ?></button>
        <?php if ($showThemeTab): ?>
        <button type="button" class="tab-btn" data-tab="theme"><i class="fas fa-language"></i> <?= t_admin('adm_lang_tab_theme', '主题语言管理') ?></button>
        <?php endif; ?>
    </div>

    <div class="tab-pane" data-pane="admin">
        <h2><i class="fas fa-flag"></i> <?= t_admin('adm_lang_admin_default', '后台默认语言') ?></h2>
        <form method="post" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
            <?= Security::csrfField() ?>
            <div class="form-group" style="margin:0">
                <select name="admin_default_lang" class="form-control">
                    <?php foreach ($adminLangs as $code => $name): ?>
                    <option value="<?= htmlspecialchars($code) ?>" <?= $currentAdminDefault === $code ? 'selected' : '' ?>><?= htmlspecialchars($name) ?> (<?= htmlspecialchars($code) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" name="set_default_admin_lang" class="btn btn-primary"><i class="fas fa-check"></i> <?= t_admin('adm_lang_set_default', '设为默认') ?></button>
        </form>

        <div class="table-wrap" style="margin-top:16px">
        <table class="data">
            <thead><tr><th><?= t_admin('adm_lang_col_code', '语言代码') ?></th><th><?= t_admin('adm_lang_col_name', '显示名称') ?></th><th><?= t_admin('adm_lang_col_status', '状态') ?></th><th><?= t_admin('adm_operation', '操作') ?></th><?= PluginTable::renderHead('admin.language_admin') ?></tr></thead>
            <tbody>
            <?php if (empty($adminLangs)): ?>
                <tr><td colspan="<?= 4 + PluginTable::count('admin.language_admin') ?>" class="empty"><?= t_admin('adm_lang_none', '暂无语言包') ?></td></tr>
            <?php else: ?>
                <?php foreach ($adminLangsSorted as $code => $name): ?>
                <tr>
                    <td><?= htmlspecialchars($code) ?></td>
                    <td><?= htmlspecialchars($name) ?></td>
                    <td><?= $currentAdminDefault === $code ? '<span class="badge badge-green">' . t_admin('adm_lang_current_default', '当前默认') . '</span>' : '' ?></td>
                    <td>
                        <?php if ($code !== $currentAdminDefault && $code !== 'zh-cn'): ?>
                        <a href="<?= htmlspecialchars(admin_url('language', ['delete_admin_lang' => $code, '_csrf' => Security::csrfToken()])) ?>"
                           class="btn btn-sm btn-danger" data-confirm="<?= t_admin('adm_lang_delete_confirm', '确认删除此语言包？') ?>"><i class="fas fa-trash"></i> <?= t_admin('adm_delete', '删除') ?></a>
                        <?php else: ?>
                        <span class="text-muted"><?= t_admin('adm_lang_default_keep', '默认不可删除') ?></span>
                        <?php endif; ?>
                    </td>
                    <?= PluginTable::renderCells('admin.language_admin', ['code' => $code, 'name' => $name]) ?>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        </div>

        <h2 style="margin-top:20px"><i class="fas fa-upload"></i> <?= t_admin('adm_lang_upload', '上传新语言包') ?></h2>
        <form method="post" enctype="multipart/form-data" style="max-width:640px">
            <?= Security::csrfField() ?>
            <div class="form-group">
                <label><?= t_admin('adm_lang_code_label', '语言代码') ?></label>
                <input type="text" name="lang_code" class="form-control" required pattern="[a-z]{2}-[a-z]{2}" placeholder="<?= t_admin('adm_lang_code_ph', '如 en-us') ?>">
            </div>
            <div class="form-group">
                <label><?= t_admin('adm_lang_name_label', '显示名称（语言自称）') ?></label>
                <input type="text" name="lang_name" class="form-control" maxlength="100" placeholder="<?= t_admin('adm_lang_name_placeholder', '如 العربية，留空则显示语言代码') ?>">
            </div>
            <div class="form-group">
                <label><?= t_admin('adm_lang_file', '语言文件（.php）') ?></label>
                <input type="file" name="lang_file" class="form-control" accept=".php" required>
            </div>
            <button type="submit" name="upload_admin_lang" class="btn btn-primary"><i class="fas fa-upload"></i> <?= t_admin('adm_lang_upload_install', '上传并安装') ?></button>
        </form>
    </div>

    <div class="tab-pane" data-pane="front">
        <h2><i class="fas fa-globe"></i> <?= t_admin('adm_lang_front_default', '前台默认语言') ?></h2>
        <?php if (empty($frontLangs)): ?>
        <p class="text-muted"><?= t_admin('adm_lang_none', '暂无语言包') ?></p>
        <?php else: ?>
        <form method="post" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
            <?= Security::csrfField() ?>
            <div class="form-group" style="margin:0">
                <select name="front_default_lang" class="form-control">
                    <?php foreach ($frontLangs as $code => $name): ?>
                    <option value="<?= htmlspecialchars($code) ?>" <?= $currentFrontLang === $code ? 'selected' : '' ?>><?= htmlspecialchars($name) ?> (<?= htmlspecialchars($code) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" name="set_default_front_lang" class="btn btn-primary"><i class="fas fa-check"></i> <?= t_admin('adm_lang_set_default', '设为默认') ?></button>
        </form>

        <h2 style="margin-top:20px"><i class="fas fa-language"></i> <?= t_admin('adm_lang_default_theme_pkg', '默认主题语言包') ?></h2>
        <div class="table-wrap">
        <table class="data">
            <thead><tr><th><?= t_admin('adm_lang_col_code', '语言代码') ?></th><th><?= t_admin('adm_lang_col_name', '显示名称') ?></th><th><?= t_admin('adm_lang_col_status', '状态') ?></th><th><?= t_admin('adm_operation', '操作') ?></th><?= PluginTable::renderHead('admin.language_front') ?></tr></thead>
            <tbody>
            <?php foreach ($frontLangsSorted as $code => $name): ?>
            <tr>
                <td><?= htmlspecialchars($code) ?></td>
                <td><?= htmlspecialchars($name) ?></td>
                <td><?= $currentFrontLang === $code ? '<span class="badge badge-green">' . t_admin('adm_lang_current_default', '当前默认') . '</span>' : '' ?></td>
                <td>
                    <?php if ($code === $currentFrontLang): ?>
                    <span class="text-muted"><?= t_admin('adm_lang_default_keep', '默认不可删除') ?></span>
                    <?php else: ?>
                    <a href="<?= htmlspecialchars(admin_url('language', ['set_default_lang' => $code, 'tab' => 'front', '_csrf' => Security::csrfToken()])) ?>"
                       class="btn btn-sm btn-outline"><i class="fas fa-check"></i> <?= t_admin('adm_lang_set_default', '设为默认') ?></a>
                    <?php if ($code !== 'zh-cn'): ?>
                    <a href="<?= htmlspecialchars(admin_url('language', ['delete_default_lang' => $code, '_csrf' => Security::csrfToken()])) ?>"
                       class="btn btn-sm btn-danger" data-confirm="<?= t_admin('adm_lang_delete_confirm', '确认删除此语言包？') ?>"><i class="fas fa-trash"></i> <?= t_admin('adm_delete', '删除') ?></a>
                    <?php else: ?>
                    <span class="text-muted"><?= t_admin('adm_lang_default_keep', '默认不可删除') ?></span>
                    <?php endif; ?>
                    <?php endif; ?>
                </td>
                <?= PluginTable::renderCells('admin.language_front', ['code' => $code, 'name' => $name]) ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <form method="post" enctype="multipart/form-data" style="max-width:640px;margin-top:16px">
            <?= Security::csrfField() ?>
            <div class="form-group">
                <label><?= t_admin('adm_lang_code_label', '语言代码') ?></label>
                <input type="text" name="lang_code" class="form-control" required pattern="[a-z]{2}-[a-z]{2}" placeholder="<?= t_admin('adm_lang_code_ph', '如 en-us') ?>">
            </div>
            <div class="form-group">
                <label><?= t_admin('adm_lang_name_label', '显示名称（语言自称）') ?></label>
                <input type="text" name="lang_name" class="form-control" maxlength="100" placeholder="<?= t_admin('adm_lang_name_placeholder', '如 العربية，留空则显示语言代码') ?>">
            </div>
            <div class="form-group">
                <label><?= t_admin('adm_lang_file', '语言文件（.php）') ?></label>
                <input type="file" name="lang_file" class="form-control" accept=".php" required>
            </div>
            <button type="submit" name="upload_default_lang" class="btn btn-primary"><i class="fas fa-upload"></i> <?= t_admin('adm_lang_upload_install', '上传并安装') ?></button>
        </form>
        <?php endif; ?>
    </div>

    <?php if ($showThemeTab): ?>
    <div class="tab-pane" data-pane="theme">
        <h2><i class="fas fa-language"></i> <?= t_admin('adm_lang_theme_current', '当前主题：%s', htmlspecialchars($activeThemeTitle . ' (' . $activeTheme . ')')) ?></h2>
        <div class="table-wrap">
        <table class="data">
            <thead><tr><th><?= t_admin('adm_lang_col_code', '语言代码') ?></th><th><?= t_admin('adm_lang_col_name', '显示名称') ?></th><th><?= t_admin('adm_lang_col_status', '状态') ?></th><th><?= t_admin('adm_operation', '操作') ?></th><?= PluginTable::renderHead('admin.language_theme') ?></tr></thead>
            <tbody>
            <?php if (empty($themeLangs)): ?>
                <tr><td colspan="<?= 4 + PluginTable::count('admin.language_theme') ?>" class="empty"><?= t_admin('adm_lang_none', '暂无语言包') ?></td></tr>
            <?php else: ?>
                <?php foreach ($themeLangsSorted as $code => $name): ?>
                <tr>
                    <td><?= htmlspecialchars($code) ?></td>
                    <td><?= htmlspecialchars($name) ?></td>
                    <td><?= $currentFrontLang === $code ? '<span class="badge badge-green">' . t_admin('adm_lang_current_default', '当前默认') . '</span>' : '' ?></td>
                    <td>
                        <?php if ($code === $currentFrontLang): ?>
                        <span class="text-muted"><?= t_admin('adm_lang_default_keep', '默认不可删除') ?></span>
                        <?php else: ?>
                        <a href="<?= htmlspecialchars(admin_url('language', ['set_default_lang' => $code, 'tab' => 'theme', '_csrf' => Security::csrfToken()])) ?>"
                           class="btn btn-sm btn-outline"><i class="fas fa-check"></i> <?= t_admin('adm_lang_set_default', '设为默认') ?></a>
                        <?php if ($code !== 'zh-cn'): ?>
                        <a href="<?= htmlspecialchars(admin_url('language', ['delete_theme_lang' => $code, '_csrf' => Security::csrfToken()])) ?>"
                           class="btn btn-sm btn-danger" data-confirm="<?= t_admin('adm_lang_delete_confirm', '确认删除此语言包？') ?>"><i class="fas fa-trash"></i> <?= t_admin('adm_delete', '删除') ?></a>
                        <?php else: ?>
                        <span class="text-muted"><?= t_admin('adm_lang_default_keep', '默认不可删除') ?></span>
                        <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <?= PluginTable::renderCells('admin.language_theme', ['code' => $code, 'name' => $name]) ?>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
        <form method="post" enctype="multipart/form-data" style="max-width:640px;margin-top:16px">
            <?= Security::csrfField() ?>
            <div class="form-group">
                <label><?= t_admin('adm_lang_code_label', '语言代码') ?></label>
                <input type="text" name="lang_code" class="form-control" required pattern="[a-z]{2}-[a-z]{2}" placeholder="<?= t_admin('adm_lang_code_ph', '如 en-us') ?>">
            </div>
            <div class="form-group">
                <label><?= t_admin('adm_lang_name_label', '显示名称（语言自称）') ?></label>
                <input type="text" name="lang_name" class="form-control" maxlength="100" placeholder="<?= t_admin('adm_lang_name_placeholder', '如 العربية，留空则显示语言代码') ?>">
            </div>
            <div class="form-group">
                <label><?= t_admin('adm_lang_file', '语言文件（.php）') ?></label>
                <input type="file" name="lang_file" class="form-control" accept=".php" required>
            </div>
            <button type="submit" name="upload_theme_lang" class="btn btn-primary"><i class="fas fa-upload"></i> <?= t_admin('adm_lang_upload_install', '上传并安装') ?></button>
        </form>
    </div>
    <?php endif; ?>
</div>
