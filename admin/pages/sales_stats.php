<?php

if (!defined('ADMIN_BOOT')) exit;
$pageTitle = t_admin('adm_sales_stats', '销售统计');

$period = g('period', 'all', 'str');
$page = max(1, (int)($_GET['page'] ?? 1));
$result = ChapterPurchaseModel::adminSalesStats($period, $page, 20);
?>
<div class="card">
    <h2><?= t_admin('adm_sales_vip_title', 'VIP章节销售统计') ?></h2>
    <p style="margin-bottom:16px">
        <?php $periodOptions = ['all' => t_admin('adm_sales_all', '全部'), 'today' => t_admin('adm_sales_today', '今日'), 'month' => t_admin('adm_sales_month', '本月'), 'year' => t_admin('adm_sales_year', '本年')];
        foreach ($periodOptions as $key => $label): ?>
        <a href="<?= htmlspecialchars(admin_url('sales_stats', ['period' => $key])) ?>" class="btn btn-sm <?= $period === $key ? 'btn-primary' : 'btn-outline' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </p>
    <div class="alert alert-info"><?= t_admin('adm_sales_summary', '共 %s 笔，合计 <strong class="text-red">%s</strong> 金币', number_format($result['total']), number_format($result['sum'])) ?></div>
    <div class="table-wrap">
    <table class="data">
        <thead><tr><th>ID</th><th><?= t_admin('adm_sales_col_user', '用户') ?></th><th><?= t_admin('adm_sales_col_novel', '小说') ?></th><th><?= t_admin('adm_sales_col_chapter', '章节') ?></th><th><?= t_admin('adm_sales_col_price', '金额') ?></th><th><?= t_admin('adm_sales_col_time', '时间') ?></th><?= PluginTable::renderHead('admin.sales_stats') ?></tr></thead>
        <tbody>
        <?php foreach ($result['list'] as $r): ?>
        <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= htmlspecialchars((string)$r['username'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['novel_title'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['chapter_title'] ?? '-') ?></td>
            <td><?= number_format((int)$r['price']) ?></td>
            <td><?= htmlspecialchars($r['created_at']) ?></td>
        <?= PluginTable::renderCells('admin.sales_stats', $r) ?></tr>
        <?php endforeach; ?>
        <?php if (empty($result['list'])): ?><tr><td colspan="<?= 6 + PluginTable::count('admin.sales_stats') ?>" class="empty"><?= t_admin('adm_sales_empty', '暂无销售记录') ?></td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
    <?= admin_pagination($result['page'], $result['totalPages'], array('action' => 'sales_stats', 'period' => $period)) ?>
</div>
