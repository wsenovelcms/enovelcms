<?php

if (!defined('ADMIN_BOOT')) exit;
$pageTitle = t_admin('adm_chapters', '章节管理');

$novelId = (int)($_GET['novel_id'] ?? 0);
$id = (int)($_GET['id'] ?? 0);
if ($novelId <= 0) { echo '<div class="card"><p class="empty">' . t_admin('adm_chapedit_missing_novel', '缺少 novel_id') . '</p></div>'; return; }
$novel = NovelModel::findById($novelId);
if (!$novel) { echo '<div class="card"><p class="empty">' . t_admin('adm_chapedit_novel_not_found', '小说不存在') . '</p></div>'; return; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && p('vol_action', null, 'raw') !== null) {
    if (!Security::verifyCsrf()) {
        $volMsg = t_admin('adm_csrf_failed_short', 'CSRF验证失败');
    } else {
        $backParams = ['novel_id' => $novelId];
        if ($id) { $backParams['id'] = $id; }
        $backUrl = admin_url('chapter_edit', $backParams);
        $act = $_POST['vol_action'];
        if ($act === 'add') {
            $t = trim((string)(p('vol_new_title', '', 'str')));
            if ($t === '') { $volMsg = t_admin('adm_chapedit_vol_name_empty', '分卷名称不能为空'); }
            else {
                $maxSortRow = VolumeModel::all(array('novel_id' => $novelId), 'sort DESC, id DESC', 1);
                $nextSort = $maxSortRow ? ((int)$maxSortRow[0]['sort'] + 1) : 1;
                VolumeModel::create(array('novel_id' => $novelId, 'title' => $t, 'sort' => $nextSort));
                header('Location: ' . $backUrl); exit;
            }
        } elseif ($act === 'rename') {
            $vid = (int)($_POST['vol_id'] ?? 0);
            $t = trim((string)(p('vol_new_title', '', 'str')));
            if ($vid > 0 && $t !== '') {
                VolumeModel::update(array('title' => $t), array('id' => $vid, 'novel_id' => $novelId));
                header('Location: ' . $backUrl); exit;
            }
            $volMsg = t_admin('adm_chapedit_vol_name_empty', '分卷名称不能为空');
        } elseif ($act === 'delete') {
            $vid = (int)($_POST['vol_id'] ?? 0);
            $cnt = VolumeModel::countChapters($vid);
            if ($cnt > 0) { $volMsg = t_admin('adm_chapedit_vol_has_chaps', '该分卷下还有 %d 章，不能删除', $cnt); }
            else {
                VolumeModel::delete(array('id' => $vid, 'novel_id' => $novelId));
                header('Location: ' . $backUrl); exit;
            }
        }
    }
}

$volumes = VolumeModel::all(array('novel_id' => $novelId), 'sort ASC, id ASC');
if (empty($volumes)) {
    VolumeModel::create(array('novel_id' => $novelId, 'title' => t_admin('adm_chapedit_default_vol', '默认卷'), 'sort' => 0));
    $volumes = VolumeModel::all(array('novel_id' => $novelId), 'sort ASC, id ASC');
}
$volCount = array();
foreach ($volumes as $v) { $volCount[(int)$v['id']] = VolumeModel::countChapters((int)$v['id']); }

$defaultVolId = (int)$volumes[count($volumes) - 1]['id'];
$chapter = array('title' => '', 'volume_id' => $defaultVolId, 'is_vip' => 0, 'price' => 0, 'file_path' => '');
$content = '';
if ($id > 0) {
    $chapter = ChapterModel::find(array('id' => $id, 'novel_id' => $novelId));
    if (!$chapter) admin_flash_redirect(t_admin('adm_chapedit_chap_not_found', '章节不存在'), 'chapters', array('novel_id' => $novelId));
    $content = ChapterModel::getRawContent($chapter);
}

