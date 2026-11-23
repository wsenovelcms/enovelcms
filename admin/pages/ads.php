<?php

if (!defined('ADMIN_BOOT')) exit;
$pageTitle = t_admin('adm_ads', '广告管理');

$positions = array(
    'home_banner' => t_admin('adm_ads_position_home_banner', '首页轮播图'),
    'home_sidebar_top' => t_admin('adm_ads_position_home_sidebar_top', '首页侧边栏顶部'),
    'home_sidebar_bottom' => t_admin('adm_ads_position_home_sidebar_bottom', '首页侧边栏底部'),
    'novel_detail_top' => t_admin('adm_ads_position_novel_detail_top', '小说详情页顶部'),
    'novel_detail_bottom' => t_admin('adm_ads_position_novel_detail_bottom', '小说详情页底部'),
    'read_top' => t_admin('adm_ads_position_read_top', '阅读页顶部'),
    'read_bottom' => t_admin('adm_ads_position_read_bottom', '阅读页底部'),
    'library_top' => t_admin('adm_ads_position_library_top', '书库页顶部'),
    'rank_top' => t_admin('adm_ads_position_rank_top', '排行榜顶部'),
);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && p('ad_submit', null, 'raw') !== null) {
    if (Security::verifyCsrf()) {
        $id = p('id', 0, 'int');
        $name = trim(p('name', '', 'text'));
        $position = p('position', '', 'text');
        if ($name === '') {
            $error = t_admin('adm_ads_name_empty', '广告名称不能为空');
        } elseif (!array_key_exists($position, $positions)) {
            $error = t_admin('adm_ads_position_bad', '广告位置不合法');
        } else {
            $data = array(
                'name' => $name,
                'position' => $position,
                'html_code' => p('html_code', '', 'text'),
                'sort' => p('sort', 0, 'int'),
                'enabled' => p('enabled', null, 'raw') !== null ? 1 : 0,
            );
            if ($id) {
                AdsModel::update($data, array('id' => $id));
            } else {
                $id = (int)AdsModel::create($data);
            }
            if ($id) PluginForm::save('ad_edit', 'ad', (int)$id);
            admin_flash_redirect(t_admin('adm_ads_saved', '广告已保存'), 'ads');
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && p('save_keyword_links', null, 'raw') !== null) {
    if (Security::verifyCsrf()) {
        SettingModel::set('keyword_links', trim(p('keyword_links', '', 'text')));
        admin_flash_redirect(t_admin('adm_ads_keyword_saved', '关键词链接已保存'), 'ads');
    }
}

if (g('delete', null, 'raw') !== null) {
    if (Security::verifyCsrf()) {
        AdsModel::delete(array('id' => g('delete', 0, 'int')));
        admin_flash_redirect(t_admin('adm_ads_deleted', '广告已删除'), 'ads');
    }
}

$ads = AdsModel::all(array(), 'sort ASC, id ASC');
$editId = (int)($_GET['edit'] ?? 0);
$editAd = $editId ? AdsModel::findById($editId) : null;
?>
<div style="display:grid;grid-template-columns:1fr 1.4fr;gap:18px" class="ad-layout">
    <div class="card" style="margin-bottom:0">
        <h2><?= $editAd ? t_admin('adm_ads_edit', '编辑广告') : t_admin('adm_ads_add', '添加广告') ?></h2>
        <?php if ($error): ?><div class="alert alert-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post">
            <?= Security::csrfField() ?>
            <input type="hidden" name="ad_submit" value="1">
            <input type="hidden" name="id" value="<?= $editAd ? (int)$editAd['id'] : 0 ?>">
            <div class="form-group"><label><?= t_admin('adm_ads_name', '广告名称') ?></label><input type="text" name="name" class="form-control" required value="<?= $editAd ? htmlspecialchars((string)$editAd['name']) : '' ?>" style="max-width:100%"></div>
            <div class="form-group"><label><?= t_admin('adm_ads_position', '广告位置') ?></label>
                <select name="position" class="form-control" style="max-width:100%">
                    <?php foreach ($positions as $val => $label): ?>
                    <option value="<?= htmlspecialchars($val) ?>" <?= $editAd && $editAd['position'] === $val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label><?= t_admin('adm_ads_html_code', 'HTML 代码') ?></label><textarea name="html_code" class="form-control" style="min-height:120px;font-family:monospace" placeholder="<a href='...'><img src='...'></a>"><?= $editAd ? htmlspecialchars((string)$editAd['html_code']) : '' ?></textarea></div>
            <div class="form-group"><label><?= t_admin('adm_ads_sort', '排序') ?></label><input type="number" name="sort" class="form-control" value="<?= $editAd ? (int)$editAd['sort'] : 0 ?>" style="max-width:120px"></div>
            <div class="form-group"><label style="font-weight:400;display:inline-flex;gap:8px;align-items:center"><input type="checkbox" name="enabled" value="1" <?= !$editAd || (int)$editAd['enabled'] === 1 ? 'checked' : '' ?>> <?= t_admin('adm_enable', '启用') ?></label></div>
            <?= PluginForm::render('ad_edit', 'ad', $editAd ? (int)$editAd['id'] : 0) ?>
            <button class="btn btn-primary"><i class="fas fa-save"></i> <?= t_admin('adm_save', '保存') ?></button>
            <?php if ($editAd): ?><a href="<?= htmlspecialchars(admin_url('ads')) ?>" class="btn btn-outline"><?= t_admin('adm_cancel', '取消') ?></a><?php endif; ?>
        </form>
    </div>

    <div class="card" style="margin-bottom:0">
        <h2><?= t_admin('adm_ads_list', '现有广告（%d）', count($ads)) ?></h2>
        <div class="table-wrap">
        <table class="data">
            <thead><tr><th>ID</th><th><?= t_admin('adm_ads_col_name', '名称') ?></th><th><?= t_admin('adm_ads_col_position', '位置') ?></th><th><?= t_admin('adm_ads_sort', '排序') ?></th><th><?= t_admin('adm_status', '状态') ?></th><th><?= t_admin('adm_operation', '操作') ?></th><?= PluginTable::renderHead('admin.ads') ?></tr></thead>
            <tbody>
            <?php foreach ($ads as $ad): ?>
            <tr>
                <td><?= (int)$ad['id'] ?></td>
                <td><?= htmlspecialchars((string)$ad['name']) ?></td>
                <td><?= htmlspecialchars((string)$positions[$ad['position']] ?? $ad['position']) ?></td>
                <td><?= (int)$ad['sort'] ?></td>
                <td><?= (int)$ad['enabled'] ? '<span class="badge badge-green">' . t_admin('adm_enable', '启用') . '</span>' : '<span class="badge badge-gray">' . t_admin('adm_disable', '禁用') . '</span>' ?></td>
                <td>
                    <a href="<?= htmlspecialchars(admin_url('ads', ['edit' => (int)$ad['id']])) ?>" class="btn btn-sm btn-outline" title="<?= t_admin('adm_edit', '编辑') ?>"><i class="fas fa-edit"></i></a>
                    <a href="<?= htmlspecialchars(admin_url('ads', ['delete' => (int)$ad['id'], '_csrf' => Security::csrfToken()])) ?>" class="btn btn-sm btn-danger" data-confirm="<?= t_admin('adm_confirm_delete', '确认删除？') ?>"><i class="fas fa-trash"></i></a>
                    <?php Hook::doAction('admin_ads_row_actions', $ad); ?>
                </td>
                <?= PluginTable::renderCells('admin.ads', $ad) ?>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($ads)): ?><tr><td colspan="<?= 6 + PluginTable::count('admin.ads') ?>" class="empty"><?= t_admin('adm_ads_empty', '暂无广告') ?></td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<div class="card" style="margin-top:18px">
    <h2><?= t_admin('adm_ads_keyword_title', '关键词自动链接（章节页生效）') ?></h2>
    <form method="post">
        <?= Security::csrfField() ?>
        <div class="form-group">
            <label><?= t_admin('adm_ads_keyword_rule', '规则（每行一个，格式：关键词|URL）') ?></label>
            <textarea name="keyword_links" class="form-control" rows="7" style="max-width:700px" placeholder="<?= t_admin('adm_ads_keyword_links_ph', '小说网|https://example.com') ?>"><?= htmlspecialchars(SettingModel::get('keyword_links', '')) ?></textarea>
        </div>
        <button type="submit" name="save_keyword_links" class="btn btn-primary"><i class="fas fa-save"></i> <?= t_admin('adm_ads_keyword_save', '保存链接规则') ?></button>
    </form>
</div>
<style>
@media (max-width: 900px) { .ad-layout { grid-template-columns:1fr !important; } }
</style>
