{include file="header.tpl"}
<div class="user-center-layout">
    {include file="user/_sidebar.tpl"}
    <div class="user-content">
        <h2><i class="fas fa-bolt"></i> {t name="auto_purchase_title"}</h2>
        <div class="page-card">
            <p class="auto-desc">{t name="auto_purchase_desc"}</p>
            {if $list}
            <table class="data-table">
                <thead><tr>
                    <th>{t name="novel"}</th>
                    <th>{t name="auto_on_time"}</th>
                    <th>{t name="operation"}</th>
                </tr></thead>
                <tbody>
                {foreach $list as $a}
                <tr>
                    <td>{if $a.novel_title}{$a.novel_title}{else}{t name="deleted_label"}{/if}</td>
                    <td>{$a.updated_at|date_format:'Y-m-d H:i'}</td>
                    <td><button type="button" class="action-link auto-off-btn" data-novel-id="{$a.novel_id}"><i class="fas fa-stop-circle"></i> {t name="close_auto_purchase"}</button></td>
                </tr>
                {/foreach}
                </tbody>
            </table>
            {else}
            <div class="empty-state"><i class="fas fa-bolt"></i><p>{t name="no_auto_novels"}</p></div>
            {/if}
        </div>
    </div>
</div>
<style>
.auto-desc { color:#888; font-size:0.9rem; margin:0 0 16px; line-height:1.7; }
.auto-off-btn { background:none; border:none; cursor:pointer; color:#b8860b; font-size:0.9rem; }
.auto-off-btn:hover { color:#333; }
</style>
<script>
(function() {
    const buttons = document.querySelectorAll('.auto-off-btn');
    if (!buttons.length) return;
    function csrf() { return (document.querySelector('meta[name="csrf-token"]') || {}).content || ''; }
    buttons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            const novelId = btn.dataset.novelId;
            UI.confirm({
                title: '{t name="close_auto_purchase"}',
                message: '{t name="confirm_close_auto_purchase"}',
                okText: '{t name="close_word"}'
            }).then(function(ok) {
                if (!ok) return;
                btn.disabled = true;
                fetch('/api/auto_purchase_toggle', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'novel_id=' + encodeURIComponent(novelId) + '&enabled=0&_csrf=' + encodeURIComponent(csrf())
                })
                .then(r => r.json())
                .then(resp => {
                    if (resp.code === 0) {
                        UI.toast('{t name="auto_purchase_closed"}', 'success');
                        setTimeout(function() { location.reload(); }, 600);
                    } else {
                        UI.alert({ message: (resp.data && resp.data.msg) || resp.msg || '{t name="operation_failed"}', type: 'error' });
                        btn.disabled = false;
                    }
                })
                .catch(function() {
                    UI.alert({ message: '{t name="network_error_retry"}', type: 'error' });
                    btn.disabled = false;
                });
            });
        });
    });
})();
</script>
{include file="footer.tpl"}
