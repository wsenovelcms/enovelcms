<?php

if (!defined('ADMIN_BOOT')) exit;
$pageTitle = t_admin('adm_users', '用户管理');

$keyword = g('kw', '', 'str');
$page = max(1, (int)($_GET['page'] ?? 1));

if ($keyword !== '') {
    $kw = '%' . $keyword . '%';
    $t = UserModel::tableName();
    $offset = ($page - 1) * 20;
    $cntRow = UserModel::db()->query("SELECT COUNT(*) AS cnt FROM {$t} WHERE username LIKE ? OR email LIKE ?", array($kw, $kw))->fetch();
    $total = (int)($cntRow['cnt'] ?? 0);
    $users = UserModel::db()->query(
        "SELECT id, username, email, vip_level, gold, created_at FROM {$t} WHERE username LIKE ? OR email LIKE ? ORDER BY id DESC LIMIT {$offset}, 20",
        array($kw, $kw)
    )->fetchAll();
    $users = UserModel::normalizeRows($users);
    $totalPages = (int)ceil($total / 20);
} else {
    $result = UserModel::paginate(array(), 'id DESC', $page, 20);
    $users = $result['list'];
    $total = $result['total'];
    $totalPages = $result['totalPages'];
}
?>
<div class="card">
    <h2><?= t_admin('adm_user_list_count', '用户列表（共 %s 位）', number_format($total)) ?></h2>
    <form method="get" style="margin-bottom:16px;display:flex;gap:8px;max-width:420px">
        <input type="hidden" name="action" value="users">
        <input type="text" name="kw" value="<?= htmlspecialchars($keyword) ?>" class="form-control" placeholder="<?= t_admin('adm_user_search_ph', '搜索用户名 / 邮箱') ?>">
        <button class="btn btn-outline" type="submit"><i class="fas fa-search"></i></button>
    </form>
    <div class="table-wrap">
    <table class="data">
        <thead><tr><th>ID</th><th><?= t_admin('adm_user_col_username', '用户名') ?></th><th><?= t_admin('adm_user_col_email', '邮箱') ?></th><th>VIP</th><th><?= t_admin('adm_user_col_gold', '金币') ?></th><th><?= t_admin('adm_user_col_author', '作者') ?></th><th><?= t_admin('adm_user_col_register', '注册时间') ?></th><th><?= t_admin('adm_operation', '操作') ?></th><?= PluginTable::renderHead('admin.users') ?></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
            <td><?= (int)$u['id'] ?></td>
            <td><?= htmlspecialchars((string)$u['username']) ?></td>
            <td><?= htmlspecialchars($u['email'] ?: '-') ?></td>
            <td><?= $u['vip_level'] ? '<span class="badge badge-orange">VIP</span>' : '<span class="badge badge-gray">' . t_admin('adm_no', '否') . '</span>' ?></td>
            <td><?= number_format((int)$u['gold']) ?></td>
            <td><?= UserModel::isAuthor((int)$u['id']) ? t_admin('adm_yes', '是') : t_admin('adm_no', '否') ?></td>
            <td><?= htmlspecialchars($u['created_at']) ?></td>
            <td>
                <a href="<?= htmlspecialchars(admin_url('user_edit', ['id' => (int)$u['id']])) ?>" class="btn btn-sm btn-warning" title="<?= t_admin('adm_edit', '编辑') ?>"><i class="fas fa-edit"></i></a>
                <?php Hook::doAction('admin_users_row_actions', $u); ?>
            </td>
            <?= PluginTable::renderCells('admin.users', $u) ?>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($users)): ?><tr><td colspan="<?= 8 + PluginTable::count('admin.users') ?>" class="empty"><?= t_admin('adm_user_empty', '暂无用户') ?></td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
    <?= admin_pagination($page, $totalPages, array('action' => 'users', 'kw' => $keyword)) ?>
</div>
