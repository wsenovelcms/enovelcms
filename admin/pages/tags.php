<?php

if (!defined('ADMIN_BOOT')) exit;
$pageTitle = t_admin('adm_tags', '标签管理');

$editTag = null;
if (g('edit', null, 'raw') !== null) {
    $editTag = TagModel::findById(g('edit', 0, 'int'));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCsrf()) {
        $error = t_admin('adm_csrf_failed', 'CSRF 验证失败，请刷新页面重试');
    } else {
        $formType = p('form_type', '', 'str');
        if ($formType === 'max_setting') {
            $maxValue = Security::post('novel_tag_max', 0, 'int');
            if ($maxValue < 1) {
                $error = t_admin('adm_tag_max_invalid', '请输入不小于 1 的标签数量');
            } else {
                SettingModel::set('novel_tag_max', (string)$maxValue);
                admin_flash_redirect(t_admin('adm_tag_max_saved', '标签数量上限已保存'), 'tags');
            }
        } else {
            $tid = Security::post('id', 0, 'int');
            $name = p('name', '', 'str');
            $sort = Security::post('sort', 0, 'int');
            if ($name === '') {
                $error = t_admin('adm_tag_name_empty', '标签名称不能为空');
            } elseif (TagModel::nameExists($name, $tid)) {
                $error = t_admin('adm_tag_name_exists', '标签名称已存在');
            } else {
                if ($tid > 0) {
                    TagModel::updateTag($tid, $name, $sort);
                    admin_flash_redirect(t_admin('adm_tag_updated', '标签已更新'), 'tags');
                } else {
                    TagModel::addTag($name, $sort);
                    admin_flash_redirect(t_admin('adm_tag_added', '标签已添加'), 'tags');
                }
            }
        }
    }
}

if (g('delete', null, 'raw') !== null && Security::verifyCsrf()) {
    TagModel::deleteTag(g('delete', 0, 'int'));
    admin_flash_redirect(t_admin('adm_tag_deleted', '标签已删除'), 'tags');
}

$page = max(1, g('page', 1, 'int'));
$perPage = 20;
$paged = TagModel::paginate(array(), 'sort ASC, id ASC', $page, $perPage, 'id, name, sort');
$allTags = $paged['list'];
$usageCounts = TagModel::getUsageCounts();
$currentMax = TagModel::getMaxTags();
?>
<div class="card">
    <h2><?= t_admin('adm_tag_title', '标签管理') ?></h2>
    <?php if ($error): ?><div class="alert alert-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="tag-bar">
        <form method="post" class="tag-bar-max">
            <?= Security::csrfField() ?>
            <input type="hidden" name="form_type" value="max_setting">
            <div class="inline-form">
                <div class="if-field">
                    <label><?= t_admin('adm_tag_max_setting', '每部小说标签数量上限') ?></label>
                    <input type="number" name="novel_tag_max" class="form-control" style="width:96px" min="1" value="<?= (int)$currentMax ?>" required>
                </div>
                <button class="btn btn-primary"><?= t_admin('adm_save', '保存') ?></button>
            </div>
        </form>

        <form method="post" class="tag-bar-edit">
            <?= Security::csrfField() ?>
            <input type="hidden" name="form_type" value="tag_edit">
            <input type="hidden" name="id" value="<?= (int)($editTag['id'] ?? 0) ?>">
            <div class="inline-form">
                <div class="if-field">
                    <label><?= t_admin('adm_tag_name_label', '标签名称') ?></label>
                    <input type="text" name="name" class="form-control" style="width:200px" value="<?= htmlspecialchars((string)($editTag['name'] ?? '')) ?>" maxlength="50" required>
                </div>
                <div class="if-field">
                    <label><?= t_admin('adm_tag_sort', '排序') ?></label>
                    <input type="number" name="sort" class="form-control" style="width:110px" value="<?= (int)($editTag['sort'] ?? 0) ?>">
                </div>
                <button class="btn btn-primary"><?= $editTag ? t_admin('adm_save_change', '保存修改') : t_admin('adm_tag_add_btn', '添加标签') ?></button>
                <?php if ($editTag): ?><a href="<?= htmlspecialchars(admin_url('tags')) ?>" class="btn btn-outline"><?= t_admin('adm_cancel', '取消') ?></a><?php endif; ?>
            </div>
        </form>
    </div>

    <div class="table-wrap">
    <table class="data">
        <thead><tr><th>ID</th><th><?= t_admin('adm_tag_col_name', '标签名称') ?></th><th><?= t_admin('adm_tag_sort', '排序') ?></th><th><?= t_admin('adm_tag_col_usage', '关联小说') ?></th><th><?= t_admin('adm_operation', '操作') ?></th></tr></thead>
        <tbody>
        <?php foreach ($allTags as $tag): ?>
        <tr>
            <td><?= (int)$tag['id'] ?></td>
            <td><strong><?= htmlspecialchars((string)$tag['name']) ?></strong></td>
            <td><?= (int)$tag['sort'] ?></td>
            <td><?= (int)($usageCounts[(int)$tag['id']] ?? 0) ?></td>
            <td style="display:flex;gap:6px">
                <a href="<?= htmlspecialchars(admin_url('tags', ['edit' => (int)$tag['id']])) ?>" class="btn btn-sm btn-warning" title="<?= t_admin('adm_edit', '编辑') ?>"><i class="fas fa-edit"></i></a>
                <a href="<?= htmlspecialchars(admin_url('tags', ['delete' => (int)$tag['id'], '_csrf' => Security::csrfToken()])) ?>" class="btn btn-sm btn-danger" data-confirm="<?= t_admin('adm_confirm_delete', '确认删除？') ?>"><i class="fas fa-trash"></i></a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($allTags)): ?><tr><td colspan="5" class="empty"><?= t_admin('adm_tag_empty', '暂无标签') ?></td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
    <?= admin_pagination((int)$paged['page'], (int)$paged['totalPages'], array('action' => 'tags')) ?>
</div>
