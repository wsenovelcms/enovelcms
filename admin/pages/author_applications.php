<?php

if (!defined('ADMIN_BOOT')) exit;
$pageTitle = t_admin('adm_author_applications', '作者审核');

$authorService = new AuthorService();

if (g('delete', null, 'raw') !== null) {
    if (Security::verifyCsrf()) {
        $pdo = Model::db()->getPdo();
        $stmt = $pdo->prepare("DELETE FROM ".tb('author_applications')." WHERE id = ?");
        $stmt->execute([g('delete', 0, 'int')]);
        admin_flash_redirect(t_admin('adm_app_deleted', '已删除该申请'), 'author_applications');
    }
}

if (g('do', null, 'raw') !== null && g('id', null, 'raw') !== null) {
    if (Security::verifyCsrf()) {
        $status = $_GET['do'] === 'approve' ? 1 : 2;
        $reason = $_GET['reason'] ?? null;
        $res = $authorService->auditApplication(g('id', 0, 'int'), $status, $reason);
        admin_flash_redirect($res['msg'], 'author_applications');
    }
    admin_flash_redirect(t_admin('adm_security_failed', '安全验证失败'), 'author_applications');
}

$statusFilter = g('status', null, 'raw') !== null ? g('status', 0, 'int') : -1;
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$counts = [];
$counts[-1] = AuthorApplicationModel::count();
$counts[0]  = AuthorApplicationModel::count(['status' => 0]);
$counts[1]  = AuthorApplicationModel::count(['status' => 1]);
$counts[2]  = AuthorApplicationModel::count(['status' => 2]);

$where = '1=1';
$params = [];
if ($statusFilter >= 0) {
    $where .= ' AND a.status = ?';
    $params[] = $statusFilter;
}

