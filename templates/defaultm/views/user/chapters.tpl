{include file="header.tpl"}
<div class="user-center-layout">
    {include file="user/_sidebar.tpl"}
    <div class="user-content">
        <h2><i class="fas fa-book-open"></i> {t name="purchased_chapters_title"}</h2>
        <div class="page-card">
        {if $list}
        <table class="data-table">
            <thead><tr>
                <th>{t name="novel"}</th>
                <th>{t name="chapter"}</th>
                <th>{t name="spend_label"}{$point_name}</th>
                <th>{t name="purchase_time"}</th>
                <th>{t name="operation"}</th>
            </tr></thead>
            <tbody>
            {foreach $list as $p}
            <tr>
                <td>{if $p.novel_title}{$p.novel_title}{else}{t name="deleted_label"}{/if}</td>
                <td>{if $p.chapter_title}{$p.chapter_title}{else}{t name="deleted_label"}{/if}</td>
                <td class="text-success">{$p.price} {$point_name}</td>
                <td>{$p.created_at|date_format:'Y-m-d H:i'}</td>
                <td><a href="{url route="read" novel=$p.novel_id chapter=$p.chapter_id}" class="action-link"><i class="fas fa-book-reader"></i> {t name="go_read"}</a></td>
            </tr>
            {/foreach}
            </tbody>
        </table>
        {if $totalPages > 1}
        <div class="pagination">
            {pagination page=$page totalPages=$totalPages urlTpl=$pageUrlTpl}
        </div>
        {/if}
        {else}
        <div class="empty-state"><i class="fas fa-book-open"></i><p>{t name="no_purchased_chapters"}</p></div>
        {/if}
        </div>
    </div>
</div>
{include file="footer.tpl"}
