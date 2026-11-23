<?php

if (!defined('ADMIN_BOOT')) exit;
$pageTitle = t_admin('adm_volumes', '分卷管理');

$novelId = (int)($_GET['novel_id'] ?? 0);
if ($novelId <= 0) {
    echo '<div class="card"><p class="empty">' . t_admin('adm_vol_novel_required', '请先从小说列表选择小说') . '</p><a href="' . htmlspecialchars(admin_url('novels')) . '" class="btn btn-outline">' . t_admin('adm_novel_list', '小说列表') . '</a></div>';
    return;
}
$novel = NovelModel::findById($novelId);
if (!$novel) { echo '<div class="card"><p class="empty">' . t_admin('adm_noveledit_not_found', '小说不存在') . '</p></div>'; return; }

$message = '';
$volumeService = new NovelService();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCsrf()) {
        $message = t_admin('adm_csrf_failed_short', 'CSRF验证失败');
    } elseif (p('vol_delete', null, 'raw') !== null) {
        $vid = p('vol_delete', 0, 'int');
        $mode = p('vol_mode', 'delete', 'str');
        $target = p('target_volume_id', 0, 'int');
        $res = $volumeService->deleteVolume($vid, $mode, $target);
        admin_flash_redirect($res['msg'], 'volumes', array('novel_id' => $novelId));
    } else {
        $vid = Security::post('id', 0, 'int');
        $title = p('title', '', 'str');
        $sort = Security::post('sort', 0, 'int');
        if ($title === '') {
            $message = t_admin('adm_vol_name_empty', '分卷名称不能为空');
        } else {
            if ($sort <= 0 && $vid === 0) {
                $existing = VolumeModel::all(array('novel_id' => $novelId), 'sort DESC', 1);
                $sort = isset($existing[0]['sort']) ? ((int)$existing[0]['sort'] + 1) : 1;
            }
            if ($vid > 0) {
                VolumeModel::update(array('title' => $title, 'sort' => $sort), array('id' => $vid, 'novel_id' => $novelId));
                admin_flash_redirect(t_admin('adm_vol_updated', '分卷已更新'), 'volumes', array('novel_id' => $novelId));
            } else {
                VolumeModel::create(array('novel_id' => $novelId, 'title' => $title, 'sort' => $sort));
                admin_flash_redirect(t_admin('adm_vol_added', '分卷已添加'), 'volumes', array('novel_id' => $novelId));
            }
        }
    }
}

$editVolume = null;
if (g('edit', null, 'raw') !== null) {
    $ev = VolumeModel::findById((int)g('edit', 0, 'int'));
    if ($ev && (int)$ev['novel_id'] === $novelId) $editVolume = $ev;
}

