{include file="header.tpl"}
<div class="user-center-layout">
    {include file="user/_sidebar.tpl"}
    <div class="user-content profile-page">
        <h2><i class="fas fa-user-edit"></i> {t name="profile"}</h2>
        {if $profileResult}
        <div data-ui-toast data-type="{if $profileResult.ok}success{else}error{/if}" data-msg="{$profileResult.msg}" style="display:none"></div>
        {/if}

        <div class="profile-hero-card home-card">
            <div class="profile-hero">
                <div class="profile-avatar-wrap">
                    <img src="{$user.avatar|default:'/assets/images/default_avatar.png'}" alt="avatar" class="profile-avatar-preview">
                    <span class="profile-avatar-name">{$user.username}</span>
                </div>
                <div class="profile-hero-info">
                    <span class="profile-meta-label">{t name="register_time"}</span>
                    <span class="profile-meta-value">{$user.created_at|date_format:'Y-m-d'}</span>
                    <span class="profile-meta-label">{t name="vip_status"}</span>
                    <span class="profile-meta-value">
                        {if $user.vip_level > 0}<span class="vip-tag">VIP{$user.vip_level}</span>{else}<span class="normal-tag">{t name="normal_user"}</span>{/if}
                    </span>
                    <span class="profile-meta-label">{t name="current_point_label"}</span>
                    <span class="profile-meta-value gold-tag"><i class="fas fa-coins"></i> {$user.gold|number_format}</span>
                </div>
            </div>
        </div>

        <form method="post" action="{url route="user" action="profile"}" enctype="multipart/form-data">
            {csrf_field}

            <div class="profile-section">
                <h3 class="profile-section-title"><i class="fas fa-id-card"></i> {t name="basic_info"}</h3>
                <div class="profile-form-row">
                    <label>{t name="username"}</label>
                    <input type="text" value="{$user.username}" disabled>
                </div>
                <div class="profile-form-row">
                    <label>{t name="email"}</label>
                    <div class="email-input-group">
                        <input type="email" name="email" id="emailInput" value="{$user.email}" placeholder="you@example.com">
                        <button type="button" id="sendCodeBtn" class="btn-send-code" data-url="{url route='user' action='send_email_code'}">{t name="send_code"}</button>
                    </div>
                </div>
                <div class="profile-form-row" id="emailCodeRow" style="{if $user.email}display:none;{/if}">
                    <label>{t name="email_code_label"}</label>
                    <input type="text" name="email_code" id="emailCodeInput" maxlength="6" placeholder="{t name="email_code_placeholder"}" style="max-width:240px;">
                </div>
                <div class="profile-form-row">
                    <label>{t name="signature"}</label>
                    <textarea name="signature" rows="3" maxlength="200" placeholder="{t name="signature_placeholder"}" style="max-width:400px;min-height:60px;resize:vertical;">{$user.signature|default:''}</textarea>
                </div>
                <div class="profile-form-row">
                    <label>{t name="gender"}</label>
                    <select name="gender">
                        <option value="0" {if $user.gender == 0}selected{/if}>{t name="gender_secret"}</option>
                        <option value="1" {if $user.gender == 1}selected{/if}>{t name="gender_male"}</option>
                        <option value="2" {if $user.gender == 2}selected{/if}>{t name="gender_female"}</option>
                    </select>
                </div>
            </div>

            {hook name="user_profile_extra"}

            <div class="profile-section">
                <h3 class="profile-section-title"><i class="fas fa-address-book"></i> {t name="contact_info"}</h3>
                <div class="profile-form-row">
                    <label>{t name="phone"}</label>
                    <input type="text" name="phone" value="{$user.phone|default:''}" placeholder="{t name="phone_placeholder"}">
                </div>
                <div class="profile-form-row">
                    <label>{t name="wechat"}</label>
                    <input type="text" name="wechat_account" value="{$user.wechat_account|default:''}" placeholder="{t name="wechat_placeholder"}">
                </div>
                <div class="profile-form-row">
                    <label>{t name="avatar_url"}</label>
                    <div class="avatar-upload-group">
                        <img src="{$user.avatar|default:'/assets/images/default_avatar.png'}" alt="avatar" id="avatarPreview" class="avatar-upload-preview">
                        <input type="file" name="avatar_file" id="avatarInput" accept="image/jpeg,image/png,image/gif">
                        <span class="avatar-hint">{t name="avatar_hint"}</span>
                    </div>
                </div>
            </div>

            {if $captcha_enabled}
            <div class="profile-section">
                <h3 class="profile-section-title"><i class="fas fa-shield-halved"></i> {t name="security_verify"}</h3>
                <div class="profile-form-row">
                    <label>{t name="captcha_label"}</label>
                    {captcha_field name="captcha" placeholder="{t name="captcha_before_profile_save"}"}
                </div>
            </div>
            {/if}

            <div class="profile-actions">
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> {t name="save"}</button>
                <button type="button" class="btn-outline" id="openPwdModal" data-captcha="{$captcha_enabled ? '1' : '0'}" data-action="{url route='user' action='password'}"><i class="fas fa-key"></i> {t name="change_password"}</button>
            </div>
        </form>
    </div>
</div>