$offset = ($page - 1) * $perPage;
$total = $counts[$statusFilter];
$list = AuthorApplicationModel::db()->query(
    "SELECT a.*, u.username, u.email FROM " . AuthorApplicationModel::tableName() . " a
     LEFT JOIN " . tb('users') . " u ON u.id = a.user_id
     WHERE {$where} ORDER BY a.created_at DESC LIMIT {$perPage} OFFSET {$offset}",
    $params
)->fetchAll();
$totalPages = (int)ceil($total / $perPage);
$statusMap = [-1 => t_admin('adm_app_status_all', '全部'), 0 => t_admin('adm_app_status_pending', '待审核'), 1 => t_admin('adm_app_status_approved', '已通过'), 2 => t_admin('adm_app_status_rejected', '已拒绝')];
$tabBadge = [-1 => '', 0 => 'badge-orange', 1 => 'badge-green', 2 => 'badge-red'];
?>
<div class="card">
    <h2><?= t_admin('adm_author_applications', '作者审核') ?></h2>

    <div class="tab-bar">
        <?php foreach ($statusMap as $k => $label): ?>
        <a href="<?= htmlspecialchars(admin_url('author_applications', ['status' => $k])) ?>"
           class="tab <?= $statusFilter === $k ? 'active' : '' ?>">
            <?= $label ?>
            <span class="tab-count"><?= $counts[$k] ?? 0 ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="table-wrap">
    <table class="data">
        <thead><tr><th>ID</th><th><?= t_admin('adm_app_col_user', '用户') ?></th><th><?= t_admin('adm_author_col_penname', '笔名') ?></th><th><?= t_admin('adm_app_col_realname', '真实姓名') ?></th><th><?= t_admin('adm_app_col_phone', '手机') ?></th><th><?= t_admin('adm_status', '状态') ?></th><th><?= t_admin('adm_app_col_time', '申请时间') ?></th><th style="width:160px"><?= t_admin('adm_operation', '操作') ?></th><?= PluginTable::renderHead('admin.author_applications') ?></tr></thead>
        <tbody>
        <?php foreach ($list as $row): $s = (int)$row['status']; ?>
        <tr>
            <td><?= (int)$row['id'] ?></td>
            <td><?= htmlspecialchars((string)$row['username']) ?> <small class="text-muted">#<?= (int)$row['user_id'] ?></small></td>
            <td><strong><?= htmlspecialchars((string)$row['pen_name']) ?></strong></td>
            <td><?= htmlspecialchars((string)$row['real_name']) ?></td>
            <td><?= htmlspecialchars($row['phone'] ?: '-') ?></td>
            <td><span class="badge <?= $tabBadge[$s] ?>"><?= $statusMap[$s] ?></span></td>
            <td><?= htmlspecialchars($row['created_at']) ?></td>
            <td>
                <div class="btn-group">
                    <a href="#" onclick="toggleDetail('detail_<?= (int)$row['id'] ?>');return false;" class="btn btn-sm btn-outline" title="<?= t_admin('adm_app_view_detail', '查看详情') ?>"><i class="fas fa-eye"></i></a>
                    <?php if ($s === 0): ?>
                    <a href="<?= htmlspecialchars(admin_url('author_applications', ['do' => 'approve', 'id' => (int)$row['id'], '_csrf' => Security::csrfToken()])) ?>" class="btn btn-sm btn-green" title="<?= t_admin('adm_app_approve', '通过') ?>" data-confirm="<?= t_admin('adm_app_approve_confirm', '确认通过？笔名将设为「%s」', htmlspecialchars((string)$row['pen_name'])) ?>"><i class="fas fa-check"></i></a>
                    <a href="#" class="btn btn-sm btn-warning" title="<?= t_admin('adm_app_reject', '拒绝') ?>" onclick="rejectApp(<?= (int)$row['id'] ?>, '<?= urlencode(Security::csrfToken()) ?>');return false;"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                    <a href="<?= htmlspecialchars(admin_url('author_applications', ['delete' => (int)$row['id'], '_csrf' => Security::csrfToken()])) ?>" class="btn btn-sm btn-danger" title="<?= t_admin('adm_delete', '删除') ?>" data-confirm="<?= t_admin('adm_app_delete_confirm', '确认删除？此操作不可恢复') ?>"><i class="fas fa-trash"></i></a>
                </div>
            </td>
        <?= PluginTable::renderCells('admin.author_applications', $row) ?></tr>
        <tr id="detail_<?= (int)$row['id'] ?>" style="display:none">
            <td colspan="8" style="background:#f8fafc;text-align:left">
                <div style="padding:12px 16px;font-size:13px;line-height:1.8">
                    <?php if ($s === 2 && !empty($row['reject_reason'])): ?>
                    <p style="color:#a83232"><strong><?= t_admin('adm_app_reject_reason', '拒绝理由：') ?></strong><?= nl2br(htmlspecialchars((string)$row['reject_reason'])) ?></p>
                    <?php endif; ?>
                    <p><strong><?= t_admin('adm_app_qq', 'QQ：') ?></strong><?= htmlspecialchars($row['qq'] ?: '-') ?></p>
                    <p><strong><?= t_admin('adm_app_email', '邮箱：') ?></strong><?= htmlspecialchars($row['email'] ?: '-') ?></p>
                    <p><strong><?= t_admin('adm_app_intro', '简介：') ?></strong><?= nl2br(htmlspecialchars($row['introduction'] ?: '-')) ?></p>
                    <p style="margin-top:8px"><strong><?= t_admin('adm_app_idcard', '身份证：') ?></strong><?= htmlspecialchars($row['id_card'] ?: t_admin('adm_app_idcard_missing', '未提供')) ?></p>
                    <div class="idcard-row">
                        <?php foreach (['front' => t_admin('adm_app_idcard_front', '身份证正面'), 'back' => t_admin('adm_app_idcard_back', '身份证反面')] as $side => $label):
                            $hasPic = !empty($row['id_card_' . $side]);
                            $picUrl = admin_url('id_card', ['app_id' => (int)$row['id'], 'side' => $side]);
                        ?>
                        <figure class="idcard-figure">
                            <figcaption><?= $label ?></figcaption>
                            <?php if ($hasPic): ?>
                            <img src="<?= htmlspecialchars($picUrl) ?>" alt="<?= htmlspecialchars($label) ?>" loading="lazy"
                                 data-preview="<?= htmlspecialchars($picUrl) ?>" data-preview-title="<?= htmlspecialchars($label) ?>">
                            <?php else: ?>
                            <div class="idcard-empty"><?= t_admin('adm_app_idcard_none', '未上传') ?></div>
                            <?php endif; ?>
                        </figure>
                        <?php endforeach; ?>
                    </div>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($list)): ?><tr><td colspan="<?= 8 + PluginTable::count('admin.author_applications') ?>" class="empty"><?= t_admin('adm_app_empty', '暂无申请记录') ?></td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
    <?= admin_pagination($page, $totalPages, ['action' => 'author_applications', 'status' => $statusFilter]) ?>
