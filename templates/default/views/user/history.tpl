{include file="header.tpl"}
<div class="user-center-layout">
    {include file="user/_sidebar.tpl"}
    <div class="user-content">
        <h2>
            <i class="fas fa-history"></i> {t name="history"}
            {if $total}<span class="history-count">{$total}</span>{/if}
        </h2>
        {if $list}
        <div class="page-card">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{t name="novel"}</th>
                        <th>{t name="chapter"}</th>
                        <th>{t name="read_time"}</th>
                    </tr>
                </thead>
                <tbody>
                {foreach $list as $h}
                    <tr>
                        <td>
                            <a href="{url route="novel" id=$h.novel_id}" target="_blank" rel="noopener noreferrer">《{$h.novel_title}》</a>
                        </td>
                        <td>
                            <a href="{url route="read" novel=$h.novel_id chapter=$h.chapter_id}" target="_blank" rel="noopener noreferrer">{$h.chapter_title}</a>
                        </td>
                        <td>{$h.read_at|date_format:'Y-m-d H:i'}</td>
                    </tr>
                {/foreach}
                </tbody>
            </table>
        </div>
        {if $totalPages > 1}
        <div class="pagination">
            {if $page > 1}<a href="?page={$page - 1}" class="page-link"><i class="fas fa-chevron-left"></i></a>{/if}
            <span class="page-ellipsis">{$page} / {$totalPages}</span>
            {if $page < $totalPages}<a href="?page={$page + 1}" class="page-link"><i class="fas fa-chevron-right"></i></a>{/if}
        </div>
        {/if}
        </div>
        {else}
        <div class="empty-state">
            <i class="fas fa-clock"></i>
            <p>{t name="empty_history"}</p>
        </div>
        {/if}
    </div>
</div>
{include file="footer.tpl"}