$volMsg = $volMsg ?? '';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCsrf()) {
        $message = t_admin('adm_csrf_failed_short', 'CSRF验证失败');
    } else {
        $rawContent = p('content', '', 'str');
        $rawContent = CommonService::cleanPlainText($rawContent);
        $title = trim((string)Security::post('title'));
        if ($title === '') {
            $message = t_admin('adm_chapedit_title_empty', '章节标题不能为空');
        }
        if ($message === '') {
            $volumeId = Security::post('volume_id', 0, 'int');
            $isVip = $novel['is_vip'] ? Security::post('is_vip', 0, 'int') : 0;
            $price = $isVip ? max(0, Security::post('price', 0, 'int')) : 0;
            $wordCount = CommonService::smartWordCount($rawContent);

            if ($id > 0) {
                if (!empty($chapter['file_path'])) {
                    $chaptersRoot = realpath(ROOT_PATH . 'data/chapters/');
                    $filePath = ROOT_PATH . 'data/chapters/' . $chapter['file_path'];
                    $realPath = realpath($filePath);
                    if ($realPath === false && is_file($filePath . '.php')) {
                        $realPath = realpath($filePath . '.php');
                    }
                    if ($chaptersRoot !== false && $realPath !== false
                        && strpos($realPath, $chaptersRoot . DIRECTORY_SEPARATOR) === 0) {
                        file_put_contents($realPath, $rawContent);
                    }
                }
                ChapterModel::update(array(
                    'title' => $title, 'volume_id' => $volumeId, 'word_count' => $wordCount,
                    'is_vip' => $isVip, 'price' => $price,
                ), array('id' => $id));
                $message = t_admin('adm_chapedit_updated', '章节已更新');
            } else {
                $nextSort = ChapterModel::getMaxSortInVolume((int)$volumeId) + 1;
                $newId = ChapterModel::create(array(
                    'novel_id' => $novelId, 'volume_id' => $volumeId, 'title' => $title,
                    'word_count' => $wordCount, 'file_path' => '', 'sort' => $nextSort,
                    'is_vip' => $isVip, 'price' => $price,
                ));
                $filePath = ChapterModel::saveContent($novelId, $newId, $rawContent);
                ChapterModel::update(array('file_path' => $filePath), array('id' => $newId));
                $message = t_admin('adm_chapedit_added', '章节已添加');
            }
            $savedChapterId = (int)($id > 0 ? $id : (isset($newId) ? $newId : 0));
            if ($savedChapterId > 0) {
                PluginForm::save('chapter_edit', 'chapter', $savedChapterId);
            }
            NovelModel::refreshStats($novelId);
            Hook::doAction('admin_chapter_edit_saved', $savedChapterId, $novelId, $_POST);
            admin_flash_redirect($message, 'chapters', array('novel_id' => $novelId));
        }
    }
}
?>
<div class="card">
    <h2><?= $id ? t_admin('adm_chapedit_title_edit', '编辑章节 #%d', $id) : t_admin('adm_chapedit_title_add', '添加章节') ?> · 《<?= htmlspecialchars((string)$novel['title']) ?>》</h2>
    <?php if (!empty($volMsg)): ?><div class="alert alert-err"><?= htmlspecialchars($volMsg) ?></div><?php endif; ?>

    <div class="vol-manager">
        <div class="vol-title"><i class="fas fa-folder-tree"></i> <?= t_admin('adm_chapedit_vol_manager', '分卷管理') ?></div>
        <ul class="vol-list">
            <?php foreach ($volumes as $v): ?>
            <li class="<?= (int)$v['id'] === (int)$chapter['volume_id'] ? 'current' : '' ?>">
                <span><?= htmlspecialchars((string)$v['title']) ?></span>
                <span class="vol-count"><?= t_admin('adm_chapedit_vol_count', '%d 章', (int)$volCount[(int)$v['id']]) ?></span>
                <form method="post" style="display:inline" class="js-rename-vol">
                    <?= Security::csrfField() ?>
                    <input type="hidden" name="vol_action" value="rename">
                    <input type="hidden" name="vol_id" value="<?= (int)$v['id'] ?>">
                    <input type="hidden" name="vol_new_title" value="<?= htmlspecialchars((string)$v['title']) ?>">
                    <button type="submit" class="btn btn-sm btn-outline" title="<?= t_admin('adm_chapedit_rename', '重命名') ?>"><i class="fas fa-pen"></i></button>
                </form>
                <?php if ((int)$volCount[(int)$v['id']] === 0): ?>
                <form method="post" style="display:inline" data-confirm="<?= t_admin('adm_chapedit_delete_confirm', '确认删除该分卷？') ?>">
                    <?= Security::csrfField() ?>
                    <input type="hidden" name="vol_action" value="delete">
                    <input type="hidden" name="vol_id" value="<?= (int)$v['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline" title="<?= t_admin('adm_delete', '删除') ?>"><i class="fas fa-trash"></i></button>
                </form>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <form method="post" class="vol-add-row">
            <?= Security::csrfField() ?>
            <input type="hidden" name="vol_action" value="add">
            <input type="text" name="vol_new_title" class="form-control input-medium" placeholder="<?= t_admin('adm_chapedit_vol_ph', '新分卷名称，回车快速添加') ?>" required>
            <button type="submit" class="btn btn-outline"><i class="fas fa-plus"></i> <?= t_admin('adm_chapedit_vol_add', '新增分卷') ?></button>
        </form>
    </div>

    <form method="post">
        <?= Security::csrfField() ?>
        <div class="form-row-2">
            <div class="form-group">
                <label><?= t_admin('adm_chapedit_volume', '所属卷') ?></label>
                <select name="volume_id" class="form-control">
                    <?php foreach ($volumes as $v): ?>
                    <option value="<?= (int)$v['id'] ?>" <?= (int)$v['id'] === (int)$chapter['volume_id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$v['title']) ?><?= (int)$volCount[(int)$v['id']] > 0 ? '（' . t_admin('adm_chapedit_vol_count', '%d 章', (int)$volCount[(int)$v['id']]) . '）' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label><?= t_admin('adm_chapedit_chap_title', '章节标题 *') ?></label><input type="text" name="title" class="form-control" value="<?= htmlspecialchars((string)$chapter['title']) ?>" required></div>
        </div>
        <?php if ($novel['is_vip']): ?>
        <div class="form-row-2">
            <div class="form-group">
                <label><?= t_admin('adm_chapedit_vip_chap', 'VIP章节') ?></label>
                <select name="is_vip" class="form-control">
                    <option value="0" <?= $chapter['is_vip'] == 0 ? 'selected' : '' ?>><?= t_admin('adm_no', '否') ?></option>
                    <option value="1" <?= $chapter['is_vip'] == 1 ? 'selected' : '' ?>><?= t_admin('adm_yes', '是') ?></option>
                </select>
            </div>
            <div class="form-group"><label><?= t_admin('adm_chapedit_price', '价格（书币，0=默认）') ?></label><input type="number" name="price" class="form-control" value="<?= (int)$chapter['price'] ?>" min="0"></div>
        </div>
        <?php endif; ?>
        <div class="form-group"><label><?= t_admin('adm_chapedit_content', '内容 *') ?></label><textarea name="content" class="form-control" rows="16" required><?= htmlspecialchars($content) ?></textarea></div>
        <?= PluginForm::render('chapter_edit', 'chapter', $id) ?>
        <?php Hook::doAction('admin_chapter_edit_extra', $chapter ?? ['id' => 0, 'novel_id' => $novelId]); ?>
        <a href="<?= htmlspecialchars(admin_url('volumes', ['novel_id' => $novelId])) ?>" class="btn btn-outline"><i class="fas fa-layer-group"></i> <?= t_admin('adm_chap_volumes', '分卷管理') ?></a>
        <a href="<?= htmlspecialchars(admin_url('novel_edit', ['id' => $novelId])) ?>" class="btn btn-outline"><i class="fas fa-pen-to-square"></i> <?= t_admin('adm_chap_novel_info', '小说信息') ?></a>
        <a href="<?= htmlspecialchars(admin_url('chapters', ['novel_id' => $novelId])) ?>" class="btn btn-outline"><?= t_admin('adm_back', '返回') ?></a>
        <button class="btn btn-primary"><i class="fas fa-save"></i> <?= t_admin('adm_save', '保存') ?></button>
    </form>
</div>
<script>
document.addEventListener('submit', function (e) {
    var f = e.target.closest('form.js-rename-vol');
    if (!f) return;
    e.preventDefault();
    var i = f.querySelector('input[name=vol_new_title]');
    UI.form({
        title: '<?= t_admin('adm_chapedit_rename_title', '重命名分卷') ?>',
        okText: '<?= t_admin('adm_chapedit_rename_ok', '保存') ?>',
        fields: [{ type: 'text', name: 'title', label: '<?= t_admin('adm_chapedit_vol_name', '分卷名称') ?>', value: i.value, required: true }],
        onSubmit: function (values) {
            var t = (values.title || '').trim();
            if (!t) { UI.toast('<?= t_admin('adm_chapedit_name_empty', '名称不能为空') ?>', 'error'); return false; }
            i.value = t;
            f.submit();
        }
    });
});
</script>