</div>
<style>
.tab-bar { display:flex; gap:0; border-bottom:2px solid #e5e7eb; margin-bottom:20px; }
.tab { padding:10px 20px; text-decoration:none; color:#666; border-bottom:2px solid transparent; margin-bottom:-2px; font-size:14px; transition:all .2s; }
.tab:hover { color:#333; }
.tab.active { color:#1a56db; border-bottom-color:#1a56db; font-weight:600; }
.tab-count { display:inline-block; background:#e5e7eb; border-radius:10px; padding:1px 8px; font-size:12px; margin-left:4px; }
.tab.active .tab-count { background:#dbeafe; color:#1a56db; }
.btn-group { display:flex; gap:4px; }
.idcard-row { display:flex; gap:14px; flex-wrap:wrap; margin-top:12px; }
.idcard-figure { margin:0; }
.idcard-figure figcaption { font-size:12px; font-weight:600; color:#475569; margin-bottom:6px; }
.idcard-figure img { width:200px; height:126px; object-fit:cover; border:1px solid #e2e8f0; border-radius:8px; cursor:zoom-in; background:#fff; }
.idcard-figure img:hover { border-color:#1a56db; box-shadow:0 4px 14px rgba(26,86,219,.18); }
.idcard-empty { width:200px; height:126px; display:flex; align-items:center; justify-content:center; border:1px dashed #cbd5e1; border-radius:8px; color:#94a3b8; font-size:13px; background:#fff; }
</style>
<script>
function toggleDetail(id) {
    var el = document.getElementById(id);
    if (el) el.style.display = (el.style.display === 'none') ? 'table-row' : 'none';
}
document.querySelectorAll('[data-preview]').forEach(function (pic) {
    pic.addEventListener('click', function () {
        UI.image({ src: pic.getAttribute('data-preview'), title: pic.getAttribute('data-preview-title') });
    });
});
function rejectApp(id, csrf) {
    UI.form({
        title: '<?= t_admin('adm_app_reject_title', '拒绝申请') ?>',
        okText: '<?= t_admin('adm_app_reject_ok', '确认拒绝') ?>',
        fields: [{ type: 'textarea', name: 'reason', label: '<?= t_admin('adm_app_reject_label', '拒绝理由') ?>', rows: 3, placeholder: '<?= t_admin('adm_app_reject_ph', '请输入拒绝理由') ?>', required: true }],
        onSubmit: function (values) {
            var reason = (values.reason || '').trim();
            if (!reason) { UI.toast('<?= t_admin('adm_app_reject_empty', '拒绝理由不能为空') ?>', 'error'); return false; }
            location.href = <?= json_encode(admin_url('author_applications', ['do' => 'reject'])) ?> + '&id=' + id + '&_csrf=' + csrf + '&reason=' + encodeURIComponent(reason);
        }
    });
}
</script>
