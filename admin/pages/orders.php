<?php

if (!defined('ADMIN_BOOT')) exit;
$pageTitle = t_admin('adm_orders', '订单管理');

$keyword = g('kw', '', 'str');
$status = g('status', null, 'raw') !== null ? g('status', 0, 'int') : -1;
$page = max(1, (int)($_GET['page'] ?? 1));
$result = OrderModel::adminPaginate($keyword, $status, $page, 20);
$totalAmount = OrderModel::salesAmount();
?>
<div class="stat-grid">
    <div class="stat-box"><div class="stat-icon icon-green"><i class="fas fa-sack-dollar"></i></div><div class="stat-num"><?= number_format($totalAmount, 2) ?></div><div class="stat-label"><?= t_admin('adm_order_total_paid', '已支付总额（元）') ?></div></div>
    <div class="stat-box"><div class="stat-icon icon-blue"><i class="fas fa-receipt"></i></div><div class="stat-num"><?= number_format($result['total']) ?></div><div class="stat-label"><?= t_admin('adm_order_total_count', '订单总数') ?></div></div>
</div>
<div class="card">
    <h2><?= t_admin('adm_order_list', '订单列表') ?></h2>
    <form method="get" style="margin-bottom:16px;display:flex;gap:8px;align-items:end;flex-wrap:wrap">
        <input type="hidden" name="action" value="orders">
        <div class="form-group" style="margin:0">
            <label><?= t_admin('adm_order_keyword', '关键词') ?></label>
            <input type="text" name="kw" value="<?= htmlspecialchars($keyword) ?>" class="form-control" placeholder="<?= t_admin('adm_order_keyword_ph', '订单号 / 用户名') ?>">
        </div>
        <div class="form-group" style="margin:0">
            <label><?= t_admin('adm_order_col_status', '状态') ?></label>
            <select name="status" class="form-control">
                <option value="-1" <?= $status === -1 ? 'selected' : '' ?>><?= t_admin('adm_all', '全部') ?></option>
                <option value="0" <?= $status === 0 ? 'selected' : '' ?>><?= t_admin('adm_order_pending', '待支付') ?></option>
                <option value="1" <?= $status === 1 ? 'selected' : '' ?>><?= t_admin('adm_order_paid', '已支付') ?></option>
            </select>
        </div>
        <button class="btn btn-outline" type="submit"><i class="fas fa-search"></i> <?= t_admin('adm_filter', '筛选') ?></button>
    </form>
    <div class="table-wrap">
    <table class="data">
        <thead><tr><th>ID</th><th><?= t_admin('adm_order_col_trade_no', '订单号') ?></th><th><?= t_admin('adm_order_col_user', '用户') ?></th><th><?= t_admin('adm_order_col_money', '金额') ?></th><th><?= t_admin('adm_order_col_type', '类型') ?></th><th><?= t_admin('adm_order_col_months', '月数') ?></th><th><?= t_admin('adm_order_col_status', '状态') ?></th><th><?= t_admin('adm_order_col_created', '下单时间') ?></th><th><?= t_admin('adm_order_col_pay_time', '支付时间') ?></th><th><?= t_admin('adm_operation', '操作') ?></th><?= PluginTable::renderHead('admin.orders') ?></tr></thead>
        <tbody>
        <?php foreach ($result['list'] as $o): ?>
        <tr>
            <td><?= (int)$o['id'] ?></td>
            <td><?= htmlspecialchars($o['out_trade_no']) ?></td>
            <td><?= htmlspecialchars((string)$o['username'] ?? ('ID:' . $o['user_id'])) ?></td>
            <td>¥<?= number_format((float)$o['money'], 2) ?></td>
            <td><?= htmlspecialchars((string)$o['type']) ?></td>
            <td><?= (int)$o['months'] ?></td>
            <td><?= $o['status'] ? '<span class="badge badge-green">' . t_admin('adm_order_paid', '已支付') . '</span>' : '<span class="badge badge-red">' . t_admin('adm_order_pending', '待支付') . '</span>' ?></td>
            <td><?= htmlspecialchars($o['created_at']) ?></td>
            <td><?= htmlspecialchars($o['pay_time'] ?: '-') ?></td>
            <td><?php Hook::doAction('admin_orders_row_actions', $o); ?></td>
            <?= PluginTable::renderCells('admin.orders', $o) ?>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($result['list'])): ?><tr><td colspan="<?= 10 + PluginTable::count('admin.orders') ?>" class="empty"><?= t_admin('adm_order_empty', '暂无订单') ?></td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
    <?= admin_pagination($result['page'], $result['totalPages'], array('action' => 'orders', 'kw' => $keyword, 'status' => $status)) ?>
</div>
