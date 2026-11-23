{include file="header.tpl"}
<div class="auth-page">
    <div class="auth-card">
        <h2 class="auth-title">{t name="login"}</h2>
        {if $error}<div data-ui-toast data-type="error" data-msg="{$error}" style="display:none"></div>{/if}
        <form method="post" action="{url route="user" action="login"}">
            {csrf_field}
            <div class="auth-field">
                <label>{t name="username_or_email"}</label>
                <input type="text" name="username" placeholder="{t name="please_enter_username_or_email"}" required>
            </div>
            <div class="auth-field">
                <label>{t name="password"}</label>
                <input type="password" name="password" required>
            </div>
            {if $captcha_enabled}
            <div class="auth-field">
                <label>{t name="captcha"}</label>
                {captcha_field name="captcha" placeholder="{t name="captcha_placeholder"}"}
            </div>
            {/if}
            <button type="submit" class="auth-btn">{t name="login"}</button>
        </form>
        <p class="auth-links">
            <a href="{url route="user" action="forgot"}">{t name="forgot_password"}</a>
            <span class="auth-divider">|</span>
            <span>{t name="no_account"}<a href="{url route="user" action="register"}">{t name="register"}</a></span>
        </p>
    </div>
</div>
<style>
{literal}
.auth-page{min-height:calc(100vh - 140px);display:flex;align-items:center;justify-content:center;padding:40px 20px;background:#faf7f2;}
.auth-card{width:100%;max-width:440px;background:#fff;border-radius:10px;padding:40px 36px;box-shadow:0 2px 20px rgba(0,0,0,.05);border-top:3px solid #b8860b;}
.auth-title{font-size:22px;font-weight:600;color:#222;margin:0 0 24px;text-align:center;}
.auth-field{margin-bottom:16px;}
.auth-field label{display:block;font-size:13px;color:#555;margin-bottom:6px;}
.auth-field input{width:100%;padding:11px 14px;border:1px solid #ddd;border-radius:4px;font-size:14px;box-sizing:border-box;transition:border-color .2s;background:#fff;}
.auth-field input:focus{outline:none;border-color:#b8860b;}
.auth-btn{width:100%;padding:12px;background:#333;color:#faf7f2;border:none;border-radius:4px;font-size:15px;cursor:pointer;margin-top:8px;transition:background .2s;letter-spacing:2px;}
.auth-btn:hover{background:#b8860b;}
.auth-links{text-align:center;margin-top:20px;font-size:13px;color:#666;}
.auth-links a{color:#b8860b;text-decoration:none;margin:0 2px;}
.auth-links a:hover{text-decoration:underline;}
.auth-divider{color:#ccc;margin:0 6px;}
{/literal}
</style>
{include file="footer.tpl"}
