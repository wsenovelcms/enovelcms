<?php

if (!defined('ADMIN_BOOT')) exit;
$pageTitle = t_admin('adm_wd', '提现管理');

$authorService = new AuthorService();

if (g('do', null, 'raw') !== null && g('id', null, 'raw') !== null) {
    if (Security::verifyCsrf()) {
        $status = $_GET['do'] === 'approve' ? 1 : 2;
        $res = $authorService->auditWithdraw(g('id', 0, 'int'), $status);
        admin_flash_redirect($res['msg'], 'withdraw');
    }
    admin_flash_redirect(t_admin('adm_security_failed', '安全验证失败'), 'withdraw');
}

$status = g('status', null, 'raw') !== null ? g('status', 0, 'int') : -1;
$page = max(1, (int)($_GET['page'] ?? 1));
$result = WithdrawRequestModel::adminPaginate($status, $page, 20);

$rate = (int)SettingModel::get('recharge_exchange_rate', 10);
if ($rate <= 0) $rate = 1;
$symbol = (string)SettingModel::get('currency_symbol', '¥');
$currencyName = (string)SettingModel::get('currency_name', t_admin('adm_wd_currency_default', '元'));
$toCny = function ($coins) use ($rate) {
    return number_format(round((float)$coins / $rate, 2), 2);
};
?>
<div class="card">
    <h2><?= t_admin('adm_wd_title', '提现申请（共 %s 条）', number_format($result['total'])) ?></h2>
    <form method="get" style="margin-bottom:16px;display:flex;gap:8px;align-items:end">
        <input type="hidden" name="action" value="withdraw">
        <div class="form-group" style="margin:0">
            <label><?= t_admin('adm_wd_col_status', '状态') ?></label>
            <select name="status" class="form-control">
                <option value="-1"><?= t_admin('adm_all', '全部') ?></option>
                <option value="0" <?= $status === 0 ? 'selected' : '' ?>><?= t_admin('adm_wd_pending', '待处理') ?></option>
                <option value="1" <?= $status === 1 ? 'selected' : '' ?>><?= t_admin('adm_wd_passed', '已通过') ?></option>
                <option value="2" <?= $status === 2 ? 'selected' : '' ?>><?= t_admin('adm_wd_rejected', '已拒绝') ?></option>
            </select>
        </div>
        <button class="btn btn-outline" type="submit"><i class="fas fa-filter"></i> <?= t_admin('adm_filter', '筛选') ?></button>
    </form>
    <div class="table-wrap">
    <table class="data">
        <thead><tr><th>ID</th><th><?= t_admin('adm_wd_col_user', '用户') ?></th><th><?= t_admin('adm_wd_col_amount', '金额(书币)') ?></th><th><?= t_admin('adm_wd_col_fee_cny', '手续费(%s)', $currencyName) ?></th><th><?= t_admin('adm_wd_col_arrival_cny', '到账(%s)', $currencyName) ?></th><th><?= t_admin('adm_wd_col_account', '收款信息') ?></th><th><?= t_admin('adm_wd_col_status', '状态') ?></th><th><?= t_admin('adm_wd_col_time', '时间') ?></th><th><?= t_admin('adm_operation', '操作') ?></th><?= PluginTable::renderHead('admin.withdraw') ?></tr></thead>
        <tbody>
        <?php foreach ($result['list'] as $row): ?>
        <tr>
            <td><?= (int)$row['id'] ?></td>
            <td><?= htmlspecialchars((string)$row['username']) ?> (ID:<?= (int)$row['user_id'] ?>)</td>
            <td><?= number_format((float)$row['amount'], 2) ?></td>
            <td><?= $toCny($row['fee']) ?> <?= htmlspecialchars($symbol) ?></td>
            <td><?= $toCny($row['actual_amount']) ?> <?= htmlspecialchars($symbol) ?></td>
            <td style="font-size:12.5px">
                <?= htmlspecialchars((string)$row['real_name']) ?><br>
                <?php if ($row['account_type'] === 'alipay'): ?><i class="fab fa-alipay"></i>
                <?php elseif ($row['account_type'] === 'wechat'): ?><i class="fab fa-weixin"></i><?php endif; ?>
                <?= htmlspecialchars((string)$row['account']) ?>
                <?php if (!empty($row['qrcode'])): ?>
                <?php
                $methodMap = ['alipay' => t_admin('adm_wd_method_alipay', '支付宝'), 'wechat' => t_admin('adm_wd_method_wechat', '微信')];
                $method = $methodMap[$row['account_type']] ?? htmlspecialchars((string)$row['account_type']);
                $arrival = $toCny($row['actual_amount']) . ' ' . htmlspecialchars($symbol);
                $isProcessed = (int)$row['status'] !== 0;
                $notice = $isProcessed
                    ? t_admin('adm_wd_processed_notice_fmt', '该笔 %s 的提现申请可能已经处理过了，请核实后再打款！', $arrival)
                    : t_admin('adm_wd_pay_notice_fmt', '请通过 %s 向作者打款 %s', $method, $arrival);
                ?>
                <div style="margin-top:4px">
                    <button type="button" class="btn btn-sm btn-outline wd-qr-btn" data-qr="<?= htmlspecialchars(admin_url('qr_code', ['user_id' => (int)$row['user_id']])) ?>" data-notice="<?= htmlspecialchars($notice) ?>" data-warn="<?= $isProcessed ? 1 : 0 ?>"><i class="fas fa-qrcode"></i> <?= t_admin('adm_wd_qrcode_view', '查看收款码') ?></button>
                </div>
                <?php endif; ?>
            </td>
            <td><?php
                $badge = array(0 => 'badge-orange', 1 => 'badge-green', 2 => 'badge-red');
                $label = array(0 => t_admin('adm_wd_pending', '待处理'), 1 => t_admin('adm_wd_passed', '已通过'), 2 => t_admin('adm_wd_rejected', '已拒绝'));
                echo '<span class="badge ' . $badge[(int)$row['status']] . '">' . $label[(int)$row['status']] . '</span>';
            ?></td>
            <td><?= htmlspecialchars($row['created_at']) ?></td>
            <td>
                <?php if ((int)$row['status'] === 0): ?>
                <a href="<?= htmlspecialchars(admin_url('withdraw', ['do' => 'approve', 'id' => (int)$row['id'], '_csrf' => Security::csrfToken()])) ?>" class="btn btn-sm btn-green" data-confirm="<?= t_admin('adm_wd_approve_confirm', '确认通过？请线下打款') ?>"><i class="fas fa-check"></i></a>
                <a href="<?= htmlspecialchars(admin_url('withdraw', ['do' => 'reject', 'id' => (int)$row['id'], '_csrf' => Security::csrfToken()])) ?>" class="btn btn-sm btn-danger" data-confirm="<?= t_admin('adm_wd_reject_confirm', '确认拒绝？金额退回') ?>"><i class="fas fa-times"></i></a>
                <?php else: ?><span class="text-muted"><?= t_admin('adm_wd_processed', '已处理') ?></span><?php endif; ?>
                <?php Hook::doAction('admin_withdraw_row_actions', $row); ?>
            </td>
            <?= PluginTable::renderCells('admin.withdraw', $row) ?>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($result['list'])): ?><tr><td colspan="<?= 9 + PluginTable::count('admin.withdraw') ?>" class="empty"><?= t_admin('adm_wd_empty', '暂无提现申请') ?></td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
    <?= admin_pagination($result['page'], $result['totalPages'], array('action' => 'withdraw', 'status' => $status)) ?>
