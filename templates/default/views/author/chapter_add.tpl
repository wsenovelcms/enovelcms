{include file="header.tpl"}
<div class="user-center-layout">
    {include file="user/_sidebar.tpl"}
    <div class="user-content">
        <h2><i class="fas fa-plus-circle"></i> {t name="add_chapter_title"} - {$novel.title}</h2>
        {if $chapterResult}<div data-ui-toast data-type="{if $chapterResult.ok}success{else}error{/if}" data-msg="{$chapterResult.msg}" style="display:none"></div>{/if}
        <div class="page-card">
            <form method="post" action="{url route="authorcenter" action="chapter_add"}?novel_id={$novel.id}">
                {csrf_field}
                <div class="profile-form-row">
                    <label>{t name="volume_belong"}</label>
                    <select name="volume_id">
                    {foreach $volumes as $vol}
                        <option value="{$vol.id}"{if $vol@last} selected{/if}>{$vol.title}</option>
                    {/foreach}
                    </select>
                </div>
                <div class="profile-form-row">
                    <label>{t name="title_label"}<span style="color:#a83232;"> *</span></label>
                    <input type="text" name="title" required>
                </div>
                {if $novel.is_vip == 1}
                <div class="profile-form-row">
                    <label>{t name="is_vip_label"}</label>
                    <div class="vip-check-row">
                        <input type="checkbox" name="is_vip" value="1" id="addIsVip" class="vip-native"{if $default_vip} checked{/if}>
                        <label for="addIsVip" class="vip-switch"><span class="vip-switch-knob"></span></label>
                    </div>
                </div>
                <div class="profile-form-row">
                    <label>{t name="chapter_price_label"}</label>
                    <div class="price-field">
                        <input type="number" name="price" value="0" min="0" style="max-width:160px;">
                        <span class="price-hint">{t name="price_hint"}</span>
                    </div>
                </div>
                {else}
                <input type="hidden" name="is_vip" value="0">
                <input type="hidden" name="price" value="0">
                {/if}
                <div class="profile-form-row">
                    <label>{t name="content_html_label"}</label>
                    <textarea name="content" rows="16" required style="max-width:100%;min-height:380px;resize:vertical;"></textarea>
                </div>
                <div class="profile-actions">
                    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> {t name="add_chapter_title"}</button>
                    <a href="{url route="authorcenter" action="volume_list"}?novel_id={$novel.id}" class="btn-outline"><i class="fas fa-layer-group"></i> {t name="volume_manage_title"}</a>
                    <a href="{url route="authorcenter" action="chapter_list"}?novel_id={$novel.id}" class="btn-outline">{t name="back_to_chapter_list"}</a>
                </div>
            </form>
        </div>
    </div>
</div>
{include file="footer.tpl"}
