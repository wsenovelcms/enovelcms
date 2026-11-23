<?php

if (!defined('ADMIN_BOOT')) exit;
$pageTitle = t_admin('adm_plugin', '插件管理');

if (g('delete', null, 'raw') !== null) {
    if (Security::verifyCsrf()) {
        $delId = (string)$_GET['delete'];
        if (Security::validPluginId($delId)) {
            $res = Plugin::delete($delId, $db);
            admin_flash_redirect($res['msg'], 'plugins', ['filter' => 'disabled']);
        }
        admin_flash_redirect(t_admin('adm_plugin_bad_id', '插件标识不合法'), 'plugins', ['filter' => 'disabled']);
    }
}
if (g('enable', null, 'raw') !== null) {
    if (Security::verifyCsrf()) {
        $id = (string)$_GET['enable'];
        if (Security::validPluginId($id)) {
            try {
                Plugin::enable($id, $db);
                admin_flash_redirect(t_admin('adm_plugin_enabled', '插件已启用'), 'plugins', ['filter' => 'enabled']);
            } catch (\Throwable $e) {
                admin_flash_redirect(t_admin('adm_plugin_enable_fail', '启用失败：%s', $e->getMessage()), 'plugins', ['filter' => 'disabled']);
            }
        }
        admin_flash_redirect(t_admin('adm_plugin_bad_id', '插件标识不合法'), 'plugins', ['filter' => 'disabled']);
    }
}
if (g('disable', null, 'raw') !== null) {
    if (Security::verifyCsrf()) {
        $id = (string)$_GET['disable'];
        if (Security::validPluginId($id)) {
            Plugin::disable($id, $db);
            admin_flash_redirect(t_admin('adm_plugin_disabled', '插件已禁用'), 'plugins', ['filter' => 'disabled']);
        }
        admin_flash_redirect(t_admin('adm_plugin_bad_id', '插件标识不合法'), 'plugins', ['filter' => 'disabled']);
    }
}

$plugins = Plugin::scanAll();
$enabledIds = Plugin::enabledIds();

$ac = new AppCenterService($db);
$localForCheck = [];
foreach ($plugins as $pid => $pp) {
    $localForCheck[$pid] = ['version' => (string)($pp['version'] ?? '1.0.0'), 'type' => 'plugin'];
}
$updates = $ac->checkUpdates($localForCheck);

