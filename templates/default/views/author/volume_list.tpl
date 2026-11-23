{include file="header.tpl"}
<div class="user-center-layout">
    {include file="user/_sidebar.tpl"}
    <div class="user-content">
        <h2><i class="fas fa-layer-group"></i> {t name="volume_manage_title"} - {$novel.title}</h2>
        {if $volumeResult}<div data-ui-toast data-type="{if $volumeResult.ok}success{else}error{/if}" data-msg="{$volumeResult.msg}" style="display:none"></div>{/if}
        <style>
        .action-link {
            font-size: 0.82rem; color: #b8860b; text-decoration: none; white-space: nowrap;
            display: inline-flex; align-items: center; gap: 4px;
            background: none; border: none; padding: 0; cursor: pointer; font-family: inherit;
        }
        .action-link:hover { text-decoration: underline; }
        </style>
        <div class="page-card">
            <div class="page-action-bar">
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    {if $perm.can_manage_volume}
                    <a href="{url route="authorcenter" action="volume_add"}?novel_id={$novel.id}" class="btn-primary"><i class="fas fa-plus"></i> {t name="add_volume_title"}</a>
                    {/if}
                    <a href="{url route="authorcenter" action="chapter_list"}?novel_id={$novel.id}" class="btn-outline"><i class="fas fa-list"></i> {t name="chapter_manage_title"}</a>
                    <a href="{url route="authorcenter" action="novel_edit"}?id={$novel.id}" class="btn-outline"><i class="fas fa-pen-to-square"></i> {t name="novel_info"}</a>
                </div>
                <a href="{url route="authorcenter" action="works"}" class="btn-outline">{t name="back_to_work_list"}</a>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>{t name="volume_name_label"}</th>
                        <th>{t name="sort_label"}</th>
                        <th>{t name="chapters_count_label"}</th>
                        <th>{t name="operation"}</th>
                    </tr>
                    </thead>
                    <tbody>
                    {foreach $volumes as $v}
                    <tr>
                        <td>{$v.id}</td>
                        <td>{$v.title}</td>
                        <td>{$v.sort}</td>
                        <td>{$v.chapter_count}</td>
                        <td>{if $perm.can_manage_volume}<a href="{url route="authorcenter" action="volume_edit"}?id={$v.id}" class="action-link"><i class="fas fa-edit"></i> {t name="edit_action"}</a><button type="button" class="action-link vol-del-open" data-id="{$v.id}" data-title="{$v.title}" data-count="{$v.chapter_count}" title="{t name="delete_action"}"><i class="fas fa-trash"></i> {t name="delete_action"}</button>{else}<i class="fas fa-lock" style="color:#bbb;font-size:13px;" title="{t name="content_locked"}"></i>{/if}</td>
                    </tr>
                    {foreachelse}
                    <tr><td colspan="5"><div class="empty-tip"><p>{t name="no_volumes"}</p></div></td></tr>
                    {/foreach}
                    </tbody>
                </table>
            </div>

            {if $pages > 1}
            <div class="pagination">
                {if $page > 1}
                    <a class="page-link" href="{url route="authorcenter" action="volume_list"}?novel_id={$novel.id}&page={$page-1}">&laquo; {t name="prev_page"}</a>
                {else}
                    <span class="page-link disabled">&laquo; {t name="prev_page"}</span>
                {/if}
                {section name=p start=1 loop=$pages+1}
                    {if $smarty.section.p.iteration == $page}
                        <span class="page-link current">{$smarty.section.p.iteration}</span>
                    {else}
                        <a class="page-link" href="{url route="authorcenter" action="volume_list"}?novel_id={$novel.id}&page={$smarty.section.p.iteration}">{$smarty.section.p.iteration}</a>
                    {/if}
                {/section}
                {if $page < $pages}
                    <a class="page-link" href="{url route="authorcenter" action="volume_list"}?novel_id={$novel.id}&page={$page+1}">{t name="next_page"} &raquo;</a>
                {else}
                    <span class="page-link disabled">{t name="next_page"} &raquo;</span>
                {/if}
                <span class="page-info">{'volume_pagination_fmt'|t|sprintf:$total:$pages}</span>
            </div>
            {/if}
        </div>
    </div>
</div>

<select id="volTargetSource" style="display:none">
    {foreach $all_volumes as $av}
    <option value="{$av.id}">{$av.title}</option>
    {/foreach}
