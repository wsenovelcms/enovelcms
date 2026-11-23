<?php

if (!defined('ADMIN_BOOT')) exit;
$pageTitle = t_admin('adm_diy_blocks', '自定义榜单');

$categories = CategoryModel::all(array(), 'sort ASC, id ASC');
$allTags = TagModel::all(array(), 'sort ASC, id ASC');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && p('save_block', null, 'raw') !== null) {
    if (Security::verifyCsrf()) {
        $id = p('id', 0, 'int');
        $name = trim(p('name', '', 'text'));
        $title = trim(p('title', '', 'text'));

        $rawCats = is_array($_POST['category_ids'] ?? null) ? $_POST['category_ids'] : array();
        $catIds = array();
        foreach ($rawCats as $c) {
            $c = (int)$c;
            if ($c > 0) $catIds[] = $c;
        }
        $catIds = array_values(array_unique($catIds));
        $catStr = empty($catIds) ? '0' : implode(',', $catIds);

        $rawTags = is_array($_POST['tag_ids'] ?? null) ? $_POST['tag_ids'] : array();
        $tagIds = array();
        foreach ($rawTags as $t) {
            $t = (int)$t;
            if ($t > 0) $tagIds[] = $t;
        }
        $tagIds = array_values(array_unique($tagIds));
        $tagStr = empty($tagIds) ? '0' : implode(',', $tagIds);

        $data = array(
            'name' => $name,
            'title' => $title,
            'num' => max(1, min(100, p('num', 0, 'int'))),
            'category_id' => $catStr,
            'tag_id' => $tagStr,
            'status' => p('status', '', 'text'),
            'min_words' => p('min_words', 0, 'int'),
            'max_words' => p('max_words', 0, 'int'),
            'order_by' => p('order_by', '', 'text'),
            'time_range' => p('time_range', '', 'text'),
            'cache_time' => max(0, p('cache_time', 0, 'int')),
            'custom_ids' => trim(p('custom_ids', '', 'text')),
        );

        if ($name === '' || !preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            admin_flash_redirect(t_admin('adm_diy_identifier_bad', '标识符只能含字母数字下划线'), 'diy_blocks');
        }
        if (DiyBlockModel::nameExists($name, $id)) {
            admin_flash_redirect(t_admin('adm_diy_identifier_exists', '标识符已存在'), 'diy_blocks');
        }
        if ($id) {
            DiyBlockModel::update($data, array('id' => $id));
            DiyBlockModel::invalidate($id);
        } else {
            $newId = DiyBlockModel::create($data);
            DiyBlockModel::invalidate((int)$newId);
        }
        admin_flash_redirect(t_admin('adm_diy_saved', '区块已保存，缓存将在下次访问时重建'), 'diy_blocks');
    }
}

if (g('delete', null, 'raw') !== null) {
    if (Security::verifyCsrf()) {
        DiyBlockModel::delete(array('id' => g('delete', 0, 'int')));
        admin_flash_redirect(t_admin('adm_diy_deleted', '区块已删除'), 'diy_blocks');
    }
}
if (g('refresh', null, 'raw') !== null) {
    if (Security::verifyCsrf()) {
        DiyBlockModel::invalidate(g('refresh', 0, 'int'));
        admin_flash_redirect(t_admin('adm_diy_cache_refreshed', '缓存已刷新'), 'diy_blocks');
    }
}

