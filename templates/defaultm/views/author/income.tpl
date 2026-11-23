{include file="header.tpl"}
<div class="user-center-layout">
    {include file="user/_sidebar.tpl"}
    <div class="user-content">
        <h2><i class="fas fa-chart-pie"></i> {t name="income_stats_title"}</h2>

        <div class="stat-row" style="grid-template-columns: repeat(4, 1fr);">
            <div class="stat-card">
                <div class="num">{$stats.total_sale|default:0|number_format:2}</div>
                <div class="label">{t name="total_sales"}({t name="book_coin"})</div>
            </div>
            <div class="stat-card">
                <div class="num">{$stats.withdraw|default:0|number_format:2}</div>
                <div class="label">{t name="withdrawn_label"}</div>
            </div>
            <div class="stat-card">
                <div class="num">{$stats.available|default:0|number_format:2}</div>
                <div class="label">{t name="unwithdrawn_label"}</div>
            </div>
            <div class="stat-card">
                <div class="num">{$stats.adjust|default:0|number_format:2}</div>
                <div class="label">{t name="type_adjust"}</div>
            </div>
        </div>

        <div class="page-card">
            <h3 class="card-title"><i class="fas fa-list"></i> {t name="income_detail_title"}</h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>{t name="time_column"}</th>
                        <th>{t name="work_column"}</th>
                        <th>{t name="chapter_column"}</th>
                        <th>{t name="type_column"}</th>
                        <th style="text-align:right;">{t name="amount_column"}</th>
                        <th>{t name="status_column"}</th>
                    </tr>
                    </thead>
                    <tbody>
                    {foreach $logs|default:[] as $item}
                    <tr>
                        <td>{$item.created_at}</td>
                        <td>{if $item.novel_id}<a href="{url route="novel" id=$item.novel_id}" target="_blank">{$item.novel_id}</a>{else}-{/if}</td>
                        <td>{if $item.chapter_id}{$item.chapter_id}{else}-{/if}</td>
                        <td>{if $item.type == 'sale'}{t name="type_sale"}{elseif $item.type == 'adjust'}{t name="type_adjust"}{elseif $item.type == 'withdraw'}{t name="type_withdraw"}{else}{$item.type}{/if}</td>
                        <td style="text-align:right;" class="{if $item.amount >= 0}text-success{else}text-danger{/if}">{$item.amount|default:0|number_format:2}</td>
                        <td>{if $item.status}{t name="settled_label"}{else}{t name="unsettled_label"}{/if}</td>
                    </tr>
                    {foreachelse}
                    <tr><td colspan="6"><div class="empty-tip"><i class="fas fa-receipt"></i><p>{t name="no_income_records"}</p></div></td></tr>
                    {/foreach}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
{include file="footer.tpl"}
