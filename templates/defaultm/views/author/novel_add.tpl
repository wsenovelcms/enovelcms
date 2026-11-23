{include file="header.tpl"}
<div class="user-center-layout">
    {include file="user/_sidebar.tpl"}
    <div class="user-content">
        <h2><i class="fas fa-plus"></i> {t name="publish_new_work"}</h2>
        {if $novelResult}<div data-ui-toast data-type="{if $novelResult.ok}success{else}error{/if}" data-msg="{$novelResult.msg}" style="display:none"></div>{/if}
        <div class="page-card">
            <form method="post" enctype="multipart/form-data" action="{url route="authorcenter" action="novel_add"}">
                {csrf_field}
                <div class="profile-form-row">
                    <label>{t name="pen_name_label"}</label>
                    <span style="color:#b8860b;font-weight:600;">{$user.pen_name|default:$user.username}</span>
                </div>
                <div class="profile-form-row">
                    <label>{t name="book_title_label"}<span style="color:#a83232;"> *</span></label>
                    <input type="text" name="title" required>
                </div>
                <div class="profile-form-row">
                    <label>{t name="category_label_field"}</label>
                    <select name="category_id">
                    {foreach $categories as $cat}
                        {if $cat.children|count > 0}
                        <optgroup label="{$cat.name}">
                            <option value="{$cat.id}">{$cat.name}</option>
                            {foreach $cat.children as $child}
                            <option value="{$child.id}">{$child.name}</option>
                            {/foreach}
                        </optgroup>
                        {else}
                        <option value="{$cat.id}">{$cat.name}</option>
                        {/if}
                    {/foreach}
                    </select>
                </div>
                <div class="profile-form-row">
                    <label>{t name="tags_label_field"}</label>
                    <div class="tag-select" data-field="tag_ids" data-max="{$maxTags}" data-tags='{$allTags|json_encode}' data-selected=""></div>
                </div>
                <div class="profile-form-row">
                    <label>{t name="cover_field_label"}</label>
                    <div>
                        <img id="coverPreview" src="/assets/images/default_cover.jpg" style="max-height:120px;border-radius:3px;border:1px solid #ddd;margin-bottom:8px;display:none;" alt="cover">
                        <input type="file" name="cover" accept="image/*" onchange="previewCover(this)">
                    </div>
                </div>
                <div class="profile-form-row">
                    <label>{t name="desc_label"}</label>
                    <textarea name="description" rows="4" style="max-width:400px;min-height:80px;"></textarea>
                </div>
                <div class="profile-actions">
                    <button type="submit" class="btn-primary"><i class="fas fa-paper-plane"></i> {t name="publish_action"}</button>
                    <a href="{url route="authorcenter" action="works"}" class="btn-outline">{t name="cancel_action"}</a>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
function previewCover(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var img = document.getElementById('coverPreview');
            img.src = e.target.result;
            img.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
<script src="/assets/js/tag_picker.js"></script>
{include file="footer.tpl"}
