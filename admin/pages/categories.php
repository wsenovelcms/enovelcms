<?php

if (!defined('ADMIN_BOOT')) exit;
$pageTitle = t_admin('adm_categories', '分类管理');

$allCats = CategoryModel::getAllCategories();
$topCats = array();
$childMap = array();
foreach ($allCats as $c) {
    if ((int)$c['parent_id'] <= 0) { $topCats[(int)$c['id']] = $c; }
    else { $childMap[(int)$c['parent_id']][] = $c; }
}

$editCat = null;
if (g('edit', null, 'raw') !== null) {
    $eid = g('edit', 0, 'int');
    foreach ($allCats as $c) { if ((int)$c['id'] === $eid) { $editCat = $c; break; } }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCsrf()) {
        $error = t_admin('adm_cat_error_csrf', 'CSRF验证失败');
    } else {
        $cid = Security::post('id', 0, 'int');
        $name = p('name', '', 'str');
        $parentId = Security::post('parent_id', 0, 'int');
        $sort = Security::post('sort', 0, 'int');
        if ($name === '') {
            $error = t_admin('adm_cat_name_empty', '分类名称不能为空');
        } elseif ($parentId === $cid && $cid > 0) {
            $error = t_admin('adm_cat_self_parent', '上级分类不能选择自己');
        } else {
            if ($cid > 0) {
                CategoryModel::update(array('name' => $name, 'parent_id' => $parentId, 'sort' => $sort), array('id' => $cid));
                PluginForm::save('category_edit', 'category', $cid);
                admin_flash_redirect(t_admin('adm_cat_updated', '分类已更新'), 'categories');
            } else {
                $cid = (int)CategoryModel::addCategory($name, $parentId, $sort);
                if ($cid > 0) PluginForm::save('category_edit', 'category', $cid);
                admin_flash_redirect(t_admin('adm_cat_added', '分类已添加'), 'categories');
            }
        }
    }
}

if (g('delete', null, 'raw') !== null) {
    $did = g('delete', 0, 'int');
    if (Security::verifyCsrf()) {
        $hasChild = CategoryModel::find(array('parent_id' => $did));
        if ($hasChild) {
            $error = t_admin('adm_cat_has_child', '该分类下还有子分类，请先处理子分类');
        } else {
            CategoryModel::delete(array('id' => $did));
            admin_flash_redirect(t_admin('adm_cat_deleted', '分类已删除'), 'categories');
        }
    }
}
?>
<div class="card">
    <h2><?= t_admin('adm_cat_title', '分类管理（支持二级分类）') ?></h2>
    <?php if ($error): ?><div class="alert alert-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post" style="margin-bottom:24px">
        <?= Security::csrfField() ?>
        <input type="hidden" name="id" value="<?= (int)($editCat['id'] ?? 0) ?>">
        <div class="form-row-2" style="max-width:760px">
            <div class="form-group"><label><?= t_admin('adm_cat_name_label', '分类名称') ?></label><input type="text" name="name" class="form-control" value="<?= htmlspecialchars((string)$editCat['name'] ?? '') ?>" required></div>
            <div class="form-group">
                <label><?= t_admin('adm_cat_parent', '上级分类') ?></label>
                <select name="parent_id" class="form-control">
                    <option value="0"><?= t_admin('adm_cat_top', '— 顶级分类 —') ?></option>
                    <?php foreach ($topCats as $tc):
                        if ($editCat && (int)$tc['id'] === (int)$editCat['id']) continue; ?>
                    <option value="<?= (int)$tc['id'] ?>" <?= (int)($editCat['parent_id'] ?? 0) === (int)$tc['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$tc['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group" style="max-width:200px"><label><?= t_admin('adm_cat_sort', '排序') ?></label><input type="number" name="sort" class="form-control" value="<?= (int)($editCat['sort'] ?? 0) ?>"></div>
        <?= PluginForm::render('category_edit', 'category', (int)($editCat['id'] ?? 0)) ?>
        <button class="btn btn-primary"><?= $editCat ? t_admin('adm_save_change', '保存修改') : t_admin('adm_cat_add_btn', '添加分类') ?></button>
        <?php if ($editCat): ?><a href="<?= htmlspecialchars(admin_url('categories')) ?>" class="btn btn-outline"><?= t_admin('adm_cancel', '取消') ?></a><?php endif; ?>
    </form>

    <div class="table-wrap">
    <table class="data">
        <thead><tr><th>ID</th><th><?= t_admin('adm_cat_col_name', '名称') ?></th><th><?= t_admin('adm_cat_col_type', '类型') ?></th><th><?= t_admin('adm_cat_sort', '排序') ?></th><th><?= t_admin('adm_operation', '操作') ?></th><?= PluginTable::renderHead('admin.categories') ?></tr></thead>
        <tbody>
        <?php foreach ($topCats as $tc): ?>
        <tr>
            <td><?= (int)$tc['id'] ?></td>
            <td><strong><?= htmlspecialchars((string)$tc['name']) ?></strong></td>
            <td><span class="badge badge-blue"><?= t_admin('adm_cat_badge_top', '顶级') ?></span></td>
            <td><?= (int)$tc['sort'] ?></td>
            <td style="display:flex;gap:6px">
                <a href="<?= htmlspecialchars(admin_url('categories', ['edit' => (int)$tc['id']])) ?>" class="btn btn-sm btn-warning" title="<?= t_admin('adm_edit', '编辑') ?>"><i class="fas fa-edit"></i></a>
                <a href="<?= htmlspecialchars(admin_url('categories', ['delete' => (int)$tc['id'], '_csrf' => Security::csrfToken()])) ?>" class="btn btn-sm btn-danger" data-confirm="<?= t_admin('adm_confirm_delete', '确认删除？') ?>"><i class="fas fa-trash"></i></a>
            </td>
            <?= PluginTable::renderCells('admin.categories', $tc) ?>
        </tr>
        <?php foreach ($childMap[(int)$tc['id']] ?? array() as $cc): ?>
        <tr>
            <td><?= (int)$cc['id'] ?></td>
            <td style="padding-left:28px">└ <?= htmlspecialchars((string)$cc['name']) ?></td>
            <td><span class="badge badge-gray"><?= t_admin('adm_cat_badge_child', '子分类') ?></span></td>
            <td><?= (int)$cc['sort'] ?></td>
            <td style="display:flex;gap:6px">
                <a href="<?= htmlspecialchars(admin_url('categories', ['edit' => (int)$cc['id']])) ?>" class="btn btn-sm btn-warning" title="<?= t_admin('adm_edit', '编辑') ?>"><i class="fas fa-edit"></i></a>
                <a href="<?= htmlspecialchars(admin_url('categories', ['delete' => (int)$cc['id'], '_csrf' => Security::csrfToken()])) ?>" class="btn btn-sm btn-danger" data-confirm="<?= t_admin('adm_confirm_delete', '确认删除？') ?>"><i class="fas fa-trash"></i></a>
                <?php Hook::doAction('admin_categories_row_actions', $cc); ?>
            </td>
            <?= PluginTable::renderCells('admin.categories', $cc) ?>
        </tr>
        <?php endforeach; ?>
        <?php endforeach; ?>
        <?php if (empty($topCats)): ?><tr><td colspan="<?= 5 + PluginTable::count('admin.categories') ?>" class="empty"><?= t_admin('adm_cat_empty', '暂无分类') ?></td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
