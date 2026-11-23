{include file="header.tpl"}
<div class="user-center-layout">
    {include file="user/_sidebar.tpl"}
    <div class="user-content">
        <h2><i class="fas fa-bookmark"></i> {t name="bookshelf"}</h2>
        <div class="page-card">
        <div class="bookshelf-grid">
        {foreach $list as $b}
            <div class="bookshelf-card" data-novel-id="{$b.novel_id}">
                <div class="bookshelf-card-cover">
                    <a href="{url route="novel" id=$b.novel_id}">
                        <img src="/{$b.cover|default:'assets/images/default_cover.jpg'}" alt="{$b.title}">
                    </a>
                    {if $b.is_vip}<span class="vip-badge">VIP</span>{/if}
                </div>
                <div class="bookshelf-card-body">
                    <h3><a href="{url route="novel" id=$b.novel_id}">{$b.title}</a></h3>
                    <p class="author">{$b.display_name}</p>
                    <p class="last-read">
                        <i class="fas fa-history"></i>
                        {if $b.last_read_chapter && $b.last_read_chapter > 0}
                            <a href="{url route="read" novel=$b.novel_id chapter=$b.last_read_chapter}">{$b.last_chapter_title|truncate:14}</a>
                        {else}
                            <a href="{url route="novel" id=$b.novel_id}">{t name="start_reading"}</a>
                        {/if}
                    </p>
                    <div class="bookshelf-card-actions">
                        {if $b.last_read_chapter && $b.last_read_chapter > 0}
                            <a href="{url route="read" novel=$b.novel_id chapter=$b.last_read_chapter}" class="btn-primary btn-sm">{t name="continue_reading"}</a>
                        {else}
                            <a href="{url route="novel" id=$b.novel_id}" class="btn-primary btn-sm">{t name="start_reading"}</a>
                        {/if}
                        <button class="btn-remove-sm remove-bookshelf" data-novel-id="{$b.novel_id}" title="{t name="remove_from_bookshelf"}">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        {foreachelse}
            <div class="empty-state"><i class="fas fa-book-open"></i><p>{t name="empty_bookshelf"}</p><a href="{url route="library"}" class="btn-primary">{t name="browse_novels"}</a></div>
        {/foreach}
        </div>
        {if $totalPages > 1}
        <div class="pagination">
            {pagination page=$page totalPages=$totalPages urlTpl=$pageUrlTpl}
        </div>
        {/if}
        </div>
    </div>
</div>
{include file="footer.tpl"}
