{include file="header.tpl"}
<div class="user-center-layout">
    {include file="user/_sidebar.tpl"}
    <div class="user-content">
        <h2><i class="fas fa-user-plus"></i> {t name="apply_author_title"}</h2>
        {if $applyResult}
        <div data-ui-toast data-type="{if $applyResult.ok}success{else}error{/if}" data-msg="{$applyResult.msg}" style="display:none"></div>
        {/if}

        {if $status == 0}
        <div class="page-card">
            <div class="empty-tip">
                <i class="fas fa-hourglass-half" style="font-size:48px;color:#b8860b;"></i>
                <p style="margin-top:12px;font-size:15px;">{t name="apply_submitted"}</p>
                <a href="{url route="user"}" class="btn-primary" style="margin-top:8px;">{t name="back_to_user_center"}</a>
            </div>
        </div>
        {elseif $status == 1}
        <div class="page-card">
            <div class="empty-tip">
                <i class="fas fa-check-circle" style="font-size:48px;color:#2d6a4f;"></i>
                <p style="margin-top:12px;font-size:15px;">{t name="msg_already_author"}</p>
                <a href="{url route="authorcenter" action="works"}" class="btn-primary" style="margin-top:8px;">{t name="enter_author_center"}</a>
            </div>
        </div>
        {else}

        {if $status == 2}
        <div class="page-card" style="border-left:4px solid #a83232;background:#fdf5f5;">
            <h3 class="card-title" style="color:#a83232;"><i class="fas fa-times-circle"></i> {t name="apply_rejected_title"}</h3>
            <p style="margin:8px 0 0;color:#a83232;"><strong>{t name="reject_reason_label"}</strong>{$rejectReason|default:"{t name="reject_reason_default"}"}</p>
            <p style="margin:12px 0 0;color:#666;font-size:13px;">{t name="resubmit_tip"}</p>
        </div>
        {/if}

        <div class="page-card">
            <h3 class="card-title"><i class="fas fa-pen"></i> {if $status == 2}{t name="reapply"}{else}{t name="apply_author_title"}{/if}</h3>
            <form method="post" enctype="multipart/form-data" action="{url route="authorcenter" action="apply"}">
                {csrf_field}
                <div class="profile-form-row">
                    <label>{t name="real_name_label"}<span style="color:#a83232;"> *</span></label>
                    <input type="text" name="real_name" required value="{$existing.real_name|default:''}">
                </div>
                <div class="profile-form-row">
                    <label>{t name="pen_name_label"}<span style="color:#a83232;"> *</span></label>
                    <input type="text" name="pen_name" required placeholder="{t name="pen_name_placeholder"}" value="{$existing.pen_name|default:''}">
                </div>
                <div class="profile-form-row">
                    <label>{t name="id_card_label"}<span style="color:#a83232;"> *</span></label>
                    <input type="text" name="id_card" required value="{$existing.id_card|default:''}">
                </div>
                <div class="profile-form-row">
                    <label>{t name="phone_label"}<span style="color:#a83232;"> *</span></label>
                    <input type="text" name="phone" required value="{$existing.phone|default:''}">
                </div>
                <div class="profile-form-row">
                    <label>{t name="qq_label"}</label>
                    <input type="text" name="qq" value="{$existing.qq|default:''}">
                </div>
                <div class="profile-form-row">
                    <label>{t name="id_card_front_label"}<span style="color:#a83232;"> *</span></label>
                    <div>
                        <input type="file" name="id_card_front" accept="image/*" required id="frontInput">
                        <div id="frontPreview" class="file-preview"></div>
                    </div>
                </div>
                <div class="profile-form-row">
                    <label>{t name="id_card_back_label"}<span style="color:#a83232;"> *</span></label>
                    <div>
                        <input type="file" name="id_card_back" accept="image/*" required id="backInput">
                        <div id="backPreview" class="file-preview"></div>
                    </div>
                </div>
                <div class="profile-form-row">
                    <label>{t name="intro_label"}</label>
                    <textarea name="introduction" rows="4" style="max-width:400px;min-height:60px;">{$existing.introduction|default:''}</textarea>
                </div>
                {if $captcha_enabled}
                <div class="profile-form-row">
                    <label>{t name="captcha_label"}</label>
                    {captcha_field name="captcha" placeholder="{t name="captcha_before_apply"}"}
                </div>
                {/if}
                <div class="profile-actions">
                    <button type="submit" name="submit_apply" class="btn-primary"><i class="fas fa-paper-plane"></i> {t name="submit_apply"}</button>
                </div>
            </form>
        </div>
        {/if}
    </div>
</div>
<style>
.file-preview { display: flex; gap: 10px; margin-top: 8px; flex-wrap: wrap; }
.file-preview-item { position: relative; width: 80px; height: 80px; border: 1px solid #ddd; border-radius: 3px; overflow: hidden; background: #faf7f2; }
.file-preview-item img { width: 100%; height: 100%; object-fit: cover; }
.file-preview-item .remove-preview { position: absolute; top: 2px; right: 2px; background: rgba(168,50,50,.85); color: #fff; border: none; border-radius: 50%; width: 18px; height: 18px; font-size: 12px; line-height: 18px; text-align: center; cursor: pointer; padding: 0; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    function setupPreview(inputId, previewId) {
        const input = document.getElementById(inputId);
        const preview = document.getElementById(previewId);
        if (!input || !preview) return;
        input.addEventListener('change', function() {
            preview.innerHTML = '';
            const file = this.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = function(ev) {
                const div = document.createElement('div');
                div.className = 'file-preview-item';
                div.innerHTML = '<img src="' + ev.target.result + '" alt="preview"><button type="button" class="remove-preview" title="' + (window.lang || {}).ui_remove + '">&times;</button>';
                div.querySelector('.remove-preview').addEventListener('click', function() {
                    input.value = ''; preview.innerHTML = '';
                });
                preview.appendChild(div);
            };
            reader.readAsDataURL(file);
        });
    }
    setupPreview('frontInput', 'frontPreview');
    setupPreview('backInput', 'backPreview');
});
</script>
{include file="footer.tpl"}
