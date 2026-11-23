{include file="header.tpl"}
<div class="user-center-layout">
    {include file="user/_sidebar.tpl"}
    <div class="user-content">
        <h2><i class="fas fa-plus-circle"></i> {t name="recharge_point"}</h2>
        {if $error}
        <div data-ui-toast data-type="error" data-msg="{$error}" style="display:none"></div>
        {/if}

        <div class="recharge-summary-bar">
            <div class="recharge-summary-item">
                <span class="rs-label">{t name="current_point_label"}{$point_name}</span>
                <span class="rs-value gold-tag">{$userGold|number_format}</span>
            </div>
            <div class="recharge-summary-item">
                <span class="rs-label">{t name="gold_exchange_rate"}</span>
                <span class="rs-value">{'rate_line_fmt'|t|sprintf:$rate:$point_name}</span>
            </div>
        </div>

        <div class="page-card">
            <div class="card-title"><i class="fas fa-credit-card"></i> {'point_recharge_fmt'|t|sprintf:$point_name}</div>
            {if empty($gateways)}
                <div data-ui-toast data-type="info" data-msg="{t name="gateway_not_configured"}" style="display:none"></div>
            {else}
            <form id="rechargeForm" method="post" action="{url route="user" action="recharge"}">
                {csrf_field}
                <div class="profile-form-row">
                    <label>{t name="recharge_money_label"}</label>
                    <input type="number" name="money" min="1" step="0.01" placeholder="{t name="recharge_money_placeholder"}" required>
                </div>
                <div class="profile-form-row">
                    <label>{t name="payment_channel"}</label>
                    <select name="gateway">
                        {foreach $gateways as $gw}
                        <option value="{$gw.id}">{$gw.name}</option>
                        {/foreach}
                    </select>
                </div>
                <div class="profile-form-row">
                    <label>{t name="payment_method"}</label>
                    {if count($methods) <= 1}
                        <input type="hidden" name="type" value="{$methods[0].type}">
                        <span><i class="fab {$methods[0].icon}"></i> {$methods[0].name}</span>
                    {else}
                        <select name="type">
                            {foreach $methods as $m}
                            <option value="{$m.type}">{$m.name}</option>
                            {/foreach}
                        </select>
                    {/if}
                </div>
                <div class="profile-actions">
                    <button type="submit" class="btn-primary"><i class="fas fa-credit-card"></i> {t name="pay_now"}</button>
                    <a href="{url route="user"}" class="btn-outline">{t name="back_action"}</a>
                </div>
            </form>
            {/if}
        </div>
    </div>
</div>

<div class="modal-mask ui-modal" id="qrcodeModal" style="display:none">
    <div class="modal-box ui-box">
        <div class="modal-head">
            <h3><i class="fas fa-qrcode"></i> {t name="scan_to_pay"}</h3>
            <button type="button" class="modal-close ui-close" id="qrcodeX" aria-label="{t name="close_word"}">&times;</button>
        </div>
        <div class="modal-body" style="text-align:center;">
            <p id="qrcodeMoney" style="margin:0 0 14px;color:#888;font-size:.85rem;"></p>
            <div id="qrcodeWrap" style="display:inline-block;padding:12px;border:1px solid #ece4d6;border-radius:8px;margin-bottom:14px;"></div>
            <p style="margin:0;color:#b8860b;font-size:.85rem;"><i class="fas fa-spinner fa-spin"></i> {t name="waiting_pay_tip"}</p>
        </div>
        <div class="modal-foot ui-foot">
            <button type="button" class="btn-outline" id="qrcodeClose">{t name="cancel_payment"}</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
window.I18N = {
    submitFailed: '{t name="submit_failed"}',
    networkErrorDetail: '{t name="network_error_detail_fmt"}',
    orderAmountFmt: '{t name="order_amount_fmt"}',
    paySuccess: '{t name="pay_success"}'
};
</script>
{literal}
<script>
(function(){
    var form = document.getElementById('rechargeForm');
    if (!form) return;
    form.addEventListener('submit', function(e){
        e.preventDefault();
        var fd = new FormData(form);
        fetch(form.action, {method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'}})
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (!res.ok) { UI.toast(res.msg || window.I18N.submitFailed, 'error'); return; }
                if (res.redirect_type === 'form') {
                    var div = document.createElement('div');
                    div.innerHTML = res.form_html;
                    document.body.appendChild(div);
                    var f = div.querySelector('form');
                    if (f) f.submit();
                } else if (res.redirect_type === 'redirect') {
                    location.href = res.redirect_url;
                } else if (res.redirect_type === 'qrcode') {
                    showQrcode(res.qrcode_url, res.trade_no, res.money);
                }
            })
            .catch(function(err){ UI.toast(window.I18N.networkErrorDetail.replace('%s', err.message), 'error'); });
    });

    var modal = document.getElementById('qrcodeModal');
    var wrap  = document.getElementById('qrcodeWrap');
    var pollTimer = null;

    function showQrcode(url, tradeNo, money){
        wrap.innerHTML = '';
        new QRCode(wrap, {text: url, width: 200, height: 200});
        document.getElementById('qrcodeMoney').textContent = window.I18N.orderAmountFmt.replace('%s', tradeNo).replace('%s', money);
        modal.style.display = 'flex';
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(function(){
            fetch('/api/order_status?trade_no=' + encodeURIComponent(tradeNo), {headers:{'X-Requested-With':'XMLHttpRequest'}})
                .then(function(r){ return r.json(); })
                .then(function(res){
                    if (res.code === 0 && res.data.paid) {
                        clearInterval(pollTimer);
                        modal.style.display = 'none';
                        UI.toast(window.I18N.paySuccess, 'success');
                        setTimeout(function(){ location.reload(); }, 1200);
                    }
                });
        }, 2500);
    }

    function closeQrcode(){
        modal.style.display = 'none';
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    }
    document.getElementById('qrcodeClose').addEventListener('click', closeQrcode);
    document.getElementById('qrcodeX').addEventListener('click', closeQrcode);
    modal.addEventListener('click', function(e){ if (e.target === modal) closeQrcode(); });
})();
</script>
{/literal}
{include file="footer.tpl"}
