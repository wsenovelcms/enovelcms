{include file="header.tpl"}
<div class="user-center-layout">
    {include file="user/_sidebar.tpl"}
    <div class="user-content">
        <h2><i class="fas fa-edit"></i> {t name="edit_work_title"}</h2>
        <div class="page-card">
            <form method="post" enctype="multipart/form-data" action="{url route="authorcenter" action="novel_edit"}?id={$novel.id}">
                {csrf_field}
                <div class="profile-form-row">
                    <label>{t name="pen_name_label"}</label>
                    <span style="color:#b8860b;font-weight:600;">{$user.pen_name|default:$user.username}</span>
                </div>
                <div class="profile-form-row">
                    <label>{t name="book_title_label"}<span style="color:#a83232;"> *</span></label>
                    <input type="text" name="title" value="{$novel.title}" required{if !$perm.can_edit_info} disabled{/if}>
                </div>
                <div class="profile-form-row">
                    <label>{t name="category_label_field"}</label>
                    <select name="category_id"{if !$perm.can_edit_info} disabled{/if}>
                    {foreach $categories as $cat}
                        {if $cat.children|count > 0}
                        <optgroup label="{$cat.name}">
                            <option value="{$cat.id}"{if $novel.category_id == $cat.id} selected{/if}>{$cat.name}</option>
                            {foreach $cat.children as $child}
                            <option value="{$child.id}"{if $novel.category_id == $child.id} selected{/if}>{$child.name}</option>
                            {/foreach}
                        </optgroup>
                        {else}
                        <option value="{$cat.id}"{if $novel.category_id == $cat.id} selected{/if}>{$cat.name}</option>
                        {/if}
                    {/foreach}
                    </select>
                </div>
                <div class="profile-form-row">
                    <label>{t name="tags_label_field"}</label>
                    <div class="tag-select" data-field="tag_ids" data-max="{$maxTags}" data-tags='{$allTags|json_encode}' data-selected="{$selectedTagIdsStr}"></div>
                </div>
                <div class="profile-form-row">
                    <label>{t name="status_label_field"}</label>
                    <select name="status"{if !$perm.can_edit_info} disabled{/if}>
                        <option value="0"{if $novel.status == 0} selected{/if}>{t name="ongoing"}</option>
                        <option value="1"{if $novel.status == 1} selected{/if}>{t name="finished"}</option>
                    </select>
                </div>
                {hook name="author_novel_edit_extra"}
                <div class="profile-form-row">
                    <label>{t name="current_cover"}</label>
                    <div>
                        <img id="coverPreview" src="/{$novel.cover|default:'assets/images/default_cover.jpg'}" style="max-height:120px;border-radius:3px;border:1px solid #ddd;margin-bottom:8px;display:block;" alt="cover">
                        <input type="file" name="cover" accept="image/*" onchange="previewCover(this)"{if !$perm.can_change_cover} disabled{/if}>
                        {if !$perm.can_change_cover}<small class="form-text text-muted">{t name="cover_locked_tip"}</small>{/if}
                        <input type="hidden" name="old_cover" value="{$novel.cover}">
                    </div>
                </div>
                <div class="profile-form-row">
                    <label>{t name="desc_label"}</label>
                    <textarea name="description" rows="4" style="max-width:400px;min-height:80px;"{if !$perm.can_edit_info} disabled{/if}>{$novel.description}</textarea>
                </div>
                <div class="profile-actions">
                    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> {t name="save_changes"}</button>
                    <a href="{url route="authorcenter" action="chapter_list"}?novel_id={$novel.id}" class="btn-outline"><i class="fas fa-list"></i> {t name="chapter_manage_title"}</a>
                    <a href="{url route="authorcenter" action="volume_list"}?novel_id={$novel.id}" class="btn-outline"><i class="fas fa-layer-group"></i> {t name="volume_manage_title"}</a>
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
            document.getElementById('coverPreview').src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
<script src="/assets/js/tag_picker.js"></script>
{include file="footer.tpl"}
