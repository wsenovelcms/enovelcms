<?php

if (!defined('ADMIN_BOOT')) exit;
$pageTitle = t_admin('adm_sstat', '安全统计');

$stats = SecurityLogModel::stats();
$total = SecurityLogModel::count();
$typeLabels = SecurityLogModel::typeLabels();

$days = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} day"));
    $days[$d] = $stats['daily'][$d] ?? 0;
}
$maxDaily = max(1, ...array_values($days));
$maxType = 1;
foreach ($stats['by_type'] as $c) { $maxType = max($maxType, (int)$c); }
?>
<div class="stat-grid" style="grid-template-columns:repeat(4,1fr);gap:10px;">
    <div class="stat-box" style="padding:14px;"><div class="stat-num" style="font-size:22px;"><?= number_format($stats['today']) ?></div><div class="stat-label" style="font-size:12px;"><?= t_admin('adm_sstat_today', '今日拦截') ?></div></div>
    <div class="stat-box" style="padding:14px;"><div class="stat-num" style="font-size:22px;"><?= number_format($stats['last7']) ?></div><div class="stat-label" style="font-size:12px;"><?= t_admin('adm_sstat_last7', '近 7 天拦截') ?></div></div>
    <div class="stat-box" style="padding:14px;"><div class="stat-num" style="font-size:22px;"><?= number_format($stats['unique_ip']) ?></div><div class="stat-label" style="font-size:12px;"><?= t_admin('adm_sstat_unique_ip', '涉及独立 IP') ?></div></div>
    <div class="stat-box" style="padding:14px;"><div class="stat-num" style="font-size:22px;"><?= number_format($total) ?></div><div class="stat-label" style="font-size:12px;"><?= t_admin('adm_sstat_total', '日志总数') ?></div></div>
</div>

<div style="display:grid;grid-template-columns:1fr 1.4fr;gap:16px;margin-top:16px;align-items:stretch;">
    <div class="card" style="padding:0;overflow:hidden;">
        <div style="padding:14px 18px;border-bottom:1px solid var(--border);font-weight:600;font-size:14px;">
            <i class="fas fa-chart-pie" style="color:var(--primary);margin-right:6px;"></i><?= t_admin('adm_sstat_type_dist', '拦截类型分布') ?>
        </div>
        <div style="padding:14px 18px;">
            <?php if (empty($stats['by_type'])): ?>
            <p class="empty" style="padding:20px 0;"><?= t_admin('adm_sstat_empty', '暂无拦截记录') ?></p>
            <?php else: ?>
                <?php foreach ($stats['by_type'] as $type => $count):
                    $label = $typeLabels[$type] ?? $type;
                    $pct = round($count / $maxType * 100);
                ?>
                <div style="margin-bottom:14px;">
                    <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px;">
                        <span><?= htmlspecialchars($label) ?></span>
                        <span style="color:#64748b;"><?= t_admin('adm_sstat_times', '%s 次', number_format($count)) ?></span>
                    </div>
                    <div style="height:8px;background:#f1f5f9;border-radius:6px;overflow:hidden;">
                        <div style="height:100%;width:<?= $pct ?>%;background:var(--primary);border-radius:6px;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="card" style="padding:0;overflow:hidden;">
        <div style="padding:14px 18px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
            <div style="font-weight:600;font-size:14px;">
                <i class="fas fa-chart-line" style="color:var(--primary);margin-right:6px;"></i><?= t_admin('adm_sstat_trend', '近 7 天拦截趋势') ?>
            </div>
            <a href="<?= htmlspecialchars(admin_url('security_logs')) ?>" style="font-size:12.5px;color:var(--primary);text-decoration:none;"><i class="fas fa-list"></i> <?= t_admin('adm_sstat_view_logs', '查看日志') ?></a>
        </div>
        <div style="padding:18px;display:flex;align-items:flex-end;gap:10px;height:220px;">
            <?php foreach ($days as $d => $count): ?>
            <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;height:100%;justify-content:flex-end;">
                <span style="font-size:12px;color:#64748b;"><?= $count > 0 ? number_format($count) : '' ?></span>
                <div style="width:100%;max-width:34px;background:#eff6ff;border-radius:6px 6px 0 0;min-height:2px;height:<?= max(2, round($count / $maxDaily * 150)) ?>px;"></div>
                <span style="font-size:11px;color:#94a3b8;"><?= substr($d, 5) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="card" style="margin-top:16px;">
    <div style="display:flex;justify-content:flex-end;align-items:center;flex-wrap:wrap;gap:10px;">
        <a href="<?= htmlspecialchars(admin_url('settings') . '#system') ?>" class="btn btn-outline btn-sm"><i class="fas fa-gear"></i> <?= t_admin('adm_sstat_guard_settings', '防护设置') ?></a>
    </div>
</div>
