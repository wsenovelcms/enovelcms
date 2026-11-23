{include file="header.tpl"}
<div class="container">
    <div class="filter-section author-profile-card">
        <div class="author-profile-row">
            <div class="author-avatar-big">
                <img src="{$author.avatar|default:'/assets/images/default_avatar.png'}" alt="{$author.pen_name|default:$author.username}">
            </div>
            <div class="author-profile-body">
                <h2 class="author-name">
                    {$author.pen_name|default:$author.username}
                    {if $author.is_author}<span class="author-badge"><i class="fas fa-pen-fancy"></i> {t name="author"}</span>{/if}
                </h2>
                <p class="author-signature">{if $author.signature}{$author.signature}{else}{t name="no_signature"}{/if}</p>
                <div class="author-stats-row">
                    <span><i class="fas fa-book"></i> {t name="author_stats_novels"} <strong>{$totalWorks}</strong></span>
                    <span><i class="fas fa-file-alt"></i> {t name="author_stats_words"} <strong>{$totalWords|number_format}</strong></span>
                    <span><i class="fas fa-eye"></i> {t name="author_stats_views"} <strong>{$totalViews|number_format}</strong></span>
                    <span><i class="fas fa-calendar-alt"></i> {t name="author_join_date"} {$author.created_at|date_format:'Y-m-d'}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="filter-section works-section">
        <div class="works-section-header">
            <i class="fas fa-book"></i> {t name="author_works"}
            <span class="works-section-count">{$totalWorks}</span>
        </div>

        <div class="novel-list-container list-view" id="novelContainer">
            {if $novels}
                {foreach $novels as $n}
                <div class="novel-item">
                    <div class="novel-cover">
                        <a href="{url route="novel" id=$n.id}">
                            <img src="/{$n.cover|default:'assets/images/default_cover.jpg'}" alt="{$n.title}">
                        </a>
                        {if $n.is_vip}<span class="vip-badge">VIP</span>{/if}
                    </div>
                    <div class="novel-info">
                        <h3 class="novel-title">
                            <a href="{url route="novel" id=$n.id}">{$n.title}</a>
                        </h3>
                        <p class="novel-author">
                            {if $n.category_name}<span class="an-cat">{$n.category_name}</span>{/if}
                        </p>
                        <div class="novel-meta">
                            <span><i class="fas fa-eye"></i> {$n.views|number_format}</span>
                            <span><i class="fas fa-file-alt"></i> {$n.total_words|number_format} {t name="words_unit"}</span>
                            <span class="status-badge {if $n.status == 0}ongoing{else}finished{/if}">
                                {if $n.status == 0}{t name="ongoing"}{else}{t name="finished"}{/if}
                            </span>
                        </div>
                        {if $n.last_chapter_title}
                        <p class="novel-latest">
                            <i class="fas fa-book-open"></i> {t name="latest_chapter_label"}：
                            <a href="{url route="read" novel=$n.id chapter=$n.last_chapter_id}">{$n.last_chapter_title}</a>
                            {if $n.last_chapter_is_vip}<span class="vip-tag small">VIP</span>{/if}
                        </p>
                        {/if}
                        {if $n.description}
                        <p class="novel-desc">{$n.description|truncate:100}</p>
                        {/if}
                    </div>
                </div>
                {/foreach}
            {else}
                <div class="empty-state"><i class="fas fa-book-open"></i><p>{t name="author_no_novels"}</p></div>
            {/if}
        </div>

        {if $totalPages > 1}
        <div class="pagination">
            {pagination page=$page totalPages=$totalPages urlTpl=$pageUrlTpl}
        </div>
        {/if}
    </div>
</div>
{include file="footer.tpl"}
