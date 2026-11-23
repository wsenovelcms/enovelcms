{include file="header.tpl"}
<div class="user-center-layout">
    {include file="user/_sidebar.tpl"}
    <div class="user-content">
        <h2><i class="fas fa-key"></i> {t name="change_password"}</h2>
        {if $pwdResult}<div data-ui-toast data-type="{if $pwdResult.ok}success{else}error{/if}" data-msg="{$pwdResult.msg}" style="display:none"></div>{/if}
        <form method="post" action="{url route="user" action="password"}">
            {csrf_field}
            <div class="form-group"><label>{t name="old_password"}</label><input type="password" name="old_password" required></div>
            <div class="form-group"><label>{t name="new_password"}</label><input type="password" name="new_password" required></div>
            {if $captcha_enabled}
            <div class="form-group">
                <label>{t name="captcha"}</label>
                {captcha_field name="captcha" placeholder="{t name="captcha_before_change"}"}
            </div>
            {/if}
            <button type="submit" class="btn-primary">{t name="save"}</button>
        </form>
    </div>
</div>
{include file="footer.tpl"}
