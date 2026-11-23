{include file="header.tpl"}
<div class="user-center-layout">
    {include file="user/_sidebar.tpl"}
    <div class="user-content">
        <h2><i class="fas fa-pen-fancy"></i> {t name="my_works"}</h2>
        {if $novelResult}<div data-ui-toast data-type="{if $novelResult.ok}success{else}error{/if}" data-msg="{$novelResult.msg}" style="display:none"></div>{/if}

        <div class="page-card">
            <div class="page-action-bar">
                <a href="{url route="authorcenter" action="novel_add"}" class="btn-primary"><i class="fas fa-plus"></i> {t name="publish_new_work"}</a>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>{t name="cover_label"}</th>
                        <th>{t name="book_title_label"}</th>
                        <th>{t name="status_label"}</th>
                        <th>{t name="vip_status"}</th>
                        <th>{t name="word_count_label"}</th>
                        <th>{t name="operation"}</th>
                    </tr>
                    </thead>
                    <tbody>
                    {foreach $works as $w}
                    <tr>
                        <td>{$w.id}</td>
                        <td>{if $w.cover}<img src="/{$w.cover}" class="cover-thumb" alt="cover">{else}<img src="/assets/images/default_cover.jpg" class="cover-thumb" alt="cover">{/if}</td>
                        <td><a href="{url route="novel" id=$w.id}">{$w.title}</a></td>
                        <td>{if $w.status}{t name="finished"}{else}{t name="ongoing"}{/if}</td>
                        <td>
                            {if $w.is_vip == 1}<span class="vip-tag">{t name="status_signed"}</span>
                            {elseif $w.vip_audit_status == 0}<span style="color:#b8860b;">{t name="status_reviewing"}</span>
                            {elseif $w.vip_audit_status == 2}<span style="color:#a83232;">{t name="status_rejected_label"}</span>
                            {else}<span class="normal-tag">{t name="unsigned_label"}</span>{/if}
                        </td>
                        <td>{$w.total_words|number_format}{t name="words_unit"}</td>
                        <td class="action-cell">
                            <div class="action-wrap">
                                <a href="{url route="authorcenter" action="novel_edit"}?id={$w.id}" class="action-link"><i class="fas fa-edit"></i> {t name="edit_action"}</a>
                                <a href="{url route="authorcenter" action="chapter_list"}?novel_id={$w.id}" class="action-link"><i class="fas fa-list"></i> {t name="chapter_action"}</a>
                                <a href="{url route="authorcenter" action="volume_list"}?novel_id={$w.id}" class="action-link"><i class="fas fa-layer-group"></i> {t name="volume_action"}</a>
                                {if $w.is_vip != 1}
                                <button type="button" class="action-link sign-open" data-novel-id="{$w.id}" style="background:none;border:none;color:#333;cursor:pointer;font-size:0.82rem;display:inline-flex;align-items:center;gap:4px;">
                                    <i class="fas fa-file-signature"></i> {if $w.vip_audit_status == 2}{t name="reapply_sign"}{else}{t name="apply_sign"}{/if}
                                </button>
                                {/if}
                            </div>
                        </td>
                    </tr>
                    {foreachelse}
                    <tr><td colspan="7"><div class="empty-tip"><i class="fas fa-book-open"></i><p>{t name="no_works_yet"}</p></div></td></tr>
                    {/foreach}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<style>
.cover-thumb { width: 40px; height: 56px; object-fit: cover; border-radius: 3px; display: block; }
.action-wrap { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 12px; }
.action-link { font-size: 0.82rem; color: #b8860b; text-decoration: none; white-space: nowrap; display: inline-flex; align-items: center; gap: 4px; }
.action-link:hover { text-decoration: underline; }
</style>

<div id="signUiConfig" data-captcha="{$captcha_enabled ? '1' : '0'}" data-sign-url="{url route='authorcenter' action='novel_sign'}" style="display:none"></div>
<script>
window.I18N = {
    applySign: '{t name="apply_sign"}',
    confirmSubmit: '{t name="confirm_submit"}',
    submitted: '{t name="submitted"}',
    submitFailed: '{t name="submit_failed"}',
    networkError: '{t name="network_error_retry"}'
};
</script>
<script>
{literal}
document.addEventListener('DOMContentLoaded', function() {
    const captchaEnabled = document.getElementById('signUiConfig').dataset.captcha === '1';
    const signUrl = document.getElementById('signUiConfig').dataset.signUrl;
    function openSignModal(novelId) {
        const fields = [];
        if (captchaEnabled) fields.push({ type: 'captcha', name: 'captcha' });
        UI.form({
            title: window.I18N.applySign, titleIcon: 'fa-file-signature', okText: window.I18N.confirmSubmit,
            fields: fields,
            onSubmit: function(v) {
                const csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                let body = 'id=' + encodeURIComponent(novelId) + '&ajax=1&_csrf=' + encodeURIComponent(csrf);
                if (captchaEnabled) body += '&captcha=' + encodeURIComponent(v.captcha || '');
                return fetch(signUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body
                }).then(r => r.json()).then(function(d) {
                    if (d.ok) {
                        UI.toast(d.msg || window.I18N.submitted, 'success');
                        setTimeout(function() { location.reload(); }, 1000);
                        return true;
                    }
                    UI.alert({ message: d.msg || window.I18N.submitFailed, type: 'error' });
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
    document.querySelectorAll('.sign-open').forEach(btn => {
        btn.addEventListener('click', function() { openSignModal(this.dataset.novelId); });
    });
});
{/literal}
</script>
{include file="footer.tpl"}