$blocks = DiyBlockModel::getAllBlocks();
$editBlock = null;
$editCatIds = array();
$editTagIds = array();
if (g('edit', null, 'raw') !== null) {
    $editBlock = DiyBlockModel::findById(g('edit', 0, 'int'));
    if ($editBlock && !empty($editBlock['category_id'])) {
        foreach (explode(',', $editBlock['category_id']) as $v) {
            $v = (int)trim($v);
            if ($v > 0) $editCatIds[] = $v;
        }
    }
    if ($editBlock && !empty($editBlock['tag_id'])) {
        foreach (explode(',', $editBlock['tag_id']) as $v) {
            $v = (int)trim($v);
            if ($v > 0) $editTagIds[] = $v;
        }
    }
}
$tagsJson = htmlspecialchars(json_encode($allTags, JSON_UNESCAPED_UNICODE), ENT_QUOTES);
$editTagStr = implode(',', $editTagIds);
?>
<div style="display:grid;grid-template-columns:1fr 1.5fr;gap:18px" class="diy-layout">
    <div class="card" style="margin-bottom:0">
        <h2><?= $editBlock ? t_admin('adm_diy_edit', '编辑区块') : t_admin('adm_diy_add', '添加区块') ?></h2>
        <form method="post">
            <?= Security::csrfField() ?>
            <input type="hidden" name="save_block" value="1">
            <input type="hidden" name="id" value="<?= $editBlock ? (int)$editBlock['id'] : 0 ?>">
            <div class="form-group"><label><?= t_admin('adm_diy_name_label', '标识符 *') ?></label><input type="text" name="name" class="form-control" required pattern="[A-Za-z0-9_]+" value="<?= $editBlock ? htmlspecialchars((string)$editBlock['name']) : '' ?>" <?= $editBlock ? 'readonly' : '' ?> style="max-width:100%"></div>
            <div class="form-group"><label><?= t_admin('adm_diy_title_label', '显示标题') ?></label><input type="text" name="title" class="form-control" value="<?= $editBlock ? htmlspecialchars((string)$editBlock['title']) : '' ?>" style="max-width:100%"></div>
            <div class="form-group"><label><?= t_admin('adm_diy_num', '数量') ?></label><input type="number" name="num" class="form-control" min="1" max="100" value="<?= $editBlock ? (int)$editBlock['num'] : 10 ?>" style="max-width:120px"></div>

            <div class="form-group">
                <label><?= t_admin('adm_diy_category', '分类（多选）') ?></label>
                <div class="multi-cat">
                    <label><input type="checkbox" name="category_ids[]" value="0" <?= empty($editCatIds) ? 'checked' : '' ?>> <?= t_admin('adm_diy_all_category', '全部分类') ?></label>
                    <?php foreach ($categories as $cat): ?>
                    <label><input type="checkbox" name="category_ids[]" value="<?= (int)$cat['id'] ?>" <?= in_array((int)$cat['id'], $editCatIds, true) ? 'checked' : '' ?>> <?= htmlspecialchars((string)$cat['name']) ?></label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label><?= t_admin('adm_diy_tag', '标签（多选，不限数量）') ?></label>
                <div class="tag-select" data-field="tag_ids" data-max="0" data-tags='<?= $tagsJson ?>' data-selected="<?= htmlspecialchars($editTagStr, ENT_QUOTES) ?>"></div>
            </div>

            <div class="form-row-2">
                <div class="form-group">
                    <label><?= t_admin('adm_diy_status', '连载状态') ?></label>
                    <select name="status" class="form-control">
                        <?php $statusOptions = ['all' => t_admin('adm_all', '全部'), 'ongoing' => t_admin('adm_diy_ongoing', '连载中'), 'finished' => t_admin('adm_diy_finished', '已完结')];
                        $statusOptions = Hook::applyFilters('diy_status_options', $statusOptions);
                        foreach ($statusOptions as $v => $l): ?>
                        <option value="<?= $v ?>" <?= $editBlock && $editBlock['status'] === $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><?= t_admin('adm_diy_time_range', '时间段') ?></label>
                    <select name="time_range" class="form-control">
                        <?php $timeOptions = ['all' => t_admin('adm_diy_all_time', '所有时间'), 'today' => t_admin('adm_diy_today', '本日'), 'week' => t_admin('adm_diy_week', '本周'), 'month' => t_admin('adm_diy_month', '本月'), 'year' => t_admin('adm_diy_year', '本年')];
                        $timeOptions = Hook::applyFilters('diy_time_options', $timeOptions);
                        foreach ($timeOptions as $v => $l): ?>
                        <option value="<?= $v ?>" <?= $editBlock && $editBlock['time_range'] === $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label><?= t_admin('adm_diy_order_by', '排序方式') ?></label>
                <select name="order_by" id="diyOrder" class="form-control" style="max-width:100%">
                    <?php
                    $orders = array(
                        'views' => t_admin('adm_diy_order_views', '点击最多'), 'new' => t_admin('adm_diy_order_new', '最新入库'), 'update' => t_admin('adm_diy_order_update', '最近更新'),
                        'favorites' => t_admin('adm_diy_order_favorites', '收藏最多'), 'chapters' => t_admin('adm_diy_order_chapters', '章节最多'), 'words' => t_admin('adm_diy_order_words', '字数最多'),
                        'vip_sales_amount' => t_admin('adm_diy_order_vip_amount', 'VIP销售额'), 'vip_sales_count' => t_admin('adm_diy_order_vip_count', 'VIP购买次数'),
                        'random' => t_admin('adm_diy_order_random', '随机排序'), 'custom' => t_admin('adm_diy_order_custom', '自定义顺序'),
                    );
                    $orders = Hook::applyFilters('diy_order_options', $orders);
                    foreach ($orders as $v => $l):
                    ?>
                    <option value="<?= $v ?>" <?= $editBlock && $editBlock['order_by'] === $v ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row-2">
                <div class="form-group"><label><?= t_admin('adm_diy_min_words', '最小字数') ?></label><input type="number" name="min_words" class="form-control" value="<?= $editBlock ? (int)$editBlock['min_words'] : 0 ?>"></div>
                <div class="form-group"><label><?= t_admin('adm_diy_max_words', '最大字数(0不限)') ?></label><input type="number" name="max_words" class="form-control" value="<?= $editBlock ? (int)$editBlock['max_words'] : 0 ?>"></div>
            </div>

            <div class="form-group"><label><?= t_admin('adm_diy_cache_time', '缓存时间(秒，0不缓存)') ?></label><input type="number" name="cache_time" class="form-control" min="0" value="<?= $editBlock ? (int)$editBlock['cache_time'] : 300 ?>" style="max-width:120px"></div>

            <div class="form-group" id="customIdsField" <?= $editBlock && $editBlock['order_by'] === 'custom' ? '' : 'style="display:none"' ?>>
                <label><?= t_admin('adm_diy_custom_ids', '自定义小说ID（逗号分隔）') ?></label>
                <input type="text" name="custom_ids" class="form-control" value="<?= $editBlock ? htmlspecialchars((string)$editBlock['custom_ids']) : '' ?>" style="max-width:100%" placeholder="123,456,789">
            </div>

            <button class="btn btn-primary"><i class="fas fa-save"></i> <?= t_admin('adm_save', '保存') ?></button>
            <?php if ($editBlock): ?><a href="<?= htmlspecialchars(admin_url('diy_blocks')) ?>" class="btn btn-outline"><?= t_admin('adm_cancel', '取消') ?></a><?php endif; ?>
        </form>
    </div>

    <div class="card" style="margin-bottom:0">
        <h2><?= t_admin('adm_diy_list', '现有区块（%d）', count($blocks)) ?></h2>
        <div class="table-wrap">
        <table class="data">
            <thead><tr><th>ID</th><th><?= t_admin('adm_diy_col_title', '标题') ?></th><th><?= t_admin('adm_diy_col_category', '分类') ?></th><th><?= t_admin('adm_diy_col_tag', '标签') ?></th><th><?= t_admin('adm_diy_col_num', '数量') ?></th><th><?= t_admin('adm_diy_col_order', '排序') ?></th><th><?= t_admin('adm_diy_col_cache', '缓存') ?></th><th><?= t_admin('adm_operation', '操作') ?></th><?= PluginTable::renderHead('admin.diy_blocks') ?></tr></thead>
            <tbody>
            <?php foreach ($blocks as $b): ?>
            <tr>
                <td><?= (int)$b['id'] ?></td>
                <td>
                    <div><?= htmlspecialchars($b['title'] ?: $b['name']) ?></div>
                    <div class="diy-name"><?= htmlspecialchars((string)$b['name']) ?></div>
                </td>
                <td style="font-size:12.5px">
                    <?php
                    if (empty($b['category_id']) || $b['category_id'] === '0') {
                        echo t_admin('adm_diy_all_category', '全部分类');
                    } else {
                        $ids = array_filter(array_map('intval', explode(',', $b['category_id'])));
                        $names = array();
                        foreach ($categories as $cat) {
                            if (in_array((int)$cat['id'], $ids, true)) $names[] = $cat['name'];
                        }
                        echo htmlspecialchars(implode(' / ', $names) ?: $b['category_id']);
                    }
                    ?>
                </td>
                <td style="font-size:12.5px">
                    <?php
                    if (empty($b['tag_id']) || $b['tag_id'] === '0') {
                        echo t_admin('adm_diy_all_tag', '不限标签');
                    } else {
                        $tids = array_filter(array_map('intval', explode(',', $b['tag_id'])));
                        $tnames = array();
                        foreach ($allTags as $tg) {
                            if (in_array((int)$tg['id'], $tids, true)) $tnames[] = $tg['name'];
                        }
                        echo htmlspecialchars(implode(' / ', $tnames) ?: $b['tag_id']);
                    }
                    ?>
                </td>
                <td><?= (int)$b['num'] ?></td>
                <td><?= htmlspecialchars((string)$b['order_by']) ?></td>
                <td><?= (int)$b['cache_time'] ?>s</td>
                <td style="white-space:nowrap">
                    <a href="<?= htmlspecialchars(admin_url('diy_blocks', ['edit' => (int)$b['id']])) ?>" class="btn btn-sm btn-outline" title="<?= t_admin('adm_edit', '编辑') ?>"><i class="fas fa-edit"></i></a>
                    <a href="<?= htmlspecialchars(admin_url('diy_blocks', ['refresh' => (int)$b['id'], '_csrf' => Security::csrfToken()])) ?>" class="btn btn-sm btn-warning" data-confirm="<?= t_admin('adm_diy_refresh_confirm', '刷新缓存？') ?>"><i class="fas fa-sync-alt"></i></a>
                    <a href="<?= htmlspecialchars(admin_url('diy_blocks', ['delete' => (int)$b['id'], '_csrf' => Security::csrfToken()])) ?>" class="btn btn-sm btn-danger" data-confirm="<?= t_admin('adm_confirm_delete', '确认删除？') ?>"><i class="fas fa-trash"></i></a>
                </td>
            <?= PluginTable::renderCells('admin.diy_blocks', $b) ?></tr>
            <?php endforeach; ?>
            <?php if (empty($blocks)): ?><tr><td colspan="<?= 8 + PluginTable::count('admin.diy_blocks') ?>" class="empty"><?= t_admin('adm_diy_empty', '暂无区块') ?></td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<style>
.multi-cat { border:1px solid var(--border);border-radius:8px;max-height:170px;overflow-y:auto;padding:8px 10px;background:#fafbfc; }
.multi-cat label { display:flex;align-items:center;gap:6px;font-weight:400;font-size:13px;padding:3px;cursor:pointer;margin-bottom:0; }
.multi-cat input { width:auto;margin:0; }
.diy-name { margin-top:2px;font-size:12px;color:#8a94a6;font-family:ui-monospace,Consolas,monospace; }
@media (max-width: 900px) { .diy-layout { grid-template-columns:1fr !important; } }
</style>
<script>
document.getElementById('diyOrder').addEventListener('change', function() {
    document.getElementById('customIdsField').style.display = this.value === 'custom' ? 'block' : 'none';
});
</script>
<script src="/assets/js/tag_picker.js"></script>
