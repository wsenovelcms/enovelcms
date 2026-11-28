<?php

if (!defined('ADMIN_BOOT')) exit;
$pageTitle = t_admin('adm_chapters', '章节管理');

$novelId = (int)($_GET['novel_id'] ?? 0);
$novel = null;
if ($novelId > 0) {
    $novel = NovelModel::findById($novelId);
    if (!$novel) admin_flash_redirect(t_admin('adm_chap_novel_not_found', '小说不存在'), 'chapters');
}

$deleteChapter = function (int $id): ?int {
    $ch = ChapterModel::findById($id);
    if (!$ch) return null;
    ChapterModel::deleteChapterFile($ch);
    ChapterModel::delete(array('id' => $id));
    return (int)$ch['novel_id'];
};

if (g('delete', null, 'raw') !== null) {
    if (Security::verifyCsrf()) {
        $deletedNovelId = $deleteChapter(g('delete', 0, 'int'));
        if ($deletedNovelId !== null) NovelModel::refreshStats($deletedNovelId);
        $back = $novelId > 0 ? array('novel_id' => $novelId) : array();
        admin_flash_redirect(t_admin('adm_chap_deleted', '章节已删除'), 'chapters', $back);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && p('batch_delete', null, 'raw') !== null) {
    if (Security::verifyCsrf()) {
        $rawIds = is_array($_POST['ids'] ?? null) ? $_POST['ids'] : [];
        $ids = [];
        foreach ($rawIds as $cid) { $cid = (int)$cid; if ($cid > 0) $ids[] = $cid; }
        $ids = array_values(array_unique($ids));
        if (empty($ids)) {
            $back = $novelId > 0 ? array('novel_id' => $novelId) : array();
            admin_flash_redirect(t_admin('adm_batch_select_none', '请先选择要删除的项'), 'chapters', $back);
        }
        $affectedNovels = [];
        foreach ($ids as $cid) {
            $affectedNovelId = $deleteChapter($cid);
            if ($affectedNovelId !== null) $affectedNovels[$affectedNovelId] = true;
        }
        foreach (array_keys($affectedNovels) as $affectedNovelId) NovelModel::refreshStats((int)$affectedNovelId);
        $back = $novelId > 0 ? array('novel_id' => $novelId) : array();
        admin_flash_redirect(t_admin('adm_batch_deleted_fmt', '已批量删除 %d 项', count($ids)), 'chapters', $back);
    }
    $back = $novelId > 0 ? array('novel_id' => $novelId) : array();
    admin_flash_redirect(t_admin('adm_security_failed', '安全验证失败'), 'chapters', $back);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (p('move_up', null, 'raw') !== null || p('move_down', null, 'raw') !== null)) {
    if (Security::verifyCsrf()) {
        $moveDirection = p('move_up', null, 'raw') !== null ? 'up' : 'down';
        $moveId = (int)(p('move_up', 0, 'int') ?: p('move_down', 0, 'int'));
        $moveChapter = ChapterModel::findById($moveId);
        if ($moveChapter) {
            (new ChapterService())->reorderChapter($moveChapter, array('direction' => $moveDirection));
        }
    }
    $back = $novelId > 0 ? array('novel_id' => $novelId) : array();
    admin_flash_redirect(t_admin('adm_chap_sorted', '章节顺序已更新'), 'chapters', $back);
}

$page = max(1, (int)($_GET['page'] ?? 1));
if ($novel) {
    $result = ChapterModel::paginateByNovel($novelId, $page, 50);
    $volumes = VolumeModel::all(array('novel_id' => $novelId), 'sort ASC');
    $volumeMap = array();
    foreach ($volumes as $v) $volumeMap[$v['id']] = $v['title'];
    $rows = $result['list'];
    $volumeFirst = [];
    $volumeLast = [];
    foreach (ChapterModel::getListByNovel($novelId) as $ordered) {
        $vid = (int)$ordered['volume_id'];
        $cid = (int)$ordered['id'];
        if (!isset($volumeFirst[$vid])) $volumeFirst[$vid] = $cid;
        $volumeLast[$vid] = $cid;
    }
} else {
    $result = ChapterModel::paginate(array(), 'id DESC', $page, 50);
    $rows = $result['list'];
    $novelIds = array();
    foreach ($rows as $c) {
        $novelIds[(int)$c['novel_id']] = 1;
    }
    $novelTitles = array();
    if ($novelIds) {
        $idList = implode(',', array_map('intval', array_keys($novelIds)));
        foreach (NovelModel::db()->query('SELECT id, title FROM ' . NovelModel::tableName() . " WHERE id IN ({$idList})")->fetchAll() as $n) {
            $novelTitles[(int)$n['id']] = $n['title'];
        }
    }
    foreach ($novelIds as $nid => $_) {
        if (!isset($novelTitles[$nid])) $novelTitles[$nid] = '#' . $nid;
    }
}
?>
<div class="card">
    <?php if ($novel): ?>
    <h2>
        <?= t_admin('adm_chap_novel_chapters', '《%s》章节', htmlspecialchars((string)$novel['title'])) ?>
        <span class="h2-actions">
            <a href="<?= htmlspecialchars(admin_url('volumes', ['novel_id' => $novelId])) ?>" class="btn btn-sm btn-outline"><i class="fas fa-layer-group"></i> <?= t_admin('adm_chap_volumes', '分卷管理') ?></a>
            <a href="<?= htmlspecialchars(admin_url('novel_edit', ['id' => $novelId])) ?>" class="btn btn-sm btn-outline"><i class="fas fa-pen-to-square"></i> <?= t_admin('adm_chap_novel_info', '小说信息') ?></a>
            <a href="<?= htmlspecialchars(admin_url('chapter_edit', ['novel_id' => $novelId])) ?>" class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> <?= t_admin('adm_chap_add', '添加章节') ?></a>
        </span>
    </h2>
    <p class="text-muted" style="margin-bottom:14px"><?= t_admin('adm_chap_words_line', '共 %s 字，%d 章', number_format((int)$novel['total_words']), (int)$novel['total_chapters']) ?> · <a href="<?= htmlspecialchars(admin_url('chapters')) ?>"><?= t_admin('adm_chap_view_all', '查看全部章节') ?></a></p>
    <div class="table-wrap">
    <form method="post">
        <?= Security::csrfField() ?>
        <div style="margin-bottom:10px">
            <button type="submit" name="batch_delete" value="1" class="btn btn-sm btn-danger" data-confirm="<?= t_admin('adm_batch_delete_confirm', '确认删除选中项？') ?>"><i class="fas fa-trash"></i> <?= t_admin('adm_batch_delete', '批量删除') ?></button>
        </div>
    <table class="data">
        <thead><tr><th style="width:38px"><input type="checkbox" data-check-all="chapters" aria-label="check"></th><th>ID</th><th><?= t_admin('adm_chap_col_title', '标题') ?></th><th><?= t_admin('adm_chap_col_volume', '分卷') ?></th><th><?= t_admin('adm_chap_col_words', '字数') ?></th><th><?= t_admin('adm_chap_col_vip', 'VIP') ?></th><th><?= t_admin('adm_chap_col_price', '价格') ?></th><th style="width:96px"><?= t_admin('adm_chap_col_sort', '排序') ?></th><th><?= t_admin('adm_operation', '操作') ?></th><?= PluginTable::renderHead('admin.chapters') ?></tr></thead>
        <tbody>
        <?php foreach ($rows as $c): ?>
        <tr>
            <td><input type="checkbox" name="ids[]" value="<?= (int)$c['id'] ?>" data-check-item="chapters"></td>
            <td><?= (int)$c['id'] ?></td>
            <td><?= htmlspecialchars((string)$c['title']) ?><?= $c['is_vip'] ? ' <span class="badge badge-orange">VIP</span>' : '' ?></td>
            <td><?= htmlspecialchars($volumeMap[$c['volume_id']] ?? '-') ?></td>
            <td><?= number_format((int)$c['word_count']) ?></td>
            <td><?= $c['is_vip'] ? t_admin('adm_yes', '是') : t_admin('adm_no', '否') ?></td>
            <td><?= (int)$c['price'] ?></td>
            <td style="display:flex;gap:6px">
                <button type="submit" class="btn btn-sm btn-outline" name="move_up" value="<?= (int)$c['id'] ?>" title="<?= t_admin('adm_chap_move_up', '上移') ?>"<?= ($volumeFirst[(int)$c['volume_id']] ?? 0) === (int)$c['id'] ? ' disabled' : '' ?>><i class="fas fa-arrow-up"></i></button>
                <button type="submit" class="btn btn-sm btn-outline" name="move_down" value="<?= (int)$c['id'] ?>" title="<?= t_admin('adm_chap_move_down', '下移') ?>"<?= ($volumeLast[(int)$c['volume_id']] ?? 0) === (int)$c['id'] ? ' disabled' : '' ?>><i class="fas fa-arrow-down"></i></button>
            </td>
            <td style="display:flex;gap:6px">
                <a href="<?= htmlspecialchars(admin_url('chapter_edit', ['novel_id' => $novelId, 'id' => (int)$c['id']])) ?>" class="btn btn-sm btn-warning" title="<?= t_admin('adm_edit', '编辑') ?>"><i class="fas fa-edit"></i></a>
                <a href="<?= htmlspecialchars(admin_url('chapters', ['novel_id' => $novelId, 'delete' => (int)$c['id'], '_csrf' => Security::csrfToken()])) ?>" class="btn btn-sm btn-danger" data-confirm="<?= t_admin('adm_chap_delete_confirm', '确认删除该章节？') ?>"><i class="fas fa-trash"></i></a>
                <?php Hook::doAction('admin_chapters_row_actions', $c, $novelId); ?>
            </td>
            <?= PluginTable::renderCells('admin.chapters', $c) ?>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?><tr><td colspan="<?= 9 + PluginTable::count('admin.chapters') ?>" class="empty"><?= t_admin('adm_chap_empty', '暂无章节') ?></td></tr><?php endif; ?>
        </tbody>
    </table>
    </form>
    </div>
    <?= admin_pagination($result['page'], $result['totalPages'], array('action' => 'chapters', 'novel_id' => $novelId)) ?>
    <?php else: ?>
    <h2><?= t_admin('adm_chap_all', '全部章节') ?></h2>
    <div class="table-wrap">
    <form method="post">
        <?= Security::csrfField() ?>
        <div style="margin-bottom:10px">
            <button type="submit" name="batch_delete" value="1" class="btn btn-sm btn-danger" data-confirm="<?= t_admin('adm_batch_delete_confirm', '确认删除选中项？') ?>"><i class="fas fa-trash"></i> <?= t_admin('adm_batch_delete', '批量删除') ?></button>
        </div>
    <table class="data">
        <thead><tr><th style="width:38px"><input type="checkbox" data-check-all="chaptersall" aria-label="check"></th><th>ID</th><th><?= t_admin('adm_chap_col_novel', '小说') ?></th><th><?= t_admin('adm_chap_col_chapter_title', '章节标题') ?></th><th><?= t_admin('adm_chap_col_words', '字数') ?></th><th><?= t_admin('adm_chap_col_vip', 'VIP') ?></th><th><?= t_admin('adm_operation', '操作') ?></th><?= PluginTable::renderHead('admin.chapters_all') ?></tr></thead>
        <tbody>
        <?php foreach ($rows as $c):
            $nid = (int)$c['novel_id'];
        ?>
        <tr>
            <td><input type="checkbox" name="ids[]" value="<?= (int)$c['id'] ?>" data-check-item="chaptersall"></td>
            <td><?= (int)$c['id'] ?></td>
            <td><a href="<?= htmlspecialchars(admin_url('chapters', ['novel_id' => $nid])) ?>"><?= htmlspecialchars($novelTitles[$nid] ?? '#'.$nid) ?></a></td>
            <td><?= htmlspecialchars((string)$c['title']) ?><?= $c['is_vip'] ? ' <span class="badge badge-orange">VIP</span>' : '' ?></td>
            <td><?= number_format((int)$c['word_count']) ?></td>
            <td><?= $c['is_vip'] ? '<span class="badge badge-orange">VIP</span>' : '<span class="badge badge-gray">' . t_admin('adm_chap_free', '免费') . '</span>' ?></td>
            <td style="display:flex;gap:6px">
                <a href="<?= htmlspecialchars(admin_url('chapter_edit', ['novel_id' => $nid, 'id' => (int)$c['id']])) ?>" class="btn btn-sm btn-warning" title="<?= t_admin('adm_edit', '编辑') ?>"><i class="fas fa-edit"></i></a>
                <a href="<?= htmlspecialchars(admin_url('chapters', ['delete' => (int)$c['id'], '_csrf' => Security::csrfToken()])) ?>" class="btn btn-sm btn-danger" data-confirm="<?= t_admin('adm_chap_delete_confirm', '确认删除该章节？') ?>" title="<?= t_admin('adm_delete', '删除') ?>"><i class="fas fa-trash"></i></a>
                <?php Hook::doAction('admin_chapters_row_actions', $c, 0); ?>
            </td>
            <?= PluginTable::renderCells('admin.chapters_all', $c) ?>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?><tr><td colspan="<?= 7 + PluginTable::count('admin.chapters_all') ?>" class="empty"><?= t_admin('adm_chap_empty', '暂无章节') ?></td></tr><?php endif; ?>
        </tbody>
    </table>
    </form>
    </div>
    <?= admin_pagination($result['page'], $result['totalPages'], array('action' => 'chapters')) ?>
    <?php endif; ?>
</div>
<?php if ($novel): ?>
<a href="<?= htmlspecialchars(admin_url('novels')) ?>" class="btn btn-outline"><i class="fas fa-arrow-left"></i> <?= t_admin('adm_chap_back_list', '返回小说列表') ?></a>
<?php endif; ?>
