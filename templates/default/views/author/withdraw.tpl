{include file="header.tpl"}
<div class="user-center-layout">
    {include file="user/_sidebar.tpl"}
    <div class="user-content">
        <h2><i class="fas fa-hand-holding-usd"></i> {t name="withdraw_title"}</h2>
        {if $withdrawResult}<div data-ui-toast data-type="{if $withdrawResult.ok}success{else}error{/if}" data-msg="{$withdrawResult.msg}" style="display:none"></div>{/if}
        {if $withdrawInfoResult}<div data-ui-toast data-type="{if $withdrawInfoResult.ok}success{else}error{/if}" data-msg="{$withdrawInfoResult.msg}" data-delay="600" style="display:none"></div>{/if}

        <div class="stat-row" style="grid-template-columns: repeat(3, 1fr);">
            <div class="stat-card">
                <div class="num">{$user.available_income|number_format:2}</div>
                <div class="label">{t name="available_balance"}({t name="book_coin"})</div>
            </div>
            <div class="stat-card">
                <div class="num">{$feeRate}%</div>
                <div class="label">{t name="fee_rate_label"}</div>
            </div>
            <div class="stat-card">
                <div class="num">{$minAmount}</div>
                <div class="label">{t name="min_withdraw_label"}({t name="book_coin"})</div>
            </div>
        </div>

        <div class="page-card">
            <h3 class="card-title"><i class="fas fa-credit-card"></i> {t name="payment_info_title"}</h3>
            <form method="post" enctype="multipart/form-data" action="{url route="authorcenter" action="withdraw"}">
                {csrf_field}
                <input type="hidden" name="save_info" value="1">
                <div class="profile-form-row">
                    <label>{t name="real_name_label"}</label>
                    <input type="text" name="real_name" value="{$info.real_name}" readonly style="background:#f5f5f5;color:#666;">
                </div>
                <div class="profile-form-row">
                    <label>{t name="payment_method"}<span style="color:#a83232;"> *</span></label>
                    <select name="account_type" id="accountType" required>
                        <option value="alipay"{if $info.account_type == 'alipay'} selected{/if}>{t name="alipay_label"}</option>
                        <option value="wechat"{if $info.account_type == 'wechat'} selected{/if}>{t name="wechat_label"}</option>
                    </select>
                </div>
                <div class="profile-form-row">
                    <label id="accountLabel">{if $info.account_type == 'wechat'}{t name="wechat_account"}{else}{t name="alipay_account"}{/if}<span style="color:#a83232;"> *</span></label>
                    <input type="text" name="account" id="accountInput" value="{$info.account}" data-alipay="{$info.alipay_account|escape:'html'}" data-wechat="{$info.wechat_account|escape:'html'}" required>
                </div>
                <div class="profile-form-row">
                    <label>{t name="qrcode_upload_label"}</label>
                    <div>
                        <input type="file" id="qrcodeFile" accept="image/jpeg,image/png,image/webp,image/gif">
                        <input type="hidden" name="qrcode_content" id="qrcodeContent" value="">
                        <p id="qrcodeStatus" style="margin:6px 0 0;font-size:0.82rem;color:#999;">{t name="qrcode_upload_tip"}</p>
                        <input type="hidden" name="existing_qrcode" id="existingQrcode" value="{$info.withdraw_qrcode}">
                        <div id="existingQrBox" style="margin-top:6px;{if !$info.withdraw_qrcode}display:none;{/if}">
                            <img id="existingQrImg" src="/api/payoutqrcode" style="width:120px;border:1px solid #ddd;padding:4px;border-radius:3px;" alt="qrcode">
                        </div>
                    </div>
                </div>
                {if $captcha_enabled}
                <div class="profile-form-row">
                    <label>{t name="captcha_label"}</label>
                    {captcha_field name="captcha" placeholder="{t name="captcha_before_save_payment"}"}
                </div>
                {/if}
                <div class="profile-actions">
                    <button type="submit" class="btn-primary">{t name="save_payment_info"}</button>
                </div>
            </form>
        </div>

        <div class="page-card">
            <h3 class="card-title"><i class="fas fa-money-check-alt"></i> {t name="apply_withdraw_title"}</h3>
            <form method="post" action="{url route="authorcenter" action="withdraw"}">
                {csrf_field}
                <div class="profile-form-row">
                    <label>{t name="withdraw_amount_label"}</label>
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                        <input type="number" name="amount" id="amountInput" step="0.01" min="{$minAmount}" value="{$user.available_income|number_format:2}" required style="max-width:240px;">
                        <span style="color:#999;font-size:0.82rem;">{'withdraw_fee_hint_fmt'|t|sprintf:$minAmount:$feeRate}</span>
                    </div>
                    <div id="cnyPreview" style="grid-column:1 / -1;display:none;margin-top:10px;padding:10px 12px;background:#faf7f2;border-radius:6px;font-size:0.85rem;line-height:1.8;"></div>
                </div>
                {if $captcha_enabled}
                <div class="profile-form-row">
                    <label>{t name="captcha_label"}</label>
                    {captcha_field name="captcha" placeholder="{t name="captcha_before_withdraw"}"}
                </div>
                {/if}
                <div class="profile-actions">
                    <button type="submit" class="btn-primary" {if !$info.real_name || !$info.account}disabled title="{t name="please_complete_payment_info"}"{/if}>{t name="submit_withdraw_action"}</button>
                </div>
            </form>
        </div>

        <div class="page-card">
            <h3 class="card-title"><i class="fas fa-list"></i> {t name="withdraw_records_title"}</h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>{t name="apply_time_column"}</th>
                        <th>{t name="amount_column_coin"}</th>
                        <th>{t name="fee_column_cny" args=[$currency_name]}</th>
                        <th>{t name="actual_amount_column_cny" args=[$currency_name]}</th>
                        <th>{t name="account_column"}</th>
                        <th>{t name="status_column"}</th>
                    </tr>
                    </thead>
                    <tbody>
                    {foreach $requests as $w}
                    <tr>
                        <td>{$w.created_at}</td>
                        <td>{$w.amount|number_format:2}</td>
                        <td>{($w.fee/$exchange_rate)|number_format:2} {$currency_symbol}</td>
                        <td>{($w.actual_amount/$exchange_rate)|number_format:2} {$currency_symbol}</td>
                        <td>
                            {if $w.account_type == 'alipay'}{t name="alipay_label"}: {$w.account}
                            {elseif $w.account_type == 'wechat'}{t name="wechat_label"}: {$w.account}
                            {else}{$w.account}{/if}
                            {if $w.real_name}<br><small style="color:#999;">{$w.real_name}</small>{/if}
                        </td>
                        <td>{if $w.status == 0}{t name="status_pending_processing"}{elseif $w.status == 1}{t name="settled_label"}{else}{t name="status_rejected_label"}{/if}</td>
                    </tr>
                    {foreachelse}
                    <tr><td colspan="6"><div class="empty-tip"><i class="fas fa-clock"></i><p>{t name="no_withdraw_records"}</p></div></td></tr>
                    {/foreach}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/jsqr.js?v={$smarty.now}"></script>
