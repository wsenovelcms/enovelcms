<?php

class PaymentGatewayRegistry
{

    private static array $gateways = [];
    private static bool $loaded = false;

    public static function load(): void
    {
        if (self::$loaded) return;
        self::$loaded = true;
        $list = Hook::applyFilters('payment_gateways', []);

        self::$gateways = [];
        foreach ($list as $gw) {
            if ($gw instanceof PaymentGateway) {
                self::$gateways[$gw->getId()] = $gw;
            }
        }
    }

    public static function register(PaymentGateway $gw): void
    {
        self::$gateways[$gw->getId()] = $gw;
    }

    public static function get(string $id): ?PaymentGateway
    {
        self::load();
        return self::$gateways[$id] ?? null;
    }

    public static function all(): array
    {
        self::load();
        $out = [];
        foreach (self::$gateways as $id => $gw) {
            if ($gw->isEnabled()) $out[$id] = $gw;
        }
        return $out;
    }

    public static function default(): ?PaymentGateway
    {
        $all = self::all();
        return $all ? reset($all) : null;
    }
}
