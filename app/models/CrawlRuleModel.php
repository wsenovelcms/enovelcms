<?php

class CrawlRuleModel extends Model
{
    protected static string $table = 'crawl_rules';

    public static function findByKey(string $key): ?array
    {
        return self::find(['rule_key' => $key]);
    }

    public static function getActive(): array
    {
        return self::all(['status' => 1], 'id DESC');
    }

    public static function getAll(): array
    {
        return self::all(array(), 'id DESC');
    }

    public static function getActiveOptions(): array
    {
        return self::all(['status' => 1], 'id ASC', 0, 0, 'id, name, rule_key');
    }

    public static function addRule(string $ruleKey, string $name, string $siteUrl,
                                  string $charset, string $configJson, int $status): int
    {
        return self::create(array(
            'rule_key' => $ruleKey,
            'name' => $name,
            'site_url' => $siteUrl,
            'charset' => $charset,
            'config' => $configJson,
            'status' => $status,
            'created_at' => date('Y-m-d H:i:s'),
        ));
    }

    public static function updateRule(int $id, string $ruleKey, string $name, string $siteUrl,
                                      string $charset, string $configJson, int $status): int
    {
        return self::updateById(array(
            'rule_key' => $ruleKey,
            'name' => $name,
            'site_url' => $siteUrl,
            'charset' => $charset,
            'config' => $configJson,
            'status' => $status,
        ), $id);
    }

    public static function updateByKey(string $ruleKey, string $name, string $siteUrl,
                                       string $charset, string $configJson, int $status): int
    {
        return self::update(array(
            'name' => $name,
            'site_url' => $siteUrl,
            'charset' => $charset,
            'config' => $configJson,
            'status' => $status,
        ), ['rule_key' => $ruleKey]);
    }
}
