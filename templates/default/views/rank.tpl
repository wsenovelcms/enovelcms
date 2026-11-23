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
        <div style="display:flex;flex-direction:column;gap:12px;padding:16px;">
            {if $novels}
                {foreach $novels as $idx => $n}
                <div style="display:flex;gap:14px;background:#fafafa;border-radius:8px;padding:14px;align-items:flex-start;">
                    <div style="flex:0 0:30px;text-align:center;font-size:20px;font-weight:700;color:{if $idx < 3}#b8860b{else}#ccc{/if};">{$idx+1}</div>
                    <div style="flex:0 0:60px;">
                        <a href="{url route="novel" id=$n.id}">
                            <img src="/{$n.cover|default:'assets/images/default_cover.jpg'}" alt="{$n.title}" loading="lazy" style="width:60px;height:80px;object-fit:cover;border-radius:6px;">
                        </a>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:16px;font-weight:600;margin-bottom:4px;">
                            <a href="{url route="novel" id=$n.id}" style="color:#1a1a1a;text-decoration:none;">{$n.title}</a>
                            {if $n.is_vip}<span style="display:inline-block;font-size:11px;padding:1px 6px;border-radius:4px;margin-left:6px;background:#fef3c7;color:#b45309;vertical-align:middle;">VIP</span>{/if}
                            {if $n.status == 0}<span style="display:inline-block;font-size:11px;padding:1px 6px;border-radius:4px;margin-left:6px;background:#fef3c7;color:#b45309;vertical-align:middle;">{t name="ongoing"}</span>{else}<span style="display:inline-block;font-size:11px;padding:1px 6px;border-radius:4px;margin-left:6px;background:#d1fae5;color:#065f46;vertical-align:middle;">{t name="finished"}</span>{/if}
                        </div>
                        <div style="font-size:12px;color:#888;display:flex;gap:16px;margin-bottom:6px;flex-wrap:wrap;">
                            <span><i class="fas fa-user"></i> {$n.display_name}</span>
                            <span><i class="fas fa-eye"></i> {$n.views|number_format}</span>
                            <span><i class="fas fa-file-alt"></i> {$n.total_words|number_format} {t name="words_unit"}</span>
                            <span><i class="fas fa-bookmark"></i> {$n.favorites|number_format} {t name="favorites"}</span>
                        </div>
                        {if $n.last_chapter_title}
                        <div style="font-size:12px;color:#999;margin-bottom:4px;">{t name="latest_prefix"}<a href="{url route="novel" id=$n.id}" style="color:#b8860b;text-decoration:none;">{$n.last_chapter_title}</a></div>
                        {/if}
                        {if $n.description}
                        <div style="font-size:13px;color:#666;line-height:1.5;">{$n.description|strip_tags|truncate:100}</div>
                        {/if}
                        {hook name="rank_item" item=$n}
                    </div>
                </div>
                {/foreach}
            {else}
            <div style="text-align:center;padding:40px;color:#999;">{t name="no_data"}</div>
            {/if}
        </div>
        {hook name="rank_after_list"}
    </div>
    {ad name="rank_top"}
</div>
{include file="footer.tpl"}
