<?php

if (!defined('ADMIN_BOOT')) exit;
$pageTitle = t_admin('adm_announcements', '公告管理');

if (g('toggle', null, 'raw') !== null && Security::verifyCsrf()) {
    $row = AnnouncementModel::findById(g('toggle', 0, 'int'));
    if ($row) {
        AnnouncementModel::update(['status' => $row['status'] ? 0 : 1], ['id' => (int)$row['id']]);
        admin_flash_redirect($row['status'] ? t_admin('adm_ann_toggle_off', '已停用') : t_admin('adm_ann_toggle_on', '已启用'), 'announcements');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && p('save_ann', null, 'raw') !== null) {
    if (Security::verifyCsrf()) {
        $id = p('id', 0, 'int');
        $expires = trim(p('expires_at', '', 'text'));
        $link = trim(p('link', '', 'text'));
        if ($link !== '' && !preg_match('~^(https?://|/(?!/)|#)~', $link)) {
            $link = '';
        }
        $data = [
            'title'   => trim(p('title', '', 'text')),
            'content' => trim(p('content', '', 'text')),
            'link'    => $link,
            'sort'    => p('sort', 0, 'int'),
            'status'  => (int)!empty($_POST['status']),
            'expires_at' => $expires ?: null,
        ];
        if ($data['title'] === '') {
            admin_flash_redirect(t_admin('adm_ann_title_empty', '标题不能为空'), 'announcements');
        }
        if ($id) {
            AnnouncementModel::update($data, ['id' => $id]);
        } else {
            $id = (int)AnnouncementModel::create($data);
        }
        if ($id) PluginForm::save('announcement_edit', 'announcement', (int)$id);
        admin_flash_redirect(t_admin('adm_ann_saved', '公告已保存'), 'announcements');
    }
}
if (g('delete', null, 'raw') !== null && Security::verifyCsrf()) {
    AnnouncementModel::delete(['id' => g('delete', 0, 'int')]);
    admin_flash_redirect(t_admin('adm_ann_deleted', '公告已删除'), 'announcements');
}

$editRow = null;
if (g('edit', null, 'raw') !== null) {
    $editRow = AnnouncementModel::findById(g('edit', 0, 'int'));
}
$page = g('page', null, 'raw') !== null ? max(1, g('page', 0, 'int')) : 1;
$pg = AnnouncementModel::adminPaginate($page, 15);
$list = $pg['list'];
?>
<div style="display:grid;grid-template-columns:1fr 1.5fr;gap:18px">
    <div class="card" style="margin-bottom:0">
        <h2><?= $editRow ? t_admin('adm_ann_edit', '编辑公告') : t_admin('adm_ann_add', '添加公告') ?></h2>
        <form method="post">
            <?= Security::csrfField() ?>
            <input type="hidden" name="save_ann" value="1">
            <input type="hidden" name="id" value="<?= $editRow ? (int)$editRow['id'] : 0 ?>">
            <div class="form-group"><label><?= t_admin('adm_ann_label_title', '标题 *') ?></label><input type="text" name="title" class="form-control" required value="<?= $editRow ? htmlspecialchars((string)$editRow['title']) : '' ?>"></div>
            <div class="form-group"><label><?= t_admin('adm_ann_content', '内容') ?></label><textarea name="content" rows="4" class="form-control"><?= $editRow ? htmlspecialchars((string)$editRow['content']) : '' ?></textarea></div>
            <div class="form-group"><label><?= t_admin('adm_ann_link', '跳转链接（可选）') ?></label><input type="text" name="link" class="form-control" value="<?= $editRow ? htmlspecialchars($editRow['link']) : '' ?>" placeholder="https://..."></div>
            <div class="form-row-2">
                <div class="form-group"><label><?= t_admin('adm_ann_sort', '排序（小在前）') ?></label><input type="number" name="sort" class="form-control" value="<?= $editRow ? (int)$editRow['sort'] : 0 ?>"></div>
                <div class="form-group"><label><?= t_admin('adm_ann_expires', '到期时间（空=永久）') ?></label><input type="datetime-local" name="expires_at" class="form-control" value="<?= $editRow && $editRow['expires_at'] ? date('Y-m-d\TH:i', strtotime($editRow['expires_at'])) : '' ?>"></div>
            </div>
            <div class="form-group"><label style="display:flex;align-items:center;gap:6px"><input type="checkbox" name="status" value="1" <?= (!$editRow || !empty($editRow['status'])) ? 'checked' : '' ?>> <?= t_admin('adm_enable', '启用') ?></label></div>
            <?= PluginForm::render('announcement_edit', 'announcement', $editRow ? (int)$editRow['id'] : 0) ?>
            <button class="btn btn-primary"><i class="fas fa-save"></i> <?= t_admin('adm_save', '保存') ?></button>
            <?php if ($editRow): ?><a href="<?= htmlspecialchars(admin_url('announcements')) ?>" class="btn btn-outline"><?= t_admin('adm_cancel', '取消') ?></a><?php endif; ?>
        </form>
    </div>
    <div class="card" style="margin-bottom:0">
        <h2><?= t_admin('adm_ann_list', '公告列表（%d）', (int)$pg['total']) ?></h2>
        <div class="table-wrap">
        <table class="data">
            <thead><tr><th>ID</th><th><?= t_admin('adm_ann_col_title', '标题') ?></th><th><?= t_admin('adm_ann_sort', '排序') ?></th><th><?= t_admin('adm_status', '状态') ?></th><th><?= t_admin('adm_ann_col_expires', '到期时间') ?></th><th><?= t_admin('adm_operation', '操作') ?></th><?= PluginTable::renderHead('admin.announcements') ?></tr></thead>
            <tbody>
            <?php foreach ($list as $a):
                $expired = !empty($a['expires_at']) && strtotime($a['expires_at']) < time();
            ?>
            <tr>
                <td><?= (int)$a['id'] ?></td>
                <td><?= htmlspecialchars((string)$a['title']) ?></td>
                <td><?= (int)$a['sort'] ?></td>
                <td>
                    <?php if ($expired): ?>
                        <span style="color:#999"><?= t_admin('adm_ann_expired', '已过期') ?></span>
                    <?php elseif (!empty($a['status'])): ?>
                        <span style="color:#16a34a"><?= t_admin('adm_enable', '启用') ?></span>
                    <?php else: ?>
                        <span style="color:#999"><?= t_admin('adm_ann_invalid', '停用') ?></span>
                    <?php endif; ?>
                </td>
                <td><?= $a['expires_at'] ? htmlspecialchars($a['expires_at']) : '<span style="color:#bbb">' . t_admin('adm_ann_forever', '永久') . '</span>' ?></td>
                <td style="white-space:nowrap">
                    <a href="<?= htmlspecialchars(admin_url('announcements', ['edit' => (int)$a['id']])) ?>" class="btn btn-sm btn-outline" title="<?= t_admin('adm_edit', '编辑') ?>"><i class="fas fa-edit"></i></a>
                    <a href="<?= htmlspecialchars(admin_url('announcements', ['toggle' => (int)$a['id'], '_csrf' => Security::csrfToken()])) ?>" class="btn btn-sm <?= !empty($a['status']) ? 'btn-warning' : 'btn-success' ?>" title="<?= !empty($a['status']) ? t_admin('adm_disable', '禁用') : t_admin('adm_enable', '启用') ?>">
                        <i class="fas fa-<?= !empty($a['status']) ? 'pause' : 'play' ?>"></i>
                    </a>
                    <a href="<?= htmlspecialchars(admin_url('announcements', ['delete' => (int)$a['id'], '_csrf' => Security::csrfToken()])) ?>" class="btn btn-sm btn-danger" data-confirm="<?= t_admin('adm_confirm_delete', '确认删除？') ?>"><i class="fas fa-trash"></i></a>
                    <?php Hook::doAction('admin_announcements_row_actions', $a); ?>
                </td>
                <?= PluginTable::renderCells('admin.announcements', $a) ?>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($list)): ?><tr><td colspan="<?= 6 + PluginTable::count('admin.announcements') ?>" class="empty"><?= t_admin('adm_ann_empty', '暂无公告') ?></td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
        <?= admin_pagination($pg['page'], $pg['totalPages'], ['action' => 'announcements']) ?>
    </div>
</div>
