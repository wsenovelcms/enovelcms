<?php

if (!defined('ADMIN_BOOT')) exit;
$pageTitle = t_admin('adm_viprev', 'VIP签约审核');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCsrf()) {
        admin_flash_redirect(t_admin('adm_csrf_failed_short', 'CSRF验证失败'), 'vip_review');
    }
    $nid = Security::post('nid', 0, 'int');
    if (p('approve', null, 'raw') !== null) {
        $affected = NovelModel::update(array('is_vip' => 1, 'vip_audit_status' => 1), array('id' => $nid, 'vip_audit_status' => 0));
        admin_flash_redirect($affected > 0 ? t_admin('adm_viprev_approved', '已通过签约申请') : t_admin('adm_viprev_duplicate', '该作品已处理，不能重复审核'), 'vip_review');
    } elseif (p('reject', null, 'raw') !== null) {
        $affected = NovelModel::update(array('vip_audit_status' => 2), array('id' => $nid, 'vip_audit_status' => 0));
        admin_flash_redirect($affected > 0 ? t_admin('adm_viprev_rejected', '已拒绝签约申请') : t_admin('adm_viprev_duplicate', '该作品已处理，不能重复审核'), 'vip_review');
    }
    admin_flash_redirect('', 'vip_review');
}

$pending = NovelModel::all(array('vip_audit_status' => 0), 'id DESC');
$approvedCount = NovelModel::count(array('vip_audit_status' => 1));
$rejectedCount = NovelModel::count(array('vip_audit_status' => 2));
?>
<div class="card">
    <h2><?= t_admin('adm_viprev', 'VIP签约审核') ?></h2>
    <p class="text-muted" style="margin-bottom:14px">
        <?= t_admin('adm_viprev_summary', '待审 <strong class="text-orange">%s</strong> 本 · 已通过 <strong class="text-green">%s</strong> · 已拒绝 <strong class="text-red">%s</strong>', count($pending), $approvedCount, $rejectedCount) ?>
    </p>
    <div class="table-wrap">
    <table class="data">
        <thead><tr><th>ID</th><th><?= t_admin('adm_viprev_col_title', '标题') ?></th><th><?= t_admin('adm_viprev_col_author', '作者') ?></th><th><?= t_admin('adm_viprev_col_category', '分类') ?></th><th><?= t_admin('adm_viprev_col_words', '字数') ?></th><th><?= t_admin('adm_status', '状态') ?></th><th><?= t_admin('adm_operation', '操作') ?></th><?= PluginTable::renderHead('admin.vip_review') ?></tr></thead>
        <tbody>
        <?php foreach ($pending as $n):
            $cat = CategoryModel::findById((int)$n['category_id']); ?>
        <tr>
            <td><?= (int)$n['id'] ?></td>
            <td><?= htmlspecialchars((string)$n['title']) ?></td>
            <td><?= htmlspecialchars((string)$n['author']) ?></td>
            <td><?= htmlspecialchars((string)$cat['name'] ?? '-') ?></td>
            <td><?= number_format((int)$n['total_words']) ?></td>
            <td><?= $n['status'] ? t_admin('adm_finished', '完结') : t_admin('adm_serial', '连载') ?></td>
            <td>
                <form method="post" style="display:inline">
                    <?= Security::csrfField() ?>
                    <input type="hidden" name="nid" value="<?= (int)$n['id'] ?>">
                    <button name="approve" value="1" class="btn btn-sm btn-green"><i class="fas fa-check"></i> <?= t_admin('adm_viprev_pass', '通过') ?></button>
                    <button name="reject" value="1" class="btn btn-sm btn-danger"><i class="fas fa-times"></i> <?= t_admin('adm_viprev_reject', '拒绝') ?></button>
                </form>
                <a href="<?= htmlspecialchars(admin_url('novel_edit', ['id' => (int)$n['id']])) ?>" class="btn btn-sm btn-outline" title="<?= t_admin('adm_edit', '编辑') ?>"><i class="fas fa-edit"></i></a>
                <?php Hook::doAction('admin_vip_review_row_actions', $n); ?>
            </td>
            <?= PluginTable::renderCells('admin.vip_review', $n) ?>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($pending)): ?><tr><td colspan="<?= 7 + PluginTable::count('admin.vip_review') ?>" class="empty"><?= t_admin('adm_viprev_empty', '暂无待审核作品') ?></td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
