{include file="header.tpl"}
<div class="container">
    <div class="home-card">
        <h3 class="page-card-title">
            <i class="fas fa-trophy"></i> {t name="rank"}
            <span class="page-card-sub">（TOP {$novels|@count}）</span>
        </h3>
        <div style="padding:16px;border-bottom:1px solid #f0f0f0;display:flex;gap:10px;flex-wrap:wrap;">
            {foreach $typeLabels as $tv => $tl}
            <a class="filter-option {if $type == $tv}active{/if}" href="{url route="rank" type=$tv}" style="padding:6px 16px;border-radius:20px;text-decoration:none;font-size:13px;{if $type == $tv}background:#b8860b;color:#fff;{else}background:#f5f5f5;color:#666;{/if}">{$tl}</a>
            {/foreach}
            {hook name="rank_tabs"}
        </div>
        {hook name="rank_before_list"}
        <div class="rank-list-m">
            {if $novels}
                {foreach $novels as $idx => $n}
                <div class="rank-item-m">
                    <span class="rank-pos-m {if $idx < 3}top{/if}">{$idx+1}</span>
                    <a href="{url route="novel" id=$n.id}" class="rank-cover-m">
                        <img src="/{$n.cover|default:'assets/images/default_cover.jpg'}" alt="{$n.title}" loading="lazy">
                    </a>
                    <div class="rank-info-m">
                        <div class="rank-title-m">
                            <a href="{url route="novel" id=$n.id}">{$n.title}</a>
                            {if $n.is_vip}<span class="vip-badge">{t name="vip_label"}</span>{/if}
                            {if $n.status == 0}<span class="status-badge ongoing">{t name="ongoing"}</span>{else}<span class="status-badge finished">{t name="finished"}</span>{/if}
                        </div>
                        <div class="rank-meta-m">
                            <span><i class="fas fa-user"></i> {$n.display_name}</span>
                            <span><i class="fas fa-eye"></i> {$n.views|number_format}</span>
                            <span><i class="fas fa-file-alt"></i> {$n.total_words|number_format} {t name="words_unit"}</span>
                            <span><i class="fas fa-bookmark"></i> {$n.favorites|number_format}</span>
                        </div>
                        {if $n.last_chapter_title}
                        <div class="rank-chapter-m">{t name="latest_prefix"}<a href="{url route="novel" id=$n.id}">{$n.last_chapter_title}</a></div>
                        {/if}
                        {hook name="rank_item" item=$n}
                    </div>
                </div>
                {/foreach}
            {else}
            <div class="rank-empty-m">{t name="no_data"}</div>
            {/if}
        </div>
        {hook name="rank_after_list"}
    </div>
    {ad name="rank_top"}
</div>
{include file="footer.tpl"}