<script>
(function(){
    var sel = document.getElementById('accountType');
    var lbl = document.getElementById('accountLabel');
    var acc = document.getElementById('accountInput');
    var qrBox = document.getElementById('existingQrBox');
    var qrImg = document.getElementById('existingQrImg');
    if (sel && lbl && acc) {
        sel.addEventListener('change', function(){
            var oldKey = sel.value === 'wechat' ? 'alipay' : 'wechat';
            acc.dataset[oldKey] = acc.value;
            lbl.innerHTML = (sel.value === 'wechat' ? '{t name="wechat_account"}' : '{t name="alipay_account"}') + ' <span style="color:#a83232;"> *</span>';
            acc.value = acc.dataset[sel.value] || '';
            if (qrImg && qrBox && qrBox.style.display !== 'none') qrImg.src = '/api/payoutqrcode?t=' + Date.now();
        });
    }

    
    
    var fileInput = document.getElementById('qrcodeFile');
    var contentInput = document.getElementById('qrcodeContent');
    var status = document.getElementById('qrcodeStatus');
    if (!fileInput || !contentInput || !status) return;

    function setStatus(msg, color) {
        status.textContent = msg;
        status.style.color = color || '#999';
    }

    fileInput.addEventListener('change', function () {
        var file = fileInput.files && fileInput.files[0];
        if (!file) {
            contentInput.value = '';
            setStatus('{t name="qrcode_upload_tip"}');
            return;
        }
        var reader = new FileReader();
        reader.onload = function (ev) {
            var img = new Image();
            img.onload = function () {
                var max = 1000, w = img.width, h = img.height;
                if (Math.max(w, h) > max) { var k = max / Math.max(w, h); w = Math.round(w * k); h = Math.round(h * k); }
                var cv = document.createElement('canvas');
                cv.width = w; cv.height = h;
                var ctx = cv.getContext('2d');
                ctx.drawImage(img, 0, 0, w, h);
                var imgData;
                try { imgData = ctx.getImageData(0, 0, w, h); } catch (e) { setStatus('{t name="img_read_failed"}', '#a83232'); return; }
                var qr = (typeof jsQR === 'function') ? jsQR(imgData.data, w, h) : null;
                if (qr && qr.data) {
                    contentInput.value = qr.data;
                    setStatus('{t name="qrcode_recognized"}', '#2e7d32');
                } else {
                    contentInput.value = '';
                    setStatus('{t name="qrcode_unrecognized"}', '#a83232');
                }
            };
            img.onerror = function () { setStatus('{t name="img_open_failed"}', '#a83232'); };
            img.src = ev.target.result;
        };
        reader.readAsDataURL(file);
    });
})();
</script>
<script>
(function(){
    var amountInput = document.getElementById('amountInput');
    var cnyPreview = document.getElementById('cnyPreview');
    if (!amountInput || !cnyPreview) return;
    var rate = {$exchange_rate};
    var feeRate = {$feeRate};
    var symbol = '{$currency_symbol|escape:'javascript'}';
    function fmt(n){ return (Number(n) || 0).toFixed(2); }
    function updateCny(){
        var v = parseFloat(amountInput.value);
        if (!v || v <= 0 || !rate || rate <= 0) { cnyPreview.innerHTML = ''; cnyPreview.style.display = 'none'; return; }
        var cny = v / rate;
        var arrival = cny * (1 - feeRate / 100);
        cnyPreview.style.display = 'block';
        cnyPreview.innerHTML =
            '<div style="display:flex;justify-content:space-between;align-items:baseline;gap:12px;"><span style="color:#475569;white-space:nowrap;">{t name="est_cny_label" args=[$currency_name]}</span><span style="font-weight:600;color:#1e293b;white-space:nowrap;margin-left:auto;">' + fmt(cny) + ' ' + symbol + '</span></div>' +
            '<div style="display:flex;justify-content:space-between;align-items:baseline;gap:12px;"><span style="color:#475569;white-space:nowrap;">{t name="est_arrival_cny" args=[$currency_name]}</span><span style="font-weight:700;color:#b8860b;white-space:nowrap;margin-left:auto;">' + fmt(arrival) + ' ' + symbol + '</span></div>';
    }
    amountInput.addEventListener('input', updateCny);
    updateCny();
})();
</script>
{include file="footer.tpl"}
