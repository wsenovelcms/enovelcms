<?php

if (!defined('ADMIN_BOOT')) exit;
$pageTitle = t_admin('adm_novels', '小说管理');

$novelService = new NovelService();

if (g('delete', null, 'raw') !== null) {
    if (Security::verifyCsrf()) {
        $novelService->deleteNovel(g('delete', 0, 'int'));
        admin_flash_redirect(t_admin('adm_novel_deleted', '小说已删除'), 'novels');
    }
    admin_flash_redirect(t_admin('adm_security_failed', '安全验证失败'), 'novels');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && p('batch_delete', null, 'raw') !== null) {
    if (Security::verifyCsrf()) {
        $rawIds = is_array($_POST['ids'] ?? null) ? $_POST['ids'] : [];
        $ids = [];
        foreach ($rawIds as $id) { $id = (int)$id; if ($id > 0) $ids[] = $id; }
        $ids = array_values(array_unique($ids));
        if (empty($ids)) {
            admin_flash_redirect(t_admin('adm_batch_select_none', '请先选择要删除的项'), 'novels');
        }
        foreach ($ids as $id) { $novelService->deleteNovel($id); }
        admin_flash_redirect(t_admin('adm_batch_deleted_fmt', '已批量删除 %d 项', count($ids)), 'novels');
    }
    admin_flash_redirect(t_admin('adm_security_failed', '安全验证失败'), 'novels');
}

$keyword = g('kw', '', 'str');
$page = max(1, (int)($_GET['page'] ?? 1));
$result = NovelModel::adminPaginate($keyword, $page, 20);
$novels = $result['list'];
?>
<div class="card">
    <h2>
        <?= t_admin('adm_novel_list', '小说列表') ?>
        <span class="h2-actions">
            <a href="<?= htmlspecialchars(admin_url('novel_edit')) ?>" class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> <?= t_admin('adm_novel_add', '添加小说') ?></a>
        </span>
    </h2>
    <form method="get" style="margin-bottom:16px;display:flex;gap:8px;max-width:420px">
        <input type="hidden" name="action" value="novels">
        <input type="text" name="kw" value="<?= htmlspecialchars($keyword) ?>" class="form-control" placeholder="<?= t_admin('adm_novel_search_ph', '搜索书名 / 作者') ?>">
        <button class="btn btn-outline" type="submit"><i class="fas fa-search"></i></button>
    </form>
    <div class="table-wrap">
    <form method="post">
        <?= Security::csrfField() ?>
        <div style="margin-bottom:10px">
            <button type="submit" name="batch_delete" value="1" class="btn btn-sm btn-danger" data-confirm="<?= t_admin('adm_batch_delete_confirm', '确认删除选中项？') ?>"><i class="fas fa-trash"></i> <?= t_admin('adm_batch_delete', '批量删除') ?></button>
        </div>
    <table class="data">
        <thead><tr><th style="width:38px"><input type="checkbox" data-check-all="novels" aria-label="check"></th><th>ID</th><th><?= t_admin('adm_novel_col_title', '书名') ?></th><th><?= t_admin('adm_novel_col_author', '作者') ?></th><th><?= t_admin('adm_novel_col_category', '分类') ?></th><th><?= t_admin('adm_novel_col_words', '字数') ?></th><th><?= t_admin('adm_novel_col_views', '点击') ?></th><th><?= t_admin('adm_novel_col_vip', 'VIP') ?></th><th><?= t_admin('adm_status', '状态') ?></th><th><?= t_admin('adm_operation', '操作') ?></th><?= PluginTable::renderHead('admin.novels') ?></tr></thead>
        <tbody>
        <?php foreach ($novels as $n): ?>
        <tr>
            <td><input type="checkbox" name="ids[]" value="<?= (int)$n['id'] ?>" data-check-item="novels"></td>
            <td><?= (int)$n['id'] ?></td>
            <td><?= htmlspecialchars((string)$n['title']) ?></td>
            <td><?= htmlspecialchars((string)$n['author']) ?></td>
            <td><?= htmlspecialchars($n['cat'] ?? '-') ?></td>
            <td><?= number_format((int)$n['total_words']) ?></td>
            <td><?= number_format((int)$n['views']) ?></td>
            <td><?= $n['is_vip'] ? '<span class="badge badge-orange">VIP</span>' : '<span class="badge badge-gray">' . t_admin('adm_free', '免费') . '</span>' ?></td>
            <td><?= $n['status'] ? '<span class="badge badge-green">' . t_admin('adm_finished', '完结') . '</span>' : '<span class="badge badge-blue">' . t_admin('adm_serial', '连载') . '</span>' ?></td>
            <td style="display:flex;gap:6px">
                <a href="<?= htmlspecialchars(admin_url('chapters', ['novel_id' => (int)$n['id']])) ?>" class="btn btn-sm btn-outline" title="<?= t_admin('adm_novel_chapters', '章节') ?>"><i class="fas fa-list"></i></a>
                <a href="<?= htmlspecialchars(admin_url('volumes', ['novel_id' => (int)$n['id']])) ?>" class="btn btn-sm btn-outline" title="<?= t_admin('adm_novel_volumes', '分卷') ?>"><i class="fas fa-layer-group"></i></a>
                <a href="<?= htmlspecialchars(admin_url('txt_import', ['novel_id' => (int)$n['id']])) ?>" class="btn btn-sm btn-outline" title="<?= t_admin('adm_txt_import', 'TXT导入') ?>"><i class="fas fa-file-alt"></i></a>
                <a href="<?= htmlspecialchars(admin_url('novel_edit', ['id' => (int)$n['id']])) ?>" class="btn btn-sm btn-warning" title="<?= t_admin('adm_edit', '编辑') ?>"><i class="fas fa-edit"></i></a>
                <a href="<?= htmlspecialchars(admin_url('novels', ['delete' => (int)$n['id'], '_csrf' => Security::csrfToken()])) ?>" class="btn btn-sm btn-danger" data-confirm="<?= t_admin('adm_novel_delete_confirm', '确认删除该小说及其章节？') ?>"><i class="fas fa-trash"></i></a>
                <?php Hook::doAction('admin_novels_row_actions', $n); ?>
            </td>
            <?= PluginTable::renderCells('admin.novels', $n) ?>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($novels)): ?><tr><td colspan="<?= 10 + PluginTable::count('admin.novels') ?>" class="empty"><?= t_admin('adm_novel_empty', '暂无小说') ?></td></tr><?php endif; ?>
        </tbody>
    </table>
    </form>
    </div>
    <?= admin_pagination($result['page'], $result['totalPages'], array('action' => 'novels', 'kw' => $keyword)) ?>
</div>
