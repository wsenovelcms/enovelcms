<?php

class CommonService
{

    public static function formatNumberShort($num): string
    {
        if (!is_numeric($num)) return (string)$num;
        $num = (float)$num;

        if ($num >= 100000000) {
            $val = $num / 100000000;
            $unit = Language::get('number_unit_yi', '亿');
        } elseif ($num >= 10000) {
            $val = $num / 10000;
            $unit = Language::get('number_unit_wan', '万');
        } elseif ($num >= 1000) {
            $val = $num / 1000;
            $unit = Language::get('number_unit_qian', '千');
        } else {
            return number_format($num, 0);
        }
        $formatted = rtrim(rtrim(number_format($val, 3, '.', ''), '0'), '.');
        return $formatted . $unit;
    }

    public static function formatTimeAgo($time): string
    {
        if (!$time) return '';
        $diff = time() - strtotime((string)$time);
        if ($diff < 60) return Language::get('time_just_now', '刚刚');
        if ($diff < 3600) return Language::get('time_minutes_ago', '%d分钟前', floor($diff / 60));
        if ($diff < 86400) return Language::get('time_hours_ago', '%d小时前', floor($diff / 3600));
        if ($diff < 2592000) return Language::get('time_days_ago', '%d天前', floor($diff / 86400));
        return date('Y-m-d', strtotime((string)$time));
    }

    public static function isSpider(): bool
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $spiders = ['bot', 'spider', 'crawl', 'baidu', 'google', 'bing', 'yahoo', 'yandex',
                    'facebookexternalhit', 'twitterbot', 'slackbot', 'bytespider', 'sogou'];
        foreach ($spiders as $s) {
            if (stripos($ua, $s) !== false) return true;
        }
        return false;
    }

    public static function recordVisit(): void
    {
        try {
            StatsModel::record(date('Y-m-d'), self::isSpider());
        } catch (\Throwable $e) {  }
    }

    public static function addGold(int $userId, int $change, string $type,
                                   string $remark = '', int $novelId = 0, int $chapterId = 0): bool
    {
        if ($change === 0) return true;

        if ($change < 0) {
            $affected = UserModel::db()->query(
                'UPDATE ' . tb('users') . ' SET gold = gold + ? WHERE id = ? AND gold >= ?',
                [$change, $userId, -$change]
            )->rowCount();
            if ($affected < 1) return false;
        } else {
            UserModel::increment('gold', $change, ['id' => $userId]);
        }

        $user = UserModel::findById($userId, 'gold');
        if (!$user) return false;

        GoldLogModel::create([
            'user_id'     => $userId,
            'type'        => $type,
            'gold_change' => $change,
            'gold_after'  => (int)$user['gold'],
            'remark'      => $remark,
            'novel_id'    => $novelId,
            'chapter_id'  => $chapterId,
        ]);
        return true;
    }

    public static function pointName(): string
    {
        return (string)SettingModel::get('point_name', Language::get('points_default_name', '金币'));
    }

    public static function rechargeRate(): int
    {
        return (int)SettingModel::get('recharge_exchange_rate', 10);
    }

    public static function currencyName(): string
    {
        return (string)SettingModel::get('currency_name', '元');
    }

    public static function currencySymbol(): string
    {
        return (string)SettingModel::get('currency_symbol', '¥');
    }

    public static function toCny(float $coins): float
    {
        $rate = self::rechargeRate();
        if ($rate <= 0) $rate = 1;
        return round($coins / $rate, 2);
    }

    public static function smartWordCount(string $content): int
    {
        $cjk = preg_match_all('/[\x{4e00}-\x{9fff}\x{3400}-\x{4dbf}]/u', $content);
        $words = preg_match_all('/[A-Za-z]+/', $content);
        return (int)$cjk + (int)$words;
    }

    public static function cleanPlainText(string $text): string
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return trim($text);
    }

    public static function htmlToPlainText(string $html): string
    {
        if ($html === '') return '';
        $text = preg_replace('#<br\s*/?>#i', "\n", $html);
        $text = preg_replace('#</(p|div|li|h[1-6]|blockquote|section|article)>#i', "\n\n", $text);
        $text = preg_replace('#<(p|div|li|h[1-6]|blockquote)(\s[^>]*)?>#i', '', $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xC2\xA0", ' ', $text);
        return self::cleanPlainText($text);
    }

    public static function sanitizeRichText(string $html): string
    {
        $html = preg_replace('/^\xEF\xBB\xBF/', '', $html);
        $html = str_replace(["\r\n", "\r"], "\n", $html);
        $html = preg_replace('#<(script|style|iframe|object|embed|form|input|textarea|select|button|meta|link)\b[^>]*>.*?</\1>#is', '', $html);
        $html = preg_replace('#<(script|style|iframe|object|embed|form|input|textarea|select|button|meta|link)\b[^>]*/?>#i', '', $html);
        $html = preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html);
        $html = preg_replace('#(href|src)\s*=\s*("[^"]*javascript:[^"]*"|\'[^\']*javascript:[^\']*\')#i', '', $html);
        $html = strip_tags($html, '<p><br><b><strong><i><em><u><h2><h3><blockquote><ul><ol><li>');
        $html = preg_replace_callback('#<(p|h2|h3|blockquote|ul|ol|li|br)\b([^>]*)>#i', function ($m) {
            $tag = strtolower($m[1]);
            $attr = '';
            if ($tag === 'p' && preg_match('#style\s*=\s*"([^"]*)"#i', $m[2], $sm) && strpos($sm[1], 'text-indent') !== false) {
                $attr = ' style="text-indent:2em"';
            }
            return '<' . $tag . $attr . '>';
        }, $html);
        $html = preg_replace('/\n{3,}/', "\n\n", $html);
        return trim($html);
    }

    public static function plainTextToHtml(string $text): string
    {
        if ($text === '') return '';
        if (preg_match('#<(p|div|br|h[1-6]|li|blockquote)\b#i', $text)) {
            return $text;
        }
        $blocks = preg_split('/\n{2,}/', trim($text));
        $out = array();
        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') continue;
            $out[] = '<p>' . str_replace("\n", '<br>', htmlspecialchars($block, ENT_QUOTES | ENT_HTML5, 'UTF-8')) . '</p>';
        }
        return implode("\n", $out);
    }
}