</div>

<div class="modal-mask" id="wdQrModal">
    <div class="modal" style="width:auto;max-width:340px;">
        <div class="modal-head">
            <h3><i class="fas fa-qrcode"></i> <?= t_admin('adm_wd_qrcode_view', '查看收款码') ?></h3>
            <button type="button" class="modal-close" id="wdQrClose" aria-label="<?= t_admin('adm_ui_close', '关闭') ?>">&times;</button>
        </div>
        <div class="modal-body" style="text-align:center;">
            <div id="wdQrNotice" style="display:none;margin-bottom:12px;padding:10px 12px;border-radius:8px;background:#fef2f2;border:1px solid #fecaca;color:#dc2626;font-weight:600;font-size:14px;line-height:1.5;"></div>
            <img id="wdQrImg" src="" alt="qrcode" style="max-width:100%;border-radius:8px;">
        </div>
    </div>
</div>
<script>
(function(){
    var mask = document.getElementById('wdQrModal');
    var img = document.getElementById('wdQrImg');
    var notice = document.getElementById('wdQrNotice');
    var closeBtn = document.getElementById('wdQrClose');
    if (!mask || !img) return;
    function open(src, noticeText, warn){
        img.src = src;
        if (notice && noticeText) {
            notice.textContent = noticeText;
            notice.style.display = 'block';
            var warned = warn === '1';
            notice.style.color = warned ? '#b91c1c' : '#dc2626';
            notice.style.borderColor = warned ? '#dc2626' : '#fecaca';
        }
        mask.classList.add('show');
    }
    function close(){ mask.classList.remove('show'); img.src = ''; if (notice) { notice.textContent = ''; notice.style.display = 'none'; } }
    document.querySelectorAll('.wd-qr-btn').forEach(function(btn){
        btn.addEventListener('click', function(){ open(btn.getAttribute('data-qr'), btn.getAttribute('data-notice'), btn.getAttribute('data-warn')); });
    });
    if (closeBtn) closeBtn.addEventListener('click', close);
    mask.addEventListener('click', function(e){ if (e.target === mask) close(); });
})();
</script>
