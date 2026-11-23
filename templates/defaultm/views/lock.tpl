{include file="header.tpl"}
<div class="container">
    <div id="lockMsg" data-msg="{$message}"></div>
    <div class="empty-state" style="text-align:center;padding:60px 20px;">
        <i class="fas fa-lock" style="font-size:3rem;color:#b8860b;"></i>
        <p style="font-size:1.15rem;margin:16px 0;">{$message}</p>
        <a href="{url route="home"}" class="btn-primary"><i class="fas fa-home"></i> {t name="back_to_home"}</a>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var msg = document.getElementById('lockMsg').getAttribute('data-msg') || '{t name="operation_restricted"}';
    UI.alert({ title: '{t name="operation_restricted"}', message: msg, type: 'error', okText: '{t name="know_it"}' });
});
</script>
{include file="footer.tpl"}
