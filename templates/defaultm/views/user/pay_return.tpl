{include file="header.tpl"}
<div class="user-center-layout">
    {include file="user/_sidebar.tpl"}
    <div class="user-content">
        <div class="page-card">
            <div class="card-title"><i class="fas fa-check-circle"></i> {t name="pay_result_title"}</div>
            {if $order && $order.status == 1}
                <div data-ui-toast data-type="success" data-msg="{'recharge_success_toast_fmt'|t|sprintf:$order.money:$order.gold:$point_name}" style="display:none"></div>
                <div style="border:1px solid #ece4d6;border-radius:8px;padding:18px 20px;color:#555;font-size:14px;line-height:1.9;">
                    <p style="margin:0 0 6px;color:#2d6a4f;"><strong><i class="fas fa-check-circle"></i> {t name="recharge_success_title"}</strong></p>
                    <p style="margin:0;">{t name="order_no_label"}{t name="label_separator"}{$order.out_trade_no}</p>
                    <p style="margin:0;">{'recharge_amount_gold_fmt'|t|sprintf:$order.money:$order.gold:$point_name}</p>
                </div>
            {else}
                <div data-ui-toast data-type="info" data-msg="{t name="pay_pending_tip"}" style="display:none"></div>
                <div style="border:1px solid #ece4d6;border-radius:8px;padding:18px 20px;color:#555;font-size:14px;line-height:1.9;">
                    <p style="margin:0 0 6px;"><strong><i class="fas fa-info-circle"></i> {t name="pay_pending_tip"}。</strong></p>
                    {if $order}<p style="margin:0;">{t name="order_no_label"}{t name="label_separator"}{$order.out_trade_no}</p>{/if}
                </div>
            {/if}
            <div class="profile-actions">
                <a href="{url route="user"}" class="btn-primary">{t name="back_to_user_center"}</a>
                <a href="{url route="user" action="recharge"}" class="btn-outline">{t name="continue_recharge"}</a>
            </div>
        </div>
    </div>
</div>
{include file="footer.tpl"}
