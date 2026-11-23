{include file="header.tpl"}
<div class="user-center-layout">
    {include file="user/_sidebar.tpl"}
    <div class="user-content">
        <h2><i class="fas fa-edit"></i> {t name="edit_volume_title"}</h2>
        <div class="page-card">
            <form method="post" action="{url route="authorcenter" action="volume_edit"}?id={$volume.id}">
                {csrf_field}
                <div class="profile-form-row">
                    <label>{t name="volume_name_label"}<span style="color:#a83232;"> *</span></label>
                    <input type="text" name="title" value="{$volume.title}" required>
                </div>
                <div class="profile-form-row">
                    <label>{t name="sort_label"}</label>
                    <input type="number" name="sort" value="{$volume.sort}" style="max-width:120px;">
                </div>
                <div class="profile-actions">
                    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> {t name="save_changes"}</button>
                    <a href="{url route="authorcenter" action="chapter_list"}?novel_id={$volume.novel_id}" class="btn-outline"><i class="fas fa-list"></i> {t name="chapter_manage_title"}</a>
                    <a href="{url route="authorcenter" action="volume_list"}?novel_id={$volume.novel_id}" class="btn-outline">{t name="back_to_volume_list"}</a>
                </div>
            </form>
        </div>
    </div>
</div>
{include file="footer.tpl"}