$filter = (string)(g('filter', '', 'str'));
if ($filter !== 'enabled' && $filter !== 'disabled') {
    header('Location: ' . admin_url('plugins', ['filter' => 'enabled']));
    exit;
}
if ($filter === 'enabled') {
    $plugins = array_filter($plugins, fn($id) => in_array($id, $enabledIds, true), ARRAY_FILTER_USE_KEY);
} elseif ($filter === 'disabled') {
    $plugins = array_filter($plugins, fn($id) => !in_array($id, $enabledIds, true), ARRAY_FILTER_USE_KEY);
}
$filterLabel = array('enabled' => t_admin('adm_plugin_enabled_list', '已启用插件'), 'disabled' => t_admin('adm_plugin_disabled_list', '未启用插件'))[$filter] ?? t_admin('adm_plugin_all', '全部插件');
?>
<div class="card">
    <h2><i class="fas fa-puzzle-piece"></i> <?= t_admin('adm_plugin', '插件管理') ?> <span class="text-muted" style="font-size:13px;font-weight:400">/ <?= htmlspecialchars($filterLabel) ?></span></h2>

    <table class="data plugin-table">
        <colgroup>
            <col style="width:46px">
            <col style="width:360px">
            <col style="width:132px">
            <col style="width:100px">
            <col style="width:88px">
            <col style="width:172px">
        </colgroup>
        <thead>
            <tr>
                <th></th>
                <th><?= t_admin('adm_plugin_col_name', '插件名称') ?></th>
                <th><?= t_admin('adm_plugin_col_version', '版本') ?></th>
                <th><?= t_admin('adm_plugin_col_author', '作者') ?></th>
                <th><?= t_admin('adm_plugin_col_status', '状态') ?></th>
                <th><?= t_admin('adm_operation', '操作') ?></th>
            <?= PluginTable::renderHead('admin.plugins') ?></tr>
        </thead>
        <tbody>
        <?php foreach ($plugins as $id => $p):
            $isEnabled = in_array($id, $enabledIds, true);
            $authorName = is_array($p['author'] ?? null) ? ($p['author']['name'] ?? '') : ($p['author'] ?? '');
            $authorUrl = is_array($p['author'] ?? null) ? ($p['author']['url'] ?? '') : '';
            $hasAdmin = Plugin::hasAdminPage($id) || count(PluginConfig::fields($id)) > 0;
        ?>
        <tr>
            <td style="text-align:center">
                <?php
                    $iconUrl = !empty($p['icon']) ? $p['icon'] : '/assets/images/default_plugin_logo.png';
                    $iconUrl = (strpos($iconUrl, 'http') === 0) ? $iconUrl : BASE_URL . $iconUrl;
                ?>
                <img src="<?= htmlspecialchars($iconUrl) ?>" alt="" style="width:34px;height:34px;border-radius:6px;object-fit:cover;background:#f1f5f9">
            </td>
            <td class="plugin-name">
                <strong><?= htmlspecialchars((string)$p['name'] ?? $id) ?></strong>
                <code style="font-size:11.5px">/<?= htmlspecialchars($id) ?></code>
                <div class="text-muted" style="font-size:12px;margin-top:2px"><?= htmlspecialchars(excerpt((string)($p['description'] ?? ''), 36)) ?></div>
            </td>
            <td>
                <?php $up = $updates[$id] ?? null; ?>
                <?php if ($up && $up['has_update']): ?>
                    <div style="line-height:1.5">
                        <div>v<?= htmlspecialchars($up['local']) ?></div>
                        <div class="badge badge-orange" style="display:inline-block;margin-top:3px;white-space:nowrap"><?= t_admin('adm_plugin_latest', '最新 v%s', htmlspecialchars($up['latest'])) ?></div>
                    </div>
                <?php else: ?>
                    <?= htmlspecialchars((string)$p['version'] ?? '1.0.0') ?>
                <?php endif; ?>
            </td>
            <td style="white-space:nowrap">
                <?php if ($authorName !== ''): ?>
                    <?php if ($authorUrl): ?><a href="<?= htmlspecialchars($authorUrl) ?>" target="_blank" rel="noopener"><?php endif; ?>
                    <?= htmlspecialchars($authorName) ?>
                    <?php if ($authorUrl): ?></a><?php endif; ?>
                <?php else: ?><span class="text-muted">—</span><?php endif; ?>
            </td>
            <td>
                <?php if ($isEnabled): ?><span class="badge badge-green"><?= t_admin('adm_plugin_enabled', '已启用') ?></span>
                <?php else: ?><span class="badge badge-gray"><?= t_admin('adm_plugin_disabled', '未启用') ?></span><?php endif; ?>
            </td>
            <td>
                <div style="display:flex;gap:6px;flex-wrap:wrap">
                    <?php if ($isEnabled): ?>
                        <a href="<?= htmlspecialchars(admin_url('plugins', ['disable' => $id, '_csrf' => Security::csrfToken()])) ?>"
                           class="btn btn-sm btn-danger" title="<?= t_admin('adm_plugin_disable_title', '停用插件（保留数据表与设置）') ?>" data-confirm="<?= t_admin('adm_plugin_disable_confirm', '确认停用？停用后暂停使用该插件，数据表与设置保留，不会执行卸载。') ?>"><i class="fas fa-toggle-off"></i></a>
                    <?php else: ?>
                        <a href="<?= htmlspecialchars(admin_url('plugins', ['enable' => $id, '_csrf' => Security::csrfToken()])) ?>"
                           class="btn btn-sm btn-green" title="<?= t_admin('adm_plugin_enable_title', '启用并安装插件') ?>"><i class="fas fa-toggle-on"></i></a>
                    <?php endif; ?>
                    <?php if ($up && $up['has_update']): ?>
                        <form method="post" action="<?= htmlspecialchars(admin_url('appcenter')) ?>" style="margin:0"
                              onsubmit="return confirm('<?= t_admin('adm_plugin_upgrade_confirm', '将从应用中心下载最新版覆盖安装该插件，确定升级？') ?>')">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="do" value="install">
                            <input type="hidden" name="app_key" value="<?= htmlspecialchars($id) ?>">
                            <input type="hidden" name="back_type" value="plugin">
                            <button type="submit" class="btn btn-sm btn-primary" title="<?= t_admin('adm_plugin_upgrade_title', '升级到最新版本') ?>">
                                <i class="fas fa-arrow-up"></i> <?= t_admin('adm_plugin_upgrade_btn', '升级') ?>
                            </button>
                        </form>
                    <?php endif; ?>
                    <?php if ($hasAdmin): ?>
                        <a href="<?= htmlspecialchars(admin_url('plugin', ['pid' => $id])) ?>" class="btn btn-sm btn-outline" title="<?= t_admin('adm_plugin_settings_title', '插件设置') ?>"><i class="fas fa-gear"></i></a>
                    <?php endif; ?>
                    <?php if (!$isEnabled): ?>
                        <a href="<?= htmlspecialchars(admin_url('plugins', ['delete' => $id, '_csrf' => Security::csrfToken()])) ?>"
                           class="btn btn-sm btn-danger" title="<?= t_admin('adm_plugin_delete_title', '删除插件目录') ?>" data-confirm="<?= t_admin('adm_plugin_delete_confirm', '确认删除？将先执行卸载清理数据表与配置，再移除插件目录，不可恢复') ?>"><i class="fas fa-trash"></i></a>
                    <?php endif; ?>
                </div>
            </td>
        <?= PluginTable::renderCells('admin.plugins', $p) ?></tr>
        <?php endforeach; ?>
        <?php if (empty($plugins)): ?>
        <tr><td colspan="<?= 6 + PluginTable::count('admin.plugins') ?>" class="empty">plugins/ <?= t_admin('adm_plugin_empty', '目录下暂无插件') ?></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
