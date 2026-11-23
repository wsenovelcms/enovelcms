{include file="header.tpl"}
<div class="container">
    <div class="empty-state" style="text-align:center;padding:60px 20px;">
        <i class="fas fa-exclamation-circle" style="font-size:3rem;color:#94a3b8;"></i>
        <p style="font-size:1.2rem;margin:16px 0;">{if $message}{$message}{else}{t name="page_not_found"}{/if}</p>
        <a href="{url route="home"}" class="btn-primary">{t name="home"}</a>
    </div>
</div>
{include file="footer.tpl"}
