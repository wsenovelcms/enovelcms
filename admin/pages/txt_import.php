<?php

if (!defined('ADMIN_BOOT')) exit;
$pageTitle = t_admin('adm_txt_import', 'TXT 一键导入');

@set_time_limit(0);
@ini_set('memory_limit', '512M');

$importService = new TxtImportService();

$message = '';
$error = '';
$selectedNovel = 0;
$selectedNovelTitle = '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && g('novel_id', null, 'raw') !== null) {
    $preId = g('novel_id', 0, 'int');
    if ($preId > 0) {
        $preNovel = NovelModel::findById($preId);
        if ($preNovel) {
            $selectedNovel = $preId;
            $selectedNovelTitle = $preNovel['title'];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && p('import_txt', null, 'raw') !== null) {
    if (!Security::verifyCsrf()) {
        $error = t_admin('adm_csrf_failed', 'CSRF验证失败，请刷新页面重试');
    }

    $novelId = Security::post('novel_id', 0, 'int');
    $selectedNovel = $novelId;
    $novel = $novelId > 0 ? NovelModel::findById($novelId) : null;

    if ($error === '') {
        if (!$novel) {
            $error = t_admin('adm_txt_select_novel', '请选择有效的小说');
        } elseif (!isset($_FILES['txt_file'])) {
            $error = t_admin('adm_txt_upload_file', '请上传TXT文件');
        } elseif ($_FILES['txt_file']['error'] !== UPLOAD_ERR_OK) {
            $error = t_admin('adm_txt_upload_fail', '上传失败：%s', uploadErrorText($_FILES['txt_file']['error']));
        } else {
            $txtExt = strtolower(pathinfo((string)($_FILES['txt_file']['name'] ?? ''), PATHINFO_EXTENSION));
            if (!in_array($txtExt, array('txt'), true)) {
                $error = t_admin('adm_txt_ext_only', '仅允许上传 .txt 文本文件');
            } elseif (($_FILES['txt_file']['size'] ?? 0) > 20 * 1024 * 1024) {
                $error = t_admin('adm_txt_size_limit', 'TXT 文件大小不能超过 20MB');
            }
        }
    }

    $chapters = array();
    if ($error === '') {
        $raw = file_get_contents($_FILES['txt_file']['tmp_name']);
        $norm = $importService->normalizeEncoding($raw);
        if (!$norm['ok']) {
            $error = $norm['error'];
        } else {
            $content = $norm['content'];
            $mode = Security::post('mode', 'auto');
            $importMode = Security::post('import_mode', 'append');
            if (!in_array($importMode, array('append', 'overwrite'), true)) $importMode = 'append';

            if ($mode === 'custom') {
                $titleStart = Security::post('title_start', '', 'raw');
                $titleEnd = Security::post('title_end', '', 'raw');
                $contentStart = Security::post('content_start', '', 'raw');
                $contentEnd = Security::post('content_end', '', 'raw');
                if ($contentStart === '' || $contentEnd === '') {
                    $error = t_admin('adm_txt_tags_required', '请填写章节内容的开始标记和结束标记');
                } else {
                    $chapters = $importService->splitByTags($content, $titleStart, $titleEnd, $contentStart, $contentEnd);
                }
            } else {
                $targetWords = Security::post('target_words', 2000, 'int');
                $maxExtra = Security::post('max_extra', 200, 'int');
                if ($targetWords < 500) $targetWords = 2000;
                if ($maxExtra < 0) $maxExtra = 200;
                $chapters = $importService->splitByAuto($content, $targetWords, $maxExtra);
            }

            if ($error === '' && empty($chapters)) {
                $error = t_admin('adm_txt_no_chapter', '未能提取到任何章节内容，请检查文件内容或分隔符设置');
            }

            if ($error === '') {
                $result = $importService->import($novelId, $chapters, $importMode);
                if (!$result['ok']) {
                    $error = $result['message'];
                } else {
                    $message = $result['message'];
                    $selectedNovelTitle = $novel['title'] ?? '';
                }
            }
        }
    }

    $flashMsg = $error !== '' ? $error : $message;
    admin_flash_redirect($flashMsg, 'txt_import', ['novel_id' => (int)$selectedNovel]);
}

if (g('ajax_novel_search', null, 'raw') !== null) {
    header('Content-Type: application/json; charset=utf-8');
    $q = trim((string)(g('q', '', 'str')));
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 10;
    $out = array('list' => array(), 'page' => $page, 'total' => 0, 'totalPages' => 0);
    if ($q === '') {
        $rows = NovelModel::all(array(), 'id DESC', $perPage, ($page - 1) * $perPage, 'id, title, author');
        $out['total'] = NovelModel::count();
        $out['totalPages'] = (int)max(1, ceil($out['total'] / $perPage));
    } else {
        $res = NovelModel::search($q, $page, $perPage);
        $rows = $res['list'];
        $out['total'] = (int)$res['total'];
        $out['totalPages'] = (int)$res['totalPages'];
    }
    foreach ($rows as $r) {
        $out['list'][] = array('id' => (int)$r['id'], 'title' => $r['title'], 'author' => $r['author']);
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

$novels = NovelModel::all(array(), 'id DESC', 0, 0, 'id, title, author');

function uploadErrorText(int $code): string
{
    $map = array(
        UPLOAD_ERR_INI_SIZE   => t_admin('adm_txt_err_ini_size', '文件大小超过 php.ini 中 upload_max_filesize 限制（当前：%s）', ini_get('upload_max_filesize')),
        UPLOAD_ERR_FORM_SIZE  => t_admin('adm_txt_err_form_size', '文件大小超过表单限制'),
        UPLOAD_ERR_PARTIAL    => t_admin('adm_txt_err_partial', '文件只上传了一部分'),
        UPLOAD_ERR_NO_FILE    => t_admin('adm_txt_err_no_file', '没有选择文件'),
        UPLOAD_ERR_NO_TMP_DIR => t_admin('adm_txt_err_no_tmp_dir', '临时目录不存在'),
        UPLOAD_ERR_CANT_WRITE => t_admin('adm_txt_err_cant_write', '写入磁盘失败'),
        UPLOAD_ERR_EXTENSION  => t_admin('adm_txt_err_extension', '扩展阻止了上传'),
    );
    return $map[$code] ?? t_admin('adm_txt_err_unknown', '未知错误');
}

if ($selectedNovel > 0 && !$selectedNovelTitle) {
    $n = NovelModel::findById($selectedNovel);
    if ($n) $selectedNovelTitle = $n['title'];
}
?>
<div class="import-layout" style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start;">
    <div>
        <form method="post" enctype="multipart/form-data" id="importForm">
            <?= Security::csrfField() ?>
            <input type="hidden" name="import_txt" value="1">

            <div class="card">
                <h2><i class="fas fa-book"></i> <?= t_admin('adm_txt_choose_novel_file', '选择小说 &amp; 上传文件') ?></h2>
                <div class="form-group">
                    <label><?= t_admin('adm_txt_target_novel', '目标小说 *') ?></label>
                    <div id="novelPicked" style="display:none;align-items:center;gap:10px;padding:10px 12px;border:1px solid #d1fae5;background:#ecfdf5;border-radius:8px;margin-bottom:8px;">
                        <i class="fas fa-book" style="color:#10b981;"></i>
                        <span id="novelPickedLabel" style="flex:1;font-size:14px;color:#065f46;"></span>
                        <button type="button" id="btnChangeNovel" class="btn" style="white-space:nowrap;padding:4px 10px;font-size:12px;"><i class="fas fa-exchange-alt"></i> <?= t_admin('adm_txt_change', '更换') ?></button>
                    </div>
                    <input type="hidden" name="novel_id" id="novelId" value="<?= (int)$selectedNovel ?>">
                    <button type="button" id="btnPickNovel" class="btn btn-primary"><i class="fas fa-search"></i> <?= t_admin('adm_txt_pick_novel', '搜索并选择小说') ?></button>
                </div>
                <div class="form-group">
                    <label><?= t_admin('adm_txt_file_label', 'TXT 文件 *') ?></label>
                    <input type="file" name="txt_file" accept=".txt" required>
                </div>
            </div>

            <div class="card">
                <h2><i class="fas fa-cogs"></i> <?= t_admin('adm_txt_split_way', '章节分割方式') ?></h2>
                <div class="mode-tabs">
                    <div class="mode-tab active" data-mode="auto"><i class="fas fa-wand-magic-sparkles"></i> <?= t_admin('adm_txt_split_auto', '自动按字数分割') ?></div>
                    <div class="mode-tab" data-mode="custom"><i class="fas fa-tags"></i> <?= t_admin('adm_txt_split_custom', '自定义标签分割') ?></div>
                </div>
                <input type="hidden" name="mode" id="modeField" value="auto">

                <div class="mode-panel active" id="panel-auto">
                    <div class="form-group">
                        <label><?= t_admin('adm_txt_target_words', '每章目标字数') ?></label>
                        <input type="number" name="target_words" id="inpTargetWords" class="form-control" value="2000" min="500" style="max-width:220px;">
                    </div>
                    <div class="form-group">
                        <label><?= t_admin('adm_txt_max_extra', '最大允许超出字数') ?></label>
                        <input type="number" name="max_extra" id="inpMaxExtra" class="form-control" value="200" min="0" style="max-width:220px;">
                    </div>
                </div>

                <div class="mode-panel" id="panel-custom">
                    <div class="form-group">
                        <label><?= t_admin('adm_txt_title_start', '章节名：开始标记（可选）') ?></label>
                        <input type="text" name="title_start" id="inpTitleStart" class="form-control" placeholder="<?= t_admin('adm_txt_ph_title_start', '例如：【章节名】') ?>">
                    </div>
                    <div class="form-group">
                        <label><?= t_admin('adm_txt_title_end', '章节名：结束标记（可选）') ?></label>
                        <input type="text" name="title_end" id="inpTitleEnd" class="form-control" placeholder="<?= t_admin('adm_txt_ph_title_end', '例如：【/章节名】') ?>">
                    </div>
                    <div class="form-group">
                        <label><?= t_admin('adm_txt_content_start', '章节内容：开始标记 *') ?></label>
                        <input type="text" name="content_start" id="inpContentStart" class="form-control" placeholder="<?= t_admin('adm_txt_ph_content_start', '例如：【章节内容】') ?>">
                    </div>
                    <div class="form-group">
                        <label><?= t_admin('adm_txt_content_end', '章节内容：结束标记 *') ?></label>
                        <input type="text" name="content_end" id="inpContentEnd" class="form-control" placeholder="<?= t_admin('adm_txt_ph_content_end', '例如：【/章节内容】') ?>">
                    </div>
                    <div class="example-box"><?= t_admin('adm_txt_example_body') ?></div>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:10px;"><i class="fas fa-upload"></i> <?= t_admin('adm_txt_start_import', '开始导入') ?></button>
            </div>
        </form>
    </div>

    <div>
        <div class="card">
            <h2><i class="fas fa-exchange-alt"></i> <?= t_admin('adm_txt_import_mode', '导入方式') ?></h2>
            <div class="radio-inline" style="display:flex;flex-direction:column;gap:10px;">
                <label style="display:flex;align-items:center;gap:6px;padding:8px;border-radius:6px;border:1px solid #e2e8f0;cursor:pointer;">
                    <input type="radio" name="import_mode" value="append" checked>
                    <span><strong><?= t_admin('adm_txt_mode_append', '追加导入') ?></strong></span>
                </label>
                <label style="display:flex;align-items:center;gap:6px;padding:8px;border-radius:6px;border:1px solid #e2e8f0;cursor:pointer;">
                    <input type="radio" name="import_mode" value="overwrite">
                    <span><strong><?= t_admin('adm_txt_mode_overwrite', '覆盖导入') ?></strong><br><small class="help-text"><?= t_admin('adm_txt_mode_overwrite_tip', '清空原章节后重新导入（不可恢复）') ?></small></span>
                </label>
            </div>
        </div>
    </div>
</div>

<div id="novelModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:10px;width:560px;max-width:92vw;max-height:85vh;display:flex;flex-direction:column;box-shadow:0 12px 40px rgba(0,0,0,.25);">
        <div style="padding:16px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
            <h3 style="margin:0;font-size:16px;"><i class="fas fa-search"></i> <?= t_admin('adm_txt_modal_title', '选择小说') ?></h3>
            <button type="button" class="btn" id="btnCloseModal" style="padding:4px 10px;"><i class="fas fa-times"></i></button>
        </div>
        <div style="padding:14px 20px;border-bottom:1px solid #f1f5f9;">
            <input type="text" id="novelSearchInput" class="form-control" placeholder="<?= t_admin('adm_txt_search_ph', '输入 ID / 书名 / 作者关键字，回车搜索') ?>">
        </div>
        <div id="novelList" style="flex:1;overflow-y:auto;padding:6px 10px;">
            <div style="padding:20px;text-align:center;color:#94a3b8;"><?= t_admin('adm_txt_loading', '加载中…') ?></div>
        </div>
        <div id="novelPager" style="padding:10px 20px;border-top:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center;font-size:13px;color:#64748b;">
            <span id="novelPagerInfo"></span>
            <span style="display:flex;gap:6px;">
                <button type="button" id="novelPrev" class="btn" style="padding:4px 10px;font-size:12px;"><i class="fas fa-chevron-left"></i> <?= t_admin('adm_txt_prev', '上一页') ?></button>
                <button type="button" id="novelNext" class="btn" style="padding:4px 10px;font-size:12px;"><?= t_admin('adm_txt_next', '下一页') ?> <i class="fas fa-chevron-right"></i></button>
            </span>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.mode-tab').forEach(function (tab) {
    tab.addEventListener('click', function () {
        var mode = this.dataset.mode;
        document.querySelectorAll('.mode-tab').forEach(function (t) { t.classList.remove('active'); });
        this.classList.add('active');
        document.getElementById('modeField').value = mode;
        document.querySelectorAll('.mode-panel').forEach(function (p) { p.classList.remove('active'); });
        document.getElementById('panel-' + mode).classList.add('active');
        savePrefs();
    });
});

document.getElementById('importForm').addEventListener('submit', function (e) {
    if (!document.getElementById('novelId').value || document.getElementById('novelId').value === '0') {
        e.preventDefault();
        UI.alert({ message: <?= json_encode(t_admin('adm_txt_pick_first_alert', '请先点击「搜索并选择小说」选择目标小说')) ?>, type: 'error' });
        return;
    }
    var checked = document.querySelector('input[name="import_mode"]:checked');
    if (checked && checked.value === 'overwrite') {
        e.preventDefault();
        UI.confirm({ message: <?= json_encode(t_admin('adm_txt_overwrite_confirm', '覆盖导入会删除该小说现有的全部章节和章节文件，且不可恢复，确定继续？')) ?> }).then(function (ok) {
            if (!ok) return;
            savePrefs();
            document.getElementById('importForm').submit();
        });
        return;
    }
    savePrefs();
});

var PREF_KEY = 'txt_import_prefs';
function savePrefs() {
    var data = {
        mode: document.getElementById('modeField').value,
        target_words: document.getElementById('inpTargetWords').value,
        max_extra: document.getElementById('inpMaxExtra').value,
        title_start: document.getElementById('inpTitleStart').value,
        title_end: document.getElementById('inpTitleEnd').value,
        content_start: document.getElementById('inpContentStart').value,
        content_end: document.getElementById('inpContentEnd').value,
        import_mode: (document.querySelector('input[name="import_mode"]:checked') || {}).value || 'append'
    };
    try { localStorage.setItem(PREF_KEY, JSON.stringify(data)); } catch (e) {}
}
function loadPrefs() {
    var raw = null;
    try { raw = localStorage.getItem(PREF_KEY); } catch (e) {}
    if (!raw) return;
    try {
        var d = JSON.parse(raw);
        if (d.target_words) document.getElementById('inpTargetWords').value = d.target_words;
        if (d.max_extra) document.getElementById('inpMaxExtra').value = d.max_extra;
        if (d.title_start !== undefined) document.getElementById('inpTitleStart').value = d.title_start;
        if (d.title_end !== undefined) document.getElementById('inpTitleEnd').value = d.title_end;
        if (d.content_start !== undefined) document.getElementById('inpContentStart').value = d.content_start;
        if (d.content_end !== undefined) document.getElementById('inpContentEnd').value = d.content_end;
        if (d.mode) {
            document.getElementById('modeField').value = d.mode;
            document.querySelectorAll('.mode-tab').forEach(function (t) {
                t.classList.toggle('active', t.dataset.mode === d.mode);
            });
            document.querySelectorAll('.mode-panel').forEach(function (p) {
                p.classList.toggle('active', p.id === 'panel-' + d.mode);
            });
        }
        if (d.import_mode) {
            var radio = document.querySelector('input[name="import_mode"][value="' + d.import_mode + '"]');
            if (radio) radio.checked = true;
        }
    } catch (e) {}
}
['inpTargetWords','inpMaxExtra','inpTitleStart','inpTitleEnd','inpContentStart','inpContentEnd'].forEach(function (id) {
    document.getElementById(id).addEventListener('change', savePrefs);
});
document.querySelectorAll('input[name="import_mode"]').forEach(function (r) {
    r.addEventListener('change', savePrefs);
});
loadPrefs();

var modal = document.getElementById('novelModal');
var listEl = document.getElementById('novelList');
var searchInput = document.getElementById('novelSearchInput');
var searchState = { q: '', page: 1, totalPages: 1 };
document.getElementById('btnPickNovel').addEventListener('click', function () {
    modal.style.display = 'flex';
    searchInput.value = '';
    searchState.q = '';
    searchState.page = 1;
    searchInput.focus();
    doSearch();
});
document.getElementById('btnCloseModal').addEventListener('click', function () { modal.style.display = 'none'; });
modal.addEventListener('click', function (e) { if (e.target === modal) modal.style.display = 'none'; });

document.getElementById('novelPrev').addEventListener('click', function () {
    if (searchState.page > 1) { searchState.page--; doSearch(); }
});
document.getElementById('novelNext').addEventListener('click', function () {
    if (searchState.page < searchState.totalPages) { searchState.page++; doSearch(); }
});

var searchTimer = null;
searchInput.addEventListener('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function () {
        searchState.q = searchInput.value.trim();
        searchState.page = 1;
        doSearch();
    }, 250);
});
searchInput.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        searchState.q = searchInput.value.trim();
        searchState.page = 1;
        doSearch();
    }
});

