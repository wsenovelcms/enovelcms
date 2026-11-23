<?php

if (!defined('ADMIN_BOOT')) exit;

function admin_pagination(int $page, int $totalPages, array $query = array()): string
{
    if ($totalPages <= 1) return '';
    $urlFor = function ($p) use ($query) {
        $q = $query;
        $q['page'] = $p;
        return admin_url('', $q);
    };
    $start = max(1, $page - 5);
    $end = min($totalPages, $page + 5);

    $html = '<nav class="pagination">';
    if ($page > 1) {
        $html .= '<a class="page-link" href="' . htmlspecialchars($urlFor(1)) . '">' . t_admin('adm_pg_first', '首页') . '</a>';
        $html .= '<a class="page-link" href="' . htmlspecialchars($urlFor($page - 1)) . '">' . t_admin('adm_pg_prev', '上一页') . '</a>';
    } else {
        $html .= '<span class="page-link disabled">' . t_admin('adm_pg_first', '首页') . '</span>';
        $html .= '<span class="page-link disabled">' . t_admin('adm_pg_prev', '上一页') . '</span>';
    }
    if ($start > 1) $html .= '<span class="page-ellipsis">…</span>';
    for ($i = $start; $i <= $end; $i++) {
        if ($i === $page) {
            $html .= '<span class="page-link active">' . $i . '</span>';
        } else {
            $html .= '<a class="page-link" href="' . htmlspecialchars($urlFor($i)) . '">' . $i . '</a>';
        }
    }
    if ($end < $totalPages) $html .= '<span class="page-ellipsis">…</span>';
    if ($page < $totalPages) {
        $html .= '<a class="page-link" href="' . htmlspecialchars($urlFor($page + 1)) . '">' . t_admin('adm_pg_next', '下一页') . '</a>';
        $html .= '<a class="page-link" href="' . htmlspecialchars($urlFor($totalPages)) . '">' . t_admin('adm_pg_last', '尾页') . '</a>';
    } else {
        $html .= '<span class="page-link disabled">' . t_admin('adm_pg_next', '下一页') . '</span>';
        $html .= '<span class="page-link disabled">' . t_admin('adm_pg_last', '尾页') . '</span>';
    }
    $q = $query; $q['page'] = '__PAGE__';
    $safeUrl = admin_url('', $q);
    $html .= '<span class="page-jump">' . t_admin('adm_pg_jump', '跳至') . ' <input type="number" min="1" max="' . $totalPages . '" value="' . $page . '" data-url="' . htmlspecialchars($safeUrl, ENT_QUOTES) . '" onchange="var p=parseInt(this.value);if(p>=1&&p<=' . $totalPages . ')location.href=this.dataset.url.replace(/__PAGE__/g,p);"> / ' . $totalPages . ' ' . t_admin('adm_pg_page', '页') . '</span>';
    $html .= '</nav>';
    return $html;
}

function admin_flash_redirect(string $message, string $action, array $extra = array()): void
{
    $_SESSION['admin_flash'] = $message;
    header('Location: ' . admin_url($action, $extra));
    exit;
}
