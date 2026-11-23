{include file="header.tpl"}
<div class="user-center-layout">
    {include file="user/_sidebar.tpl"}
    <div class="user-content">
        <h2><i class="fas fa-list"></i> {t name="chapter_manage_title"} - {$novel.title}</h2>
        {if $chapterResult}<div data-ui-toast data-type="{if $chapterResult.ok}success{else}error{/if}" data-msg="{$chapterResult.msg}" style="display:none"></div>{/if}
        <div class="page-card">
            <div class="page-action-bar">
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    {if $perm.can_manage_chapter}
                    <a href="{url route="authorcenter" action="chapter_add"}?novel_id={$novel.id}" class="btn-primary"><i class="fas fa-plus"></i> {t name="add_chapter_title"}</a>
                    {/if}
                    <a href="{url route="authorcenter" action="volume_list"}?novel_id={$novel.id}" class="btn-outline"><i class="fas fa-layer-group"></i> {t name="volume_manage_title"}</a>
                    <a href="{url route="authorcenter" action="novel_edit"}?id={$novel.id}" class="btn-outline"><i class="fas fa-pen-to-square"></i> {t name="novel_info"}</a>
                </div>
                <a href="{url route="authorcenter" action="works"}" class="btn-outline">{t name="back_to_work_list"}</a>
            </div>

            {if $all_chapters|@count > 1 && $perm.can_reorder}
            <form method="post" action="{url route="authorcenter" action="chapter_move"}" class="move-panel">
                {csrf_field}
                <div class="move-panel-title"><i class="fas fa-arrows-up-down"></i> {t name="reorder_chapters"}</div>
                <div class="move-panel-row">
                    <label>{t name="move_from_label"}</label>
                    <select name="chapter_id" required>
                        {foreach $all_chapters as $ai => $ac}
                        {assign var=cn value=$ai+1}
                        <option value="{$ac.id}">{'chapter_option_fmt'|t|sprintf:$cn:$ac.title}</option>
                        {/foreach}
                    </select>
                    <label>{t name="move_to_label"}</label>
                    <select name="target_id" required>
                        {foreach $all_chapters as $ai => $ac}
                        {assign var=cn value=$ai+1}
                        <option value="{$ac.id}">{'chapter_option_fmt'|t|sprintf:$cn:$ac.title}</option>
                        {/foreach}
                    </select>
                    <label>{t name="move_of_label"}</label>
                    <label class="move-radio"><input type="radio" name="position" value="before" checked> {t name="before_word"}</label>
                    <label class="move-radio"><input type="radio" name="position" value="after"> {t name="after_word"}</label>
                    <button type="submit" class="move-confirm-btn">{t name="confirm_word"}</button>
                </div>
                <div class="move-hint">{t name="move_hint"}</div>
            </form>
            {/if}

            <div class="table-responsive chapter-table-wrap">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>{t name="chapter_title_required"}</th>
                        <th>{t name="word_count_label"}</th>
                        <th>{t name="vip_label"}</th>
                        <th>{t name="price_label"}</th>
                        <th style="width:200px;">{t name="operation"}</th>
                    </tr>
                    </thead>
                    <tbody>
                    {assign var=gpos value=$offset}
                    {foreach $chapters_by_volume as $group}
                    {if $group.show_header}
                    <tr class="volume-head-row">
                        <td colspan="5"><i class="fas fa-layer-group"></i> {$group.volume.title}<span class="volume-ch-count">{'chapters_count_in_volume_fmt'|t|sprintf:$group.total_count}</span></td>
                    </tr>
                    {/if}
                    {foreach $group.chapters as $c}
                    <tr>
                        <td>{$c.title}</td>
                        <td>{$c.word_count|number_format}{t name="words_unit"}</td>
                        <td>{if $c.is_vip}<span class="vip-tag">VIP</span>{else}-{/if}</td>
                        <td>{if $c.price}{$c.price}{else}{t name="default_label"}{/if}</td>
                        <td class="op-cell">
                            {if $perm.can_reorder || $perm.can_manage_chapter}
                            <div class="op-group">
                                {if $perm.can_reorder}
                                <form method="post" action="{url route="authorcenter" action="chapter_move"}" class="op-sort">
                                    {csrf_field}
                                    <input type="hidden" name="chapter_id" value="{$c.id}">
                                    <input type="hidden" name="direction" value="up">
                                    <button type="submit" class="op-btn op-sort-btn" title="{t name="move_up"}"{if $vol_pos[$c.id] === 0} disabled{/if}><i class="fas fa-chevron-up"></i></button>
                                </form>
                                <form method="post" action="{url route="authorcenter" action="chapter_move"}" class="op-sort">
                                    {csrf_field}
                                    <input type="hidden" name="chapter_id" value="{$c.id}">
                                    <input type="hidden" name="direction" value="down">
                                    <button type="submit" class="op-btn op-sort-btn" title="{t name="move_down"}"{if $vol_pos[$c.id] === $volume_total[$c.volume_id]-1} disabled{/if}><i class="fas fa-chevron-down"></i></button>
                                </form>
                                {/if}
                                {if $perm.can_reorder && $perm.can_manage_chapter}
                                <span class="op-divider"></span>
                                {/if}
                                {if $perm.can_manage_chapter}
                                <a href="{url route="authorcenter" action="chapter_edit"}?id={$c.id}" class="op-btn op-edit" title="{t name="edit_action"}"><i class="fas fa-edit"></i></a>
                                <button type="button" class="op-btn op-del ch-delete-open" data-chapter-id="{$c.id}" data-chapter-title="{$c.title}" title="{t name="delete_action"}"><i class="fas fa-trash"></i></button>
                                {/if}
                            </div>
                            {else}
                            <i class="fas fa-lock op-locked" title="{t name="content_locked"}"></i>
                            {/if}
                        </td>
                    </tr>
                    {assign var=gpos value=$gpos+1}
                    {/foreach}
                    {foreachelse}
                    <tr><td colspan="5"><div class="empty-tip"><p>{t name="no_chapters"}</p></div></td></tr>
                    {/foreach}
                    </tbody>
                </table>
            </div>

            {if $pages > 1}
            <div class="pagination">
                {if $page > 1}
                    <a class="page-link" href="{url route="authorcenter" action="chapter_list"}?novel_id={$novel.id}&page={$page-1}">&laquo; {t name="prev_page"}</a>
                {else}
                    <span class="page-link disabled">&laquo; {t name="prev_page"}</span>
                {/if}
                {section name=p start=1 loop=$pages+1}
                    {if $smarty.section.p.iteration == $page}
                        <span class="page-link current">{$smarty.section.p.iteration}</span>
                    {else}
                        <a class="page-link" href="{url route="authorcenter" action="chapter_list"}?novel_id={$novel.id}&page={$smarty.section.p.iteration}">{$smarty.section.p.iteration}</a>
                    {/if}
                {/section}
                {if $page < $pages}
                    <a class="page-link" href="{url route="authorcenter" action="chapter_list"}?novel_id={$novel.id}&page={$page+1}">{t name="next_page"} &raquo;</a>
                {else}
                    <span class="page-link disabled">{t name="next_page"} &raquo;</span>
                {/if}
                <span class="page-info">{'list_pagination_fmt'|t|sprintf:$total:$pages}</span>
            </div>
            {/if}
        </div>
    </div>