function doSearch() {
    listEl.innerHTML = '<div style="padding:20px;text-align:center;color:#94a3b8;">' + <?= json_encode(t_admin('adm_txt_searching', '搜索中…')) ?> + '</div>';
    var url = <?= json_encode(admin_url('txt_import', ['ajax_novel_search' => 1])) ?> + '&q=' + encodeURIComponent(searchState.q) + '&page=' + searchState.page;
    fetch(url, { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            searchState.totalPages = res.totalPages || 1;
            var rows = res.list || [];
            if (!rows.length) {
                listEl.innerHTML = '<div style="padding:20px;text-align:center;color:#94a3b8;">' + <?= json_encode(t_admin('adm_txt_no_match', '没有匹配的小说')) ?> + '</div>';
                document.getElementById('novelPagerInfo').textContent = '';
                return;
            }
            var html = '';
            rows.forEach(function (n) {
                html += '<div class="novel-item" data-id="' + n.id + '" data-title="' + escapeAttr(n.title) + '" style="padding:10px 12px;border-radius:6px;cursor:pointer;display:flex;justify-content:space-between;align-items:center;">'
                    + '<span><strong>#' + n.id + '</strong> ' + escapeHtml(n.title) + ' <small style="color:#94a3b8;">—— ' + escapeHtml(n.author) + '</small></span>'
                    + '<i class="fas fa-chevron-right" style="color:#cbd5e1;font-size:12px;"></i></div>';
            });
            listEl.innerHTML = html;
            listEl.querySelectorAll('.novel-item').forEach(function (row) {
                row.addEventListener('mouseenter', function () { row.style.background = '#f1f5f9'; });
                row.addEventListener('mouseleave', function () { row.style.background = ''; });
                row.addEventListener('click', function () {
                    pickNovel(this.dataset.id, this.dataset.title);
                    modal.style.display = 'none';
                });
            });
            var pageTpl = <?= json_encode(t_admin('adm_txt_page_line', '第 %d / %d 页，共 %s 本')) ?>;
            document.getElementById('novelPagerInfo').textContent = pageTpl.replace('%d', searchState.page).replace('%d', searchState.totalPages).replace('%s', res.total);
            document.getElementById('novelPrev').disabled = searchState.page <= 1;
            document.getElementById('novelNext').disabled = searchState.page >= searchState.totalPages;
        })
        .catch(function (e) {
            listEl.innerHTML = '<div style="padding:20px;text-align:center;color:#ef4444;">' + <?= json_encode(t_admin('adm_txt_search_fail', '搜索失败，请重试')) ?> + '</div>';
        });
}
function pickNovel(id, title) {
    document.getElementById('novelId').value = id;
    document.getElementById('novelPickedLabel').textContent = '#' + id + ' ' + title;
    document.getElementById('novelPicked').style.display = 'flex';
    document.getElementById('btnPickNovel').style.display = 'none';
}
(function initPicked() {
    var id = document.getElementById('novelId').value;
    if (id && id !== '0') {
        var label = document.getElementById('novelPickedLabel');
        <?php if ($selectedNovel > 0): ?>
        label.textContent = '#' + <?= (int)$selectedNovel ?> + ' ' + <?= json_encode($selectedNovelTitle, JSON_UNESCAPED_UNICODE) ?>;
        document.getElementById('novelPicked').style.display = 'flex';
        document.getElementById('btnPickNovel').style.display = 'none';
        <?php endif; ?>
    }
})();
document.getElementById('btnChangeNovel').addEventListener('click', function () {
    modal.style.display = 'flex';
    searchInput.value = '';
    searchState.q = '';
    searchState.page = 1;
    searchInput.focus();
    doSearch();
});
function escapeHtml(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
function escapeAttr(s) { return String(s).replace(/"/g, '&quot;'); }
</script>
