<?php

if (!defined('ADMIN_BOOT')) exit;
$pageTitle = t_admin('adm_authors', '作家管理');

if (g('revoke', null, 'raw') !== null) {
    if (Security::verifyCsrf()) {
        UserModel::update(array('is_author' => 0), array('id' => g('revoke', 0, 'int'), 'is_author' => 1));
        admin_flash_redirect(t_admin('adm_author_revoke_done', '已取消该用户的作者身份'), 'authors');
    }
}

$keyword = g('search', '', 'str');
$page = max(1, g('page', 1, 'int'));
$result = UserModel::adminAuthorPaginate($keyword, $page, 20);
?>
<div class="card">
    <h2><?= t_admin('adm_author_list', '作家列表（共 %s 位）', number_format($result['total'])) ?></h2>
    <form method="get" style="margin-bottom:16px;display:flex;gap:8px;max-width:420px">
        <input type="hidden" name="action" value="authors">
        <input type="text" name="search" value="<?= htmlspecialchars($keyword) ?>" class="form-control" placeholder="<?= t_admin('adm_author_search_ph', '搜索用户名 / 笔名') ?>">
        <button class="btn btn-outline" type="submit"><i class="fas fa-search"></i></button>
    </form>
    <div class="table-wrap">
    <table class="data">
        <thead><tr><th>ID</th><th><?= t_admin('adm_author_col_username', '用户名') ?></th><th><?= t_admin('adm_author_col_penname', '笔名') ?></th><th><?= t_admin('adm_author_col_work_count', '作品数') ?></th><th><?= t_admin('adm_author_col_sales_count', '销售章节数') ?></th><th><?= t_admin('adm_author_col_gold', '金币') ?></th><th><?= t_admin('adm_author_col_register', '注册时间') ?></th><th><?= t_admin('adm_operation', '操作') ?></th><?= PluginTable::renderHead('admin.authors') ?></tr></thead>
        <tbody>
        <?php foreach ($result['list'] as $a): ?>
        <tr>
            <td><?= (int)$a['id'] ?></td>
            <td><?= htmlspecialchars((string)$a['username']) ?></td>
            <td><?= htmlspecialchars($a['pen_name'] ?: '-') ?></td>
            <td><?= (int)$a['work_count'] ?></td>
            <td><?= (int)$a['sales_count'] ?></td>
            <td><?= number_format((int)$a['gold']) ?></td>
            <td><?= htmlspecialchars($a['created_at']) ?></td>
            <td style="display:flex;gap:6px">
                <a href="<?= htmlspecialchars(admin_url('user_edit', ['id' => (int)$a['id']])) ?>" class="btn btn-sm btn-warning" title="<?= t_admin('adm_edit', '编辑') ?>"><i class="fas fa-edit"></i></a>
                <a href="<?= htmlspecialchars(admin_url('authors', ['revoke' => (int)$a['id'], '_csrf' => Security::csrfToken()])) ?>" class="btn btn-sm btn-danger" data-confirm="<?= t_admin('adm_author_revoke_confirm', '确认取消该作者身份？') ?>"><i class="fas fa-user-slash"></i></a>
                <?php Hook::doAction('admin_authors_row_actions', $a); ?>
            </td>
            <?= PluginTable::renderCells('admin.authors', $a) ?>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($result['list'])): ?><tr><td colspan="<?= 8 + PluginTable::count('admin.authors') ?>" class="empty"><?= t_admin('adm_author_empty', '暂无作家') ?></td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
    <?= admin_pagination($result['page'], $result['totalPages'], array('action' => 'authors', 'search' => $keyword)) ?>
</div>