</select>
<div id="volUiConfig" data-captcha="{$captcha_enabled ? '1' : '0'}" data-delete-url="{url route='authorcenter' action='volume_delete'}" style="display:none"></div>
<script>
window.I18N = {
    deleteWithChapters: '{t name="volume_delete_with_chapters_fmt"}',
    modeMove: '{t name="mode_move_label"}',
    modeDelete: '{t name="mode_delete_label"}',
    disposeMode: '{t name="dispose_mode_label"}',
    moveToVolume: '{t name="move_to_volume_label"}',
    deleteEmpty: '{t name="volume_delete_empty_fmt"}',
    deleteVolumeTitle: '{t name="delete_volume_title"}',
    confirmDelete: '{t name="confirm_delete"}',
    targetRequired: '{t name="target_volume_required"}',
    deletedLabel: '{t name="deleted_label"}',
    deleteFailed: '{t name="delete_failed"}',
    networkError: '{t name="network_error_retry"}'
};
</script>
<script>
{literal}
document.addEventListener('DOMContentLoaded', function () {
    var captchaEnabled = document.getElementById('volUiConfig').dataset.captcha === '1';
    var deleteUrl = document.getElementById('volUiConfig').dataset.deleteUrl;
    var source = document.getElementById('volTargetSource');

    function targetOptions(excludeId) {
        var opts = [];
        Array.prototype.forEach.call(source.options, function (op) {
            if (parseInt(op.value, 10) !== excludeId) {
                opts.push({ value: op.value, label: op.textContent });
            }
        });
        return opts;
    }

    function openDeleteModal(volumeId, title, count) {
        var others = targetOptions(volumeId);
        var fields = [];
        if (count > 0) {
            fields.push({ type: 'html', html: '<div style="font-size:13px;color:#666;line-height:1.7;">' + window.I18N.deleteWithChapters.replace('%s', title).replace('%d', count) + '</div>' });
            var modeOptions = [];
            if (others.length > 0) modeOptions.push({ value: 'move', label: window.I18N.modeMove });
            modeOptions.push({ value: 'delete', label: window.I18N.modeDelete });
            fields.push({ type: 'select', name: 'mode', label: window.I18N.disposeMode, options: modeOptions });
            if (others.length > 0) {
                fields.push({ type: 'select', name: 'target_volume_id', label: window.I18N.moveToVolume, options: others });
            }
        } else {
            fields.push({ type: 'html', html: '<div style="font-size:13px;color:#666;line-height:1.7;">' + window.I18N.deleteEmpty.replace('%s', title) + '</div>' });
            fields.push({ type: 'hidden', name: 'mode', value: 'delete' });
        }
        if (captchaEnabled) fields.push({ type: 'captcha', name: 'captcha' });

        UI.form({
            title: window.I18N.deleteVolumeTitle, titleIcon: 'fa-triangle-exclamation', okText: window.I18N.confirmDelete,
            fields: fields,
            onSubmit: function (v) {
                var mode = v.mode || 'delete';
                if (count > 0 && mode === 'move') {
                    if (!v.target_volume_id || parseInt(v.target_volume_id, 10) === volumeId) {
                        UI.alert({ message: window.I18N.targetRequired, type: 'error' });
                        return false;
                    }
                }
                var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                var body = 'volume_id=' + encodeURIComponent(volumeId) + '&mode=' + encodeURIComponent(mode)
                    + '&target_volume_id=' + encodeURIComponent(v.target_volume_id || 0)
                    + '&ajax=1&_csrf=' + encodeURIComponent(csrf);
                if (captchaEnabled) body += '&captcha=' + encodeURIComponent(v.captcha || '');
                return fetch(deleteUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body
                }).then(function (r) { return r.json(); }).then(function (d) {
                    if (d.ok) {
                        UI.toast(d.msg || window.I18N.deletedLabel, 'success');
                        setTimeout(function () { location.reload(); }, 1000);
                        return true;
                    }
                    UI.alert({ message: d.msg || window.I18N.deleteFailed, type: 'error' });
                    var img = document.querySelector('.ui-modal img.ui-captcha-img');
                    var cap = document.querySelector('.ui-modal input[name="captcha"]');
                    if (img) img.src = '/captcha.php?' + Date.now();
                    if (cap) cap.value = '';
                    return false;
                }).catch(function () {
                    UI.alert({ message: window.I18N.networkError, type: 'error' });
                    return false;
                });
            }
        });
    }

    document.querySelectorAll('.vol-del-open').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openDeleteModal(parseInt(this.dataset.id, 10), this.dataset.title || '', parseInt(this.dataset.count, 10) || 0);
        });
    });
});
{/literal}
</script>
{include file="footer.tpl"}