$delVolume = null;
if (g('delete', null, 'raw') !== null) {
    $dv = VolumeModel::findById((int)g('delete', 0, 'int'));
    if ($dv && (int)$dv['novel_id'] === $novelId) $delVolume = $dv;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$result = VolumeModel::paginate(array('novel_id' => $novelId), 'sort ASC, id ASC', $page, 20);
$allVolumes = VolumeModel::all(array('novel_id' => $novelId), 'sort ASC, id ASC');
$delCount = $delVolume ? VolumeModel::countChapters((int)$delVolume['id']) : 0;
$otherTargets = $delVolume ? array_values(array_filter($allVolumes, function ($v) use ($delVolume) {
    return (int)$v['id'] !== (int)$delVolume['id'];
})) : array();
?>
<div class="card">
    <h2>
        <?= t_admin('adm_vol_title', '《%s》分卷', htmlspecialchars((string)$novel['title'])) ?>
        <span class="h2-actions">
            <a href="<?= htmlspecialchars(admin_url('novel_edit', ['id' => $novelId])) ?>" class="btn btn-sm btn-outline"><i class="fas fa-pen-to-square"></i> <?= t_admin('adm_vol_novel_info', '小说信息') ?></a>
            <a href="<?= htmlspecialchars(admin_url('chapters', ['novel_id' => $novelId])) ?>" class="btn btn-sm btn-outline"><i class="fas fa-list"></i> <?= t_admin('adm_chapters', '章节管理') ?></a>
        </span>
    </h2>
    <?php if ($message): ?><div class="alert alert-err"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <?php if ($delVolume): ?>
    <div class="alert alert-err" style="margin-bottom:16px">
        <strong><?= t_admin('adm_vol_delete_confirm', '确认删除分卷「%s」？', htmlspecialchars((string)$delVolume['title'])) ?></strong>
        <?php if ($delCount > 0): ?><?= t_admin('adm_vol_with_chapters', '该卷内含 <strong>%d</strong> 章，需先选择章节去向。', $delCount) ?><?php else: ?><?= t_admin('adm_vol_no_chapters', '该卷暂无章节。') ?><?php endif; ?>
    </div>
    <form method="post" style="margin-bottom:24px;max-width:560px">
        <?= Security::csrfField() ?>
        <input type="hidden" name="vol_delete" value="<?= (int)$delVolume['id'] ?>">
        <?php if ($delCount > 0): ?>
        <div class="form-group">
            <label style="display:block;margin-bottom:6px"><?= t_admin('adm_vol_chapter_action', '章节处置方式') ?></label>
            <label style="display:block;margin-bottom:6px">
                <input type="radio" name="vol_mode" value="move" <?= !empty($otherTargets) ? 'checked' : 'disabled' ?> onchange="var r=document.getElementById('targetVolRow');if(r)r.style.display=''"> <?= t_admin('adm_vol_move', '迁移到其它分卷') ?>
            </label>
            <div class="form-group" id="targetVolRow" style="<?= empty($otherTargets) ? 'display:none' : '' ?>">
                <?php if (!empty($otherTargets)): ?>
                <select name="target_volume_id" class="form-control">
                    <?php foreach ($otherTargets as $ot): ?>
                    <option value="<?= (int)$ot['id'] ?>"><?= t_admin('adm_vol_move_target', '%s（%d 章）', htmlspecialchars((string)$ot['title']), VolumeModel::countChapters((int)$ot['id'])) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php else: ?>
                <p class="text-muted"><?= t_admin('adm_vol_no_other', '本书暂无其它分卷可迁移。') ?></p>
                <?php endif; ?>
            </div>
            <label style="display:block;margin-bottom:6px"><input type="radio" name="vol_mode" value="delete"<?php if (empty($otherTargets)) echo ' checked'; ?> onchange="var r=document.getElementById('targetVolRow');if(r)r.style.display='none'"> <?= t_admin('adm_vol_delete_all', '删除卷内全部章节（不可恢复，连同正文文件一起清除）') ?></label>
        </div>
        <?php else: ?>
        <input type="hidden" name="vol_mode" value="delete">
        <?php endif; ?>
        <button class="btn btn-danger"><i class="fas fa-trash"></i> <?= t_admin('adm_vol_confirm_delete', '确认删除') ?></button>
        <a href="<?= htmlspecialchars(admin_url('volumes', ['novel_id' => $novelId])) ?>" class="btn btn-outline"><?= t_admin('adm_cancel', '取消') ?></a>
    </form>
    <?php endif; ?>

    <form method="post" style="margin-bottom:24px;max-width:520px">
        <?= Security::csrfField() ?>
        <input type="hidden" name="id" value="<?= $editVolume ? (int)$editVolume['id'] : 0 ?>">
        <div class="form-group"><label><?= t_admin('adm_vol_name', '分卷名称') ?></label><input type="text" name="title" class="form-control" required value="<?= $editVolume ? htmlspecialchars((string)$editVolume['title']) : '' ?>"></div>
        <div class="form-group"><label><?= t_admin('adm_vol_sort', '排序（0=自动）') ?></label><input type="number" name="sort" class="form-control" value="<?= $editVolume ? (int)$editVolume['sort'] : 0 ?>"></div>
        <?php if ($editVolume): ?>
        <button class="btn btn-primary"><i class="fas fa-save"></i> <?= t_admin('adm_save_changes', '保存修改') ?></button>
        <a href="<?= htmlspecialchars(admin_url('volumes', ['novel_id' => $novelId])) ?>" class="btn btn-outline"><?= t_admin('adm_cancel_edit', '取消编辑') ?></a>
        <?php else: ?>
        <button class="btn btn-primary"><i class="fas fa-plus"></i> <?= t_admin('adm_vol_add', '添加分卷') ?></button>
        <?php endif; ?>
    </form>

    <div class="table-wrap">
    <table class="data">
        <thead><tr><th>ID</th><th><?= t_admin('adm_vol_col_name', '名称') ?></th><th><?= t_admin('adm_vol_col_sort', '排序') ?></th><th><?= t_admin('adm_vol_col_chapters', '章节数') ?></th><th><?= t_admin('adm_operation', '操作') ?></th><?= PluginTable::renderHead('admin.volumes') ?></tr></thead>
        <tbody>
        <?php foreach ($result['list'] as $v): ?>
        <tr>
            <td><?= (int)$v['id'] ?></td>
            <td><?= htmlspecialchars((string)$v['title']) ?></td>
            <td><?= (int)$v['sort'] ?></td>
            <td><?= VolumeModel::countChapters((int)$v['id']) ?></td>
            <td style="display:flex;gap:6px">
                <a href="<?= htmlspecialchars(admin_url('volumes', ['novel_id' => $novelId, 'edit' => (int)$v['id']])) ?>" class="btn btn-sm btn-warning" title="<?= t_admin('adm_edit', '编辑') ?>"><i class="fas fa-edit"></i></a>
                <a href="<?= htmlspecialchars(admin_url('chapters', ['novel_id' => $novelId])) ?>" class="btn btn-sm btn-outline" title="<?= t_admin('adm_chapters', '章节') ?>"><i class="fas fa-list"></i></a>
                <a href="<?= htmlspecialchars(admin_url('volumes', ['novel_id' => $novelId, 'delete' => (int)$v['id']])) ?>" class="btn btn-sm btn-danger" title="<?= t_admin('adm_delete', '删除') ?>"><i class="fas fa-trash"></i></a>
                <?php Hook::doAction('admin_volumes_row_actions', $v, $novelId); ?>
            </td>
        <?= PluginTable::renderCells('admin.volumes', $v) ?></tr>
        <?php endforeach; ?>
        <?php if (empty($result['list'])): ?><tr><td colspan="<?= 5 + PluginTable::count('admin.volumes') ?>" class="empty"><?= t_admin('adm_vol_empty', '暂无分卷') ?></td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
    <?= admin_pagination($result['page'], $result['totalPages'], array('action' => 'volumes', 'novel_id' => $novelId)) ?>
</div>
<a href="<?= htmlspecialchars(admin_url('novels')) ?>" class="btn btn-outline"><i class="fas fa-arrow-left"></i> <?= t_admin('adm_vol_back_list', '返回小说列表') ?></a>
