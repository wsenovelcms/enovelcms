<?php

if (!defined('ADMIN_BOOT')) exit;
$pageTitle = t_admin('adm_novels', '小说管理');

$id = (int)($_GET['id'] ?? 0);
$categoryTree = CategoryModel::getCategoryTree();
$allTags = TagModel::getAllTags();
$maxTags = TagModel::getMaxTags();
$selectedTagIds = $id > 0 ? TagModel::getTagIdsByNovel($id) : array();
$tagsJson = htmlspecialchars(json_encode($allTags, JSON_UNESCAPED_UNICODE), ENT_QUOTES);
$selectedStr = implode(',', $selectedTagIds);

$novel = array(
    'title' => '', 'author' => '', 'category_id' => 0, 'cover' => '',
    'description' => '', 'status' => 0, 'is_vip' => 0, 'vip_audit_status' => -1,
);
if ($id > 0) {
    $novel = NovelModel::findById($id);
    if (!$novel) admin_flash_redirect(t_admin('adm_noveledit_not_found', '小说不存在'), 'novels');
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCsrf()) {
        $message = t_admin('adm_csrf_failed_short', 'CSRF验证失败');
    } else {
        $titleInput = trim((string)Security::post('title'));
        if ($titleInput === '') {
            $message = t_admin('adm_noveledit_title_empty', '小说标题不能为空');
        }
        if ($message === '') {
            $tagIds = Security::post('tag_ids', array(), 'arr');
            $tagIds = array_values(array_unique(array_map('intval', (array)$tagIds)));
            $maxTags = TagModel::getMaxTags();
            if (count($tagIds) > $maxTags) {
                $message = t_admin('adm_noveledit_tag_limit', '每部小说最多选择 %d 个标签', $maxTags);
            }
        }
        if ($message === '') {
            $cover = p('old_cover', '', 'str');
            if ($cover !== '' && strpos($cover, 'uploads/covers/') !== 0) {
                $cover = '';
            }
            if (!empty($_FILES['cover']['tmp_name'])) {
                $img = Security::verifyImageUpload($_FILES['cover']);
                if ($img) {
                    $dir = ROOT_PATH . 'uploads/covers/';
                    if (!is_dir($dir)) mkdir($dir, 0755, true);
                    $cover = 'uploads/covers/' . uniqid('cover_') . '.' . $img['ext'];
                    move_uploaded_file($_FILES['cover']['tmp_name'], $dir . basename($cover));
                }
            }
            $isVip = Security::post('is_vip', 0, 'int');
            $vipAudit = Security::post('vip_audit_status', -1, 'int');
            if ($isVip === 1) {
                $vipAudit = 1;
            } elseif ($vipAudit === 1) {
                $vipAudit = -1;
            }
            $data = array(
                'title'            => $titleInput,
                'author'           => Security::post('author'),
                'category_id'      => Security::post('category_id', 0, 'int'),
                'cover'            => $cover,
                'description'      => Security::post('description', '', 'raw'),
                'status'           => Security::post('status', 0, 'int'),
                'is_vip'           => $isVip,
                'vip_audit_status' => $vipAudit,
            );
            $data = Hook::applyFilters('admin_novel_save_data', $data);
            if ($id > 0) {
                NovelModel::update($data, array('id' => $id));
                NovelModel::refreshStats($id);
            } else {
                $id = NovelModel::create($data);
            }
            PluginForm::save('novel_edit', 'novel', $id);
            TagModel::syncNovelTags($id, $tagIds);
            Hook::doAction('admin_novel_edit_saved', $id, $_POST);
            admin_flash_redirect(t_admin('adm_noveledit_saved', '小说已保存'), 'novels');
        }
    }
}
?>
<div class="card">
    <h2><?= $id ? t_admin('adm_noveledit_title_edit', '编辑小说 #%d', $id) : t_admin('adm_noveledit_title_add', '添加小说') ?></h2>
    <?php if ($message): ?><div class="alert alert-err"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" style="max-width:760px">
        <?= Security::csrfField() ?>
        <div class="form-row-2">
            <div class="form-group"><label><?= t_admin('adm_noveledit_title', '标题 *') ?></label><input type="text" name="title" class="form-control" value="<?= htmlspecialchars((string)$novel['title']) ?>" required></div>
            <div class="form-group"><label><?= t_admin('adm_noveledit_author', '作者') ?></label><input type="text" name="author" class="form-control" value="<?= htmlspecialchars((string)$novel['author']) ?>"></div>
        </div>
        <div class="form-row-2">
            <div class="form-group">
                <label><?= t_admin('adm_noveledit_category', '分类') ?></label>
                <select name="category_id" class="form-control">
                    <?php foreach ($categoryTree as $top): ?>
                    <option value="<?= (int)$top['id'] ?>" <?= $top['id'] == $novel['category_id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$top['name']) ?></option>
                    <?php foreach (($top['children'] ?? array()) as $sub): ?>
                    <option value="<?= (int)$sub['id'] ?>" <?= $sub['id'] == $novel['category_id'] ? 'selected' : '' ?>>　└ <?= htmlspecialchars((string)$sub['name']) ?></option>
                    <?php endforeach; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label><?= t_admin('adm_noveledit_status', '状态') ?></label>
                <select name="status" class="form-control">
                    <option value="0" <?= $novel['status'] == 0 ? 'selected' : '' ?>><?= t_admin('adm_noveledit_serial', '连载中') ?></option>
                    <option value="1" <?= $novel['status'] == 1 ? 'selected' : '' ?>><?= t_admin('adm_noveledit_finished', '已完结') ?></option>
                </select>
            </div>
        </div>
        <div class="form-row-2">
            <div class="form-group">
                <label><?= t_admin('adm_noveledit_is_vip', '是否VIP') ?></label>
                <select name="is_vip" class="form-control">
                    <option value="0" <?= $novel['is_vip'] == 0 ? 'selected' : '' ?>><?= t_admin('adm_noveledit_free', '否（免费）') ?></option>
                    <option value="1" <?= $novel['is_vip'] == 1 ? 'selected' : '' ?>><?= t_admin('adm_noveledit_vip', '是（VIP）') ?></option>
                </select>
            </div>
            <div class="form-group">
                <label><?= t_admin('adm_noveledit_vip_audit', 'VIP审核状态') ?></label>
                <select name="vip_audit_status" class="form-control">
                    <option value="-1" <?= $novel['vip_audit_status'] == -1 ? 'selected' : '' ?>><?= t_admin('adm_noveledit_vip_unapplied', '未申请') ?></option>
                    <option value="0" <?= $novel['vip_audit_status'] == 0 ? 'selected' : '' ?>><?= t_admin('adm_noveledit_vip_pending', '待审核') ?></option>
                    <option value="1" <?= $novel['vip_audit_status'] == 1 ? 'selected' : '' ?>><?= t_admin('adm_noveledit_vip_approved', '已通过') ?></option>
                    <option value="2" <?= $novel['vip_audit_status'] == 2 ? 'selected' : '' ?>><?= t_admin('adm_noveledit_vip_rejected', '已拒绝') ?></option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label><?= t_admin('adm_noveledit_cover', '封面') ?></label>
            <input type="file" name="cover" accept="image/*">
            <input type="hidden" name="old_cover" value="<?= htmlspecialchars((string)$novel['cover']) ?>">
            <?php if ($novel['cover']): ?><img src="/<?= htmlspecialchars((string)$novel['cover']) ?>" style="max-height:120px;border-radius:8px;margin-top:8px"><?php endif; ?>
        </div>
        <div class="form-group"><label><?= t_admin('adm_noveledit_desc', '简介') ?></label><textarea name="description" class="form-control" rows="5"><?= htmlspecialchars((string)$novel['description']) ?></textarea></div>
        <div class="form-group">
            <label><?= t_admin('adm_noveledit_tags', '标签') ?> <span class="tag-limit-hint"><?= t_admin('adm_noveledit_tag_pick_hint', '最多选择 %d 个', $maxTags) ?></span></label>
            <div class="tag-select" data-field="tag_ids" data-max="<?= (int)$maxTags ?>" data-tags="<?= $tagsJson ?>" data-selected="<?= htmlspecialchars($selectedStr) ?>"></div>
        </div>
        <?= PluginForm::render('novel_edit', 'novel', $id) ?>
        <?php Hook::doAction('admin_novel_edit_extra', $novel); ?>
        <?php if ($id > 0): ?>
        <a href="<?= htmlspecialchars(admin_url('chapters', ['novel_id' => $id])) ?>" class="btn btn-outline"><i class="fas fa-list"></i> <?= t_admin('adm_chapters', '章节管理') ?></a>
        <a href="<?= htmlspecialchars(admin_url('volumes', ['novel_id' => $id])) ?>" class="btn btn-outline"><i class="fas fa-layer-group"></i> <?= t_admin('adm_chap_volumes', '分卷管理') ?></a>
        <?php endif; ?>
        <a href="<?= htmlspecialchars(admin_url('novels')) ?>" class="btn btn-outline"><?= t_admin('adm_back', '返回') ?></a>
        <button class="btn btn-primary"><i class="fas fa-save"></i> <?= t_admin('adm_save', '保存') ?></button>
    </form>
</div>
<script src="/assets/js/tag_picker.js"></script>
