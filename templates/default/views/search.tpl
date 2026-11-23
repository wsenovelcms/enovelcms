{include file="header.tpl"}
<div class="container">
    <div class="home-card">
        <h3 class="page-card-title">
            <i class="fas fa-search"></i> {t name="search"}
            {if $keyword != '' && $list}
            <span class="page-card-sub">（{'found_total'|t|sprintf:$total}，{t name="keyword_label"}：「{$keyword}」）</span>
            {else}
            <span class="page-card-sub">（{t name="search_tip"}）</span>
            {/if}
        </h3>
        {if $keyword != ''}
            {if $list}
            <div style="display:flex;flex-direction:column;gap:12px;padding:16px;">
                {foreach $list as $novel}
                <div style="display:flex;gap:16px;background:#fafafa;border-radius:8px;padding:14px;align-items:flex-start;">
                    <div style="flex:0 0:60px;">
                        <a href="{url route="novel" id=$novel.id}">
                            <img src="/{$novel.cover|default:'assets/images/default_cover.jpg'}" alt="{$novel.title}" loading="lazy" style="width:60px;height:80px;object-fit:cover;border-radius:6px;">
                        </a>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:16px;font-weight:600;margin-bottom:4px;">
                            <a href="{url route="novel" id=$novel.id}" style="color:#1a1a1a;text-decoration:none;">{$novel.title}</a>
                            {if $novel.is_vip}<span style="display:inline-block;font-size:11px;padding:1px 6px;border-radius:4px;margin-left:6px;background:#fef3c7;color:#b45309;vertical-align:middle;">VIP</span>{/if}
                            {if $novel.status}<span style="display:inline-block;font-size:11px;padding:1px 6px;border-radius:4px;margin-left:6px;background:#d1fae5;color:#065f46;vertical-align:middle;">{t name="finished"}</span>{/if}
                        </div>
                        <div style="font-size:12px;color:#888;display:flex;gap:16px;margin-bottom:6px;">
                            <span><i class="fas fa-user"></i> {$novel.display_name}</span>
                            <span><i class="fas fa-file-alt"></i> {$novel.total_words|number_format} {t name="words_unit"}</span>
                            <span><i class="fas fa-eye"></i> {$novel.views|number_format} {t name="read_count_label"}</span>
                        </div>
                        <div style="font-size:13px;color:#666;line-height:1.5;margin-bottom:6px;">{$novel.description|truncate:80}</div>
                        {if $novel.last_chapter_title}
                        <div style="font-size:12px;color:#999;">{t name="latest_prefix"}<a href="{url route="novel" id=$novel.id}" style="color:#b8860b;text-decoration:none;">{$novel.last_chapter_title}</a></div>
                        {/if}
                    </div>
                </div>
                {/foreach}
            </div>
            {if $totalPages > 1}
            <div style="padding:0 16px 16px;">
                {pagination page=$page totalPages=$totalPages urlTpl="?keyword={$keyword|escape:'url'}&page=@@PAGE@@"}
            </div>
            {/if}
            {else}
            <div style="text-align:center;padding:40px;color:#999;">{t name="no_search_results"}</div>
            {/if}
        {else}
        <div style="text-align:center;padding:40px;color:#999;">{t name="search_book_tip"}</div>
        {/if}
    </div>
    {ad name="rank_top"}
</div>
{include file="footer.tpl"}
