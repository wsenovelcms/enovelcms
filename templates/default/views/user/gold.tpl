{include file="header.tpl"}
<div class="user-center-layout">
    {include file="user/_sidebar.tpl"}
    <div class="user-content">
        <h2><i class="fas fa-coins"></i> {t name="gold_log_title"}</h2>
        <div class="page-card" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
            <p style="margin:0;"><strong>{t name="current_point_label"}{t name="label_separator"}</strong> <span class="gold-tag">{$userGold|number_format}</span></p>
            <a href="{url route="user" action="recharge"}" class="btn-primary" style="padding: 6px 20px; text-decoration:none;"><i class="fas fa-plus-circle"></i> {t name="recharge_action"}</a>
        </div>
        <div class="page-card">
        {if $list}
        <table class="data-table">
            <thead><tr>
                <th>{t name="gold_log_time"}</th>
                <th>{t name="gold_log_type"}</th>
                <th>{t name="gold_log_change"}</th>
                <th>{t name="gold_log_after"}</th>
                <th>{t name="gold_log_remark"}</th>
            </tr></thead>
            <tbody>
            {foreach $list as $g}
            <tr>
                <td>{$g.created_at|date_format:'Y-m-d H:i'}</td>
                <td>{t name="gold_type_{$g.type}" default=$g.type}</td>
                <td class="{if $g.gold_change >= 0}text-success{else}text-danger{/if}">{if $g.gold_change >= 0}+{/if}{$g.gold_change}</td>
                <td>{$g.gold_after}</td>
                <td>{if $g.novel_title && $g.chapter_title}{t name="purchase_novel_prefix"}<a href="{url route="novel" id=$g.novel_id}" target="_blank" rel="noopener">{$g.novel_title}</a>{t name="book_close_bracket"}- <a href="{url route="read" novel=$g.novel_id chapter=$g.chapter_id}" target="_blank" rel="noopener">{$g.chapter_title}</a>{elseif $g.chapter_id && ($g.type === 'purchase_chapter' || $g.type === 'read_vip')}<a href="{url route="read" novel=$g.novel_id chapter=$g.chapter_id}" target="_blank" rel="noopener">{$g.remark}</a>{else}{$g.remark}{/if}</td>
            </tr>
            {/foreach}
            </tbody>
        </table>
        {if $totalPages > 1}
        <div class="pagination">
            {if $page > 1}<a href="?page={$page - 1}" class="page-link"><i class="fas fa-chevron-left"></i></a>{/if}
            <span class="page-ellipsis">{$page} / {$totalPages}</span>
            {if $page < $totalPages}<a href="?page={$page + 1}" class="page-link"><i class="fas fa-chevron-right"></i></a>{/if}
        </div>
        {/if}
        {else}
        <div class="empty-state"><i class="fas fa-receipt"></i><p>{t name="no_records"}</p></div>
        {/if}
        </div>
    </div>
</div>
{include file="footer.tpl"}