<script>
window.I18N = {
    avatarSizeLimit: '{t name="avatar_size_limit"}',
    emailInvalid: '{t name="email_invalid_tip"}',
    sending: '{t name="sending"}',
    sentOk: '{t name="sent_ok"}',
    sendFail: '{t name="send_fail"}',
    retryAfter: '{t name="retry_after_sec"}',
    sendCode: '{t name="send_code"}',
    networkError: '{t name="network_error_retry"}',
    oldPassword: '{t name="old_password"}',
    newPasswordHint: '{t name="new_password_hint"}',
    confirmPassword: '{t name="confirm_password"}',
    changePassword: '{t name="change_password"}',
    confirmChange: '{t name="confirm_change"}',
    passwordTooShort: '{t name="password_too_short"}',
    passwordMismatch: '{t name="password_mismatch"}',
    passwordChangedOk: '{t name="password_changed_ok"}',
    modifyFailed: '{t name="modify_failed"}'
};
</script>
<script>
{literal}
(function() {
    const input = document.getElementById('avatarInput');
    const preview = document.getElementById('avatarPreview');
    if (input && preview && input.type === 'file') {
        input.addEventListener('change', function() {
            const f = this.files && this.files[0];
            if (!f) return;
            if (f.size > 2 * 1024 * 1024) { UI.alert({message: window.I18N.avatarSizeLimit, type: 'error'}); this.value = ''; return; }
            const reader = new FileReader();
            reader.onload = e => { preview.src = e.target.result; };
            reader.readAsDataURL(f);
        });
    }
    
    const emailInput = document.getElementById('emailInput');
    const codeRow = document.getElementById('emailCodeRow');
    const sendBtn = document.getElementById('sendCodeBtn');
    const originalEmail = emailInput ? emailInput.value : '';
    if (emailInput && codeRow) {
        emailInput.addEventListener('input', function() {
            codeRow.style.display = (this.value.trim() && this.value.trim() !== originalEmail) ? '' : 'none';
        });
    }
    if (sendBtn) {
        sendBtn.addEventListener('click', function() {
            const email = emailInput.value.trim();
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                UI.alert({message: window.I18N.emailInvalid, type: 'error'});
                return;
            }
            const csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
            sendBtn.disabled = true;
            sendBtn.textContent = window.I18N.sending;
            fetch(sendBtn.dataset.url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'email=' + encodeURIComponent(email) + '&_csrf=' + encodeURIComponent(csrf)
            })
            .then(r => r.json())
            .then(data => {
                UI.alert({message: data.msg || (data.ok ? window.I18N.sentOk : window.I18N.sendFail), type: data.ok ? 'success' : 'error'});
                if (data.ok) {
                    let sec = 60;
                    sendBtn.textContent = window.I18N.retryAfter.replace('%d', sec);
                    const timer = setInterval(() => {
                        sec--;
                        if (sec <= 0) {
                            clearInterval(timer);
                            sendBtn.disabled = false;
                            sendBtn.textContent = window.I18N.sendCode;
                        } else {
                            sendBtn.textContent = window.I18N.retryAfter.replace('%d', sec);
                        }
                    }, 1000);
                } else {
                    sendBtn.disabled = false;
                    sendBtn.textContent = window.I18N.sendCode;
                }
            })
            .catch(() => {
                UI.alert({message: window.I18N.networkError, type: 'error'});
                sendBtn.disabled = false;
                sendBtn.textContent = window.I18N.sendCode;
            });
        });
    }
    
    const openPwdBtn = document.getElementById('openPwdModal');
    const captchaEnabled = openPwdBtn && openPwdBtn.dataset.captcha === '1';
    const pwdAction = openPwdBtn ? openPwdBtn.dataset.action : '';
    if (openPwdBtn) {
        openPwdBtn.addEventListener('click', function() {
            const fields = [
                { label: window.I18N.oldPassword, type: 'password', name: 'old_password', required: true },
                { label: window.I18N.newPasswordHint, type: 'password', name: 'new_password', required: true },
                { label: window.I18N.confirmPassword, type: 'password', name: 'confirm_password', required: true }
            ];
            if (captchaEnabled) fields.push({ type: 'captcha', name: 'captcha' });
            UI.form({
                title: window.I18N.changePassword, titleIcon: 'fa-key', okText: window.I18N.confirmChange,
                fields: fields,
                onSubmit: function(v) {
                    if ((v.new_password || '').length < 6) { UI.alert({ message: window.I18N.passwordTooShort, type: 'error' }); return false; }
                    if (v.new_password !== v.confirm_password) { UI.alert({ message: window.I18N.passwordMismatch, type: 'error' }); return false; }
                    const csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                    let body = 'old_password=' + encodeURIComponent(v.old_password)
                        + '&new_password=' + encodeURIComponent(v.new_password)
                        + '&ajax=1&_csrf=' + encodeURIComponent(csrf);
                    if (captchaEnabled) body += '&captcha=' + encodeURIComponent(v.captcha || '');
                    return fetch(pwdAction, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: body
                    }).then(r => r.json()).then(function(d) {
                        if (d.ok) { UI.toast(d.msg || window.I18N.passwordChangedOk, 'success'); return true; }
                        UI.alert({ message: d.msg || window.I18N.modifyFailed, type: 'error' });
                        const img = document.querySelector('.ui-modal img.ui-captcha-img');
                        const cap = document.querySelector('.ui-modal input[name="captcha"]');
                        if (img) img.src = '/api/captcha?' + Date.now();
                        if (cap) cap.value = '';
                        return false;
                    }).catch(function() {
                        UI.alert({ message: window.I18N.networkError, type: 'error' });
                        return false;
                    });
                }
            });
        });
    }
})();
{/literal}
</script>
{include file="footer.tpl"}