</div>

<style>
.action-link { font-size: 0.82rem; color: #b8860b; text-decoration: none; white-space: nowrap; display: inline-flex; align-items: center; gap: 4px; margin-right: 10px; }

.volume-head-row td {
    background: linear-gradient(90deg, #f3ead6, #faf7f2);
    border-left: 3px solid #b8860b;
    color: #6b5410; font-weight: 600; font-size: 13px;
    padding: 9px 14px; letter-spacing: .3px;
}
.volume-head-row i { color: #b8860b; margin-right: 6px; }
.volume-ch-count { font-weight: 400; color: #9a8a63; font-size: 12px; margin-left: 4px; }

.op-cell { text-align: center; }
.op-group { display: inline-flex; align-items: center; gap: 2px; padding: 4px 6px; background: #faf7f2; border-radius: 6px; }
.op-sort { display: inline-flex; margin: 0; padding: 0; }
.op-btn {
    width: 26px; height: 26px;
    display: inline-flex; align-items: center; justify-content: center;
    background: transparent; border: none; border-radius: 4px;
    color: #666; font-size: 12px; cursor: pointer;
    text-decoration: none; transition: all .15s;
}
.op-btn:hover { background: #fff; color: #b8860b; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
.op-btn:disabled { opacity: .3; cursor: not-allowed; }
.op-btn:disabled:hover { background: transparent; color: #666; box-shadow: none; }
.op-del:hover { color: #a83232 !important; }
.op-divider { width: 1px; height: 16px; background: #ddd; margin: 0 2px; }
.op-locked { color: #bbb; font-size: 13px; padding: 6px; }

.move-panel { background: #faf7f2; border: 1px solid #eee; border-radius: 6px; padding: 14px 16px; margin-bottom: 16px; }
.move-panel-title { font-size: 13px; font-weight: 600; color: #333; margin-bottom: 10px; }
.move-panel-row { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
.move-panel-row label { font-size: 13px; color: #555; margin: 0; }
.move-panel-row select { flex: 1 1 140px; min-width: 0; padding: 7px 10px; border: 1px solid #ddd; border-radius: 4px; background: #fff; font-size: 13px; max-width: 220px; }
.move-radio { display: inline-flex; align-items: center; gap: 4px; cursor: pointer; flex: 0 0 auto; }
.move-hint { margin-top: 8px; font-size: 12px; color: #999; line-height: 1.5; }
.move-panel-row .move-confirm-btn {
    display: inline-flex; align-items: center; justify-content: center;
    flex: 0 0 auto; width: auto; margin: 0;
    padding: 7px 20px; font-size: 13px; line-height: 1;
    background: #333; color: #fff; border: 1px solid #333; border-radius: 3px;
    cursor: pointer; white-space: nowrap; transition: background .15s;
}
.move-panel-row .move-confirm-btn:hover { background: #000; }

.chapter-table-wrap { margin-top: 18px; }

.pagination { display: flex; align-items: center; gap: 6px; margin-top: 16px; flex-wrap: wrap; }
.page-link {
    display: inline-block; padding: 6px 12px; border: 1px solid #e5e5e5; border-radius: 4px;
    font-size: 13px; color: #555; text-decoration: none; background: #fff;
}
.page-link:hover { border-color: #b8860b; color: #b8860b; }
.page-link.current { background: #333; color: #fff; border-color: #333; }
.page-link.disabled { color: #bbb; cursor: not-allowed; background: #fafafa; }
.page-link.disabled:hover { border-color: #e5e5e5; color: #bbb; }
.page-info { margin-left: 10px; font-size: 12px; color: #999; }

.page-action-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; gap: 8px; }
</style>
<div id="chUiConfig" data-captcha="{$captcha_enabled ? '1' : '0'}" data-delete-url="{url route='authorcenter' action='chapter_delete'}" style="display:none"></div>
<script>
window.I18N = {
    deleteChapterConfirm: '{t name="delete_chapter_confirm_fmt"}',
    deleteChapterTitle: '{t name="delete_chapter_title"}',
    confirmDelete: '{t name="confirm_delete"}',
    deletedLabel: '{t name="deleted_label"}',
    deleteFailed: '{t name="delete_failed"}',
    networkError: '{t name="network_error_retry"}'
};
</script>
<script>
{literal}
document.addEventListener('DOMContentLoaded', function() {
    const captchaEnabled = document.getElementById('chUiConfig').dataset.captcha === '1';
    const deleteUrl = document.getElementById('chUiConfig').dataset.deleteUrl;
    function openDeleteModal(chapterId, chapterTitle) {
        const fields = [
            { type: 'html', html: '<div style="font-size:13px;color:#666;line-height:1.7;">' + window.I18N.deleteChapterConfirm.replace('%s', chapterTitle) + '</div>' }
        ];
        if (captchaEnabled) fields.push({ type: 'captcha', name: 'captcha' });
        UI.form({
            title: window.I18N.deleteChapterTitle, titleIcon: 'fa-triangle-exclamation', okText: window.I18N.confirmDelete,
            fields: fields,
            onSubmit: function(v) {
                const csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                let body = 'chapter_id=' + encodeURIComponent(chapterId) + '&ajax=1&_csrf=' + encodeURIComponent(csrf);
                if (captchaEnabled) body += '&captcha=' + encodeURIComponent(v.captcha || '');
                return fetch(deleteUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body
                }).then(r => r.json()).then(function(d) {
                    if (d.ok) {
                        UI.toast(d.msg || window.I18N.deletedLabel, 'success');
                        setTimeout(function() { location.reload(); }, 1000);
                        return true;
                    }
                    UI.alert({ message: d.msg || window.I18N.deleteFailed, type: 'error' });
                    const img = document.querySelector('.ui-modal img.ui-captcha-img');
                    const cap = document.querySelector('.ui-modal input[name="captcha"]');
                    if (img) img.src = '/captcha.php?' + Date.now();
                    if (cap) cap.value = '';
                    return false;
                }).catch(function() {
                    UI.alert({ message: window.I18N.networkError, type: 'error' });
                    return false;
                });
            }
        });
    }
    document.querySelectorAll('.ch-delete-open').forEach(btn => {
        btn.addEventListener('click', function() {
            openDeleteModal(this.dataset.chapterId, this.dataset.chapterTitle || '');
        });
    });
});
{/literal}
</script>
{include file="footer.tpl"}
