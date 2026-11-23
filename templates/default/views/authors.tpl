{include file="header.tpl"}
<div class="container">
    <div class="home-card author-list-card">
        <h3 class="page-card-title"><i class="fas fa-users"></i> {t name="author"} <span class="page-card-sub">（{t name="total"} <strong style="color:#b8860b;">{$total}</strong> {t name="authors"}）</span></h3>
        <div class="author-card-grid">
            {foreach $list as $a}
            <a href="{url route="author" id=$a.id}" class="author-card-item">
                <div class="author-card-avatar">
                    <img src="{$a.avatar|default:'/assets/images/default_avatar.png'}" alt="{$a.pen_name|default:$a.username}">
                </div>
                <div class="author-card-body">
                    <h3>{$a.pen_name|default:$a.username}</h3>
                    <p class="author-card-sign">{if $a.signature}{$a.signature|truncate:60}{else}{t name="no_signature"}{/if}</p>
                    <div class="author-card-meta">
                        <span><i class="fas fa-book"></i> {$a.work_count|default:0} {t name="novel"}</span>
                        <span><i class="fas fa-file-alt"></i> {$a.total_words|default:0|number_format} {t name="words_unit"}</span>
                    </div>
                </div>
            </a>
            {hook name="authors_item" item=$a}
            {foreachelse}
            <div class="empty-state"><i class="fas fa-user-slash"></i><p>{t name="no_authors"}</p></div>
            {/foreach}
        </div>
    </div>

    {if $totalPages > 1}
    <div class="pagination">
        {pagination page=$page totalPages=$totalPages urlTpl=$pageUrlTpl}
    </div>
    {/if}
</div>
{include file="footer.tpl"}
